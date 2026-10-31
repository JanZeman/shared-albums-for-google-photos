import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

const { photoAspectRatio } = loadHelpers(['parseAspectRatio', 'photoAspectRatio']);

describe('photoAspectRatio', () => {
    test('reads the size from Google =w-h URLs', () => {
        expect(photoAspectRatio({ preview: 'https://lh3.googleusercontent.com/abc=w800-h400', width: 300, height: 600 })).toBe(2);
    });

    test('uses the photo dimensions for URLs without a size (iCloud)', () => {
        expect(photoAspectRatio({ preview: 'https://site.example/?jzsa_media=1&r=JPEGMed', width: 3024, height: 4032 })).toBe(0.75);
    });

    test('falls back to 4:3 when nothing is known', () => {
        expect(photoAspectRatio({ full: 'https://site.example/?jzsa_media=1' })).toBe(4 / 3);
    });
});
