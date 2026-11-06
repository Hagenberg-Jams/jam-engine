/**
 * Registration for a jam: validation, seat assignment, storage in D1 and the mails that go with it.
 *
 * The handlers take their dependencies as arguments, so that tests can run them without Cloudflare:
 * `env.JAM_DB` is the D1 database, `deps.fetch` sends HTTP requests and `deps.now` is the time in milliseconds.
 */

const WISHES = ['onsite_only', 'onsite_preferred', 'remote'];
const PLACEMENTS = ['onsite', 'remote', 'waitlist'];
const MAX_PER_HOUR = 5;

const headers = { 'content-type': 'application/json', 'cache-control': 'no-store' };
export const json = (status, body) => new Response(JSON.stringify(body), { status, headers });

const text = (value, max) => (typeof value === 'string' ? value.trim().slice(0, max) : '');

export async function sha256(value) {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value));

    return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

function randomToken() {
    const bytes = crypto.getRandomValues(new Uint8Array(32));

    return btoa(String.fromCharCode(...bytes)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/**
 * Links in mails point to the host the request came from, so that a preview sends preview links.
 */
export function forRequest(request, config) {
    return { ...config, siteUrl: new URL(request.url).origin };
}

export function isOpen(config, now) {
    return config.active && (!config.deadline || now <= Date.parse(config.deadline));
}

/**
 * Checks and cleans the submitted form. Returns the data to store, or a list of errors for the form.
 */
export function validate(input, config) {
    const errors = [];
    const fields = config.fields;
    const data = {
        team_name: text(input.team_name, 80),
        contact_email: text(input.contact_email, 200).toLowerCase(),
        programs: [],
        wish: fields.participation ? input.wish : 'onsite_preferred',
        comment: fields.comment ? text(input.comment, 2000) : '',
        members: [],
    };

    if (data.team_name === '') {
        errors.push('team_name');
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.contact_email)) {
        errors.push('contact_email');
    }

    if (!WISHES.includes(data.wish)) {
        errors.push('wish');
    }

    if (fields.programs) {
        const programs = Array.isArray(input.programs) ? input.programs : [];
        data.programs = config.programs.filter((program) => programs.includes(program));
    }

    const members = Array.isArray(input.members) ? input.members : [];
    members.forEach((member, index) => {
        const entry = {
            real_name: text(member?.real_name, 100),
            nickname: text(member?.nickname, 60),
            show_as: member?.show_as === 'nick' ? 'nick' : 'real',
            discord: fields.discord ? text(member?.discord, 40) : '',
            remote: member?.remote === true || data.wish === 'remote',
        };

        // Empty rows from the form are skipped, half-filled ones are errors.
        if (entry.real_name === '' && entry.nickname === '' && entry.discord === '' && member?.remote !== true) {
            return;
        }

        if (entry.real_name === '') {
            errors.push(`members.${index}.real_name`);
        }

        if (entry.show_as === 'nick' && entry.nickname === '') {
            errors.push(`members.${index}.nickname`);
        }

        data.members.push(entry);
    });

    data.onsite = data.members.filter((member) => !member.remote).length;

    const [min, max] = config.teamSize;
    if (data.members.length < min || data.members.length > max) {
        errors.push('members');
    }

    if (input.consent_privacy !== true) {
        errors.push('consent_privacy');
    }

    if (input.consent_names !== true) {
        errors.push('consent_names');
    }

    return { errors, data };
}

/**
 * The SQL expression that decides where a team goes: from its people on site, the seats left, and its wish
 * for the case that there are not enough seats.
 * Parameters: ?1 wish, ?2 people on site, ?3 capacity (or null), ?4 year, ?5 id of the registration to leave out.
 */
const PLACEMENT_SQL = `CASE
    WHEN ?2 = 0 THEN 'remote'
    WHEN ?3 IS NULL THEN 'onsite'
    WHEN COALESCE((SELECT SUM(onsite_people) FROM registrations
        WHERE year = ?4 AND id != ?5 AND placement = 'onsite' AND status IN ('pending', 'confirmed')), 0) + ?2 <= ?3 THEN 'onsite'
    WHEN ?1 = 'onsite_only' THEN 'waitlist'
    ELSE 'remote'
END`;

function memberStatements(db, id, members) {
    return members.map((member, position) =>
        db.prepare('INSERT INTO members (registration_id, position, real_name, nickname, show_as, discord, remote) VALUES (?, ?, ?, ?, ?, ?, ?)')
            .bind(id, position, member.real_name, member.nickname, member.show_as, member.discord, member.remote ? 1 : 0),
    );
}

export async function seatsTaken(db, year) {
    const row = await db.prepare(
        "SELECT COALESCE(SUM(onsite_people), 0) AS taken FROM registrations WHERE year = ? AND placement = 'onsite' AND status IN ('pending', 'confirmed')",
    ).bind(year).first();

    return Number(row?.taken ?? 0);
}

export async function findRegistration(db, where, value) {
    const registration = await db.prepare(`SELECT * FROM registrations WHERE ${where} = ?`).bind(value).first();

    if (!registration) {
        return null;
    }

    const { results } = await db.prepare('SELECT * FROM members WHERE registration_id = ? ORDER BY position').bind(registration.id).all();

    return {
        ...registration,
        programs: JSON.parse(registration.programs),
        members: results.map((member) => ({ ...member, remote: Number(member.remote) === 1 })),
    };
}

/** What a team sees about itself: everything except internal fields. */
export function publicView(registration) {
    const { edit_token_hash, ip_hash, purge_after, ...rest } = registration;

    return { ...rest, members: registration.members.map(({ registration_id, ...member }) => member) };
}

export function displayName(member) {
    return member.show_as === 'nick' && member.nickname !== '' ? member.nickname : member.real_name;
}

async function verifyTurnstile(deps, env, token, ip) {
    const body = new URLSearchParams({ secret: env.TURNSTILE_SECRET ?? '', response: token ?? '', remoteip: ip ?? '' });
    const response = await deps.fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', { method: 'POST', body });
    const result = await response.json().catch(() => ({}));

    return result.success === true;
}

// Mails

const PLACEMENT_TEXT = {
    onsite: 'Your team has seats on site for everyone who takes part on site.',
    remote: 'Your team takes part remotely.',
    waitlist: 'You are on the waiting list for a seat on site. We let you know as soon as one becomes free.',
};

function teamSummary(registration, config) {
    const lines = [`Team: ${registration.team_name}`, 'Members:'];

    registration.members.forEach((member) => {
        const shown = displayName(member) === member.real_name ? '' : ` (shown as ${member.nickname})`;
        const discord = config.fields.discord && member.discord ? `, Discord: ${member.discord}` : '';
        lines.push(`- ${member.real_name}${shown}${discord}${member.remote ? ', remote' : ''}`);
    });

    return lines.join('\n');
}

export function teamMail(event, registration, config, token) {
    const editLink = token ? `\n\nTo change or cancel your registration, use this link. Keep it private:\n${config.siteUrl}/register/edit#${token}` : '';
    const texts = config.texts;
    const subjects = {
        received: `Registration received: ${config.siteName} ${config.year}`,
        confirmed: `Registration confirmed: ${config.siteName} ${config.year}`,
        declined: `Registration declined: ${config.siteName} ${config.year}`,
        placement: `Your participation: ${config.siteName} ${config.year}`,
        cancelled: `Registration cancelled: ${config.siteName} ${config.year}`,
    };
    const bodies = {
        received: `Thank you for registering for the ${config.siteName} ${config.year}. We will confirm your registration soon.\n\n${PLACEMENT_TEXT[registration.placement]}`,
        confirmed: `Your registration for the ${config.siteName} ${config.year} is confirmed. ${PLACEMENT_TEXT[registration.placement]}${texts.confirmed ? `\n\n${texts.confirmed}` : ''}`,
        declined: `Unfortunately, we cannot accept your registration for the ${config.siteName} ${config.year}.${texts.declined ? `\n\n${texts.declined}` : ''}`,
        placement: `Your participation in the ${config.siteName} ${config.year} has changed. ${PLACEMENT_TEXT[registration.placement]}`,
        cancelled: `Your registration for the ${config.siteName} ${config.year} is cancelled. If this was a mistake, please reply to this mail.`,
    };

    return {
        to: registration.contact_email,
        subject: subjects[event],
        text: `${bodies[event]}\n\n${teamSummary(registration, config)}${event === 'declined' || event === 'cancelled' ? '' : editLink}\n\n${config.siteName}\n${config.siteUrl}`,
    };
}

export function organiserMail(event, registration, config) {
    const verbs = { created: 'New registration', updated: 'Registration changed', cancelled: 'Registration cancelled' };

    return {
        to: config.notify,
        subject: `${verbs[event]}: ${registration.team_name} (${registration.people} people, ${registration.onsite_people} on site, ${registration.placement})`,
        text: `${verbs[event]} for the ${config.siteName} ${config.year}.\n\n${teamSummary(registration, config)}\nContact: ${registration.contact_email}\nPlacement: ${registration.placement}\n\nManage registrations: ${config.siteUrl}/admin/registrations`,
    };
}

/** Sends a mail through the Cloudflare Email Service. A failed mail never fails the registration. */
export async function sendMail(deps, env, config, mail) {
    if (!env.EMAIL_API_TOKEN || !env.CF_ACCOUNT_ID || !config.from || mail.to.length === 0) {
        return false;
    }

    try {
        const response = await deps.fetch(`https://api.cloudflare.com/client/v4/accounts/${env.CF_ACCOUNT_ID}/email/sending/send`, {
            method: 'POST',
            headers: { authorization: `Bearer ${env.EMAIL_API_TOKEN}`, 'content-type': 'application/json' },
            body: JSON.stringify({ from: { address: config.from, name: config.siteName }, reply_to: config.replyTo || undefined, ...mail }),
        });

        return response.ok;
    } catch {
        return false;
    }
}

// Handlers for the public API

export async function handleStatus(env, deps, config) {
    const taken = config.capacity === null ? 0 : await seatsTaken(env.JAM_DB, config.year);

    return json(200, {
        open: isOpen(config, deps.now),
        deadline: config.deadline,
        capacity: config.capacity,
        seats_left: config.capacity === null ? null : Math.max(0, config.capacity - taken),
    });
}

export async function handleCreate(request, env, deps, config) {
    config = forRequest(request, config);
    if (!isOpen(config, deps.now)) {
        return json(403, { error: 'closed' });
    }

    const input = await request.json().catch(() => null);
    if (!input || typeof input !== 'object') {
        return json(400, { error: 'invalid' });
    }

    // Bots fill in the hidden field. They get the same answer as people, so they learn nothing.
    if (input.website) {
        return json(201, { placement: 'remote' });
    }

    const ip = request.headers.get('cf-connecting-ip') ?? '';
    if (!(await verifyTurnstile(deps, env, input.turnstile, ip))) {
        return json(400, { error: 'turnstile' });
    }

    const { errors, data } = validate(input, config);
    if (errors.length > 0) {
        return json(422, { error: 'validation', fields: errors });
    }

    const db = env.JAM_DB;
    const ipHash = await sha256(`${config.year}:${ip}`);
    const recent = await db.prepare('SELECT COUNT(*) AS count FROM registrations WHERE ip_hash = ? AND created_at > ?')
        .bind(ipHash, deps.now - 3600000).first();
    if (Number(recent?.count ?? 0) >= MAX_PER_HOUR) {
        return json(429, { error: 'too_many' });
    }

    const id = crypto.randomUUID();
    const token = randomToken();

    await db.batch([
        db.prepare(`INSERT INTO registrations (id, year, team_name, contact_email, programs, wish, placement, people, onsite_people,
                comment, edit_token_hash, ip_hash, consent_at, created_at, updated_at, purge_after)
            SELECT ?5, ?4, ?6, ?7, ?8, ?1, ${PLACEMENT_SQL}, ?14, ?2, ?9, ?10, ?11, ?12, ?12, ?12, ?13`)
            .bind(data.wish, data.onsite, config.capacity, config.year, id, data.team_name, data.contact_email,
                JSON.stringify(data.programs), data.comment, await sha256(token), ipHash, deps.now, Date.parse(config.purgeAfter),
                data.members.length),
        ...memberStatements(db, id, data.members),
    ]);

    const registration = await findRegistration(db, 'id', id);
    await sendMail(deps, env, config, teamMail('received', registration, config, token));
    await sendMail(deps, env, config, organiserMail('created', registration, config));

    return json(201, { placement: registration.placement });
}

async function byToken(request, env) {
    const token = (request.headers.get('authorization') ?? '').replace(/^Bearer\s+/i, '');

    return token === '' ? null : findRegistration(env.JAM_DB, 'edit_token_hash', await sha256(token));
}

export async function handleSelf(request, env, deps, config) {
    config = forRequest(request, config);
    const registration = await byToken(request, env);
    if (!registration || registration.status === 'cancelled') {
        return json(404, { error: 'not_found' });
    }

    if (request.method === 'GET') {
        return json(200, { registration: publicView(registration), open: isOpen(config, deps.now) });
    }

    const db = env.JAM_DB;

    if (request.method === 'DELETE') {
        await db.prepare("UPDATE registrations SET status = 'cancelled', updated_at = ? WHERE id = ?")
            .bind(deps.now, registration.id).run();
        const cancelled = { ...registration, status: 'cancelled' };
        await sendMail(deps, env, config, teamMail('cancelled', cancelled, config));
        await sendMail(deps, env, config, organiserMail('cancelled', cancelled, config));

        return json(200, { status: 'cancelled' });
    }

    if (!isOpen(config, deps.now)) {
        return json(403, { error: 'closed' });
    }

    const input = await request.json().catch(() => null);
    const { errors, data } = validate({ ...input, consent_privacy: true, consent_names: true }, config);
    if (!input || errors.length > 0) {
        return json(422, { error: 'validation', fields: errors });
    }

    // A team keeps its seats on site as long as it does not bring more people. Otherwise the seats are checked again.
    const keepSeat = registration.placement === 'onsite' && data.onsite > 0 && data.onsite <= registration.onsite_people;
    const placement = keepSeat ? "'onsite'" : PLACEMENT_SQL;

    await db.batch([
        db.prepare(`UPDATE registrations SET team_name = ?6, contact_email = ?7, programs = ?8, wish = ?1, people = ?11,
                onsite_people = ?2, comment = ?9, placement = ${placement}, updated_at = ?10 WHERE id = ?5`)
            .bind(data.wish, data.onsite, config.capacity, config.year, registration.id, data.team_name, data.contact_email,
                JSON.stringify(data.programs), data.comment, deps.now, data.members.length),
        db.prepare('DELETE FROM members WHERE registration_id = ?').bind(registration.id),
        ...memberStatements(db, registration.id, data.members),
    ]);

    const updated = await findRegistration(db, 'id', registration.id);
    await sendMail(deps, env, config, organiserMail('updated', updated, config));

    return json(200, { registration: publicView(updated) });
}

// Handlers for the admin API. The caller has checked the Access token before.

export async function handleAdminList(env, config) {
    const db = env.JAM_DB;
    const { results } = await db.prepare('SELECT id FROM registrations WHERE year = ? ORDER BY created_at').bind(config.year).all();
    const registrations = await Promise.all(results.map((row) => findRegistration(db, 'id', row.id)));

    return json(200, {
        year: config.year,
        capacity: config.capacity,
        seats_taken: await seatsTaken(db, config.year),
        registrations: registrations.map(publicView),
    });
}

export async function handleAdminAction(request, env, deps, config) {
    config = forRequest(request, config);
    const input = await request.json().catch(() => ({}));
    const db = env.JAM_DB;
    const registration = await findRegistration(db, 'id', input.id ?? '');

    if (!registration || registration.year !== config.year) {
        return json(404, { error: 'not_found' });
    }

    if (input.action === 'delete') {
        await db.batch([
            db.prepare('DELETE FROM members WHERE registration_id = ?').bind(registration.id),
            db.prepare('DELETE FROM registrations WHERE id = ?').bind(registration.id),
        ]);

        return json(200, { deleted: true });
    }

    const changes = {
        confirm: { status: 'confirmed', mail: 'confirmed' },
        decline: { status: 'declined', mail: 'declined' },
        place: { placement: input.placement, mail: 'placement' },
    }[input.action];

    if (!changes || (changes.placement !== undefined && !PLACEMENTS.includes(changes.placement))) {
        return json(400, { error: 'invalid_action' });
    }

    const status = changes.status ?? registration.status;
    const placement = changes.placement ?? registration.placement;
    await db.prepare('UPDATE registrations SET status = ?, placement = ?, updated_at = ? WHERE id = ?')
        .bind(status, placement, deps.now, registration.id).run();

    const updated = { ...registration, status, placement };
    const mailed = input.notify === false ? false : await sendMail(deps, env, config, teamMail(changes.mail, updated, config));

    return json(200, { registration: publicView(updated), mailed });
}

const csvCell = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;

/**
 * `people` lists every person with real name for the organisers, for example for the security service.
 * `teams` only holds team names and the names people chose to show, for `jam:add-project`.
 */
export async function handleAdminExport(url, env, config) {
    const db = env.JAM_DB;
    const { results } = await db.prepare("SELECT id FROM registrations WHERE year = ? AND status != 'cancelled' ORDER BY created_at")
        .bind(config.year).all();
    const registrations = await Promise.all(results.map((row) => findRegistration(db, 'id', row.id)));
    const format = url.searchParams.get('format');

    if (format === 'teams') {
        const teams = registrations.filter((registration) => registration.status === 'confirmed').map((registration) => ({
            name: registration.team_name,
            members: registration.members.map(displayName),
        }));

        return new Response(JSON.stringify({ year: config.year, teams }, null, 2) + '\n', {
            headers: { ...headers, 'content-disposition': `attachment; filename="teams-${config.year}.json"` },
        });
    }

    const placement = url.searchParams.get('placement');
    const rows = [['Team', 'Status', 'Placement', 'Real name', 'Shown as', 'Remote', 'Discord', 'Contact'].map(csvCell).join(',')];
    registrations.filter((registration) => !placement || registration.placement === placement).forEach((registration) => {
        // The list of people on site leaves out the ones who take part remotely.
        registration.members.filter((member) => placement !== 'onsite' || !member.remote).forEach((member) => {
            rows.push([registration.team_name, registration.status, registration.placement, member.real_name, displayName(member),
                member.remote ? 'yes' : 'no', member.discord, registration.contact_email].map(csvCell).join(','));
        });
    });

    return new Response('﻿' + rows.join('\r\n') + '\r\n', {
        headers: { 'content-type': 'text/csv; charset=utf-8', 'cache-control': 'no-store',
            'content-disposition': `attachment; filename="registrations-${config.year}.csv"` },
    });
}
