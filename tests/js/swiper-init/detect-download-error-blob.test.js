import { describe, test, expect, vi } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

// detectDownloadErrorFromBlob depends on normalizeDownloadErrorData.
const { detectDownloadErrorFromBlob } = loadHelpers([
    'normalizeDownloadErrorData',
    'detectDownloadErrorFromBlob',
]);

/**
 * Wrap the callback-style helper in a Promise so tests can await it cleanly.
 */
function detect(blob) {
    return new Promise((resolve) => {
        detectDownloadErrorFromBlob(blob, resolve);
    });
}

describe('detectDownloadErrorFromBlob', () => {
    test('does nothing when callback is not a function (no throw)', () => {
        expect(() => detectDownloadErrorFromBlob(new Blob(['x']), undefined)).not.toThrow();
        expect(() => detectDownloadErrorFromBlob(new Blob(['x']), null)).not.toThrow();
    });

    test('returns null when blob is missing', async () => {
        expect(await detect(null)).toBeNull();
        expect(await detect(undefined)).toBeNull();
    });

    test('returns null for binary content-type (image/jpeg)', async () => {
        const blob = new Blob(['fake binary'], { type: 'image/jpeg' });
        expect(await detect(blob)).toBeNull();
    });

    test('returns null for video binary types', async () => {
        const blob = new Blob(['fake'], { type: 'video/mp4' });
        expect(await detect(blob)).toBeNull();
    });

    test('parses application/json error envelope into normalized error data', async () => {
        const payload = JSON.stringify({
            success: false,
            data: {
                message: 'File too large',
                requires_large_download_confirmation: true,
                actual_size_bytes: 99,
                warning_size_bytes: 50,
            },
        });
        const blob = new Blob([payload], { type: 'application/json' });

        expect(await detect(blob)).toEqual({
            message: 'File too large',
            requiresLargeDownloadConfirmation: true,
            actualSizeBytes: 99,
            maxSizeBytes: 50,
        });
    });

    test('parses text/* content types as well', async () => {
        const payload = JSON.stringify({ success: false, data: 'plain message' });
        const blob = new Blob([payload], { type: 'text/plain' });
        expect(await detect(blob)).toMatchObject({ message: 'plain message' });
    });

    test('returns null when JSON has success: true (not a wp_send_json_error envelope)', async () => {
        const payload = JSON.stringify({ success: true, data: { ok: true } });
        const blob = new Blob([payload], { type: 'application/json' });
        expect(await detect(blob)).toBeNull();
    });

    test('returns null when content is not parseable JSON (graceful fall-through)', async () => {
        const blob = new Blob(['not json {{{'], { type: 'application/json' });
        expect(await detect(blob)).toBeNull();
    });

    test('returns null for empty JSON body', async () => {
        const blob = new Blob([''], { type: 'application/json' });
        expect(await detect(blob)).toBeNull();
    });

    test('inspects untyped small blobs heuristically (size ≤ 4096)', async () => {
        const payload = JSON.stringify({ success: false, data: { message: 'sneaky' } });
        const blob = new Blob([payload]); // no type
        expect(await detect(blob)).toMatchObject({ message: 'sneaky' });
    });

    test('skips untyped blobs that are too large to be a JSON error', async () => {
        const big = 'x'.repeat(5000);
        const blob = new Blob([big]); // no type, > 4096 bytes
        expect(await detect(blob)).toBeNull();
    });

    test('falls back to FileReader when blob.text() is not available', async () => {
        const payload = JSON.stringify({ success: false, data: { message: 'via reader' } });

        // Construct a Blob-like that exposes type/size but no .text(), forcing the
        // FileReader code path.
        const fakeBlob = {
            type: 'application/json',
            size: payload.length,
            // text intentionally absent
        };

        // jsdom provides a real FileReader; replace it with a deterministic stub.
        const originalReader = globalThis.FileReader;
        globalThis.FileReader = vi.fn().mockImplementation(() => ({
            readAsText() {
                setTimeout(() => {
                    this.result = payload;
                    if (typeof this.onload === 'function') {
                        this.onload();
                    }
                }, 0);
            },
        }));

        try {
            const result = await new Promise((resolve) => {
                detectDownloadErrorFromBlob(fakeBlob, resolve);
            });
            expect(result).toMatchObject({ message: 'via reader' });
        } finally {
            globalThis.FileReader = originalReader;
        }
    });

    test('returns null when blob.text() rejects', async () => {
        const fakeBlob = {
            type: 'application/json',
            size: 10,
            text() {
                return Promise.reject(new Error('boom'));
            },
        };
        expect(await detect(fakeBlob)).toBeNull();
    });
});
