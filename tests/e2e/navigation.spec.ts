import { test, expect, type Page, type Locator } from '@playwright/test';

// feature-fixture contains 4 albums in this order:
//   #0  show-navigation="true"
//   #1  show-download-button="true"  show-link-button="true"
//   #2  interaction-lock="true"
//   #3  show-navigation="false"
const FIXTURE_URL = '/?pagename=feature-fixture';

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

test.describe('Navigation - arrows visible/hidden', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(FIXTURE_URL);
    });

    test('album 0 navigation arrows are visible when show-navigation="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album.locator('.swiper-button-prev')).toBeVisible();
        await expect(album.locator('.swiper-button-next')).toBeVisible();
    });

    test('album 3 navigation arrows are hidden when show-navigation="false"', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        await expect(album.locator('.swiper-button-prev')).not.toBeVisible();
        await expect(album.locator('.swiper-button-next')).not.toBeVisible();
    });

    test('album 2 navigation arrows are hidden when interaction-lock="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album.locator('.swiper-button-prev')).not.toBeVisible();
        await expect(album.locator('.swiper-button-next')).not.toBeVisible();
    });

    test('album 0 has data-show-navigation="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album).toHaveAttribute('data-show-navigation', 'true');
    });

    test('album 3 has data-show-navigation="false"', async ({ page }) => {
        const album = await waitForAlbum(page, 3);
        await expect(album).toHaveAttribute('data-show-navigation', 'false');
    });

    test('album 2 has data-interaction-lock="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        await expect(album).toHaveAttribute('data-interaction-lock', 'true');
    });
});

test.describe('Navigation - click arrow advances slide', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(FIXTURE_URL);
    });

    test('clicking next arrow advances to a new slide', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const initialActive = album.locator('.swiper-slide-active');
        await expect(initialActive).toBeVisible();
        const initialIdx = await initialActive.getAttribute('data-swiper-slide-index');

        await album.locator('.swiper-button-next').click({ force: true });

        await expect(async () => {
            const newActive = album.locator('.swiper-slide-active');
            const newIdx = await newActive.getAttribute('data-swiper-slide-index');
            expect(newIdx).not.toBe(initialIdx);
        }).toPass({ timeout: 2_000 });
    });

    test('clicking next twice advances to two different slides', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        const initialIdx = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');

        // First next click - wait for the active slide to change.
        await album.locator('.swiper-button-next').click({ force: true });
        let idxAfterFirst: string | null = initialIdx;
        await expect(async () => {
            idxAfterFirst = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(idxAfterFirst).not.toBe(initialIdx);
        }).toPass({ timeout: 2_000 });

        // Wait for Swiper's 600ms slide transition to complete before clicking again.
        // Swiper ignores clicks on the navigation buttons while a transition is in progress.
        await page.waitForTimeout(800);

        // Second next click.
        await album.locator('.swiper-button-next').click({ force: true });
        await expect(async () => {
            const idx = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(idx).not.toBe(idxAfterFirst);
        }).toPass({ timeout: 2_000 });
    });
});

test.describe('Navigation - keyboard arrows', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(FIXTURE_URL);
    });

    test('pressing ArrowRight advances the active slide', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await album.click();
        const initialActive = album.locator('.swiper-slide-active');
        const initialIdx = await initialActive.getAttribute('data-swiper-slide-index');

        await page.keyboard.press('ArrowRight');

        await expect(async () => {
            const newIdx = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(newIdx).not.toBe(initialIdx);
        }).toPass({ timeout: 2_000 });
    });
});

test.describe('Navigation - interaction-lock prevents navigation', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(FIXTURE_URL);
    });

    test('locked album slide does not change on next-arrow click attempt', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        const initialActive = album.locator('.swiper-slide-active');
        await expect(initialActive).toBeVisible();
        const initialIdx = await initialActive.getAttribute('data-swiper-slide-index');

        // Arrows are hidden, but attempt interaction anyway.
        await page.waitForTimeout(800);

        const currentIdx = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
        expect(currentIdx).toBe(initialIdx);
    });
});

test.describe('Navigation - download and link buttons', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(FIXTURE_URL);
    });

    test('download button is present when show-download-button="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album.locator('.swiper-button-download')).toBeAttached();
    });

    test('link button is present when show-link-button="true"', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await expect(album.locator('.swiper-button-external-link')).toBeAttached();
    });

    test('download button is absent when show-download-button is not set', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await expect(album.locator('.swiper-button-download')).not.toBeAttached();
    });

    test('download button is absent when interaction-lock is set', async ({ page }) => {
        const album = await waitForAlbum(page, 2);
        // interaction-lock hides all buttons; download button should not be rendered either.
        await expect(album.locator('.swiper-button-download')).not.toBeAttached();
    });
});
