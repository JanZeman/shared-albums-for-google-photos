import { test, expect, type Page } from '@playwright/test';

const ADMIN_USER = 'dev';
const ADMIN_PASS = 'test123';
const COMMUNITY_URL = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-community';

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**', { timeout: 10_000 });
}

test.describe('Community - page structure', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(COMMUNITY_URL);
    });

    test('Community page loads', async ({ page }) => {
        await expect(page.locator('.jzsa-settings-wrap')).toBeAttached({ timeout: 10_000 });
    });

    test('page has the intro section', async ({ page }) => {
        await expect(page.locator('.jzsa-community-intro')).toBeAttached();
    });

    test('page has the audience legend chips', async ({ page }) => {
        const chips = page.locator('.jzsa-community-audience-chip');
        const count = await chips.count();
        expect(count).toBe(2);
    });

    test('page has an account section', async ({ page }) => {
        await expect(page.locator('.jzsa-community-account-section')).toBeAttached();
    });

    test('page has a browse section open by default', async ({ page }) => {
        const section = page.locator('.jzsa-community-browse-section');
        await expect(section).toBeAttached();
        await expect(section).toHaveAttribute('open');
    });
});

test.describe('Community - account (not connected)', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(COMMUNITY_URL);
    });

    test('account section shows disconnected badge', async ({ page }) => {
        const badge = page.locator('.jzsa-summary-badge--disconnected');
        await expect(badge).toBeAttached({ timeout: 5_000 });
    });

    test('connect button is present when not connected', async ({ page }) => {
        await expect(page.locator('.jzsa-community-connect-btn')).toBeAttached();
    });

    test('connect display name input is present', async ({ page }) => {
        await expect(page.locator('#jzsa-connect-display-name')).toBeAttached();
    });

    test('connect display URL input is present', async ({ page }) => {
        await expect(page.locator('#jzsa-connect-display-url')).toBeAttached();
    });
});

test.describe('Community - browse section', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(COMMUNITY_URL);
    });

    test('browse section has a search input', async ({ page }) => {
        await expect(page.locator('#jzsa-community-search')).toBeAttached();
    });

    test('browse section has a search button', async ({ page }) => {
        await expect(page.locator('#jzsa-community-search-btn')).toBeAttached();
    });

    test('browse section has three sort buttons', async ({ page }) => {
        const buttons = page.locator('.jzsa-community-sort-btn');
        const count = await buttons.count();
        expect(count).toBe(3);
    });

    test('"Most Used" sort button is active by default', async ({ page }) => {
        const activeBtn = page.locator('.jzsa-community-sort-btn--active');
        await expect(activeBtn).toBeAttached();
        await expect(activeBtn).toContainText('Most Used');
    });

    test('entries container is present', async ({ page }) => {
        await expect(page.locator('#jzsa-community-entries')).toBeAttached();
    });

    test('entries container transitions out of loading state', async ({ page }) => {
        const container = page.locator('#jzsa-community-entries');
        await expect(container).toBeAttached();
        // Wait for the loading spinner to disappear (AJAX resolves).
        await expect(container.locator('.jzsa-community-loading')).not.toBeAttached({ timeout: 15_000 });
    });

    test('clicking a sort button updates the active class', async ({ page }) => {
        const newestBtn = page.locator('.jzsa-community-sort-btn[data-sort="newest"]');
        await newestBtn.click();
        await expect(newestBtn).toHaveClass(/jzsa-community-sort-btn--active/);
        // Previous active button should no longer be active.
        const mostUsedBtn = page.locator('.jzsa-community-sort-btn[data-sort="interactions"]');
        await expect(mostUsedBtn).not.toHaveClass(/jzsa-community-sort-btn--active/);
    });

    test('typing in search input and clicking Search does not crash the page', async ({ page }) => {
        await page.fill('#jzsa-community-search', 'slider');
        await page.click('#jzsa-community-search-btn');
        // The page should still be intact after a search request.
        await expect(page.locator('#jzsa-community-entries')).toBeAttached({ timeout: 5_000 });
    });
});

test.describe('Community - publish form (not connected)', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(COMMUNITY_URL);
    });

    test('publish form section is not present when not connected', async ({ page }) => {
        // The share section is only rendered for connected users.
        await expect(page.locator('.jzsa-community-share-section')).not.toBeAttached();
    });
});
