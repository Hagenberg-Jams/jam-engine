import { describe, expect, it } from 'vitest';
import {
    camel,
    checkReadme,
    fileName,
    handleAbort,
    handleAdminFile,
    handleAdminList,
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
            { heading: 'Team Members', aliases: ['Members'], required: true },
            { heading: 'Description', required: true },
            { heading: 'Controls', required: true },
            { heading: 'How to Play', required: false },
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

// Laid out like the READMEs of the Game Jam 2025.
const README = `# Most Awesome Game

## Team

The Awesomes

## Team Members

- Ann Example
- Bo Tester

## Description

<!-- What is the setting, what must the player do to win? -->
You are a cat in space. Collect all fish to win.

## Controls

- **Movement:** WASD
- **Jump:** Space

## How to Play

Run \`npm install\` and \`npm run dev\`, or play it online.
`;

const TEMPLATE = `# Game name

<!-- Replace "Game name" with the name of your game. Fill in every section in English, keep the headings, and save the file as UTF-8. -->

## Team

The Awesomes

## Team Members

<!-- One person per line, with the name that should appear on the website. -->
-

## Description

<!-- In English: what is the setting or story, and what must the player do to win? -->

## Controls

<!-- One control per line, for example: - **Movement:** WASD -->
-

## How to Play

<!-- Optional: anything else players need to know. Delete this section if you do not need it. -->
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
            { code: 'empty', section: 'Team Members' },
            { code: 'empty', section: 'Description' },
            { code: 'empty', section: 'Controls' },
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

        expect((await teams({ JAM_DB: db }, { ...gameConfig, teams: null })).map((team) => team.slug)).toEqual(['ant-hill', 'zebra-games']);
        // A list of the site wins, even an empty one, for example while the teams are not known yet.
        expect(await teams({ JAM_DB: db }, { ...gameConfig, teams: [] })).toEqual([]);
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

describe('admin', () => {
    it('lists every team with its uploads, README and screenshots, also teams without upload and unknown folders', async () => {
        const e = env();
        await upload(e, fakeDeps(), gameConfig, { version: '1.0.0', readme: README, screenshots: screenshots(4) });
        await upload(e, fakeDeps({ now: LATE }), gameConfig, { version: '1.0.1' }, 'fixed');
        await e.JAM_SUBMISSIONS.put('2026/old-team/uploads/Old_Game_Win_1.0.0.zip', 'x', { customMetadata: { team: 'Old Team', platform: 'Win', version: '1.0.0', late: 'yes' } });
        await e.JAM_SUBMISSIONS.put('2025/the-awesomes/uploads/Last_Year_Win_1.0.0.zip', 'x', { customMetadata: { platform: 'Win', version: '1.0.0' } });

        const body = await (await handleAdminList(e, gameConfig)).json();
        const [old, pirates, awesomes] = body.teams;

        expect(body.teams.map((team) => team.slug)).toEqual(['old-team', 'pixel-pirates', 'the-awesomes']);
        expect(old).toMatchObject({ name: 'Old Team', listed: false, onTime: false });
        expect(pirates).toMatchObject({ listed: true, uploads: [], readme: null, screenshots: [] });
        expect(awesomes).toMatchObject({ latest: { Win: '1.0.1' }, onTime: true, readmes: 1 });
        expect(awesomes.uploads.map((upload) => [upload.version, upload.latest, upload.late])).toEqual([['1.0.1', true, true], ['1.0.0', false, false]]);
        expect(awesomes.uploads[0].key).toBe('2026/the-awesomes/uploads/TheAwesomes_MostAwesomeGame_Win_1.0.1.zip');
        expect(awesomes.readme).toMatchObject({ key: '2026/the-awesomes/README.md', title: 'Most Awesome Game', text: README });
        expect(awesomes.screenshots).toHaveLength(4);
        expect(awesomes.screenshots[0]).toMatch(/^2026\/the-awesomes\/screenshots\/.+\/1\.png$/);
    });

    it('sends files as downloads, shows only images and the README in the page, and stays in the year', async () => {
        const e = env();
        await e.JAM_SUBMISSIONS.put('2026/a/uploads/A_Game_Win_1.0.0.zip', 'zip', { httpMetadata: { contentType: 'application/zip' } });
        await e.JAM_SUBMISSIONS.put('2026/a/screenshots/s/1.png', 'png', { httpMetadata: { contentType: 'image/png' } });
        await e.JAM_SUBMISSIONS.put('2026/a/README.md', '# A', { httpMetadata: { contentType: 'text/markdown; charset=utf-8' } });
        await e.JAM_SUBMISSIONS.put('2025/a/README.md', '# Old');
        const get = (query) => handleAdminFile(new URL(`https://jam.example/api/admin/submissions/file?${query}`), e, gameConfig);

        const zip = await get('key=2026/a/uploads/A_Game_Win_1.0.0.zip&inline=1');
        expect(zip.headers.get('content-disposition')).toBe('attachment; filename="A_Game_Win_1.0.0.zip"');
        expect(await zip.text()).toBe('zip');

        expect((await get('key=2026/a/screenshots/s/1.png&inline=1')).headers.get('content-disposition')).toBe('inline; filename="1.png"');
        const readme = await get('key=2026/a/README.md&inline=1');
        expect(readme.headers.get('content-type')).toBe('text/plain; charset=utf-8');
        expect(readme.headers.get('content-security-policy')).toContain('sandbox');

        expect((await get('key=2025/a/README.md')).status).toBe(400);
        expect((await get('key=2026/../2025/a/README.md')).status).toBe(400);
        expect((await get('key=2026/a/missing.zip')).status).toBe(404);
    });
});
