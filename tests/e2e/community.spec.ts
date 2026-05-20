import { test, expect, type Page } from '@playwright/test';
import { CONNECTED_PASS, CONNECTED_USER, DISCONNECTED_PASS, DISCONNECTED_USER, loginAs } from './support/auth';

const COMMUNITY_URL = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-community';

// Set the textContent of the publish shortcode contenteditable <code> element.
async function setPublishShortcode(page: Page, value: string): Promise<void> {
    await page.evaluate((sc) => {
        const el = document.querySelector('#jzsa-pub-shortcode') as HTMLElement | null;
        if (el) el.textContent = sc;
    }, value);
}

// Open the "Share a Shortcode" <details> section.
async function openShareSection(page: Page): Promise<void> {
    const section = page.locator('#jzsa-publish-details');
    const isOpen = await section.getAttribute('open');
    if (isOpen === null) {
        await section.locator('summary').click();
    }
}

// -------------------------------------------------------------------------
// Page structure (user-agnostic)
// -------------------------------------------------------------------------

test.describe('Community - page structure', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, CONNECTED_USER, CONNECTED_PASS);
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

// -------------------------------------------------------------------------
// Account — not connected (jan)
// -------------------------------------------------------------------------

test.describe('Community - account (not connected)', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, DISCONNECTED_USER, DISCONNECTED_PASS);
        await page.goto(COMMUNITY_URL);
    });

    test('account section shows disconnected badge', async ({ page }) => {
        await expect(page.locator('.jzsa-summary-badge--disconnected')).toBeAttached({ timeout: 5_000 });
    });

    test('connect button is present', async ({ page }) => {
        await expect(page.locator('.jzsa-community-connect-btn')).toBeAttached();
    });

    test('connect display name input is present', async ({ page }) => {
        await expect(page.locator('#jzsa-connect-display-name')).toBeAttached();
    });

    test('connect display URL input is present', async ({ page }) => {
        await expect(page.locator('#jzsa-connect-display-url')).toBeAttached();
    });

    test('publish form section is not rendered when not connected', async ({ page }) => {
        await expect(page.locator('.jzsa-community-share-section')).not.toBeAttached();
    });

    test('"Your Shared Examples" section is not rendered when not connected', async ({ page }) => {
        await expect(page.locator('.jzsa-community-my-section')).not.toBeAttached();
    });
});

// -------------------------------------------------------------------------
// Account — connected (dev)
// -------------------------------------------------------------------------

test.describe('Community - account (connected)', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, CONNECTED_USER, CONNECTED_PASS);
        await page.goto(COMMUNITY_URL);
    });

    test('account section shows Connected badge', async ({ page }) => {
        await expect(page.locator('.jzsa-summary-badge--connected')).toBeAttached({ timeout: 5_000 });
    });

    test('disconnect button is present', async ({ page }) => {
        await expect(page.locator('.jzsa-community-disconnect-btn')).toBeAttached();
    });

    test('display name is shown', async ({ page }) => {
        const nameEl = page.locator('#jzsa-display-name-view');
        await expect(nameEl).toBeAttached();
        const text = await nameEl.textContent();
        expect(text?.trim().length).toBeGreaterThan(0);
    });

    test('"Share a Shortcode" section is present', async ({ page }) => {
        await expect(page.locator('.jzsa-community-share-section')).toBeAttached();
    });

    test('"Your Shared Examples" section is present', async ({ page }) => {
        await expect(page.locator('.jzsa-community-my-section')).toBeAttached();
    });
});

// -------------------------------------------------------------------------
// My entries (connected)
// -------------------------------------------------------------------------

test.describe('Community - My entries (connected)', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, CONNECTED_USER, CONNECTED_PASS);
        await page.goto(COMMUNITY_URL);
        // Open the section so JS loads the entries.
        await page.locator('.jzsa-community-my-section summary').click();
    });

    test('My entries section transitions out of loading state', async ({ page }) => {
        const container = page.locator('#jzsa-community-my-entries');
        await expect(container.locator('.jzsa-community-loading')).not.toBeAttached({ timeout: 15_000 });
    });

    test('My entries renders either entries or the empty state after loading', async ({ page }) => {
        const container = page.locator('#jzsa-community-my-entries');
        await expect(container.locator('.jzsa-community-loading')).not.toBeAttached({ timeout: 15_000 });
        const terminalState = container.locator('.jzsa-community-empty, .jzsa-community-my-entry, .jzsa-community-error');
        await expect(terminalState.first()).toBeAttached({ timeout: 5_000 });
    });
});

// -------------------------------------------------------------------------
// Browse (works for both connected and not-connected)
// -------------------------------------------------------------------------

