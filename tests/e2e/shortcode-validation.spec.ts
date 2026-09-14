import { test, expect, type Page } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

const GUIDE_URL = '/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos';

// The live validation area is inserted by admin-settings.js immediately after
// the Playground code block on the Guide page.
const VALIDATION = '.jzsa-playground-code-block + .jzsa-code-validation';
const REVERT_BTN = '.jzsa-playground-code-block [data-jzsa-action="revert"]';
const PRETTIFY_BTN = '.jzsa-playground-code-block [data-jzsa-action="prettify"]';

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

    test('editable shortcode controls keep the requested order and minimum height', async ({ page }) => {
        const block = page.locator('.jzsa-playground-code-block');
        const labels = await block.locator('.jzsa-code-block-btns .jzsa-action-btn').allTextContents();
        const dimensions = await block.evaluate((element) => {
            const editor = element.querySelector('code');
            const buttons = element.querySelector('.jzsa-code-block-btns');
            return {
                editor: editor?.getBoundingClientRect().height || 0,
                buttons: buttons?.getBoundingClientRect().height || 0,
            };
        });

        expect(labels).toEqual(['Apply', 'Prettify', 'Revert', 'Copy']);
        expect(dimensions.editor).toBeGreaterThanOrEqual(dimensions.buttons);
    });

    test('Prettify standardizes syntax and applies the preview', async ({ page }) => {
        const preview = page.locator('.jzsa-playground-code-block').locator(
            'xpath=following-sibling::*[contains(@class, "jzsa-preview-container")][1]',
        );
        await expect(preview.locator('[data-lazy-state="loaded"]')).toHaveCount(1, { timeout: 20_000 });
        let previewRequests = 0;
        page.on('request', (request) => {
            const params = new URLSearchParams(request.postData() || '');
            if (
                params.get('action') === 'jzsa_shortcode_preview' &&
                (params.get('shortcode') || '').includes('photos.google.com/share/x')
            ) {
                previewRequests++;
            }
        });
        await setShortcode(
            page,
            "[jzsa-album   corner-radius='16' viewer='lightbox' width='600' link='https://photos.google.com/share/x' mode='slider']",
        );
        const button = page.locator(PRETTIFY_BTN);
        await expect(button).toBeEnabled();
        await expect(page.locator(VALIDATION)).toContainText(
            'Prettify is recommended. It standardizes quotes, spacing, and parameter order, then applies the shortcode.',
        );
        await expect(page.locator(VALIDATION)).toHaveClass(/jzsa-code-validation--warning/);
        await button.click();

        await expect(page.locator('#jzsa-playground-shortcode')).toHaveText(
            '[jzsa-album link="https://photos.google.com/share/x" mode="slider" viewer="lightbox" width="600" corner-radius="16"]',
        );
        // Now that Prettify has a real "is there still something to fix" meaning, applying it
        // leaves nothing left to standardize, so it should end up disabled, not enabled.
        await expect(button).toBeDisabled();
        await expect(page.locator(VALIDATION)).not.toBeVisible();
        await expect.poll(() => previewRequests).toBe(1);
    });

    test('Prettify starts disabled on load and only enables once editing finds something to fix', async ({ page }) => {
        // No server request should fire for the untouched, already-correct prefilled sample -
        // that is the whole point of not auto-checking on load (see admin-settings.js,
        // runValidation's skipServerCheck).
        // jzsaAdminPost() sends a multipart FormData body, not URL-encoded, so match the
        // action name as a substring of the raw multipart payload.
        let validateRequests = 0;
        page.on('request', (request) => {
            if (request.url().includes('admin-ajax.php') && (request.postData() || '').includes('jzsa_validate_shortcode')) {
                validateRequests++;
            }
        });

        const button = page.locator(PRETTIFY_BTN);
        await expect(button).toBeDisabled();
        await page.waitForTimeout(500); // past the 350ms debounce, in case anything auto-fires
        expect(validateRequests).toBe(0);

        await setShortcode(
            page,
            "[jzsa-album   corner-radius='16' viewer='lightbox' width='600' link='https://photos.google.com/share/x' mode='slider']",
        );
        await expect(button).toBeEnabled();
        expect(validateRequests).toBe(1);
    });

    test('Revert starts disabled and enables on any edit, including whitespace-only ones', async ({ page }) => {
        const revertButton = page.locator(REVERT_BTN);
        await expect(revertButton).toBeDisabled();

        const original = await page.locator('#jzsa-playground-shortcode').textContent();
        await setShortcode(page, `${original}  `); // trailing whitespace only, nothing else changed
        await expect(revertButton).toBeEnabled();

        await revertButton.click();
        await expect(revertButton).toBeDisabled();
        await expect(page.locator('#jzsa-playground-shortcode')).toHaveText(original || '');
    });

    test('Prettify reacts to a whitespace-only edit even though the shortcode itself is unchanged', async ({ page }) => {
        // The server trims the posted value twice before comparing (sanitize_textarea_field(),
        // then format()'s own trim()), so it can never see leading/trailing whitespace by
        // itself. The enabled state is decided client-side against the untrimmed text instead -
        // see admin-settings.js, the rawNormalized comparison in runValidation().
        const prettifyButton = page.locator(PRETTIFY_BTN);
        await expect(prettifyButton).toBeDisabled();

        const original = await page.locator('#jzsa-playground-shortcode').textContent();
        await setShortcode(page, `${original}   `);
        await expect(prettifyButton).toBeEnabled();

        await prettifyButton.click();
        await expect(page.locator('#jzsa-playground-shortcode')).toHaveText(original || '');
    });

    test('Revert and Prettify enable together, not staggered by the semantic check delay', async ({ page }) => {
        // Revert's own answer (does the text differ from the original?) is a plain string
        // compare, known instantly; Prettify's needs a 350ms-debounced server round trip.
        // Both must flip from disabled to enabled in the same tick, once Prettify's slower
        // check is ready - not Revert jumping ahead for the intervening ~350ms+.
        const revertButton = page.locator(REVERT_BTN);
        const prettifyButton = page.locator(PRETTIFY_BTN);
        await expect(revertButton).toBeDisabled();
        await expect(prettifyButton).toBeDisabled();

        const original = await page.locator('#jzsa-playground-shortcode').textContent();
        await setShortcode(page, `${original} `);

        // Poll both together immediately after the edit, well before the 350ms debounce
        // fires; at every sampled instant they must agree, never one enabled and the other not.
        for (let i = 0; i < 6; i++) {
            const [revertDisabled, prettifyDisabled] = await Promise.all([
                revertButton.isDisabled(),
                prettifyButton.isDisabled(),
            ]);
            expect(revertDisabled).toBe(prettifyDisabled);
            if (!revertDisabled) {
                break;
            }
            await page.waitForTimeout(50);
        }

        await expect(revertButton).toBeEnabled();
        await expect(prettifyButton).toBeEnabled();
    });

    test('both buttons return to disabled after Prettify restores the original text', async ({ page }) => {
        const revertButton = page.locator(REVERT_BTN);
        const prettifyButton = page.locator(PRETTIFY_BTN);
        const original = await page.locator('#jzsa-playground-shortcode').textContent();

        await setShortcode(page, `${original}  `);
        await expect(prettifyButton).toBeEnabled();
        await expect(revertButton).toBeEnabled();

        await prettifyButton.click();
        await expect(page.locator('#jzsa-playground-shortcode')).toHaveText(original || '');
        // jzsaApplyPreview() unconditionally clears `disabled` once its own request settles,
        // with no notion of Prettify's "is there anything left to fix" meaning - this must
        // win over that generic reset, not lose to it.
        await expect(prettifyButton).toBeDisabled();
        await expect(revertButton).toBeDisabled();
    });

    test('both buttons return to disabled after Revert restores the original text', async ({ page }) => {
        const revertButton = page.locator(REVERT_BTN);
        const prettifyButton = page.locator(PRETTIFY_BTN);
        const original = await page.locator('#jzsa-playground-shortcode').textContent();

        await setShortcode(page, `${original}  `);
        await expect(revertButton).toBeEnabled();
        await expect(prettifyButton).toBeEnabled();

        await revertButton.click();
        await expect(page.locator('#jzsa-playground-shortcode')).toHaveText(original || '');
        // Same generic-reset hazard as Prettify above, on revertBtn instead.
        await expect(revertButton).toBeDisabled();
        await expect(prettifyButton).toBeDisabled();
    });

    test('Prettify does not flicker disabled/enabled while typing continues', async ({ page }) => {
        // Regression guard for the eager `prettifyBtn.disabled = true` that used to run at
        // the top of every runValidation() call: typing several characters in quick
        // succession (faster than the 350ms semantic-check debounce) used to flip the
        // button disabled, then re-enabled a moment later, on every single keystroke, even
        // though the final answer rarely changed. It must now settle once, not oscillate.
        const prettifyButton = page.locator(PRETTIFY_BTN);
        const original = await page.locator('#jzsa-playground-shortcode').textContent();

        await page.evaluate(() => {
            const btn = document.querySelector(
                '.jzsa-playground-code-block [data-jzsa-action="prettify"]',
            ) as HTMLButtonElement;
            (window as unknown as { __disabledLog: boolean[] }).__disabledLog = [btn.disabled];
            new MutationObserver(() => {
                const log = (window as unknown as { __disabledLog: boolean[] }).__disabledLog;
                if (btn.disabled !== log[log.length - 1]) {
                    log.push(btn.disabled);
                }
            }).observe(btn, { attributes: true, attributeFilter: ['disabled'] });
        });

        let current = original || '';
        for (let i = 0; i < 5; i++) {
            current += ' ';
            await setShortcode(page, current);
            await page.waitForTimeout(150); // faster than the 350ms debounce
        }
        await expect(prettifyButton).toBeEnabled(); // let the final debounce settle

        const log = await page.evaluate(
            () => (window as unknown as { __disabledLog: boolean[] }).__disabledLog,
        );
        // Exactly the starting value, then exactly one flip to enabled once settled - no
        // disabled/enabled oscillation during the five keystrokes in between.
        expect(log).toEqual([true, false]);
    });

    test('all published Guide shortcodes use the canonical leading parameter order', async ({ page }) => {
        const violations = await page.locator('.jzsa-code-block code').evaluateAll((nodes) => {
            return nodes.flatMap((node) => {
                const shortcode = (node.textContent || '').trim();
                if (!shortcode.startsWith('[jzsa-album')) {
                    return [];
                }
                const names = Array.from(shortcode.matchAll(/\s([\w-]+)\s*=/g), (match) => match[1]);
                const priority = [
                    'link',
                    'mode',
                    'viewer',
                    'viewer-trigger',
                    'lightbox-trigger',
                    'fullscreen-trigger',
                ].filter((name) => names.includes(name));
                const valid = priority.every((name, index) => names[index] === name);
                return valid ? [] : [shortcode];
            });
        });

        expect(violations).toEqual([]);
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
        await setShortcode(page, '[jzsa-album link="hidden-album-link" viewer="lightbox" mode="gallery"]');
        const area = page.locator(VALIDATION);
        await expect(area).not.toBeVisible();
        await expect(area).not.toHaveClass(/jzsa-code-validation--(warning|error)/);
    });

    test('valid values across types produce no message', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" viewer="fullscreen" mode="slider" limit="12" ` +
                'gallery-columns="3" controls-color="#1A2B3C" show-navigation="true" ' +
                'slideshow-delay="4-12" mosaic-opacity="0.4" fullscreen-max-width="900"]',
        );
        const area = page.locator(VALIDATION);
        await expect(area).not.toBeVisible();
    });

    test('a valid viewer and viewer-trigger combination produces no message', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" viewer="fullscreen" viewer-trigger="click" ` +
                'viewer-max-width="900" viewer-slideshow="auto"]',
        );
        await expect(page.locator(VALIDATION)).not.toBeVisible();
    });

    test('viewer-trigger is an error when viewer is both', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" viewer="both" viewer-trigger="click"]`,
        );
        const area = page.locator(VALIDATION);
		await expect(area).toHaveClass(/jzsa-code-validation--error/);
		await expect(area).toContainText('shared viewer trigger cannot be used with viewer="both"');
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

    test('legacy syntax can be updated while unrelated warnings remain visible', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" fullscreen-toggle="double-click" sparkle="true"]`,
        );
        const area = page.locator(VALIDATION);
        await expect(area).toContainText('This shortcode uses legacy viewer syntax');
        await expect(area).toContainText('Unknown parameter "sparkle"');
        await area.getByRole('button', { name: 'Update to Current Syntax' }).click();

        await expect(page.locator('#jzsa-playground-shortcode')).toContainText(
            `link="${VALID_LINK}" viewer="fullscreen" viewer-trigger="double-click" sparkle="true"`,
        );
        await expect(area).toContainText('Unknown parameter "sparkle"');
        await expect(area.getByRole('button', { name: 'Update to Current Syntax' })).toHaveCount(0);
    });

    test('Update to Current Syntax removes line-break whitespace from the album link', async ({ page }) => {
        const link =
            'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ' +
            '?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R';
        await setShortcode(
            page,
            `[jzsa-album link="${link.replace('?key=', '?\n  key=')}" mode="slider" corner-radius="16"]`,
        );
        const area = page.locator(VALIDATION);
        await expect(area).toContainText(
            'The Google Photos link contains invalid whitespace. The generated shortcode removes it because URLs cannot contain spaces or line breaks.',
        );
        await area.getByRole('button', { name: 'Update to Current Syntax' }).click();

        await expect(page.locator('#jzsa-playground-shortcode')).toHaveText(
            `[jzsa-album link="${link}" mode="slider" viewer="fullscreen" corner-radius="16"]`,
        );
        await expect(area).not.toContainText('The Google Photos link contains invalid whitespace.');
    });

    test('validation limits the number of visible issues', async ({ page }) => {
        await setShortcode(
            page,
            `[jzsa-album link="${VALID_LINK}" first-x="1" second-x="2" third-x="3" ` +
                'fourth-x="4" fifth-x="5" sixth-x="6"]',
        );
        const area = page.locator(VALIDATION);
        await expect(area.locator('.jzsa-code-validation__list li')).toHaveCount(6);
		await expect(area).toContainText(
			'2 additional issues are hidden to keep this list readable. Fix the visible issues, then validate again.',
		);
    });
});

