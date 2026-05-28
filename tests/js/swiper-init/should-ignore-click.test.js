import { describe, test, expect, beforeAll } from 'vitest';
import jQuery from 'jquery';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

// shouldIgnoreClick uses jQuery's $(target).closest(selectorList). The production
// IIFE receives jQuery as its parameter, so replicate that by injecting it as $.
const { shouldIgnoreClick } = loadHelpers(['shouldIgnoreClick'], { $: jQuery });

function makeDom(html) {
    document.body.innerHTML = html;
}

describe('shouldIgnoreClick', () => {
    beforeAll(() => {
        // Make jQuery operate against the jsdom document.
        // jQuery in module form already binds to globalThis.window in jsdom.
    });

    test('returns true when the target IS the lightbox button', () => {
        makeDom('<div class="swiper-button-lightbox"></div>');
        const target = document.querySelector('.swiper-button-lightbox');
        expect(shouldIgnoreClick(target)).toBe(true);
    });

    test('returns true when the target is a CHILD of a control button', () => {
        makeDom('<div class="swiper-button-fullscreen"><span class="icon"></span></div>');
        const target = document.querySelector('.icon');
        expect(shouldIgnoreClick(target)).toBe(true);
    });

    test('matches each documented control selector', () => {
        const controls = [
            'swiper-button-next',
            'swiper-button-prev',
            'swiper-button-fullscreen',
            'swiper-button-lightbox',
            'swiper-button-external-link',
            'swiper-button-download',
            'swiper-button-play-pause',
            'swiper-pagination',
            'plyr__controls',
            'plyr__control',
        ];
        for (const cls of controls) {
            makeDom(`<div class="${cls}"><span class="probe"></span></div>`);
            const target = document.querySelector('.probe');
            expect(shouldIgnoreClick(target), `child of .${cls} must be ignored`).toBe(true);
        }
    });

    test('returns false for a click on the slide content itself', () => {
        makeDom('<div class="swiper-slide"><img class="photo"/></div>');
        const target = document.querySelector('.photo');
        expect(shouldIgnoreClick(target)).toBe(false);
    });

    test('returns false for arbitrary unrelated DOM nodes', () => {
        makeDom('<div class="some-other-area"><a class="link">x</a></div>');
        const target = document.querySelector('.link');
        expect(shouldIgnoreClick(target)).toBe(false);
    });

    test('handles deeply nested targets inside a control', () => {
        makeDom(`
            <div class="swiper-button-fullscreen">
                <span><span><span class="deep">x</span></span></span>
            </div>
        `);
        const target = document.querySelector('.deep');
        expect(shouldIgnoreClick(target)).toBe(true);
    });

    test('returns false when the element shares a prefix but not the full class', () => {
        // The selector list uses exact class names. A custom class like
        // "swiper-button-next-custom" must NOT trigger the ignore branch.
        makeDom('<div class="swiper-button-next-custom"></div>');
        const target = document.querySelector('.swiper-button-next-custom');
        expect(shouldIgnoreClick(target)).toBe(false);
    });
});
