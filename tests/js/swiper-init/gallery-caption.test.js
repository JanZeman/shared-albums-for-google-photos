import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

// gallery-caption wraps each gallery tile in a figure with the caption below,
// so the tile and its overlays stay unchanged.
function setup() {
    const helpers = loadHelpers(
        ['escapeHtml', 'resolveGalleryCaption', 'wrapGalleryTileWithCaption'],
        {
            $: { extend: Object.assign },
            shouldShowPhotoMetaLoading: (format, photo) => /\{description\}/.test(format) && photo.description === undefined,
            resolveInfoPlaceholders: (format, photo) => format.replace('{description}', photo.description || ''),
            buildSinglePhotoItemPlaceholders: () => ({}),
            getContainerTotalCount: () => 3,
        }
    );
    const container = (attrs) => ({ attr: (name) => attrs[name] });
    return { ...helpers, container };
}

describe('gallery-caption', () => {
    test('leaves the tile alone when gallery-caption is not set', () => {
        const { wrapGalleryTileWithCaption, container } = setup();
        const tile = '<div class="jzsa-gallery-item" data-index="0"></div>';

        expect(wrapGalleryTileWithCaption(container({}), tile, { description: 'Lake' }, 0, '')).toBe(tile);
    });

    test('puts the resolved and escaped caption below the tile', () => {
        const { wrapGalleryTileWithCaption, container } = setup();
        const html = wrapGalleryTileWithCaption(
            container({ 'data-gallery-caption': '{description}' }),
            '<div class="jzsa-gallery-item"></div>',
            { description: 'Lake <b>at dusk</b>' },
            2,
            'width:120px;'
        );

        expect(html).toBe(
            '<figure class="jzsa-gallery-cell" data-index="2" style="width:120px;">' +
            '<div class="jzsa-gallery-item"></div>' +
            '<figcaption class="jzsa-gallery-caption">Lake &lt;b&gt;at dusk&lt;/b&gt;</figcaption>' +
            '</figure>'
        );
    });

    test('hides the caption while the description is loading or empty', () => {
        const { wrapGalleryTileWithCaption, resolveGalleryCaption, container } = setup();
        const $album = container({ 'data-gallery-caption': '{description}' });

        expect(resolveGalleryCaption($album, {}, 0)).toBe('');
        expect(wrapGalleryTileWithCaption($album, '<i></i>', { description: '' }, 0, '')).toContain(
            '<figcaption class="jzsa-gallery-caption" hidden></figcaption>'
        );
    });
});
