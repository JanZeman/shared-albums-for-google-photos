import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

// mosaic-fixture contains 4 albums in this order:
//   #0  mosaic="true"  (no explicit position, defaults to right)
//   #1  mosaic="true"  mosaic-position="left"
//   #2  mosaic="true"  mosaic-position="top"
//   #3  mosaic="true"  mosaic-position="bottom"
const FIXTURE_URL = '/?pagename=mosaic-fixture';

// Wait for the nth main album slider to finish loading. Mosaic adds a
// .jzsa-gallery-wrapper parent, but the .jzsa-album element inside is still
// the nth slider in page order.
async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

// Return the wrapper div for the nth mosaic album.
// The wrapper has class jzsa-gallery-wrapper and encloses both the main
// slider (.jzsa-album) and the mosaic strip (.jzsa-mosaic.swiper).
async function waitForWrapper(page: Page, index: number): Promise<Locator> {
    await waitForAlbum(page, index);
    return page.locator('.jzsa-gallery-wrapper').nth(index);
}

test.describe('Mosaic - wrapper position classes', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('album 0 wrapper has jzsa-mosaic-right class (default)', async ({ page }) => {
        const wrapper = await waitForWrapper(page, 0);
        await expect(wrapper).toHaveClass(/jzsa-mosaic-right/);
    });

    test('album 1 wrapper has jzsa-mosaic-left class', async ({ page }) => {
        const wrapper = await waitForWrapper(page, 1);
        await expect(wrapper).toHaveClass(/jzsa-mosaic-left/);
    });

    test('album 2 wrapper has jzsa-mosaic-top class', async ({ page }) => {
        const wrapper = await waitForWrapper(page, 2);
        await expect(wrapper).toHaveClass(/jzsa-mosaic-top/);
    });

    test('album 3 wrapper has jzsa-mosaic-bottom class', async ({ page }) => {
        const wrapper = await waitForWrapper(page, 3);
        await expect(wrapper).toHaveClass(/jzsa-mosaic-bottom/);
    });
});

test.describe('Mosaic - strip presence', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('album 0 has a mosaic strip (.jzsa-mosaic.swiper)', async ({ page }) => {
        const wrapper = await waitForWrapper(page, 0);
        await expect(wrapper.locator('.jzsa-mosaic.swiper')).toBeAttached();
    });

    test('album 0 mosaic strip contains thumbnail elements', async ({ page }) => {
        const wrapper = await waitForWrapper(page, 0);
        const strip = wrapper.locator('.jzsa-mosaic.swiper');
        await expect(strip.locator('.jzsa-mosaic-thumb-inner').first()).toBeAttached({ timeout: 10_000 });
    });

    test('all 4 wrappers have a mosaic strip', async ({ page }) => {
        for (let i = 0; i < 4; i++) {
            const wrapper = await waitForWrapper(page, i);
            await expect(wrapper.locator('.jzsa-mosaic.swiper')).toBeAttached();
        }
    });
});

test.describe('Mosaic - data attributes', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('album 0 has data-mosaic="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-mosaic', 'true');
    });

    test('album 1 has data-mosaic-position="left"', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album).toHaveAttribute('data-mosaic-position', 'left');
    });

    test('album 2 has data-mosaic-position="top"', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album).toHaveAttribute('data-mosaic-position', 'top');
    });

    test('album 3 has data-mosaic-position="bottom"', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        await expect(album).toHaveAttribute('data-mosaic-position', 'bottom');
    });
});

test.describe('Mosaic - thumbnail click advances main slider', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('clicking the 2nd mosaic thumbnail navigates main slider to index 1', async ({ page }) => {
        const wrapper = await waitForWrapper(page, 0);
        const album = wrapper.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)');
        const strip = wrapper.locator('.jzsa-mosaic.swiper');

        // Wait for thumbnails to be populated by JS.
        const thumbs = strip.locator('.jzsa-mosaic-thumb-inner');
        await expect(thumbs.nth(1)).toBeAttached({ timeout: 10_000 });

        // Click the second thumbnail (real index 1).
        await thumbs.nth(1).click();

        // The main slider should show the slide with data-swiper-slide-index="1".
        await expect(async () => {
            const activeIdx = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(activeIdx).toBe('1');
        }).toPass({ timeout: 2_000 });
    });

    test('clicking the 1st mosaic thumbnail navigates main slider to index 0', async ({ page }) => {
        const wrapper = await waitForWrapper(page, 0);
        const album = wrapper.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)');
        const strip = wrapper.locator('.jzsa-mosaic.swiper');

        const thumbs = strip.locator('.jzsa-mosaic-thumb-inner');
        await expect(thumbs.first()).toBeAttached({ timeout: 10_000 });

        // Navigate away first, then back to index 0.
        await thumbs.nth(1).click();
        await thumbs.first().click();

        await expect(async () => {
            const activeIdx = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(activeIdx).toBe('0');
        }).toPass({ timeout: 2_000 });
    });
});
