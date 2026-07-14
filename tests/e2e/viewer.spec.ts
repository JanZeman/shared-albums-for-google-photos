import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

const PAGE_URL = '/?pagename=viewer-fixture';
const LIGHTBOX_BUTTON = '.swiper-button-lightbox:not(.jzsa-gallery-thumb-fs-btn)';
const FULLSCREEN_BUTTON = '.swiper-button-fullscreen:not(.jzsa-gallery-thumb-fs-btn)';
const ACTIVE_SLIDE = '.swiper-slide.swiper-slide-active';

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

async function readAlbumMetrics(album: Locator) {
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

test.describe('Guide Samples Shortcode Tests', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, PAGE_URL);
        await expect(page.locator('.jzsa-album')).toHaveCount(8);
    });

    test('Sample 31 keeps the shared viewer size identical in both modes', async ({ page }) => {
        const album = await waitForAlbum(page, 0);

        await expect(album).toHaveAttribute('data-lightbox-max-width', '600');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-width', '600');
        await expect(album).toHaveAttribute('data-lightbox-max-height', '400');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-height', '400');

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
    });

    test('Sample 32 keeps the shared baseline small and the fullscreen override larger', async ({ page }) => {
        const album = await waitForAlbum(page, 1);

        await expect(album).toHaveAttribute('data-lightbox-max-width', '600');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-width', '1200');
        await expect(album).toHaveAttribute('data-lightbox-max-height', '400');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-height', '800');

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        const lightboxWidth = (await readAlbumMetrics(lightboxAlbum)).width;
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        const fullscreenWidth = await readAlbumMetrics(album).then((metrics) => metrics.width);
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        expect(fullscreenWidth).toBeGreaterThanOrEqual(lightboxWidth);
    });

    test('Sample 33 applies the shared cover fit in both modes', async ({ page }) => {
        const album = await waitForAlbum(page, 2);

        await expect(album).toHaveAttribute('data-lightbox-image-fit', 'cover');
        await expect(album).toHaveAttribute('data-fullscreen-image-fit', 'cover');

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        expect(await readAlbumMetrics(lightboxAlbum)).toMatchObject({ fit: 'cover' });
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        expect(await readAlbumMetrics(album)).toMatchObject({ fit: 'cover' });
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
    });

    test('Sample 34 keeps fullscreen-image-fit isolated from lightbox-image-fit', async ({ page }) => {
        const album = await waitForAlbum(page, 3);

        await expect(album).toHaveAttribute('data-lightbox-image-fit', 'contain');
        await expect(album).toHaveAttribute('data-fullscreen-image-fit', 'cover');

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        const fullscreenFit = await page.evaluate(() => {
            const fs = document.fullscreenElement;
            const img = fs?.querySelector<HTMLImageElement>('.swiper-slide-active img');
            return img ? getComputedStyle(img).objectFit : '';
        });
        expect(fullscreenFit).toBe('cover');
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        expect(await readAlbumMetrics(lightboxAlbum)).toMatchObject({ fit: 'contain' });
        await backdrop(page).locator(FULLSCREEN_BUTTON).evaluate((button: HTMLElement) => button.click());
        await waitForNativeFullscreen(page, true);
        const fullscreenFitViaLightbox = await page.evaluate(() => {
            const fs = document.fullscreenElement;
            const img = fs?.querySelector<HTMLImageElement>('.swiper-slide-active img');
            return img ? getComputedStyle(img).objectFit : '';
        });
        expect(fullscreenFitViaLightbox).toBe('cover');
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('Sample 35 splits the viewer background from the lightbox backdrop', async ({ page }) => {
        const album = await waitForAlbum(page, 4);

        await expect(album).toHaveAttribute('data-lightbox-backdrop-color', 'rgba(0,128,64,0.7)');
        await expect(album).toHaveAttribute('data-lightbox-corner-radius', '16');
        await expect(album).toHaveAttribute('data-background-color', 'rgba(128,0,64,0.7)');

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        const fullscreenBg = await page.evaluate(() => {
            const fs = document.fullscreenElement;
            return fs ? getComputedStyle(fs).getPropertyValue('--gallery-bg-color').trim() : '';
        });
        expect(fullscreenBg).toBe('rgba(128,0,64,0.7)');
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        expect(await readAlbumMetrics(lightboxAlbum)).toMatchObject({
            bg: 'rgba(128,0,64,0.7)',
        });
        const backdropColor = await backdrop(page).evaluate((element) => (element as HTMLElement).style.background);
        expect(backdropColor.replace(/\s+/g, '')).toBe('rgba(0,128,64,0.7)');

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('Sample 36 keeps the lightbox controls override local', async ({ page }) => {
        const album = await waitForAlbum(page, 5);

        await expect(album).toHaveAttribute('data-lightbox-controls-color', '#00A878');

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        const fullscreenControls = await page.evaluate(() => {
            const fs = document.fullscreenElement;
            return fs ? getComputedStyle(fs).getPropertyValue('--jzsa-controls-color').trim() : '';
        });
        expect(fullscreenControls).toBe('#E63946');
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        expect(await readAlbumMetrics(lightboxAlbum)).toMatchObject({ controls: '#00A878' });
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('Sample 37 starts the slideshow only after fullscreen opens', async ({ page }) => {
        const album = await waitForAlbum(page, 6);

        await expect(album.locator(LIGHTBOX_BUTTON)).toHaveCount(0);
        await expect(album.locator('.swiper-button-play-pause')).toBeAttached();

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        await expect(album.locator('.swiper-button-play-pause')).toHaveClass(/playing/, { timeout: 3_000 });

        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
    });

    test('Sample 38 keeps the two slideshow delays separate by mode', async ({ page }) => {
        const album = await waitForAlbum(page, 7);

        const lightboxButton = album.locator(LIGHTBOX_BUTTON);
        const fullscreenButton = album.locator(FULLSCREEN_BUTTON);

        await lightboxButton.click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        const lightboxActiveBefore = await backdrop(page).locator(ACTIVE_SLIDE).getAttribute('data-swiper-slide-index');
        await expect(async () => {
            const current = await backdrop(page).locator(ACTIVE_SLIDE).getAttribute('data-swiper-slide-index');
            expect(current).not.toBe(lightboxActiveBefore);
        }).toPass({ timeout: 2_500 });

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();

        await fullscreenButton.click({ force: true });
        await waitForNativeFullscreen(page, true);
        const fullscreenActiveBefore = await album.locator(ACTIVE_SLIDE).getAttribute('data-swiper-slide-index');
        await expect(async () => {
            const current = await album.locator(ACTIVE_SLIDE).getAttribute('data-swiper-slide-index');
            expect(current).not.toBe(fullscreenActiveBefore);
        }).toPass({ timeout: 10_000 });

        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
    });
});
