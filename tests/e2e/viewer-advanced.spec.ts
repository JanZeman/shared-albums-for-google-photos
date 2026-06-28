import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

const PAGE_URL = '/?pagename=viewer-fixture';
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
        };
    });
}

test.describe('Viewer advanced entry paths', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, PAGE_URL);
        await expect(page.locator('.jzsa-album')).toHaveCount(10);
    });

    test('explicit lightbox-button mode ignores slide click and still opens from the button', async ({ page }) => {
        const album = await waitForAlbum(page, 9);

        await album.locator(ACTIVE_SLIDE).click();
        await page.waitForTimeout(250);
        await expect(backdrop(page)).not.toBeVisible();

        await album.locator(LIGHTBOX_BUTTON).evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('double-click lightbox mode ignores a single click but opens on double-click', async ({ page }) => {
        const album = await waitForAlbum(page, 4);

        await album.locator(ACTIVE_SLIDE).click();
        await page.waitForTimeout(250);
        await expect(backdrop(page)).not.toBeVisible();

        await album.locator(ACTIVE_SLIDE).dblclick();
        await expect(backdrop(page)).toBeVisible();
        await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { timeout: 2_000 }).toBe(false);
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('double-click fullscreen mode ignores a single click but opens on double-click', async ({ page }) => {
        const album = await waitForAlbum(page, 5);

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

    test('shared size and fit survive both fullscreen entry paths', async ({ page }) => {
        const album = await waitForAlbum(page, 6);

        await expect(album).toHaveAttribute('data-lightbox-max-width', '700');
        await expect(album).toHaveAttribute('data-fullscreen-display-max-width', '1100');
        await expect(album).toHaveAttribute('data-lightbox-image-fit', 'contain');
        await expect(album).toHaveAttribute('data-fullscreen-image-fit', 'cover');

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        expect(await readViewerState(album)).toMatchObject({
            fit: 'cover',
        });
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        await album.locator(LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        expect(await readViewerState(album)).toMatchObject({
            fit: 'cover',
        });

        await backdrop(page).locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        expect(await readViewerState(album)).toMatchObject({
            fit: 'cover',
        });
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);
        await expect(backdrop(page)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('shared background stays aligned while control colors diverge by mode', async ({ page }) => {
        const album = await waitForAlbum(page, 7);

        await expect(album).toHaveAttribute('data-lightbox-backdrop-color', 'rgba(0,128,64,0.7)');
        await expect(album).toHaveAttribute('data-lightbox-controls-color', '#00A878');
        await expect(album).toHaveAttribute('data-fullscreen-controls-color', '#2A9D8F');

        await album.locator(FULLSCREEN_BUTTON).click({ force: true });
        await waitForNativeFullscreen(page, true);
        expect(await readViewerState(album)).toMatchObject({
            bg: 'rgba(128,0,64,0.7)',
            controls: '#2A9D8F',
        });
        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        await album.locator(LIGHTBOX_BUTTON).click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        expect(await readViewerState(lightboxAlbum)).toMatchObject({
            bg: 'rgba(128,0,64,0.7)',
            controls: '#00A878',
        });
        const backdropColor = await backdrop(page).evaluate((el) => (el as HTMLElement).style.background);
        expect(backdropColor.replace(/\s+/g, '')).toBe('rgba(0,128,64,0.7)');

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });

    test('slideshow state survives switching between Lightbox and Fullscreen', async ({ page }) => {
        const album = await waitForAlbum(page, 8);

        const lightboxButton = album.locator(LIGHTBOX_BUTTON);
        const fullscreenButton = album.locator(FULLSCREEN_BUTTON);

        await lightboxButton.click({ force: true });
        await expect(backdrop(page)).toBeVisible();
        const lightboxActiveBefore = await backdrop(page).locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
        await expect(async () => {
            const current = await backdrop(page).locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(current).not.toBe(lightboxActiveBefore);
        }).toPass({ timeout: 2_500 });

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();

        await fullscreenButton.click({ force: true });
        await waitForNativeFullscreen(page, true);
        const fullscreenActiveBefore = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
        await expect(async () => {
            const current = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(current).not.toBe(fullscreenActiveBefore);
        }).toPass({ timeout: 10_000 });

        await page.evaluate(() => document.fullscreenElement && document.exitFullscreen());
        await waitForNativeFullscreen(page, false);

        await lightboxButton.evaluate((button: HTMLElement) => button.click());
        await expect(backdrop(page)).toBeVisible();
        const lightboxActiveAfter = await backdrop(page).locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
        await expect(async () => {
            const current = await backdrop(page).locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(current).not.toBe(lightboxActiveAfter);
        }).toPass({ timeout: 2_500 });

        await page.keyboard.press('Escape');
        await expect(backdrop(page)).not.toBeVisible();
    });
});
