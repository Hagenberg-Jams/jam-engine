/**
 * Uploads at the end of a jam: the build of a game or the video of an animation, the README.md and screenshots.
 *
 * Files go to the private R2 bucket `env.JAM_SUBMISSIONS`, nothing is ever overwritten:
 *   {year}/{team}/uploads/{Team}_{Title}_{Platform}_{version}.{ext}  one file per version and platform
 *   {year}/{team}/readme/{stamp}.md and {year}/{team}/README.md      every README, and the latest one
 *   {year}/{team}/screenshots/{stamp}/{n}.{ext} and .../current.json  every set of screenshots, and the latest one
 *
 * Large files are uploaded in parts, because a request to a Pages Function may carry at most 100 MB.
 * The handlers take their dependencies as arguments, so that tests can run them without Cloudflare.
 */

import { json, sendMail, sha256, verifyTurnstile } from './_shared.js';

export const PART_SIZE = 50 * 1024 * 1024;
const MAX_SCREENSHOT = 25 * 1024 * 1024;
const MAX_README = 100 * 1024;
const SESSION_HOURS = 24;
const IMAGE_TYPES = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp' };
const CONTENT_TYPES = { zip: 'application/zip', mp4: 'video/mp4', mov: 'video/quicktime', mkv: 'video/x-matroska', webm: 'video/webm' };

// Names

