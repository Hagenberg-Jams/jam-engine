import { handleCreate, handleStatus } from '../_registration-logic.js';
import config from '../_registration-config.json';

const deps = () => ({ fetch, now: Date.now() });

export const onRequestGet = ({ env }) => handleStatus(env, deps(), config);
export const onRequestPost = ({ request, env }) => handleCreate(request, env, deps(), config);
