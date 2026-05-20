import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

const FEATURE_FIXTURE_URL = '/?pagename=feature-fixture';
const GALLERY_FIXTURE_URL = '/?pagename=gallery-fixture';

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

test.describe('Shortcode integration - real WordPress rendering', () => {
    test('published page renders shortcode output instead of raw shortcode text', async ({ page }) => {
        await gotoFixture(page, FEATURE_FIXTURE_URL);

        await expect(page.locator('.jzsa-album').first()).toBeAttached({ timeout: 20_000 });
        await expect(page.locator('body')).not.toContainText('[jzsa-album');
    });

    test('WordPress enqueues plugin frontend styles and scripts on shortcode pages', async ({ page }) => {
        await gotoFixture(page, FEATURE_FIXTURE_URL);

        await expect(page.locator('link#jzsa-style-css')).toHaveAttribute('href', /assets\/css\/swiper-style\.css/);
        await expect(page.locator('script#jzsa-init-js')).toHaveAttribute('src', /assets\/js\/swiper-init\.js/);
        await expect(page.locator('script#swiper-js-js')).toHaveAttribute('src', /assets\/vendor\/swiper\/swiper-bundle\.min\.js/);
        await expect(page.locator('script#plyr-js-js')).toHaveAttribute('src', /assets\/vendor\/plyr\/plyr\.min\.js/);
    });

    test('localized frontend config is present with AJAX URL and required nonces', async ({ page }) => {
        await gotoFixture(page, FEATURE_FIXTURE_URL);

        const config = await page.evaluate(() => {
            const value = (window as typeof window & { jzsaAjax?: Record<string, unknown> }).jzsaAjax;
            return {
                ajaxUrl: value?.ajaxUrl,
                downloadNonce: value?.downloadNonce,
                refreshNonce: value?.refreshNonce,
                chunkNonce: value?.chunkNonce,
                photoMetaNonce: value?.photoMetaNonce,
                openLightbox: (value?.i18n as Record<string, unknown> | undefined)?.openLightbox,
            };
        });

        expect(config.ajaxUrl).toContain('/wp-admin/admin-ajax.php');
        expect(config.downloadNonce).toBeTruthy();
        expect(config.refreshNonce).toBeTruthy();
        expect(config.chunkNonce).toBeTruthy();
        expect(config.photoMetaNonce).toBeTruthy();
        expect(config.openLightbox).toBe('Open in lightbox');
    });

    test('shortcode attributes survive WordPress parsing into renderer data attributes', async ({ page }) => {
        await gotoFixture(page, FEATURE_FIXTURE_URL);

        const album = await waitForAlbum(page, 1);
        await expect(album).toHaveAttribute('data-mode', 'slider');
        await expect(album).toHaveAttribute('data-show-download-button', 'true');
        await expect(album).toHaveAttribute('data-show-link-button', 'true');
        await expect(album.locator('.swiper-button-download')).toBeAttached();
        await expect(album.locator('.swiper-button-external-link')).toBeAttached();
    });

    test('gallery shortcode renders through WordPress into gallery-mode markup', async ({ page }) => {
        await gotoFixture(page, GALLERY_FIXTURE_URL);

        const gallery = await waitForAlbum(page, 0);
        await expect(gallery).toHaveClass(/jzsa-gallery-album/);
        await expect(gallery).toHaveAttribute('data-mode', 'gallery');
        await expect(gallery).toHaveAttribute('data-gallery-columns', '3');
        await expect(gallery.locator('.jzsa-gallery-item').first()).toBeVisible({ timeout: 10_000 });
    });
});
