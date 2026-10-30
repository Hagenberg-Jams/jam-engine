import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '.build/sites');
const types = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript' };

createServer(async (request, response) => {
    if (request.url === '/healthz') {
        response.end('ok');
        return;
    }

    const path = normalize(decodeURIComponent(new URL(request.url, 'http://x').pathname));
    try {
        const body = await readFile(join(root, path));
        response.writeHead(200, { 'content-type': types[extname(path)] ?? 'application/octet-stream' });
        response.end(body);
    } catch {
        response.writeHead(404);
        response.end('not found');
    }
}).listen(8123, '127.0.0.1');
