import { test, expect, type Page, type Locator } from '@playwright/test';

// gallery-fixture contains 4 albums in this order:
//   #0  mode="gallery"  gallery-columns-desktop="3"  (grid, default layout)
//   #1  mode="gallery"  gallery-layout="justified"
//   #2  mode="gallery"  gallery-scrollable="true"
//   #3  mode="gallery"  gallery-rows="2"
// All four have fullscreen-toggle="button-only".
const FIXTURE_URL = '/?pagename=gallery-fixture';

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

test.describe('Gallery - data attributes', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(FIXTURE_URL);
    });

    test('album 0 has data-mode="gallery"', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-mode', 'gallery');
    });

    test('album 0 has data-gallery-layout="grid" (default)', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-gallery-layout', 'grid');
    });

    test('album 0 has data-gallery-columns="3" (desktop)', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-gallery-columns', '3');
    });

    test('album 0 has data-gallery-scrollable="false" (default)', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-gallery-scrollable', 'false');
    });

    test('album 1 has data-gallery-layout="justified"', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album).toHaveAttribute('data-gallery-layout', 'justified');
    });

    test('album 2 has data-gallery-scrollable="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album).toHaveAttribute('data-gallery-scrollable', 'true');
    });

    test('album 3 has data-gallery-rows="2"', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        await expect(album).toHaveAttribute('data-gallery-rows', '2');
    });

    test('album 3 has data-gallery-columns="3" (desktop)', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        await expect(album).toHaveAttribute('data-gallery-columns', '3');
    });
});

test.describe('Gallery - items and structure', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(FIXTURE_URL);
    });

    test('album 0 renders gallery items', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const items = album.locator('.jzsa-gallery-item');
        await expect(items.first()).toBeVisible({ timeout: 10_000 });
    });

    test('album 0 gallery items contain an image', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const firstItem = album.locator('.jzsa-gallery-item').first();
        await expect(firstItem).toBeVisible({ timeout: 10_000 });
        await expect(firstItem.locator('img')).toBeAttached();
    });

    test('album 1 (justified) renders gallery items', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album.locator('.jzsa-gallery-item').first()).toBeVisible({ timeout: 10_000 });
    });

    test('album 2 (scrollable) renders gallery items', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album.locator('.jzsa-gallery-item').first()).toBeVisible({ timeout: 10_000 });
    });
});

test.describe('Gallery - hover button', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(FIXTURE_URL);
    });

    test('hovering a gallery item shows the fullscreen button', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const item = album.locator('.jzsa-gallery-item').first();
        await item.waitFor({ state: 'visible', timeout: 10_000 });
        await item.hover();
        const btn = item.locator('.jzsa-gallery-thumb-fs-btn');
        await expect(btn).toBeAttached();
        // After hover the button opacity transitions from 0 to 1.
        await expect(btn).not.toHaveCSS('opacity', '0');
    });

    test('hovering a second item hides the first item button', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const items = album.locator('.jzsa-gallery-item');
        const first = items.nth(0);
        const second = items.nth(1);
        await first.waitFor({ state: 'visible', timeout: 10_000 });
        await second.waitFor({ state: 'visible', timeout: 10_000 });

        await first.hover();
        await second.scrollIntoViewIfNeeded();
        await second.hover();

        // First item button must be hidden after focus moves away.
        await expect(first.locator('.jzsa-gallery-thumb-fs-btn')).toHaveCSS('opacity', '0');
    });
});
