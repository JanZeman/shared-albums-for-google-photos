import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

const GUIDE_URL      = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos';
const PARAMS_URL     = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-shortcode-parameters';
const COMMUNITY_URL  = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-community';
const SETTINGS_URL   = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-settings';

test.describe('Admin - Guide page', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('Guide page loads without error', async ({ page }) => {
        await page.goto(GUIDE_URL);
        await expect(page.locator('.jzsa-settings-wrap')).toBeAttached({ timeout: 10_000 });
    });

    test('Guide page has the plugin version badge', async ({ page }) => {
        await page.goto(GUIDE_URL);
        await expect(page.locator('.jzsa-version')).toBeAttached();
    });

    test('Guide page renders lazy sample preview placeholders', async ({ page }) => {
        await page.goto(GUIDE_URL);
        await expect(page.locator('.jzsa-lazy-preview').first()).toBeAttached({ timeout: 10_000 });
    });

    test('Guide page has at least one content section', async ({ page }) => {
        await page.goto(GUIDE_URL);
        await expect(page.locator('.jzsa-section').first()).toBeAttached();
    });

    test('Admin nav contains a link to the Parameters page', async ({ page }) => {
        await page.goto(GUIDE_URL);
        const link = page.locator(`a[href*="page=janzeman-shared-albums-for-google-photos-shortcode-parameters"]`).first();
        await expect(link).toBeAttached();
    });

    test('Settings contains the migration tool with Lightbox context', async ({ page }) => {
        await page.goto(SETTINGS_URL);
        const migration = page.locator('#jzsa-shortcode-migration');
        if ((await migration.count()) === 0) {
            test.skip(true, 'Site was not upgraded from a pre-2.4.0 version, so the tool is absent by design.');
        }
        await expect(migration.locator('h2')).toContainText('Shortcode Migration Tool: Lightbox Viewer');
        await expect(migration).toContainText('version 2.4.0 (July 2026)');
        await expect(migration.locator('#jzsa-migration-preview')).toHaveCount(0);
    });

    test('Guide page does not speculatively preload full-resolution images', async ({ page }) => {
        // Regression guard for roadmap 014. Galleries used to warm the browser cache with a
        // large "full" variant for four slides each, so that opening the lightbox later would
        // be instant. Nobody opens a viewer in the admin, and this page renders dozens of
        // documentation samples, so the page once pulled ~70 large images for nothing.
        // Measured before the fix: 71 variants at 1920x1440 or larger, ~40MB. After: 1.
        test.setTimeout(300_000);

        const largeImageUrls: string[] = [];
        page.on('response', (response) => {
            const url = response.url();
            if (!url.includes('googleusercontent.com')) {
                return;
            }
            const size = url.match(/=w(\d+)-h(\d+)/);
            if (size && Number.parseInt(size[1], 10) >= 1920) {
                largeImageUrls.push(url);
            }
        });

        await page.goto(GUIDE_URL);

        // The flag PHP uses to switch the preload off. Checked directly because it pins the
        // mechanism, while the request count below pins the effect. Soft, so that a failure
        // here still lets the behavioral assertion run and report its own number.
        // Note: wp_localize_script casts booleans to strings, so this is "1" or "".
        const preloadFlag = await page.evaluate(
            () => (window as unknown as { jzsaAjax?: { preloadFullImages?: unknown } }).jzsaAjax?.preloadFullImages
        );
        expect.soft(preloadFlag, 'jzsaAjax.preloadFullImages must be falsy in the admin').toBeFalsy();

        // Samples load in a background queue independent of scrolling, so the measurement is
        // only meaningful once that queue has drained.
        await page.waitForFunction(
            () => document.querySelectorAll('.jzsa-lazy-preview[data-lazy-state="pending"]').length === 0,
            undefined,
            { timeout: 150_000 }
        );
        await page.waitForFunction(
            () => document.querySelectorAll('.jzsa-lazy-preview[data-lazy-state="loading"]').length === 0,
            undefined,
            { timeout: 150_000 }
        );
        await page.waitForTimeout(2_000);

        // A handful of samples legitimately document large source sizes, so this is a bound
        // rather than zero. It still sits far below the ~71 seen before the fix.
        expect(
            largeImageUrls.length,
            `Guide page requested ${largeImageUrls.length} image(s) at 1920px or wider`
        ).toBeLessThanOrEqual(5);
    });
});

