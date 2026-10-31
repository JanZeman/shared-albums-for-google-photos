import { describe, test, expect } from 'vitest';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

const i18n = {
    openInGooglePhotos: 'Open in Google Photos',
    openAlbumGooglePhotos: 'Open album in Google Photos',
    openInICloudPhotos: 'Open in iCloud Photos',
    openAlbumICloudPhotos: 'Open album in iCloud Photos',
};

const { albumLinkLabel, inferMediaTypeFromUrl } = loadHelpers(
    ['jzsaI18n', 'albumLinkLabel', 'inferMediaTypeFromUrl'],
    { jzsaAjax: { i18n } }
);

describe('albumLinkLabel', () => {
    test('names iCloud for iCloud album links', () => {
        expect(albumLinkLabel('https://photos.icloud.com/shared/album/00abc', false)).toBe('Open in iCloud Photos');
        expect(albumLinkLabel('https://www.icloud.com/sharedalbum/#B0abc', true)).toBe('Open album in iCloud Photos');
    });

    test('keeps Google Photos for other links', () => {
        expect(albumLinkLabel('https://photos.google.com/share/AF1Qip?key=abc', false)).toBe('Open in Google Photos');
        expect(albumLinkLabel('', true)).toBe('Open album in Google Photos');
    });
});

describe('inferMediaTypeFromUrl', () => {
    test('recognizes video renditions behind stable iCloud media URLs', () => {
        expect(inferMediaTypeFromUrl('https://site.example/?jzsa_media=1&album=x&id=A&r=VidMed')).toBe('video');
        expect(inferMediaTypeFromUrl('https://site.example/?jzsa_media=1&album=x&id=A&r=720p')).toBe('video');
    });

    test('treats image renditions and other URLs as photos', () => {
        expect(inferMediaTypeFromUrl('https://site.example/?jzsa_media=1&album=x&id=A&r=JPEGMed')).toBe('photo');
        expect(inferMediaTypeFromUrl('https://site.example/page?r=VidMed')).toBe('photo');
        expect(inferMediaTypeFromUrl('https://cvws.icloud-content.com/B/x/clip.mp4?o=1')).toBe('video');
    });
});
