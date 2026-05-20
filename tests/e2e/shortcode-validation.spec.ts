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
