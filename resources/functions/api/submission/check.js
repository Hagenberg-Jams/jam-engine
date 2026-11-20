import { handleCheck } from '../_submission-logic.js';
import config from '../_submission-config.json';

export const onRequestPost = ({ request }) => handleCheck(request, config);
