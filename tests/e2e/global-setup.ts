import { request } from '@playwright/test';

const BASE_URL      = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8080';
const FIXTURE_SLUG  = 'lightbox-fixture';
const EXPECTED_ALBUMS = 5;

export default async function globalSetup() {
    const ctx = await request.newContext({ baseURL: BASE_URL });

    let res: Awaited<ReturnType<typeof ctx.get>>;
    try {
        res = await ctx.get(`/?pagename=${FIXTURE_SLUG}`);
    } catch {
        throw new Error(
            `Cannot reach WordPress at ${BASE_URL}.\n` +
            'Start the dev server before running e2e tests.'
        );
    }

    if (res.status() === 404) {
        throw new Error(
            `Fixture page not found: ${BASE_URL}/?pagename=${FIXTURE_SLUG}\n\n` +
            'Create a published WordPress page with slug "lightbox-fixture" containing\n' +
            'the five shortcodes described in tests/e2e/README.md.'
        );
    }

    if (!res.ok()) {
        throw new Error(`Fixture page returned unexpected HTTP ${res.status()}.`);
    }

    const body  = await res.text();
    const count = (body.match(/class="jzsa-album/g) ?? []).length;
    if (count < EXPECTED_ALBUMS) {
        throw new Error(
            `Fixture page exists but only ${count}/${EXPECTED_ALBUMS} albums rendered.\n` +
            'Check that all five shortcodes are present and published.\n' +
            'See tests/e2e/README.md for the required shortcode configuration.'
        );
    }

    await ctx.dispose();
}
