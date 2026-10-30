/**
 * Answers the topic request. The topic and video only leave the server after the release time.
 *
 * @param {{ JAM_TOPIC?: string, JAM_TOPIC_VIDEO?: string }} env Cloudflare secrets
 * @param {number} now Current time in milliseconds
 * @param {{ year: number, release: string }} config Generated from the jam's YAML file
 */
export function respond(env, now, config) {
    const release = Date.parse(config.release);
    const headers = { 'content-type': 'application/json', 'cache-control': 'no-store' };
    const json = (status, body) => new Response(JSON.stringify({ now, ...body }), { status, headers });

    if (Number.isNaN(release)) {
        return json(500, { error: 'invalid release time' });
    }

    if (now < release) {
        return json(403, { released: false, release });
    }

    if (!env.JAM_TOPIC) {
        return json(503, { released: true, error: 'topic not configured' });
    }

    return json(200, { released: true, year: config.year, topic: env.JAM_TOPIC, video: env.JAM_TOPIC_VIDEO || null });
}