test.describe('Admin - Settings page', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('Selecting a default viewer option saves it immediately, with no separate save button', async ({ page }) => {
        // This setting is a site-wide WordPress option, and this suite runs against the same
        // local dev site a human may be actively looking at (there is no throwaway instance
        // per test run here). Read back whatever was selected before this test touched
        // anything, and restore exactly that in `finally`, regardless of outcome -- never
        // assume or leave behind an assumed default.
        await page.goto(SETTINGS_URL);

        const lightbox = page.locator('input[name="jzsa-default-viewer"][value="lightbox"]');
        const fullscreen = page.locator('input[name="jzsa-default-viewer"][value="fullscreen"]');
        const status = page.locator('#jzsa-default-viewer-status');
        await expect(lightbox).toBeAttached({ timeout: 10_000 });

        // The button is gone; a radio change is the only way to trigger a save now.
        await expect(page.locator('#jzsa-save-default-viewer')).toHaveCount(0);

        const originalValue = (await lightbox.isChecked()) ? 'lightbox' : 'fullscreen';
        const other = originalValue === 'lightbox' ? fullscreen : lightbox;

        try {
            // Flip to the other option and confirm the flip persists across a reload,
            // proving the change was actually saved server-side, not just reflected in the DOM.
            await other.check();
            await expect(status).toHaveText('Saved.', { timeout: 10_000 });

            await page.reload();
            await expect(other).toBeChecked();
        } finally {
            await page.locator(`input[name="jzsa-default-viewer"][value="${originalValue}"]`).check();
            await expect(status).toHaveText('Saved.', { timeout: 10_000 });
        }
    });
});

test.describe('Admin - Parameters page', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('Parameters page loads without error', async ({ page }) => {
        await page.goto(PARAMS_URL);
        await expect(page.locator('.jzsa-settings-wrap')).toBeAttached({ timeout: 10_000 });
    });

    test('Parameters page renders at least one parameters table', async ({ page }) => {
        await page.goto(PARAMS_URL);
        await expect(page.locator('.jzsa-settings-table--params').first()).toBeAttached({ timeout: 10_000 });
    });

    test('Parameters table has a Parameter column header', async ({ page }) => {
        await page.goto(PARAMS_URL);
        const table = page.locator('.jzsa-settings-table--params').first();
        await expect(table.locator('th').first()).toContainText('Parameter');
    });

    test('Parameters table has multiple rows', async ({ page }) => {
        await page.goto(PARAMS_URL);
        const rows = page.locator('.jzsa-settings-table--params tbody tr');
        const count = await rows.count();
        expect(count).toBeGreaterThan(5);
    });
});

test.describe('Admin - Community page', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('Community page loads without error', async ({ page }) => {
        await page.goto(COMMUNITY_URL);
        await expect(page.locator('.jzsa-settings-wrap')).toBeAttached({ timeout: 10_000 });
    });

    test('Community page has the browse section', async ({ page }) => {
        await page.goto(COMMUNITY_URL);
        await expect(page.locator('.jzsa-community-browse-section')).toBeAttached();
    });

    test('Community page has a search input', async ({ page }) => {
        await page.goto(COMMUNITY_URL);
        await expect(page.locator('#jzsa-community-search')).toBeAttached();
    });

    test('Community page has sort buttons', async ({ page }) => {
        await page.goto(COMMUNITY_URL);
        await expect(page.locator('.jzsa-community-sort-btn').first()).toBeAttached();
        const count = await page.locator('.jzsa-community-sort-btn').count();
        expect(count).toBe(3);
    });

    test('Community page shows the account section', async ({ page }) => {
        await page.goto(COMMUNITY_URL);
        await expect(page.locator('.jzsa-community-account-section')).toBeAttached();
    });
});
