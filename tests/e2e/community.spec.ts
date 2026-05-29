import { test, expect, type Page } from '@playwright/test';
import { CONNECTED_PASS, CONNECTED_USER, DISCONNECTED_PASS, DISCONNECTED_USER, loginAs } from './support/auth';

const COMMUNITY_URL = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-community';

// =====================================================================
// TEMPORARY: connected-user tests are disabled in CI.
//
// These describes require the WP user to be in the "community connected"
// state, which depends on a real JWT in user_meta plus a reachable
// community API. CI seeds neither, so every assertion against the
// connected-only DOM fails.
//
// They still run locally (CI env var unset), so the dev workflow of
// running the full suite before a release is unaffected.
//
// TO RE-ENABLE IN CI:
//   1. Provision a permanent test account on the community API and
//      capture its JWT.
//   2. Add JZSA_E2E_CONNECTED_JWT as a GitHub Actions secret and pass
//      it through to setup-fixtures.php in .github/workflows/tests.yml.
//   3. Delete SKIP_CONNECTED_ON_CI and its four call sites below.
//
// Affected describes: lines tagged with SKIP_CONNECTED_ON_CI.
// =====================================================================
const SKIP_CONNECTED_ON_CI = !!process.env.CI;

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
// Account - not connected (jan)
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

    test('"Edit / Delete Your Published Samples" section is not rendered when not connected', async ({ page }) => {
        await expect(page.locator('.jzsa-community-my-section')).not.toBeAttached();
    });
});

// -------------------------------------------------------------------------
// Account - connected (dev)
// -------------------------------------------------------------------------

test.describe('Community - account (connected)', () => {
    test.skip(SKIP_CONNECTED_ON_CI, 'Requires a connected community user; not configured on CI yet.');

    test.beforeEach(async ({ page }) => {
        await loginAs(page, CONNECTED_USER, CONNECTED_PASS);
        await page.goto(COMMUNITY_URL);
    });

    test('account section shows Connected badge', async ({ page }) => {
        await expect(page.locator('.jzsa-summary-badge--connected')).toBeAttached({ timeout: 5_000 });
    });

    test('sign-out button is present', async ({ page }) => {
        // Renamed from "disconnect" to "sign out" during the community-auth
        // redesign. The button's local-only semantics are unchanged: it
        // clears the JWT from this WP install's user_meta but leaves the
        // install authorized on the account.
        await expect(page.locator('.jzsa-community-signout-btn')).toBeAttached();
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

    test('"Edit / Delete Your Published Samples" section is present', async ({ page }) => {
        await expect(page.locator('.jzsa-community-my-section')).toBeAttached();
    });
});

// -------------------------------------------------------------------------
// My entries (connected)
// -------------------------------------------------------------------------

test.describe('Community - My entries (connected)', () => {
    test.skip(SKIP_CONNECTED_ON_CI, 'Requires a connected community user; not configured on CI yet.');

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
// Publish form - client-side validation (connected, dev)
// -------------------------------------------------------------------------

test.describe('Community - publish form validation', () => {
    test.skip(SKIP_CONNECTED_ON_CI, 'Requires a connected community user; not configured on CI yet.');

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
        await expect(page.locator('#jzsa-pub-title')).toHaveValue('My Test Album');
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

    test('showcase show-shortcode checkbox mirrors showcase consent state', async ({ page }) => {
        // Both checkboxes default to CHECKED. Toggling consent off disables
        // and visually unchecks show; toggling consent back on re-enables
        // and re-checks it. The publish form has a single consent panel
        // now (the duplicate at the top of the table was removed); ids no
        // longer carry a -bottom suffix.
        const consent       = page.locator('#jzsa-pub-showcase-consent');
        const showShortcode = page.locator('#jzsa-pub-showcase-show-shortcode');

        await expect(consent).toBeChecked();
        await expect(showShortcode).toBeEnabled();
        await expect(showShortcode).toBeChecked();

        await consent.uncheck();
        await expect(showShortcode).toBeDisabled();
        await expect(showShortcode).not.toBeChecked();

        await consent.check();
        await expect(showShortcode).toBeEnabled();
        await expect(showShortcode).toBeChecked();

        await showShortcode.uncheck();
        await expect(showShortcode).not.toBeChecked();
    });
});

// -------------------------------------------------------------------------
// Publish form - structure (connected)
// -------------------------------------------------------------------------

test.describe('Community - publish form structure', () => {
    test.skip(SKIP_CONNECTED_ON_CI, 'Requires a connected community user; not configured on CI yet.');

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
