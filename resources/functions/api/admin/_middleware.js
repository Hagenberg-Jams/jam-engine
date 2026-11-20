import { accessToken, verifyAccess } from '../_access.js';
import { json } from '../_shared.js';
import access from '../_access-config.json';

export async function onRequest({ request, next }) {
    const email = await verifyAccess(accessToken(request), access, { fetch: (...args) => fetch(...args), now: Date.now() });

    return email ? next() : json(401, { error: 'unauthorized' });
}
