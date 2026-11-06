import { handleAdminAction, handleAdminList } from '../_registration-logic.js';
import config from '../_registration-config.json';

export const onRequestGet = ({ env }) => handleAdminList(env, config);
export const onRequestPost = ({ request, env }) => handleAdminAction(request, env, { fetch, now: Date.now() }, config);
