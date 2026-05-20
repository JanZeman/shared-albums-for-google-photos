import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

// buildCameraDisplayName depends on normalizeCameraMakeForDisplay.
const { normalizeCameraMakeForDisplay, buildCameraDisplayName } = loadHelpers([
    'normalizeCameraMakeForDisplay',
    'buildCameraDisplayName',
]);

describe('normalizeCameraMakeForDisplay', () => {
    test('returns empty for null / undefined / empty input', () => {
        expect(normalizeCameraMakeForDisplay(null)).toBe('');
        expect(normalizeCameraMakeForDisplay(undefined)).toBe('');
        expect(normalizeCameraMakeForDisplay('')).toBe('');
        expect(normalizeCameraMakeForDisplay('   ')).toBe('');
    });

    test('keeps fully-uppercase tokens (likely acronyms like RICOH, NIKON) intact', () => {
        expect(normalizeCameraMakeForDisplay('NIKON')).toBe('NIKON');
        expect(normalizeCameraMakeForDisplay('RICOH IMAGING COMPANY')).toBe('RICOH IMAGING COMPANY');
    });

    test('title-cases mixed-case tokens', () => {
        expect(normalizeCameraMakeForDisplay('canon')).toBe('Canon');
        expect(normalizeCameraMakeForDisplay('canon inc.')).toBe('Canon Inc.');
        expect(normalizeCameraMakeForDisplay('OLYMPUS imaging corp.')).toBe('OLYMPUS Imaging Corp.');
    });

    test('preserves whitespace between tokens', () => {
        expect(normalizeCameraMakeForDisplay('foo   bar')).toBe('Foo   Bar');
    });
});

describe('buildCameraDisplayName', () => {
    test('returns just the make when model is missing', () => {
        expect(buildCameraDisplayName('Canon', '')).toBe('Canon');
        expect(buildCameraDisplayName('Canon', null)).toBe('Canon');
    });

    test('returns just the model when make is missing', () => {
        expect(buildCameraDisplayName('', 'EOS R5')).toBe('EOS R5');
        expect(buildCameraDisplayName(null, 'EOS R5')).toBe('EOS R5');
    });

    test('returns empty when both are missing', () => {
        expect(buildCameraDisplayName('', '')).toBe('');
        expect(buildCameraDisplayName(null, null)).toBe('');
    });

    test('strips legal-entity suffixes from the make (Inc., Corp., GmbH, ...)', () => {
        expect(buildCameraDisplayName('Leica Camera GmbH', 'Q3')).toBe('Leica Camera Q3');
        expect(buildCameraDisplayName('Canon Incorporated', 'EOS R5')).toBe('Canon EOS R5');
        // Regression: the suffix regex used to leave a dangling period from "Inc."
        // / "Corp." because its trailing \b sits between letter and ".".
        expect(buildCameraDisplayName('Canon Inc.', 'EOS R5')).toBe('Canon EOS R5');
        expect(buildCameraDisplayName('OLYMPUS IMAGING CORP.', 'PEN-F')).toBe(
            'OLYMPUS IMAGING PEN-F'
        );
        expect(buildCameraDisplayName('Hewlett-Packard Co.', 'LaserJet')).toBe(
            'Hewlett-Packard LaserJet'
        );
    });

    test('returns just the model when the make is already embedded in it', () => {
        // "iPhone 15 Pro" already contains "Apple"? No. But common pattern:
        // model "NIKON D850" already contains the make NIKON.
        expect(buildCameraDisplayName('NIKON CORPORATION', 'NIKON D850')).toBe('NIKON D850');
        expect(buildCameraDisplayName('Sony', 'Sony A7 IV')).toBe('Sony A7 IV');
    });

    test('case-insensitive embed detection', () => {
        // make "Canon" inside model "canon eos" (lowercase) should still dedupe.
        expect(buildCameraDisplayName('Canon Inc.', 'canon EOS R5')).toBe('canon EOS R5');
    });

    test('punctuation/whitespace differences do not block the dedupe', () => {
        // "Hewlett-Packard" stripped of non-alphanumerics → "hewlettpackard",
        // and model "HP" alone wouldn't contain it, so this would concat.
        // But "OLYMPUS IMAGING" → "olympusimaging" matches "OLYMPUS-IMAGING PEN".
        expect(buildCameraDisplayName('OLYMPUS IMAGING', 'OLYMPUS-IMAGING PEN-F')).toBe(
            'OLYMPUS-IMAGING PEN-F'
        );
    });

    test('falls back to the original make when suffix-stripping would empty it', () => {
        // "Incorporated" alone strips to nothing → fallback kicks in.
        expect(buildCameraDisplayName('Incorporated', 'X100V')).toBe('Incorporated X100V');
        // Regression: "Inc." used to leak a lone period because the dangling-dot
        // bypassed the empty-fallback check.
        expect(buildCameraDisplayName('Inc.', 'X100V')).toBe('Inc. X100V');
        expect(buildCameraDisplayName('Co.', 'Foo')).toBe('Co. Foo');
    });

    test('concatenates with a single space (no double-spacing) when both are kept', () => {
        expect(buildCameraDisplayName('Canon', 'EOS R5')).toBe('Canon EOS R5');
        expect(buildCameraDisplayName('  Canon  ', '  EOS R5  ')).toBe('Canon EOS R5');
    });
});
