import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

// The fixture page contains five shortcodes in this order:
//   #0  slider  lightbox-toggle="click"        fullscreen-toggle="disabled"
//   #1  slider  lightbox-toggle="button-only"  fullscreen-toggle="disabled"
//   #2  slider  lightbox-toggle="button-only"  fullscreen-toggle="button-only"  (dual)
//   #3  gallery lightbox-toggle="button-only"  fullscreen-toggle="disabled"
//   #4  gallery lightbox-toggle="button-only"  fullscreen-toggle="button-only"  (dual)
const FIXTURE_URL = '/?pagename=lightbox-fixture';
const ACTIVE_SLIDER_SLIDE = '.swiper-slide.swiper-slide-active';
const SLIDER_FULLSCREEN_BUTTON = '.swiper-button-fullscreen:not(.jzsa-gallery-thumb-fs-btn)';
const SLIDER_LIGHTBOX_BUTTON = '.swiper-button-lightbox:not(.jzsa-gallery-thumb-fs-btn)';

// Wait until the nth top-level album has finished loading (jzsa-loader-pending removed).
// Excludes .jzsa-gallery-slideshow: those are dynamically created child Swipers inside gallery
// containers and would shift the nth() indices as gallery albums initialize.
// Scroll into view first to trigger IntersectionObserver-based lazy init (gallery mode).
async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

async function waitForSliderAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album.swiper:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

const backdrop = (page: Page) => page.locator('.jzsa-lightbox-backdrop');

test.describe('Lightbox - slider / click trigger', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('clicking slider opens the lightbox overlay @cross-browser', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        await expect(backdrop(page)).toBeVisible();
    });

    test('lightbox backdrop has dialog semantics when opened', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        const overlay = backdrop(page);

        await expect(overlay).toBeVisible();
        await expect(overlay).toHaveAttribute('role', 'dialog');
        await expect(overlay).toHaveAttribute('aria-modal', 'true');
        await expect(overlay).toHaveAttribute('aria-label', 'Photo viewer');
    });

    test('lightbox shows the album in fullscreen style (jzsa-is-fullscreen class)', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        await expect(backdrop(page)).toBeVisible();
        // openLightbox moves the album element into the backdrop div and adds these classes.
        // After the move, the original nth(0) locator shifts, so check inside the backdrop.
        await expect(backdrop(page).locator('.jzsa-album.jzsa-is-fullscreen')).toBeAttached();
    });

    test('Escape closes the lightbox', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        await expect(backdrop(page)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('clicking the backdrop closes the lightbox', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        await expect(backdrop(page)).toBeVisible();
        // Click the edge of the backdrop (outside the album element inside it).
        await backdrop(page).click({ position: { x: 10, y: 10 } });
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('close button closes the lightbox', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        await expect(backdrop(page)).toBeVisible();
        await page.locator('.jzsa-lightbox-close').click();
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('close button is labelled for keyboard and assistive technology users', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        const close = page.locator('.jzsa-lightbox-close');

        await expect(close).toBeVisible();
        await expect(close).toHaveAttribute('type', 'button');
        await expect(close).toHaveAttribute('aria-label', 'Close');
        await expect(close).toHaveAttribute('title', 'Close');
    });

    test('lightbox button is present on the slider in click mode (alongside the slide trigger)', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await expect(album.locator(SLIDER_LIGHTBOX_BUTTON)).toBeVisible();
    });

    test('lightbox button also opens the lightbox in click mode', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(SLIDER_LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
    });
});

test.describe('Lightbox - slider / button-only trigger', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('lightbox button is visible on the slider', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 1);
        await expect(album.locator(SLIDER_LIGHTBOX_BUTTON)).toBeVisible();
    });

    test('clicking the slide image does NOT open the lightbox', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 1);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('clicking the lightbox button opens the lightbox', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 1);
        // force:true bypasses Playwright's pointer-events hit-test: the cover-fit slide image
        // overlaps the button's bounding box but the button's z-index keeps it on top visually.
        await album.locator(SLIDER_LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
    });

    test('no fullscreen button present when fullscreen-toggle is disabled', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 1);
        await expect(album.locator(SLIDER_FULLSCREEN_BUTTON)).not.toBeAttached();
    });
});

