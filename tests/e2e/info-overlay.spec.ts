import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

// info-fixture contains 4 albums in this order:
//   #0  info-bottom="{item} / {items}"    (renders in .swiper-pagination)
//   #1  info-top="{album-title}"          (renders in .jzsa-info-box.jzsa-info-top)
//   #2  both info-bottom and info-top set
//   #3  lazy metadata placeholders: info-top="{filename}", info-top-secondary="{camera} {description}"
const FIXTURE_URL = '/?pagename=info-fixture';

type PhotoMetaPayload = Record<string, string>;

async function mockPhotoMetaAjax(
    page: Page,
    handler: (payload: PhotoMetaPayload, requestNumber: number) => Promise<{ status?: number; body: unknown }>,
): Promise<PhotoMetaPayload[]> {
    const payloads: PhotoMetaPayload[] = [];

    await page.route('**/wp-admin/admin-ajax.php', async (route, request) => {
        const form = new URLSearchParams(request.postData() ?? '');
        const action = form.get('action');

        if (action !== 'jzsa_fetch_photo_meta') {
            await route.fallback();
            return;
        }

        const payload = Object.fromEntries(form.entries());
        payloads.push(payload);

        const response = await handler(payload, payloads.length);
        await route.fulfill({
            status: response.status ?? 200,
            contentType: 'application/json',
            body: JSON.stringify(response.body),
        });
    });

    return payloads;
}

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

test.describe('Info overlay - lazy photo metadata', () => {
    test('fetches missing metadata and refreshes visible info boxes', async ({ page }) => {
        const payloads = await mockPhotoMetaAjax(page, async () => ({
            body: {
                success: true,
                data: {
                    filename: 'lazy-fixture-photo.jpg',
                    camera_make: 'Nikon',
                    camera_model: 'Z6',
                    description: 'Lazy caption from metadata',
                },
            },
        }));

        await gotoFixture(page, FIXTURE_URL);
        const album = await waitForAlbum(page, 3);

        await expect.poll(() => payloads.length, { timeout: 10_000 }).toBeGreaterThan(0);

        const firstPayload = payloads[0];
        expect(firstPayload.action).toBe('jzsa_fetch_photo_meta');
        expect(firstPayload.nonce).toBeTruthy();
        expect(firstPayload.photo_url).toMatch(/^https:\/\/photos\.google\.com\/share\/.+\/photo\/.+\?key=.+/);
        expect(firstPayload.media_url).toContain('googleusercontent.com');
        expect(firstPayload.media_type).toBe('photo');
        expect(firstPayload.need_description).toBe('true');
        expect(firstPayload.need_exif).toBe('true');
        // The fixture's album data already includes filenames for early slides, so
        // the lazy request should avoid asking for filename again for this photo.
        expect(firstPayload.need_filename).toBe('false');

        await expect(album.locator('.jzsa-info-box.jzsa-info-top')).toContainText('lazy-fixture-photo.jpg', {
            timeout: 10_000,
        });
        const secondary = album.locator('.jzsa-info-box.jzsa-info-top-secondary');
        await expect(secondary).toContainText('Nikon Z6', { timeout: 10_000 });
        await expect(secondary).toContainText('Lazy caption from metadata');

        const photosJson = await album.getAttribute('data-all-photos');
        const photos = JSON.parse(photosJson ?? '[]') as Array<Record<string, unknown>>;
        expect(photos.some((photo) => photo.filename === 'lazy-fixture-photo.jpg')).toBe(true);
        expect(photos.some((photo) => photo.camera === 'Nikon Z6')).toBe(true);
        expect(photos.some((photo) => photo.description === 'Lazy caption from metadata')).toBe(true);
    });

    test('metadata request failure leaves slider navigation usable', async ({ page }) => {
        const payloads = await mockPhotoMetaAjax(page, async () => ({
            status: 500,
            body: {
                success: false,
                data: {
                    message: 'metadata failed',
                },
            },
        }));

        await gotoFixture(page, FIXTURE_URL);
        const album = await waitForAlbum(page, 3);

        await expect.poll(() => payloads.length, { timeout: 10_000 }).toBeGreaterThan(0);
        await expect(album.locator('.jzsa-error')).not.toBeAttached();

        const initialIndex = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
        await album.locator('.swiper-button-next').click({ force: true });

        await expect(async () => {
            const nextIndex = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
            expect(nextIndex).not.toBe(initialIndex);
        }).toPass({ timeout: 3_000 });
    });
});
