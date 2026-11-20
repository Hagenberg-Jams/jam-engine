import { handlePart } from '../_submission-logic.js';

export const onRequestPut = ({ request, env }) => handlePart(request, env, { now: Date.now() });
