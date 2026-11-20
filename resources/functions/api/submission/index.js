import { handleStatus } from '../_submission-logic.js';
import config from '../_submission-config.json';

export const onRequestGet = ({ env }) => handleStatus(env, { now: Date.now() }, config);
