/**
 * Reads the JAM_TOPIC_DATA secret, which the site workflow writes as {"year", "topic", "video"}.
 * A topic for another year counts as missing, so a secret left over from an earlier jam is never shown.
 */
function topicFor(env, year) {
    try {
        const data = JSON.parse(env.JAM_TOPIC_DATA ?? '');

        return data && String(data.year) === String(year) && data.topic ? data : null;
    } catch {
        return null;
    }
}

/**
 * Answers the topic request. The topic and video only leave the server after the release time.
 *
 * @param {{ JAM_TOPIC_DATA?: string }} env Cloudflare secrets
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

    const data = topicFor(env, config.year);

    if (!data) {
        return json(503, { released: true, error: 'topic not configured' });
    }

    return json(200, { released: true, year: config.year, topic: data.topic, video: data.video || null });
}
