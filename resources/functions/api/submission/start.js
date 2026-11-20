import { handleStart } from '../_submission-logic.js';
import config from '../_submission-config.json';

const deps = () => ({ fetch: (...args) => fetch(...args), now: Date.now() });

export const onRequestPost = ({ request, env }) => handleStart(request, env, deps(), config);
