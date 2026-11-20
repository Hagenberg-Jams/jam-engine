import { handleAbort } from '../_submission-logic.js';

export const onRequestPost = ({ request, env }) => handleAbort(request, env, { now: Date.now() });
