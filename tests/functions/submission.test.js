import { describe, expect, it } from 'vitest';
import {
    camel,
    checkReadme,
    fileName,
    handleAbort,
    handleCheck,
    handleComplete,
    handlePart,
    handleScreenshot,
    handleStart,
    handleStatus,
    handleTeam,
    slug,
    suggestVersion,
    teams,
} from '../../resources/functions/api/_submission-logic.js';
import { createD1 } from './d1.js';
import { createR2 } from './r2.js';

const NOW = Date.parse('2026-12-13T20:30:00+01:00');
const LATE = Date.parse('2026-12-13T21:30:00+01:00');

const gameConfig = {
    year: 2026,
    siteName: 'Test Jam',
    siteUrl: 'https://jam.example',
    active: true,
    kind: 'game',
    platforms: ['Win', 'Mac', 'Linux', 'Web'],
    extensions: ['zip'],
    maxSize: 4 * 1024 * 1024 * 1024,
    deadline: '2026-12-13T21:00:00+01:00',
    closes: '2027-01-31T23:59:00+01:00',
    screenshots: { min: 4, max: 8, width: 1920, height: 1080 },
    readme: {
        title: 'Game name',
        sections: [
            { heading: 'Team', required: true },
            { heading: 'Team members', aliases: ['Members'], required: true },
            { heading: 'Description', required: true },
            { heading: 'How to play', aliases: ['Controls'], required: true },
            { heading: 'Credits', required: false },
        ],
    },
    teams: [{ name: 'The Awesomes', slug: 'the-awesomes' }, { name: 'Pixel Pirates', slug: 'pixel-pirates' }],
    notify: ['orga@jam.example'],
    from: 'noreply@jam.example',
    replyTo: 'info@jam.example',
};

const videoConfig = {
    ...gameConfig,
    kind: 'video',
    platforms: [],
    extensions: ['mp4', 'mov'],
    screenshots: { min: 1, max: 6, width: 1920, height: 1080 },
    readme: { ...gameConfig.readme, title: 'Animation title' },
};

const README = `# Most Awesome Game

## Team
The Awesomes

## Team members
- Ann Example
- Bo Tester

## Description
<!-- What is the setting, what must the player do to win? -->
You are a cat in space. Collect all fish to win.

## How to play
WASD to move, Space to jump.

## Credits
`;

const TEMPLATE = `# Game name
<!-- Replace "Game name" with the name of your game. -->

## Team
The Awesomes

## Team members
<!-- One person per line. -->
-

## Description
<!-- What is the setting, what must the player do to win? -->

## How to play
<!-- Controls, or how to play. -->
`;

const env = () => ({ JAM_SUBMISSIONS: createR2(), JAM_SUBMISSION_CODE: 'Pixel-2026', TURNSTILE_SECRET: 'secret', EMAIL_API_TOKEN: 'mail', CF_ACCOUNT_ID: 'account' });

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

const post = (path, body, token) => new Request(`https://jam.example/api/submission/${path}`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', ...(token ? { authorization: `Bearer ${token}` } : {}) },
    body: JSON.stringify(body),
});

const put = (path, body, token, type = 'application/octet-stream') => new Request(`https://jam.example/api/submission/${path}`, {
    method: 'PUT',
    headers: { authorization: `Bearer ${token}`, 'content-type': type, 'content-length': String(body.length) },
    body,
});

const screenshots = (count) => Array.from({ length: count }, () => ({ type: 'image/png', size: 4 }));

/** Runs a whole upload as the page does: start, parts, screenshots, complete. */
async function upload(e, deps, config, input, content = 'PK-zip-content') {
    const bytes = new TextEncoder().encode(content);
    const start = await handleStart(post('start', { code: 'pixel-2026 ', turnstile: 'ok', team: 'the-awesomes', platform: 'Win', extension: 'zip', size: bytes.length, ...input }), e, deps, config);
    const started = await start.json();
    if (start.status !== 201) {
        return { status: start.status, body: started };
    }

    const part = await (await handlePart(put('part?n=1', bytes, started.token), e, deps)).json();
    for (let i = 1; i <= (input.screenshots ?? []).length; i++) {
        await handleScreenshot(put(`screenshot?i=${i}`, new Uint8Array([1, 2, 3, 4]), started.token, 'image/png'), e, deps);
    }
    const complete = await handleComplete(post('complete', { parts: [part.part] }, started.token), e, deps, config);

    return { status: complete.status, body: await complete.json(), started };
}

