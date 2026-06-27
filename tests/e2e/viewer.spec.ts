import { test, expect, type Page, type Locator } from '@playwright/test';

// viewer-fixture page layout (four shortcodes in order):
//   #0  slider  viewer-toggle="lightbox-button, fullscreen-button"  viewer-image-fit="contain"  (all viewer-* shared)
//   #1  slider  viewer-toggle="lightbox-button, fullscreen-button"  concrete lightbox/fullscreen overrides
//   #2  slider  viewer-toggle="lightbox-button, fullscreen-button"  fullscreen-image-fit="cover"  (isolation test)
//   #3  slider  viewer-toggle="lightbox-button, fullscreen-button"  viewer-mosaic="true"  (mosaic arrow geometry)
const PAGE_URL = '/?pagename=viewer-fixture';
const SLIDER_FULLSCREEN_BUTTON = '.swiper-button-fullscreen:not(.jzsa-gallery-thumb-fs-btn)';
const SLIDER_LIGHTBOX_BUTTON = '.swiper-button-lightbox:not(.jzsa-gallery-thumb-fs-btn)';

const backdrop = (page: Page) => page.locator('.jzsa-lightbox-backdrop');

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

test.describe('Viewer shared settings', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(PAGE_URL);
        await expect(page.locator('.jzsa-album')).toHaveCount(4);
    });

    test('shared settings resolve to both lightbox and fullscreen attributes', async ({ page }) => {
        const album = page.locator('.jzsa-album').nth(0);

        await expect(album).toHaveAttribute('data-lightbox-toggle', 'button-only');
        await expect(album).toHaveAttribute('data-fullscreen-toggle', 'button-only');
        await expect(album).toHaveAttribute('data-lightbox-max-width', '640');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-width', '640');
        await expect(album).toHaveAttribute('data-lightbox-max-height', '480');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-height', '480');
        await expect(album).toHaveAttribute('data-lightbox-image-fit', 'contain');
        await expect(album).toHaveAttribute('data-fullscreen-image-fit', 'contain');
        await expect(album).toHaveAttribute('data-lightbox-info-top', 'Shared {item}');
        await expect(album).toHaveAttribute('data-fullscreen-info-top', 'Shared {item}');
        await expect(album).toHaveAttribute('data-lightbox-mosaic', 'true');
        await expect(album).toHaveAttribute('data-fullscreen-mosaic', 'true');
    });

    test('lightbox applies shared info, size, color, and mosaic settings', async ({ page }) => {
        const album = page.locator('.jzsa-album').nth(0);
        const albumId = await album.getAttribute('id');
        await expect(album).toHaveAttribute('data-jzsa-initialized', 'true');
        await album.locator('.swiper-button-lightbox').evaluate((button: HTMLElement) => button.click());
        const openedAlbum = page.locator(`#${albumId}`);

        await expect(openedAlbum).toHaveClass(/jzsa-lightbox-active/);
        await expect(openedAlbum.locator('.jzsa-info-top')).toContainText('Shared 1');
        await expect(openedAlbum.locator('.jzsa-fullscreen-mosaic')).toBeVisible();

        const styles = await openedAlbum.evaluate((element) => {
            const style = getComputedStyle(element);
            return {
                width: element.getBoundingClientRect().width,
                controls: style.getPropertyValue('--jzsa-controls-color').trim(),
                fontSize: style.getPropertyValue('--jzsa-info-font-size').trim(),
            };
        });

        expect(styles.width).toBeLessThanOrEqual(640);
        expect(styles.controls).toBe('#123456');
        expect(styles.fontSize).toBe('18px');
    });

    test('lightbox mosaic arrows do not overlap clickable thumbnails', async ({ page }) => {
        await page.setViewportSize({ width: 640, height: 480 });
        const album = await waitForAlbum(page, 3);
        const albumId = await album.getAttribute('id');
        await expect(album).toHaveAttribute('data-jzsa-initialized', 'true');
        await album.locator('.swiper-button-lightbox').evaluate((button: HTMLElement) => button.click());

        const mosaic = page.locator(`#${albumId} .jzsa-fullscreen-mosaic`);
        await expect(mosaic).toBeVisible();
        await expect(mosaic.locator('.jzsa-mosaic-arrow-next')).toBeAttached();

        const geometry = await mosaic.evaluate((element) => {
            const mosaicRect = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            const leftLane = parseFloat(style.paddingLeft) || 0;
            const rightLane = parseFloat(style.paddingRight) || 0;
            const thumbStart = mosaicRect.left + leftLane;
            const thumbEnd = mosaicRect.right - rightLane;
            const prev = element.querySelector<HTMLElement>('.jzsa-mosaic-arrow-prev')?.getBoundingClientRect();
            const next = element.querySelector<HTMLElement>('.jzsa-mosaic-arrow-next')?.getBoundingClientRect();
            const visibleSlides = Array.from(element.querySelectorAll<HTMLElement>('.swiper-slide'))
                .map((slide) => slide.getBoundingClientRect())
                .filter((rect) => rect.right > thumbStart && rect.left < thumbEnd);
            return {
                prevRight: prev?.right ?? 0,
                nextLeft: next?.left ?? 0,
                firstThumbLeft: visibleSlides[0]?.left ?? 0,
                lastThumbRight: visibleSlides[visibleSlides.length - 1]?.right ?? 0,
                visibleCount: visibleSlides.length,
            };
        });

        expect(geometry.visibleCount).toBeGreaterThan(1);
        expect(geometry.firstThumbLeft).toBeGreaterThanOrEqual(geometry.prevRight);
        expect(geometry.lastThumbRight).toBeLessThanOrEqual(geometry.nextLeft);
    });

    test('concrete settings override shared settings for only their mode', async ({ page }) => {
        const album = page.locator('.jzsa-album').nth(1);

        await expect(album).toHaveAttribute('data-lightbox-max-width', '700');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-width', '1100');
        await expect(album).toHaveAttribute('data-lightbox-info-top', 'Lightbox only');
        await expect(album).toHaveAttribute('data-fullscreen-info-top', 'Fullscreen only');
        await expect(album).toHaveAttribute('data-lightbox-mosaic', 'false');
        await expect(album).toHaveAttribute('data-fullscreen-mosaic', 'true');
    });
});

