import { handleAdminExport } from '../_registration-logic.js';
import config from '../_registration-config.json';

export const onRequestGet = ({ request, env }) => handleAdminExport(new URL(request.url), env, config);
