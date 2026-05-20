import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

const { jzsaEscapeAttr } = loadHelpers(['jzsaEscapeAttr']);

describe('jzsaEscapeAttr', () => {
    test('passes through plain ASCII unchanged', () => {
        expect(jzsaEscapeAttr('hello world')).toBe('hello world');
    });

    test('escapes ampersand', () => {
        expect(jzsaEscapeAttr('Tom & Jerry')).toBe('Tom &amp; Jerry');
    });

    test('escapes double quotes', () => {
        expect(jzsaEscapeAttr('he said "hi"')).toBe('he said &quot;hi&quot;');
    });

    test('escapes angle brackets', () => {
        expect(jzsaEscapeAttr('<script>alert(1)</script>')).toBe(
            '&lt;script&gt;alert(1)&lt;/script&gt;'
        );
    });

    test('escapes all four chars in one call without double-escaping', () => {
        // & must be replaced first; otherwise "&lt;" would become "&amp;lt;".
        expect(jzsaEscapeAttr('a & <b "c">')).toBe('a &amp; &lt;b &quot;c&quot;&gt;');
    });

    test('coerces non-string input to string', () => {
        expect(jzsaEscapeAttr(42)).toBe('42');
        expect(jzsaEscapeAttr(null)).toBe('null');
        expect(jzsaEscapeAttr(undefined)).toBe('undefined');
        expect(jzsaEscapeAttr(true)).toBe('true');
    });

    test('does NOT escape single quotes (apostrophes pass through)', () => {
        // Documented behaviour: function is for attribute values inside double-quoted
        // HTML attributes, so single quotes are intentionally left intact.
        expect(jzsaEscapeAttr("it's fine")).toBe("it's fine");
    });

    test('handles empty string', () => {
        expect(jzsaEscapeAttr('')).toBe('');
    });
});
