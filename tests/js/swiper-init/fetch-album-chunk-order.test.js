import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

// The server sorts every chunk again, so each chunk request must carry the
// album's order (shuffle, oldest, newest) or later chunks would come back in
// album order.
function setup() {
    const requests = [];
    const $ = {
        ajax(options) {
            requests.push(options.data);
            return { then: () => ({}) };
        },
        Deferred() {
            return { reject: () => ({ promise: () => ({}) }) };
        },
    };
    const { fetchAlbumChunk } = loadHelpers(['fetchAlbumChunk'], {
        $,
        jzsaAjax: { ajaxUrl: '/wp-admin/admin-ajax.php', chunkNonce: 'nonce' },
        getProgressiveSliderSettings: () => ({
            limit: 300,
            showVideos: true,
            sourceWidth: 800,
            sourceHeight: 600,
            fullscreenSourceWidth: 1920,
            fullscreenSourceHeight: 1440,
        }),
    });
    const container = (attrs) => ({ attr: (name) => attrs[name] });
    return { fetchAlbumChunk, requests, container };
}

describe('fetchAlbumChunk order', () => {
    test.each(['oldest', 'newest', 'shuffle'])('sends order="%s" with every chunk', (order) => {
        const { fetchAlbumChunk, requests, container } = setup();
        const $album = container({ 'data-album-url': 'https://photos.icloud.com/shared/album/X', 'data-order': order });

        fetchAlbumChunk($album, 24, 24);
        fetchAlbumChunk($album, 48, 24);

        expect(requests.map((data) => data.order)).toEqual([order, order]);
        expect(requests.map((data) => data.offset)).toEqual([24, 48]);
    });

    test('sends an empty order for the album order', () => {
        const { fetchAlbumChunk, requests, container } = setup();

        fetchAlbumChunk(container({ 'data-album-url': 'https://photos.google.com/share/X' }), 0, 24);

        expect(requests[0].order).toBe('');
    });
});
