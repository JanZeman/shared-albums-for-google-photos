import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

// slideshow-fixture contains 3 albums in this order:
//   #0  slideshow="auto"     slideshow-delay="1"  (auto-advances every 1 second)
//   #1  slideshow="manual"                        (no auto-advance)
//   #2  slideshow="disabled"                      (no slideshow at all)
const FIXTURE_URL = '/?pagename=slideshow-fixture';

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

test.describe('Slideshow - data attributes', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('album 0 has data-slideshow="auto"', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-slideshow', 'auto');
    });

    test('album 0 has data-slideshow-delay="1"', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-slideshow-delay', '1');
    });

    test('album 1 has data-slideshow="manual"', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album).toHaveAttribute('data-slideshow', 'manual');
    });

    test('album 2 has data-slideshow="disabled"', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album).toHaveAttribute('data-slideshow', 'disabled');
    });
});

test.describe('Slideshow - play/pause button', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('play/pause button is present on auto-slideshow album', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album.locator('.swiper-button-play-pause')).toBeAttached();
    });

    test('play/pause button is present on manual-slideshow album', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album.locator('.swiper-button-play-pause')).toBeAttached();
    });

    test('auto slideshow starts with playing class on the play/pause button', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const btn = album.locator('.swiper-button-play-pause');
        // After load the auto slideshow starts immediately; button should have .playing.
        await expect(btn).toHaveClass(/playing/, { timeout: 3_000 });
    });

    test('clicking play/pause on auto album pauses the slideshow', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const btn = album.locator('.swiper-button-play-pause');
        await expect(btn).toHaveClass(/playing/, { timeout: 3_000 });
        await btn.click({ force: true });
        await expect(btn).not.toHaveClass(/playing/);
    });

    test('clicking play/pause again on paused album resumes it', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const btn = album.locator('.swiper-button-play-pause');
        await expect(btn).toHaveClass(/playing/, { timeout: 3_000 });
        await btn.click({ force: true });
        await expect(btn).not.toHaveClass(/playing/);
        await btn.click({ force: true });
        await expect(btn).toHaveClass(/playing/);
    });
});

test.describe('Slideshow - auto-advance', () => {
    test.beforeEach(async ({ page }) => {
        await gotoFixture(page, FIXTURE_URL);
    });

    test('auto album advances to a new slide within 2 seconds @cross-browser', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        // Capture the data-swiper-slide-index of the initially active slide.
        const initialActive = album.locator('.swiper-slide-active');
        await expect(initialActive).toBeVisible();
        const initialIdx = await initialActive.getAttribute('data-swiper-slide-index');

        // Wait up to 2500ms for the active slide to change (delay=1s).
        await expect(async () => {
            const newActive = album.locator('.swiper-slide-active');
            const newIdx = await newActive.getAttribute('data-swiper-slide-index');
            expect(newIdx).not.toBe(initialIdx);
        }).toPass({ timeout: 2_500 });
    });

    test('manual album does NOT auto-advance within 2 seconds', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        const initialActive = album.locator('.swiper-slide-active');
        await expect(initialActive).toBeVisible();
        const initialIdx = await initialActive.getAttribute('data-swiper-slide-index');

        await page.waitForTimeout(2_000);

        const stillActive = album.locator('.swiper-slide-active');
        const currentIdx = await stillActive.getAttribute('data-swiper-slide-index');
        expect(currentIdx).toBe(initialIdx);
    });
});
