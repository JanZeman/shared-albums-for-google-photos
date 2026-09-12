import { describe, test, expect, beforeEach, vi } from 'vitest';
import jQuery from 'jquery';
import { loadHelpers } from '../helpers/loadFromSwiperInit.js';

// Regression coverage for the Guide-page slowdown: Playground "Apply" and lazy sample
// previews replace a container's innerHTML without ever calling swiper.destroy() or
// removing the stale entry from the shared `swipers` registry (assets/js/swiper-init.js
// never had a `delete swipers[id]` anywhere). applyGalleryKeyboardOwner() runs on every
// mouseenter/focusin/pointerdown/touchstart on any gallery, plus every fullscreen/lightbox
// toggle, and used to scan every gallery ever created for the life of the page -- so the
// longer an editing session ran, the more dead work every interaction did.

function makeFakeSwiper(el) {
    return {
        el,
        destroyed: false,
        destroy: vi.fn(function () {
            this.destroyed = true;
        }),
        keyboard: {
            enabled: false,
            enable: vi.fn(function () {
                this.enabled = true;
            }),
            disable: vi.fn(function () {
                this.enabled = false;
            }),
        },
    };
}

describe('gallery keyboard ownership: stale-entry pruning', () => {
    let swipers;
    let helpers;

    beforeEach(() => {
        document.body.innerHTML = '';
        swipers = {};
        helpers = loadHelpers(
            [
                'isSwiperElementLive',
                'applyGalleryKeyboardOwner',
                'pruneGalleryKeyboardOwnership',
                'claimGalleryKeyboardOwner',
                'restoreGalleryKeyboardOwner',
                'setupGalleryKeyboardOwnership',
            ],
            { $: jQuery, swipers, keyboardOwnerGalleryId: null, _jzsaLightboxBackdrop: null, _jzsaLightboxActiveEl: null }
        );
    });

    test('a live, attached gallery is left in the registry and can hold keyboard ownership', () => {
        const el = document.createElement('div');
        el.id = 'g1';
        document.body.appendChild(el);
        const swiper = makeFakeSwiper(el);
        swiper._jzsaKeyboardAllowed = true;
        swipers.g1 = swiper;

        helpers.setupGalleryKeyboardOwnership(jQuery(el), swiper, false);
        helpers.claimGalleryKeyboardOwner('g1');

        expect(Object.keys(swipers)).toEqual(['g1']);
        expect(swiper.keyboard.enable).toHaveBeenCalled();
        expect(swiper.destroy).not.toHaveBeenCalled();
    });

    test('a container whose DOM was replaced without destroy() is pruned on the next scan', () => {
        const elA = document.createElement('div');
        elA.id = 'gA';
        document.body.appendChild(elA);
        const swiperA = makeFakeSwiper(elA);
        swiperA._jzsaKeyboardAllowed = true;
        swipers.gA = swiperA;
        helpers.setupGalleryKeyboardOwnership(jQuery(elA), swiperA, false);

        // Simulate a Playground "Apply" / lazy-preview re-render: the container's
        // innerHTML is replaced, detaching the old element -- swiper.destroy() is never
        // called and swipers.gA is never deleted by the caller.
        elA.remove();

        const elB = document.createElement('div');
        elB.id = 'gB';
        document.body.appendChild(elB);
        const swiperB = makeFakeSwiper(elB);
        swiperB._jzsaKeyboardAllowed = true;
        swipers.gB = swiperB;
        helpers.setupGalleryKeyboardOwnership(jQuery(elB), swiperB, false);

        // Any subsequent interaction anywhere on the page runs this scan.
        helpers.applyGalleryKeyboardOwner();

        expect(Object.keys(swipers)).toEqual(['gB']);
        expect(swiperA.destroy).toHaveBeenCalled();
    });

    test('repeated replacements never grow the registry past the currently-attached galleries', () => {
        let previous = null;
        for (let i = 0; i < 25; i++) {
            if (previous) {
                previous.remove();
            }
            const el = document.createElement('div');
            el.id = 'gen-' + i;
            document.body.appendChild(el);
            const swiper = makeFakeSwiper(el);
            swiper._jzsaKeyboardAllowed = true;
            swipers[el.id] = swiper;
            helpers.setupGalleryKeyboardOwnership(jQuery(el), swiper, false);
            previous = el;
        }

        expect(Object.keys(swipers)).toHaveLength(1);
        expect(Object.keys(swipers)[0]).toBe('gen-24');
    });

    // openLightbox() moves an album into the single shared body-level backdrop and leaves a
    // comment-node placeholder at its original spot, restored on close. If a Playground/lazy
    // preview re-render replaces that spot's container while the lightbox is still open, the
    // element stays document.body.contains()-true (it's inside the backdrop) even though its
    // placeholder's whole subtree has been detached -- so document.body.contains() must be
    // checked on the placeholder too, not just `placeholder.parentNode` (non-null even for a
    // fully orphaned subtree, since detachment doesn't sever internal parent-child links).
    describe('an album relocated into the shared lightbox backdrop', () => {
        let backdrop;

        beforeEach(() => {
            backdrop = document.createElement('div');
            backdrop.className = 'jzsa-lightbox-backdrop';
            document.body.appendChild(backdrop);
            helpers = loadHelpers(
                [
                    'isSwiperElementLive',
                    'applyGalleryKeyboardOwner',
                    'pruneGalleryKeyboardOwnership',
                    'claimGalleryKeyboardOwner',
                    'restoreGalleryKeyboardOwner',
                    'setupGalleryKeyboardOwnership',
                ],
                { $: jQuery, swipers, keyboardOwnerGalleryId: null, _jzsaLightboxBackdrop: backdrop, _jzsaLightboxActiveEl: null }
            );
        });

        test('stays live while its original placeholder is still connected to the document', () => {
            const originalParent = document.createElement('div');
            document.body.appendChild(originalParent);
            const placeholder = document.createComment('jzsa-lightbox-placeholder');
            originalParent.appendChild(placeholder);

            const el = document.createElement('div');
            el.id = 'open-lightbox';
            backdrop.appendChild(el);
            jQuery(el).data('jzsaLightboxPlaceholder', placeholder);
            const swiper = makeFakeSwiper(el);
            swiper._jzsaKeyboardAllowed = true;
            swipers[el.id] = swiper;

            helpers.applyGalleryKeyboardOwner();

            expect(Object.keys(swipers)).toEqual(['open-lightbox']);
            expect(swiper.destroy).not.toHaveBeenCalled();
        });

        test('is pruned once its original container is replaced out from under it', () => {
            const originalParent = document.createElement('div');
            document.body.appendChild(originalParent);
            const placeholder = document.createComment('jzsa-lightbox-placeholder');
            originalParent.appendChild(placeholder);

            const el = document.createElement('div');
            el.id = 'orphaned-lightbox';
            backdrop.appendChild(el);
            jQuery(el).data('jzsaLightboxPlaceholder', placeholder);
            const swiper = makeFakeSwiper(el);
            swiper._jzsaKeyboardAllowed = true;
            swipers[el.id] = swiper;

            // Playground "Apply" replaces the original container's innerHTML while the
            // lightbox is still open: the placeholder's subtree is detached, but the
            // element itself is still sitting inside the backdrop (still in document.body).
            originalParent.innerHTML = '<div>new preview content</div>';

            helpers.applyGalleryKeyboardOwner();

            expect(Object.keys(swipers)).toEqual([]);
            expect(swiper.destroy).toHaveBeenCalled();
            // The dead element must not be left behind, hidden, inside the backdrop forever.
            expect(backdrop.contains(el)).toBe(false);
        });
    });
});
