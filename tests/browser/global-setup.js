import { execFileSync } from 'node:child_process';
import { cpSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';

const here = import.meta.dirname;
const engine = resolve(here, '../..');
const output = join(here, '.build/sites');

// Pages are served from a sub-folder per site, so root-relative URLs need that prefix.
function prefixUrls(dir, prefix) {
    for (const name of readdirSync(dir)) {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) {
            prefixUrls(path, prefix);
        } else if (name.endsWith('.html')) {
            writeFileSync(path, readFileSync(path, 'utf8').replace(/(href|src)="\/(?!\/)/g, `$1="/${prefix}/`));
        }
    }
}

function build(fixture, now) {
    const site = mkdtempSync(join(tmpdir(), 'jam-browser-'));
    cpSync(join(engine, 'tests/Fixtures/skeleton'), site, { recursive: true });
    cpSync(join(engine, 'tests/Fixtures', fixture), site, { recursive: true });
    for (const dir of ['_pages', '_media', '_posts', '_docs', 'resources/views', 'app/storage/framework/views', 'app/storage/framework/cache/data', 'app/storage/app']) {
        mkdirSync(join(site, dir), { recursive: true });
    }
    cpSync(join(here, '.build/assets/app.css'), join(site, '_media/app.css'));
    cpSync(join(here, '.build/assets/app.js'), join(site, '_media/app.js'));

    execFileSync('php', ['-d', 'display_errors=0', 'hyde', 'build', '--no-interaction'], {
        cwd: site,
        env: { ...process.env, JAM_ENGINE_ROOT: engine, JAM_TEST_NOW: now },
        stdio: 'pipe',
        shell: process.platform === 'win32',
    });

    const target = join(output, fixture);
    cpSync(join(site, '_site'), target, { recursive: true });
    prefixUrls(target, fixture);
}

export default function globalSetup() {
    rmSync(output, { recursive: true, force: true });
    mkdirSync(dirname(output), { recursive: true });
    execFileSync(process.platform === 'win32' ? 'npx.cmd' : 'npx', ['vite', 'build', '--config', 'tests/browser/vite.config.js'], { cwd: engine, stdio: 'pipe', shell: process.platform === 'win32' });
    build('game', '2026-01-10T12:00:00+01:00');
    build('animation', '2026-11-10T12:00:00+01:00');
}
