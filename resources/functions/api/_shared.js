/**
 * Helpers that the registration and the submission functions share.
 */

const headers = { 'content-type': 'application/json', 'cache-control': 'no-store' };
export const json = (status, body) => new Response(JSON.stringify(body), { status, headers });

export async function sha256(value) {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value));

    return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

/**
 * Links in mails point to the host the request came from, so that a preview sends preview links.
 */
export function forRequest(request, config) {
    return { ...config, siteUrl: new URL(request.url).origin };
}

export async function verifyTurnstile(deps, env, token, ip) {
    const body = new URLSearchParams({ secret: env.TURNSTILE_SECRET ?? '', response: token ?? '', remoteip: ip ?? '' });
    const response = await deps.fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', { method: 'POST', body });
    const result = await response.json().catch(() => ({}));

    return result.success === true;
}

/**
 * Sends a mail through the Cloudflare Email Service. A failed mail never fails the request that sends it.
 * A mail to several organisers goes to each one separately, so one bad address does not stop the others.
 */
export async function sendMail(deps, env, config, mail) {
    if (Array.isArray(mail.to)) {
        const results = [];
        for (const to of mail.to) {
            results.push(await sendMail(deps, env, config, { ...mail, to }));
        }

        return results.length > 0 && results.every(Boolean);
    }

    if (!env.EMAIL_API_TOKEN || !env.CF_ACCOUNT_ID || !config.from || !mail.to) {
        return false;
    }

    try {
        const response = await deps.fetch(`https://api.cloudflare.com/client/v4/accounts/${env.CF_ACCOUNT_ID}/email/sending/send`, {
            method: 'POST',
            headers: { authorization: `Bearer ${env.EMAIL_API_TOKEN}`, 'content-type': 'application/json' },
            body: JSON.stringify({ from: { address: config.from, name: config.siteName }, reply_to: config.replyTo || undefined, ...mail }),
        });
        if (!response.ok) {
            // Shows up in the logs of the Pages project, without the content of the mail.
            console.error(`Mail "${mail.subject}" failed with ${response.status}: ${(await response.text().catch(() => '')).slice(0, 300)}`);
        }

        return response.ok;
    } catch (error) {
        console.error(`Mail "${mail.subject}" failed: ${error}`);
        return false;
    }
}
