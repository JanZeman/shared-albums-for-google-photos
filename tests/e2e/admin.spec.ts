import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

const GUIDE_URL      = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos';
const PARAMS_URL     = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-shortcode-parameters';
const COMMUNITY_URL  = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-community';

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
