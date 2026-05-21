import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

// resolveInfoPlaceholders depends on formatPhotoDate and buildCameraDisplayName,
// which in turn depends on normalizeCameraMakeForDisplay.
const { resolveInfoPlaceholders } = loadHelpers([
    'normalizeCameraMakeForDisplay',
    'buildCameraDisplayName',
    'formatPhotoDate',
    'resolveInfoPlaceholders',
]);

describe('resolveInfoPlaceholders', () => {
    test('returns empty when format or photo is missing', () => {
        expect(resolveInfoPlaceholders('', { iso: '100' })).toBe('');
        expect(resolveInfoPlaceholders('{iso}', null)).toBe('');
    });

    test('resolves a placeholder to its value', () => {
        expect(resolveInfoPlaceholders('{iso}', { iso: 'ISO 100' })).toBe('ISO 100');
    });

    test('hides the box when every placeholder is empty and only separators remain', () => {
        // The screenshot bug: EXIF absent, U+2E31 (⸱) separators left orphaned.
        const format = '{aperture} ⸱ {shutter} ⸱ {focal} ⸱ {iso}';
        expect(resolveInfoPlaceholders(format, {})).toBe('');
    });

    test('hides the box for the classic middle-dot separator too', () => {
        expect(resolveInfoPlaceholders('{aperture} · {shutter}', {})).toBe('');
    });

    test('drops orphaned leading separators when only some values resolve', () => {
        const format = '{aperture} ⸱ {shutter} ⸱ {focal} ⸱ {iso}';
        expect(resolveInfoPlaceholders(format, { iso: 'ISO 100' })).toBe('ISO 100');
    });

    test('keeps separators between two resolved values', () => {
        const format = '{aperture} ⸱ {iso}';
        expect(resolveInfoPlaceholders(format, { aperture: 'f/2.8', iso: 'ISO 100' }))
            .toBe('f/2.8 ⸱ ISO 100');
    });

    test('preserves literal text the user typed even when placeholders are empty', () => {
        expect(resolveInfoPlaceholders('Camera: {camera}', {})).toBe('Camera:');
    });

    test('keeps a placeholder value that has no letters or digits (e.g. emoji description)', () => {
        expect(resolveInfoPlaceholders('{description}', { description: '\u{1F389}' }))
            .toBe('\u{1F389}');
    });
});
