import { handleScreenshot } from '../_submission-logic.js';

export const onRequestPut = ({ request, env }) => handleScreenshot(request, env, { now: Date.now() });