// Invariant: fullscreen-image-fit controls the fullscreen display regardless of how
// fullscreen is entered. Lightbox-image-fit must never bleed into fullscreen and
// fullscreen-image-fit must never bleed into lightbox.
test.describe('Viewer mode isolation - image-fit entry-path invariant', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(PAGE_URL);
    });

    test('album index 2 has fullscreen-image-fit cover and lightbox-image-fit contain', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album).toHaveAttribute('data-fullscreen-image-fit', 'cover');
        await expect(album).toHaveAttribute('data-lightbox-image-fit', 'contain');
    });

    // Verify the CSS specificity fix is present. The combined .jzsa-lightbox-active:fullscreen
    // selector must exist in the stylesheet so fullscreen-image-fit wins over lightbox-image-fit
    // when both states are active simultaneously. @cross-browser so it runs in Firefox and WebKit.
    test('CSS contains higher-specificity rules for the lightbox-active+fullscreen combined state @cross-browser', async ({ page }) => {
        const hasCombinedRule = await page.evaluate(() => {
            return Array.from(document.styleSheets).some((sheet) => {
                let rules: CSSRuleList;
                try {
                    rules = sheet.cssRules;
                } catch {
                    return false;
                }
                return Array.from(rules).some((rule) => {
                    if (!(rule instanceof CSSStyleRule)) return false;
                    const sel = rule.selectorText;
                    return (
                        sel.includes('jzsa-lightbox-active') &&
                        (sel.includes(':fullscreen') || sel.includes(':-webkit-full-screen')) &&
                        sel.includes('data-fullscreen-image-fit')
                    );
                });
            });
        });
        expect(hasCombinedRule).toBe(true);
    });

    test('fullscreen entered directly shows fullscreen-image-fit cover', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await album.locator(SLIDER_FULLSCREEN_BUTTON).click({ force: true });
        await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout: 10_000 }).toBe(true);

        const fit = await page.evaluate(() => {
            const fs = document.fullscreenElement;
            if (!fs) return null;
            const img = fs.querySelector<HTMLImageElement>('.swiper-slide-active img');
            return img ? getComputedStyle(img).objectFit : null;
        });
        expect(fit).toBe('cover');

        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout: 10_000 }).toBe(false);
    });

    // This is the regression test for Sample 33: fullscreen entered via the lightbox must
    // apply fullscreen-image-fit, not lightbox-image-fit.
    test('fullscreen entered via lightbox shows fullscreen-image-fit cover, not lightbox-image-fit contain', async ({ page }) => {
        const album = await waitForAlbum(page, 2);

        // Open lightbox first.
        await album.locator(SLIDER_LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();

        // Click the fullscreen button inside the lightbox. The album element moves
        // into the backdrop when lightbox opens, so look for the button there.
        await backdrop(page).locator(SLIDER_FULLSCREEN_BUTTON).click({ force: true });
        await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout: 10_000 }).toBe(true);

        // object-fit must be cover (fullscreen-image-fit) not contain (lightbox-image-fit).
        const fit = await page.evaluate(() => {
            const fs = document.fullscreenElement;
            if (!fs) return null;
            const img = fs.querySelector<HTMLImageElement>('.swiper-slide-active img');
            return img ? getComputedStyle(img).objectFit : null;
        });
        expect(fit).toBe('cover');

        // Exit native fullscreen and return to lightbox.
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout: 10_000 }).toBe(false);
        await expect(backdrop(page)).toBeVisible();

        // Second Escape closes the lightbox.
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });
});
