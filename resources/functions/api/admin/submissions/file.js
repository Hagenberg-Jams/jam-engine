import { handleAdminFile } from '../../_submission-logic.js';
import config from '../../_submission-config.json';

export const onRequestGet = ({ request, env }) => handleAdminFile(new URL(request.url), env, config);
