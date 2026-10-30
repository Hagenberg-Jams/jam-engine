import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.js',
    fullyParallel: true,
    reporter: 'list',
    globalSetup: './global-setup.js',
    use: { baseURL: 'http://127.0.0.1:8123' },
    webServer: {
        command: 'node tests/browser/server.js',
        cwd: '../..',
        url: 'http://127.0.0.1:8123/healthz',
        reuseExistingServer: false,
    },
});
