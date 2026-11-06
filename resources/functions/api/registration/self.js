import { handleSelf } from '../_registration-logic.js';
import config from '../_registration-config.json';

export const onRequest = ({ request, env }) => handleSelf(request, env, { fetch: (...args) => fetch(...args), now: Date.now() }, config);
