import { test, expect, type Page } from '@playwright/test';
import { CONNECTED_PASS, CONNECTED_USER, loginAs } from './support/auth';

const COMMUNITY_URL = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-community';
const ALBUM_URL = 'https://photos.google.com/share/AF1Qip-test?key=abc123';

// =====================================================================
// TEMPORARY: connected-user mocked flows are disabled in CI.
//
// These tests interact with DOM that only renders when the WP user is
// in the "community connected" state. The mock here only intercepts
// client-side fetch AFTER navigation; the initial page render is
// server-side in PHP and checks JWT + a live API call, neither of
// which is set up in CI. So the share section / my-entries section
// never exists for these tests to drive.
//
// They still run locally (CI env var unset).
//
// TO RE-ENABLE IN CI: same plan as in community.spec.ts (provision a
// JZSA_E2E_CONNECTED_JWT secret + wire it into setup-fixtures.php),
// then delete SKIP_CONNECTED_ON_CI and its call sites below.
// =====================================================================
const SKIP_CONNECTED_ON_CI = !!process.env.CI;

// A genuinely renderable album link, kept in sync with tests/e2e/setup-fixtures.php.
// global-setup loads the fixture pages, which warms this album's server-side cache.
const REAL_ALBUM_URL = process.env.JZSA_E2E_ALBUM_URL
    ?? 'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R';

type AjaxRequest = {
    action: string;
    fields: Record<string, string>;
};

declare global {
    interface Window {
        __jzsaCommunityRequests?: AjaxRequest[];
    }
}

