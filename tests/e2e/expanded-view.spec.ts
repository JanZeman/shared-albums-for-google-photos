import { test, expect } from '@playwright/test';

const PAGE_URL = '/?pagename=expanded-fixture';

test.describe('Expanded view shared settings', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(PAGE_URL);
        await expect(page.locator('.jzsa-album')).toHaveCount(2);
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
