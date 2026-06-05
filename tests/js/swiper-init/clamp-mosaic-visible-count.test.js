import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

const { clampMosaicVisibleCount } = loadHelpers(['clampMosaicVisibleCount']);

describe('clampMosaicVisibleCount', () => {
    test('caps the visible count to the number of available thumbnails', () => {
        expect(clampMosaicVisibleCount(7, 6)).toBe(6);
    });

    test('keeps a valid count when enough thumbnails exist', () => {
        expect(clampMosaicVisibleCount(7, 12)).toBe(7);
    });

    test('normalizes invalid visible counts to one', () => {
        expect(clampMosaicVisibleCount(0, 6)).toBe(1);
        expect(clampMosaicVisibleCount('auto', 6)).toBe(1);
    });

    test('does not cap when thumbnail count is unknown', () => {
        expect(clampMosaicVisibleCount(7, 0)).toBe(7);
    });
});
