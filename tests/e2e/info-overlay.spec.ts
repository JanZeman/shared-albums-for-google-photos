import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

// info-fixture contains 3 albums in this order:
//   #0  info-bottom="{item} / {items}"    (renders in .swiper-pagination)
//   #1  info-top="{album-title}"          (renders in .jzsa-info-box.jzsa-info-top)
//   #2  both info-bottom and info-top set
const FIXTURE_URL = '/?pagename=info-fixture';

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

test.describe('Info overlay - info-bottom placeholder substitution', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('album 0 pagination shows substituted info-bottom text', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        // info-bottom="{item} / {items}" renders as "1 / N" inside .swiper-pagination.
        const pagination = album.locator('.swiper-pagination');
        await expect(pagination).toBeVisible({ timeout: 5_000 });
        // Text must match the "N / M" pattern.
        await expect(pagination).toHaveText(/\d+\s*\/\s*\d+/);
    });

    test('album 0 pagination item counter starts at 1', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const pagination = album.locator('.swiper-pagination');
        await expect(pagination).toBeVisible({ timeout: 5_000 });
        const text = await pagination.textContent();
        // First number must be 1 (the active slide is the first one).
        expect(text?.trim()).toMatch(/^1\s*\//);
    });

    test('album 1 shows pagination because slider mode has a default info-bottom', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        // Slider mode applies show-counter=true by default, so pagination shows even
        // without an explicit info-bottom shortcode attribute.
        const pagination = album.locator('.swiper-pagination');
        await expect(pagination).toBeVisible({ timeout: 5_000 });
        await expect(pagination).toHaveText(/\d+\s*\/\s*\d+/);
    });

    test('album 2 pagination shows only the item number (info-bottom="{item}")', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        // album 2 has info-bottom="{item}" which overrides the default.
        const pagination = album.locator('.swiper-pagination');
        await expect(pagination).toBeVisible({ timeout: 5_000 });
        // Just a number, not "N / M".
        await expect(pagination).toHaveText(/^\d+$/);
    });
});

test.describe('Info overlay - info-top placeholder substitution', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('album 1 has an info-top box', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        const box = album.locator('.jzsa-info-box.jzsa-info-top');
        await expect(box).toBeAttached({ timeout: 5_000 });
    });

    test('album 1 info-top box contains the album title (non-empty)', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        const box = album.locator('.jzsa-info-box.jzsa-info-top');
        await expect(box).toBeAttached({ timeout: 5_000 });
        // {album-title} resolves to the title from the Google Photos API.
        const text = await box.textContent();
        expect(text?.trim().length).toBeGreaterThan(0);
    });

    test('album 0 does not have an info-top box (info-top not set)', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album.locator('.jzsa-info-box.jzsa-info-top')).not.toBeAttached();
    });

    test('album 2 has an info-top box (both info-bottom and info-top set)', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        const box = album.locator('.jzsa-info-box.jzsa-info-top');
        await expect(box).toBeAttached({ timeout: 5_000 });
        const text = await box.textContent();
        expect(text?.trim().length).toBeGreaterThan(0);
    });
});

test.describe('Info overlay - data attributes', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('album 0 has data-info-bottom attribute', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const val = await album.getAttribute('data-info-bottom');
        expect(val).not.toBeNull();
        expect(val?.trim().length).toBeGreaterThan(0);
    });

    test('album 1 has data-info-top attribute', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        const val = await album.getAttribute('data-info-top');
        expect(val).not.toBeNull();
        expect(val?.trim().length).toBeGreaterThan(0);
    });

    test('album 0 has data-has-active-bottom-center="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-has-active-bottom-center', 'true');
    });

    test('album 1 has data-has-active-bottom-center="true" (legacy default info-bottom)', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        // Slider mode defaults show-counter=true so info-bottom is always set for slider albums.
        await expect(album).toHaveAttribute('data-has-active-bottom-center', 'true');
    });
});
