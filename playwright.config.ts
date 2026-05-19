import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    globalSetup: './tests/e2e/global-setup.ts',
    testDir: './tests/e2e',
    timeout: 30_000,
    retries: 1,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8080',
        // Album data is fetched from Google Photos on first load and cached by WP.
        // On subsequent runs the cache is served locally with no network needed.
        // A longer timeout accommodates a cold (uncached) first run.
        actionTimeout: 15_000,
        navigationTimeout: 20_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