describe('names and versions', () => {
    it('builds file names from team, title, platform and version', () => {
        expect(slug('Die Pixel-Piraten!')).toBe('die-pixel-piraten');
        expect(camel('the awesomes')).toBe('TheAwesomes');
        expect(camel('größte Äpfel 2')).toBe('GrossteApfel2');
        expect(fileName(gameConfig, { team: 'The Awesomes', title: 'Most Awesome Game', platform: 'Win', version: '1.0.0', extension: 'zip' }))
            .toBe('TheAwesomes_MostAwesomeGame_Win_1.0.0.zip');
        expect(fileName(videoConfig, { team: 'The Awesomes', title: 'Night Shift', platform: 'video', version: '1.0.1', extension: 'mp4' }))
            .toBe('TheAwesomes_NightShift_1.0.1.mp4');
    });

    it('suggests 1.0.0 first and then raises the last number', () => {
        expect(suggestVersion(undefined)).toBe('1.0.0');
        expect(suggestVersion('1.0.9')).toBe('1.0.10');
        expect(suggestVersion('2.1.0')).toBe('2.1.1');
    });
});

describe('README check', () => {
    it('accepts a filled README and reads the title', () => {
        expect(checkReadme(README, gameConfig)).toEqual({ title: 'Most Awesome Game', errors: [] });
    });

    it('rejects the unchanged template, because hints and empty list items are no content', () => {
        const { errors } = checkReadme(TEMPLATE, gameConfig);

        expect(errors).toEqual([
            { code: 'title', section: 'Game name' },
            { code: 'empty', section: 'Team members' },
            { code: 'empty', section: 'Description' },
            { code: 'empty', section: 'How to play' },
        ]);
    });

    it('names missing sections, accepts other spellings, and lets optional ones out', () => {
        const readme = '# Cat Quest\n\n## TEAM:\nThe Awesomes\n\n## Members\n- Ann\n\n## Controls\nMouse';

        expect(checkReadme(readme, gameConfig).errors).toEqual([{ code: 'missing', section: 'Description' }]);
    });

    it('answers the check from the page', async () => {
        const response = await handleCheck(post('check', { readme: TEMPLATE }), gameConfig);

        expect((await response.json()).errors).toHaveLength(4);
    });
});

describe('teams', () => {
    it('uses the list of the site', async () => {
        const response = await handleStatus(env(), fakeDeps(), gameConfig);
        const body = await response.json();

        expect(body).toMatchObject({ open: true, late: false });
        expect(body.teams.map((team) => team.name)).toEqual(['Pixel Pirates', 'The Awesomes']);
    });

    it('falls back to the confirmed registrations of the year', async () => {
        const db = createD1();
        const insert = (id, name, status, year = 2026) => db.sqlite.prepare(`INSERT INTO registrations (id, year, team_name, contact_email, wish, placement, status,
            people, edit_token_hash, consent_at, created_at, updated_at, purge_after) VALUES (?, ?, ?, 'a@b.c', 'remote', 'remote', ?, 1, ?, 0, 0, 0, 0)`)
            .run(id, year, name, status, `hash-${id}`);
        insert('1', 'Zebra Games', 'confirmed');
        insert('2', 'Ant Hill', 'confirmed');
        insert('3', 'Declined Team', 'declined');
        insert('4', 'Old Team', 'confirmed', 2025);

        expect((await teams({ JAM_DB: db }, { ...gameConfig, teams: [] })).map((team) => team.slug)).toEqual(['ant-hill', 'zebra-games']);
    });
});

