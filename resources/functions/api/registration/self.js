import { handleSelf } from '../_registration-logic.js';
import config from '../_registration-config.json';

export const onRequest = ({ request, env }) => handleSelf(request, env, { fetch, now: Date.now() }, config);
