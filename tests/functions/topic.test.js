import { describe, expect, it } from 'vitest';
import { respond } from '../../resources/functions/api/_topic-logic.js';

const config = { year: 2026, release: '2026-11-20T18:00:00+01:00' };
const release = Date.parse(config.release);
const secret = (data) => ({ JAM_TOPIC_DATA: JSON.stringify(data) });
const env = secret({ year: 2026, topic: 'Secret Topic', video: 'secretVideo1' });

describe('topic function', () => {
    it('keeps topic and video secret before the release', async () => {
        const response = respond(env, release - 1, config);
        const text = await response.text();

        expect(response.status).toBe(403);
        expect(text).not.toContain('Secret Topic');
        expect(text).not.toContain('secretVideo1');
    });

    it('returns topic and video from the exact release time on', async () => {
        const response = respond(env, release, config);

        expect(response.status).toBe(200);
        expect(await response.json()).toMatchObject({ released: true, year: 2026, topic: 'Secret Topic', video: 'secretVideo1' });
    });

    it('sends the server time so that clients can correct their clock', async () => {
        expect((await respond(env, release - 5000, config).json()).now).toBe(release - 5000);
    });

    it('is never cached, neither before nor after the release', () => {
        expect(respond(env, release - 1, config).headers.get('cache-control')).toBe('no-store');
        expect(respond(env, release, config).headers.get('cache-control')).toBe('no-store');
    });

    it('works without a video', async () => {
        expect((await respond(secret({ year: 2026, topic: 'Topic', video: null }), release, config).json()).video).toBeNull();
    });

    it('reports a missing secret instead of an empty topic', async () => {
        const response = respond({}, release, config);

        expect(response.status).toBe(503);
        expect((await response.json()).topic).toBeUndefined();
    });

    it('ignores a topic left over from an earlier jam', async () => {
        const response = respond(secret({ year: 2025, topic: 'Old Topic', video: 'oldVideo123' }), release, config);
        const text = await response.text();

        expect(response.status).toBe(503);
        expect(text).not.toContain('Old Topic');
        expect(text).not.toContain('oldVideo123');
    });

    it('treats a malformed secret as missing', () => {
        expect(respond({ JAM_TOPIC_DATA: 'Secret Topic' }, release, config).status).toBe(503);
    });

    it('fails closed on an invalid release time', async () => {
        const response = respond(env, release, { year: 2026, release: 'nonsense' });

        expect(response.status).toBe(500);
        expect(await response.text()).not.toContain('Secret Topic');
    });
});
