import { verifyAccess } from '../_access.js';
import { json } from '../_registration-logic.js';
import config from '../_registration-config.json';

export async function onRequest({ request, next }) {
    const email = await verifyAccess(request.headers.get('cf-access-jwt-assertion'), config.access, { fetch, now: Date.now() });

    return email ? next() : json(401, { error: 'unauthorized' });
}
