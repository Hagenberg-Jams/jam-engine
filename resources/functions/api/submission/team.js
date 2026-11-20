import { handleTeam } from '../_submission-logic.js';
import config from '../_submission-config.json';

export const onRequestPost = ({ request, env }) => handleTeam(request, env, { now: Date.now() }, config);
