import { test, expect, type Page } from '@playwright/test';
import { gotoFixture } from './support/navigation';

const FIXTURE_URL = '/?pagename=feature-fixture';
const TOTAL_PHOTOS = 70;

declare global {
    interface Window {
        SharedGooglePhotos?: {
            reinitialize: () => void;
        };
    }
}

interface ChunkRequest {
    offset: number;
    count: number;
}

function parseFormPostData(postData: string | null): URLSearchParams {
    return new URLSearchParams(postData ?? '');
}

function buildPhoto(index: number) {
    const id = `JZSA_PROGRESSIVE_${String(index + 1).padStart(2, '0')}`;
    return {
        full: `https://lh3.googleusercontent.com/${id}=w1920-h1440-no`,
        preview: `https://lh3.googleusercontent.com/${id}=w800-h600-no`,
        thumb: `https://lh3.googleusercontent.com/${id}=w400-h400-c`,
        id,
        width: 1200,
        height: 800,
        filename: `progressive-${String(index + 1).padStart(2, '0')}.jpg`,
        globalIndex: index,
    };
}

async function mockProgressiveAjax(page: Page): Promise<ChunkRequest[]> {
    const requests: ChunkRequest[] = [];

    await page.route('https://lh3.googleusercontent.com/**', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'image/gif',
            body: Buffer.from(
                'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',
                'base64',
            ),
        });
    });

    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
        const payload = parseFormPostData(route.request().postData());
        if (payload.get('action') !== 'jzsa_fetch_album_chunk') {
            await route.fallback();
            return;
        }

        const offset = Math.max(0, Number.parseInt(payload.get('offset') ?? '0', 10) || 0);
        const count = Math.max(1, Number.parseInt(payload.get('count') ?? '24', 10) || 24);
        requests.push({ offset, count });

        const photos = Array.from(
            { length: Math.max(0, Math.min(count, TOTAL_PHOTOS - offset)) },
            (_, i) => buildPhoto(offset + i),
        );

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                success: true,
                data: {
                    photos,
                    total_count: TOTAL_PHOTOS,
                    has_more: offset + count < TOTAL_PHOTOS,
                },
            }),
        });
    });

    return requests;
}

