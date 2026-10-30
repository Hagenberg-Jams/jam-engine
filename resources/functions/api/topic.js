import { respond } from './_topic-logic.js';
import config from './_topic-config.json';

export const onRequestGet = ({ env }) => respond(env, Date.now(), config);
