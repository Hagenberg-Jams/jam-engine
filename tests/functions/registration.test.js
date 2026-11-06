import { beforeEach, describe, expect, it, vi } from 'vitest';
import { accessToken, verifyAccess } from '../../resources/functions/api/_access.js';
import {
    handleAdminAction,
    handleAdminExport,
    handleAdminList,
    handleCreate,
    handleSelf,
    handleStatus,
    validate,
} from '../../resources/functions/api/_registration-logic.js';
import { createD1 } from './d1.js';

const NOW = Date.parse('2026-11-10T12:00:00+01:00');

const config = {
    year: 2026,
    siteName: 'Test Jam',
    siteUrl: 'https://jam.example',
    active: true,
    deadline: '2026-12-07T23:59:00+01:00',
    teamSize: [1, 6],
    capacity: 5,
    fields: { discord: true, programs: true, comment: true, participation: true },
    programs: ['MTD.ba', 'SE.ba'],
    notify: ['orga@jam.example'],
    from: 'noreply@jam.example',
    replyTo: 'info@jam.example',
    purgeAfter: '2027-03-22T21:00:00+01:00',
    access: { team: 'jams', aud: 'aud-tag' },
    texts: { confirmed: 'See you there.', declined: '' },
};

const env = () => ({ JAM_DB: createD1(), TURNSTILE_SECRET: 'secret', EMAIL_API_TOKEN: 'mail-token', CF_ACCOUNT_ID: 'account' });

/** Records mails and answers Turnstile with success unless told otherwise. */
function fakeDeps({ turnstile = true, now = NOW } = {}) {
    const mails = [];
    const fetch = async (url, init) => {
        if (url.includes('turnstile')) {
            return Response.json({ success: turnstile });
        }
        mails.push(JSON.parse(init.body));

        return Response.json({ success: true });
    };

    return { fetch, now, mails };
}

const team = (overrides = {}) => ({
    team_name: 'Pixel Pushers',
    contact_email: 'Lead@Example.org',
    wish: 'onsite_preferred',
    comment: 'Hello',
    members: [
        { program: 'SE.ba', real_name: 'Ann Real', nickname: 'Annie', show_as: 'nick', discord: 'ann#1' },
        { program: 'MTD.ba', real_name: 'Bo Real', nickname: '', show_as: 'real', discord: '' },
    ],
    consent_privacy: true,
    consent_names: true,
    turnstile: 'token',
    ...overrides,
});

const post = (body, ip = '1.2.3.4') => new Request('https://jam.example/api/registration', {
    method: 'POST',
    headers: { 'content-type': 'application/json', 'cf-connecting-ip': ip },
    body: JSON.stringify(body),
});

