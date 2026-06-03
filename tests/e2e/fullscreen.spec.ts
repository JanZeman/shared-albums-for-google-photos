import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

// The lightbox-fixture page contains five shortcodes (see tests/e2e/README.md).
// Album at index 2 has both lightbox-toggle="button-only" AND fullscreen-toggle="button-only"
// (dual expand), making it the source of truth for testing both expand modes on the
// same slider at once.
//
// Album at index 1 has lightbox-toggle="button-only" + fullscreen-toggle="disabled",
// so it has no fullscreen button at all and is useful as a negative control.
//
// Album at index 0 has lightbox-toggle="click" + fullscreen-toggle="disabled".
//
// One native fullscreen slider has fullscreen-display max dimensions; it covers
// the Firefox regression from f7d5911 where grouped vendor fullscreen selectors
// made Firefox drop the limited-presentation rules.
const FIXTURE_URL = '/?pagename=lightbox-fixture';
const SLIDER_FULLSCREEN_BUTTON = '.swiper-button-fullscreen:not(.jzsa-gallery-thumb-fs-btn)';
const SLIDER_LIGHTBOX_BUTTON = '.swiper-button-lightbox:not(.jzsa-gallery-thumb-fs-btn)';

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album.swiper:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

async function waitForLimitedFullscreenAlbum(page: Page): Promise<Locator> {
    const album = page.locator(
        '.jzsa-album.swiper[data-fullscreen-display-max-width="320"][data-fullscreen-display-max-height="240"]'
    );
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

const backdrop = (page: Page) => page.locator('.jzsa-lightbox-backdrop');

test.describe('Fullscreen - button present / absent', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('fullscreen button absent when fullscreen-toggle is disabled', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album.locator(SLIDER_FULLSCREEN_BUTTON)).not.toBeAttached();
    });

    test('fullscreen button present when fullscreen-toggle is button-only', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album.locator(SLIDER_FULLSCREEN_BUTTON)).toBeVisible();
    });

    test('lightbox button also present in dual-expand album (index 2)', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album.locator(SLIDER_LIGHTBOX_BUTTON)).toBeVisible();
        await expect(album.locator(SLIDER_FULLSCREEN_BUTTON)).toBeVisible();
    });
});

test.describe('Fullscreen - dual expand interaction', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('clicking fullscreen button does NOT open the lightbox overlay', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        // force:true: cover-fit slide image overlaps button bounding box.
        await album.locator(SLIDER_FULLSCREEN_BUTTON).click({ force: true });
        // The lightbox backdrop must stay hidden; fullscreen uses the native API.
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('clicking lightbox button opens the overlay (not native fullscreen) @cross-browser', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await album.locator(SLIDER_LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        // Native fullscreen API must NOT be active after lightbox open.
        const isNativeFullscreen = await page.evaluate(() => !!document.fullscreenElement);
        expect(isNativeFullscreen).toBe(false);
    });

    test('jzsa-has-dual-expand class present on album with both toggles', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album).toHaveClass(/jzsa-has-dual-expand/);
    });

    test('album without fullscreen button lacks jzsa-has-dual-expand class', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album).not.toHaveClass(/jzsa-has-dual-expand/);
    });
});

test.describe('Fullscreen - Firefox limited presentation regression', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('Firefox applies fullscreen display caps in native fullscreen @cross-browser', async ({ page, browserName }) => {
        const album = await waitForLimitedFullscreenAlbum(page);

        const hasFirefoxRule = await page.evaluate(() => {
            return Array.from(document.styleSheets).some((sheet) => {
                let rules: CSSRuleList;
                try {
                    rules = sheet.cssRules;
                } catch {
                    return false;
                }

                return Array.from(rules).some((rule) => {
                    return rule instanceof CSSStyleRule &&
                        rule.selectorText === '.jzsa-album:fullscreen[data-has-fullscreen-display-limits="true"] .swiper-zoom-container' &&
                        rule.style.width.includes('var(--jzsa-fullscreen-display-max-width');
                });
            });
        });
        expect(hasFirefoxRule).toBe(true);

        await expect(album).toHaveAttribute('data-fullscreen-display-max-width', '320');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-height', '240');

        if (browserName !== 'firefox') {
            return;
        }

        const zoomContainer = album.locator('.swiper-slide-active .swiper-zoom-container').first();

        await album.locator(SLIDER_FULLSCREEN_BUTTON).click({ force: true });
        await expect
            .poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout: 10_000 })
            .toBe(true);
        await expect(album).toHaveClass(/jzsa-is-fullscreen/);
        await expect(album).toHaveAttribute('data-has-fullscreen-display-limits', 'true');

        const box = await zoomContainer.boundingBox();
        expect(box).not.toBeNull();
        expect(Math.round(box!.width)).toBeLessThanOrEqual(322);
        expect(Math.round(box!.height)).toBeLessThanOrEqual(242);

        await page.keyboard.press('Escape');
        await expect
            .poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout: 10_000 })
            .toBe(false);
    });
});

test.describe('Fullscreen - lightbox close methods (via dual-expand album)', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('Escape closes the lightbox opened from dual-expand album', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await album.locator(SLIDER_LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('close button closes the lightbox opened from dual-expand album', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await album.locator(SLIDER_LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        await page.locator('.jzsa-lightbox-close').click();
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('clicking the backdrop edge closes the lightbox', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await album.locator(SLIDER_LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        await backdrop(page).click({ position: { x: 10, y: 10 } });
        await expect(backdrop(page)).not.toBeVisible();
    });
});

test.describe('Fullscreen - data attributes on rendered albums', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('album index 1 has data-fullscreen-toggle="disabled"', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album).toHaveAttribute('data-fullscreen-toggle', 'disabled');
    });

    test('album index 2 has data-fullscreen-toggle="button-only"', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album).toHaveAttribute('data-fullscreen-toggle', 'button-only');
    });

    test('album index 2 has data-lightbox-toggle="button-only"', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album).toHaveAttribute('data-lightbox-toggle', 'button-only');
    });
});