describe('uploads', () => {
    it('needs the code', async () => {
        const response = await handleTeam(post('team', { code: 'wrong', team: 'the-awesomes' }), env(), fakeDeps(), gameConfig);

        expect(response.status).toBe(403);
    });

    it('stores the first upload with README and screenshots, and tells the organisers', async () => {
        const e = env();
        const deps = fakeDeps();
        const result = await upload(e, deps, gameConfig, { version: '1.0.0', readme: README, screenshots: screenshots(4) });
        const bucket = e.JAM_SUBMISSIONS;

        expect(result.status).toBe(200);
        expect(result.body).toMatchObject({ file: 'TheAwesomes_MostAwesomeGame_Win_1.0.0.zip', version: '1.0.0', late: false });
        expect(await (await bucket.get('2026/the-awesomes/uploads/TheAwesomes_MostAwesomeGame_Win_1.0.0.zip')).text()).toBe('PK-zip-content');
        expect((await bucket.head('2026/the-awesomes/uploads/TheAwesomes_MostAwesomeGame_Win_1.0.0.zip')).customMetadata)
            .toMatchObject({ team: 'The Awesomes', platform: 'Win', version: '1.0.0', late: 'no' });
        expect(await (await bucket.get('2026/the-awesomes/README.md')).text()).toBe(README);
        expect((await (await bucket.get('2026/the-awesomes/screenshots/current.json')).json()).files).toHaveLength(4);
        expect(deps.mails).toHaveLength(1);
        expect(deps.mails[0]).toMatchObject({ to: 'orga@jam.example', subject: 'Upload: The Awesomes, Most Awesome Game Win 1.0.0' });
    });

    it('suggests the next version and takes a bugfix without README and screenshots', async () => {
        const e = env();
        await upload(e, fakeDeps(), gameConfig, { version: '1.0.0', readme: README, screenshots: screenshots(4) });
        const state = await (await handleTeam(post('team', { code: 'Pixel-2026', team: 'the-awesomes' }), e, fakeDeps(), gameConfig)).json();

        expect(state).toMatchObject({ latest: { Win: '1.0.0' }, suggested: { Win: '1.0.1', Mac: '1.0.0' }, readme: { title: 'Most Awesome Game' }, screenshots: 4 });

        const again = await upload(e, fakeDeps(), gameConfig, { version: '1.0.0' });
        expect(again.status).toBe(422);
        expect(again.body.errors).toEqual([{ code: 'version_low', latest: '1.0.0' }]);

        const bugfix = await upload(e, fakeDeps({ now: LATE }), gameConfig, { version: '1.0.1' }, 'fixed');
        expect(bugfix.status).toBe(200);
        expect(bugfix.body).toMatchObject({ file: 'TheAwesomes_MostAwesomeGame_Win_1.0.1.zip', late: true });
        expect(e.JAM_SUBMISSIONS.objects.has('2026/the-awesomes/uploads/TheAwesomes_MostAwesomeGame_Win_1.0.0.zip')).toBe(true);
    });

    it('needs a filled README and the screenshots for the first upload', async () => {
        const missing = await upload(env(), fakeDeps(), gameConfig, { version: '1.0.0' });
        expect(missing.body.errors.map((error) => error.code)).toEqual(['readme_required', 'screenshots_required']);

        const template = await upload(env(), fakeDeps(), gameConfig, { version: '1.0.0', readme: TEMPLATE, screenshots: screenshots(2) });
        expect(template.body.errors.map((error) => error.code)).toEqual(['title', 'empty', 'empty', 'empty', 'screenshots_count']);
    });

    it('checks platform, file type, size, spam check and the end of uploads', async () => {
        const wrong = await upload(env(), fakeDeps(), gameConfig, { version: 'one', platform: 'Amiga', extension: 'exe', readme: README, screenshots: screenshots(4) });
        expect(wrong.body.errors.map((error) => error.code)).toEqual(['platform', 'extension', 'version']);

        const bot = await upload(env(), fakeDeps({ turnstile: false }), gameConfig, { version: '1.0.0', readme: README, screenshots: screenshots(4) });
        expect(bot).toMatchObject({ status: 400, body: { error: 'turnstile' } });

        const closed = await upload(env(), fakeDeps({ now: Date.parse('2027-02-01T00:00:00+01:00') }), gameConfig, { version: '1.0.0' });
        expect(closed).toMatchObject({ status: 403, body: { error: 'closed' } });
    });

    it('refuses a changed session and a part out of range', async () => {
        const e = env();
        const deps = fakeDeps();
        const start = await (await handleStart(post('start', { code: 'Pixel-2026', turnstile: 'ok', team: 'the-awesomes', platform: 'Win', extension: 'zip', size: 10, version: '1.0.0', readme: README, screenshots: screenshots(4) }), e, deps, gameConfig)).json();
        const [payload, signature] = start.token.split('.');
        const changed = btoa(atob(payload.replace(/-/g, '+').replace(/_/g, '/')).replace('the-awesomes', 'pixel-pirates')).replace(/=+$/, '');

        expect((await handlePart(put('part?n=1', new Uint8Array(10), `${changed}.${signature}`), e, deps)).status).toBe(401);
        expect((await handlePart(put('part?n=2', new Uint8Array(10), start.token), e, deps)).status).toBe(400);
        expect((await handleScreenshot(put('screenshot?i=5', new Uint8Array(4), start.token, 'image/png'), e, deps)).status).toBe(400);
        expect((await handleAbort(post('abort', {}, start.token), e, deps)).status).toBe(200);
        expect(e.JAM_SUBMISSIONS.uploads.size).toBe(0);
    });

    it('refuses an upload whose size does not match', async () => {
        const e = env();
        const deps = fakeDeps();
        const start = await (await handleStart(post('start', { code: 'Pixel-2026', turnstile: 'ok', team: 'the-awesomes', platform: 'Win', extension: 'zip', size: 100, version: '1.0.0', readme: README, screenshots: [] }), e, deps, { ...gameConfig, screenshots: { min: 0, max: 8 } })).json();
        const part = await (await handlePart(put('part?n=1', new Uint8Array(10), start.token), e, deps)).json();
        const complete = await handleComplete(post('complete', { parts: [part.part] }, start.token), e, deps, gameConfig);

        expect(complete.status).toBe(422);
        expect(e.JAM_SUBMISSIONS.objects.has(`2026/the-awesomes/uploads/${start.file}`)).toBe(false);
    });

    it('names a video without a platform', async () => {
        const readme = README.replace('Most Awesome Game', 'Night Shift');
        const result = await upload(env(), fakeDeps(), videoConfig, { version: '1.0.0', extension: 'mp4', platform: 'Win', readme, screenshots: screenshots(1) });

        expect(result.body.file).toBe('TheAwesomes_NightShift_1.0.0.mp4');
    });
});