async function installCommunityAjaxMock(page: Page): Promise<{ requests: () => Promise<AjaxRequest[]> }> {
    await page.addInitScript(({ albumUrl }) => {
        const originalFetch = window.fetch.bind(window);
        const makeEntry = (overrides = {}) => ({
            id: 101,
            title: 'Mocked Slider Example',
            shortcode: `[jzsa-album link="${albumUrl}" mode="slider" show-navigation="true"]`,
            preview_shortcode: `[jzsa-album link="${albumUrl}" mode="slider" show-navigation="true"]`,
            description: 'A deterministic community entry from the Playwright mock.',
            tags: ['slider', 'navigation'],
            site_url: 'https://example.test/album',
            photographer_name: 'Mock Author',
            photographer_bio: 'Uses mocked AJAX responses.',
            interaction_score: 12,
            avg_rating: 3.8,
            rating_count: 4,
            public_showcase_consent: true,
            public_showcase_show_shortcode: true,
            author: {
                display_name: 'Mock Author',
                display_url: 'https://example.test',
            },
            ...overrides,
        });
        const browseEntries = [makeEntry()];
        let myEntries = [makeEntry({ id: 202, title: 'Owned Mocked Example', interaction_score: 0 })];

        window.__jzsaCommunityRequests = [];
        window.fetch = async (input, init) => {
            const url = input instanceof Request ? input.url : String(input);
            const body = init && init.body;
            const fields = {};

            if (/admin-ajax\.php/.test(url) && body instanceof FormData) {
                body.forEach((value, key) => {
                    fields[key] = String(value);
                });
            }

            const action = fields.action || '';
            if (!action.startsWith('jzsa_community_')) {
                return originalFetch(input, init);
            }

            window.__jzsaCommunityRequests.push({ action, fields });

            let payload;
            switch (action) {
                case 'jzsa_community_browse':
                    payload = {
                        success: true,
                        data: {
                            data: browseEntries,
                            meta: { total: browseEntries.length, page: Number(fields.page || 1), per_page: 12 },
                        },
                    };
                    break;

                case 'jzsa_community_load_my_entries':
                    payload = { success: true, data: myEntries };
                    break;

                case 'jzsa_community_publish': {
                    const published = makeEntry({
                        id: 303,
                        title: fields.title,
                        shortcode: fields.shortcode,
                        description: fields.description,
                        tags: fields.tags ? fields.tags.split(',').map((tag) => tag.trim()) : [],
                        site_url: fields.site_url,
                        photographer_name: fields.photographer_name,
                        photographer_bio: fields.photographer_bio,
                        public_showcase_consent: fields.public_showcase_consent === 'true',
                        // Default true when missing or anything other than the literal 'false'.
                        public_showcase_show_shortcode: fields.public_showcase_show_shortcode !== 'false',
                    });
                    browseEntries.unshift(published);
                    myEntries.unshift(published);
                    payload = { success: true, data: published };
                    break;
                }

                case 'jzsa_community_update_entry':
                    myEntries = myEntries.map((entry) => (
                        String(entry.id) === fields.entry_id
                            ? {
                                ...entry,
                                title: fields.title,
                                description: fields.description,
                                site_url: fields.site_url,
                                public_showcase_consent: fields.public_showcase_consent === 'true',
                                public_showcase_show_shortcode: fields.public_showcase_show_shortcode !== 'false',
                            }
                            : entry
                    ));
                    payload = { success: true, data: myEntries.find((entry) => String(entry.id) === fields.entry_id) };
                    break;

                case 'jzsa_community_delete_entry':
                    myEntries = myEntries.filter((entry) => String(entry.id) !== fields.entry_id);
                    payload = { success: true, data: { deleted: true } };
                    break;

                case 'jzsa_community_rate':
                    payload = { success: true, data: { avg_rating: 4.2, rating_count: 9 } };
                    break;

                default:
                    payload = { success: true, data: {} };
            }

            return new Response(JSON.stringify(payload), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        };
    }, { albumUrl: ALBUM_URL });

    return {
        requests: () => page.evaluate(() => window.__jzsaCommunityRequests || []),
    };
}

async function openShareSection(page: Page): Promise<void> {
    const section = page.locator('#jzsa-publish-details');
    if ((await section.getAttribute('open')) === null) {
        await section.locator('summary').click();
    }
}

async function openMyEntriesSection(page: Page): Promise<void> {
    const section = page.locator('.jzsa-community-my-section');
    if ((await section.getAttribute('open')) === null) {
        await section.locator('summary').click();
    }
}

async function setPublishShortcode(page: Page, value: string): Promise<void> {
    await page.evaluate((shortcode) => {
        const el = document.querySelector('#jzsa-pub-shortcode') as HTMLElement | null;
        if (el) el.textContent = shortcode;
    }, value);
}

test.describe('Community - mocked AJAX flows', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, CONNECTED_USER, CONNECTED_PASS);
    });

    test('browse renders deterministic mocked entries', async ({ page }) => {
        await installCommunityAjaxMock(page);
        await page.goto(COMMUNITY_URL);

        const entry = page.locator('#jzsa-community-entries .jzsa-community-entry').first();
        await expect(entry).toContainText('Mocked Slider Example', { timeout: 10_000 });
        await expect(entry).toContainText('slider, navigation');
        await expect(entry.locator('.jzsa-community-entry-score')).toContainText('12 interaction points');
        await expect(page.locator('#jzsa-community-entries-count')).toContainText('1 published');
    });

    test('sort and search send deterministic browse parameters', async ({ page }) => {
        const mock = await installCommunityAjaxMock(page);
        await page.goto(COMMUNITY_URL);
        await expect(page.locator('#jzsa-community-entries .jzsa-community-entry')).toHaveCount(1, { timeout: 10_000 });

        await page.locator('.jzsa-community-sort-btn[data-sort="newest"]').click();
        await page.fill('#jzsa-community-search', 'slider');
        await page.click('#jzsa-community-search-btn');

        await expect.poll(async () => (await mock.requests()).filter((request) => request.action === 'jzsa_community_browse').length).toBeGreaterThanOrEqual(3);
        const lastBrowse = (await mock.requests()).filter((request) => request.action === 'jzsa_community_browse').at(-1);
        expect(lastBrowse?.fields).toMatchObject({ page: '1', q: 'slider', sort: 'newest' });
    });

    test('publish submits a valid shortcode and reloads browse/my entries', async ({ page }) => {
        test.skip(SKIP_CONNECTED_ON_CI, 'Requires the connected-only share section; not configured on CI yet.');
        const mock = await installCommunityAjaxMock(page);
        await page.goto(COMMUNITY_URL);
        await openShareSection(page);

        await page.fill('#jzsa-pub-title', 'Published From Mock');
        await setPublishShortcode(page, `[jzsa-album link="${ALBUM_URL}" mode="gallery"]`);
        await page.fill('#jzsa-pub-description', 'Published through a mocked community API.');
        await page.fill('#jzsa-pub-tags', 'gallery,test');
        await page.fill('#jzsa-pub-site-url', 'example.test/published');
        await page.fill('#jzsa-pub-photographer-name', 'Mock Publisher');
        await page.check('#jzsa-pub-showcase-consent');
        // After enabling consent, the show-shortcode checkbox becomes
        // enabled and visually checked (default true). Leave it checked
        // to assert the default-true payload below.
        await page.click('#jzsa-community-publish-btn');

        await expect(page.locator('#jzsa-publish-result')).toContainText('Published!', { timeout: 10_000 });
        const publish = (await mock.requests()).find((request) => request.action === 'jzsa_community_publish');
        expect(publish?.fields).toMatchObject({
            title: 'Published From Mock',
            tags: 'gallery,test',
            site_url: 'https://example.test/published',
            photographer_name: 'Mock Publisher',
            public_showcase_consent: 'true',
            // show_shortcode defaults to true in the new UI; the publish
            // form sends it unconditionally.
            public_showcase_show_shortcode: 'true',
        });
        expect(publish?.fields.shortcode).toContain('mode="gallery"');
        await expect.poll(async () => (await mock.requests()).filter((request) => request.action === 'jzsa_community_load_my_entries').length).toBeGreaterThanOrEqual(1);
    });

    test('rating a community entry updates the star summary from the mock response', async ({ page }) => {
        test.skip(SKIP_CONNECTED_ON_CI, 'Star controls only render for a connected user; not configured on CI yet.');
        const mock = await installCommunityAjaxMock(page);
        await page.goto(COMMUNITY_URL);

        const entry = page.locator('#jzsa-community-entries .jzsa-community-entry').first();
        await expect(entry).toContainText('Mocked Slider Example', { timeout: 10_000 });
        await entry.locator('.jzsa-star[data-value="4"]').click();

        await expect(entry.locator('.jzsa-community-entry-rating-count')).toContainText('4.2 ★ (9)', { timeout: 10_000 });
        const rate = (await mock.requests()).find((request) => request.action === 'jzsa_community_rate');
        expect(rate?.fields).toMatchObject({ entry_id: '101', rating: '4' });
    });

    test('my entry save and delete use mocked update/delete endpoints', async ({ page }) => {
        test.skip(SKIP_CONNECTED_ON_CI, 'Requires the connected-only "Edit / Delete Your Published Samples" section; not configured on CI yet.');
        const mock = await installCommunityAjaxMock(page);
        await page.goto(COMMUNITY_URL);
        await openMyEntriesSection(page);

        const entry = page.locator('#jzsa-community-my-entries .jzsa-community-my-entry').first();
        await expect(entry).toContainText('Owned Mocked Example', { timeout: 10_000 });

        await entry.locator('.jzsa-community-my-entry-title-input').fill('Updated Owned Example');
        await entry.locator('.jzsa-community-save-entry-btn').click();
        await expect(entry.locator('.jzsa-community-result')).toContainText('Saved!', { timeout: 10_000 });

        page.once('dialog', async (dialog) => dialog.accept());
        await entry.locator('.jzsa-community-delete-entry-btn').click();
        await expect(page.locator('#jzsa-community-my-entries .jzsa-community-empty')).toBeAttached({ timeout: 10_000 });

        const requests = await mock.requests();
        expect(requests.find((request) => request.action === 'jzsa_community_update_entry')?.fields.title).toBe('Updated Owned Example');
        expect(requests.find((request) => request.action === 'jzsa_community_delete_entry')?.fields.entry_id).toBe('202');
    });

    test('a browsed entry with a valid album link renders a real gallery preview', async ({ page }) => {
        // Mock ONLY jzsa_community_browse. The lazy preview render
        // (jzsa_shortcode_preview) is deliberately left to hit the real plugin,
        // so this exercises the genuine render path. Nothing is published and
        // nothing is written to the community database.
        await page.addInitScript((albumUrl: string) => {
            const originalFetch = window.fetch.bind(window);
            window.fetch = async (input, init) => {
                const url = input instanceof Request ? input.url : String(input);
                const body = init && init.body;
                const action = (/admin-ajax\.php/.test(url) && body instanceof FormData)
                    ? String(body.get('action') || '')
                    : '';

                if (action !== 'jzsa_community_browse') {
                    return originalFetch(input, init);
                }

                const entry = {
                    id: 9001,
                    title: 'Renderable Community Entry',
                    shortcode: '[jzsa-album link="hidden-album-link" mode="gallery"]',
                    preview_shortcode: `[jzsa-album link="${albumUrl}" mode="gallery"]`,
                    description: 'Uses a real album link so the preview renders.',
                    tags: ['gallery'],
                    site_url: 'https://example.test/renderable',
                    photographer_name: 'Render Test',
                    interaction_score: 0,
                    avg_rating: 0,
                    rating_count: 0,
                    public_showcase_consent: false,
                    author: { display_name: 'Render Test', display_url: 'https://example.test' },
                };

                return new Response(JSON.stringify({
                    success: true,
                    data: { data: [entry], meta: { total: 1, page: 1, per_page: 12 } },
                }), { status: 200, headers: { 'Content-Type': 'application/json' } });
            };
        }, REAL_ALBUM_URL);

        await page.goto(COMMUNITY_URL);

        const entry = page.locator('#jzsa-community-entries .jzsa-community-entry').first();
        await expect(entry).toContainText('Renderable Community Entry', { timeout: 10_000 });

        // The lazy preview is IntersectionObserver-gated; scroll it into view,
        // then wait for the real jzsa_shortcode_preview AJAX to finish.
        const preview = entry.locator('.jzsa-preview-container');
        await preview.scrollIntoViewIfNeeded();
        await expect(preview).toHaveAttribute('data-lazy-state', 'loaded', { timeout: 20_000 });

        // A valid album link must render a gallery, not the "No Photos Found"
        // error box that an invalid link (e.g. link="hidden-album-link") produces.
        // Gallery mode renders the grid plus a slideshow sub-element; assert the
        // grid specifically and that real photos were resolved.
        const gallery = preview.locator('.jzsa-album[data-mode="gallery"]');
        await expect(gallery).toBeAttached();
        expect(Number(await gallery.getAttribute('data-total-count'))).toBeGreaterThan(0);
        await expect(preview.locator('.jzsa-playground-error')).toHaveCount(0);
    });
});
