import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

const { shufflePhotos } = loadHelpers(['shufflePhotos']);

describe('shufflePhotos', () => {
    const photos = Array.from({ length: 40 }, (_, i) => ({ full: 'https://example.test/' + i }));

    test('returns every photo exactly once', () => {
        const shuffled = shufflePhotos(photos);
        expect(shuffled).toHaveLength(photos.length);
        expect(new Set(shuffled)).toEqual(new Set(photos));
    });

    test('does not modify the input array', () => {
        const copy = photos.slice();
        shufflePhotos(photos);
        expect(photos).toEqual(copy);
    });

    test('changes the order', () => {
        const orders = new Set();
        for (let i = 0; i < 5; i++) {
            orders.add(shufflePhotos(photos).map((p) => p.full).join(','));
        }
        expect(orders.size).toBeGreaterThan(1);
    });

    test('handles empty and single-photo lists', () => {
        expect(shufflePhotos([])).toEqual([]);
        expect(shufflePhotos([photos[0]])).toEqual([photos[0]]);
    });
});