/** "Die Pixel-Piraten!" → "die-pixel-piraten", like Str::slug in the engine. */
export function slug(value) {
    return ascii(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

/** "the awesomes" → "TheAwesomes", for file names. */
export function camel(value) {
    return ascii(value).split(/[^A-Za-z0-9]+/).filter(Boolean)
        .map((word) => word[0].toUpperCase() + word.slice(1)).join('').slice(0, 40);
}

function ascii(value) {
    return String(value ?? '').replace(/ß/g, 'ss').normalize('NFKD').replace(/[̀-ͯ]/g, '');
}

export function fileName(config, { team, title, platform, version, extension }) {
    const parts = [camel(team) || 'Team', camel(title) || 'Untitled'];
    if (config.kind === 'game') {
        parts.push(platform);
    }

    return `${parts.join('_')}_${version}.${extension}`;
}

// Versions

const SEMVER = /^(\d{1,4})\.(\d{1,4})\.(\d{1,4})$/;

export function parseVersion(value) {
    const match = SEMVER.exec(String(value ?? '').trim());

    return match ? match.slice(1).map(Number) : null;
}

export function compareVersions(a, b) {
    const [x, y] = [parseVersion(a), parseVersion(b)];
    for (let i = 0; i < 3; i++) {
        if (x[i] !== y[i]) {
            return x[i] - y[i];
        }
    }

    return 0;
}

/** The first upload is 1.0.0, every later one raises the last number. */
export function suggestVersion(latest) {
    const version = parseVersion(latest);

    return version ? `${version[0]}.${version[1]}.${version[2] + 1}` : '1.0.0';
}

// README

const normalize = (heading) => heading.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();

/**
 * Checks a README.md against the sections in the config. Template hints in HTML comments do not count as content.
 * Returns the title from the first heading, and a list of problems, each with a code and the section.
 */
export function checkReadme(text, config) {
    const errors = [];
    const content = String(text ?? '').replace(/^﻿/, '').replace(/<!--[\s\S]*?-->/g, '').replace(/\r\n?/g, '\n');
    const placeholder = normalize(config.readme.title);

    const titleMatch = /^#[ \t]+(.+?)[ \t#]*$/m.exec(content);
    const title = titleMatch ? titleMatch[1].trim() : '';
    if (title === '' || normalize(title) === placeholder) {
        errors.push({ code: 'title', section: config.readme.title });
    }

    const sections = new Map();
    const parts = content.split(/^##[ \t]+(.+?)[ \t#]*$/m);
    for (let i = 1; i < parts.length; i += 2) {
        sections.set(normalize(parts[i]), parts[i + 1] ?? '');
    }

    for (const section of config.readme.sections) {
        const names = [section.heading, ...(section.aliases ?? [])].map(normalize);
        const found = names.find((name) => sections.has(name));
        if (found === undefined) {
            if (section.required) {
                errors.push({ code: 'missing', section: section.heading });
            }
            continue;
        }

        // Empty list items from the template are no content either.
        const body = sections.get(found).replace(/^[ \t]*([-*+]|\d+\.)[ \t]*$/gm, '').trim();
        if (section.required && body === '') {
            errors.push({ code: 'empty', section: section.heading });
        }
    }

    return { title, errors };
}

// Sessions: what a started upload may do, signed so that it cannot be changed by the browser.

const base64url = (bytes) => btoa(String.fromCharCode(...bytes)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const fromBase64url = (text) => Uint8Array.from(atob(text.replace(/-/g, '+').replace(/_/g, '/')), (char) => char.charCodeAt(0));

async function hmacKey(env) {
    const secret = new TextEncoder().encode(`submission|${env.TURNSTILE_SECRET ?? ''}|${env.JAM_SUBMISSION_CODE ?? ''}`);

    return crypto.subtle.importKey('raw', secret, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign', 'verify']);
}

export async function signSession(env, session) {
    const payload = new TextEncoder().encode(JSON.stringify(session));
    const signature = new Uint8Array(await crypto.subtle.sign('HMAC', await hmacKey(env), payload));

    return `${base64url(payload)}.${base64url(signature)}`;
}

export async function readSession(request, env, now) {
    const token = (request.headers.get('authorization') ?? '').replace(/^Bearer\s+/i, '');
    const [payload, signature] = token.split('.');
    if (!payload || !signature) {
        return null;
    }

    try {
        const bytes = fromBase64url(payload);
        if (!(await crypto.subtle.verify('HMAC', await hmacKey(env), fromBase64url(signature), bytes))) {
            return null;
        }
        const session = JSON.parse(new TextDecoder().decode(bytes));

        return session.expires > now ? session : null;
    } catch {
        return null;
    }
}

// State

export function isOpen(config, now) {
    return config.active && (!config.closes || now <= Date.parse(config.closes));
}

export function isLate(config, now) {
    return Boolean(config.deadline) && now > Date.parse(config.deadline);
}

/** The code from the announcement, compared without case and surrounding spaces. */
async function codeMatches(env, code) {
    const expected = String(env.JAM_SUBMISSION_CODE ?? '').trim().toLowerCase();

    return expected !== '' && (await sha256(String(code ?? '').trim().toLowerCase())) === (await sha256(expected));
}

/**
 * The teams to choose from: the list of the site, even an empty one, or without a list the confirmed registrations of the jam.
 */
export async function teams(env, config) {
    let list = Array.isArray(config.teams) ? config.teams : [];

    if (!Array.isArray(config.teams) && env.JAM_DB) {
        const { results } = await env.JAM_DB.prepare("SELECT team_name FROM registrations WHERE year = ? AND status = 'confirmed'")
            .bind(config.year).all();
        list = results.map((row) => ({ name: row.team_name, slug: slug(row.team_name) }));
    }

    const seen = new Set();

    return list.filter((team) => team.slug !== '' && !seen.has(team.slug) && seen.add(team.slug))
        .sort((a, b) => a.name.localeCompare(b.name, 'en', { sensitivity: 'base' }));
}

async function findTeam(env, config, value) {
    return (await teams(env, config)).find((team) => team.slug === value) ?? null;
}

async function listAll(bucket, prefix) {
    const objects = [];
    let cursor;
    do {
        const page = await bucket.list({ prefix, cursor, include: ['customMetadata'] });
        objects.push(...page.objects);
        cursor = page.truncated ? page.cursor : undefined;
    } while (cursor);

    return objects;
}

const platformOf = (config, platform) => (config.kind === 'game' ? platform : 'video');

/** What a team has uploaded so far: its files, the latest version for each platform, its README and screenshots. */
export async function teamState(env, config, team) {
    const bucket = env.JAM_SUBMISSIONS;
    const base = `${config.year}/${team.slug}`;
    const uploads = (await listAll(bucket, `${base}/uploads/`)).map((object) => ({
        file: object.key.slice(`${base}/uploads/`.length),
        platform: object.customMetadata?.platform ?? 'video',
        version: object.customMetadata?.version ?? '',
        late: object.customMetadata?.late === 'yes',
        uploadedAt: object.customMetadata?.uploadedAt ?? null,
        size: object.size,
    })).filter((upload) => parseVersion(upload.version) !== null);

    const latest = {};
    for (const upload of uploads) {
        if (!latest[upload.platform] || compareVersions(upload.version, latest[upload.platform]) > 0) {
            latest[upload.platform] = upload.version;
        }
    }

    const readme = await bucket.get(`${base}/README.md`);
    const readmeText = readme ? await readme.text() : null;
    const screenshots = await bucket.get(`${base}/screenshots/current.json`);
    const current = screenshots ? await screenshots.json() : null;
    const chosen = await bucket.get(`${base}/category.json`);
    const category = chosen ? ((await chosen.json().catch(() => ({}))).category ?? null) : null;

    return {
        uploads: uploads.sort((a, b) => String(b.uploadedAt).localeCompare(String(a.uploadedAt))),
        latest,
        readme: readmeText === null ? null : { title: checkReadme(readmeText, config).title, text: readmeText },
        screenshots: current?.files?.length ?? 0,
        category,
    };
}

const categories = (config) => (Array.isArray(config.categories) ? config.categories : []);

/** The name of a category, or its key when it is no longer in the list. */
export function categoryName(config, key) {
    return categories(config).find((category) => category.key === key)?.name ?? key;
}

// Handlers

export async function handleStatus(env, deps, config) {
    return json(200, {
        open: isOpen(config, deps.now),
        late: isLate(config, deps.now),
        deadline: config.deadline,
        closes: config.closes,
        teams: (await teams(env, config)).map(({ name, slug }) => ({ name, slug })),
    });
}

export async function handleTeam(request, env, deps, config) {
    const input = await request.json().catch(() => ({}));
    if (!(await codeMatches(env, input.code))) {
        return json(403, { error: 'code' });
    }

    const team = await findTeam(env, config, input.team);
    if (!team) {
        return json(404, { error: 'team' });
    }

    const state = await teamState(env, config, team);
    const platforms = config.kind === 'game' ? config.platforms : ['video'];
    const suggested = Object.fromEntries(platforms.map((platform) => [platform, suggestVersion(state.latest[platform])]));

    return json(200, {
        team: team.name,
        uploads: state.uploads,
        latest: state.latest,
        suggested,
        readme: state.readme ? { title: state.readme.title } : null,
        screenshots: state.screenshots,
        category: state.category,
    });
}

export async function handleCheck(request, config) {
    const input = await request.json().catch(() => ({}));
    if (typeof input.readme !== 'string' || input.readme.length > MAX_README) {
        return json(400, { error: 'invalid' });
    }

    return json(200, checkReadme(input.readme, config));
}

export async function handleStart(request, env, deps, config) {
    if (!isOpen(config, deps.now)) {
        return json(403, { error: 'closed' });
    }

    if (!env.JAM_SUBMISSIONS || !env.JAM_SUBMISSION_CODE) {
        return json(503, { error: 'not_configured' });
    }

    const input = await request.json().catch(() => null);
    if (!input || typeof input !== 'object') {
        return json(400, { error: 'invalid' });
    }

    if (!(await codeMatches(env, input.code))) {
        return json(403, { error: 'code' });
    }

    if (!(await verifyTurnstile(deps, env, input.turnstile, request.headers.get('cf-connecting-ip') ?? ''))) {
        return json(400, { error: 'turnstile' });
    }

    const team = await findTeam(env, config, input.team);
    if (!team) {
        return json(404, { error: 'team' });
    }

    const errors = [];
    const extension = String(input.extension ?? '').toLowerCase();
    const size = Number(input.size);
    const platform = platformOf(config, input.platform);

    if (config.kind === 'game' && !config.platforms.includes(platform)) {
        errors.push({ code: 'platform' });
    }
    if (!config.extensions.includes(extension)) {
        errors.push({ code: 'extension' });
    }
    if (!Number.isInteger(size) || size <= 0 || size > config.maxSize) {
        errors.push({ code: 'size' });
    }
    if (parseVersion(input.version) === null) {
        errors.push({ code: 'version' });
    }

    const state = await teamState(env, config, team);
    const latest = state.latest[platform];
    if (parseVersion(input.version) !== null && latest && compareVersions(input.version, latest) <= 0) {
        errors.push({ code: 'version_low', latest });
    }

    // A README and screenshots are needed once. Later uploads, for example a bugfix, may leave them out.
    let title = state.readme?.title ?? '';
    const readme = typeof input.readme === 'string' && input.readme.trim() !== '' ? input.readme : null;
    if (readme !== null) {
        const check = readme.length > MAX_README ? { title: '', errors: [{ code: 'readme_size' }] } : checkReadme(readme, config);
        errors.push(...check.errors);
        title = check.title;
    } else if (!state.readme) {
        errors.push({ code: 'readme_required' });
    }

    // A category is needed once, like the README. Later uploads may keep it or choose another one.
    const category = typeof input.category === 'string' && input.category !== '' ? input.category : null;
    if (categories(config).length > 0) {
        if (category !== null && !categories(config).some((item) => item.key === category)) {
            errors.push({ code: 'category' });
        } else if (category === null && !state.category) {
            errors.push({ code: 'category_required' });
        }
    }

    const screenshots = Array.isArray(input.screenshots) ? input.screenshots : [];
    const { min, max } = config.screenshots;
    if (screenshots.length > 0) {
        if (screenshots.length < min || screenshots.length > max) {
            errors.push({ code: 'screenshots_count', min, max });
        }
        if (screenshots.some((shot) => !IMAGE_TYPES[shot?.type] || !(Number(shot?.size) > 0 && Number(shot.size) <= MAX_SCREENSHOT))) {
            errors.push({ code: 'screenshots_type' });
        }
    } else if (min > 0 && state.screenshots === 0) {
        errors.push({ code: 'screenshots_required', min });
    }

    if (errors.length > 0) {
        return json(422, { error: 'validation', errors });
    }

    const bucket = env.JAM_SUBMISSIONS;
    const base = `${config.year}/${team.slug}`;
    const file = fileName(config, { team: team.name, title, platform, version: input.version.trim(), extension });
    const key = `${base}/uploads/${file}`;
    if (await bucket.head(key)) {
        return json(409, { error: 'exists' });
    }

    const stamp = new Date(deps.now).toISOString().replace(/[:.]/g, '-');
    const late = isLate(config, deps.now);
    if (readme !== null) {
        await bucket.put(`${base}/readme/${stamp}.md`, readme, { httpMetadata: { contentType: 'text/markdown; charset=utf-8' } });
    }

    const chosen = categories(config).length > 0 ? (category ?? state.category) : null;
    const upload = await bucket.createMultipartUpload(key, {
        httpMetadata: { contentType: CONTENT_TYPES[extension] ?? 'application/octet-stream' },
        customMetadata: {
            team: team.name, title, platform, version: input.version.trim(), late: late ? 'yes' : 'no', uploadedAt: new Date(deps.now).toISOString(),
            ...(chosen ? { category: chosen } : {}),
        },
    });

    const session = {
        key, uploadId: upload.uploadId, team: team.slug, teamName: team.name, title, platform, version: input.version.trim(),
        category: chosen, categoryChanged: chosen !== null && chosen !== state.category,
        size, stamp, late, readme: readme !== null, screenshots: screenshots.map((shot) => IMAGE_TYPES[shot.type]),
        expires: deps.now + SESSION_HOURS * 3600000,
    };

    return json(201, { token: await signSession(env, session), file, partSize: PART_SIZE, parts: Math.ceil(size / PART_SIZE), late });
}

export async function handlePart(request, env, deps) {
    const session = await readSession(request, env, deps.now);
    if (!session) {
        return json(401, { error: 'session' });
    }

    const number = Number(new URL(request.url).searchParams.get('n'));
    if (!Number.isInteger(number) || number < 1 || number > Math.ceil(session.size / PART_SIZE)) {
        return json(400, { error: 'part' });
    }

    const upload = env.JAM_SUBMISSIONS.resumeMultipartUpload(session.key, session.uploadId);
    const part = await upload.uploadPart(number, request.body);

    return json(200, { part: { partNumber: part.partNumber, etag: part.etag } });
}

export async function handleScreenshot(request, env, deps) {
    const session = await readSession(request, env, deps.now);
    if (!session) {
        return json(401, { error: 'session' });
    }

    const index = Number(new URL(request.url).searchParams.get('i'));
    const extension = session.screenshots[index - 1];
    const length = Number(request.headers.get('content-length'));
    if (!Number.isInteger(index) || !extension || !(length > 0 && length <= MAX_SCREENSHOT)) {
        return json(400, { error: 'screenshot' });
    }

    const type = Object.keys(IMAGE_TYPES).find((key) => IMAGE_TYPES[key] === extension);
    await env.JAM_SUBMISSIONS.put(`${session.key.split('/uploads/')[0]}/screenshots/${session.stamp}/${index}.${extension}`, request.body, {
        httpMetadata: { contentType: type },
    });

    return json(200, { stored: index });
}

export async function handleComplete(request, env, deps, config) {
    const session = await readSession(request, env, deps.now);
    if (!session) {
        return json(401, { error: 'session' });
    }

    const bucket = env.JAM_SUBMISSIONS;
    const base = session.key.split('/uploads/')[0];
    const input = await request.json().catch(() => ({}));
    const parts = Array.isArray(input.parts) ? input.parts.map((part) => ({ partNumber: Number(part.partNumber), etag: String(part.etag) })) : [];

    if (await bucket.head(session.key)) {
        return json(409, { error: 'exists' });
    }

    const screenshotPrefix = `${base}/screenshots/${session.stamp}/`;
    const stored = session.screenshots.length > 0 ? await listAll(bucket, screenshotPrefix) : [];
    if (stored.length !== session.screenshots.length) {
        return json(422, { error: 'screenshots_missing' });
    }

    const upload = bucket.resumeMultipartUpload(session.key, session.uploadId);
    await upload.complete(parts.sort((a, b) => a.partNumber - b.partNumber));

    const object = await bucket.head(session.key);
    if (!object || object.size !== session.size) {
        await bucket.delete(session.key);

        return json(422, { error: 'incomplete' });
    }

    if (session.readme) {
        const readme = await bucket.get(`${base}/readme/${session.stamp}.md`);
        await bucket.put(`${base}/README.md`, await readme.text(), { httpMetadata: { contentType: 'text/markdown; charset=utf-8' } });
    }
    if (session.categoryChanged) {
        await bucket.put(`${base}/category.json`, JSON.stringify({ category: session.category, chosenAt: new Date(deps.now).toISOString() }), {
            httpMetadata: { contentType: 'application/json' },
        });
    }
    if (stored.length > 0) {
        await bucket.put(`${base}/screenshots/current.json`, JSON.stringify({
            stamp: session.stamp, files: stored.map((item) => item.key.slice(`${base}/screenshots/`.length)).sort(),
        }), { httpMetadata: { contentType: 'application/json' } });
    }

    const file = session.key.slice(`${base}/uploads/`.length);
    await sendMail(deps, env, config, organiserMail(session, file, config));

    return json(200, { file, version: session.version, late: session.late, size: session.size });
}

export async function handleAbort(request, env, deps) {
    const session = await readSession(request, env, deps.now);
    if (!session) {
        return json(401, { error: 'session' });
    }

    await env.JAM_SUBMISSIONS.resumeMultipartUpload(session.key, session.uploadId).abort().catch(() => {});

    return json(200, { aborted: true });
}

// Handlers for the admin API. The middleware has checked the Access token before.

/**
 * Every team with its uploads, README and screenshots, also teams that uploaded nothing yet,
 * and folders of teams that are no longer in the list.
 */
export async function handleAdminList(env, config) {
    if (!env.JAM_SUBMISSIONS) {
        return json(503, { error: 'not_configured' });
    }

    const bucket = env.JAM_SUBMISSIONS;
    const objects = await listAll(bucket, `${config.year}/`);
    const known = await teams(env, config);
    const bySlug = new Map(known.map((team) => [team.slug, { name: team.name, slug: team.slug, listed: true }]));

    for (const object of objects) {
        const slug = object.key.split('/')[1];
        if (slug && !bySlug.has(slug)) {
            bySlug.set(slug, { name: object.customMetadata?.team ?? slug, slug, listed: false });
        }
    }

    const result = [];
    for (const team of bySlug.values()) {
        const base = `${config.year}/${team.slug}/`;
        const own = objects.filter((object) => object.key.startsWith(base));
        const state = own.length > 0 ? await teamState(env, config, team) : { uploads: [], latest: {}, readme: null, category: null };
        const current = own.some((object) => object.key === `${base}screenshots/current.json`)
            ? await (await bucket.get(`${base}screenshots/current.json`)).json()
            : null;
        const uploads = state.uploads.map((upload) => ({
            ...upload,
            key: `${base}uploads/${upload.file}`,
            latest: state.latest[upload.platform] === upload.version,
        }));

        result.push({
            ...team,
            uploads,
            latest: state.latest,
            onTime: uploads.some((upload) => !upload.late),
            readme: state.readme ? { key: `${base}README.md`, title: state.readme.title, text: state.readme.text } : null,
            readmes: own.filter((object) => object.key.startsWith(`${base}readme/`)).length,
            screenshots: (current?.files ?? []).map((file) => `${base}screenshots/${file}`),
            category: state.category ? { key: state.category, name: categoryName(config, state.category) } : null,
        });
    }

    result.sort((a, b) => a.name.localeCompare(b.name, 'en', { sensitivity: 'base' }));

    return json(200, { year: config.year, kind: config.kind, deadline: config.deadline, categories: categories(config), teams: result });
}

const INLINE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'text/markdown; charset=utf-8'];

/**
 * Sends one file of the bucket. Only images and the README may be shown in the page, everything else is a download.
 */
export async function handleAdminFile(url, env, config) {
    const key = url.searchParams.get('key') ?? '';
    if (!env.JAM_SUBMISSIONS || !key.startsWith(`${config.year}/`) || key.includes('..') || key.endsWith('/')) {
        return json(400, { error: 'key' });
    }

    const object = await env.JAM_SUBMISSIONS.get(key);
    if (!object) {
        return json(404, { error: 'not_found' });
    }

    const type = object.httpMetadata?.contentType ?? 'application/octet-stream';
    const inline = url.searchParams.get('inline') === '1' && INLINE_TYPES.includes(type);
    const name = key.split('/').pop().replace(/["\\\r\n]/g, '_');

    return new Response(object.body, {
        headers: {
            'content-type': inline && type.startsWith('text/') ? 'text/plain; charset=utf-8' : type,
            'content-length': String(object.size),
            'content-disposition': `${inline ? 'inline' : 'attachment'}; filename="${name}"`,
            'cache-control': 'private, no-store',
            'x-content-type-options': 'nosniff',
            'content-security-policy': "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        },
    });
}

export function organiserMail(session, file, config) {
    const what = config.kind === 'game' ? `${session.platform} ${session.version}` : session.version;
    const extras = [session.readme ? 'a new README.md' : '', session.screenshots.length > 0 ? `${session.screenshots.length} screenshots` : '']
        .filter(Boolean).join(' and ');

    return {
        to: config.notify,
        subject: `Upload${session.late ? ' (late)' : ''}: ${session.teamName}, ${session.title} ${what}`,
        text: `${session.teamName} uploaded ${file} (${(session.size / 1048576).toFixed(1)} MB)${extras ? ` with ${extras}` : ''} `
            + `for the ${config.siteName} ${config.year}.`
            + `${session.category ? `\n\nCategory: ${categoryName(config, session.category)}${session.categoryChanged ? ' (new)' : ''}` : ''}`
            + `${session.late ? '\n\nThe upload came after the deadline.' : ''}`
            + `\n\nThe files are in the R2 bucket of the site under ${config.year}/${session.team}/.\n\n${config.siteName}\n${config.siteUrl}`,
    };
}