test.describe('Lightbox - slider / dual expand (lightbox + fullscreen)', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('both lightbox and fullscreen buttons are visible', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 2);
        await expect(album.locator(SLIDER_LIGHTBOX_BUTTON)).toBeVisible();
        await expect(album.locator(SLIDER_FULLSCREEN_BUTTON)).toBeVisible();
    });

    test('album has jzsa-has-dual-expand class', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 2);
        await expect(album).toHaveClass(/jzsa-has-dual-expand/);
    });

    test('lightbox button opens overlay (not native fullscreen)', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 2);
        // force:true: same pointer-events reason as slider/button-only test above.
        await album.locator(SLIDER_LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        // Native fullscreen would make document.fullscreenElement non-null.
        const isNativeFullscreen = await page.evaluate(() => !!document.fullscreenElement);
        expect(isNativeFullscreen).toBe(false);
    });
});

test.describe('Lightbox - gallery mode', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('gallery has lightbox-toggle attribute set', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        await expect(album).toHaveAttribute('data-lightbox-toggle', 'button-only');
    });

    test('gallery has fullscreen-toggle set to disabled', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        await expect(album).toHaveAttribute('data-fullscreen-toggle', 'disabled');
    });

    test('ghost button gone: closing lightbox then hovering another item hides the original button', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        const items = album.locator('.jzsa-gallery-item');

        // Open lightbox via item 0.
        await items.nth(0).hover();
        await items.nth(0).locator('.jzsa-gallery-thumb-fs-btn').click();
        await expect(backdrop(page)).toBeVisible();

        // Close via Escape.
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();

        // Hover a different item. Item 0's button should now be hidden (ghost bug would keep it visible).
        // Use toHaveCSS('opacity', '0') rather than not.toBeVisible(): the button uses opacity-based
        // hiding (display:flex is always set), so Playwright's bounding-box visibility check always
        // returns true. toHaveCSS auto-retries until the 100ms CSS transition completes.
        await items.nth(1).scrollIntoViewIfNeeded();
        await items.nth(1).hover();
        await expect(items.nth(0).locator('.jzsa-gallery-thumb-fs-btn')).toHaveCSS('opacity', '0');
    });

    test('gallery dual expand shows two buttons per item on hover', async ({ page }) => {
        const album = await waitForAlbum(page, 4);
        // Wait for gallery items to be attached before trying to interact with them.
        const firstItem = album.locator('.jzsa-gallery-item').first();
        await firstItem.waitFor({ state: 'attached', timeout: 30_000 });
        await firstItem.scrollIntoViewIfNeeded();
        await firstItem.hover();
        const buttons = firstItem.locator('.jzsa-gallery-thumb-fs-btn');
        await expect(buttons).toHaveCount(2);
    });

    test('gallery thumbnail lightbox button opens from keyboard', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        const firstButton = album.locator('.jzsa-gallery-item').first().locator('.jzsa-gallery-thumb-fs-btn');
        await firstButton.waitFor({ state: 'attached', timeout: 30_000 });
        await firstButton.focus();

        await page.keyboard.press('Enter');

        await expect(backdrop(page)).toBeVisible({ timeout: 5_000 });
    });
});

test.describe('Lightbox - Accessibility Depth', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('focus is trapped inside the lightbox when tabbed', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        const overlay = backdrop(page);
        await expect(overlay).toBeVisible();

        const closeBtn = page.locator('.jzsa-lightbox-close');
        await expect(closeBtn).toBeAttached();

        const focusableElements = await overlay.evaluate((el) => {
            const els = Array.from(el.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')) as HTMLElement[];
            return els.map(e => ({
                tagName: e.tagName.toLowerCase(),
                className: e.className
            }));
        });

        if (focusableElements.length > 0) {
            await overlay.focus();
            await page.keyboard.press('Tab');
            
            for (let i = 0; i < 15; i++) {
                await page.keyboard.press('Tab');
                const isInsideBackdrop = await page.evaluate(() => {
                    const active = document.activeElement;
                    if (!active) return false;
                    return active.closest('.jzsa-lightbox-backdrop') !== null;
                });
                expect(isInsideBackdrop).toBe(true);
            }
        }
    });

    test('focus is restored back to the originating trigger button when closed', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        const firstButton = album.locator('.jzsa-gallery-item').first().locator('.jzsa-gallery-thumb-fs-btn');
        await firstButton.waitFor({ state: 'attached', timeout: 30_000 });
        
        await firstButton.focus();
        await expect(firstButton).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(backdrop(page)).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();

        await expect(firstButton).toBeFocused();
    });

    test('pressing Escape closes the lightbox even when active inside other focusable elements', async ({ page }) => {
        const album = await waitForSliderAlbum(page, 0);
        await album.locator(ACTIVE_SLIDER_SLIDE).click();
        const overlay = backdrop(page);
        await expect(overlay).toBeVisible();

        const closeBtn = page.locator('.jzsa-lightbox-close');
        await closeBtn.focus();
        await expect(closeBtn).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(overlay).not.toBeVisible();
    });
});

