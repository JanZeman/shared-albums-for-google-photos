import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

const HOME = 'https://example.com/';
const ALBUM = 'https://photos.icloud.com/shared/album/ABC';
const stable = (id, r) => HOME + '?jzsa_media=1&album=' + encodeURIComponent(ALBUM) + '&id=' + id + '&r=' + r;
const DIRECT = 'https://cvws.icloud-content.com/B/abc/x.jpg?o=1&e=1793900000';

// Direct media URLs replace the stable redirect URLs of the first images shown,
// only while the album's direct URLs are known to be valid.
function setup(entry) {
    const byAlbum = entry ? { [ALBUM]: entry } : {};
    const stableByDirect = {};
    const helpers = loadHelpers(
        ['parseStableMediaUrl', 'directMediaUrl', 'stableMediaUrl', 'getDirectMediaRequest'],
        { _directMediaByAlbum: byAlbum, _stableByDirectUrl: stableByDirect }
    );
    const container = (photos) => ({ attr: () => JSON.stringify(photos) });
    return { ...helpers, container, stableByDirect };
}

describe('direct media URLs', () => {
    test('uses the direct URL while it is valid and remembers the stable one', () => {
        const { directMediaUrl, stableMediaUrl } = setup({ urls: { P1: { JPEGMed: DIRECT } }, validUntil: Date.now() + 60000 });
        const url = stable('P1', 'JPEGMed');

        expect(directMediaUrl(url)).toBe(DIRECT);
        expect(stableMediaUrl(DIRECT)).toBe(url);
    });

    test('keeps the stable URL once the direct URLs have expired', () => {
        const { directMediaUrl } = setup({ urls: { P1: { JPEGMed: DIRECT } }, validUntil: Date.now() - 1 });

        expect(directMediaUrl(stable('P1', 'JPEGMed'))).toBe(stable('P1', 'JPEGMed'));
    });

    test('keeps the stable URL for unknown items, renditions or albums', () => {
        const { directMediaUrl } = setup({ urls: { P1: { JPEGMed: DIRECT } }, validUntil: Date.now() + 60000 });

        expect(directMediaUrl(stable('P2', 'JPEGMed'))).toBe(stable('P2', 'JPEGMed'));
        expect(directMediaUrl(stable('P1', 'Original'))).toBe(stable('P1', 'Original'));
        expect(setup().directMediaUrl(stable('P1', 'JPEGMed'))).toBe(stable('P1', 'JPEGMed'));
    });

    test('leaves other URLs alone', () => {
        const { directMediaUrl, stableMediaUrl } = setup({ urls: {}, validUntil: Date.now() + 60000 });
        const google = 'https://lh3.googleusercontent.com/abc=w800-h600';

        expect(directMediaUrl(google)).toBe(google);
        expect(stableMediaUrl(google)).toBe(google);
        expect(directMediaUrl('')).toBe('');
    });

    test('asks only for the renditions of photos shown first, never for videos', () => {
        const { getDirectMediaRequest, container } = setup();
        const request = getDirectMediaRequest(container([
            { type: 'image', preview: stable('P1', 'JPEGMed'), full: stable('P1', 'Original'), thumb: stable('P1', 'JPEGThumb') },
            { type: 'image', full: stable('P2', 'Original') },
            { type: 'video', preview: stable('V1', 'JPEGMed'), video: stable('V1', 'VidMed') },
        ]));

        expect(request).toEqual({ album: ALBUM, renditions: ['JPEGMed', 'JPEGThumb', 'Original'] });
    });

    test('needs no request for albums without stable media URLs', () => {
        const { getDirectMediaRequest, container } = setup();

        expect(getDirectMediaRequest(container([{ preview: 'https://lh3.googleusercontent.com/a=w800', full: 'https://lh3.googleusercontent.com/a=w1920' }]))).toBeNull();
        expect(getDirectMediaRequest({ attr: () => 'not json' })).toBeNull();
    });
});
