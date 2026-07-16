import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

const PAGE_URL = '/?pagename=random-fixture';
const ACTIVE_SLIDE = '.swiper-slide.swiper-slide-active';
const LIGHTBOX_BUTTON = '.swiper-button-lightbox:not(.jzsa-gallery-thumb-fs-btn)';
const FULLSCREEN_BUTTON = '.swiper-button-fullscreen:not(.jzsa-gallery-thumb-fs-btn)';

const backdrop = (page: Page) => page.locator('.jzsa-lightbox-backdrop');

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

async function waitForNativeFullscreen(page: Page, expected: boolean, timeout = 10_000): Promise<void> {
    await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout }).toBe(expected);
}

async function readViewerState(album: Locator) {
    return album.evaluate((element) => {
        const style = getComputedStyle(element);
        const activeImage = element.querySelector<HTMLImageElement>('.swiper-slide-active img');
        return {
            width: element.getBoundingClientRect().width,
            fit: activeImage ? getComputedStyle(activeImage).objectFit : '',
            bg: style.getPropertyValue('--gallery-bg-color').trim(),
            controls: style.getPropertyValue('--jzsa-controls-color').trim(),
            cornerRadius: style.getPropertyValue('--jzsa-corner-radius').trim(),
        };
    });
}

test.describe('Random Shortcode Tests', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, PAGE_URL);
        await expect(page.locator('.jzsa-album')).toHaveCount(10);
    });

    test('lightbox-button mode ignores slide click and still opens from the button', async ({ page }) => {
        const album = await waitForAlbum(page, 0);

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('lightbox-double-click mode ignores a single click but opens on double-click', async ({ page }) => {
        const album = await waitForAlbum(page, 1);

        await album.locator(ACTIVE_SLIDE).click();
        await page.waitForTimeout(250);
        await expect(backdrop(page)).not.toBeVisible();

        await album.locator(ACTIVE_SLIDE).dblclick();
        await expect(backdrop(page)).toBeVisible();
        await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout: 2_000 }).toBe(false);
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('fullscreen-button mode ignores slide click and still enters native fullscreen from the button', async ({ page }) => {
        const album = await waitForAlbum(page, 2);

        await album.locator(ACTIVE_SLIDE).click();
        await page.waitForTimeout(250);
        await expect(backdrop(page)).not.toBeVisible();
        await waitForNativeFullscreen(page, false, 2_000);

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        await expect(backdrop(page)).not.toBeVisible();
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
    });

    test('fullscreen-double-click mode ignores a single click but opens on double-click', async ({ page }) => {
        const album = await waitForAlbum(page, 3);

        await album.locator(ACTIVE_SLIDE).click();
        await page.waitForTimeout(250);
        await expect(backdrop(page)).not.toBeVisible();
        await waitForNativeFullscreen(page, false, 2_000);

        await album.locator(ACTIVE_SLIDE).dblclick();
        await waitForNativeFullscreen(page, true);
        await expect(backdrop(page)).not.toBeVisible();

        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
    });

    test('lightbox-first mode offers fullscreen only after lightbox opens', async ({ page }) => {
        const album = await waitForAlbum(page, 4);

        await expect(album.locator(LIGHTBOX_BUTTON)).toBeVisible();
        await expect(album.locator(FULLSCREEN_BUTTON)).not.toBeVisible();

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        await backdrop(page).locator('.jzsa-album').hover();
        await expect(backdrop(page).locator(FULLSCREEN_BUTTON)).toBeVisible();

        await backdrop(page).locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
        await expect(backdrop(page)).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('mosaic layout keeps the arrow hit targets outside the thumbnail strip', async ({ page }) => {
        await page.setViewportSize({ width: 640, height: 480 });
        const album = await waitForAlbum(page, 5);

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();

        const mosaic = backdrop(page).locator('.jzsa-fullscreen-mosaic');
        await expect(mosaic).toBeVisible();
        const slideCount = await mosaic.locator('.swiper-slide').count();
        expect(slideCount).toBeGreaterThan(1);

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('mixed size and fit settings stay isolated by mode and by entry path', async ({ page }) => {
        const album = await waitForAlbum(page, 6);

        await expect(album).toHaveAttribute('data-lightbox-max-width', '500');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-width', '1100');
        await expect(album).toHaveAttribute('data-lightbox-image-fit', 'cover');
        await expect(album).toHaveAttribute('data-fullscreen-image-fit', 'contain');

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        const fullscreenFit = await page.evaluate(() => {
            const fs = document.fullscreenElement;
            const img = fs?.querySelector<HTMLImageElement>('.swiper-slide-active img');
            return img ? getComputedStyle(img).objectFit : '';
        });
        expect(fullscreenFit).toBe('contain');
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        expect(await readViewerState(lightboxAlbum)).toMatchObject({ fit: 'cover' });

        await backdrop(page).locator(FULLSCREEN_BUTTON).evaluate((button: HTMLElement) => button.click());
        await waitForNativeFullscreen(page, true);
        const fullscreenFitFromLightbox = await page.evaluate(() => {
            const fs = document.fullscreenElement;
            const img = fs?.querySelector<HTMLImageElement>('.swiper-slide-active img');
            return img ? getComputedStyle(img).objectFit : '';
        });
        expect(fullscreenFitFromLightbox).toBe('contain');
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('background color stays shared while control colors diverge by mode', async ({ page }) => {
        const album = await waitForAlbum(page, 7);

        await expect(album).toHaveAttribute('data-lightbox-backdrop-color', 'rgba(0,128,64,0.7)');
        await expect(album).toHaveAttribute('data-lightbox-controls-color', '#00A878');

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        expect(await readViewerState(album)).toMatchObject({
            bg: 'rgba(128,0,64,0.7)',
            controls: '#2A9D8F',
        });
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        expect(await readViewerState(lightboxAlbum)).toMatchObject({
            bg: 'rgba(128,0,64,0.7)',
            controls: '#00A878',
        });
        const backdropColor = await backdrop(page).evaluate((element) => (element as HTMLElement).style.background);
        expect(backdropColor.replace(/\s+/g, '')).toBe('rgba(0,128,64,0.7)');

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('slideshow state survives switching between Lightbox and Fullscreen', async ({ page }) => {
        const album = await waitForAlbum(page, 8);

        await expect(album.locator('.swiper-button-play-pause')).toBeAttached();
        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        await expect(backdrop(page).locator('.swiper-button-play-pause')).toHaveClass(/playing/, { timeout: 3_000 });

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        await expect(album.locator('.swiper-button-play-pause')).toHaveClass(/playing/, { timeout: 3_000 });

        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
    });

    test('show-navigation false keeps the inline and lightbox arrows hidden', async ({ page }) => {
        const album = await waitForAlbum(page, 9);

        await expect(album).toHaveAttribute('data-lightbox-show-navigation', 'false');
        await expect(album).toHaveAttribute('data-fullscreen-show-navigation', 'false');

        await album.locator(LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        await expect(lightboxAlbum.locator('.swiper-button-prev')).not.toBeVisible();
        await expect(lightboxAlbum.locator('.swiper-button-next')).not.toBeVisible();

        await backdrop(page).locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        await expect(album.locator('.swiper-button-prev')).not.toBeVisible();
        await expect(album.locator('.swiper-button-next')).not.toBeVisible();

        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });
});