test.describe('Community - browse section', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, CONNECTED_USER, CONNECTED_PASS);
        await page.goto(COMMUNITY_URL);
    });

    test('browse section has a search input', async ({ page }) => {
        await expect(page.locator('#jzsa-community-search')).toBeAttached();
    });

    test('browse section has three sort buttons', async ({ page }) => {
        const count = await page.locator('.jzsa-community-sort-btn').count();
        expect(count).toBe(3);
    });

    test('"Most Used" sort button is active by default', async ({ page }) => {
        const activeBtn = page.locator('.jzsa-community-sort-btn--active');
        await expect(activeBtn).toContainText('Most Used');
    });

    test('entries container transitions out of loading state', async ({ page }) => {
        const container = page.locator('#jzsa-community-entries');
        await expect(container.locator('.jzsa-community-loading')).not.toBeAttached({ timeout: 15_000 });
    });

    test('clicking a sort button updates the active class', async ({ page }) => {
        const newestBtn = page.locator('.jzsa-community-sort-btn[data-sort="newest"]');
        await newestBtn.click();
        await expect(newestBtn).toHaveClass(/jzsa-community-sort-btn--active/);
        await expect(page.locator('.jzsa-community-sort-btn[data-sort="interactions"]')).not.toHaveClass(/jzsa-community-sort-btn--active/);
    });

    test('search request completes without crashing the page', async ({ page }) => {
        await page.fill('#jzsa-community-search', 'slider');
        await page.click('#jzsa-community-search-btn');
        await expect(page.locator('#jzsa-community-entries')).toBeAttached({ timeout: 5_000 });
    });
});

// -------------------------------------------------------------------------
// Publish form — client-side validation (connected, dev)
// -------------------------------------------------------------------------

test.describe('Community - publish form validation', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, CONNECTED_USER, CONNECTED_PASS);
        await page.goto(COMMUNITY_URL);
        await openShareSection(page);
    });

    test('empty title shows validation error', async ({ page }) => {
        await page.click('#jzsa-community-publish-btn');
        await expect(page.locator('#jzsa-publish-result')).toContainText('Title must be at least 3 characters', { timeout: 5_000 });
    });

    test('title shorter than 3 characters shows validation error', async ({ page }) => {
        await page.fill('#jzsa-pub-title', 'ab');
        await page.click('#jzsa-community-publish-btn');
        await expect(page.locator('#jzsa-publish-result')).toContainText('Title must be at least 3 characters', { timeout: 5_000 });
    });

    test('valid title but no shortcode shows validation error', async ({ page }) => {
        await page.fill('#jzsa-pub-title', 'My Test Album');
        await page.click('#jzsa-community-publish-btn');
        await expect(page.locator('#jzsa-publish-result')).toContainText('Shortcode must be a valid', { timeout: 5_000 });
    });

    test('shortcode without a Google Photos link shows validation error', async ({ page }) => {
        await page.fill('#jzsa-pub-title', 'My Test Album');
        await setPublishShortcode(page, '[jzsa-album mode="slider"]');
        await page.click('#jzsa-community-publish-btn');
        await expect(page.locator('#jzsa-publish-result')).toContainText('valid Google Photos share URL', { timeout: 5_000 });
    });

    test('more than 5 tags shows validation error', async ({ page }) => {
        await page.fill('#jzsa-pub-title', 'My Test Album');
        await setPublishShortcode(page, '[jzsa-album link="https://photos.google.com/share/AF1Qip-test"]');
        await page.fill('#jzsa-pub-tags', 'tag1,tag2,tag3,tag4,tag5,tag6');
        await page.click('#jzsa-community-publish-btn');
        await expect(page.locator('#jzsa-publish-result')).toContainText('no more than 5 tags', { timeout: 5_000 });
    });

    test('showcase consent without description shows validation error', async ({ page }) => {
        await page.fill('#jzsa-pub-title', 'My Test Album');
        await setPublishShortcode(page, '[jzsa-album link="https://photos.google.com/share/AF1Qip-test"]');
        await page.check('#jzsa-pub-showcase-consent');
        // Leave description, site URL, and photographer name empty.
        await page.click('#jzsa-community-publish-btn');
        await expect(page.locator('#jzsa-publish-result')).toContainText('required', { timeout: 5_000 });
    });
});

// -------------------------------------------------------------------------
// Publish form — structure (connected)
// -------------------------------------------------------------------------

test.describe('Community - publish form structure', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, CONNECTED_USER, CONNECTED_PASS);
        await page.goto(COMMUNITY_URL);
        await openShareSection(page);
    });

    test('title input is present', async ({ page }) => {
        await expect(page.locator('#jzsa-pub-title')).toBeAttached();
    });

    test('shortcode block is present', async ({ page }) => {
        await expect(page.locator('#jzsa-pub-shortcode')).toBeAttached();
    });

    test('description textarea is present', async ({ page }) => {
        await expect(page.locator('#jzsa-pub-description')).toBeAttached();
    });

    test('tags input is present', async ({ page }) => {
        await expect(page.locator('#jzsa-pub-tags')).toBeAttached();
    });

    test('publish button is present', async ({ page }) => {
        await expect(page.locator('#jzsa-community-publish-btn')).toBeAttached();
    });
});