const tokenFrom = (mail) => mail.text.match(/edit#([A-Za-z0-9_-]+)/)[1];

describe('validation', () => {
    it('cleans the input and collects the programmes of the team', () => {
        const { errors, data } = validate(team(), config);

        expect(errors).toEqual([]);
        expect(data.contact_email).toBe('lead@example.org');
        expect(data.members.map((member) => member.program)).toEqual(['SE.ba', 'MTD.ba']);
        expect(data.programs).toEqual(['MTD.ba', 'SE.ba']);
        expect(data.members).toHaveLength(2);
    });

    it('asks each person for a programme from the list, except when organisers add a registration', () => {
        const members = [{ real_name: 'Ann', program: 'Unknown' }, { real_name: 'Bo' }];

        expect(validate(team({ members }), config).errors).toEqual(['members.0.program', 'members.1.program']);
        expect(validate(team({ members }), config, { organisers: true }).errors).toEqual([]);
        expect(validate(team({ members }), config, { organisers: true }).data.members[0].program).toBe('');
    });

    it('requires a real name, a nickname when it is shown, both consents and a valid team size', () => {
        const { errors } = validate(team({
            members: [{ program: 'SE.ba', real_name: '', nickname: 'X' }, { program: 'SE.ba', real_name: 'Y', show_as: 'nick' }],
            consent_names: false,
        }), config);

        expect(errors).toEqual(expect.arrayContaining(['members.0.real_name', 'members.1.nickname', 'consent_names']));
        expect(validate(team({ members: [] }), config).errors).toContain('members');
        expect(validate(team({ members: Array(7).fill({ program: 'SE.ba', real_name: 'Z' }) }), config).errors).toContain('members');
    });

    it('ignores fields that the jam does not ask for', () => {
        const { data } = validate(team(), { ...config, fields: { discord: false, programs: false, comment: false, participation: false } });

        expect(data.members[0].discord).toBe('');
        expect(data.programs).toEqual([]);
        expect(data.comment).toBe('');
        expect(data.wish).toBe('onsite_preferred');
    });
});

describe('registering', () => {
    let site;
    beforeEach(() => {
        site = env();
    });

    it('stores the team and mails the team and the organisers', async () => {
        const deps = fakeDeps();
        const response = await handleCreate(post(team()), site, deps, config);

        expect(response.status).toBe(201);
        expect(await response.json()).toEqual({ placement: 'onsite' });
        expect(deps.mails.map((mail) => mail.to)).toEqual(['lead@example.org', 'orga@jam.example']);
        expect(deps.mails[0].text).toContain('https://jam.example/register/edit#');
        expect(deps.mails[0].text).toContain('- Bo Real, MTD.ba');
        expect(deps.mails[0].from).toEqual({ address: 'noreply@jam.example', name: 'Test Jam' });
        expect(site.JAM_DB.sqlite.prepare('SELECT COUNT(*) AS n FROM members').get().n).toBe(2);
    });

    it('mails each organiser separately, so one failing address does not stop the others', async () => {
        const deps = fakeDeps();
        const send = deps.fetch;
        deps.fetch = async (url, init) => (String(init?.body).includes('"to":"broken@jam.example"') ? new Response('{"success":false}', { status: 400 }) : send(url, init));
        const errors = vi.spyOn(console, 'error').mockImplementation(() => {});
        await handleCreate(post(team()), site, deps, { ...config, notify: ['broken@jam.example', 'orga@jam.example', 'second@jam.example'] });

        expect(deps.mails.map((mail) => mail.to)).toEqual(['lead@example.org', 'orga@jam.example', 'second@jam.example']);
        expect(errors).toHaveBeenCalledWith(expect.stringContaining('failed with 400'));
        errors.mockRestore();
    });

    it('links to the host the registration came from, for example a preview', async () => {
        const deps = fakeDeps();
        const request = new Request('https://abc.preview.example/api/registration', {
            method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify(team()),
        });
        await handleCreate(request, site, deps, config);

        expect(deps.mails[0].text).toContain('https://abc.preview.example/register/edit#');
        expect(deps.mails[1].text).toContain('https://abc.preview.example/admin/registrations');
    });

    it('never stores the edit token itself', async () => {
        const deps = fakeDeps();
        await handleCreate(post(team()), site, deps, config);
        const token = tokenFrom(deps.mails[0]);
        const row = site.JAM_DB.sqlite.prepare('SELECT edit_token_hash FROM registrations').get();

        expect(row.edit_token_hash).not.toBe(token);
        expect(row.edit_token_hash).toMatch(/^[0-9a-f]{64}$/);
    });

    it('rejects a failed Turnstile check, a closed registration and invalid data', async () => {
        expect((await handleCreate(post(team()), site, fakeDeps({ turnstile: false }), config)).status).toBe(400);
        expect((await handleCreate(post(team()), site, fakeDeps({ now: Date.parse('2026-12-08T00:00:00+01:00') }), config)).status).toBe(403);
        expect((await handleCreate(post(team()), site, fakeDeps(), { ...config, active: false })).status).toBe(403);

        const invalid = await handleCreate(post(team({ contact_email: 'nope' })), site, fakeDeps(), config);
        expect(invalid.status).toBe(422);
        expect((await invalid.json()).fields).toContain('contact_email');
    });

    it('pretends to accept bots that fill in the hidden field, but stores nothing', async () => {
        const deps = fakeDeps();
        const response = await handleCreate(post(team({ website: 'spam' })), site, deps, config);

        expect(response.status).toBe(201);
        expect(deps.mails).toEqual([]);
        expect(site.JAM_DB.sqlite.prepare('SELECT COUNT(*) AS n FROM registrations').get().n).toBe(0);
    });

    it('limits registrations from one address per hour', async () => {
        for (let i = 0; i < 5; i++) {
            expect((await handleCreate(post(team()), site, fakeDeps(), config)).status).toBe(201);
        }

        expect((await handleCreate(post(team()), site, fakeDeps(), config)).status).toBe(429);
        expect((await handleCreate(post(team(), '5.6.7.8'), site, fakeDeps(), config)).status).toBe(201);
    });
});

describe('seats on site', () => {
    let site;
    beforeEach(() => {
        site = env();
    });

    const register = async (wish, people, ip) => {
        const members = Array.from({ length: people }, (_, i) => ({ program: 'SE.ba', real_name: `Person ${i}` }));
        const response = await handleCreate(post(team({ wish, members }), ip), site, fakeDeps(), config);

        return (await response.json()).placement;
    };

    it('fills exactly up to the capacity, then follows the wish of each team', async () => {
        expect(await register('onsite_only', 3, 'a')).toBe('onsite');
        expect(await register('onsite_preferred', 2, 'b')).toBe('onsite');
        expect(await register('onsite_only', 1, 'c')).toBe('waitlist');
        expect(await register('onsite_preferred', 1, 'd')).toBe('remote');
        expect(await register('remote', 1, 'e')).toBe('remote');

        const status = await (await handleStatus(site, fakeDeps(), config)).json();
        expect(status).toMatchObject({ open: true, capacity: 5, seats_left: 0 });
    });

    it('counts only the people on site, and puts a team without any of them remote', async () => {
        const mixed = [{ program: 'SE.ba', real_name: 'A' }, { program: 'SE.ba', real_name: 'B' }, { program: 'SE.ba', real_name: 'C', remote: true }, { program: 'SE.ba', real_name: 'D', remote: true }];
        const first = await handleCreate(post(team({ wish: 'onsite_only', members: mixed }), 'm'), site, fakeDeps(), config);
        expect(await first.json()).toEqual({ placement: 'onsite' });
        expect(site.JAM_DB.sqlite.prepare('SELECT people, onsite_people FROM registrations').get()).toEqual({ people: 4, onsite_people: 2 });

        // 3 seats are left, so a team of 3 on site still fits.
        expect(await register('onsite_only', 3, 'n')).toBe('onsite');

        const remoteOnly = [{ program: 'SE.ba', real_name: 'E', remote: true }];
        const response = await handleCreate(post(team({ wish: 'onsite_only', members: remoteOnly }), 'o'), site, fakeDeps(), config);
        expect(await response.json()).toEqual({ placement: 'remote' });
    });

    it('puts everybody on site who wants it when there is no limit', async () => {
        const unlimited = { ...config, capacity: null };
        const response = await handleCreate(post(team({ wish: 'onsite_only' })), site, fakeDeps(), unlimited);

        expect(await response.json()).toEqual({ placement: 'onsite' });
        expect(await (await handleStatus(site, fakeDeps(), unlimited)).json()).toMatchObject({ seats_left: null });
    });

    it('frees the seats of a cancelled team', async () => {
        const deps = fakeDeps();
        await handleCreate(post(team({ members: Array(5).fill({ program: 'SE.ba', real_name: 'X' }) })), site, deps, config);
        const token = tokenFrom(deps.mails[0]);

        expect(await register('onsite_only', 1, 'z')).toBe('waitlist');
        await handleSelf(new Request('https://jam.example/api/registration/self', { method: 'DELETE', headers: { authorization: `Bearer ${token}` } }), site, fakeDeps(), config);
        expect(await register('onsite_only', 1, 'y')).toBe('onsite');
    });
});

describe('changing a registration with the edit link', () => {
    let site;
    let token;
    beforeEach(async () => {
        site = env();
        const deps = fakeDeps();
        await handleCreate(post(team()), site, deps, config);
        token = tokenFrom(deps.mails[0]);
    });

    const self = (method, auth, body) => new Request('https://jam.example/api/registration/self', {
        method,
        headers: { authorization: `Bearer ${auth}`, 'content-type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    });

    it('shows the registration without internal fields', async () => {
        const body = await (await handleSelf(self('GET', token), site, fakeDeps(), config)).json();

        expect(body.registration.team_name).toBe('Pixel Pushers');
        expect(body.registration).not.toHaveProperty('edit_token_hash');
        expect(body.registration).not.toHaveProperty('ip_hash');
    });

    it('rejects a wrong token', async () => {
        expect((await handleSelf(self('GET', 'wrong'), site, fakeDeps(), config)).status).toBe(404);
        expect((await handleSelf(self('GET', ''), site, fakeDeps(), config)).status).toBe(404);
    });

    it('updates members and keeps the seat while the team does not grow', async () => {
        const deps = fakeDeps();
        const response = await handleSelf(self('PUT', token, team({ members: [{ program: 'SE.ba', real_name: 'Only One' }] })), site, deps, config);
        const body = await response.json();

        expect(response.status).toBe(200);
        expect(body.registration.members).toHaveLength(1);
        expect(body.registration.placement).toBe('onsite');
        expect(deps.mails[0].to).toBe('orga@jam.example');
    });

    it('checks the seats again when the team grows beyond the capacity', async () => {
        const members = Array(6).fill({ program: 'SE.ba', real_name: 'X' });
        const body = await (await handleSelf(self('PUT', token, team({ members })), site, fakeDeps(), config)).json();

        expect(body.registration.placement).toBe('remote');
    });

    it('cancels and then forgets the link', async () => {
        const deps = fakeDeps();
        expect((await handleSelf(self('DELETE', token), site, deps, config)).status).toBe(200);
        expect(deps.mails.map((mail) => mail.to)).toEqual(['lead@example.org', 'orga@jam.example']);
        expect((await handleSelf(self('GET', token), site, fakeDeps(), config)).status).toBe(404);
    });
});

describe('admin', () => {
    let site;
    let id;
    beforeEach(async () => {
        site = env();
        await handleCreate(post(team()), site, fakeDeps(), config);
        id = site.JAM_DB.sqlite.prepare('SELECT id FROM registrations').get().id;
    });

    const action = (body) => new Request('https://jam.example/api/admin/registrations', { method: 'POST', body: JSON.stringify(body) });

    it('lists registrations with the seats taken', async () => {
        const body = await (await handleAdminList(site, config)).json();

        expect(body.seats_taken).toBe(2);
        expect(body.registrations[0].members[0].real_name).toBe('Ann Real');
    });

    it('confirms and mails the team, or skips the mail on request', async () => {
        const deps = fakeDeps();
        const body = await (await handleAdminAction(action({ id, action: 'confirm' }), site, deps, config)).json();

        expect(body.registration.status).toBe('confirmed');
        expect(deps.mails[0].subject).toContain('confirmed');
        expect(deps.mails[0].text).toContain('See you there.');

        const quiet = fakeDeps();
        await handleAdminAction(action({ id, action: 'place', placement: 'waitlist', notify: false }), site, quiet, config);
        expect(quiet.mails).toEqual([]);
    });

    it('adds a registration from an earlier form, without Turnstile or deadline, and mails the edit link', async () => {
        const deps = fakeDeps({ turnstile: false, now: Date.parse('2026-12-20T12:00:00+01:00') });
        const body = { action: 'create', status: 'confirmed', registration: team({ team_name: 'Old Form', consent_privacy: false, consent_names: false, turnstile: '' }) };
        const response = await handleAdminAction(action(body), site, deps, config);
        const result = await response.json();

        expect(response.status).toBe(201);
        expect(result.registration).toMatchObject({ team_name: 'Old Form', status: 'confirmed', placement: 'onsite' });
        expect(deps.mails).toHaveLength(1);
        expect(deps.mails[0].subject).toContain('Please check your registration');
        expect(deps.mails[0].text).toContain('/register/edit#');
        expect(deps.mails[0].text).toContain('/imprint#registration');
    });

    it('rejects an added registration without a real name', async () => {
        const body = { action: 'create', registration: team({ members: [{ program: 'SE.ba', real_name: '', nickname: 'X' }] }) };

        expect((await handleAdminAction(action(body), site, fakeDeps(), config)).status).toBe(422);
    });

    it('rejects unknown actions and placements', async () => {
        expect((await handleAdminAction(action({ id, action: 'explode' }), site, fakeDeps(), config)).status).toBe(400);
        expect((await handleAdminAction(action({ id, action: 'place', placement: 'moon' }), site, fakeDeps(), config)).status).toBe(400);
        expect((await handleAdminAction(action({ id: 'nope', action: 'confirm' }), site, fakeDeps(), config)).status).toBe(404);
    });

    it('deletes a registration with its members', async () => {
        await handleAdminAction(action({ id, action: 'delete' }), site, fakeDeps(), config);

        expect(site.JAM_DB.sqlite.prepare('SELECT COUNT(*) AS n FROM members').get().n).toBe(0);
    });

    it('exports only chosen names for the website, and real names only in the list for the organisers', async () => {
        await handleAdminAction(action({ id, action: 'confirm' }), site, fakeDeps(), config);
        const teams = await (await handleAdminExport(new URL('https://jam.example/api/admin/export?format=teams'), site, config)).text();
        const csv = await (await handleAdminExport(new URL('https://jam.example/api/admin/export?placement=onsite'), site, config)).text();

        expect(JSON.parse(teams).teams).toEqual([{ name: 'Pixel Pushers', members: ['Annie', 'Bo Real'] }]);
        expect(teams).not.toContain('Ann Real');
        expect(csv).toContain('"Ann Real","Annie"');
    });

    it('leaves remote people out of the list of people on site', async () => {
        const members = [{ program: 'SE.ba', real_name: 'On Site' }, { program: 'SE.ba', real_name: 'At Home', remote: true }];
        await handleCreate(post(team({ team_name: 'Mixed', members }), '9.9.9.9'), site, fakeDeps(), config);
        const onsite = await (await handleAdminExport(new URL('https://jam.example/api/admin/export?placement=onsite'), site, config)).text();
        const everyone = await (await handleAdminExport(new URL('https://jam.example/api/admin/export'), site, config)).text();

        expect(onsite).toContain('"On Site"');
        expect(onsite).not.toContain('At Home');
        expect(everyone).toContain('"At Home","At Home","yes","SE.ba"');
    });
});

describe('Access token', () => {
    const access = { team: 'jams', aud: 'aud-tag' };
    let keys;
    beforeEach(async () => {
        keys = await crypto.subtle.generateKey(
            { name: 'RSASSA-PKCS1-v1_5', modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
            true,
            ['sign', 'verify'],
        );
    });

    const encode = (value) => Buffer.from(typeof value === 'string' ? value : JSON.stringify(value)).toString('base64url');

    async function sign(claims) {
        const unsigned = `${encode({ alg: 'RS256', kid: 'k1' })}.${encode(claims)}`;
        const signature = await crypto.subtle.sign('RSASSA-PKCS1-v1_5', keys.privateKey, new TextEncoder().encode(unsigned));

        return `${unsigned}.${Buffer.from(signature).toString('base64url')}`;
    }

    const deps = async () => {
        const jwk = { ...(await crypto.subtle.exportKey('jwk', keys.publicKey)), kid: 'k1' };

        return { now: NOW, fetch: async () => Response.json({ keys: [jwk] }) };
    };
    const valid = { iss: 'https://jams.cloudflareaccess.com', aud: ['aud-tag'], exp: NOW / 1000 + 600, email: 'orga@jam.example' };

    it('takes the token from the header, or else from the Access cookie', () => {
        const request = (headers) => new Request('https://jam.example/api/admin/registrations', { headers });

        expect(accessToken(request({ 'cf-access-jwt-assertion': 'a.b.c' }))).toBe('a.b.c');
        expect(accessToken(request({ cookie: 'theme=dark; CF_Authorization=x.y.z; other=1' }))).toBe('x.y.z');
        expect(accessToken(request({ cookie: 'NOT_CF_Authorization=x.y.z' }))).toBeNull();
        expect(accessToken(request({}))).toBeNull();
    });

    it('accepts a valid token and returns the e-mail address', async () => {
        expect(await verifyAccess(await sign(valid), access, await deps())).toBe('orga@jam.example');
    });

    it('rejects missing, expired, foreign and tampered tokens', async () => {
        expect(await verifyAccess(null, access, await deps())).toBeNull();
        expect(await verifyAccess(await sign({ ...valid, exp: NOW / 1000 - 1 }), access, await deps())).toBeNull();
        expect(await verifyAccess(await sign({ ...valid, aud: ['other'] }), access, await deps())).toBeNull();
        expect(await verifyAccess(await sign({ ...valid, iss: 'https://evil.cloudflareaccess.com' }), access, await deps())).toBeNull();

        const [header, , signature] = (await sign(valid)).split('.');
        expect(await verifyAccess(`${header}.${encode({ ...valid, email: 'evil@x' })}.${signature}`, access, await deps())).toBeNull();
    });
});
