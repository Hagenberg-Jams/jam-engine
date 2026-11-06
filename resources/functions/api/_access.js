/**
 * Checks the token that Cloudflare Access adds to every request it lets through.
 *
 * Access already blocks /admin and /api/admin. This check makes sure the admin API stays closed
 * even if the Access application is changed or a path is missing from it.
 */

const decode = (part) => Uint8Array.from(atob(part.replace(/-/g, '+').replace(/_/g, '/')), (char) => char.charCodeAt(0));
const parse = (part) => JSON.parse(new TextDecoder().decode(decode(part)));

/**
 * @param {string|null} token The Cf-Access-Jwt-Assertion header
 * @param {{ team: string, aud: string }} access From the site config
 * @param {{ fetch: typeof fetch, now: number }} deps
 * @returns {Promise<string|null>} The e-mail address of the organiser, or null
 */
export async function verifyAccess(token, access, deps) {
    if (!token || !access.team || !access.aud) {
        return null;
    }

    try {
        const [header, payload, signature] = token.split('.');
        const { kid, alg } = parse(header);
        const claims = parse(payload);
        const issuer = `https://${access.team}.cloudflareaccess.com`;
        const audiences = Array.isArray(claims.aud) ? claims.aud : [claims.aud];

        if (alg !== 'RS256' || claims.iss !== issuer || !audiences.includes(access.aud) || claims.exp * 1000 < deps.now) {
            return null;
        }

        const { keys } = await (await deps.fetch(`${issuer}/cdn-cgi/access/certs`)).json();
        const jwk = keys.find((key) => key.kid === kid);
        if (!jwk) {
            return null;
        }

        const key = await crypto.subtle.importKey('jwk', jwk, { name: 'RSASSA-PKCS1-v1_5', hash: 'SHA-256' }, false, ['verify']);
        const valid = await crypto.subtle.verify('RSASSA-PKCS1-v1_5', key, decode(signature), new TextEncoder().encode(`${header}.${payload}`));

        return valid ? (claims.email ?? 'unknown') : null;
    } catch {
        return null;
    }
}
