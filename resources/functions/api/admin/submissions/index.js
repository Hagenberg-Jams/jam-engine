import { handleAdminList } from '../../_submission-logic.js';
import config from '../../_submission-config.json';

export const onRequestGet = ({ env }) => handleAdminList(env, config);
