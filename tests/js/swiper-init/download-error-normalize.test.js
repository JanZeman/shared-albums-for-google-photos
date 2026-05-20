import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

const { normalizeDownloadErrorData } = loadHelpers(['normalizeDownloadErrorData']);

describe('normalizeDownloadErrorData', () => {
    test('returns a canonical shape with safe defaults for nullish input', () => {
        expect(normalizeDownloadErrorData(null)).toEqual({
            message: 'Download failed. Please try again.',
            requiresLargeDownloadConfirmation: false,
            actualSizeBytes: 0,
            maxSizeBytes: 0,
        });
        expect(normalizeDownloadErrorData(undefined)).toEqual({
            message: 'Download failed. Please try again.',
            requiresLargeDownloadConfirmation: false,
            actualSizeBytes: 0,
            maxSizeBytes: 0,
        });
    });

    test('uses raw string as the message', () => {
        expect(normalizeDownloadErrorData('Custom failure')).toMatchObject({
            message: 'Custom failure',
            requiresLargeDownloadConfirmation: false,
        });
    });

    test('reads message + size fields from a structured wp_send_json_error payload', () => {
        const raw = {
            message: 'Too large',
            requires_large_download_confirmation: true,
            actual_size_bytes: 12_345_678,
            warning_size_bytes: 10_000_000,
        };
        expect(normalizeDownloadErrorData(raw)).toEqual({
            message: 'Too large',
            requiresLargeDownloadConfirmation: true,
            actualSizeBytes: 12_345_678,
            maxSizeBytes: 10_000_000,
        });
    });

    test('coerces stringified numeric sizes to integers', () => {
        const raw = {
            message: 'Big',
            actual_size_bytes: '1024',
            warning_size_bytes: '512',
        };
        expect(normalizeDownloadErrorData(raw)).toMatchObject({
            actualSizeBytes: 1024,
            maxSizeBytes: 512,
        });
    });

    test('non-boolean truthy value does NOT trigger the large-download flag', () => {
        // The check is strictly === true, so "true" / 1 / "yes" do not flip it.
        expect(
            normalizeDownloadErrorData({ requires_large_download_confirmation: 'true' })
                .requiresLargeDownloadConfirmation
        ).toBe(false);
        expect(
            normalizeDownloadErrorData({ requires_large_download_confirmation: 1 })
                .requiresLargeDownloadConfirmation
        ).toBe(false);
    });

    test('ignores unparseable size fields (NaN guard)', () => {
        const raw = {
            message: 'nope',
            actual_size_bytes: 'huge',
            warning_size_bytes: 'tiny',
        };
        expect(normalizeDownloadErrorData(raw)).toMatchObject({
            actualSizeBytes: 0,
            maxSizeBytes: 0,
        });
    });

    test('treats explicit null size fields as zero (do not throw)', () => {
        const raw = {
            actual_size_bytes: null,
            warning_size_bytes: null,
        };
        expect(normalizeDownloadErrorData(raw)).toMatchObject({
            actualSizeBytes: 0,
            maxSizeBytes: 0,
        });
    });

    test('falls back to default message when raw object lacks a message', () => {
        expect(normalizeDownloadErrorData({ actual_size_bytes: 5 }).message).toBe(
            'Download failed. Please try again.'
        );
    });

    test('ignores non-string message values', () => {
        expect(normalizeDownloadErrorData({ message: 42 }).message).toBe(
            'Download failed. Please try again.'
        );
    });
});