test.describe('Shortcode Migration Tool', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(GUIDE_URL);
        await page.locator('#jzsa-guide-migration-details').evaluate((details: HTMLDetailsElement) => {
            details.open = true;
        });
        await expect(page.locator('#jzsa-migrate-shortcode')).toBeVisible();
    });

    test('preserves legacy behavior and loads the modern shortcode in Playground', async ({ page }) => {
        await page.locator('#jzsa-migration-shortcode').fill(
            '[jzsa-album link="https://photos.google.com/share/test" ' +
                'fullscreen-toggle="click" fullscreen-source-width="800"]',
        );
        await page.locator('#jzsa-migrate-shortcode').click();

        const output = page.locator('#jzsa-migrated-shortcode');
        await expect(output).toBeVisible();
        await expect(output).toHaveValue(/viewer="fullscreen"/);
        await expect(output).toHaveValue(/viewer-trigger="click"/);
        await expect(output).toHaveValue(/viewer-source-width="800"/);
        await expect(output).not.toHaveValue(/fullscreen-source-width=/);
        await expect(output).not.toHaveValue(/lightbox-source-width=/);
        await expect(page.locator('#jzsa-migration-source-validation')).toContainText(
            'Parameter "fullscreen-toggle" is deprecated',
        );
        await expect(page.locator('.jzsa-migration-replacements')).toContainText('fullscreen-toggle');
        await expect(page.locator('.jzsa-migration-replacements')).toContainText('viewer="fullscreen"');
        await expect(page.locator('.jzsa-migration-replacements')).toContainText('viewer-trigger="click"');
        await expect(page.locator('.jzsa-migration-replacements')).toContainText('What changed:');
        await expect(page.locator('#jzsa-migration-result')).toContainText('Behavior preserved.');
        await expect(page.locator('#jzsa-migration-result')).not.toContainText('Validation: valid');

        await page.getByRole('button', { name: 'Preview in Playground' }).click();
        await expect(page.locator('#jzsa-playground-shortcode')).toContainText('viewer="fullscreen"');
    });

    test('keeps the click trigger when Use Lightbox matches the current viewer', async ({ page }) => {
        await page.locator('#jzsa-migration-shortcode').fill(
            '[jzsa-album link="https://photos.google.com/share/test" viewer="lightbox" ' +
                'viewer-toggle="click" corner-radius="16" viewer-toggle="click"]',
        );
        await page.getByLabel('Use Lightbox').check();
        await page.locator('#jzsa-migrate-shortcode').click();

        await expect(page.locator('#jzsa-migrated-shortcode')).toHaveValue(
            '[jzsa-album link="https://photos.google.com/share/test" viewer="lightbox" ' +
                'viewer-trigger="click" corner-radius="16"]',
        );
        await expect(page.locator('#jzsa-migration-result')).toContainText('Behavior preserved.');
        await expect(page.locator('#jzsa-migration-result')).not.toContainText(
            'Viewer behavior intentionally changed.',
        );
    });

    test('rejects competing legacy gestures instead of guessing an owner', async ({ page }) => {
        await page.locator('#jzsa-migration-shortcode').fill(
            '[jzsa-album link="https://photos.google.com/share/test" ' +
                'lightbox-toggle="click" fullscreen-toggle="double-click"]',
        );
        await page.locator('#jzsa-migrate-shortcode').click();

        await expect(page.locator('#jzsa-migration-source-validation')).toContainText(
            'legacy Lightbox and Fullscreen gestures compete',
        );
        await expect(page.locator('#jzsa-migrated-shortcode')).toHaveCount(0);
    });

    test('preserves and highlights an unknown parameter', async ({ page }) => {
        await page.locator('#jzsa-migration-shortcode').fill(
            '[jzsa-album link="https://photos.google.com/share/test" sparkle="true"]',
        );
        await page.locator('#jzsa-migrate-shortcode').click();

        await expect(page.locator('#jzsa-migrated-shortcode')).toHaveValue(/sparkle="true"/);
        await expect(page.locator('#jzsa-migration-result')).toContainText('Migrated shortcode validation:');
        await expect(page.locator('#jzsa-migration-result')).toContainText('Unknown parameter "sparkle"');
    });
});
