import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

// jzsaI18n / jzsaFormatI18n read from a global `jzsaAjax.i18n` map.
// Inject our own stub so we can assert behaviour deterministically.
function load(i18n) {
    return loadHelpers(['jzsaI18n', 'jzsaFormatI18n'], {
        jzsaAjax: i18n === undefined ? undefined : { i18n },
    });
}

describe('jzsaI18n', () => {
    test('returns the matching string from the i18n map', () => {
        const { jzsaI18n } = load({ openLightbox: 'Open in lightbox' });
        expect(jzsaI18n('openLightbox')).toBe('Open in lightbox');
    });

    test('returns empty string for missing key', () => {
        const { jzsaI18n } = load({ otherKey: 'x' });
        expect(jzsaI18n('openLightbox')).toBe('');
    });

    test('returns empty string when i18n map is missing', () => {
        const { jzsaI18n } = load({});
        // jzsaAjax exists but no i18n property.
        expect(jzsaI18n('openLightbox')).toBe('');
    });

    test('returns empty string when jzsaAjax is undefined', () => {
        const { jzsaI18n } = load(undefined);
        expect(jzsaI18n('openLightbox')).toBe('');
    });

    test('returns empty string for falsy values (covers missing-translation guard)', () => {
        const { jzsaI18n } = load({ emptyKey: '' });
        expect(jzsaI18n('emptyKey')).toBe('');
    });
});

describe('jzsaFormatI18n', () => {
    test('substitutes %d with the provided number', () => {
        const { jzsaFormatI18n } = load({ photoOfTotal: 'Photo %d of total' });
        expect(jzsaFormatI18n('photoOfTotal', 5)).toBe('Photo 5 of total');
    });

    test('coerces the value to a string', () => {
        const { jzsaFormatI18n } = load({ photoOfTotal: 'Photo %d of total' });
        expect(jzsaFormatI18n('photoOfTotal', '7')).toBe('Photo 7 of total');
    });

    test('only replaces the FIRST %d (documented limitation of String.replace)', () => {
        const { jzsaFormatI18n } = load({ pair: '%d / %d' });
        expect(jzsaFormatI18n('pair', 3)).toBe('3 / %d');
    });

    test('returns empty string when key is missing (no template, no error)', () => {
        const { jzsaFormatI18n } = load({});
        expect(jzsaFormatI18n('missing', 1)).toBe('');
    });
});