async function injectProgressiveAlbum(page: Page): Promise<void> {
    await page.evaluate((totalPhotos) => {
        const album = document.createElement('div');
        album.id = 'jzsa-progressive-e2e';
        album.className = 'jzsa-album swiper jzsa-loader-pending';
        album.setAttribute('data-mode', 'slider');
        album.setAttribute('data-all-photos', '[]');
        album.setAttribute('data-total-count', String(totalPhotos));
        album.setAttribute('data-progressive-loading', 'true');
        album.setAttribute('data-progressive-initial-chunk-size', '24');
        album.setAttribute('data-progressive-chunk-size', '24');
        album.setAttribute('data-progressive-limit', String(totalPhotos));
        album.setAttribute('data-progressive-show-videos', 'false');
        album.setAttribute('data-progressive-source-width', '800');
        album.setAttribute('data-progressive-source-height', '600');
        album.setAttribute('data-progressive-fullscreen-source-width', '1920');
        album.setAttribute('data-progressive-fullscreen-source-height', '1440');
        album.setAttribute('data-album-url', 'https://photos.google.com/share/JZSA_PROGRESSIVE?key=e2e');
        album.setAttribute('data-start-at', '36');
        album.setAttribute('data-slideshow', 'disabled');
        album.setAttribute('data-fullscreen-slideshow', 'disabled');
        album.setAttribute('data-slideshow-delay', '5');
        album.setAttribute('data-fullscreen-slideshow-delay', '5');
        album.setAttribute('data-slideshow-autoresume', '30');
        album.setAttribute('data-fullscreen-slideshow-autoresume', '30');
        album.setAttribute('data-show-navigation', 'true');
        album.setAttribute('data-fullscreen-show-navigation', 'true');
        album.setAttribute('data-show-link-button', 'false');
        album.setAttribute('data-show-download-button', 'false');
        album.setAttribute('data-interaction-lock', 'false');
        album.setAttribute('data-fullscreen-toggle', 'disabled');
        album.setAttribute('data-lightbox-toggle', 'disabled');
        album.setAttribute('data-mosaic', 'false');
        album.setAttribute('data-fullscreen-mosaic', 'false');
        album.setAttribute('data-mosaic-position', 'bottom');
        album.setAttribute('data-mosaic-count', '0');
        album.setAttribute('data-mosaic-gap', '8');
        album.setAttribute('data-mosaic-opacity', '0.3');
        album.setAttribute('data-fullscreen-mosaic-position', 'bottom');
        album.setAttribute('data-fullscreen-mosaic-layout', 'outer');
        album.setAttribute('data-fullscreen-mosaic-count', '0');
        album.setAttribute('data-fullscreen-mosaic-gap', '8');
        album.setAttribute('data-fullscreen-mosaic-opacity', '0.3');
        album.setAttribute('data-image-fit', 'cover');
        album.setAttribute('data-fullscreen-image-fit', 'contain');
        album.setAttribute('data-background-color', 'transparent');
        album.setAttribute('data-controls-color', '#ffffff');
        album.setAttribute('data-fullscreen-controls-color', '#ffffff');
        album.setAttribute('data-video-controls-color', '#00b2ff');
        album.setAttribute('data-fullscreen-video-controls-color', '#00b2ff');
        album.setAttribute('data-video-controls-autohide', 'false');
        album.setAttribute('data-fullscreen-video-controls-autohide', 'false');
        album.setAttribute('data-info-halo-effect', 'true');
        album.setAttribute('data-has-active-bottom-center', 'true');
        album.setAttribute('data-info-bottom', '{item} / {items}');
        album.setAttribute('data-fullscreen-info-bottom', '{item} / {items}');
        album.setAttribute('data-info-font-size', '12');
        album.setAttribute('data-fullscreen-info-font-size', '12');
        album.style.width = '400px';
        album.style.maxWidth = '100%';
        album.style.height = '300px';
        album.innerHTML = [
            '<div class="swiper-wrapper"></div>',
            '<div class="swiper-button-prev"></div>',
            '<div class="swiper-button-next"></div>',
            '<div class="swiper-pagination"></div>',
            '<button class="swiper-button-play-pause" title="Play/Pause (Space)"></button>',
            '<div class="swiper-slideshow-progress"><div class="swiper-slideshow-progress-bar"></div></div>',
        ].join('');

        document.body.appendChild(album);
        window.SharedGooglePhotos?.reinitialize();
    }, TOTAL_PHOTOS);
}

test.describe('Progressive slider loading', () => {
    test('bootstraps a centered chunk and merges before/after chunks in browser', async ({ page }) => {
        const requests = await mockProgressiveAjax(page);
        await gotoFixture(page, FIXTURE_URL);
        await injectProgressiveAlbum(page);

        const album = page.locator('#jzsa-progressive-e2e');
        await album.scrollIntoViewIfNeeded();
        await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
        await expect(album.locator('.swiper-slide')).toHaveCount(TOTAL_PHOTOS, { timeout: 20_000 });
        await expect(album.locator('.swiper-slide[data-jzsa-global-index="0"]')).toHaveCount(1);
        await expect(album.locator('.swiper-slide[data-jzsa-global-index="35"]')).toHaveCount(1);
        await expect(album.locator('.swiper-slide[data-jzsa-global-index="69"]')).toHaveCount(1);

        await expect(async () => {
            expect(requests).toEqual(
                expect.arrayContaining([
                    { offset: 23, count: 24 },
                    { offset: 0, count: 23 },
                    { offset: 47, count: 23 },
                ]),
            );
        }).toPass({ timeout: 5_000 });

        const loadedPhotos = await album.evaluate((el) => {
            const raw = el.getAttribute('data-all-photos') ?? '[]';
            return JSON.parse(raw).map((photo: { globalIndex: number }) => photo.globalIndex);
        });
        expect(loadedPhotos).toEqual(Array.from({ length: TOTAL_PHOTOS }, (_, index) => index));

        await album.locator('.swiper-button-next').click({ force: true });
        await expect(async () => {
            const activeIndex = await album
                .locator('.swiper-slide-active')
                .getAttribute('data-jzsa-global-index');
            expect(activeIndex).not.toBe('35');
        }).toPass({ timeout: 2_000 });
    });
});
