import { test, expect, type Page } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

const GUIDE_URL = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos';

// The live validation area is inserted by admin-settings.js immediately after
// the Playground code block on the Guide page.
const VALIDATION = '.jzsa-playground-code-block + .jzsa-code-validation';
const REVERT_BTN = '.jzsa-playground-code-block [data-jzsa-action="revert"]';

/**
 * Replace the Playground shortcode and fire an `input` event so the shared
 * code-block validation reacts exactly as it would to real typing.
 */
async function setShortcode(page: Page, value: string): Promise<void> {
    await page.evaluate((shortcode) => {
        const el = document.getElementById('jzsa-playground-shortcode');
        if (!el) {
            throw new Error('Playground shortcode element not found');
        }
        el.textContent = shortcode;
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }, value);
}

test.describe('Shortcode validation - Playground live feedback', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(GUIDE_URL);
        // Wait for admin-settings.js to wire the block and insert the area.
        await expect(page.locator(VALIDATION)).toBeAttached({ timeout: 10_000 });
    });

    test('a valid prefilled sample shortcode shows no message', async ({ page }) => {
        const area = page.locator(VALIDATION);
        await expect(area).not.toBeVisible();
        await expect(area).not.toHaveClass(/jzsa-code-validation--(warning|error)/);
    });

    test('missing link parameter reports an error', async ({ page }) => {
        await setShortcode(page, '[jzsa-album mode="slider"]');
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--error/);
        await expect(area).toContainText('Missing required "link" parameter');
    });

    test('unknown parameter reports a warning', async ({ page }) => {
        await setShortcode(page, '[jzsa-album link="https://photos.google.com/share/x" sparkle="true"]');
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('Unknown parameter "sparkle"');
    });

    test('a parameter typo gets a "did you mean" suggestion', async ({ page }) => {
        await setShortcode(page, '[jzsa-album link="https://photos.google.com/share/x" modee="slider"]');
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('did you mean "mode"');
    });

    test('obsolete parameters warn with the preferred replacement', async ({ page }) => {
        await setShortcode(page, '[jzsa-album link="https://photos.google.com/share/x" cache-refresh="24"]');
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('Parameter "cache-refresh" is obsolete');
        await expect(area).toContainText('Use "album-cache-refresh" instead');
    });

    test('legacy expanded viewer aliases warn with viewer replacements', async ({ page }) => {
        await setShortcode(page, '[jzsa-album link="https://photos.google.com/share/x" expanded-max-width="900"]');
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('Parameter "expanded-max-width" is obsolete');
        await expect(area).toContainText('Use "viewer-max-width" instead');
    });

    test('legacy fullscreen display bounds warn with fullscreen max replacements', async ({ page }) => {
        await setShortcode(page, '[jzsa-album link="https://photos.google.com/share/x" fullscreen-display-max-width="900"]');
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('Parameter "fullscreen-display-max-width" is obsolete');
        await expect(area).toContainText('Use "fullscreen-max-width" instead');
    });

    test('an unterminated quote reports an error', async ({ page }) => {
        await setShortcode(page, '[jzsa-album link="https://photos.google.com/share/x]');
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--error/);
        await expect(area).toContainText('Unterminated');
    });

    test('a wrong shortcode tag reports an error', async ({ page }) => {
        await setShortcode(page, '[wrong-album link="https://photos.google.com/share/x"]');
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--error/);
        await expect(area).toContainText('jzsa-album');
    });

    test('an empty field collapses the validation area', async ({ page }) => {
        await setShortcode(page, '');
        const area = page.locator(VALIDATION);
        await expect(area).not.toBeVisible();
        await expect(area).not.toHaveClass(/jzsa-code-validation--(ok|warning|error)/);
    });

    test('Revert clears the error message after an invalid edit', async ({ page }) => {
        await setShortcode(page, '[jzsa-album mode="slider"]');
        await expect(page.locator(VALIDATION)).toHaveClass(/jzsa-code-validation--error/);

        await page.locator(REVERT_BTN).click();
        await expect(page.locator(VALIDATION)).not.toBeVisible();
    });
});

const VALID_LINK = 'https://photos.google.com/share/x';

test.describe('Shortcode validation - parameter values', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(GUIDE_URL);
        await expect(page.locator(VALIDATION)).toBeAttached({ timeout: 10_000 });
    });

    test('a non true/false boolean value warns', async ({ page }) => {
        await setShortcode(page, `[jzsa-album link="${VALID_LINK}" show-navigation="yes"]`);
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('expects true or false');
    });

    test('an out-of-set enum value warns and lists the accepted values', async ({ page }) => {
        await setShortcode(page, `[jzsa-album link="${VALID_LINK}" mode="slideshow"]`);
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('gallery, slider, carousel');
    });

    test('a non-numeric value for a number parameter warns', async ({ page }) => {
        await setShortcode(page, `[jzsa-album link="${VALID_LINK}" limit="lots"]`);
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('expects a whole number');
    });

    test('an out-of-range number warns with the allowed bound', async ({ page }) => {
        await setShortcode(page, `[jzsa-album link="${VALID_LINK}" gallery-columns="20"]`);
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('12 or lower');
    });

    test('justified layout warns when grid-only gallery parameters are set', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" mode="gallery" gallery-layout="justified" ` +
                'gallery-columns="5" gallery-sizing="fill"]',
        );
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('gallery-columns, gallery-sizing are ignored');
        await expect(area).toContainText('gallery-row-height');
    });

    test('an invalid color value warns', async ({ page }) => {
        await setShortcode(page, `[jzsa-album link="${VALID_LINK}" controls-color="white"]`);
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('expects a color');
    });

    test('the community masked album link produces no warning', async ({ page }) => {
        await setShortcode(page, '[jzsa-album link="hidden-album-link" mode="gallery"]');
        const area = page.locator(VALIDATION);
        await expect(area).not.toBeVisible();
        await expect(area).not.toHaveClass(/jzsa-code-validation--(warning|error)/);
    });

    test('valid values across types produce no message', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" mode="slider" limit="12" ` +
                'gallery-columns="3" controls-color="#1A2B3C" show-navigation="true" ' +
                'slideshow-delay="4-12" mosaic-opacity="0.4" fullscreen-max-width="900"]',
        );
        const area = page.locator(VALIDATION);
        await expect(area).not.toBeVisible();
    });

    test('a valid viewer and viewer-toggle combination produces no message', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" viewer="fullscreen" viewer-toggle="click" ` +
                'viewer-max-width="900" viewer-slideshow="auto"]',
        );
        await expect(page.locator(VALIDATION)).not.toBeVisible();
    });

    test('viewer-toggle warns when viewer is lightbox, fullscreen', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" viewer="lightbox, fullscreen" viewer-toggle="click"]`,
        );
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('"viewer-toggle" is ignored when viewer="lightbox, fullscreen"');
        await expect(area).not.toHaveClass(/jzsa-code-validation--error/);
    });

    test('deprecated lightbox-toggle and fullscreen-toggle both warn with migration hints', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" lightbox-toggle="button-only" fullscreen-toggle="double-click"]`,
        );
        const area = page.locator(VALIDATION);
        await expect(area).toHaveClass(/jzsa-code-validation--warning/);
        await expect(area).toContainText('"lightbox-toggle" is deprecated');
        await expect(area).toContainText('"fullscreen-toggle" is deprecated');
        await expect(area).not.toHaveClass(/jzsa-code-validation--error/);
    });
});
