import { test, expect, type Page, type Locator } from '@playwright/test';
import { gotoFixture } from './support/navigation';

const FIXTURE_URL = '/?pagename=video-fixture';

async function installPlyrStub(page: Page): Promise<void> {
    await page.route('**/assets/vendor/plyr/plyr.min.js?**', async (route) => {
        await route.fulfill({
            contentType: 'application/javascript',
            body: `
                window.__jzsaVideoEvents = [];
                window.Plyr = class {
                    constructor(media, config) {
                        this.media = media;
                        this.config = config || {};
                        this.options = config || {};
                        this.playing = false;
                        this.currentTime = 0;
                        this.handlers = {};
                        const wrapper = media.closest('.jzsa-video-wrapper');
                        if (wrapper && !wrapper.querySelector('.plyr__control--overlaid')) {
                            const overlay = document.createElement('button');
                            overlay.type = 'button';
                            overlay.className = 'plyr__control plyr__control--overlaid';
                            overlay.setAttribute('aria-label', 'Play');
                            overlay.style.display = 'block';
                            overlay.style.position = 'absolute';
                            overlay.style.left = '50%';
                            overlay.style.top = '50%';
                            overlay.style.width = '44px';
                            overlay.style.height = '44px';
                            wrapper.appendChild(overlay);
                        }
                        if (wrapper && !wrapper.querySelector('.plyr__controls')) {
                            const controls = document.createElement('div');
                            controls.className = 'plyr__controls';
                            controls.style.display = 'block';
                            controls.style.width = '120px';
                            controls.style.height = '24px';
                            wrapper.appendChild(controls);
                        }
                        media.pause = () => {
                            this.playing = false;
                            this.currentTime = 0;
                            window.__jzsaVideoEvents.push({ type: 'native-pause', src: media.getAttribute('src') });
                            this.emit('pause');
                        };
                        media.addEventListener('pause', () => {
                            this.playing = false;
                            this.currentTime = 0;
                            window.__jzsaVideoEvents.push({ type: 'native-pause', src: media.getAttribute('src') });
                            this.emit('pause');
                        });
                    }
                    on(name, callback) {
                        this.handlers[name] = this.handlers[name] || [];
                        this.handlers[name].push(callback);
                    }
                    emit(name) {
                        (this.handlers[name] || []).forEach((callback) => callback());
                    }
                    play() {
                        this.playing = true;
                        this.currentTime = 0.2;
                        try { this.media.currentTime = 0.2; } catch (e) {}
                        window.__jzsaVideoEvents.push({ type: 'play', src: this.media.getAttribute('src') });
                        setTimeout(() => {
                            this.emit('playing');
                            this.emit('timeupdate');
                        }, 0);
                        return Promise.resolve();
                    }
                    pause() {
                        this.playing = false;
                        window.__jzsaVideoEvents.push({ type: 'plyr-pause', src: this.media.getAttribute('src') });
                        this.emit('pause');
                    }
                    stop() {
                        this.playing = false;
                        this.currentTime = 0;
                        try { this.media.currentTime = 0; } catch (e) {}
                        window.__jzsaVideoEvents.push({ type: 'stop', src: this.media.getAttribute('src') });
                        this.emit('pause');
                    }
                    destroy() {
                        window.__jzsaVideoEvents.push({ type: 'destroy', src: this.media.getAttribute('src') });
                    }
                };
            `,
        });
    });
}

async function waitForAlbum(page: Page, index: number): Promise<Locator> {
    const album = page.locator('.jzsa-album.swiper:not(.jzsa-gallery-slideshow):not(.jzsa-gallery-controls)').nth(index);
    await album.scrollIntoViewIfNeeded();
    await expect(album).not.toHaveClass(/jzsa-loader-pending/, { timeout: 20_000 });
    return album;
}

async function playActiveVideo(album: Locator): Promise<void> {
    const activeVideo = album.locator('.swiper-slide-active.jzsa-slide-video');
    await expect(activeVideo.locator('video.jzsa-video-player')).toBeAttached();
    await activeVideo.locator('.plyr__control--overlaid').click({ force: true });
    await expect(album).toHaveClass(/jzsa-video-playing/);
    await expect(activeVideo.locator('.plyr__controls')).toHaveCSS('display', 'block');
}

test.describe('Video - deterministic browser behavior', () => {
    test.beforeEach(async ({ page }) => {
        await installPlyrStub(page);
        await gotoFixture(page, FIXTURE_URL);
    });

    test('mixed album renders video slides, image slides, and Plyr controls', async ({ page }) => {
        const album = await waitForAlbum(page, 0);

        await expect(album.locator('.jzsa-slide-video')).toHaveCount(2);
        await expect(album.locator('.swiper-slide img.jzsa-progressive-image')).toHaveCount(1);

        const activeVideo = album.locator('.swiper-slide-active.jzsa-slide-video');
        const video = activeVideo.locator('video.jzsa-video-player');
        await expect(video).toHaveAttribute('preload', 'none');
        await expect(video).toHaveAttribute('playsinline', '');
        await expect(video).toHaveAttribute('disablepictureinpicture', '');
        await expect(video).toHaveAttribute('src', /jzsa-e2e-video-one=dv$/);
        await expect(activeVideo.locator('.plyr__control--overlaid')).toBeAttached();
        await expect(activeVideo.locator('.plyr__controls')).toBeAttached();
    });

    test('clicking the video overlay enters playing state and reveals controls', async ({ page }) => {
        const album = await waitForAlbum(page, 0);

        await playActiveVideo(album);

        const events = await page.evaluate(() => window.__jzsaVideoEvents);
        expect(events.some((event: { type: string }) => event.type === 'play')).toBe(true);
    });

    test('navigating away from a playing video stops and resets it', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await playActiveVideo(album);

        await album.locator('.swiper-button-next').click({ force: true });

        await expect(album).not.toHaveClass(/jzsa-video-playing/);
        const activeIndex = await album.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
        expect(activeIndex).not.toBe('0');
        const events = await page.evaluate(() => window.__jzsaVideoEvents.map((event: { type: string }) => event.type));
        expect(events).toContain('native-pause');
    });

    test('entering native fullscreen stops inline video playback first', async ({ page }) => {
        const album = await waitForAlbum(page, 0);
        await playActiveVideo(album);

        await album.locator('.swiper-button-fullscreen:not(.jzsa-gallery-thumb-fs-btn)').click({ force: true });

        await expect(album).not.toHaveClass(/jzsa-video-playing/);
        const events = await page.evaluate(() => window.__jzsaVideoEvents.map((event: { type: string }) => event.type));
        expect(events).toContain('stop');

        await page.keyboard.press('Escape');
    });

    test('closing lightbox stops video playback inside the overlay', async ({ page }) => {
        const album = await waitForAlbum(page, 1);
        await album.locator('.swiper-button-lightbox:not(.jzsa-gallery-thumb-fs-btn)').click({ force: true });

        const lightboxAlbum = page.locator('.jzsa-lightbox-backdrop .jzsa-album');
        await expect(lightboxAlbum).toBeVisible();
        await playActiveVideo(lightboxAlbum);

        await page.locator('.jzsa-lightbox-close').click();

        await expect(page.locator('.jzsa-lightbox-backdrop')).not.toBeVisible();
        const events = await page.evaluate(() => window.__jzsaVideoEvents.map((event: { type: string }) => event.type));
        expect(events).toContain('stop');
    });
});
