/**
 * Swiper Gallery Initialization for Shared Albums for Google Photos (by JanZeman)
 */
/* global Swiper */
(function($) {
'use strict';

	// iOS/iPadOS detection - iPad Pro reports as MacIntel with touch points > 1.
	var IS_IOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
	    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

	// Debug flag for optional console logging. Disabled by default.
	// To enable in the browser console, run: window.JZSA_DEBUG = true;
	window.JZSA_DEBUG = window.JZSA_DEBUG || false;
	function jzsaDebug() {
		if (!window.JZSA_DEBUG) {
			return;
        }
		// eslint-disable-next-line no-console
		console.log.apply(console, arguments);
	}

	jzsaDebug('✅ Shared Albums for Google Photos (by JanZeman) loaded');

	/**
	 * Recolor inline-SVG icon pseudo-elements for a scoped container.
	 * Data-URI SVGs cannot reference CSS variables, so we inject a scoped
	 * <style> that rewrites the white fill (%23ffffff) with the chosen color.
	 */
	function applyControlsColorToIcons(scopeSelector, color) {
		var $scope = $(scopeSelector).first();
		if (!$scope.length) {
			return;
		}

		var enc = encodeURIComponent(color);
		var svgs = {
			next:       "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 24'><path d='M2 2l8 10-8 10' fill='none' stroke='" + enc + "' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' paint-order='stroke fill'/><path d='M2 2l8 10-8 10' fill='none' stroke='%23000000' stroke-width='4.5' stroke-linecap='round' stroke-linejoin='round'/><path d='M2 2l8 10-8 10' fill='none' stroke='" + enc + "' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/></svg>\")",
			prev:       "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 24'><path d='M10 2L2 12l8 10' fill='none' stroke='" + enc + "' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' paint-order='stroke fill'/><path d='M10 2L2 12l8 10' fill='none' stroke='%23000000' stroke-width='4.5' stroke-linecap='round' stroke-linejoin='round'/><path d='M10 2L2 12l8 10' fill='none' stroke='" + enc + "' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/></svg>\")",
			play:       "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M8 5v14l11-7z' fill='" + enc + "' stroke='%23000000' stroke-width='1.5' paint-order='stroke fill'/></svg>\")",
			pause:      "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M7 18h3V6H7v12zm7-12v12h3V6h-3z' fill='" + enc + "' stroke='%23000000' stroke-width='1' paint-order='stroke fill'/></svg>\")",
			fullscreen: "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z' fill='" + enc + "' stroke='%23000000' stroke-width='1.5' paint-order='stroke fill'/></svg>\")",
			exitFs:     "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z' fill='" + enc + "' stroke='%23000000' stroke-width='1.5' paint-order='stroke fill'/></svg>\")",
			link:       "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z' fill='" + enc + "' stroke='%23000000' stroke-width='1.5' paint-order='stroke fill'/></svg>\")",
			download:   "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z' fill='" + enc + "' stroke='%23000000' stroke-width='1.5' paint-order='stroke fill'/></svg>\")"
		};
		var s = scopeSelector;
		var css =
			s + ' .swiper-button-next:after{background-image:' + svgs.next + '}' +
			s + ' .swiper-button-prev:after{background-image:' + svgs.prev + '}' +
			s + ' .swiper-button-play-pause:after{background-image:' + svgs.play + '}' +
			s + ' .swiper-button-play-pause.playing:after{background-image:' + svgs.pause + '}' +
			s + ' .swiper-button-fullscreen:after{background-image:' + svgs.fullscreen + '}' +
			s + ':fullscreen .swiper-button-fullscreen:after,' +
			s + ':-webkit-full-screen .swiper-button-fullscreen:after{background-image:' + svgs.exitFs + '}' +
			s + ' .swiper-button-external-link:after{background-image:' + svgs.link + '}' +
			s + ' .swiper-button-download:after{background-image:' + svgs.download + '}';
		var styleKey = (scopeSelector || 'scope').replace(/[^a-zA-Z0-9_-]/g, '_');
		var $style = $scope.children('style[data-jzsa-controls-color="' + styleKey + '"]');
		if (!$style.length) {
			$style = $('<style>').attr('data-jzsa-controls-color', styleKey);
			$scope.append($style);
		}
		$style.text(css);
	}

	var swipers = {};

    // Stop and reset all managed videos on the page. Used when switching
    // between inline and fullscreen so no hidden/orphan playback continues.
    function stopAllManagedVideos() {
        $('video.jzsa-video-player').each(function() {
            var videoEl = this;
            var $video = $(videoEl);
            var $album = $video.closest('.jzsa-album, .jzsa-gallery-album');

            // Abort any in-flight loading attempt first.
            if (typeof videoEl._jzsaCancelLoading === 'function') {
                try { videoEl._jzsaCancelLoading(); } catch (e) { /* ignore */ }
            }

            if (videoEl._jzsaPlyr) {
                videoEl._jzsaSuppressNextError = true;
                try { videoEl._jzsaPlyr.stop(); } catch (e) { /* ignore */ }
            } else {
                try { videoEl.pause(); } catch (e) { /* ignore */ }
                try { videoEl.currentTime = 0; } catch (e) { /* ignore */ }
            }

            if ($album.length) {
                $album.removeClass('jzsa-video-playing');
                $album.trigger('jzsa:video-stopped');
            }
        });
    }

    // ============================================================================
    // FULLSCREEN FUNCTIONALITY
    // ============================================================================

    // Fullscreen toggle function (with iPhone pseudo-fullscreen fallback)
    function toggleFullscreen(element, showHints) {
        var showHintsFn = showHints;
        stopAllManagedVideos();
        var currentlyFullscreen = isFullscreen(element);
        var pseudoActive = $(element).hasClass('jzsa-pseudo-fullscreen');

        if (!currentlyFullscreen) {
            // On iPhone/iPod, use CSS-based pseudo fullscreen instead of the
            // Fullscreen API, which is not reliably supported for arbitrary
            // elements.
            if (isIphone()) {
                if (enterPseudoFullscreen(element) && typeof showHintsFn === 'function') {
                    showHintsFn();
                }
                // Native fullscreen events do not fire for pseudo fullscreen.
                $(element).trigger('jzsa:fullscreen-state', [true]);
            } else {
                // Save scroll position so we can restore it when exiting
                $(element).data('jzsa-scroll-y', window.scrollY || window.pageYOffset);
                // Enter native fullscreen where supported
                if (element.requestFullscreen) {
                    element.requestFullscreen();
                } else if (element.webkitRequestFullscreen) {
                    element.webkitRequestFullscreen();
                } else if (element.mozRequestFullScreen) {
                    element.mozRequestFullScreen();
                } else if (element.msRequestFullscreen) {
                    element.msRequestFullscreen();
                }

                // Show hints if enabled
                if (typeof showHintsFn === 'function') {
                    showHintsFn();
                }
            }
        } else {
            if (pseudoActive) {
                // Exit pseudo fullscreen
                exitPseudoFullscreen(element);
                $(element).trigger('jzsa:fullscreen-state', [false]);
            } else {
                // Exit native fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }
    }

    // ============================================================================
    // HINT SYSTEM
    // ============================================================================

    // Show gesture hints (only first few times entering fullscreen, global counter across all albums)
    // To reset for testing: localStorage.removeItem('jzsa-hints-counter')
    function createHintSystem(galleryId) {
        var HINTS_STORAGE_KEY = 'jzsa-hints-counter'; // Global counter for all albums
        var MAX_HINT_DISPLAYS = 1; // Maximum number of times to show hints
        var HINT_FADE_IN_DELAY = 100; // ms
        var HINT_FADE_OUT_DELAY = 500; // ms
        var HINT_DISPLAY_DURATION = 9500; // ms - how long hint stays visible

        return function showHints() {
            // Check if we should show hints
            try {
                var hintCount = parseInt(localStorage.getItem(HINTS_STORAGE_KEY) || '0');

                if (hintCount >= MAX_HINT_DISPLAYS) {
                    return; // Already shown enough times
                }

                // Increment counter
                localStorage.setItem(HINTS_STORAGE_KEY, String(hintCount + 1));

                // Build hint message
                var hints = [];
                hints.push('Click / tap / swipe left or right to browse photos');
                hints.push('Press Esc or tap \u29C9 to exit fullscreen');

                if (hints.length === 0) {
                    return; // No hints to show
                }

                // Create hint overlay - use line breaks instead of bullets
                var hintText = hints.join('<br>');
                var $hint = $('<div class="jzsa-hint">' + hintText + '</div>');
                $('#' + galleryId).append($hint);

                // Fade in
                setTimeout(function() {
                    $hint.addClass('jzsa-hint-visible');
                }, HINT_FADE_IN_DELAY);

                // Fade out after some seconds
                setTimeout(function() {
                    $hint.removeClass('jzsa-hint-visible');
                    setTimeout(function() {
                        $hint.remove();
                    }, HINT_FADE_OUT_DELAY);
                }, HINT_DISPLAY_DURATION);

			} catch (e) {
				// localStorage might not be available (private browsing, etc.)
				jzsaDebug('Hints not available:', e);
			}
		};
    }

    // ============================================================================
    // HELPER FUNCTIONS
    // ============================================================================

    // Time conversion constant (shared by all helpers and initializers)
    var MILLISECONDS_PER_SECOND = 1000; // Conversion factor from seconds to milliseconds
    // Loader UX: avoid flashing loader on quick responses.
    var LOADER_SHOW_DELAY_MS = 300;
    var LOADER_MIN_VISIBLE_MS = 250;

    // Helper: Run after the next paint cycle (double RAF) to avoid synchronous
    // layout flushes when restarting CSS transitions.
    function runOnNextPaint(callback) {
        var raf = window.requestAnimationFrame || function(cb) { return window.setTimeout(cb, 16); };
        raf(function() {
            raf(function() {
                callback();
            });
        });
    }

    // Helper: Prefer transitionend, with a timeout fallback for safety.
    function waitForTransitionEnd($element, fallbackMs, callback) {
        var done = false;
        function finish() {
            if (done) {
                return;
            }
            done = true;
            if ($element && $element.length) {
                $element.off('.jzsaTransitionWait');
            }
            callback();
        }

        if ($element && $element.length) {
            $element.on('transitionend.jzsaTransitionWait webkitTransitionEnd.jzsaTransitionWait', function(event) {
                if (!event || event.target !== $element[0]) {
                    return;
                }
                finish();
            });
        }

        window.setTimeout(finish, fallbackMs);
    }

    // Helper: Detect Android (for platform-specific workarounds)
    function isAndroid() {
        var ua = window.navigator.userAgent || '';
        return /Android/i.test(ua);
    }

    // Helper: Detect iOS devices (iPhone, iPad, iPod, and iPadOS with Mac-like UA)
    // Used for pseudo fullscreen fallback and for old-iOS layout workarounds.
    function isIosDevice() {
        var ua = window.navigator.userAgent || '';
        var isAppleMobile = /iPad|iPhone|iPod/i.test(ua);
        var isTouchMac = /Macintosh/i.test(ua) && window.navigator.maxTouchPoints > 1;
        return isAppleMobile || isTouchMac;
    }

    // Helper: Detect touch-capable devices for pinch gestures.
    // We only keep zoom enabled on touch input to avoid desktop
    // mouse/double-click zoom conflicts with fullscreen gestures.
    function hasTouchInput() {
        if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
            return true;
        }
        if ('ontouchstart' in window) {
            return true;
        }
        return (window.navigator.maxTouchPoints || 0) > 0;
    }

    // Old iOS/WebKit limits
    var OLD_IOS_MAX_PHOTOS = 25; // Keep galleries small on older iOS devices

    // Helper: Parse iOS major version from the user agent string.
    // Returns a number (e.g. 16) or null if not on iOS / not parsable.
    function getIosMajorVersion() {
        var ua = window.navigator.userAgent || '';
        var match = ua.match(/OS (\d+)_\d+(?:_\d+)? like Mac OS X/);
        if (match && match[1]) {
            var v = parseInt(match[1], 10);
            return isNaN(v) ? null : v;
        }
        return null;
    }

    // Helper: Detect "old" iOS/WebKit where large galleries are problematic.
    // Threshold 16 is chosen based on real-device behaviour; adjust if needed.
    function isOldIosWebkit() {
        if (!isIosDevice()) {
            return false;
        }
        var major = getIosMajorVersion();
        if (major == null) {
            return false;
        }
        return major <= 16;
    }

    // Helper: Detect iPhone/iPod (used for pseudo fullscreen fallback)
    function isIphone() {
        var ua = window.navigator.userAgent || '';
        return /iPhone|iPod/i.test(ua);
    }

    // Helper: Check if currently in fullscreen (native or pseudo)
    function isFullscreen(targetElement) {
        var nativeElement = document.fullscreenElement || document.webkitFullscreenElement ||
                  document.mozFullScreenElement || document.msFullscreenElement;

        if (nativeElement) {
            return targetElement ? nativeElement === targetElement : true;
        }

        if (targetElement) {
            return $(targetElement).hasClass('jzsa-pseudo-fullscreen');
        }

        return $('.jzsa-pseudo-fullscreen').length > 0;
    }

    // Helper: Enter/exit pseudo fullscreen (CSS-driven fallback for iPhone)
    // Paints page background black to blend with browser chrome (YouTube-like).
    var _savedHtmlBg = '';
    var _savedBodyBg = '';

    function enterPseudoFullscreen(element) {
        if (!element) {
            return false;
        }
        var $el = $(element);
        if ($el.hasClass('jzsa-pseudo-fullscreen')) {
            return true;
        }

        // Save scroll position for restoration on exit
        $el.data('jzsa-scroll-y', window.scrollY || window.pageYOffset);

        // Paint page background to match fullscreen so browser chrome blends in
        _savedHtmlBg = document.documentElement.style.backgroundColor;
        _savedBodyBg = document.body.style.backgroundColor;
        var fsBgRaw = $el.attr('data-fullscreen-background-color') ||
                      $el.attr('data-background-color') || '';
        var fsBg = (fsBgRaw && fsBgRaw !== 'transparent') ? fsBgRaw : '#000';
        document.documentElement.style.backgroundColor = fsBg;
        document.body.style.backgroundColor = fsBg;

        // Apply fullscreen background to the gallery element itself.
        // The CSS variable --gallery-bg-color may be 'transparent' for inline
        // mode; override it so the fixed container is fully opaque.
        $el.data('jzsa-pseudo-fs-original-bg', element.style.getPropertyValue('--gallery-bg-color'));
        element.style.setProperty('--gallery-bg-color', fsBg);

        $el.addClass('jzsa-pseudo-fullscreen jzsa-is-fullscreen');
        $('html, body').addClass('jzsa-no-scroll');
        return true;
    }

    function exitPseudoFullscreen(element) {
        if (!element) {
            return;
        }
        var $el = $(element);

        // Restore page background
        document.documentElement.style.backgroundColor = _savedHtmlBg;
        document.body.style.backgroundColor = _savedBodyBg;

        // Restore original --gallery-bg-color
        var originalBg = $el.data('jzsa-pseudo-fs-original-bg');
        if (originalBg !== undefined) {
            if (originalBg) {
                element.style.setProperty('--gallery-bg-color', originalBg);
            } else {
                element.style.removeProperty('--gallery-bg-color');
            }
            $el.removeData('jzsa-pseudo-fs-original-bg');
        }

        $el.removeClass('jzsa-pseudo-fullscreen jzsa-is-fullscreen');
        $('html, body').removeClass('jzsa-no-scroll');

        notifyGalleryOnFullscreenExit(element, swipers[element.id]);
    }

    // Helper: Restore page scroll and notify the gallery to navigate to the
    // last-viewed photo after any fullscreen exit (native or pseudo).
    // A single shared function ensures both exit paths behave identically
    // and cannot diverge.
    //
    // @param {Element} element  The slideshow DOM element that was in fullscreen.
    // @param {Object}  swiper   The Swiper instance for that element.
    function notifyGalleryOnFullscreenExit(element, swiper) {
        var $el = $(element);
        var savedY = $el.data('jzsa-scroll-y');

        // Only act if this element was the one that entered fullscreen.
        // savedY is set exclusively on FS entry; its absence means this
        // handler fired for a gallery that was never in fullscreen (e.g. a
        // second gallery on the same page whose fullscreenchange listener
        // fired as a bystander when another gallery exited).
        if (savedY == null) {
            return;
        }

        window.scrollTo(0, savedY);
        $el.removeData('jzsa-scroll-y');

        if ($el.hasClass('jzsa-gallery-slideshow') && swiper) {
            var currentIndex = (typeof swiper.realIndex === 'number') ? swiper.realIndex : swiper.activeIndex;
            var galleryId = element.id.replace(/-slideshow$/, '');
            $('#' + galleryId).trigger('jzsa:focus-index', [currentIndex]);
        }
    }

    // Helper: Check if click should be ignored (clicked on UI element)
    function shouldIgnoreClick(target) {
        return $(target).closest('.swiper-button-next, .swiper-button-prev, .swiper-button-fullscreen, .swiper-button-external-link, .swiper-button-download, .swiper-button-play-pause, .swiper-pagination, .plyr__controls, .plyr__control').length > 0;
    }

    // Helper: Read gallery mode data attributes.
    function readGalleryAttr($container, suffix) {
        return $container.attr('data-gallery-' + suffix);
    }

    // Helper: Read a boolean data attribute with fallback.
    function readBooleanDataAttr($container, attrName, fallback) {
        var value = $container.attr(attrName);
        if (value === undefined) {
            return !!fallback;
        }
        return value === 'true';
    }

    // Helper: Parse per-gallery download warning size from data attr (MB -> bytes).
    // Returns null when not specified, 0 to disable warning.
    function getDownloadWarningSizeBytes($container) {
        if (!$container || !$container.length) {
            return null;
        }

        var rawValue = $container.attr('data-download-size-warning');
        if (rawValue === undefined || rawValue === null || rawValue === '') {
            return null;
        }

        var parsedMb = parseInt(rawValue, 10);
        if (isNaN(parsedMb) || parsedMb < 0) {
            return null;
        }

        if (parsedMb === 0) {
            return 0;
        }

        return parsedMb * 1024 * 1024;
    }

    // Helper: Parse slideshow autoresume data attribute.
    function parseSlideshowAutoresumeAttr(rawValue, fallbackValue) {
        var fallback = (fallbackValue === undefined || fallbackValue === null || fallbackValue === '') ? 30 : fallbackValue;
        if (rawValue === undefined || rawValue === null || rawValue === '') {
            return fallback;
        }

        if (String(rawValue).toLowerCase() === 'disabled') {
            return 'disabled';
        }

        var parsed = parseInt(rawValue, 10);
        if (!isNaN(parsed) && parsed > 0) {
            return parsed;
        }

        return fallback;
    }

    // Helper: Write gallery mode data attributes.
    function writeGalleryAttr($container, suffix, value) {
        $container.attr('data-gallery-' + suffix, value);
    }

    // Helper: Double-tap detection for touch devices
    var DOUBLE_TAP_MAX_DELAY = 250; // ms - maximum time between taps to be considered a double-tap
    var lastTap = 0;
    function handleDoubleTap(e, callback) {
        var currentTime = new Date().getTime();
        var tapLength = currentTime - lastTap;

        if (tapLength < DOUBLE_TAP_MAX_DELAY && tapLength > 0) {
            e.preventDefault();
            callback(e);
            lastTap = 0;
        } else {
            lastTap = currentTime;
        }
    }

    // Trace tag for video auto-healing flow (use in browser console filter).
    var VIDEO_HEAL_TRACE_TAG = '[JZSA_HEAL_TRACE]';
    // Keep console trace focused strictly on heal attempts and outcomes.
    var VIDEO_HEAL_TRACE_EVENTS = {
        'heal-start': true,
        'heal-refresh-applied': true,
        'heal-refresh-failed': true,
        'heal-play-rejected': true,
        'playback-failed': true
    };

    function shortMediaUrl(url) {
        if (!url) {
            return '';
        }
        if (url.length <= 96) {
            return url;
        }
        return url.slice(0, 56) + '…' + url.slice(-24);
    }

    function traceVideoHeal(eventName, payload) {
        if (!VIDEO_HEAL_TRACE_EVENTS[eventName]) {
            return;
        }
        if (!window.console || typeof window.console.log !== 'function') {
            return;
        }
        try {
            console.log(VIDEO_HEAL_TRACE_TAG + ' ' + eventName, payload || {});
        } catch (e) {
            // Ignore logging failures.
        }
    }

    /**
     * Build the inner HTML for a video item (wrapper + video element).
     * Shared across player, carousel, and gallery modes.
     *
     * @param {Object} opts
     * @param {string} opts.src        Video source URL.
     * @param {number} [opts.mediaIndex] Stable index in the full album array.
     * @param {string} [opts.extraClass] Additional classes on the <video> element.
     * @param {string} [opts.style]      Inline style attribute value for the <video>.
     * @return {string} HTML string.
     */
    function buildVideoHtml(opts) {
        var src = opts.src || '';
        var poster = opts.poster || '';
        var extraClass = opts.extraClass ? ' ' + opts.extraClass : '';
        var styleAttr = opts.style ? ' style="' + opts.style + '"' : '';
        var posterAttr = poster ? ' poster="' + poster + '"' : '';
        var mediaIndexAttr = (typeof opts.mediaIndex === 'number' && opts.mediaIndex >= 0)
            ? ' data-jzsa-media-index="' + opts.mediaIndex + '"'
            : '';
        return '<div class="jzsa-video-wrapper">' +
            '<video' +
            ' src="' + src + '"' +
            posterAttr +
            ' playsinline preload="none" disablepictureinpicture referrerpolicy="no-referrer"' +
            ' class="jzsa-video-player' + extraClass + '"' +
            mediaIndexAttr +
            styleAttr +
            '></video>' +
            '</div>';
    }

    /**
     * Re-fetch fresh media URLs from Google Photos when video URLs expire (HTTP 400).
     * One in-flight request per album container; returns a promise.
     */
    var _refreshPromises = {};

    function applyFreshMediaToContainer($albumContainer, freshPhotos, options) {
        var opts = options || {};
        var triggerVideoEl = opts.triggerVideoEl || null;
        var reason = opts.reason || 'unknown';
        var forceTriggerReload = !!opts.forceTriggerReload;
        var freshVideos = freshPhotos.filter(function(p) { return p.type === 'video'; });
        var fallbackVideoCursor = 0;
        var triggerUpdated = false;
        var triggerSeenInMapping = false;

        // Update data attribute so future renders use fresh URLs.
        $albumContainer.attr('data-all-photos', JSON.stringify(freshPhotos));

        $albumContainer.find('video.jzsa-video-player').each(function() {
            var videoEl = this;
            var mediaIndex = parseInt(videoEl.getAttribute('data-jzsa-media-index') || '', 10);
            var freshEntry = null;

            if (!isNaN(mediaIndex) && mediaIndex >= 0 && mediaIndex < freshPhotos.length) {
                var candidate = freshPhotos[mediaIndex];
                if (candidate && candidate.type === 'video') {
                    freshEntry = candidate;
                }
            }
            if (!freshEntry && fallbackVideoCursor < freshVideos.length) {
                freshEntry = freshVideos[fallbackVideoCursor++];
            }
            if (!freshEntry || !freshEntry.video) {
                traceVideoHeal('refresh-map-skip', {
                    reason: reason,
                    containerId: $albumContainer.attr('id') || '',
                    mediaIndex: isNaN(mediaIndex) ? null : mediaIndex
                });
                return;
            }

            var oldSrc = videoEl.getAttribute('src') || videoEl.src || '';
            $(videoEl).attr('src', freshEntry.video);
            if (freshEntry.preview) {
                $(videoEl).attr('poster', freshEntry.preview);
            }
            var srcChanged = oldSrc !== freshEntry.video;

            var isTrigger = !!(triggerVideoEl && videoEl === triggerVideoEl);
            if (isTrigger) {
                triggerSeenInMapping = true;
            }
            traceVideoHeal('refresh-map', {
                reason: reason,
                containerId: $albumContainer.attr('id') || '',
                mediaIndex: isNaN(mediaIndex) ? null : mediaIndex,
                trigger: isTrigger,
                srcChanged: srcChanged,
                oldSrc: shortMediaUrl(oldSrc),
                newSrc: shortMediaUrl(freshEntry.video)
            });

            // Only force reload for the video that is currently recovering,
            // and only when the URL actually changed.
            if (isTrigger && (srcChanged || forceTriggerReload)) {
                triggerUpdated = true;
                videoEl.load();
            }
        });

        if (triggerVideoEl && !triggerUpdated && !triggerSeenInMapping && document.documentElement.contains(triggerVideoEl)) {
            traceVideoHeal('refresh-trigger-fallback-load', {
                reason: reason,
                containerId: $albumContainer.attr('id') || '',
                mediaIndex: triggerVideoEl.getAttribute('data-jzsa-media-index') || null
            });
            triggerVideoEl.load();
        }
    }

    function refreshAlbumUrls($albumContainer, options) {
        var opts = options || {};
        var reason = opts.reason || 'unknown';
        var triggerVideoEl = opts.triggerVideoEl || null;
        var forceNetwork = !!opts.forceNetwork;
        var forceTriggerReload = !!opts.forceTriggerReload;
        var shouldApply = typeof opts.shouldApply === 'function' ? opts.shouldApply : null;

        // Try the container itself, then walk up to find data-album-url
        // (e.g. gallery slideshow is a sibling of the gallery container).
        var albumUrl = $albumContainer.attr('data-album-url') || '';
        if (!albumUrl) {
            var $parent = $albumContainer.closest('[data-album-url]');
            if (!$parent.length) {
                $parent = $albumContainer.siblings('[data-album-url]');
            }
            albumUrl = $parent.attr('data-album-url') || '';
        }
        if (!albumUrl || typeof jzsaAjax === 'undefined') {
            traceVideoHeal('refresh-skip-missing-context', {
                reason: reason,
                containerId: $albumContainer.attr('id') || '',
                hasAlbumUrl: !!albumUrl,
                hasAjax: typeof jzsaAjax !== 'undefined'
            });
            return $.Deferred().reject().promise();
        }

        // Deduplicate network calls per album URL; each caller still applies
        // the fresh response to its own container.
        if (!forceNetwork && _refreshPromises[albumUrl]) {
            traceVideoHeal('refresh-reuse-inflight', {
                reason: reason,
                albumUrl: shortMediaUrl(albumUrl),
                containerId: $albumContainer.attr('id') || ''
            });
            return _refreshPromises[albumUrl].then(function(freshPhotos) {
                if (!shouldApply || shouldApply()) {
                    applyFreshMediaToContainer($albumContainer, freshPhotos, {
                        reason: reason,
                        triggerVideoEl: triggerVideoEl,
                        forceTriggerReload: forceTriggerReload
                    });
                }
                return freshPhotos;
            });
        }

        traceVideoHeal('refresh-fetch-start', {
            reason: reason,
            albumUrl: shortMediaUrl(albumUrl),
            containerId: $albumContainer.attr('id') || '',
            triggerMediaIndex: triggerVideoEl ? triggerVideoEl.getAttribute('data-jzsa-media-index') || null : null
        });

        var refreshRequest = $.ajax({
            url: jzsaAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jzsa_refresh_urls',
                nonce: jzsaAjax.refreshNonce,
                album_url: albumUrl,
                force_refresh: forceNetwork ? 1 : 0
            }
        }).then(function(response) {
            if (!response.success || !response.data || !response.data.photos) {
                traceVideoHeal('refresh-fetch-invalid-response', {
                    reason: reason,
                    albumUrl: shortMediaUrl(albumUrl),
                    containerId: $albumContainer.attr('id') || ''
                });
                return $.Deferred().reject().promise();
            }
            traceVideoHeal('refresh-fetch-success', {
                reason: reason,
                albumUrl: shortMediaUrl(albumUrl),
                containerId: $albumContainer.attr('id') || '',
                mediaCount: response.data.photos.length
            });
            return response.data.photos;
        }).fail(function(jqXHR, textStatus, errorThrown) {
            traceVideoHeal('refresh-fetch-fail', {
                reason: reason,
                albumUrl: shortMediaUrl(albumUrl),
                containerId: $albumContainer.attr('id') || '',
                status: jqXHR && typeof jqXHR.status === 'number' ? jqXHR.status : null,
                textStatus: textStatus || '',
                error: errorThrown || ''
            });
        }).always(function() {
            if (_refreshPromises[albumUrl] === refreshRequest) {
                delete _refreshPromises[albumUrl];
            }
        });
        _refreshPromises[albumUrl] = refreshRequest;

        return refreshRequest.then(function(freshPhotos) {
            if (!shouldApply || shouldApply()) {
                applyFreshMediaToContainer($albumContainer, freshPhotos, {
                    reason: reason,
                    triggerVideoEl: triggerVideoEl,
                    forceTriggerReload: forceTriggerReload
                });
            }
            return freshPhotos;
        });
    }

    /**
     * Initialise Plyr on all uninitialised .jzsa-video-player elements
     * inside the given container.
     *
     * @param {jQuery} $container Parent element to search within.
     */
    function initPlyrInContainer($container) {
        if (typeof Plyr === 'undefined') {
            // console.warn('⚠️ Plyr not loaded');
            return;
        }
        var $album = $container.closest('.jzsa-album, .jzsa-gallery');
        var autohide = $album.length ? $album.attr('data-video-controls-autohide') === 'true' : false;
        var videos = $container.find('video.jzsa-video-player');
        // console.log('🎬 initPlyrInContainer: found', videos.length, 'videos');
        videos.each(function() {
            if (this._jzsaPlyr) {
                return;
            }
            var wrapper = $(this).closest('.jzsa-video-wrapper')[0];
            this._jzsaPlyr = new Plyr(this, {
                iconUrl: (typeof jzsaAjax !== 'undefined' && jzsaAjax.plyrSvgUrl) || '',
                controls: ['play-large', 'play', 'restart', 'progress', 'current-time', 'duration', 'mute', 'volume'],
                clickToPlay: false,
                hideControls: autohide,
                resetOnEnd: true,
                invertTime: false,
                disableContextMenu: false,
                fullscreen: { enabled: false }
            });
            // Hide bottom control bar until playback starts
            var plyrContainer = $(this).closest('.jzsa-video-wrapper').find('.plyr__controls');
            if (plyrContainer.length) {
                plyrContainer.hide();
            }
            var $wrapper = $(wrapper);
            var $albumContainer = $wrapper.closest('.jzsa-album, .jzsa-gallery-album');
            var $playLarge = $wrapper.find('.plyr__control--overlaid');

            // When the video is playing, intercept clicks on the overlaid
            // button to pause instead of plyr's default (re-play).
            // Capture phase ensures this fires before plyr's own handler.
            $playLarge[0].addEventListener('click', function(e) {
                if (plyrRef && plyrRef.playing) {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    plyrRef.pause();
                }
            }, true);

            // Loading state + auto-heal finite-state machine.
            var LOADING_TIMEOUT_MS = 30000;
            var STALL_WATCHDOG_MS = 5000;
            var MAX_AUTO_HEAL_ATTEMPTS = 2;
            var HEAL_RETRY_DELAY_MS = 50;

            var VIDEO_STATE_IDLE = 'idle';
            var VIDEO_STATE_STARTING = 'starting';
            var VIDEO_STATE_HEALING = 'healing';
            var VIDEO_STATE_PLAYING = 'playing';
            var VIDEO_STATE_FAILED = 'failed';

            var loadingTimeout = null;
            var stallWatchdogTimer = null;
            var playbackState = VIDEO_STATE_IDLE;
            // Play button starts in default triangle state - allow duration badge to show.
            $wrapper.addClass('jzsa-show-duration');
            var autoHealAttempts = 0;
            var healRunToken = 0;
            var plyrRef = this._jzsaPlyr;
            var videoEl = this;

            function isStartingState() {
                return playbackState === VIDEO_STATE_STARTING || playbackState === VIDEO_STATE_HEALING;
            }

            function isPlaybackActive() {
                return isStartingState() || playbackState === VIDEO_STATE_PLAYING;
            }

            function setPlaybackState(nextState) {
                playbackState = nextState;
                // Duration label must only be visible when play button shows its default triangle.
                if (nextState === VIDEO_STATE_IDLE && !$playLarge.hasClass('jzsa-plyr-error')) {
                    $wrapper.addClass('jzsa-show-duration');
                } else {
                    $wrapper.removeClass('jzsa-show-duration');
                }
            }

            function trace(eventName, extra) {
                traceVideoHeal(eventName, $.extend({
                    containerId: $albumContainer.attr('id') || '',
                    mediaIndex: videoEl.getAttribute('data-jzsa-media-index') || null,
                    state: playbackState,
                    waitingForPlay: isStartingState(),
                    healingInProgress: playbackState === VIDEO_STATE_HEALING,
                    playing: playbackState === VIDEO_STATE_PLAYING || !!(plyrRef && plyrRef.playing),
                    attempts: autoHealAttempts,
                    currentTime: (typeof videoEl.currentTime === 'number' ? videoEl.currentTime : null),
                    src: shortMediaUrl(videoEl.currentSrc || videoEl.getAttribute('src') || '')
                }, extra || {}));
            }

            function clearLoadingTimeout() {
                if (loadingTimeout) {
                    clearTimeout(loadingTimeout);
                    loadingTimeout = null;
                }
            }

            function clearStallWatchdog() {
                if (stallWatchdogTimer) {
                    clearTimeout(stallWatchdogTimer);
                    stallWatchdogTimer = null;
                }
            }

            function clearRecoveryTimers() {
                clearLoadingTimeout();
                clearStallWatchdog();
            }

            function showLoadingUi() {
                $playLarge.addClass('jzsa-plyr-loading');
                $wrapper.addClass('jzsa-video-loading');
            }

            function clearLoadingUi() {
                $playLarge.removeClass('jzsa-plyr-loading');
                $wrapper.removeClass('jzsa-video-loading');
            }

            function armLoadingTimeout(source) {
                clearLoadingTimeout();
                if (!isStartingState()) {
                    return;
                }
                loadingTimeout = setTimeout(function() {
                    if (!isStartingState()) {
                        return;
                    }
                    trace('loading-timeout', {
                        source: source,
                        timeoutMs: LOADING_TIMEOUT_MS,
                        readyState: videoEl.readyState,
                        networkState: videoEl.networkState
                    });
                    if (autoHealAttempts < MAX_AUTO_HEAL_ATTEMPTS) {
                        runRecovery('loading-timeout', 'soft');
                        return;
                    }
                    failPlayback('loading-timeout');
                }, LOADING_TIMEOUT_MS);
            }

            function armStallWatchdog(source) {
                clearStallWatchdog();
                if (!isPlaybackActive()) {
                    return;
                }
                stallWatchdogTimer = setTimeout(function() {
                    if (!isPlaybackActive() || playbackState === VIDEO_STATE_PLAYING) {
                        return;
                    }
                    trace('stall-watchdog-fired', {
                        source: source,
                        timeoutMs: STALL_WATCHDOG_MS,
                        readyState: videoEl.readyState,
                        networkState: videoEl.networkState
                    });
                    runRecovery('stall-watchdog', 'soft');
                }, STALL_WATCHDOG_MS);
            }

            function failPlayback(reason, extra) {
                healRunToken++;
                setPlaybackState(VIDEO_STATE_FAILED);
                clearRecoveryTimers();
                clearLoadingUi();
                $playLarge.addClass('jzsa-plyr-error');
                trace('playback-failed', $.extend({ reason: reason }, extra || {}));
                if (!plyrRef.playing) {
                    videoEl._jzsaSuppressNextError = true;
                    try { plyrRef.stop(); } catch (e) { /* ignore */ }
                }
            }

            function runRecovery(reason, mode) {
                var isHard = mode === 'hard';

                if (!isPlaybackActive()) {
                    trace('heal-skip-not-active', { reason: reason, mode: mode });
                    return;
                }
                if (playbackState === VIDEO_STATE_HEALING && !isHard) {
                    var canOverrideInflight =
                        reason === 'loading-timeout' ||
                        reason === 'stall-watchdog';
                    if (!canOverrideInflight) {
                        trace('heal-skip-inflight', { reason: reason, mode: mode });
                        return;
                    }
                    // A previous heal run appears stuck (e.g. unresolved play promise).
                    // Invalidate it and start a fresh recovery attempt.
                    healRunToken++;
                    setPlaybackState(VIDEO_STATE_STARTING);
                    trace('heal-override-stuck-inflight', { reason: reason, mode: mode });
                }
                if (isHard) {
                    autoHealAttempts = 0;
                }
                if (autoHealAttempts >= MAX_AUTO_HEAL_ATTEMPTS) {
                    failPlayback('max-attempts-reached', { trigger: reason });
                    return;
                }

                var runToken = ++healRunToken;
                autoHealAttempts++;
                setPlaybackState(VIDEO_STATE_HEALING);
                showLoadingUi();
                trace('heal-start', { reason: reason, mode: mode, attempt: autoHealAttempts });
                armLoadingTimeout('heal-start');
                armStallWatchdog('heal-start');

                refreshAlbumUrls($albumContainer, {
                    reason: 'auto-heal:' + reason,
                    triggerVideoEl: videoEl,
                    forceNetwork: isHard,
                    forceTriggerReload: isHard,
                    shouldApply: function() {
                        return runToken === healRunToken;
                    }
                }).then(function() {
                    if (runToken !== healRunToken) {
                        return;
                    }
                    trace('heal-refresh-applied', { reason: reason, attempt: autoHealAttempts });
                    var playPromise;
                    try {
                        playPromise = plyrRef.play();
                    } catch (err) {
                        if (runToken !== healRunToken) {
                            return;
                        }
                        setPlaybackState(VIDEO_STATE_STARTING);
                        trace('heal-play-rejected', {
                            reason: reason,
                            attempt: autoHealAttempts,
                            error: err && err.message ? err.message : String(err || '')
                        });
                        if (autoHealAttempts >= MAX_AUTO_HEAL_ATTEMPTS) {
                            failPlayback('play-rejected-after-heal', { trigger: reason });
                            return;
                        }
                        setTimeout(function() {
                            if (runToken !== healRunToken) {
                                return;
                            }
                            runRecovery('play-rejected', 'soft');
                        }, HEAL_RETRY_DELAY_MS);
                        return;
                    }

                    armStallWatchdog('post-heal-play');

                    if (playPromise && typeof playPromise.then === 'function') {
                        playPromise.then(function() {
                            if (runToken !== healRunToken) {
                                return;
                            }
                            if (playbackState === VIDEO_STATE_HEALING) {
                                setPlaybackState(VIDEO_STATE_STARTING);
                            }
                        }).catch(function(err) {
                            if (runToken !== healRunToken) {
                                return;
                            }
                            setPlaybackState(VIDEO_STATE_STARTING);
                            trace('heal-play-rejected', {
                                reason: reason,
                                attempt: autoHealAttempts,
                                error: err && err.message ? err.message : String(err || '')
                            });
                            if (autoHealAttempts >= MAX_AUTO_HEAL_ATTEMPTS) {
                                failPlayback('play-rejected-after-heal', { trigger: reason });
                                return;
                            }
                            setTimeout(function() {
                                if (runToken !== healRunToken) {
                                    return;
                                }
                                runRecovery('play-rejected', 'soft');
                            }, HEAL_RETRY_DELAY_MS);
                        });
                        return;
                    }

                    // Browsers without play() promise support.
                    setTimeout(function() {
                        if (runToken !== healRunToken) {
                            return;
                        }
                        if (playbackState === VIDEO_STATE_HEALING) {
                            setPlaybackState(VIDEO_STATE_STARTING);
                        }
                    }, 800);
                }).fail(function() {
                    if (runToken !== healRunToken) {
                        return;
                    }
                    setPlaybackState(VIDEO_STATE_STARTING);
                    trace('heal-refresh-failed', { reason: reason, attempt: autoHealAttempts });
                    if (autoHealAttempts >= MAX_AUTO_HEAL_ATTEMPTS) {
                        failPlayback('refresh-failed', { trigger: reason });
                        return;
                    }
                    setTimeout(function() {
                        if (runToken !== healRunToken) {
                            return;
                        }
                        runRecovery('refresh-failed-retry', 'soft');
                    }, 250);
                });
            }

            // Expose a cancel function so other videos can abort our buffering
            videoEl._jzsaCancelLoading = function() {
                if (!isStartingState()) { return; }
                trace('cancel-loading');
                healRunToken++;
                setPlaybackState(VIDEO_STATE_IDLE);
                autoHealAttempts = 0;
                clearRecoveryTimers();
                clearLoadingUi();
                videoEl._jzsaSuppressNextError = true;
                plyrRef.stop();
            };

            function pauseAllPageVideos() {
                document.querySelectorAll('video.jzsa-video-player').forEach(function(v) {
                    if (v === videoEl) { return; }
                    // Cancel buffering if still loading
                    if (v._jzsaCancelLoading) { v._jzsaCancelLoading(); }
                    // Stop (reset to start) if already playing
                    if (v._jzsaPlyr && v._jzsaPlyr.playing) {
                        v._jzsaSuppressNextError = true;
                        v._jzsaPlyr.stop();
                    }
                });
            }

            function showPlaying() {
                healRunToken++;
                setPlaybackState(VIDEO_STATE_PLAYING);
                autoHealAttempts = 0;
                clearRecoveryTimers();
                clearLoadingUi();
                $playLarge.removeClass('jzsa-plyr-error');
                plyrContainer.show();
                $albumContainer.addClass('jzsa-video-playing');
                trace('playback-confirmed');
            }

            function requestPlaybackStart(source) {
                var playPromise;
                try {
                    playPromise = plyrRef.play();
                } catch (err) {
                    if (!isStartingState()) {
                        return;
                    }
                    trace('play-start-threw', {
                        source: source,
                        error: err && err.message ? err.message : String(err || '')
                    });
                    runRecovery('play-start-threw', 'soft');
                    return;
                }
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function(err) {
                        if (!isStartingState()) {
                            return;
                        }
                        trace('play-start-rejected', {
                            source: source,
                            error: err && err.message ? err.message : String(err || '')
                        });
                        runRecovery('play-start-rejected', 'soft');
                    });
                }
            }

            $playLarge.on('click', function() {
                if (isStartingState()) {
                    trace('play-click-hard-retry');
                    runRecovery('user-click-hard-retry', 'hard');
                    armLoadingTimeout('user-click-hard-retry');
                    return;
                }
                // Clear any previous error state
                $playLarge.removeClass('jzsa-plyr-error');
                // Stop all other videos immediately on click
                pauseAllPageVideos();
                setPlaybackState(VIDEO_STATE_STARTING);
                autoHealAttempts = 0;
                showLoadingUi();
                trace('play-click');

                // 30s timeout - show error state if playback never starts
                armLoadingTimeout('initial-click');

                armStallWatchdog('initial-click');
                requestPlaybackStart('play-click');
            });

            // timeupdate fires when frames are actively rendering.
            // Wait until currentTime > 0.1s to be sure a real frame has
            // painted over the poster (and its baked-in triangle).
            this._jzsaPlyr.on('playing', function() {
                if (isStartingState() || !$albumContainer.hasClass('jzsa-video-playing')) {
                    showPlaying();
                }
            });
            this._jzsaPlyr.on('timeupdate', function() {
                if (!isStartingState()) { return; }
                if (plyrRef.currentTime > 0.1) {
                    showPlaying();
                }
            });

            videoEl.addEventListener('waiting', function() {
                if (!isPlaybackActive() && !plyrRef.playing) {
                    return;
                }
                trace('native-waiting', {
                    readyState: videoEl.readyState,
                    networkState: videoEl.networkState
                });
                armStallWatchdog('native-waiting');
            });

            videoEl.addEventListener('stalled', function() {
                if (!isPlaybackActive() && !plyrRef.playing) {
                    return;
                }
                trace('native-stalled', {
                    readyState: videoEl.readyState,
                    networkState: videoEl.networkState
                });
                armStallWatchdog('native-stalled');
            });

            this._jzsaPlyr.on('pause', function() {
                healRunToken++;
                setPlaybackState(VIDEO_STATE_IDLE);
                autoHealAttempts = 0;
                clearRecoveryTimers();
                clearLoadingUi();
                plyrContainer.hide();
                $albumContainer.removeClass('jzsa-video-playing');
                $albumContainer.trigger('jzsa:video-stopped');
                trace('plyr-pause');
            });
            this._jzsaPlyr.on('ended', function() {
                healRunToken++;
                setPlaybackState(VIDEO_STATE_IDLE);
                autoHealAttempts = 0;
                clearRecoveryTimers();
                clearLoadingUi();
                plyrContainer.hide();
                $albumContainer.removeClass('jzsa-video-playing');
                $albumContainer.trigger('jzsa:video-stopped');
                trace('plyr-ended');
            });

            // If the video URL has expired (HTTP 400), re-fetch fresh URLs
            // from Google Photos and retry.
            this._jzsaPlyr.on('error', function() {
                if (videoEl._jzsaSuppressNextError) {
                    videoEl._jzsaSuppressNextError = false;
                    trace('plyr-error-suppressed');
                    return;
                }

                trace('plyr-error', {
                    readyState: videoEl.readyState,
                    networkState: videoEl.networkState
                });

                // Some failures drop playing=false before the error callback
                // fires. Recover if playback had already progressed.
                var hadPlaybackProgress =
                    typeof videoEl.currentTime === 'number' &&
                    videoEl.currentTime > 0.1 &&
                    !videoEl.ended;
                var shouldRecover = isStartingState() || playbackState === VIDEO_STATE_PLAYING || hadPlaybackProgress;
                if (!shouldRecover) {
                    trace('plyr-error-ignored-inactive');
                    return;
                }
                if (playbackState === VIDEO_STATE_IDLE && hadPlaybackProgress) {
                    setPlaybackState(VIDEO_STATE_STARTING);
                }
                runRecovery('plyr-error', 'soft');
            });
        });
    }

    /**
     * Destroy any Plyr instances inside the given container.
     *
     * @param {jQuery} $container Parent element to search within.
     */
    function destroyPlyrInContainer($container) {
        $container.find('video.jzsa-video-player').each(function() {
            if (this._jzsaPlyr) {
                try { this._jzsaPlyr.destroy(); } catch (e) { /* ignore */ }
                this._jzsaPlyr = null;
            }
        });
    }

    // ========================================================================
    // Video duration prefetch
    // ========================================================================

    /**
     * Lightweight metadata-only prefetch: probes each video to discover its
     * duration (a few KB per video) and displays a duration badge on the
     * video wrapper. No full video data is downloaded. Durations are cached
     * so they persist across DOM rebuilds (e.g. fullscreen toggle).
     */
    var durationCache = {};      // src -> formatted duration text
    var durationQueue = [];      // pending src values
    var durationQueued = {};     // src -> true when queued
    var durationInFlight = {};   // src -> true when probe is running
    var durationPrefetchActive = 0;
    var durationPrefetchScheduled = false;
    var DURATION_PREFETCH_WORKERS = 3;

    function getVideoSrc(videoEl) {
        return videoEl.currentSrc || videoEl.src || videoEl.getAttribute('src') || '';
    }

    function formatDurationText(seconds) {
        var mins = Math.floor(seconds / 60);
        var secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    function applyDurationLabelToVideo(videoEl, durationText) {
        if (!durationText) {
            return;
        }
        var $wrapper = $(videoEl).closest('.jzsa-video-wrapper');
        if (!$wrapper.length) {
            return;
        }
        var $badge = $wrapper.children('.jzsa-video-duration');
        if ($badge.length) {
            $badge.text(durationText);
            return;
        }
        $wrapper.append('<span class="jzsa-video-duration">' + durationText + '</span>');
    }

    /** Apply cached durations to matching video wrappers in scope. */
    function applyDurationLabels($scope) {
        var $videos = $scope ? $scope.find('video.jzsa-video-player') : $('video.jzsa-video-player');
        $videos.each(function() {
            var src = getVideoSrc(this);
            if (!src || !durationCache[src]) {
                return;
            }
            applyDurationLabelToVideo(this, durationCache[src]);
        });
    }

    function queueDurationPrefetch(src) {
        if (!src || durationCache[src] || durationInFlight[src] || durationQueued[src]) {
            return;
        }
        durationQueued[src] = true;
        durationQueue.push(src);
    }

    function processDurationPrefetchQueue() {
        while (durationPrefetchActive < DURATION_PREFETCH_WORKERS && durationQueue.length) {
            var src = durationQueue.shift();
            delete durationQueued[src];
            if (!src || durationCache[src] || durationInFlight[src]) {
                continue;
            }

            durationPrefetchActive++;
            durationInFlight[src] = true;

            (function(videoSrc) {
                var probe = document.createElement('video');
                probe.preload = 'metadata';
                probe.referrerPolicy = 'no-referrer';
                var done = false;

                function cleanup() {
                    if (done) {
                        return;
                    }
                    done = true;
                    probe.onloadedmetadata = null;
                    probe.onerror = null;
                    probe.src = '';
                    probe = null;
                    delete durationInFlight[videoSrc];
                    durationPrefetchActive = Math.max(0, durationPrefetchActive - 1);
                    if (durationCache[videoSrc]) {
                        applyDurationLabels();
                    }
                    processDurationPrefetchQueue();
                }

                probe.onloadedmetadata = function() {
                    if (isFinite(probe.duration) && probe.duration > 0) {
                        durationCache[videoSrc] = formatDurationText(probe.duration);
                    }
                    cleanup();
                };
                probe.onerror = function() {
                    cleanup();
                };
                probe.src = videoSrc;
            })(src);
        }
    }

    function scheduleDurationPrefetch($scope) {
        applyDurationLabels($scope);

        var $videos = $scope ? $scope.find('video.jzsa-video-player') : $('video.jzsa-video-player');
        $videos.each(function() {
            queueDurationPrefetch(getVideoSrc(this));
        });

        if (!durationQueue.length) {
            return;
        }

        if (durationPrefetchScheduled) {
            return;
        }
        durationPrefetchScheduled = true;

        var schedule = window.requestIdleCallback || function(cb) { setTimeout(cb, 500); };
        schedule(function() {
            durationPrefetchScheduled = false;
            processDurationPrefetchQueue();
        });
    }

    // Helper: Format a timestamp (ms since epoch) as a short locale date string.
    function formatPhotoDate(timestampMs) {
        if (!timestampMs) {
            return '';
        }
        try {
            var d = new Date(parseInt(timestampMs, 10));
            if (isNaN(d.getTime())) {
                return '';
            }
            return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        } catch (e) {
            return '';
        }
    }

    // Helper: Escape HTML for safe attribute/content insertion.
    function escapeHtml(text) {
        if (!text) {
            return '';
        }
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ========================================================================
    // Info box system - resolve placeholder format strings per photo
    // ========================================================================

    // info-top/info-top-secondary stack top-to-bottom; info-bottom is the Swiper pagination pill.
    var INFO_BOX_NAMES = [
        'info-top',
        'info-top-secondary',
        'info-bottom'
    ];
    var SAFE_INFO_TOP_ORDER = [
        'info-top',
        'info-top-secondary'
    ];
    // info-bottom is the Swiper pagination pill - handled separately outside the stack.
    var SAFE_INFO_BOTTOM_ORDER = [];
    var infoMeasureCanvas = null;
    var DEFAULT_INFO_FONT_FAMILY = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

    /**
     * Resolve a format string by replacing placeholders like {date} with photo data.
     * Placeholders that resolve to empty are removed along with adjacent separators.
     *
     * @param {string} format  Format string (e.g. "{name} · {date}").
     * @param {Object} photo   Photo data object.
     * @param {Object} context Optional extra placeholder context.
     * @return {string} Resolved string, or empty if all placeholders resolved to nothing.
     */
    function resolveInfoPlaceholders(format, photo, context) {
        if (!format || !photo) {
            return '';
        }
        var placeholderContext = context || {};

        var name = photo.filename
            ? photo.filename.replace(/\.[^.]+$/, '')
            : '';

        var dims = '';
        if (photo.width && photo.height) {
            dims = photo.width + '\u00d7' + photo.height;
        }

        var mp = '';
        if (photo.width && photo.height) {
            var pixels = parseInt(photo.width, 10) * parseInt(photo.height, 10);
            if (pixels > 0) {
                mp = (pixels / 1000000).toFixed(1) + ' MP';
            }
        }

        var fs = '';
        if (photo.filesize) {
            var bytes = parseInt(photo.filesize, 10);
            if (bytes >= 1048576) {
                fs = (bytes / 1048576).toFixed(1) + ' MB';
            } else if (bytes >= 1024) {
                fs = Math.round(bytes / 1024) + ' KB';
            } else if (bytes > 0) {
                fs = bytes + ' B';
            }
        }

        var placeholders = {
            '{item}': placeholderContext.item || '',
            '{items}': placeholderContext.items || '',
            '{page}': placeholderContext.page || '',
            '{pages}': placeholderContext.pages || '',
            '{album-title}': placeholderContext.albumTitle || '',
            '{album-name}': placeholderContext.albumTitle || '',
            '{name}': name || '',
            '{filename}': photo.filename || '',
            '{date}': photo.timestamp ? formatPhotoDate(photo.timestamp) : '',
            '{author}': '',
            '{camera}': photo.camera || '',
            '{aperture}': photo.aperture || '',
            '{shutter}': photo.shutter || '',
            '{focal}': photo.focal || '',
            '{iso}': photo.iso || '',
            '{dimensions}': dims,
            '{megapixels}': mp,
            '{filesize}': fs
        };

        var result = format;
        for (var placeholder in placeholders) {
            if (placeholders.hasOwnProperty(placeholder)) {
                result = result.replace(new RegExp(placeholder.replace(/[{}]/g, '\\$&'), 'g'), placeholders[placeholder]);
            }
        }

        // Clean up separators around removed placeholders: collapse runs of
        // whitespace + separator + whitespace into nothing when adjacent
        // to the start/end or another separator.
        result = result.replace(/^[\s·•|—–-]+/, '').replace(/[\s·•|—–-]+$/, '');
        // Collapse internal double-separators (e.g. "foo ·  · bar" → "foo · bar").
        result = result.replace(/([·•|—–-])\s*([·•|—–-])/g, '$1');
        result = result.replace(/\s{2,}/g, ' ').trim();

        return result;
    }

    /**
     * Read info box format strings from a container's data attributes.
     *
     * @param {jQuery} $container Gallery container element.
     * @return {Object} { inline: { 'info-top': '...', ... }, fullscreen: { ... } }
     */
    function readInfoZoneFormats($container) {
        var inline = {};
        var fullscreen = {};
        for (var i = 0; i < INFO_BOX_NAMES.length; i++) {
            var zone = INFO_BOX_NAMES[i];
            inline[zone] = $container.attr('data-' + zone) || '';
            fullscreen[zone] = $container.attr('data-fullscreen-' + zone) || inline[zone];
        }
        return { inline: inline, fullscreen: fullscreen };
    }

    function getInfoZoneFormat(zoneFormats, zone, fullscreen) {
        if (!zoneFormats) {
            return '';
        }
        return fullscreen
            ? ((zoneFormats.fullscreen && zoneFormats.fullscreen[zone]) || (zoneFormats.inline && zoneFormats.inline[zone]) || '')
            : ((zoneFormats.inline && zoneFormats.inline[zone]) || '');
    }

    function buildInfoBoxHtml(zone, inlineText, fullscreenText) {
        return '<div class="jzsa-info-box jzsa-' + zone + '"' +
            ' data-inline="' + escapeHtml(inlineText) + '"' +
            ' data-fullscreen="' + escapeHtml(fullscreenText) + '"' +
            (inlineText ? '' : ' style="display:none"') + '>' +
            escapeHtml(inlineText) + '</div>';
    }

    function buildInfoStackHtml(order, zoneFormats, context, photo) {
        var html = '';
        for (var i = 0; i < order.length; i++) {
            var zone = order[i];
            var inlineFormat = getInfoZoneFormat(zoneFormats, zone, false);
            var fullscreenFormat = getInfoZoneFormat(zoneFormats, zone, true);
            if (!inlineFormat && !fullscreenFormat) {
                continue;
            }
            html += buildInfoBoxHtml(
                zone,
                resolveInfoPlaceholders(inlineFormat, photo, context),
                resolveInfoPlaceholders(fullscreenFormat, photo, context)
            );
        }
        return html;
    }

    /**
     * Build info box overlay HTML for a single slide/thumbnail.
     *
     * @param {Object} photo       Photo data object.
     * @param {Object} zoneFormats Inline zone formats.
     * @param {Object} fsFormats   Fullscreen zone formats.
     * @return {string} HTML string with grouped zone divs.
     */
    // In gallery thumbnails info-bottom renders as a regular info box (no Swiper pill).
    var GALLERY_INFO_BOTTOM_ORDER = ['info-bottom'];

    function buildInfoZoneHtml(photo, zoneFormats, fsFormats, context, options) {
        var opts = options || {};
        var bottomOrder = opts.bottomOrder || SAFE_INFO_BOTTOM_ORDER;
        var mergedZoneFormats = {
            inline: zoneFormats || {},
            fullscreen: fsFormats || {}
        };
        var topHtml = buildInfoStackHtml(SAFE_INFO_TOP_ORDER, mergedZoneFormats, context, photo);
        var bottomHtml = buildInfoStackHtml(bottomOrder, mergedZoneFormats, context, photo);
        return (topHtml ? '<div class="jzsa-info-stack jzsa-info-stack-top">' + topHtml + '</div>' : '') +
            (bottomHtml ? '<div class="jzsa-info-stack jzsa-info-stack-bottom">' + bottomHtml + '</div>' : '');
    }

    function getInfoMeasureContext(fontSizePx, fontFamily) {
        if (!infoMeasureCanvas) {
            infoMeasureCanvas = document.createElement('canvas');
        }
        var ctx = infoMeasureCanvas.getContext('2d');
        ctx.font = fontSizePx + 'px ' + (fontFamily || DEFAULT_INFO_FONT_FAMILY);
        return ctx;
    }

    function estimateInfoBoxWidth(text, fontSizePx, fontFamily) {
        if (!text) {
            return 0;
        }
        var ctx = getInfoMeasureContext(fontSizePx, fontFamily);
        return Math.ceil(ctx.measureText(text).width) + 24;
    }

    function getInfoFontSizePx($container, attrName, fallbackPx) {
        var attrValue = parseInt($container.attr(attrName || 'data-info-font-size'), 10);
        if (!isNaN(attrValue) && attrValue > 0) {
            return attrValue;
        }
        return (typeof fallbackPx === 'number' && fallbackPx > 0) ? fallbackPx : 12;
    }

    function getInfoFontFamily($container, attrName, fallbackFamily) {
        var attrValue = ($container.attr(attrName || 'data-info-font-family') || '').trim();
        if (attrValue) {
            return attrValue;
        }
        return (typeof fallbackFamily === 'string') ? fallbackFamily : '';
    }

    // ========================================================================
    // Wave 2: Background photo metadata prefetch via AJAX
    // ========================================================================

    var EXIF_PLACEHOLDER_RE = /\{(?:camera|aperture|shutter|focal|iso)\}/;
    var FILENAME_PLACEHOLDER_RE = /\{(?:filename|name)\}/;
    var EXIF_PREFETCH_WORKERS = 3;
    var exifCache = {};          // mediaId -> { camera, aperture, shutter, focal, iso, filename }
    var exifQueue = [];          // { mediaId, photoUrl }
    var exifQueued = {};         // mediaId -> true
    var exifInFlight = {};       // mediaId -> true
    var exifWaiters = {};        // mediaId -> [ { $container, zoneFormats, photoIndex } ]
    var exifPrefetchActive = 0;
    var exifPrefetchScheduled = false;

    /**
     * Check whether any zone format string references Wave 2 metadata placeholders.
     */
    function getPhotoMetaNeeds(zoneFormats) {
        if (!zoneFormats) {
            return { exif: false, filename: false };
        }
        var all = zoneFormats.inline || {};
        var fs  = zoneFormats.fullscreen || {};
        var needs = {
            exif: false,
            filename: false
        };
        for (var i = 0; i < INFO_BOX_NAMES.length; i++) {
            var zone = INFO_BOX_NAMES[i];
            needs.exif = needs.exif || EXIF_PLACEHOLDER_RE.test(all[zone] || '') || EXIF_PLACEHOLDER_RE.test(fs[zone] || '');
            needs.filename = needs.filename || FILENAME_PLACEHOLDER_RE.test(all[zone] || '') || FILENAME_PLACEHOLDER_RE.test(fs[zone] || '');
        }
        return needs;
    }

    /**
     * Build the individual photo page URL from the album URL and media ID.
     */
    function buildPhotoPageUrl(albumUrl, mediaId) {
        if (!albumUrl || !mediaId) {
            return '';
        }
        var match = albumUrl.match(/\/share\/([^?]+)\?key=([^&"]+)/);
        if (!match) {
            return '';
        }
        return 'https://photos.google.com/share/' + match[1] + '/photo/' + mediaId + '?key=' + match[2];
    }

    /**
     * After EXIF arrives for a photo, merge into the allPhotos array and
     * trigger a container-level info box refresh.
     */
    function applyExifToPhoto(mediaId, exifData, $container, zoneFormats, photoIndex) {
        if (!exifData) {
            return;
        }

        // Merge into allPhotos JSON so future slide rebuilds have EXIF.
        var photosJson = $container.attr('data-all-photos');
        if (photosJson) {
            try {
                var photos = JSON.parse(photosJson);
                if (photos[photoIndex]) {
                    var p = photos[photoIndex];
                    var changed = false;
                    var fields = ['camera', 'aperture', 'shutter', 'focal', 'iso', 'filename'];
                    for (var f = 0; f < fields.length; f++) {
                        if (exifData[fields[f]]) {
                            p[fields[f]] = exifData[fields[f]];
                            changed = true;
                        }
                    }
                    if (changed) {
                        $container.attr('data-all-photos', JSON.stringify(photos));
                        // Trigger container-level info box refresh.
                        // Info boxes are container-level (not per-slide), so we
                        // fire a custom event that updateAllInfoBoxes listens on.
                        $container.trigger('jzsa:exif-update');
                    }
                }
            } catch (e) { /* ignore */ }
        }
    }

    function addExifWaiter(mediaId, $container, zoneFormats, photoIndex) {
        if (!mediaId || !$container || !$container.length) {
            return;
        }

        if (!exifWaiters[mediaId]) {
            exifWaiters[mediaId] = [];
        }

        exifWaiters[mediaId].push({
            $container: $container,
            zoneFormats: zoneFormats,
            photoIndex: photoIndex
        });
    }

    function flushExifWaiters(mediaId, exifData) {
        var waiters = exifWaiters[mediaId] || [];
        delete exifWaiters[mediaId];

        if (!exifData) {
            return;
        }

        for (var i = 0; i < waiters.length; i++) {
            applyExifToPhoto(mediaId, exifData, waiters[i].$container, waiters[i].zoneFormats, waiters[i].photoIndex);
        }
    }

    function processExifQueue() {
        while (exifPrefetchActive < EXIF_PREFETCH_WORKERS && exifQueue.length) {
            var item = exifQueue.shift();
            delete exifQueued[item.mediaId];
            if (exifCache[item.mediaId] || exifInFlight[item.mediaId]) {
                if (exifCache[item.mediaId]) {
                    flushExifWaiters(item.mediaId, exifCache[item.mediaId]);
                }
                continue;
            }

            if (!item.photoUrl) {
                continue;
            }

            exifPrefetchActive++;
            exifInFlight[item.mediaId] = true;

            (function(queueItem) {
                $.ajax({
                    url: jzsaAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'jzsa_fetch_photo_meta',
                        nonce: jzsaAjax.photoMetaNonce,
                        photo_url: queueItem.photoUrl,
                        media_url: queueItem.mediaUrl,
                        media_type: queueItem.mediaType,
                        need_exif: queueItem.needExif ? 'true' : 'false',
                        need_filename: queueItem.needFilename ? 'true' : 'false'
                    }
                }).done(function(response) {
                    if (response && response.success && response.data) {
                        exifCache[queueItem.mediaId] = $.extend({}, exifCache[queueItem.mediaId] || {}, response.data);
                        flushExifWaiters(queueItem.mediaId, response.data);
                    }
                }).always(function() {
                    if (exifCache[queueItem.mediaId]) {
                        flushExifWaiters(queueItem.mediaId, exifCache[queueItem.mediaId]);
                    }
                    delete exifInFlight[queueItem.mediaId];
                    exifPrefetchActive = Math.max(0, exifPrefetchActive - 1);
                    processExifQueue();
                });
            })(item);
        }
    }

    /**
     * Schedule Wave 2 metadata prefetch for all photos in a container.
     * Only runs if zone format strings reference filename/name or EXIF placeholders.
     *
     * @param {jQuery} $container Gallery/album container.
     * @param {Object} zoneFormats  { inline: {...}, fullscreen: {...} }
     */
    function scheduleExifPrefetch($container, zoneFormats) {
        var metaNeeds = getPhotoMetaNeeds(zoneFormats);
        if (!metaNeeds.exif && !metaNeeds.filename) {
            return;
        }
        if (typeof jzsaAjax === 'undefined' || !jzsaAjax.photoMetaNonce) {
            return;
        }

        var albumUrl = $container.attr('data-album-url') || '';
        if (!albumUrl) {
            return;
        }

        var photosJson = $container.attr('data-all-photos');
        if (!photosJson) {
            return;
        }

        try {
            var photos = JSON.parse(photosJson);
            for (var i = 0; i < photos.length; i++) {
                var p = photos[i];
                var cachedMeta = exifCache[p.id] || {};
                var needsExifForPhoto = metaNeeds.exif && !p.camera && !cachedMeta.camera;
                var needsFilenameForPhoto = metaNeeds.filename && !p.filename && !cachedMeta.filename;

                if (exifCache[p.id]) {
                    (function(mediaId, cachedMeta, containerEl, formats, photoIndex) {
                        window.setTimeout(function() {
                            applyExifToPhoto(mediaId, cachedMeta, containerEl, formats, photoIndex);
                        }, 0);
                    })(p.id, exifCache[p.id], $container, zoneFormats, i);
                }

                if (!p.id || (!needsExifForPhoto && !needsFilenameForPhoto)) {
                    continue;
                }

                addExifWaiter(p.id, $container, zoneFormats, i);

                if (exifQueued[p.id] || exifInFlight[p.id]) {
                    continue;
                }
                var photoUrl = buildPhotoPageUrl(albumUrl, p.id);
                if (!photoUrl) {
                    delete exifWaiters[p.id];
                    continue;
                }
                exifQueued[p.id] = true;
                exifQueue.push({
                    mediaId: p.id,
                    photoUrl: photoUrl,
                    mediaUrl: p.type === 'video' ? (p.video || '') : (p.full || p.preview || ''),
                    mediaType: p.type === 'video' ? 'video' : 'photo',
                    needExif: needsExifForPhoto,
                    needFilename: needsFilenameForPhoto
                });
            }
        } catch (e) { /* ignore parse errors */ }

        if (!exifQueue.length || exifPrefetchScheduled) {
            return;
        }
        exifPrefetchScheduled = true;

        var schedule = window.requestIdleCallback || function(cb) { setTimeout(cb, 500); };
        schedule(function() {
            exifPrefetchScheduled = false;
            processExifQueue();
        });
    }

    function getSwiperPhotoIndex(swiper) {
        if (!swiper) {
            return 0;
        }

        if (typeof swiper.realIndex === 'number' && !isNaN(swiper.realIndex)) {
            return swiper.realIndex;
        }

        if (
            swiper.slides &&
            typeof swiper.activeIndex === 'number' &&
            swiper.activeIndex >= 0 &&
            swiper.slides[swiper.activeIndex]
        ) {
            var realIndexAttr = $(swiper.slides[swiper.activeIndex]).attr('data-swiper-slide-index');
            var parsedRealIndex = parseInt(realIndexAttr, 10);
            if (!isNaN(parsedRealIndex) && parsedRealIndex >= 0) {
                return parsedRealIndex;
            }
        }

        if (typeof swiper.activeIndex === 'number' && swiper.activeIndex >= 0) {
            return swiper.activeIndex;
        }

        return 0;
    }

    function getContainerPhoto($container, photoIndex) {
        var photosJson = $container.attr('data-all-photos');
        if (!photosJson || photoIndex < 0) {
            return {};
        }
        try {
            return JSON.parse(photosJson)[photoIndex] || {};
        } catch (e) {
            return {};
        }
    }

    // Returns {item, items} strings for the container-level pagination pill.
    // In carousel mode, {item} may be a range like "16-17" when multiple slides are visible.
    function buildItemPlaceholders(mode, swiper, current, total) {
        if (mode === 'carousel') {
            var slidesPerView = swiper.params.slidesPerView || 1;
            var realIndex = getSwiperPhotoIndex(swiper);
            var visible = [];
            var maxVisible = Math.min(slidesPerView, total);
            for (var i = 0; i < maxVisible; i++) {
                var idx = realIndex + i;
                if (idx >= total) {
                    idx = swiper.params.loop ? idx % total : -1;
                }
                if (idx >= 0) {
                    visible.push(idx + 1);
                }
            }
            var itemStr = visible.length <= 1
                ? String(visible[0] || current)
                : visible[0] + '-' + visible[visible.length - 1];
            return { item: itemStr, items: String(total) };
        }

        return { item: String(current), items: String(total) };
    }

    // Returns {item, items} strings for a single photo at a 0-based index.
    function buildSinglePhotoItemPlaceholders(index, total) {
        var safeIndex = typeof index === 'number' && index >= 0 ? index + 1 : 1;
        return { item: String(safeIndex), items: String(total) };
    }

    function resolveCarouselTileInfo(photo, zoneFormats, context) {
        if (!zoneFormats) {
            return {};
        }

        var info = {};
        for (var i = 0; i < SAFE_INFO_TOP_ORDER.length; i++) {
            info[SAFE_INFO_TOP_ORDER[i]] = resolveInfoPlaceholders(getInfoZoneFormat(zoneFormats, SAFE_INFO_TOP_ORDER[i], false), photo, context);
        }
        for (var j = 0; j < SAFE_INFO_BOTTOM_ORDER.length; j++) {
            info[SAFE_INFO_BOTTOM_ORDER[j]] = resolveInfoPlaceholders(getInfoZoneFormat(zoneFormats, SAFE_INFO_BOTTOM_ORDER[j], false), photo, context);
        }
        info['info-bottom'] = resolveInfoPlaceholders(getInfoZoneFormat(zoneFormats, 'info-bottom', false), photo, context);
        return info;
    }

    function buildCarouselTileInfoHtml(photo, zoneFormats, context) {
        if (!zoneFormats) {
            return '';
        }

        var tileInfo = resolveCarouselTileInfo(photo, zoneFormats, context);
        var html = '';

        function buildZoneBox(zone) {
            var format = getInfoZoneFormat(zoneFormats, zone, false);
            if (!format) {
                return '';
            }
            var text = tileInfo[zone] || '';
            return '<div class="jzsa-info-box jzsa-' + zone + ' jzsa-carousel-tile-info-box"' +
                (text ? '' : ' style="display:none"') + '>' +
                escapeHtml(text) + '</div>';
        }

        var topHtml = '';
        for (var i = 0; i < SAFE_INFO_TOP_ORDER.length; i++) {
            topHtml += buildZoneBox(SAFE_INFO_TOP_ORDER[i]);
        }
        if (topHtml) {
            html += '<div class="jzsa-info-stack jzsa-info-stack-top jzsa-carousel-tile-info-stack">' + topHtml + '</div>';
        }

        // info-bottom is emitted as a standalone element so it can stay
        // pinned at the tile bottom independently of the  stack.
        var bottomSideHtml = '';
        for (var j = 0; j < SAFE_INFO_BOTTOM_ORDER.length; j++) {
            bottomSideHtml += buildZoneBox(SAFE_INFO_BOTTOM_ORDER[j]);
        }
        if (bottomSideHtml) {
            html += '<div class="jzsa-info-stack jzsa-info-stack-bottom jzsa-carousel-tile-info-stack">' + bottomSideHtml + '</div>';
        }
        html += buildZoneBox('info-bottom');

        return html;
    }

    // Helper: Build slides HTML structure (for photo/video array)
    function buildSlidesHtml(photos, options) {
        var config = options || {};
        var useLazyHints = !!config.lazyHints;
        var eagerIndex = typeof config.eagerIndex === 'number' ? config.eagerIndex : 0;
        var mode = config.mode || '';
        var showCarouselTileFullscreenButtons = !!config.showCarouselTileFullscreenButtons;
        var showCarouselTileLinkButtons = !!config.showCarouselTileLinkButtons;
        var showCarouselTileDownloadButtons = !!config.showCarouselTileDownloadButtons;
        var carouselAlbumUrl = config.carouselAlbumUrl || '';
        var carouselZoneFormats = config.carouselZoneFormats || null;
        var carouselTotalCount = parseInt(config.carouselTotalCount, 10) || photos.length || 0;
        var carouselAlbumTitle = config.carouselAlbumTitle || '';
        var html = '';
        photos.forEach(function(photo, index) {
            var isVideo = photo.type === 'video';
            var tileOverlayButtons = '';
            var tileInfoHtml = '';
            if (mode === 'carousel') {
                var showTileLink = showCarouselTileLinkButtons && !!carouselAlbumUrl;
                var showTileDownload = showCarouselTileDownloadButtons;
                if (showCarouselTileFullscreenButtons) {
                    tileOverlayButtons +=
                        '<button class="swiper-button-fullscreen jzsa-gallery-thumb-fs-btn jzsa-carousel-slide-overlay-btn jzsa-carousel-slide-fs-btn" type="button" ' +
                        'aria-label="Open media ' + (index + 1) + ' in fullscreen"></button>';
                }
                if (showTileLink) {
                    tileOverlayButtons +=
                        '<a href="' + carouselAlbumUrl + '" target="_blank" rel="noopener noreferrer" class="swiper-button-external-link jzsa-carousel-slide-overlay-btn jzsa-carousel-slide-link-btn jzsa-carousel-slide-left-primary" title="Open in Google Photos" aria-label="Open album in Google Photos"></a>';
                }
                if (showTileDownload) {
                    var downloadUrl = isVideo
                        ? (photo.video || photo.full || photo.preview || '')
                        : (photo.full || photo.preview || '');
                    var downloadPosClass = showTileLink ? 'jzsa-carousel-slide-left-secondary' : 'jzsa-carousel-slide-left-primary';
                    tileOverlayButtons +=
                        '<button class="swiper-button-download jzsa-carousel-slide-overlay-btn jzsa-carousel-slide-download-btn ' + downloadPosClass + '" type="button" data-download-url="' + downloadUrl + '" data-download-type="' + (isVideo ? 'video' : 'photo') + '" data-download-index="' + (index + 1) + '" title="Download current media" aria-label="Download media ' + (index + 1) + '"></button>';
                }

                tileInfoHtml = buildCarouselTileInfoHtml(photo, carouselZoneFormats, $.extend(
                    buildSinglePhotoItemPlaceholders(index, carouselTotalCount),
                    { albumTitle: carouselAlbumTitle }
                ));
            }

            // Build alt text from filename when available.
            var altText = photo.filename
                ? photo.filename.replace(/\.[^.]+$/, '')
                : 'Photo';
            if (isVideo) {
                var posterUrl = photo.preview || photo.full || '';
                html += '<div class="swiper-slide jzsa-slide-video" data-media-type="video" data-carousel-photo-index="' + index + '">' +
                    buildVideoHtml({ src: photo.video, poster: posterUrl, mediaIndex: index }) +
                    tileInfoHtml +
                    tileOverlayButtons +
                    '</div>';
            } else {
                // Photo format: object with preview and full URLs
                var previewUrl = photo.preview || photo.full;
                var fullUrl = photo.full;
                var loadingAttr = '';
                if (useLazyHints) {
                    loadingAttr = ' loading="' + (index === eagerIndex ? 'eager' : 'lazy') + '" decoding="async"';
                }

                html += '<div class="swiper-slide" data-carousel-photo-index="' + index + '">' +
                    '<div class="swiper-zoom-container">' +
                    '<img src="' + previewUrl + '" ' +
                    (previewUrl !== fullUrl ? 'data-full-src="' + fullUrl + '" ' : '') +
                    'alt="' + escapeHtml(altText) + '" class="jzsa-progressive-image"' + loadingAttr + ' decoding="async" />' +
                    '</div>' +
                    tileInfoHtml +
                    tileOverlayButtons +
                    '</div>';
            }
        });
        return html;
    }

    function updateCarouselTileInfoBoxes($container, swiper, zoneFormats) {
        if (!$container || !$container.length || !swiper) {
            return;
        }
        if ($container.attr('data-mode') !== 'carousel') {
            return;
        }
        if ($container.hasClass('jzsa-is-fullscreen') || $container.hasClass('jzsa-pseudo-fullscreen')) {
            return;
        }

        var photosJson = $container.attr('data-all-photos');
        var photos = [];
        var total = 0;
        if (photosJson) {
            try {
                photos = JSON.parse(photosJson);
                total = photos.length;
            } catch (e) {
                total = 0;
            }
        }
        var albumTitle = $container.attr('data-album-title') || '';
        var allInfoZones = SAFE_INFO_TOP_ORDER.concat(SAFE_INFO_BOTTOM_ORDER);
        $container.find('.swiper-slide').each(function() {
            var $slide = $(this);
            var photoIndex = parseInt($slide.attr('data-swiper-slide-index'), 10);
            if (isNaN(photoIndex) || photoIndex < 0) {
                photoIndex = parseInt($slide.attr('data-carousel-photo-index'), 10);
            }
            if (isNaN(photoIndex) || photoIndex < 0) {
                return;
            }

            var photo = getContainerPhoto($container, photoIndex);
            $slide.find('.jzsa-carousel-tile-info-box').each(function() {
                var $box = $(this);
                var zone = '';
                for (var i = 0; i < allInfoZones.length; i++) {
                    if ($box.hasClass('jzsa-' + allInfoZones[i])) {
                        zone = allInfoZones[i];
                        break;
                    }
                }
                if (!zone) {
                    return;
                }
                var text = resolveInfoPlaceholders(getInfoZoneFormat(zoneFormats, zone, false), photo,
                    $.extend(buildSinglePhotoItemPlaceholders(photoIndex, total), { albumTitle: albumTitle })
                );
                $box.text(text).toggle(text !== '');
            });
        });

    }

    function updateGalleryThumbnailInfoBoxes($container) {
        if (!$container || !$container.length || $container.attr('data-mode') !== 'gallery') {
            return;
        }

        var photosJson = $container.attr('data-all-photos');
        var photos = [];
        var total = 0;
        if (photosJson) {
            try {
                photos = JSON.parse(photosJson);
                total = photos.length;
            } catch (e) {
                total = 0;
            }
        }
        var albumTitle = $container.attr('data-album-title') || '';
        var allInfoZones = SAFE_INFO_TOP_ORDER.concat(GALLERY_INFO_BOTTOM_ORDER);

        $container.find('.jzsa-gallery-item, .jzsa-gallery-item-video').each(function() {
            var $item = $(this);
            var photoIndex = parseInt($item.attr('data-index'), 10);
            if (isNaN(photoIndex) || photoIndex < 0) {
                return;
            }

            var photo = getContainerPhoto($container, photoIndex);
            $item.find('.jzsa-info-box').each(function() {
                var $box = $(this);
                var inlineFmt = '';
                for (var i = 0; i < allInfoZones.length; i++) {
                    if ($box.hasClass('jzsa-' + allInfoZones[i])) {
                        inlineFmt = $container.attr('data-' + allInfoZones[i]) || '';
                        break;
                    }
                }
                var text = resolveInfoPlaceholders(inlineFmt, photo,
                    $.extend(buildSinglePhotoItemPlaceholders(photoIndex, total), { albumTitle: albumTitle })
                );
                $box.text(text).toggle(text !== '');
            });
        });

    }

    // Helper: Build loading overlay markup.
    function buildLoaderHtml(text) {
        var label = text || 'Loading content...';
        return '' +
            '<div class="jzsa-loader">' +
                '<div class="jzsa-loader-inner">' +
                    '<div class="jzsa-loader-spinner"></div>' +
                    '<div class="jzsa-loader-text">' + label + '</div>' +
                '</div>' +
            '</div>';
    }

    // Helper: Intro fade for gallery container so content appears progressively.
    function triggerGalleryIntroFade($container) {
        if (!$container || !$container.length) {
            return;
        }

        var existingTimer = $container.data('jzsaIntroFadeTimer');
        if (existingTimer) {
            window.clearTimeout(existingTimer);
        }

        $container
            .removeClass('jzsa-content-intro-visible')
            .addClass('jzsa-content-intro');

        var introTimer = window.setTimeout(function() {
            $container.addClass('jzsa-content-intro-visible');
            $container.removeData('jzsaIntroFadeTimer');
        }, 20);

        $container.data('jzsaIntroFadeTimer', introTimer);
    }

    // Helper: Update video control auto-hide behavior for current and future playback.
    function applyVideoControlsAutohideSetting($container, autohide) {
        var hideControls = !!autohide;
        $container.attr('data-video-controls-autohide', hideControls ? 'true' : 'false');

        // Best effort: update existing Plyr instances in-place.
        $container.find('video.jzsa-video-player').each(function() {
            var plyrRef = this._jzsaPlyr;
            if (!plyrRef) {
                return;
            }
            if (plyrRef.config) {
                plyrRef.config.hideControls = hideControls;
            }
            if (plyrRef.options) {
                plyrRef.options.hideControls = hideControls;
            }
        });
    }

    // Helper: Apply inline/fullscreen display overrides that have sibling params.
    function applyFullscreenDisplayOverrides(containerElement, swiper, params, fullscreenActive) {
        if (!containerElement || !params) {
            return;
        }

        var $container = $(containerElement);
        var useFullscreen = !!fullscreenActive;

        var showNavigation = useFullscreen ? params.fullscreenShowNavigation : params.showNavigation;
        var controlsColor = useFullscreen ? params.fullscreenControlsColor : params.controlsColor;
        var videoControlsColor = useFullscreen ? params.fullscreenVideoControlsColor : params.videoControlsColor;
        var videoControlsAutohide = useFullscreen ? params.fullscreenVideoControlsAutohide : params.videoControlsAutohide;
        var infoFontSize = useFullscreen ? params.fullscreenInfoFontSize : params.infoFontSize;
        var infoFontFamily = useFullscreen ? params.fullscreenInfoFontFamily : params.infoFontFamily;

        $container.attr('data-show-navigation', showNavigation ? 'true' : 'false');

        if (controlsColor) {
            containerElement.style.setProperty('--jzsa-controls-color', controlsColor);
            applyControlsColorToIcons('#' + (params.galleryId || $container.attr('id')), controlsColor);
        } else {
            containerElement.style.removeProperty('--jzsa-controls-color');
        }

        if (videoControlsColor) {
            containerElement.style.setProperty('--jzsa-video-controls-color', videoControlsColor);
        } else {
            containerElement.style.removeProperty('--jzsa-video-controls-color');
        }

        if (infoFontSize > 0) {
            containerElement.style.setProperty('--jzsa-info-font-size', infoFontSize + 'px');
        } else {
            containerElement.style.removeProperty('--jzsa-info-font-size');
        }

        if (infoFontFamily) {
            containerElement.style.setProperty('--jzsa-info-font-family', infoFontFamily);
        } else {
            containerElement.style.removeProperty('--jzsa-info-font-family');
        }

        var activeBottomCenterFormat = useFullscreen
            ? ($container.attr('data-fullscreen-info-bottom') || $container.attr('data-info-bottom') || '')
            : ($container.attr('data-info-bottom') || '');
        $container.attr('data-has-active-bottom-center', activeBottomCenterFormat ? 'true' : 'false');

        applyVideoControlsAutohideSetting($container, videoControlsAutohide);

        params.slideshowAutoresume = useFullscreen ? params.fullscreenSlideshowAutoresume : params.inlineSlideshowAutoresume;

        if (swiper && swiper.pagination) {
            if (typeof swiper.pagination.render === 'function') {
                swiper.pagination.render();
            }
            if (typeof swiper.pagination.update === 'function') {
                swiper.pagination.update();
            }
        }
    }

    // Helper: Apply fullscreen slideshow settings immediately (for Android compatibility)
    function applyFullscreenAutoplaySettings(swiper, params) {
        // Only run this workaround on Android devices where fullscreenchange events
        // are known to be unreliable.
		if (params.fullscreenSlideshow === 'disabled' || !isAndroid()) {
			return;
		}

		jzsaDebug('🔍 Applying fullscreen autoplay settings immediately (Android workaround)');

        // Stop current autoplay if running
        if (swiper.autoplay && swiper.autoplay.running) {
            swiper.autoplay.stop();
        }

        // Update autoplay delay for fullscreen mode
			var newDelay = params.fullscreenSlideshowDelay * MILLISECONDS_PER_SECOND;
			jzsaDebug('🔍 Setting fullscreen autoplay delay to:', newDelay, 'ms (', params.fullscreenSlideshowDelay, 's)');

        // Update both params and the active autoplay object
        swiper.params.autoplay.delay = newDelay;
        if (swiper.autoplay) {
            swiper.autoplay.delay = newDelay;
        }

        // Start fullscreen autoplay after a short delay to ensure fullscreen is active
        setTimeout(function() {
				if (!params.slideshowPausedByInteraction && params.fullscreenSlideshow === 'auto' && swiper.autoplay) {
					swiper.autoplay.start();
					jzsaDebug('▶️  Fullscreen autoplay started immediately (delay: ' + params.fullscreenSlideshowDelay + 's)');
				}
        }, 100);

        // ANDROID WORKAROUND: Some Android browsers don't fire fullscreen change events reliably.
        // Poll for fullscreen state and apply settings if events don't fire within 300ms
			var fullscreenCheckTimeout = setTimeout(function() {
				var nowFullscreen = isFullscreen();

				if (nowFullscreen && params.fullscreenSlideshow !== 'disabled') {
					jzsaDebug('⚠️  Fullscreen change event did not fire - applying settings via fallback (Android workaround)');

                // Ensure settings are applied
                if (swiper.autoplay && swiper.autoplay.running) {
                    swiper.autoplay.stop();
                }

                var newDelay = params.fullscreenSlideshowDelay * MILLISECONDS_PER_SECOND;
                swiper.params.autoplay.delay = newDelay;
                if (swiper.autoplay) {
                    swiper.autoplay.delay = newDelay;
                }

					if (!params.slideshowPausedByInteraction && params.fullscreenSlideshow === 'auto') {
						swiper.autoplay.start();
						jzsaDebug('▶️  Fullscreen autoplay started via fallback (delay: ' + params.fullscreenSlideshowDelay + 's)');
					}
            }
        }, 300);

        // Clear the timeout if fullscreen change event fires (it will handle the settings)
        var clearFallback = function() {
            clearTimeout(fullscreenCheckTimeout);
            document.removeEventListener('fullscreenchange', clearFallback);
            document.removeEventListener('webkitfullscreenchange', clearFallback);
            document.removeEventListener('mozfullscreenchange', clearFallback);
            document.removeEventListener('MSFullscreenChange', clearFallback);
        };
        document.addEventListener('fullscreenchange', clearFallback);
        document.addEventListener('webkitfullscreenchange', clearFallback);
        document.addEventListener('mozfullscreenchange', clearFallback);
        document.addEventListener('MSFullscreenChange', clearFallback);
    }

    // Helper: Handle fullscreen change events
    function handleFullscreenChange(containerElement, swiper, params) {
        // Only handle fullscreen changes for THIS gallery
        var fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement ||
                               document.mozFullScreenElement || document.msFullscreenElement;
        var isPseudoFullscreen = $(containerElement).hasClass('jzsa-pseudo-fullscreen');
        var isContainerFullscreen = fullscreenElement === containerElement || isPseudoFullscreen;

        if (isContainerFullscreen) {
				// Entering fullscreen - switch to fullscreen autoplay settings
				var logPrefix = params.browserPrefix ? ' (' + params.browserPrefix + ')' : '';
				jzsaDebug('🔍 Fullscreen entered for gallery' + logPrefix + ':', params.galleryId);
            params._fullscreenActive = true;

            // In carousel mode, switch layout to a single slide in fullscreen
            // while keeping the preview in multi-slide carousel mode.
            if (params.mode === 'carousel') {
                if (params.originalSlidesPerView == null) {
                    params.originalSlidesPerView = swiper.params.slidesPerView;
                    params.originalBreakpoints = swiper.params.breakpoints;
                    params.originalCenteredSlides = swiper.params.centeredSlides;
                }
                swiper.params.slidesPerView = 1;
                swiper.params.centeredSlides = true;
                swiper.params.breakpoints = undefined;
                swiper.update();
            }

            // Add fullscreen class for CSS styling
            $(containerElement).addClass('jzsa-is-fullscreen');
            applyFullscreenDisplayOverrides(containerElement, swiper, params, true);

            // Apply fullscreen background color if set
            var fsBgColor = $(containerElement).attr('data-fullscreen-background-color');
            if (fsBgColor && !isPseudoFullscreen) {
                params._originalBgColor = containerElement.style.getPropertyValue('--gallery-bg-color');
                containerElement.style.setProperty('--gallery-bg-color', fsBgColor);
            }

            if (!params.browserPrefix) {
                // Only log detailed debug info for standard API (avoid log spam)
                // console.log('🔍 fullscreenSlideshow:', params.fullscreenSlideshow);
                // console.log('🔍 fullscreenSlideshowDelay:', params.fullscreenSlideshowDelay);
            }

            if (params.fullscreenSlideshow !== 'disabled') {
                // Stop current autoplay if running
                if (swiper.autoplay && swiper.autoplay.running) {
                    swiper.autoplay.stop();
                }
                // Update autoplay delay for fullscreen mode
                var newDelay = params.fullscreenSlideshowDelay * MILLISECONDS_PER_SECOND;

			if (!params.browserPrefix) {
				jzsaDebug('🔍 Setting fullscreen autoplay delay to:', newDelay, 'ms (', params.fullscreenSlideshowDelay, 's)');
			}

                // Update both params and the active autoplay object
                swiper.params.autoplay.delay = newDelay;
				if (swiper.autoplay) {
					swiper.autoplay.delay = newDelay;
				}

				if (!params.browserPrefix) {
					jzsaDebug('🔍 swiper.params.autoplay.delay is now:', swiper.params.autoplay.delay);
					jzsaDebug('🔍 swiper.autoplay.delay is now:', swiper.autoplay ? swiper.autoplay.delay : 'N/A');
				}

	                // Start fullscreen autoplay only in 'auto' mode
					if (!params.slideshowPausedByInteraction && params.fullscreenSlideshow === 'auto') {
						swiper.autoplay.start();
						jzsaDebug('▶️  Fullscreen autoplay started (delay: ' + params.fullscreenSlideshowDelay + 's' + logPrefix + ')');
					}
	            }
        } else if (!fullscreenElement && swiper && params._fullscreenActive) {
				// Exiting fullscreen (this gallery was in fullscreen before) - switch back to normal autoplay settings
				var logPrefix = params.browserPrefix ? ' (' + params.browserPrefix + ')' : '';
				jzsaDebug('🔍 Fullscreen exited for gallery' + logPrefix + ':', params.galleryId);

            // Exit via Esc/browser chrome does not go through toggleFullscreen().
            // Ensure video playback is stopped consistently on fullscreen exit.
            stopAllManagedVideos();

            // In carousel mode, restore the original multi-slide layout
            // but keep the same logical photo index the user was viewing in
            // fullscreen. We must capture the realIndex *before* changing
            // slidesPerView/breakpoints, because Swiper may adjust indices when
            // layout changes.
            if (params.mode === 'carousel' && params.originalSlidesPerView != null) {
                var targetIndex = (typeof swiper.realIndex === 'number') ? swiper.realIndex : swiper.activeIndex;

                swiper.params.slidesPerView = params.originalSlidesPerView;
                swiper.params.centeredSlides = params.originalCenteredSlides;
                swiper.params.breakpoints = params.originalBreakpoints;
                swiper.update();

                if (swiper.params.loop && typeof swiper.slideToLoop === 'function') {
                    swiper.slideToLoop(targetIndex, 0, false);
                } else {
                    swiper.slideTo(targetIndex, 0, false);
                }
            }

            // Attempt to auto-correct WordPress tile width if iOS Safari/WebKit
            // leaves it in a broken state after fullscreen. We compare the parent LI
            // width with the gallery container width and, if they differ
            // significantly, clamp the LI to match the gallery width.
            // Only apply this workaround on iOS devices (Safari and WebKit-based
            // browsers like Chrome on iOS all use the same engine).
            if (!isIosDevice()) {
                // Remove any stale no-scroll class even on non-iOS, just in case.
                $('html, body').removeClass('jzsa-no-scroll');
            }

            // Poll a few times shortly after exiting fullscreen so we can
            // correct the width almost immediately after the buggy layout
            // kicks in (old iOS sometimes applies it with a delay).
            (function () {
                var attempts = 0;
                var maxAttempts = 6; // ~6 * 120ms ≈ 720ms total
                var interval = 120;

                function tryFix() {
                    attempts++;
                    try {
                        if (!isIosDevice()) {
                            return;
                        }

                        var $container = $(containerElement);
                        var liEl = $container.closest('li')[0];
                        if (!liEl || !containerElement || !containerElement.getBoundingClientRect) {
                            return;
                        }

                        var galleryRect = containerElement.getBoundingClientRect();
                        var liRect = liEl.getBoundingClientRect();

                        if (!galleryRect || !liRect) {
                            return;
                        }

                        var gw = galleryRect.width;
                        var lw = liRect.width;

                        // Only adjust if we have sane numbers and the LI is much
                        // narrower than the gallery (e.g. 35px vs 267px).
						if (gw > 0 && lw > 0 && Math.abs(lw - gw) > 20) {
							jzsaDebug('[JZSA LAYOUT FIX] correcting LI width for', params.galleryId, 'from', lw, 'to', gw, 'on attempt', attempts);
							liEl.style.width = gw + 'px';
                            return; // stop polling once fixed
                        }
					} catch (e) {
						jzsaDebug('[JZSA LAYOUT FIX ERROR]', e);
					}

                    if (attempts < maxAttempts) {
                        setTimeout(tryFix, interval);
                    }
                }

                setTimeout(tryFix, interval);
            })();

            // Restore original background color
            if (params._originalBgColor !== undefined) {
                if (params._originalBgColor) {
                    containerElement.style.setProperty('--gallery-bg-color', params._originalBgColor);
                } else {
                    containerElement.style.removeProperty('--gallery-bg-color');
                }
                delete params._originalBgColor;
            }

	            // Remove fullscreen class
	            $(containerElement).removeClass('jzsa-is-fullscreen');
	            $(containerElement).removeClass('jzsa-fullscreen-waiting');
            applyFullscreenDisplayOverrides(containerElement, swiper, params, false);
	            clearCountdownRing($(containerElement));

            notifyGalleryOnFullscreenExit(containerElement, swiper);

            params.slideshowPausedByInteraction = false;

            if (params.slideshow === 'auto') {
                // Stop current autoplay if running
                if (swiper.autoplay && swiper.autoplay.running) {
                    swiper.autoplay.stop();
                }
                // Restore normal autoplay delay
                var normalDelay = params.slideshowDelay * MILLISECONDS_PER_SECOND;
                swiper.params.autoplay.delay = normalDelay;
                if (swiper.autoplay) {
                    swiper.autoplay.delay = normalDelay;
                }
                // Start normal autoplay
                swiper.autoplay.start();
                // console.log('▶️  Normal autoplay restored (delay: ' + params.slideshowDelay + 's' + logPrefix + ')');
	            } else if (swiper.autoplay && swiper.autoplay.running) {
	                // Stop autoplay if inline slideshow is not 'auto'
	                swiper.autoplay.stop();
	                // console.log('⏸️  Autoplay stopped (not auto in normal mode' + logPrefix + ')');
	            }
            params._fullscreenActive = false;
	        }
	    }

    // Helper: Show countdown ring on the play/pause button (appears after 5s delay)
    var COUNTDOWN_RING_DELAY_MS = 5000;

    function showCountdownRing($el, durationSeconds) {
        var $btn = $el.find('.swiper-button-play-pause');
        $btn.find('.jzsa-countdown-ring').remove();
        clearTimeout($el.data('jzsa-countdown-show-timer'));
        $el.addClass('jzsa-slideshow-interrupted');

        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('class', 'jzsa-countdown-ring');
        svg.setAttribute('viewBox', '0 0 22 22');
        var circle = document.createElementNS(ns, 'circle');
        circle.setAttribute('cx', '11');
        circle.setAttribute('cy', '11');
        circle.setAttribute('r', '9');
        circle.style.animationDuration = durationSeconds + 's';
        svg.appendChild(circle);
        $btn.append(svg);

        // Fade in after delay
        var showTimer = setTimeout(function() {
            $(svg).addClass('jzsa-visible');
        }, COUNTDOWN_RING_DELAY_MS);
        $el.data('jzsa-countdown-show-timer', showTimer);
    }

    // Helper: Remove countdown ring from the play/pause button
    function clearCountdownRing($el) {
        clearTimeout($el.data('jzsa-countdown-show-timer'));
        $el.removeClass('jzsa-slideshow-interrupted');
        $el.find('.swiper-button-play-pause .jzsa-countdown-ring').remove();
    }

    // Helper: Pause slideshow on user interaction
    function pauseAutoplayOnInteraction(swiper, params) {
        // Act when autoplay is running OR already interrupted (to reset the countdown)
        if (swiper.autoplay && (swiper.autoplay.running || params.slideshowPausedByInteraction)) {
            if (swiper.autoplay.running) {
                swiper.autoplay.stop();
                jzsaDebug('⏸️  Autoplay paused by user interaction');
            } else {
                jzsaDebug('⏸️  Autoresume countdown reset by user interaction');
            }
            params.slideshowPausedByInteraction = true;

            // Clear any existing inactivity timer
            if (params.inactivityTimer) {
                clearTimeout(params.inactivityTimer);
            }

            // Set inactivity timer to resume autoplay after configured timeout
            if (params.slideshowAutoresume !== 'disabled') {
                var timeoutMs = (params.slideshowAutoresume || 30) * 1000;
                showCountdownRing($(swiper.el), params.slideshowAutoresume || 30);
                params.inactivityTimer = setTimeout(function() {
                    if (params.slideshowPausedByInteraction && swiper.autoplay && !swiper.autoplay.running) {
                        jzsaDebug('▶️  Resuming autoplay after ' + (params.slideshowAutoresume || 30) + ' seconds of inactivity');
                        params.slideshowPausedByInteraction = false;
                        clearCountdownRing($(swiper.el));
                        swiper.autoplay.start();
                    }
                }, timeoutMs);
            }
        }
    }


    // Helper: Setup fullscreen button
    function setupFullscreenButton(swiper, $container, params) {
        if (params && params.interactionLock) {
            return;
        }

        var $fullscreenBtn = $container.find('.swiper-button-fullscreen');
			$fullscreenBtn.on('click', function(e) {
				e.stopPropagation();

			// Check if we're entering or exiting fullscreen
			var isCurrentlyFullscreen = isFullscreen();

			if (!isCurrentlyFullscreen) {
				// In carousel mode, a per-tile fullscreen button should open the
				// exact clicked tile in fullscreen (not whichever slide is active).
				if (params.mode === 'carousel') {
					var $clickedSlide = $(e.target).closest('.swiper-slide');
					if ($clickedSlide.length) {
						var realIndexAttr = $clickedSlide.attr('data-swiper-slide-index');
						var realIndex = realIndexAttr != null ? parseInt(realIndexAttr, 10) : NaN;
						if (!isNaN(realIndex) && realIndex >= 0) {
							if (swiper.params.loop && typeof swiper.slideToLoop === 'function') {
								swiper.slideToLoop(realIndex, 0, false);
							} else {
								swiper.slideTo(realIndex, 0, false);
							}
						}
					}
				}

				// About to enter fullscreen - apply fullscreen autoplay settings immediately (Android workaround)
				jzsaDebug('🔍 Fullscreen button clicked - entering fullscreen');
                applyFullscreenAutoplaySettings(swiper, {
                    fullscreenSlideshow: params.fullscreenSlideshow,
                    fullscreenSlideshowDelay: params.fullscreenSlideshowDelay,
                    slideshowPausedByInteraction: params.slideshowPausedByInteraction
                });
            }

            toggleFullscreen($container[0], params.showHintsOnFullscreen);
        });
    }

    function normalizeDownloadErrorData(raw) {
        var message = '';
        var requiresLargeDownloadConfirmation = false;
        var actualSizeBytes = 0;
        var maxSizeBytes = 0;

        if (typeof raw === 'string') {
            message = raw;
        } else if (raw && typeof raw === 'object') {
            if (typeof raw.message === 'string') {
                message = raw.message;
            }
            requiresLargeDownloadConfirmation = raw.requires_large_download_confirmation === true;
            if (raw.actual_size_bytes !== undefined && raw.actual_size_bytes !== null && !isNaN(parseInt(raw.actual_size_bytes, 10))) {
                actualSizeBytes = parseInt(raw.actual_size_bytes, 10);
            }
            if (raw.warning_size_bytes !== undefined && raw.warning_size_bytes !== null && !isNaN(parseInt(raw.warning_size_bytes, 10))) {
                maxSizeBytes = parseInt(raw.warning_size_bytes, 10);
            }
        }

        return {
            message: message || 'Download failed. Please try again.',
            requiresLargeDownloadConfirmation: requiresLargeDownloadConfirmation,
            actualSizeBytes: actualSizeBytes,
            maxSizeBytes: maxSizeBytes
        };
    }

    function getAjaxErrorData(xhr) {
        if (!xhr) {
            return null;
        }
        if (xhr.responseJSON && xhr.responseJSON.success === false) {
            return normalizeDownloadErrorData(xhr.responseJSON.data);
        }
        if (typeof xhr.responseText === 'string' && xhr.responseText) {
            try {
                var parsed = JSON.parse(xhr.responseText);
                if (parsed && parsed.success === false) {
                    return normalizeDownloadErrorData(parsed.data);
                }
            } catch (_ignore) {
                return null;
            }
        }
        return null;
    }

    var jzsaDownloadStatusTimer = null;

    function showDownloadStatus(message, isError) {
        if (!message || !document || !document.body) {
            return;
        }

        var id = 'jzsa-download-status';
        var node = document.getElementById(id);
        if (!node) {
            node = document.createElement('div');
            node.id = id;
            node.className = 'jzsa-download-status';
            node.setAttribute('role', 'status');
            node.setAttribute('aria-live', 'polite');
            document.body.appendChild(node);
        }

        node.textContent = message;
        node.classList.toggle('jzsa-download-status-error', !!isError);
        node.classList.add('jzsa-download-status-visible');

        if (jzsaDownloadStatusTimer) {
            window.clearTimeout(jzsaDownloadStatusTimer);
        }

        jzsaDownloadStatusTimer = window.setTimeout(function() {
            node.classList.remove('jzsa-download-status-visible');
        }, isError ? 4200 : 1800);
    }

    function showDownloadErrorMessage(message) {
        var text = message || 'Download failed. Please try again.';
        if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('Download failed:', text);
        }
        showDownloadStatus(text, true);
    }

    function formatBytesForHumans(bytes) {
        var value = parseInt(bytes, 10);
        if (isNaN(value) || value <= 0) {
            return '';
        }

        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var unitIndex = 0;
        var sizedValue = value;
        while (sizedValue >= 1024 && unitIndex < units.length - 1) {
            sizedValue = sizedValue / 1024;
            unitIndex++;
        }

        var decimals = (sizedValue >= 10 || unitIndex === 0) ? 0 : 1;
        return sizedValue.toFixed(decimals) + ' ' + units[unitIndex];
    }

    function confirmLargeDownload(errorData) {
        if (typeof window.confirm !== 'function') {
            return false;
        }

        var details = [];
        var actual = formatBytesForHumans(errorData.actualSizeBytes);
        var limit = formatBytesForHumans(errorData.maxSizeBytes);
        if (actual && limit) {
            details.push('File size: ' + actual + ' (configured limit: ' + limit + ').');
        } else if (actual) {
            details.push('File size: ' + actual + '.');
        }
        details.push('Do you want to continue downloading this file?');

        var baseMessage = errorData.message || 'This file is larger than the configured download warning threshold.';
        return window.confirm(baseMessage + '\n\n' + details.join('\n'));
    }

    function downloadBlobToFile(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    function detectDownloadErrorFromBlob(blob, callback) {
        if (typeof callback !== 'function') {
            return;
        }

        if (!blob) {
            callback(null);
            return;
        }

        var blobType = (blob.type || '').toLowerCase();
        var shouldInspectAsText =
            blobType.indexOf('application/json') !== -1 ||
            blobType.indexOf('text/') === 0 ||
            (!blobType && blob.size > 0 && blob.size <= 4096);

        if (!shouldInspectAsText) {
            callback(null);
            return;
        }

        function parseText(text) {
            if (!text) {
                callback(null);
                return;
            }
            try {
                var parsed = JSON.parse(text);
                if (parsed && parsed.success === false) {
                    callback(normalizeDownloadErrorData(parsed.data));
                    return;
                }
            } catch (_ignore) {
                // Not JSON payload from wp_send_json_error.
            }
            callback(null);
        }

        if (typeof blob.text === 'function') {
            blob.text().then(parseText).catch(function() {
                callback(null);
            });
            return;
        }

        if (typeof FileReader !== 'undefined') {
            var reader = new FileReader();
            reader.onload = function() {
                parseText(String(reader.result || ''));
            };
            reader.onerror = function() {
                callback(null);
            };
            reader.readAsText(blob);
            return;
        }

        callback(null);
    }

    // Helper: Setup download button
    function setupDownloadButton(swiper, $container) {
        var $downloadBtn = $container.find('.swiper-button-download');
        if ($downloadBtn.length === 0) {
            return; // Download button not enabled
        }
        var downloadWarningSizeBytes = getDownloadWarningSizeBytes($container);

        function inferMediaTypeFromUrl(url) {
            if (!url) {
                return 'photo';
            }
            var normalized = String(url).split('?')[0].toLowerCase();
            if (
                normalized.indexOf('video-downloads.googleusercontent.com') !== -1 ||
                /\.(mp4|mov|m4v|webm|3gp)$/i.test(normalized)
            ) {
                return 'video';
            }
            return 'photo';
        }

        function resolveSlideDownloadData(slideEl) {
            if (!slideEl) {
                return null;
            }
            var $slide = $(slideEl);
            var mediaType = $slide.attr('data-media-type') === 'video' ? 'video' : 'photo';
            var mediaUrl = '';

            if (mediaType === 'video') {
                var $video = $slide.find('video.jzsa-video-player').first();
                mediaUrl = $video.prop('currentSrc') || $video.attr('src') || '';
            } else {
                var $img = $slide.find('img').first();
                mediaUrl = $img.attr('data-full-src') || $img.attr('src') || '';
            }

            if (!mediaUrl) {
                return null;
            }

            return {
                mediaUrl: mediaUrl,
                mediaType: mediaType
            };
        }

        function getDownloadPhotoIndex(downloadIndex) {
            if (!isNaN(downloadIndex) && downloadIndex > 0) {
                return downloadIndex - 1;
            }
            if (typeof swiper.realIndex === 'number' && !isNaN(swiper.realIndex)) {
                return swiper.realIndex;
            }
            return swiper.activeIndex;
        }

        function buildFilename(mediaType, downloadIndex) {
            var currentIndex = getDownloadPhotoIndex(downloadIndex);
            // Use original filename from Google Photos when available.
            var photo = allPhotos[currentIndex];
            if (photo && photo.filename) {
                return photo.filename;
            }
            var safeIndex = currentIndex + 1;
            var prefix = mediaType === 'video' ? 'video-' : 'photo-';
            var extension = mediaType === 'video' ? '.mp4' : '.jpg';
            return prefix + safeIndex + extension;
        }

        function fetchFilenameForDownload(mediaType, mediaUrl, downloadIndex, onDone) {
            var photoIndex = getDownloadPhotoIndex(downloadIndex);
            var photo = allPhotos[photoIndex] || {};
            if (!photo.id || photo.filename || typeof jzsaAjax === 'undefined' || !jzsaAjax.photoMetaNonce) {
                onDone(buildFilename(mediaType, downloadIndex));
                return;
            }

            var albumUrl = $container.attr('data-album-url') || '';
            var photoUrl = buildPhotoPageUrl(albumUrl, photo.id);
            if (!photoUrl) {
                onDone(buildFilename(mediaType, downloadIndex));
                return;
            }

            $.ajax({
                url: jzsaAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jzsa_fetch_photo_meta',
                    nonce: jzsaAjax.photoMetaNonce,
                    photo_url: photoUrl,
                    media_url: mediaUrl,
                    media_type: mediaType,
                    need_exif: 'false',
                    need_filename: 'true'
                }
            }).done(function(response) {
                if (response && response.success && response.data && response.data.filename) {
                    photo.filename = response.data.filename;
                    allPhotos[photoIndex] = photo;
                    $container.attr('data-all-photos', JSON.stringify(allPhotos));
                }
            }).always(function() {
                onDone(buildFilename(mediaType, downloadIndex));
            });
        }

        $downloadBtn.on('click', function(e) {
            e.stopPropagation();
            var $clickedBtn = $(this);
            var mediaUrl = $clickedBtn.attr('data-download-url') || '';
            var mediaType = $clickedBtn.attr('data-download-type') || '';
            var downloadIndex = parseInt($clickedBtn.attr('data-download-index'), 10);

            if (!mediaType) {
                mediaType = $clickedBtn.closest('.swiper-slide').attr('data-media-type') === 'video' ? 'video' : '';
            }

            if (!mediaUrl) {
                // Fallback for container-level download button: resolve from active slide.
                var activeSlideData = resolveSlideDownloadData(swiper.slides[swiper.activeIndex]);
                if (activeSlideData) {
                    mediaUrl = activeSlideData.mediaUrl;
                    if (!mediaType) {
                        mediaType = activeSlideData.mediaType;
                    }
                }
            }

            if (!mediaUrl) {
                return;
            }

            if (!mediaType) {
                mediaType = inferMediaTypeFromUrl(mediaUrl);
            }

            // Google Photos doesn't allow direct downloads due to CORS
            // We need to download via WordPress AJAX proxy
            var originalTitle = $clickedBtn.attr('title');
            var filename = '';

            function restoreButtonState() {
                $clickedBtn.attr('title', originalTitle);
                $clickedBtn.css('opacity', '');
            }

            function requestProxyDownload(allowLargeDownload) {
                $clickedBtn.attr('title', 'Downloading...');
                $clickedBtn.css('opacity', '0.5');
                showDownloadStatus(allowLargeDownload ? 'Preparing large download...' : 'Preparing download...', false);

                var requestData = {
                    action: 'jzsa_download_image',
                    nonce: jzsaAjax.downloadNonce,
                    media_url: mediaUrl,
                    image_url: mediaUrl,
                    filename: filename
                };
                if (downloadWarningSizeBytes !== null) {
                    requestData.warning_size_bytes = downloadWarningSizeBytes;
                }
                if (allowLargeDownload) {
                    requestData.allow_large_download = 'true';
                }

                // Use WordPress AJAX to proxy the download
                $.ajax({
                    url: jzsaAjax.ajaxUrl,
                    type: 'POST',
                    data: requestData,
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function(blob) {
                        detectDownloadErrorFromBlob(blob, function(blobErrorData) {
                            if (blobErrorData) {
                                if (blobErrorData.requiresLargeDownloadConfirmation && !allowLargeDownload) {
                                    restoreButtonState();
                                    if (confirmLargeDownload(blobErrorData)) {
                                        requestProxyDownload(true);
                                    } else {
                                        showDownloadStatus('Download canceled.', false);
                                    }
                                    return;
                                }

                                showDownloadErrorMessage(blobErrorData.message);
                                restoreButtonState();
                                return;
                            }

                            downloadBlobToFile(blob, filename);
                            showDownloadStatus('Download started.', false);
                            restoreButtonState();
                        });
                    },
                    error: function(xhr, _status, error) {
                        var ajaxErrorData = getAjaxErrorData(xhr);
                        if (ajaxErrorData) {
                            if (ajaxErrorData.requiresLargeDownloadConfirmation && !allowLargeDownload) {
                                restoreButtonState();
                                if (confirmLargeDownload(ajaxErrorData)) {
                                    requestProxyDownload(true);
                                } else {
                                    showDownloadStatus('Download canceled.', false);
                                }
                                return;
                            }

                            showDownloadErrorMessage(ajaxErrorData.message);
                            restoreButtonState();
                            return;
                        }

                        console.error('Download failed:', error);

                        // Fallback: Try direct link with download attribute
                        var link = document.createElement('a');
                        link.href = mediaUrl;
                        link.download = filename;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        showDownloadStatus('Opening direct download...', false);
                        restoreButtonState();
                    }
                });
            }

            fetchFilenameForDownload(mediaType, mediaUrl, downloadIndex, function(resolvedFilename) {
                filename = resolvedFilename;
                requestProxyDownload(false);
            });
        });
    }

    // Helper: Setup slideshow progress bar
    function setupAutoplayProgress(swiper, $container) {
        var $progressBar = $container.find('.swiper-slideshow-progress-bar');
        var $progressContainer = $container.find('.swiper-slideshow-progress');
        var progressInterval = null;
        var progressCycleToken = 0;

        // Hide progress bar initially if autoplay is not running
        if (!swiper.autoplay || !swiper.autoplay.running) {
            $progressContainer.css('display', 'none');
        }

        function startProgress() {
            // Clear any existing interval
            if (progressInterval) {
                clearInterval(progressInterval);
            }

            // Get current autoplay delay
            var delay = swiper.params.autoplay.delay;

            if (!delay || delay <= 0) {
                return;
            }

            // Show progress bar
            $progressContainer.css('display', 'block');

            // Reset to 100%
            $progressBar.css({
                'transform': 'scaleX(1)',
                'transition': 'none'
            });

            // Adjust animation duration so slide transition starts just as progress completes
            // Make progress bar last almost the entire delay, ending right when slide transitions
            var progressDuration = delay + 500;
            if (progressDuration < 0) progressDuration = delay;
            var cycleToken = ++progressCycleToken;

            // Start animation on next paint instead of forcing synchronous reflow.
            runOnNextPaint(function() {
                if (cycleToken !== progressCycleToken || !$progressBar.length) {
                    return;
                }
                $progressBar.css({
                    'transform': 'scaleX(0)',
                    'transition': 'transform ' + progressDuration + 'ms linear'
                });
            });
        }

        function stopProgress() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
            progressCycleToken++;

            // Reset to full width and hide
            $progressBar.css({
                'transform': 'scaleX(1)',
                'transition': 'none'
            });
            $progressContainer.css('display', 'none');
        }

        function holdProgressAtStart() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
            progressCycleToken++;

            // Reset to full width and keep visible (used for hover-pause parity with gallery mode).
            $progressBar.css({
                'transform': 'scaleX(1)',
                'transition': 'none'
            });
            $progressContainer.css('display', 'block');
        }

        function updateProgressVisibility() {
            // Only show in fullscreen when autoplay is running
            if (swiper.autoplay && swiper.autoplay.running) {
                startProgress();
            } else {
                stopProgress();
            }
        }

        // Listen to autoplay events
        swiper.on('autoplayStart', startProgress);
        swiper.on('autoplayStop', stopProgress);
        swiper.on('autoplayPause', holdProgressAtStart);
        swiper.on('autoplayResume', startProgress);
        swiper.on('slideChange', function() {
            if (swiper.autoplay && swiper.autoplay.running) {
                startProgress();
            }
        });

        // Start progress bar after a delay to ensure autoplay has started
        setTimeout(function() {
            if (swiper.autoplay && swiper.autoplay.running) {
                startProgress();
            }
        }, 500);

        return {
            start: startProgress,
            stop: stopProgress,
            updateVisibility: updateProgressVisibility
        };
    }

    // Helper: Setup play/pause button
    function setupPlayPauseButton(swiper, $container) {
        var $playPauseBtn = $container.find('.swiper-button-play-pause');

        // Update button state based on autoplay status
        function updateButtonState() {
            if (swiper.autoplay && swiper.autoplay.running) {
                $playPauseBtn.addClass('playing');
            } else {
                $playPauseBtn.removeClass('playing');
            }
        }

        // Toggle play/pause
        function togglePlayPause() {
			if (swiper.autoplay) {
				// Explicit user action - clear interrupted state
				clearCountdownRing($container);
				if (swiper.autoplay.running) {
					swiper.autoplay.stop();
					jzsaDebug('⏸️ Autoplay paused');
				} else {
					swiper.autoplay.start();
					jzsaDebug('▶️ Autoplay started');
				}
                updateButtonState();
            }
        }

        // Click handler
        $playPauseBtn.on('click', function(e) {
            e.stopPropagation();
            togglePlayPause();
        });

        // Listen to autoplay events to update button state
        swiper.on('autoplayStart', updateButtonState);
        swiper.on('autoplayStop', updateButtonState);
        swiper.on('autoplayPause', updateButtonState);
        swiper.on('autoplayResume', updateButtonState);

        // Initialize button state
        updateButtonState();

        // Return toggle function for keyboard binding
        return togglePlayPause;
    }

    // Helper: Setup fullscreen switch handlers
    function setupFullscreenSwitchHandlers(swiper, $container, params) {
        if (params && params.interactionLock) {
            return;
        }

        // When entering fullscreen from carousel mode, ensure we focus the
        // exact slide the user clicked in the preview (multi-slide) view.
        function focusClickedSlide(e) {
            if (params.mode !== 'carousel') {
                return;
            }

            var $clickedSlide = $(e.target).closest('.swiper-slide');
            if (!$clickedSlide.length) {
                return;
            }

            // In loop mode Swiper duplicates slides. Use the real slide index
            // stored in data-swiper-slide-index to avoid jumping to the
            // duplicated copy at the end of the wrapper.
            var realIndexAttr = $clickedSlide.attr('data-swiper-slide-index');
            var realIndex = realIndexAttr != null ? parseInt(realIndexAttr, 10) : NaN;

            if (!isNaN(realIndex) && realIndex >= 0) {
                if (swiper.params.loop && typeof swiper.slideToLoop === 'function') {
                    swiper.slideToLoop(realIndex, 0, false);
                } else {
                    swiper.slideTo(realIndex, 0, false);
                }
            } else {
                // Fallback: use DOM index if real index is not available
                var clickedIndex = $clickedSlide.index();
                if (clickedIndex >= 0 && clickedIndex < swiper.slides.length) {
                    swiper.slideTo(clickedIndex, 0, false);
                }
            }
        }

        // FULLSCREEN SWITCH HANDLERS
		if (params.fullscreenToggle === 'click') {
			// Single-click toggles fullscreen (enter and exit)
			$container.on('click', function(e) {
				if (!shouldIgnoreClick(e.target)) {
					e.preventDefault();

					if (!isFullscreen()) {
						focusClickedSlide(e);
						jzsaDebug('🔍 Single-click entering fullscreen');
						applyFullscreenAutoplaySettings(swiper, {
							fullscreenSlideshow: params.fullscreenSlideshow,
							fullscreenSlideshowDelay: params.fullscreenSlideshowDelay,
							slideshowPausedByInteraction: params.slideshowPausedByInteraction
						});
					} else {
						jzsaDebug('🔍 Single-click exiting fullscreen');
					}

					toggleFullscreen($container[0], params.showHintsOnFullscreen);
                }
            });
		} else if (params.fullscreenToggle === 'double-click') {
			// Double-click toggles fullscreen (both enter and exit)
			$container.on('dblclick', function(e) {
				if (!shouldIgnoreClick(e.target)) {
					e.preventDefault();
					clearPendingNavClick();

					if (!isFullscreen()) {
						focusClickedSlide(e);
						jzsaDebug('🔍 Double-click entering fullscreen');
						applyFullscreenAutoplaySettings(swiper, {
							fullscreenSlideshow: params.fullscreenSlideshow,
							fullscreenSlideshowDelay: params.fullscreenSlideshowDelay,
							slideshowPausedByInteraction: params.slideshowPausedByInteraction
						});
					} else {
						jzsaDebug('🔍 Double-click exiting fullscreen');
					}

					toggleFullscreen($container[0], params.showHintsOnFullscreen);
				}
			});

			// Mobile double-tap
			$container.on('touchend', function(e) {
				if (!shouldIgnoreClick(e.target)) {
					handleDoubleTap(e, function(evt) {
						clearPendingNavClick();

						if (!isFullscreen()) {
							focusClickedSlide(evt);
							jzsaDebug('🔍 Double-tap entering fullscreen');
							applyFullscreenAutoplaySettings(swiper, {
								fullscreenSlideshow: params.fullscreenSlideshow,
								fullscreenSlideshowDelay: params.fullscreenSlideshowDelay,
								slideshowPausedByInteraction: params.slideshowPausedByInteraction
							});
						} else {
							jzsaDebug('🔍 Double-tap exiting fullscreen');
						}

						toggleFullscreen($container[0], params.showHintsOnFullscreen);
					});
				}
			});
		}

		// TOUCH BUTTON REVEAL: show link/download on tap; ignore scroll/swipe gestures.
		// Passive listeners so iOS never has to wait for JS before committing a scroll frame.
		if (hasTouchInput()) {
			var touchRevealTimer = null;
			var sliderEl = $container[0];
			sliderEl.addEventListener('touchstart', function(e) {
				var t = e.touches[0];
				$container.data('jzsaRevealTouchX', t.clientX).data('jzsaRevealTouchY', t.clientY);
			}, { passive: true });
			sliderEl.addEventListener('touchend', function(e) {
				var startX = $container.data('jzsaRevealTouchX');
				var startY = $container.data('jzsaRevealTouchY');
				$container.removeData('jzsaRevealTouchX jzsaRevealTouchY');
				if (startX == null) return;
				var t = e.changedTouches[0];
				if (Math.abs(t.clientX - startX) > 8 || Math.abs(t.clientY - startY) > 8) return;
				$container.addClass('jzsa-touch-active');
				clearTimeout(touchRevealTimer);
				touchRevealTimer = setTimeout(function() {
					$container.removeClass('jzsa-touch-active');
				}, 3000);
			}, { passive: true });
			sliderEl.addEventListener('touchcancel', function() {
				$container.removeData('jzsaRevealTouchX jzsaRevealTouchY');
			}, { passive: true });
		}

		// FULLSCREEN NAVIGATION CURSOR: left/right chevron cursors in fullscreen
		// hint at click-to-navigate (button-only and double-click modes only).
		// Uses a dynamic <style> element with a none→real two-frame kick to
		// force the browser to repaint the cursor (browsers skip repainting
		// when the cursor value is unchanged on a stationary mouse).
		var CURSOR_PREV = 'url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'22\' height=\'22\'%3E%3Cpath d=\'M14 5.6L8.4 11.2l5.6 5.6\' fill=\'none\' stroke=\'black\' stroke-width=\'2.8\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3E%3Cpath d=\'M14 5.6L8.4 11.2l5.6 5.6\' fill=\'none\' stroke=\'white\' stroke-width=\'1.4\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3E%3C/svg%3E") 11 11, w-resize';
		var CURSOR_NEXT = 'url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'22\' height=\'22\'%3E%3Cpath d=\'M8.4 5.6l5.6 5.6-5.6 5.6\' fill=\'none\' stroke=\'black\' stroke-width=\'2.8\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3E%3Cpath d=\'M8.4 5.6l5.6 5.6-5.6 5.6\' fill=\'none\' stroke=\'white\' stroke-width=\'1.4\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3E%3C/svg%3E") 11 11, e-resize';
		var navCursorActive = false;

		if (params.fullscreenToggle !== 'click' && params.fullscreenToggle !== 'disabled') {
			var lastMouseX = -1;
			var cursorStyleEl = document.createElement('style');
			$container[0].appendChild(cursorStyleEl);
			var containerId = $container.attr('id');
			var cursorKickTimer = null;

			function applyNavCursor() {
				if (!isFullscreen() || lastMouseX < 0) {
					if (navCursorActive) {
						cursorStyleEl.textContent = '';
						navCursorActive = false;
					}
					return;
				}
				var rect = $container[0].getBoundingClientRect();
				var isLeft = (lastMouseX - rect.left) < rect.width / 2;
				var cursor = isLeft ? CURSOR_PREV : CURSOR_NEXT;
				// Two-frame kick: set 'none' first, then the real cursor on the
				// next animation frame to force a browser cursor repaint.
				cursorStyleEl.textContent =
					'#' + containerId + ' .swiper-slide { cursor: none !important; }';
				if (cursorKickTimer) cancelAnimationFrame(cursorKickTimer);
				cursorKickTimer = requestAnimationFrame(function() {
					cursorStyleEl.textContent =
						'#' + containerId + ' .swiper-slide { cursor: ' + cursor + ' !important; }';
				});
				navCursorActive = true;
			}

			$container.on('mousemove', function(e) {
				lastMouseX = e.clientX;
				applyNavCursor();
			});

			// Guard: re-apply every 500ms while in fullscreen to catch any
			// cursor resets caused by Swiper or browser re-layouts.
			var cursorGuardInterval = null;
			$(document).on('fullscreenchange webkitfullscreenchange', function() {
				if (isFullscreen()) {
					if (!cursorGuardInterval) {
						cursorGuardInterval = setInterval(applyNavCursor, 500);
					}
				} else {
					if (cursorGuardInterval) {
						clearInterval(cursorGuardInterval);
						cursorGuardInterval = null;
					}
				}
				setTimeout(applyNavCursor, 50);
			});
			swiper.on('slideChangeTransitionEnd', applyNavCursor);
		}

		// FULLSCREEN NAVIGATION: single click navigates in fullscreen (all modes).
		// When fullscreenToggle is double-click, delay navigation so a double-click
		// to exit fullscreen does not trigger a spurious navigate first.
		var navClickTimer = null;
		var NAV_CLICK_DELAY = params.fullscreenToggle === 'double-click' ? 250 : 0;

		function clearPendingNavClick() {
			if (navClickTimer) {
				clearTimeout(navClickTimer);
				navClickTimer = null;
			}
		}

		if (NAV_CLICK_DELAY > 0) {
			$container.on('dblclick.jzsaNavGuard', function() {
				clearPendingNavClick();
			});
		}

		$container.on('click', function(e) {
				if (!shouldIgnoreClick(e.target) && isFullscreen()) {
					// Single-click mode uses click to exit fullscreen, not navigate.
					if (params.fullscreenToggle === 'click') {
						return;
					}

					// Video slides: clickToPlay is disabled, so clicks on the
					// video area are free for navigation (plyr controls are
					// already filtered by shouldIgnoreClick above).

					e.preventDefault();
					var clickX = e.originalEvent ? e.originalEvent.clientX : e.clientX;
					var containerRect = $container[0].getBoundingClientRect();
					var relativeX = clickX - containerRect.left;
					var direction = relativeX < containerRect.width / 2 ? 'prev' : 'next';

				if (NAV_CLICK_DELAY > 0) {
					// Keep only one pending navigation click timer. Without this,
					// two click events in a double-click could leave one uncanceled.
					clearPendingNavClick();
					navClickTimer = setTimeout(function() {
						navClickTimer = null;
						pauseAutoplayOnInteraction(swiper, params);
						jzsaDebug('🔍 Fullscreen click: navigate ' + direction);
						if (direction === 'prev') {
							swiper.slidePrev(300);
						} else {
							swiper.slideNext(300);
						}
					}, NAV_CLICK_DELAY);
				} else {
					pauseAutoplayOnInteraction(swiper, params);
					jzsaDebug('🔍 Fullscreen click: navigate ' + direction);
					if (direction === 'prev') {
						swiper.slidePrev(300);
					} else {
						swiper.slideNext(300);
					}
				}
			}
		});
    }

    // Helper: Setup video playback handling for Swiper
    function setupVideoHandling(swiper, $container, fullscreenChangeParams) {
        var videoAutoplayPaused = false;

        // Capture-phase listener: on video slides, only block clicks that land
        // directly on the <video> element (to protect native controls). Clicks on
        // the surrounding wrapper area pass through to normal navigation handlers.
        $container[0].addEventListener('click', function(e) {
            var $activeSlide = $(swiper.slides[swiper.activeIndex]);
            if ($activeSlide.attr('data-media-type') !== 'video') {
                return;
            }

            // Let clicks on Swiper UI controls and Plyr controls pass through
            if (shouldIgnoreClick(e.target)) {
                return;
            }

            // Only block clicks on the actual video element or Plyr's video wrapper.
            // In fullscreen, let clicks through so they reach the navigation handler.
            if (e.target.tagName === 'VIDEO' || $(e.target).closest('.plyr__video-wrapper').length > 0) {
                if (!isFullscreen()) {
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                }
            }

        }, true);


        // Pause all videos in the gallery except the one in the active slide
        function pauseAllVideos(exceptSlide) {
            $container.find('.jzsa-video-player').each(function() {
                var videoEl = this;
                if (exceptSlide && $(videoEl).closest('.swiper-slide')[0] === exceptSlide) {
                    return;
                }
                if (!videoEl.paused) {
                    videoEl.pause();
                }
            });
        }

        // When a video starts playing, pause slider autoplay (no inactivity timer -
        // autoplay stays paused for the entire duration of playback)
        // Use capture phase because media events don't bubble
        $container[0].addEventListener('play', function() {
            if (swiper.autoplay && swiper.autoplay.running) {
                videoAutoplayPaused = true;
                swiper.autoplay.stop();
                // Clear any existing inactivity timer so it doesn't fire mid-video
                if (fullscreenChangeParams.inactivityTimer) {
                    clearTimeout(fullscreenChangeParams.inactivityTimer);
                }
                jzsaDebug('⏸️ Autoplay paused for video playback');
            }
        }, true);

        // When a video ends or is paused, start the inactivity countdown
        // to resume autoplay after the configured timeout (default 30s)
        function startAutoplayCountdown() {
            if (!videoAutoplayPaused) return;
            videoAutoplayPaused = false;

            // Clear any existing inactivity timer
            if (fullscreenChangeParams.inactivityTimer) {
                clearTimeout(fullscreenChangeParams.inactivityTimer);
            }

            fullscreenChangeParams.slideshowPausedByInteraction = true;
            if (fullscreenChangeParams.slideshowAutoresume !== 'disabled') {
                var timeoutMs = (fullscreenChangeParams.slideshowAutoresume || 30) * 1000;
                showCountdownRing($container, fullscreenChangeParams.slideshowAutoresume || 30);
                fullscreenChangeParams.inactivityTimer = setTimeout(function() {
                    if (fullscreenChangeParams.slideshowPausedByInteraction && swiper.autoplay && !swiper.autoplay.running) {
                        fullscreenChangeParams.slideshowPausedByInteraction = false;
                        clearCountdownRing($container);
                        swiper.autoplay.start();
                        jzsaDebug('▶️ Autoplay resumed after video inactivity timeout');
                    }
                }, timeoutMs);
                jzsaDebug('⏱️ Autoplay autoresume countdown started (' + (fullscreenChangeParams.slideshowAutoresume || 30) + 's)');
            }
        }
        $container[0].addEventListener('ended', startAutoplayCountdown, true);
        $container[0].addEventListener('pause', startAutoplayCountdown, true);

        // Toggle class on container so CSS can disable nav overlays on video slides
        function updateVideoActiveClass() {
            var $activeSlide = $(swiper.slides[swiper.activeIndex]);
            $container.toggleClass('jzsa-video-active', $activeSlide.attr('data-media-type') === 'video');
        }

        // Set initial state
        updateVideoActiveClass();

        // On slide change: pause videos on previous slide, prep video on new slide
        swiper.on('slideChange', function() {
            updateVideoActiveClass();
            pauseAllVideos(null);
            videoAutoplayPaused = false;

            // Pause and reset all video slides
            $container.find('.jzsa-slide-video').each(function() {
                var $video = $(this).find('.jzsa-video-player');
                var videoEl = $video[0];
                if (videoEl && videoEl.src) {
                    videoEl.pause();
                    videoEl.currentTime = 0;
                }
            });
        });

    }

    // Helper: Setup progressive image loading
    function setupProgressiveImageLoading(swiper, $container) {
        var fullImageRegistry = {};
        var fullscreenGateToken = 0;
        var hasQueuedFullscreenBulkPreload = false;

        function setFullscreenQualityGate(active) {
            if (!$container || !$container.length) {
                return;
            }

            if (active) {
                if ($container.find('.jzsa-loader').length === 0) {
                    $container.append(buildLoaderHtml('Loading full-resolution photo...'));
                }
                $container.addClass('jzsa-fullscreen-waiting');
            } else {
                $container.removeClass('jzsa-fullscreen-waiting');
            }
        }

        function ensureFullImageCached(fullSrc, onLoad, onError) {
            if (!fullSrc) {
                return;
            }

            var entry = fullImageRegistry[fullSrc];
            if (entry && entry.state === 'loaded') {
                if (typeof onLoad === 'function') {
                    onLoad();
                }
                return;
            }

            if (entry && entry.state === 'error') {
                if (typeof onError === 'function') {
                    onError();
                }
                return;
            }

            if (entry && entry.state === 'loading') {
                if (typeof onLoad === 'function') {
                    entry.onLoad.push(onLoad);
                }
                if (typeof onError === 'function') {
                    entry.onError.push(onError);
                }
                return;
            }

            entry = {
                state: 'loading',
                onLoad: [],
                onError: []
            };
            if (typeof onLoad === 'function') {
                entry.onLoad.push(onLoad);
            }
            if (typeof onError === 'function') {
                entry.onError.push(onError);
            }
            fullImageRegistry[fullSrc] = entry;

            var tempImg = new Image();
            tempImg.onload = function() {
                entry.state = 'loaded';
                entry.onLoad.forEach(function(cb) {
                    cb();
                });
                entry.onLoad = [];
                entry.onError = [];
            };
            tempImg.onerror = function() {
                entry.state = 'error';
                entry.onError.forEach(function(cb) {
                    cb();
                });
                entry.onLoad = [];
                entry.onError = [];
            };
            tempImg.src = fullSrc;
        }

        function markImageAsFullLoaded($img, fullSrc) {
            if (!$img || !$img.length || !fullSrc) {
                return;
            }
            if ($img.attr('src') !== fullSrc) {
                $img.attr('src', fullSrc);
            }
            $img.data('full-loaded', true);
            $img.data('full-prefetched', true);
            $img.addClass('jzsa-full-loaded');
            $img.removeClass('jzsa-image-error');
        }

        function preloadFullImage($img) {
            if (!$img || !$img.length) {
                return;
            }

            var fullSrc = $img.attr('data-full-src');
            if (!fullSrc || $img.data('full-prefetched') || $img.data('full-loaded') === true) {
                return;
            }

            ensureFullImageCached(fullSrc, function() {
                if (!$img.closest('html').length) {
                    return;
                }
                $img.data('full-prefetched', true);
            });
        }

        // Swap to full source (never preview) once the full image is cached.
        function loadFullImage($img, onReady, onError) {
            if (!$img || !$img.length) {
                return;
            }

            var fullSrc = $img.attr('data-full-src');
            if (!fullSrc) {
                return;
            }

            if ($img.data('full-loaded') === true && $img.attr('src') === fullSrc) {
                return;
            }

            ensureFullImageCached(
                fullSrc,
                function() {
                    if (!$img.closest('html').length) {
                        return;
                    }
                    markImageAsFullLoaded($img, fullSrc);
                    if (typeof onReady === 'function') {
                        onReady($img, fullSrc);
                    }
                },
                function() {
                    if (!$img.closest('html').length) {
                        return;
                    }
                    $img.addClass('jzsa-image-error');
                    $img.data('full-loaded', 'error');
                    // console.warn('Failed to load image:', fullSrc);
                    if (typeof onError === 'function') {
                        onError($img, fullSrc);
                    }
                }
            );
        }

        function getSlideImageAt(index) {
            if (!swiper.slides || !swiper.slides[index]) {
                return null;
            }
            var $img = $(swiper.slides[index]).find('.jzsa-progressive-image');
            return $img.length ? $img : null;
        }

        function processOffsets(offsets, processor) {
            if (!swiper || !swiper.slides || !swiper.slides.length) {
                return;
            }
            var currentIndex = swiper.activeIndex;
            offsets.forEach(function(offset) {
                var targetIndex = currentIndex + offset;
                var $img = getSlideImageAt(targetIndex);
                if ($img) {
                    processor($img);
                }
            });
        }

        function preloadAllSlidesFull() {
            if (!swiper || !swiper.slides || !swiper.slides.length) {
                return;
            }

            $(swiper.slides).each(function() {
                var $img = $(this).find('.jzsa-progressive-image');
                preloadFullImage($img);
            });
        }

        function isRenderedAsFull($img) {
            if (!$img || !$img.length) {
                return true;
            }
            var fullSrc = $img.attr('data-full-src');
            if (!fullSrc) {
                return true;
            }
            return $img.data('full-loaded') === true && $img.attr('src') === fullSrc;
        }

        function runProgressiveLoadingCycle() {
            var inFullscreen = !$container || !$container.length || isFullscreen($container[0]);

            if (inFullscreen) {
                var $currentImg = getSlideImageAt(swiper.activeIndex);
                var needsCurrentGate = !isRenderedAsFull($currentImg);

                if (needsCurrentGate) {
                    fullscreenGateToken += 1;
                    var gateToken = fullscreenGateToken;
                    setFullscreenQualityGate(true);
                    loadFullImage(
                        $currentImg,
                        function() {
                            if (gateToken !== fullscreenGateToken) {
                                return;
                            }
                            setFullscreenQualityGate(false);
                        },
                        function() {
                            if (gateToken !== fullscreenGateToken) {
                                return;
                            }
                            setFullscreenQualityGate(false);
                        }
                    );
                } else {
                    setFullscreenQualityGate(false);
                }

                // Fullscreen rule: keep visible and nearby queue in full-res.
                processOffsets([1, -1, 2, -2], loadFullImage);

                // Aggressively preload the queue for smooth looping in fullscreen.
                processOffsets([3, -3, 4, -4], preloadFullImage);
                if (!hasQueuedFullscreenBulkPreload) {
                    hasQueuedFullscreenBulkPreload = true;
                    preloadAllSlidesFull();
                }
                return;
            }

            fullscreenGateToken += 1;
            setFullscreenQualityGate(false);

            // Outside fullscreen, keep previews on screen but warm the cache for likely next images.
            processOffsets([0, 1, -1, 2], preloadFullImage);
        }

        runProgressiveLoadingCycle();

        swiper.on('slideChangeTransitionStart', function() {
            runProgressiveLoadingCycle();
        });
        swiper.on('slideChange', function() {
            runProgressiveLoadingCycle();
        });

        if ($container && $container.length) {
            var containerId = ($container.attr('id') || 'gallery').replace(/[^a-zA-Z0-9_-]/g, '');
            var progressiveNamespace = '.jzsaProgressive-' + containerId;

            $(document).off(
                'fullscreenchange' + progressiveNamespace +
                ' webkitfullscreenchange' + progressiveNamespace +
                ' mozfullscreenchange' + progressiveNamespace +
                ' MSFullscreenChange' + progressiveNamespace
            );
            $(document).on(
                'fullscreenchange' + progressiveNamespace +
                ' webkitfullscreenchange' + progressiveNamespace +
                ' mozfullscreenchange' + progressiveNamespace +
                ' MSFullscreenChange' + progressiveNamespace,
                function() {
                    window.setTimeout(function() {
                        runProgressiveLoadingCycle();
                    }, 0);
                }
            );

            // iPhone pseudo fullscreen fallback.
            $container.off('jzsa:fullscreen-state' + progressiveNamespace);
            $container.on('jzsa:fullscreen-state' + progressiveNamespace, function() {
                window.setTimeout(function() {
                    runProgressiveLoadingCycle();
                }, 0);
            });
        }
    }

    // Helper: Build Swiper configuration object
    function buildSwiperConfig(params) {
        var interactionLock = !!params.interactionLock;

        var config = {
            // Initial slide
            initialSlide: params.initialSlide,
            // Navigation
            navigation: {
                nextEl: '#' + params.galleryId + ' .swiper-button-next',
                prevEl: '#' + params.galleryId + ' .swiper-button-prev',
            },

        // Pagination: driven by info-bottom format string.
        // Supports {item}, {items}, and all per-photo placeholders.
        pagination: (function() {
            var base = {
                el: '#' + params.galleryId + ' .swiper-pagination'
            };

            base.type = 'custom';
            base.renderCustom = function(swiper, current, total) {
                var $swiperEl = $(swiper.el);
                var isFs = $swiperEl.hasClass('jzsa-is-fullscreen') || $swiperEl.hasClass('jzsa-pseudo-fullscreen');
                var format = isFs
                    ? ($swiperEl.attr('data-fullscreen-info-bottom') || $swiperEl.attr('data-info-bottom') || '')
                    : ($swiperEl.attr('data-info-bottom') || '');

                if (!format) {
                    $swiperEl.find('.swiper-pagination').hide();
                    return '';
                }

                var albumTitle = $swiperEl.attr('data-album-title') || '';
                var photoIndex = getSwiperPhotoIndex(swiper);
                var photo = getContainerPhoto($swiperEl, photoIndex);
                var text = resolveInfoPlaceholders(format, photo,
                    $.extend(buildItemPlaceholders(params.mode, swiper, current, total), { albumTitle: albumTitle })
                );

                $swiperEl.find('.swiper-pagination').toggle(text !== '');
                return text;
            };

            return base;
        })(),

            // Default: no zoom unless enabled per mode.
            zoom: false,

            // Autoplay - enable module if either normal or fullscreen mode is not disabled
            autoplay: (params.slideshow !== 'disabled' || params.fullscreenSlideshow !== 'disabled') ? {
                delay: params.slideshowDelay * MILLISECONDS_PER_SECOND,
                disableOnInteraction: false,
            } : false,

            // Loop
            loop: params.loop,

            // Performance
            lazy: {
                loadPrevNext: true,
                loadPrevNextAmount: params.LAZY_LOAD_PREV_NEXT_AMOUNT,
            },

            // Speed
            speed: params.SWIPER_SPEED,

            // Touch
            grabCursor: !interactionLock,
            allowTouchMove: !interactionLock,
            simulateTouch: !interactionLock,
            noSwiping: true,
            noSwipingSelector: '.plyr__controls, .plyr__control',

            // Keyboard control
            keyboard: {
                enabled: !interactionLock,
            },

            // Mouse wheel
            mousewheel: false,
        };

        // Mode-specific configuration
        if (params.mode === 'carousel') {
            // Carousel mode: show multiple slides at once
            config.slidesPerView = params.SLIDES_MOBILE;
            config.spaceBetween = params.SPACING_MOBILE;
            config.centeredSlides = false;
            config.effect = 'slide';

            // Responsive breakpoints for carousel
            config.breakpoints = {
                [params.BREAKPOINT_MOBILE]: {
                    slidesPerView: params.SLIDES_MOBILE,
                    spaceBetween: params.SPACING_MOBILE
                },
                [params.BREAKPOINT_TABLET]: {
                    slidesPerView: params.SLIDES_TABLET,
                    spaceBetween: params.SPACING_TABLET
                },
                [params.BREAKPOINT_DESKTOP]: {
                    slidesPerView: params.SLIDES_DESKTOP,
                    spaceBetween: params.SPACING_DESKTOP
                }
            };

            // Enforce minimum of 2 visible slides in carousel mode (when enough photos exist)
            if (config.slidesPerView < 2) {
                config.slidesPerView = 2;
            }
            [''+params.BREAKPOINT_MOBILE, ''+params.BREAKPOINT_TABLET, ''+params.BREAKPOINT_DESKTOP].forEach(function(bp) {
                if (config.breakpoints[bp] && config.breakpoints[bp].slidesPerView < 2) {
                    config.breakpoints[bp].slidesPerView = 2;
                }
            });

            // Disable zoom in carousel mode (doesn't work well with multiple slides)
            config.zoom = false;
        } else {
            // Single mode: Single photo viewer with touch pinch zoom support.
            config.slidesPerView = 1;
            config.spaceBetween = 0;
            config.centeredSlides = true;

            // Historically, iOS/WebKit (≤ 16) used fade instead of slide to avoid
            // transient black frames during transform-based slide transitions.
            // However, testing on iOS 15.7 (Safari and Chrome) showed no black frames,
            // suggesting the issue was either fixed in WebKit long ago or never reliably
            // reproducible. The fade fallback is therefore disabled below - retain it
            // here in case it needs to be re-enabled for a specific future device report.
            //
            // if (isOldIosWebkit()) {
            //     config.effect = 'fade';
            //     config.fadeEffect = { crossFade: true };
            // } else {
            //     config.effect = 'slide';
            // }
            config.effect = 'slide';

            // Keep zoom touch-only and disable double-click/double-tap toggles.
            // This avoids conflicts with fullscreen switch/navigation gestures.
            if (hasTouchInput()) {
                config.zoom = {
                    maxRatio: params.ZOOM_MAX_RATIO,
                    minRatio: params.ZOOM_MIN_RATIO,
                    toggle: false
                };
            } else {
                config.zoom = false;
            }
        }

        return config;
    }

    // ============================================================================
    // SWIPER INITIALIZATION
    // ============================================================================

    function initializeSwiper(container, mode) {
        // Swiper configuration constants
        var SWIPER_SPEED = 600; // ms - transition speed between slides
        var ZOOM_MAX_RATIO = 3; // Maximum zoom level
        var ZOOM_MIN_RATIO = 1; // Minimum zoom level (no zoom)
        var LAZY_LOAD_PREV_NEXT_AMOUNT = 2; // Number of slides to preload
        var DEFAULT_SLIDESHOW_DELAY_FALLBACK = 5; // seconds - fallback slideshow delay if not specified

            // Carousel mode breakpoint constants
        var BREAKPOINT_MOBILE = 320;
        var BREAKPOINT_TABLET = 640;
        var BREAKPOINT_DESKTOP = 1024;
        var SLIDES_MOBILE = 2;
        var SLIDES_TABLET = 2;
        var SLIDES_DESKTOP = 3;
        var SPACING_MOBILE = 10;
        var SPACING_TABLET = 15;
        var SPACING_DESKTOP = 20;

        var $container = $(container);
        var galleryId = $container.attr('id');

        // Parse configuration from data attributes
        var allPhotosJson = $container.attr('data-all-photos');
        var allPhotos = allPhotosJson ? JSON.parse(allPhotosJson) : [];

        // Guard: Swiper (especially with loop:true) crashes on empty containers
        if (!allPhotos.length) {
            // console.warn('[JZSA] No photos for gallery "' + galleryId + '", skipping Swiper init.');
            return null;
        }
        var totalCount = parseInt($container.attr('data-total-count')) || allPhotos.length;

        // On older iOS/WebKit stacks, very large galleries (e.g. 300 photos) can
        // be unstable. Cap the number of photos there, but allow the full set
        // everywhere else.
        if (isOldIosWebkit() && allPhotos.length > OLD_IOS_MAX_PHOTOS) {
            allPhotos = allPhotos.slice(0, OLD_IOS_MAX_PHOTOS);
            // console.log('[JZSA] Old iOS/WebKit detected – capping photos to', OLD_IOS_MAX_PHOTOS, 'out of', totalCount);
        }

        var inlineShowNavigationSetting = readBooleanDataAttr($container, 'data-show-navigation', true);
        var fullscreenShowNavigationSetting = readBooleanDataAttr($container, 'data-fullscreen-show-navigation', inlineShowNavigationSetting);
        var inlineControlsColorSetting = $container.attr('data-controls-color') || '';
        var fullscreenControlsColorSetting = $container.attr('data-fullscreen-controls-color') || inlineControlsColorSetting;
        var inlineVideoControlsColorSetting = $container.attr('data-video-controls-color') || '';
        var fullscreenVideoControlsColorSetting = $container.attr('data-fullscreen-video-controls-color') || inlineVideoControlsColorSetting;
        var inlineInfoFontSizeSetting = getInfoFontSizePx($container, 'data-info-font-size', 12);
        var fullscreenInfoFontSizeSetting = getInfoFontSizePx($container, 'data-fullscreen-info-font-size', inlineInfoFontSizeSetting);
        var inlineInfoFontFamilySetting = getInfoFontFamily($container, 'data-info-font-family', '');
        var fullscreenInfoFontFamilySetting = getInfoFontFamily($container, 'data-fullscreen-info-font-family', inlineInfoFontFamilySetting);
        var inlineVideoControlsAutohideSetting = readBooleanDataAttr($container, 'data-video-controls-autohide', false);
        var fullscreenVideoControlsAutohideSetting = readBooleanDataAttr(
            $container,
            'data-fullscreen-video-controls-autohide',
            inlineVideoControlsAutohideSetting
        );
        var inlineSlideshowAutoresumeSetting = parseSlideshowAutoresumeAttr($container.attr('data-slideshow-autoresume'), 30);
        var fullscreenSlideshowAutoresumeSetting = parseSlideshowAutoresumeAttr(
            $container.attr('data-fullscreen-slideshow-autoresume'),
            inlineSlideshowAutoresumeSetting
        );

        var config = {
            // Photo data
            allPhotos: allPhotos,
            totalCount: totalCount,

            // Slideshow settings ('auto', 'manual', or 'disabled')
            slideshow: $container.attr('data-slideshow') || 'disabled',
            slideshowDelay: parseInt($container.attr('data-slideshow-delay')) || DEFAULT_SLIDESHOW_DELAY_FALLBACK,
            fullscreenSlideshow: $container.attr('data-fullscreen-slideshow') || 'disabled',
            fullscreenSlideshowDelay: parseInt($container.attr('data-fullscreen-slideshow-delay')) || 5,
            slideshowAutoresume: inlineSlideshowAutoresumeSetting,
            fullscreenSlideshowAutoresume: fullscreenSlideshowAutoresumeSetting,

            // Display settings
            loop: allPhotos.length >= 4, // Loop requires enough slides for Swiper to work properly
            interactionLock: $container.attr('data-interaction-lock') === 'true',
            fullscreenToggle:
                $container.attr('data-fullscreen-toggle') || 'button-only',
            startAt: $container.attr('data-start-at') || '1',
            showNavigation: inlineShowNavigationSetting,
            fullscreenShowNavigation: fullscreenShowNavigationSetting,
            controlsColor: inlineControlsColorSetting,
            fullscreenControlsColor: fullscreenControlsColorSetting,
            videoControlsColor: inlineVideoControlsColorSetting,
            fullscreenVideoControlsColor: fullscreenVideoControlsColorSetting,
            infoFontSize: inlineInfoFontSizeSetting,
            fullscreenInfoFontSize: fullscreenInfoFontSizeSetting,
            infoFontFamily: inlineInfoFontFamilySetting,
            fullscreenInfoFontFamily: fullscreenInfoFontFamilySetting,
            videoControlsAutohide: inlineVideoControlsAutohideSetting,
            fullscreenVideoControlsAutohide: fullscreenVideoControlsAutohideSetting,
            albumTitle: $container.attr('data-album-title') || '',
            initialSlide: 0,

            // Mosaic settings
            mosaic: $container.attr('data-mosaic') === 'true',
            mosaicPosition: $container.attr('data-mosaic-position') || 'right',
            mosaicCount: parseInt($container.attr('data-mosaic-count'), 10) || 0, // 0 = auto
            mosaicGap: parseInt($container.attr('data-mosaic-gap'), 10) || 8,
            mosaicOpacity: parseFloat($container.attr('data-mosaic-opacity')) || 0.3
        };

        // Safe default: show inline play/pause only when normal-mode slideshow is enabled.
        // Fullscreen controls are handled separately via .jzsa-is-fullscreen styling.
        $container.toggleClass('jzsa-inline-slideshow-controls', config.slideshow !== 'disabled');

        // Calculate initial slide based on startAt setting
        var startAtRaw = (config.startAt || 'random').toString().toLowerCase();
        if (startAtRaw === 'random') {
            if (totalCount > 0) {
                config.initialSlide = Math.floor(Math.random() * totalCount);
            }
        } else {
            var requested = parseInt(startAtRaw, 10);
            if (isNaN(requested) || requested < 1 || requested > totalCount) {
                requested = 1; // Out of range or invalid -> start at 1
            }
            config.initialSlide = requested - 1;
        }

        // Extract config values for easier access
        var allPhotos = config.allPhotos;
        var totalCount = config.totalCount;
        var slideshow = config.slideshow;
        var slideshowDelay = config.slideshowDelay;
        var fullscreenSlideshow = config.fullscreenSlideshow;
        var fullscreenSlideshowDelay = config.fullscreenSlideshowDelay;
        var slideshowAutoresume = config.slideshowAutoresume;
        var fullscreenSlideshowAutoresume = config.fullscreenSlideshowAutoresume;
        var loop = config.loop;
        var interactionLock = config.interactionLock;
        var fullscreenToggle = interactionLock ? 'disabled' : config.fullscreenToggle;
        var showLinkButton = readBooleanDataAttr($container, 'data-show-link-button', false);
        var showDownloadButton = readBooleanDataAttr($container, 'data-show-download-button', false);
        var albumUrl = $container.attr('data-album-url') || '';
        var showNavigation = config.showNavigation;
        var fullscreenShowNavigation = config.fullscreenShowNavigation;
        var controlsColor = config.controlsColor;
        var fullscreenControlsColor = config.fullscreenControlsColor;
        var videoControlsColor = config.videoControlsColor;
        var fullscreenVideoControlsColor = config.fullscreenVideoControlsColor;
        var videoControlsAutohide = config.videoControlsAutohide;
        var fullscreenVideoControlsAutohide = config.fullscreenVideoControlsAutohide;
        var albumTitle = config.albumTitle;
        var initialSlide = config.initialSlide;
        var mosaic = config.mosaic;
        var mosaicPosition = config.mosaicPosition;
        var mosaicCount = config.mosaicCount;
        var mosaicOpacity = config.mosaicOpacity;

        // console.log('📸 Initializing Swiper for gallery:', galleryId);
        // console.log('  - Mode:', mode);
        // console.log('  - Total photos:', totalCount);
        // console.log('  - Initial photos loaded:', allPhotos.length);
        // console.log('  - startAt setting:', startAt, '=> initial slide index (0-based):', initialSlide, '/', totalCount);

        // Debug: Log configuration values
        // console.log('🔍 Configuration debug:');
        // console.log('  - data-fullscreen-slideshow-delay attribute:', $container.attr('data-fullscreen-slideshow-delay'));
        // console.log('  - fullscreenSlideshowDelay parsed:', fullscreenSlideshowDelay);
        // console.log('  - fullscreenSlideshowDelay in ms:', fullscreenSlideshowDelay * MILLISECONDS_PER_SECOND);

        // Two-phase single bootstrap caused visible re-render flicker on some pages.
        // Keep slider mode one-pass for stable rendering.
        var useDeferredSingleFirstPaint = false;
        var shouldUseLazyHints = mode === 'slider';
        var showCarouselTileFullscreenButtons =
            mode === 'carousel' && !interactionLock && fullscreenToggle !== 'disabled';
        var showCarouselTileLinkButtons =
            mode === 'carousel' && !interactionLock && showLinkButton && !!albumUrl;
        var showCarouselTileDownloadButtons =
            mode === 'carousel' && !interactionLock && showDownloadButton;
        $container.toggleClass('jzsa-carousel-tile-fs-enabled', showCarouselTileFullscreenButtons);
        var zoneFormats = readInfoZoneFormats($container);
        $container.attr('data-has-active-bottom-center', (zoneFormats.inline['info-bottom'] || '') ? 'true' : 'false');
        var slidesRenderOptions = {
            mode: mode,
            showCarouselTileFullscreenButtons: showCarouselTileFullscreenButtons,
            showCarouselTileLinkButtons: showCarouselTileLinkButtons,
            showCarouselTileDownloadButtons: showCarouselTileDownloadButtons,
            carouselAlbumUrl: albumUrl,
            carouselZoneFormats: zoneFormats,
            carouselTotalCount: totalCount,
            carouselAlbumTitle: albumTitle,
            lazyHints: shouldUseLazyHints,
            eagerIndex: initialSlide
        };

        function renderSwiperBootstrapSlides() {
            if (useDeferredSingleFirstPaint) {
                var bootstrapPhoto = allPhotos[initialSlide] || allPhotos[0];
                $container.find('.swiper-wrapper').html(
                    buildSlidesHtml(bootstrapPhoto ? [bootstrapPhoto] : [], {
                        mode: mode,
                        showCarouselTileFullscreenButtons: showCarouselTileFullscreenButtons,
                        lazyHints: true,
                        eagerIndex: 0
                    })
                );
                return;
            }

            $container.find('.swiper-wrapper').html(buildSlidesHtml(allPhotos, slidesRenderOptions));
        }

        renderSwiperBootstrapSlides();

        // --------------------------------------------------------------------
        // Mosaic thumbnail strip
        // --------------------------------------------------------------------

        var mosaicSwiper = null;
        var mosaicPageSize = 1;
        if (mosaic) {
            var $mosaicContainer = $('#' + galleryId + '-mosaic');
            if ($mosaicContainer.length) {
                var isVerticalMosaic = (mosaicPosition === 'left' || mosaicPosition === 'right');
                if (isVerticalMosaic) {
                    $mosaicContainer.addClass('jzsa-mosaic-vertical');
                }
                // Build thumb slides
                var thumbSlidesHtml = '';
                allPhotos.forEach(function(photo) {
                    var thumbUrl = photo.thumb || photo.preview || photo.full;
                    thumbSlidesHtml += '<div class="swiper-slide">' +
                        '<span class="jzsa-mosaic-thumb-inner">' +
                        '<img src="' + thumbUrl + '" alt="Thumb" loading="lazy" />' +
                        '</span></div>';
                });
                $mosaicContainer.find('.swiper-wrapper').html(thumbSlidesHtml);

                var mosaicGap = config.mosaicGap;
                $mosaicContainer[0].style.setProperty('--jzsa-mosaic-opacity', mosaicOpacity);
                var MOSAIC_TARGET_THUMB_SIZE = 100; // px – ideal thumb size for auto-count

                // Calculate how many thumbs fit in the available space.
                function computeAutoMosaicCount() {
                    var $wrapper = $mosaicContainer.parent();
                    var mobile = window.innerWidth <= 480;
                    var availableLength;
                    if (mobile) {
                        availableLength = $wrapper.width() || 400;
                    } else if (mosaicPosition === 'left' || mosaicPosition === 'right') {
                        var wrapperH = $wrapper.height();
                        var albumH = $container.height();
                        availableLength = (wrapperH > 0 ? wrapperH : albumH) || 300;
                    } else {
                        availableLength = $wrapper.width() || 400;
                    }
                    // How many thumbs of MOSAIC_TARGET_THUMB_SIZE fit?
                    var fitCount = Math.floor((availableLength + mosaicGap) / (MOSAIC_TARGET_THUMB_SIZE + mosaicGap));
                    return Math.max(1, fitCount);
                }

                function getEffectiveMosaicCount() {
                    return mosaicCount > 0 ? mosaicCount : computeAutoMosaicCount();
                }

                function buildMosaicConfig(startSlide) {
                    var mobile = window.innerWidth <= 480;
                    var count = getEffectiveMosaicCount();
                    var cfg = {
                        spaceBetween: mosaicGap,
                        freeMode: false,
                        watchSlidesProgress: true,
                        slideToClickedSlide: true,
                        initialSlide: startSlide,
                        watchOverflow: true,
                        slidesPerView: count,
                        slidesPerGroup: count
                    };

                    if (mobile) {
                        cfg.direction = 'horizontal';
                    } else if (mosaicPosition === 'left' || mosaicPosition === 'right') {
                        cfg.direction = 'vertical';
                    } else {
                        cfg.direction = 'horizontal';
                    }

                    return cfg;
                }

                function resizeMosaic() {
                    var mobile = window.innerWidth <= 480;
                    if (mobile) {
                        $mosaicContainer.css({ width: '', height: '' });
                        return;
                    }
                    var $wrapper = $mosaicContainer.parent();
                    var count = getEffectiveMosaicCount();
                    var availableLength;
                    if (mosaicPosition === 'left' || mosaicPosition === 'right') {
                        var wrapperH = $wrapper.height();
                        var albumH = $container.height();
                        availableLength = (wrapperH > 0 ? wrapperH : albumH) || 300;
                    } else {
                        availableLength = $wrapper.width() || 400;
                    }
                    var thumbSize = (availableLength - (mosaicGap * (count - 1))) / count;
                    thumbSize = Math.max(1, Math.floor(thumbSize));
                    if (mosaicPosition === 'left' || mosaicPosition === 'right') {
                        $mosaicContainer.css({ width: thumbSize + 'px', height: '' });
                    } else {
                        $mosaicContainer.css({ width: '', height: thumbSize + 'px' });
                    }
                    if (mosaicSwiper && !mosaicSwiper.destroyed) {
                        mosaicSwiper.update();
                    }
                }

                mosaicSwiper = new Swiper('#' + galleryId + '-mosaic', buildMosaicConfig(initialSlide));
                resizeMosaic();
                mosaicPageSize = getEffectiveMosaicCount();

                // Deferred layout pass to pick up correct dimensions after first paint.
                var raf = window.requestAnimationFrame || function(cb) { window.setTimeout(cb, 16); };
                raf(function() { raf(resizeMosaic); });

                // Navigation arrows
                if (!$mosaicContainer.find('.jzsa-mosaic-arrow-prev').length) {
                    $mosaicContainer.append(
                        '<button type="button" class="jzsa-mosaic-arrow jzsa-mosaic-arrow-prev swiper-button-prev" aria-label="Previous page"></button>' +
                        '<button type="button" class="jzsa-mosaic-arrow jzsa-mosaic-arrow-next swiper-button-next" aria-label="Next page"></button>'
                    );
                    $mosaicContainer.on('click', '.jzsa-mosaic-arrow-prev', function(e) {
                        e.preventDefault();
                        if (mosaicSwiper && !mosaicSwiper.destroyed) { mosaicSwiper.slidePrev(); }
                    });
                    $mosaicContainer.on('click', '.jzsa-mosaic-arrow-next', function(e) {
                        e.preventDefault();
                        if (mosaicSwiper && !mosaicSwiper.destroyed) { mosaicSwiper.slideNext(); }
                    });
                }

                // Resize observer for dynamic layout
                if (typeof ResizeObserver !== 'undefined') {
                    var wrapperEl = $mosaicContainer.parent()[0];
                    if (wrapperEl) {
                        var mosaicResizeObserver = new ResizeObserver(function() { resizeMosaic(); });
                        mosaicResizeObserver.observe(wrapperEl);
                    }
                }

                // Window resize: rebuild mosaic direction if orientation changes
                $(window).on('resize.jzsaMosaic-' + galleryId, function() {
                    if (!mosaicSwiper || mosaicSwiper.destroyed) { return; }
                    resizeMosaic();
                    var newCfg = buildMosaicConfig(0);
                    if (mosaicSwiper.params.direction !== newCfg.direction) {
                        var currentSlide = mosaicSwiper.activeIndex;
                        mosaicSwiper.destroy(true, true);
                        mosaicSwiper = new Swiper('#' + galleryId + '-mosaic', buildMosaicConfig(currentSlide));
                    }
                });
            }
        }

        // --------------------------------------------------------------------
        // Loading overlay: show a subtle loader until the first image is ready
        // --------------------------------------------------------------------

        if ($container.find('.jzsa-loader').length === 0) {
            $container.append(buildLoaderHtml('Loading content...'));
        }
        $container
            .removeClass('jzsa-loaded jzsa-loader-visible')
            .addClass('jzsa-loader-pending');
        triggerGalleryIntroFade($container);

        var galleryLoaderShownAt = 0;
        var jzsaHasMarkedLoaded = false;
        var jzsaLoaderShowTimer = null;
        var jzsaLoaderFallbackTimer = null;
        var jzsaLoaderCommitTimer = null;
        function clearGalleryLoaderTimers() {
            if (jzsaLoaderShowTimer) {
                window.clearTimeout(jzsaLoaderShowTimer);
                jzsaLoaderShowTimer = null;
            }
            if (jzsaLoaderFallbackTimer) {
                window.clearTimeout(jzsaLoaderFallbackTimer);
                jzsaLoaderFallbackTimer = null;
            }
            if (jzsaLoaderCommitTimer) {
                window.clearTimeout(jzsaLoaderCommitTimer);
                jzsaLoaderCommitTimer = null;
            }
        }
        function commitGalleryLoaded() {
            clearGalleryLoaderTimers();
            $container
                .removeClass('jzsa-loader-pending jzsa-loader-visible')
                .addClass('jzsa-loaded');
        }
        function markGalleryLoaded() {
            if (jzsaHasMarkedLoaded) return;
            jzsaHasMarkedLoaded = true;

            var minVisibleRemaining = 0;
            if (galleryLoaderShownAt > 0) {
                minVisibleRemaining = LOADER_MIN_VISIBLE_MS - (Date.now() - galleryLoaderShownAt);
            }

            if (minVisibleRemaining > 0) {
                jzsaLoaderCommitTimer = window.setTimeout(commitGalleryLoaded, minVisibleRemaining);
            } else {
                commitGalleryLoaded();
            }
        }

        function watchInitialPreviewLoad() {
            if (jzsaHasMarkedLoaded) {
                return;
            }

            var $previewImages = $container.find('.jzsa-progressive-image');
            if (!$previewImages.length) {
                window.setTimeout(markGalleryLoaded, 800);
                return;
            }

            var initialIndexInMarkup = $previewImages.length === allPhotos.length ? initialSlide : 0;
            var $initialPreviewImg = $previewImages.eq(initialIndexInMarkup);
            if (!$initialPreviewImg.length) {
                $initialPreviewImg = $previewImages.first();
            }

            var imgEl = $initialPreviewImg[0];
            $initialPreviewImg.off('.jzsaLoader');
            if (imgEl.complete && imgEl.naturalWidth > 0) {
                markGalleryLoaded();
                return;
            }

            $initialPreviewImg.one('load.jzsaLoader', function() {
                markGalleryLoaded();
            });
            $initialPreviewImg.one('error.jzsaLoader', function() {
                window.setTimeout(markGalleryLoaded, 800);
            });
        }

        jzsaLoaderShowTimer = window.setTimeout(function() {
            if (jzsaHasMarkedLoaded) {
                return;
            }
            galleryLoaderShownAt = Date.now();
            $container.addClass('jzsa-loader-visible');
        }, LOADER_SHOW_DELAY_MS);

        watchInitialPreviewLoad();
        jzsaLoaderFallbackTimer = window.setTimeout(markGalleryLoaded, 3500);

        function finalizeSwiperInitialization() {
            // Swiper configuration - gather all parameters
            var swiperConfig = buildSwiperConfig({
                mode: mode,
                galleryId: galleryId,
                initialSlide: initialSlide,
                albumTitle: albumTitle,
                ZOOM_MAX_RATIO: ZOOM_MAX_RATIO,
                ZOOM_MIN_RATIO: ZOOM_MIN_RATIO,
                slideshow: slideshow,
                fullscreenSlideshow: fullscreenSlideshow,
                slideshowDelay: slideshowDelay,
                loop: loop,
                LAZY_LOAD_PREV_NEXT_AMOUNT: LAZY_LOAD_PREV_NEXT_AMOUNT,
                SWIPER_SPEED: SWIPER_SPEED,
                SLIDES_MOBILE: SLIDES_MOBILE,
                SPACING_MOBILE: SPACING_MOBILE,
                BREAKPOINT_MOBILE: BREAKPOINT_MOBILE,
                SLIDES_TABLET: SLIDES_TABLET,
                SPACING_TABLET: SPACING_TABLET,
                BREAKPOINT_TABLET: BREAKPOINT_TABLET,
                SLIDES_DESKTOP: SLIDES_DESKTOP,
                SPACING_DESKTOP: SPACING_DESKTOP,
                BREAKPOINT_DESKTOP: BREAKPOINT_DESKTOP,
                fullscreenToggle: fullscreenToggle,
                interactionLock: interactionLock
            });

            // Add thumbs config if mosaic is enabled
            if (mosaicSwiper) {
                swiperConfig.thumbs = {
                    swiper: mosaicSwiper
                };
            }

            // Initialize Swiper (pass the DOM element directly to avoid selector resolution issues)
            var swiper = new Swiper($container[0], swiperConfig);
            swipers[galleryId] = swiper;

            // Sync mosaic with main gallery: scroll mosaic to keep active thumb visible
            if (mosaicSwiper) {
                swiper.on('slideChange', function() {
                    if (mosaicSwiper && !mosaicSwiper.destroyed) {
                        // With loop=true, activeIndex includes cloned slides.
                        // Use realIndex so mosaic paging stays aligned with real photos.
                        var activeRealIndex = (typeof swiper.realIndex === 'number') ? swiper.realIndex : swiper.activeIndex;
                        var pageStart = Math.floor(activeRealIndex / mosaicPageSize) * mosaicPageSize;
                        mosaicSwiper.slideTo(pageStart);
                    }
                });
            }

            // Defensive guard: keep double-click/double-tap zoom disabled.
            // Pinch zoom remains available on touch devices via config.zoom.
            if (swiper.zoom && swiper.zoom.toggle) {
                swiper.zoom.toggle = function() {};
            }

            // All info boxes: rendered at container level (like pagination).
            // Updated on every slide change so they don't animate with slides.
            var containerBoxes = [];
            var $topInfoStack = $('<div class="jzsa-info-stack jzsa-info-stack-top"></div>');
            var $bottomInfoStack = $('<div class="jzsa-info-stack jzsa-info-stack-bottom"></div>');
            var hasTopStackBoxes = false;
            var hasBottomStackBoxes = false;

            function registerContainerBox(boxName) {
                if (boxName === 'info-bottom') {
                    return; // Driven by Swiper pagination, not a DOM box.
                }
                var boxFmt = $container.attr('data-' + boxName) || '';
                var boxFsFmt = $container.attr('data-fullscreen-' + boxName) || boxFmt;
                if (!boxFmt && !boxFsFmt) {
                    return;
                }
                var $box = $('<div class="jzsa-info-box jzsa-' + boxName + '"></div>');
                if (boxName === 'info-top' || boxName === 'info-top-secondary') {
                    $topInfoStack.append($box);
                    hasTopStackBoxes = true;
                } else {
                    $bottomInfoStack.append($box);
                    hasBottomStackBoxes = true;
                }
                containerBoxes.push({ $el: $box, fmt: boxFmt, fsFmt: boxFsFmt });
            }

            for (var bi = 0; bi < SAFE_INFO_TOP_ORDER.length; bi++) {
                registerContainerBox(SAFE_INFO_TOP_ORDER[bi]);
            }
            for (var bj = 0; bj < SAFE_INFO_BOTTOM_ORDER.length; bj++) {
                registerContainerBox(SAFE_INFO_BOTTOM_ORDER[bj]);
            }

            if (hasTopStackBoxes) {
                $container.append($topInfoStack);
            }
            if (hasBottomStackBoxes) {
                $container.append($bottomInfoStack);
            }

            function updateAllInfoBoxes() {
                var isFs = $container.hasClass('jzsa-is-fullscreen') || $container.hasClass('jzsa-pseudo-fullscreen');
                var photoIndex = getSwiperPhotoIndex(swiper);
                var photo = getContainerPhoto($container, photoIndex);
                var photosJson = $container.attr('data-all-photos');
                var photos = [];
                var total = 0;
                if (photosJson) {
                    try {
                        photos = JSON.parse(photosJson);
                        total = photos.length;
                    } catch (e) {
                        total = 0;
                    }
                }
                var itemPlaceholders = total ? buildItemPlaceholders(mode, swiper, photoIndex + 1, total) : { item: '', items: '' };
                var albumTitle = $container.attr('data-album-title') || '';
                for (var j = 0; j < containerBoxes.length; j++) {
                    var cb = containerBoxes[j];
                    var fmt = isFs ? cb.fsFmt : cb.fmt;
                    var text = resolveInfoPlaceholders(fmt, photo,
                        $.extend(itemPlaceholders, { albumTitle: albumTitle })
                    );
                    cb.$el.text(text).toggle(text !== '');
                }
            }

            if (containerBoxes.length) {
                updateAllInfoBoxes();
                swiper.on('slideChange', updateAllInfoBoxes);
                swiper.on('slideChangeTransitionEnd', updateAllInfoBoxes);
                swiper.on('resize', updateAllInfoBoxes);
                $container.on('jzsa:fullscreen-state', updateAllInfoBoxes);
                $container.on('jzsa:exif-update', updateAllInfoBoxes);
            } else {
                updateAllInfoBoxes();
            }

            if (mode === 'carousel') {
                updateCarouselTileInfoBoxes($container, swiper, zoneFormats);
                swiper.on('slideChange', function() {
                    updateCarouselTileInfoBoxes($container, swiper, zoneFormats);
                });
                swiper.on('slideChangeTransitionEnd', function() {
                    updateCarouselTileInfoBoxes($container, swiper, zoneFormats);
                });
                swiper.on('resize', function() {
                    updateCarouselTileInfoBoxes($container, swiper, zoneFormats);
                });
                $container.on('jzsa:fullscreen-state', function() {
                    updateCarouselTileInfoBoxes($container, swiper, zoneFormats);
                });
                $container.on('jzsa:exif-update', function() {
                    updateCarouselTileInfoBoxes($container, swiper, zoneFormats);
                });
            }

            if (mode === 'gallery') {
                $container.on('jzsa:exif-update', function() {
                    updateGalleryThumbnailInfoBoxes($container);
                });
            }

            // Stop autoplay initially if inline slideshow is not 'auto' (e.g. 'manual' or 'disabled',
            // but fullscreen or manual mode still needs the autoplay module available)
            if (slideshow !== 'auto' && swiper.autoplay && swiper.autoplay.running) {
                swiper.autoplay.stop();
                // console.log('⏸️  Autoplay stopped (not auto-start inline)');
            }

            // Create hint system for fullscreen navigation guidance (not needed for button-only)
            var showHintsOnFullscreen = null;
            if (fullscreenToggle !== 'button-only') {
                showHintsOnFullscreen = createHintSystem(galleryId);
            }

            var slideshowPausedByInteraction = false;

            // ------------------------------------------------------------------------
            // Fullscreen change event listeners (all browser prefixes)
            // ------------------------------------------------------------------------

            // Create params object for handleFullscreenChange
            var fullscreenChangeParams = {
                galleryId: galleryId,
                mode: mode,
                fullscreenSlideshow: fullscreenSlideshow,
                fullscreenSlideshowDelay: fullscreenSlideshowDelay,
                slideshow: slideshow,
                slideshowDelay: slideshowDelay,
                slideshowPausedByInteraction: slideshowPausedByInteraction,
                slideshowAutoresume: slideshowAutoresume,
                inlineSlideshowAutoresume: slideshowAutoresume,
                fullscreenSlideshowAutoresume: fullscreenSlideshowAutoresume,
                showNavigation: showNavigation,
                fullscreenShowNavigation: fullscreenShowNavigation,
                controlsColor: controlsColor,
                fullscreenControlsColor: fullscreenControlsColor,
                videoControlsColor: videoControlsColor,
                fullscreenVideoControlsColor: fullscreenVideoControlsColor,
                infoFontSize: config.infoFontSize,
                fullscreenInfoFontSize: config.fullscreenInfoFontSize,
                infoFontFamily: config.infoFontFamily,
                fullscreenInfoFontFamily: config.fullscreenInfoFontFamily,
                videoControlsAutohide: videoControlsAutohide,
                fullscreenVideoControlsAutohide: fullscreenVideoControlsAutohide,
                browserPrefix: null,
                _fullscreenActive: false,
                // For carousel mode: remember original layout so we can
                // temporarily switch to a single-slide view in fullscreen.
                originalSlidesPerView: null,
                originalBreakpoints: null,
                originalCenteredSlides: null
            };
            applyFullscreenDisplayOverrides($container[0], swiper, fullscreenChangeParams, false);

            // Fullscreen change event listeners - Standard API (Chrome, Firefox, Edge)
            document.addEventListener('fullscreenchange', function() {
                fullscreenChangeParams.browserPrefix = null;
                fullscreenChangeParams.slideshowPausedByInteraction = slideshowPausedByInteraction;
                handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
            });

            // Webkit prefix (Safari, older Chrome/Android)
            document.addEventListener('webkitfullscreenchange', function() {
                fullscreenChangeParams.browserPrefix = 'webkit';
                fullscreenChangeParams.slideshowPausedByInteraction = slideshowPausedByInteraction;
                handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
            });

            // Mozilla prefix (Firefox)
            document.addEventListener('mozfullscreenchange', function() {
                fullscreenChangeParams.browserPrefix = 'moz';
                fullscreenChangeParams.slideshowPausedByInteraction = slideshowPausedByInteraction;
                handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
            });

            // MS prefix (old IE/Edge)
            document.addEventListener('MSFullscreenChange', function() {
                fullscreenChangeParams.browserPrefix = 'ms';
                fullscreenChangeParams.slideshowPausedByInteraction = slideshowPausedByInteraction;
                handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
            });
            var fullscreenStateNamespace = '.jzsaFullscreenState-' + galleryId;
            $container.off('jzsa:fullscreen-state' + fullscreenStateNamespace);
            $container.on('jzsa:fullscreen-state' + fullscreenStateNamespace, function() {
                fullscreenChangeParams.browserPrefix = null;
                handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
            });

            // ------------------------------------------------------------------------
            // Fullscreen switch handlers (click/double-click to enter/exit fullscreen)
            // ------------------------------------------------------------------------

            var fullscreenParams = {
                mode: mode,
                fullscreenToggle: fullscreenToggle,
                interactionLock: interactionLock,
                fullscreenSlideshow: fullscreenSlideshow,
                fullscreenSlideshowDelay: fullscreenSlideshowDelay,
                slideshowPausedByInteraction: slideshowPausedByInteraction,
                showHintsOnFullscreen: showHintsOnFullscreen
            };

            setupFullscreenButton(swiper, $container, fullscreenParams);
            setupDownloadButton(swiper, $container);
            setupAutoplayProgress(swiper, $container);
            var togglePlayPause = setupPlayPauseButton(swiper, $container);
            setupFullscreenSwitchHandlers(swiper, $container, fullscreenParams);

            // Desktop UX parity with gallery mode: pause inline autoplay while hovering
            // over the gallery, then resume when the pointer leaves.
            var hoverPauseSupported = !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);
            var slideshowPausedByHover = false;
            var hoverPausedWithStopFallback = false;
            var suppressHoverPauseUntilLeave = false;
            var slideshowHoverNamespace = '.jzsaSlideshowHover-' + galleryId;
            var slideshowHoverFullscreenNamespace = '.jzsaSlideshowHoverFs-' + galleryId;
            $container.off('mouseenter' + slideshowHoverNamespace + ' mouseleave' + slideshowHoverNamespace);

            function resetHoverAutoplayFlags() {
                slideshowPausedByHover = false;
                hoverPausedWithStopFallback = false;
            }

            function shouldBlockHoverPause() {
                return suppressHoverPauseUntilLeave || isFullscreen($container[0]) || fullscreenChangeParams.slideshowPausedByInteraction;
            }

            function setInlineAutoplayDelay() {
                if (!swiper.autoplay) {
                    return;
                }
                var normalDelay = slideshowDelay * MILLISECONDS_PER_SECOND;
                swiper.params.autoplay.delay = normalDelay;
                swiper.autoplay.delay = normalDelay;
            }

            function resumeInlineAutoplay(forceStart) {
                if (!swiper.autoplay || fullscreenChangeParams.slideshowPausedByInteraction) {
                    return;
                }

                setInlineAutoplayDelay();

                if (!forceStart && typeof swiper.autoplay.resume === 'function' && swiper.autoplay.paused) {
                    swiper.autoplay.resume();
                    return;
                }

                if (!swiper.autoplay.running || forceStart) {
                    swiper.autoplay.start();
                }
            }

            function handleHoverFullscreenExit() {
                // Some browsers emit pointer/hover events while exiting fullscreen.
                // Ignore hover-based pause until cursor leaves the gallery once.
                suppressHoverPauseUntilLeave = true;
                resetHoverAutoplayFlags();

                // Defensive recovery: after fullscreen exit, ensure inline autoplay is
                // actually advancing (some browsers can leave autoplay in paused/running state).
                if (slideshow !== 'auto' || !swiper.autoplay || fullscreenChangeParams.slideshowPausedByInteraction) {
                    return;
                }

                window.setTimeout(function() {
                    if (isFullscreen($container[0]) || !swiper.autoplay || fullscreenChangeParams.slideshowPausedByInteraction) {
                        return;
                    }

                    resumeInlineAutoplay(false);
                }, 80);
            }

            $(document).off(
                'fullscreenchange' + slideshowHoverFullscreenNamespace +
                ' webkitfullscreenchange' + slideshowHoverFullscreenNamespace +
                ' mozfullscreenchange' + slideshowHoverFullscreenNamespace +
                ' MSFullscreenChange' + slideshowHoverFullscreenNamespace
            );
            $(document).on(
                'fullscreenchange' + slideshowHoverFullscreenNamespace +
                ' webkitfullscreenchange' + slideshowHoverFullscreenNamespace +
                ' mozfullscreenchange' + slideshowHoverFullscreenNamespace +
                ' MSFullscreenChange' + slideshowHoverFullscreenNamespace,
                function() {
                    if (!isFullscreen($container[0])) {
                        handleHoverFullscreenExit();
                    }
                }
            );
            $container.off('jzsa:fullscreen-state' + slideshowHoverFullscreenNamespace);
            $container.on('jzsa:fullscreen-state' + slideshowHoverFullscreenNamespace, function(_e, isActive) {
                if (!isActive) {
                    handleHoverFullscreenExit();
                }
            });

            if (slideshow !== 'disabled' && hoverPauseSupported && swiper.autoplay && !interactionLock) {
                $container.on('mouseenter' + slideshowHoverNamespace, function() {
                    if (shouldBlockHoverPause()) {
                        return;
                    }

                    if (swiper.autoplay.running) {
                        slideshowPausedByHover = true;
                        if (typeof swiper.autoplay.pause === 'function') {
                            hoverPausedWithStopFallback = false;
                            swiper.autoplay.pause();
                        } else {
                            hoverPausedWithStopFallback = true;
                            swiper.autoplay.stop();
                        }
                    }
                });

                $container.on('mouseleave' + slideshowHoverNamespace, function() {
                    if (suppressHoverPauseUntilLeave) {
                        suppressHoverPauseUntilLeave = false;
                        resetHoverAutoplayFlags();
                        return;
                    }

                    if (!slideshowPausedByHover || shouldBlockHoverPause()) {
                        return;
                    }

                    slideshowPausedByHover = false;

                    // If autoplay is no longer running, user likely stopped it manually.
                    // Do not auto-resume in that case (except stop/start fallback mode).
                    if (!swiper.autoplay || !swiper.autoplay.running) {
                        if (hoverPausedWithStopFallback && swiper.autoplay && !fullscreenChangeParams.slideshowPausedByInteraction) {
                            resumeInlineAutoplay(true);
                        }
                        hoverPausedWithStopFallback = false;
                        return;
                    }

                    resumeInlineAutoplay(false);
                    hoverPausedWithStopFallback = false;
                });
            }

            // ------------------------------------------------------------------------
            // Image error handling - Add error handlers to all images
            // ------------------------------------------------------------------------

            $container.find('.jzsa-progressive-image').each(function() {
                var $img = $(this);
                // Handle errors on the preview image
                this.onerror = function() {
                    $img.addClass('jzsa-image-error');
                    // console.warn('Failed to load preview image:', $img.attr('src'));
                };
            });

            // ------------------------------------------------------------------------
            // Pause slideshow when user clicks navigation buttons
            // ------------------------------------------------------------------------

            $container.find('.swiper-button-next, .swiper-button-prev').on('click', function() {
                pauseAutoplayOnInteraction(swiper, fullscreenChangeParams);
            });

            // ------------------------------------------------------------------------
            // Pause slideshow on actual swipe/drag gestures (not plain clicks/taps)
            // ------------------------------------------------------------------------

            swiper.on('sliderFirstMove', function() {
                pauseAutoplayOnInteraction(swiper, fullscreenChangeParams);
            });

            // ------------------------------------------------------------------------
            // Keyboard handlers
            // ------------------------------------------------------------------------

            $(document).on('keydown', function(e) {
                if (interactionLock) {
                    return;
                }

                // Spacebar - play/pause toggle (only in fullscreen)
                if (e.key === ' ' || e.keyCode === 32) {
                    if (isFullscreen()) {
                        e.preventDefault(); // Prevent page scroll
                        togglePlayPause();
                    }
                }

                // Arrow keys - pause autoplay on navigation
                if (e.key === 'ArrowLeft' || e.keyCode === 37 || e.key === 'ArrowRight' || e.keyCode === 39) {
                    pauseAutoplayOnInteraction(swiper, fullscreenChangeParams);
                }
            });

            // ------------------------------------------------------------------------
            // Progressive image loading
            // ------------------------------------------------------------------------

            setupProgressiveImageLoading(swiper, $container);

            // ------------------------------------------------------------------------
            // Video playback handling
            // ------------------------------------------------------------------------

            setupVideoHandling(swiper, $container, fullscreenChangeParams);
            initPlyrInContainer($container);
            scheduleDurationPrefetch($container);
            scheduleExifPrefetch($container, zoneFormats);

            // CAROUSEL TOUCH REVEAL:
            // Passive listeners so iOS never blocks scroll. Same approach as gallery touch reveal.
            if (mode === 'carousel' && hasTouchInput()) {
                (function() {
                    var carouselEl = $container[0];
                    function closestSlide(target) {
                        return target && target.closest ? target.closest('.swiper-slide') : null;
                    }
                    function clearAllTouched() {
                        $container.find('.jzsa-slide-touched').each(function() {
                            clearTimeout($(this).data('jzsaSlideRevealTimer'));
                            $(this).removeData('jzsaSlideRevealTimer').removeClass('jzsa-slide-touched');
                        });
                    }
                    carouselEl.addEventListener('touchstart', function(e) {
                        var slide = closestSlide(e.target);
                        if (!slide) return;
                        var t = e.touches[0];
                        $(slide).data('jzsaRevealTouchX', t.clientX).data('jzsaRevealTouchY', t.clientY);
                    }, { passive: true });
                    carouselEl.addEventListener('touchend', function(e) {
                        var slide = closestSlide(e.target);
                        if (!slide) return;
                        // Skip Swiper loop clones - they are repositioned without transition events.
                        if (slide.classList.contains('swiper-slide-duplicate')) return;
                        var $slide = $(slide);
                        var startX = $slide.data('jzsaRevealTouchX');
                        var startY = $slide.data('jzsaRevealTouchY');
                        $slide.removeData('jzsaRevealTouchX jzsaRevealTouchY');
                        if (startX == null) return;
                        var t = e.changedTouches[0];
                        if (Math.abs(t.clientX - startX) > 8 || Math.abs(t.clientY - startY) > 8) return;
                        $container.find('.jzsa-slide-touched').not($slide).each(function() {
                            clearTimeout($(this).data('jzsaSlideRevealTimer'));
                            $(this).removeData('jzsaSlideRevealTimer').removeClass('jzsa-slide-touched');
                        });
                        $slide.addClass('jzsa-slide-touched');
                        clearTimeout($slide.data('jzsaSlideRevealTimer'));
                        $slide.data('jzsaSlideRevealTimer', setTimeout(function() {
                            $slide.removeData('jzsaSlideRevealTimer').removeClass('jzsa-slide-touched');
                        }, 3000));
                    }, { passive: true });
                    carouselEl.addEventListener('touchcancel', function(e) {
                        var slide = closestSlide(e.target);
                        if (!slide) return;
                        $(slide).removeData('jzsaRevealTouchX jzsaRevealTouchY');
                    }, { passive: true });
                    // Clear touched state when a swipe transition starts.
                    swiper.on('slideChangeTransitionStart', clearAllTouched);
                }());
            }

            // console.log('✅ Swiper initialized:', galleryId);
            // console.log('  - Normal mode slideshow:', slideshow ? 'Enabled (delay: ' + slideshowDelay + 's)' : 'Disabled');
            // console.log('  - Fullscreen mode autoplay:', fullscreenSlideshow ? 'Enabled (delay: ' + fullscreenSlideshowDelay + 's)' : 'Disabled');
            // console.log('  - Loop: Always enabled');
            // console.log('  - Zoom: Pinch-to-zoom on touch devices (double-click disabled)');
            // console.log('  - Fullscreen: ' + (fullscreenToggle === 'button-only' ? 'Button only' : fullscreenToggle === 'double-click' ? 'Double-click or button' : 'Click or button'));
            // console.log('  - Progressive loading: Preview → Full resolution');

            return swiper;
        }

        if (useDeferredSingleFirstPaint) {
            var deferInit = window.requestAnimationFrame || function(callback) {
                window.setTimeout(callback, 16);
            };
            deferInit(function() {
                if (!$container.closest('html').length) {
                    return;
                }
                $container.find('.swiper-wrapper').html(buildSlidesHtml(allPhotos, slidesRenderOptions));
                watchInitialPreviewLoad();
                finalizeSwiperInitialization();
            });
            return null;
        }

        return finalizeSwiperInitialization();
    }

    // ============================================================================
    // GALLERY MODE - thumbnail gallery + fullscreen slideshow
    // ============================================================================

    /**
     * Build a hidden player container for a thumbnail gallery.
     * Uses the same DOM structure as build_gallery_container in PHP so that
     * initializeSwiper can power it with the full feature set.
     *
     * @param  {jQuery} $galleryContainer The gallery container element.
     * @return {string} The player container ID.
     */
    function buildGallerySlideshow($galleryContainer) {
        var galleryId = $galleryContainer.attr('id');
        var slideshowId = galleryId + '-slideshow';

        // Build full player DOM structure (mirrors PHP build_gallery_container)
        var html =
            '<div id="' + slideshowId + '" class="jzsa-album swiper jzsa-gallery-slideshow jzsa-loader-pending jzsa-content-intro">' +
                '<div class="swiper-wrapper"></div>' +
                '<div class="swiper-button-prev"></div>' +
                '<div class="swiper-button-next"></div>' +
                '<div class="swiper-pagination"></div>' +
                '<button class="swiper-button-play-pause" title="Play/Pause (Space)"></button>' +
                '<div class="swiper-slideshow-progress"><div class="swiper-slideshow-progress-bar"></div></div>';

        // External link button (render if enabled in either inline or fullscreen contexts;
        // CSS decides visibility based on current fullscreen state).
        var showLink = $galleryContainer.attr('data-show-link-button') === 'true';
        var showFullscreenLink = readBooleanDataAttr(
            $galleryContainer,
            'data-fullscreen-show-link-button',
            showLink
        );
        var albumUrl = $galleryContainer.attr('data-album-url') || '';
        if ((showLink || showFullscreenLink) && albumUrl) {
            html += '<a href="' + albumUrl + '" target="_blank" rel="noopener noreferrer" ' +
                'class="swiper-button-external-link" title="Open in Google Photos"></a>';
        }

        // Download button (same visibility split logic as link button).
        var showDownload = $galleryContainer.attr('data-show-download-button') === 'true';
        var showFullscreenDownload = readBooleanDataAttr(
            $galleryContainer,
            'data-fullscreen-show-download-button',
            showDownload
        );
        if (showDownload || showFullscreenDownload) {
            html += '<button class="swiper-button-download" title="Download current media"></button>';
        }

        html += '<div class="swiper-button-fullscreen"></div>';
        html += '</div>';

        // Insert player after the gallery shell when present so controls can stay
        // overlaid on top of the gallery without affecting player placement.
        var $shell = $galleryContainer.parent('.jzsa-gallery-shell');
        if ($shell.length) {
            $shell.after(html);
        } else {
            $galleryContainer.after(html);
        }

        var $slideshow = $('#' + slideshowId);

        // Copy data attributes for initializeSwiper
        $slideshow.attr('data-all-photos', $galleryContainer.attr('data-all-photos'));
        $slideshow.attr('data-total-count', $galleryContainer.attr('data-total-count'));
        $slideshow.attr('data-mode', 'slider');
        $slideshow.attr('data-start-at', $galleryContainer.attr('data-start-at') || '1');
        // Gallery has no inline slideshow - use fullscreen slideshow settings
        $slideshow.attr('data-slideshow', 'disabled');

        // Forward player-relevant settings from the gallery container
        var forwardAttrs = [
            'data-fullscreen-slideshow',
            'data-fullscreen-slideshow-delay',
            'data-slideshow-autoresume',
            'data-fullscreen-slideshow-autoresume',
            'data-fullscreen-toggle',
            'data-interaction-lock',
            'data-show-navigation',
            'data-fullscreen-show-navigation',
            'data-album-title',
            'data-album-url',
            'data-image-fit',
            'data-fullscreen-image-fit',
            'data-background-color',
            'data-fullscreen-background-color',
            'data-controls-color',
            'data-fullscreen-controls-color',
            'data-video-controls-color',
            'data-fullscreen-video-controls-color',
            'data-video-controls-autohide',
            'data-fullscreen-video-controls-autohide',
            'data-show-download-button',
            'data-show-link-button',
            'data-fullscreen-show-download-button',
            'data-fullscreen-show-link-button',
            'data-download-size-warning',
            'data-info-font-size',
            'data-fullscreen-info-font-size',
            'data-info-font-family',
            'data-fullscreen-info-font-family',
            'data-info-top',
            'data-fullscreen-info-top',
            'data-info-top-secondary',
            'data-fullscreen-info-top-secondary',
            'data-info-bottom',
            'data-fullscreen-info-bottom',
            'data-has-active-bottom-center'
        ];
        for (var i = 0; i < forwardAttrs.length; i++) {
            var val = $galleryContainer.attr(forwardAttrs[i]);
            if (val !== undefined) {
                $slideshow.attr(forwardAttrs[i], val);
            }
        }

        // Forward --gallery-bg-color CSS custom property for fullscreen background
        // Prefer fullscreen-background-color for the slideshow (which is always fullscreen)
        var fsBgColor = $galleryContainer.attr('data-fullscreen-background-color');
        var bgColor = fsBgColor || $galleryContainer.attr('data-background-color');
        if (bgColor) {
            $slideshow[0].style.setProperty('--gallery-bg-color', bgColor);
        }
        var controlsColor = $galleryContainer.attr('data-fullscreen-controls-color') || $galleryContainer.attr('data-controls-color');
        if (controlsColor) {
            $slideshow[0].style.setProperty('--jzsa-controls-color', controlsColor);
            applyControlsColorToIcons('#' + $slideshow.attr('id'), controlsColor);
        }
        var videoControlsColor = $galleryContainer.attr('data-fullscreen-video-controls-color') || $galleryContainer.attr('data-video-controls-color');
        if (videoControlsColor) {
            $slideshow[0].style.setProperty('--jzsa-video-controls-color', videoControlsColor);
        }
        var infoFontSize = parseInt($galleryContainer.attr('data-fullscreen-info-font-size'), 10);
        if (isNaN(infoFontSize) || infoFontSize <= 0) {
            infoFontSize = parseInt($galleryContainer.attr('data-info-font-size'), 10);
        }
        if (!isNaN(infoFontSize) && infoFontSize > 0) {
            $slideshow[0].style.setProperty('--jzsa-info-font-size', infoFontSize + 'px');
        } else {
            var inheritedInfoFontSize = $galleryContainer[0].style.getPropertyValue('--jzsa-info-font-size');
            if (inheritedInfoFontSize) {
                $slideshow[0].style.setProperty('--jzsa-info-font-size', inheritedInfoFontSize);
            }
        }
        var infoFontFamily = ($galleryContainer.attr('data-fullscreen-info-font-family') || $galleryContainer.attr('data-info-font-family') || '').trim();
        if (infoFontFamily) {
            $slideshow[0].style.setProperty('--jzsa-info-font-family', infoFontFamily);
        } else {
            var inheritedInfoFontFamily = $galleryContainer[0].style.getPropertyValue('--jzsa-info-font-family');
            if (inheritedInfoFontFamily) {
                $slideshow[0].style.setProperty('--jzsa-info-font-family', inheritedInfoFontFamily);
            }
        }

        return slideshowId;
    }

    /**
     * Parse Google Photos aspect ratio from a URL like "…=w800-h600".
     * Returns width/height ratio, defaulting to 4/3.
     *
     * @param  {string} url Photo URL.
     * @return {number} Aspect ratio (width / height).
     */
    function parseAspectRatio(url) {
        var match = url.match(/=w(\d+)-h(\d+)/);
        if (match) {
            var w = parseInt(match[1], 10);
            var h = parseInt(match[2], 10);
            if (w > 0 && h > 0) {
                return w / h;
            }
        }
        return 4 / 3; // safe fallback
    }

    /**
     * Group photos into rows for a justified gallery.
     * Each row fills approximately `containerWidth` when photos are scaled to `targetHeight`.
     *
     * @param  {Array}  photos         Array of {photo, ratio, index}.
     * @param  {number} containerWidth Available pixel width.
     * @param  {number} targetHeight   Desired row height in pixels.
     * @param  {number} gap            Pixel gap between photos.
     * @return {Array}  Array of rows, each row being an array of photo items.
     */
    function buildJustifiedRows(photos, containerWidth, targetHeight, gap) {
        var rows = [];
        var currentRow = [];
        var currentRowWidth = 0;

        photos.forEach(function(item) {
            var photoWidth = targetHeight * item.ratio;
            var gapBefore = currentRow.length > 0 ? gap : 0;

            // Start a new row when adding this photo would exceed ~110% of container width
            if (currentRow.length > 0 && currentRowWidth + gapBefore + photoWidth > containerWidth * 1.1) {
                rows.push(currentRow);
                currentRow = [];
                currentRowWidth = 0;
                gapBefore = 0;
            }

            currentRow.push(item);
            currentRowWidth += gapBefore + photoWidth;
        });

        if (currentRow.length > 0) {
            rows.push(currentRow);
        }

        return rows;
    }

    /**
     * Render a grid CSS gallery of thumbnails into `$container`.
     *
     * @param {jQuery} $container Gallery album element.
     * @param {Array}  pageItems  Array of objects: { photo, index }.
     */
    function buildUniformGallery($container, pageItems, options) {
        var renderOptions = options || {};
        var placeholderCount = parseInt(renderOptions.placeholderCount, 10);
        if (isNaN(placeholderCount) || placeholderCount < 0) {
            placeholderCount = 0;
        }
        var tileHeight = parseFloat(renderOptions.tileHeight);
        if (!isFinite(tileHeight) || tileHeight <= 0) {
            tileHeight = 0;
        }
        var tileHeightValue = tileHeight > 0 ? (Math.round(tileHeight * 1000) / 1000) + 'px' : '';
        var tileStyleAttr = tileHeightValue ? ' style="height:' + tileHeightValue + ';"' : '';
        var tileFillClass = tileHeightValue ? ' jzsa-gallery-tile-fill' : '';

        var columns       = parseInt(readGalleryAttr($container, 'columns'), 10) || 3;
        var columnsTablet = parseInt(readGalleryAttr($container, 'columns-tablet'), 10) || 2;
        var columnsMobile = parseInt(readGalleryAttr($container, 'columns-mobile'), 10) || 1;
        // Pass column counts and gap as CSS custom properties so the media queries pick them up
        var galleryGap = parseInt(readGalleryAttr($container, 'gap'), 10) || 4;
        $container[0].style.setProperty('--jzsa-gallery-columns',        columns);
        $container[0].style.setProperty('--jzsa-gallery-columns-tablet', columnsTablet);
        $container[0].style.setProperty('--jzsa-gallery-columns-mobile', columnsMobile);
        $container[0].style.setProperty('--jzsa-gallery-gap',            galleryGap + 'px');
        var allowThumbFullscreen =
            $container.attr('data-fullscreen-toggle') !== 'disabled' &&
            $container.attr('data-interaction-lock') !== 'true';
        var showThumbLink = $container.attr('data-show-link-button') === 'true' &&
            $container.attr('data-interaction-lock') !== 'true';
        var showThumbDownload = $container.attr('data-show-download-button') === 'true' &&
            $container.attr('data-interaction-lock') !== 'true';
        var thumbAlbumUrl = $container.attr('data-album-url') || '';

        var html = '';
        pageItems.forEach(function(item) {
            var photo = item.photo;
            var globalIndex = item.index;
            var src = photo.preview || photo.full;
            var isVideo = photo.type === 'video';
            var itemClass = 'jzsa-gallery-item' + (isVideo ? ' jzsa-gallery-item-video' : '');
            var mediaLabel = isVideo ? 'video' : 'photo';
            var mediaHtml;

            if (isVideo) {
                var videoSrc = photo.video || src;
                mediaHtml = buildVideoHtml({ src: videoSrc, poster: src, mediaIndex: globalIndex });
            } else {
                mediaHtml =
                    '<img class="jzsa-gallery-thumb' + tileFillClass + '"' +
                    ' src="' + src + '"' +
                    (src !== photo.full ? ' data-full-src="' + photo.full + '"' : '') +
                    ' data-index="' + globalIndex + '"' +
                    ' alt="' + mediaLabel.charAt(0).toUpperCase() + mediaLabel.slice(1) + ' ' + (globalIndex + 1) + '"' +
                    ' draggable="false"' +
                    (IS_IOS ? '' : ' loading="lazy"') +
                    ' decoding="' + (IS_IOS ? 'sync' : 'async') + '"' + tileStyleAttr + '>';
            }

            var thumbOverlayBtns = '';
            if (showThumbLink && thumbAlbumUrl) {
                thumbOverlayBtns += '<a href="' + thumbAlbumUrl + '" target="_blank" rel="noopener noreferrer" class="jzsa-gallery-thumb-link-btn swiper-button-external-link" tabindex="0" aria-label="Open album in Google Photos"></a>';
            }
            if (showThumbDownload) {
                thumbOverlayBtns += '<div class="jzsa-gallery-thumb-download-btn swiper-button-download" role="button" tabindex="0" data-index="' + globalIndex + '" data-media-type="' + mediaLabel + '" aria-label="Download ' + mediaLabel + ' ' + (globalIndex + 1) + '"></div>';
            }
            if (allowThumbFullscreen) {
                thumbOverlayBtns += '<div class="jzsa-gallery-thumb-fs-btn swiper-button-fullscreen" role="button" tabindex="0" data-index="' + globalIndex + '" aria-label="Open ' + mediaLabel + ' ' + (globalIndex + 1) + ' in fullscreen"></div>';
            }

            // Info box overlays for gallery thumbnails.
            var thumbZoneFormats = readInfoZoneFormats($container);
            var thumbZoneHtml = buildInfoZoneHtml(photo, thumbZoneFormats.inline, thumbZoneFormats.fullscreen, {
                item: String(globalIndex + 1),
                items: String(parseInt($container.attr('data-total-count'), 10) || 0),
                albumTitle: $container.attr('data-album-title') || ''
            }, { bottomOrder: GALLERY_INFO_BOTTOM_ORDER });

            html +=
                '<div class="' + itemClass + '" data-index="' + globalIndex + '">' +
                    mediaHtml +
                    thumbZoneHtml +
                    thumbOverlayBtns +
                '</div>';
        });

        for (var i = 0; i < placeholderCount; i++) {
            html += '<div class="jzsa-gallery-placeholder' + tileFillClass + '" aria-hidden="true"' + tileStyleAttr + '></div>';
        }

        renderGalleryMarkup($container, html);
    }

    /**
     * Render gallery HTML while preserving transient overlay nodes (like loader).
     *
     * @param {jQuery} $container Gallery album element.
     * @param {string} html       Gallery markup to render.
     */
    function renderGalleryMarkup($container, html) {
        // Pause any playing videos before destroying DOM to prevent orphan audio
        $container.find('video.jzsa-video-player').each(function() {
            if (this._jzsaPlyr && this._jzsaPlyr.playing) {
                this._jzsaPlyr.pause();
            }
        });
        destroyPlyrInContainer($container);
        var $loader = $container.children('.jzsa-loader').detach();
        $container.html(html);
        if ($loader.length) {
            $container.append($loader);
        }
        initPlyrInContainer($container);
        scheduleDurationPrefetch($container);
    }

    /**
     * Measure gallery width with a safe fallback.
     *
     * @param {jQuery} $container Gallery album element.
     * @return {number}
     */
    function getGalleryContainerWidth($container) {
        var containerWidth = $container.width();
        if (!containerWidth || containerWidth < 10) {
            return 800;
        }
        return containerWidth;
    }

    /**
     * Render precomputed justified rows into `$container`.
     *
     * @param {jQuery} $container      Gallery album element.
     * @param {Array}  rows            Array of justified rows.
     * @param {number} containerWidth  Available width in pixels.
     * @param {number} targetHeight    Row height in pixels.
     * @param {number} gap             Gap between thumbnails in pixels.
     */
    function renderJustifiedRows($container, rows, containerWidth, targetHeight, gap) {
        var allowThumbFullscreen =
            $container.attr('data-fullscreen-toggle') !== 'disabled' &&
            $container.attr('data-interaction-lock') !== 'true';
        var showThumbLink = $container.attr('data-show-link-button') === 'true' &&
            $container.attr('data-interaction-lock') !== 'true';
        var showThumbDownload = $container.attr('data-show-download-button') === 'true' &&
            $container.attr('data-interaction-lock') !== 'true';
        var thumbAlbumUrl = $container.attr('data-album-url') || '';
        var html = '';
        rows.forEach(function(row) {
            var totalRatio    = row.reduce(function(sum, item) { return sum + item.ratio; }, 0);
            var totalGap      = gap * (row.length - 1);
            var availableWidth = containerWidth - totalGap;

            html += '<div class="jzsa-justified-row">';
            row.forEach(function(item) {
                var width = Math.round((item.ratio / totalRatio) * availableWidth);
                var src   = item.photo.preview || item.photo.full;
                var isVideo = item.photo.type === 'video';
                var itemClass = 'jzsa-gallery-item' + (isVideo ? ' jzsa-gallery-item-video' : '');
                var mediaLabel = isVideo ? 'video' : 'photo';
                var mediaHtml;

                if (isVideo) {
                    var videoSrc = item.photo.video || src;
                    mediaHtml = buildVideoHtml({ src: videoSrc, poster: src, mediaIndex: item.index });
                } else {
                    mediaHtml =
                        '<img class="jzsa-gallery-thumb jzsa-justified-thumb"' +
                        ' src="' + src + '"' +
                        (src !== item.photo.full ? ' data-full-src="' + item.photo.full + '"' : '') +
                        ' data-index="' + item.index + '"' +
                        ' alt="' + mediaLabel.charAt(0).toUpperCase() + mediaLabel.slice(1) + ' ' + (item.index + 1) + '"' +
                        ' draggable="false"' +
                        (IS_IOS ? '' : ' loading="lazy"') +
                        ' decoding="' + (IS_IOS ? 'sync' : 'async') + '"' +
                        ' style="width:100%;height:100%;">';
                }

                var thumbOverlayBtns = '';
                if (showThumbLink && thumbAlbumUrl) {
                    thumbOverlayBtns += '<a href="' + thumbAlbumUrl + '" target="_blank" rel="noopener noreferrer" class="jzsa-gallery-thumb-link-btn swiper-button-external-link" tabindex="0" aria-label="Open album in Google Photos"></a>';
                }
                if (showThumbDownload) {
                    thumbOverlayBtns += '<div class="jzsa-gallery-thumb-download-btn swiper-button-download" role="button" tabindex="0" data-index="' + item.index + '" data-media-type="' + mediaLabel + '" aria-label="Download ' + mediaLabel + ' ' + (item.index + 1) + '"></div>';
                }
                if (allowThumbFullscreen) {
                    thumbOverlayBtns += '<div class="jzsa-gallery-thumb-fs-btn swiper-button-fullscreen" role="button" tabindex="0" data-index="' + item.index + '" aria-label="Open ' + mediaLabel + ' ' + (item.index + 1) + ' in fullscreen"></div>';
                }

                // Info box overlays for justified thumbnails.
                var justifiedZoneFormats = readInfoZoneFormats($container);
                var justifiedZoneHtml = buildInfoZoneHtml(item.photo, justifiedZoneFormats.inline, justifiedZoneFormats.fullscreen, {
                    item: String(item.index + 1),
                    items: String(parseInt($container.attr('data-total-count'), 10) || 0),
                    albumTitle: $container.attr('data-album-title') || ''
                });

                html +=
                    '<div class="' + itemClass + '" data-index="' + item.index + '" style="width:' + width + 'px;height:' + targetHeight + 'px;">' +
                        mediaHtml +
                        justifiedZoneHtml +
                        thumbOverlayBtns +
                    '</div>';
            });
            html += '</div>';
        });

        renderGalleryMarkup($container, html);
    }

    /**
     * Determine the active grid-gallery column count for the current viewport.
     *
     * @param {jQuery} $container Gallery album element.
     * @return {number}
     */
    function getUniformColumnsForViewport($container) {
        var columnsDesktop = parseInt(readGalleryAttr($container, 'columns'), 10) || 3;
        var columnsTablet  = parseInt(readGalleryAttr($container, 'columns-tablet'), 10) || 2;
        var columnsMobile  = parseInt(readGalleryAttr($container, 'columns-mobile'), 10) || 1;
        var viewportWidth  = window.innerWidth || document.documentElement.clientWidth || 1024;

        if (viewportWidth <= 480) {
            return columnsMobile;
        }

        if (viewportWidth <= 768) {
            return columnsTablet;
        }

        return columnsDesktop;
    }

    /**
     * Build a page slice of photos for the gallery renderer.
     *
     * @param {Array}  allPhotos Full ordered photo array.
     * @param {number} pageIndex Zero-based page index.
     * @param {number} pageSize  Photos per page.
     * @return {Array} Array of objects: { photo, index }.
     */
    function getGalleryPageItems(allPhotos, pageIndex, pageSize) {
        var items = [];
        var safePageSize = pageSize > 0 ? pageSize : (allPhotos.length || 1);
        var start = pageIndex * safePageSize;
        var end = Math.min(start + safePageSize, allPhotos.length);

        for (var i = start; i < end; i++) {
            items.push({
                photo: allPhotos[i],
                index: i
            });
        }

        return items;
    }

    /**
     * Ensure the gallery container is wrapped by a positioned shell for overlay controls.
     *
     * @param {jQuery} $container Gallery album element.
     * @return {jQuery} Shell element.
     */
    function ensureGalleryShell($container) {
        var $shell = $container.parent('.jzsa-gallery-shell');

        if (!$shell.length) {
            $container.wrap('<div class="jzsa-gallery-shell"></div>');
            $shell = $container.parent('.jzsa-gallery-shell');
        }

        // Keep shell dimensions aligned with explicit shortcode size so
        // overlaid gallery controls (prev/next/counter) stay within the same box.
        // Use renderer-provided explicit attributes to avoid style drift when
        // JS temporarily sets container width/height to percentages.
        var explicitWidthPx = parseInt(readGalleryAttr($container, 'explicit-width'), 10);
        var explicitHeightPx = parseInt(readGalleryAttr($container, 'explicit-height'), 10);

        var explicitWidth = (!isNaN(explicitWidthPx) && explicitWidthPx > 0)
            ? explicitWidthPx + 'px'
            : (($container[0] && $container[0].style) ? $container[0].style.width : '');
        var explicitHeight = (!isNaN(explicitHeightPx) && explicitHeightPx > 0)
            ? explicitHeightPx + 'px'
            : (($container[0] && $container[0].style) ? $container[0].style.height : '');

        $shell.css('width', explicitWidth || '');
        $shell.css('height', explicitHeight || '');
        $shell.toggleClass('jzsa-gallery-shell-bounded', !!(explicitWidth || explicitHeight));

        // When shell is explicitly bounded, keep gallery content inside that box.
        if (explicitWidth) {
            $container.css('width', '100%');
        } else {
            $container.css('width', '');
        }
        if (explicitHeight) {
            $container.css('height', '100%');
        } else {
            $container.css('height', '');
        }

        return $shell;
    }

    /**
     * Build/update gallery pagination controls using existing Swiper-style controls.
     *
     * @param {jQuery}   $container    Gallery album element.
     * @param {Object}   state         Pagination state object.
     * @param {Function} onPageChange  Callback invoked with next page index.
     * @param {Object}   options       Optional controls config.
     */
    function setupGalleryPaginationControls($container, state, onPageChange, options) {
        var config = options || {};
        if ($container.attr('data-interaction-lock') === 'true') {
            removeGalleryPaginationControls($container);
            return;
        }

        var showCounter = config.showCounter !== false;
        var showAutoplayProgress = !!config.showAutoplayProgress;
        var showSlideshowControls = !!config.showSlideshowControls;
        var isAutoplayRunning = typeof config.isAutoplayRunning === 'function' ? config.isAutoplayRunning : null;
        var onToggleAutoplay = typeof config.onToggleAutoplay === 'function' ? config.onToggleAutoplay : null;
        var controlsId = $container.attr('id') + '-gallery-controls';
        var $shell = ensureGalleryShell($container);
        var $controls = $('#' + controlsId);

        if (state.totalPages <= 1) {
            $controls.remove();
            return;
        }

        if (!$controls.length) {
            var html =
                '<div id="' + controlsId + '" class="jzsa-gallery-controls jzsa-album" role="group" aria-label="Gallery page navigation">' +
                    '<div class="swiper-button-prev" role="button" tabindex="0" aria-label="Previous gallery page"></div>' +
                    '<div class="swiper-pagination" aria-live="polite"></div>' +
                    '<button class="swiper-button-play-pause" title="Play/Pause" aria-label="Pause slideshow"></button>' +
                    '<div class="swiper-slideshow-progress" aria-hidden="true"><div class="swiper-slideshow-progress-bar"></div></div>' +
                    '<div class="swiper-button-next" role="button" tabindex="0" aria-label="Next gallery page"></div>' +
                '</div>';

            $shell.append(html);
            $controls = $('#' + controlsId);
        }

        var $prev = $controls.find('.swiper-button-prev');
        var $next = $controls.find('.swiper-button-next');
        var $status = $controls.find('.swiper-pagination');
        var $playPause = $controls.find('.swiper-button-play-pause');
        var $progressContainer = $controls.find('.swiper-slideshow-progress');
        var $progressBar = $controls.find('.swiper-slideshow-progress-bar');
        var controlsColor = $container.attr('data-controls-color');
        if (controlsColor) {
            $controls[0].style.setProperty('--jzsa-controls-color', controlsColor);
            applyControlsColorToIcons('#' + $container.attr('id'), controlsColor);
        }
        var infoFontSize = getInfoFontSizePx($container, 'data-info-font-size', 12);
        $controls[0].style.setProperty('--jzsa-info-font-size', infoFontSize + 'px');
        var infoFontFamily = getInfoFontFamily($container, 'data-info-font-family', '');
        if (infoFontFamily) {
            $controls[0].style.setProperty('--jzsa-info-font-family', infoFontFamily);
        } else {
            $controls[0].style.removeProperty('--jzsa-info-font-family');
        }

        function isActivationKey(e) {
            return e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32;
        }

        function bindControl($el, direction) {
            function activate(e) {
                if (e.type === 'keydown' && !isActivationKey(e)) {
                    return;
                }
                e.preventDefault();
                if ($container.data('jzsaGalleryAnimating')) {
                    return;
                }

                var nextPage = state.currentPage + direction;
                if (nextPage < 0) {
                    nextPage = state.totalPages - 1;
                } else if (nextPage > state.totalPages - 1) {
                    nextPage = 0;
                }

                onPageChange(nextPage, direction);
            }

            $el.off('click.jzsaGallery keydown.jzsaGallery');
            $el.on('click.jzsaGallery', activate);
            $el.on('keydown.jzsaGallery', activate);
        }

        bindControl($prev, -1);
        bindControl($next, 1);

        $controls.toggleClass('jzsa-gallery-slideshow-enabled', showSlideshowControls);
        if (showSlideshowControls) {
            var running = isAutoplayRunning ? isAutoplayRunning() : false;
            $playPause.toggleClass('playing', !!running);
            $playPause.attr('aria-label', running ? 'Pause slideshow' : 'Resume slideshow');
            $playPause.attr('title', running ? 'Pause slideshow' : 'Resume slideshow');

            $playPause.off('click.jzsaGallery keydown.jzsaGallery');
            $playPause.on('click.jzsaGallery keydown.jzsaGallery', function(e) {
                if (e.type === 'keydown' && !isActivationKey(e)) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                if (onToggleAutoplay) {
                    onToggleAutoplay();
                }
            });
        } else {
            $playPause.off('click.jzsaGallery keydown.jzsaGallery');
            $playPause.removeClass('playing');
        }

	        var pageBottomFmt = $container.attr('data-gallery-info-bottom');
	        if (pageBottomFmt === undefined) {
	            pageBottomFmt = $container.attr('data-gallery-page-bottom');
	        }
	        if (showCounter && typeof pageBottomFmt === 'string') {
            // gallery-info-bottom is the final parser-provided format string (with legacy fallback to gallery-page-bottom).
            var pageText = resolveInfoPlaceholders(pageBottomFmt, {}, {
                page: String(state.currentPage + 1),
                pages: String(state.totalPages),
                albumTitle: $container.attr('data-album-title') || ''
            });
            $status.text(pageText).toggle(pageText !== '');
        } else {
            $status.hide();
        }
        $prev.removeClass('swiper-button-disabled').attr('aria-disabled', 'false');
        $next.removeClass('swiper-button-disabled').attr('aria-disabled', 'false');
        $progressBar.css({
            transform: 'scaleX(1)',
            transition: 'none'
        });
        $progressContainer.css('display', showAutoplayProgress ? 'block' : 'none');
    }

    /**
     * Remove gallery pagination controls if they are present.
     *
     * @param {jQuery} $container Gallery album element.
     */
    function removeGalleryPaginationControls($container) {
        $('#' + $container.attr('id') + '-gallery-controls').remove();
    }

    /**
     * Enable or disable fixed-height gallery scrolling.
     *
     * @param {jQuery} $container Gallery album element.
     * @param {boolean} enabled   Whether scrolling mode is enabled.
     * @param {number} maxHeight  Max visible height in pixels.
     */
    function setGalleryScrollableState($container, enabled, maxHeight) {
        if (!enabled) {
            $container.removeClass('jzsa-gallery-scrollable');
            $container.css('max-height', '');
            return;
        }

        $container.addClass('jzsa-gallery-scrollable');

        if (typeof maxHeight === 'number' && maxHeight > 0) {
            $container.css('max-height', Math.floor(maxHeight) + 'px');
        } else {
            $container.css('max-height', '');
        }
    }

    /**
     * Keep paginated gallery height stable across pages to avoid layout jumps.
     *
     * @param {jQuery} $container Gallery album element.
     * @param {boolean} enabled   Whether fixed-height pagination is enabled.
     * @param {number} height     Target minimum height in pixels.
     */
    function setGalleryPaginationHeightState($container, enabled, height) {
        if (!enabled || typeof height !== 'number' || height <= 0) {
            $container.css('min-height', '');
            return;
        }

        $container.css('min-height', Math.ceil(height) + 'px');
    }

    /**
     * Build a stable visual snapshot of the current gallery markup for transitions.
     *
     * @param {jQuery} $source Gallery album element.
     * @return {jQuery} Snapshot node.
     */
    function createGalleryTransitionSnapshot($source) {
        var $snapshot = $source.clone(false, false);
        var isCssGallery = false;
        $snapshot.removeAttr('id');
        $snapshot.removeClass('jzsa-gallery-scrollable jzsa-gallery-transition-target');
        $snapshot.css({
            maxHeight: '',
            overflowY: '',
            overflowX: '',
            visibility: '',
            transform: '',
            transition: '',
            scrollbarGutter: ''
        });

        var sourceRect = $source[0] ? $source[0].getBoundingClientRect() : null;
        if (sourceRect && sourceRect.width > 0) {
            $snapshot.css('width', sourceRect.width + 'px');
        }

        // Freeze computed layout to prevent intermediate one-tile flashes.
        if ($source[0] && window.getComputedStyle) {
            var sourceStyle = window.getComputedStyle($source[0]);
            if (sourceStyle && sourceStyle.display === 'grid') {
                isCssGallery = true;
                $snapshot.css({
                    display: 'grid',
                    gridTemplateColumns: sourceStyle.gridTemplateColumns,
                    gridAutoRows: sourceStyle.gridAutoRows,
                    gap: sourceStyle.gap
                });
            }
        }

        var sourceThumbs = $source.find('.jzsa-gallery-thumb');
        var snapshotThumbs = $snapshot.find('.jzsa-gallery-thumb');
        snapshotThumbs.each(function(i) {
            var sourceThumb = sourceThumbs.get(i);
            if (!sourceThumb) {
                return;
            }

            var rect = sourceThumb.getBoundingClientRect();
            if (!isCssGallery && rect.width > 0 && rect.height > 0) {
                this.style.width = rect.width + 'px';
                this.style.height = rect.height + 'px';
                this.style.aspectRatio = '';
            }

            this.setAttribute('loading', 'eager');
            this.setAttribute('decoding', 'sync');
        });

        return $snapshot;
    }

    /**
     * Enable desktop mouse drag behavior for gallery pagination/scrolling.
     *
     * @param {jQuery} $container Gallery album element.
     * @param {Object} config     { enabled, mode, onPageSwipe, onPageDragStart }.
     */
    function setupGalleryMouseInteractions($container, config) {
        var id = $container.attr('id') || 'gallery';
        var ns = '.jzsaGalleryMouse-' + id;
        var $shell = ensureGalleryShell($container);
        var options = config || {};
        var mode = options.mode || '';
        var enabled = !!options.enabled;
        var onPageSwipe = typeof options.onPageSwipe === 'function' ? options.onPageSwipe : null;
        var onPageDragStart = typeof options.onPageDragStart === 'function' ? options.onPageDragStart : null;

        $container.off(ns);
        $(document).off(ns);
        $container.removeClass('jzsa-gallery-draggable jzsa-gallery-grabbing');
        $shell.removeClass('jzsa-gallery-draggable jzsa-gallery-grabbing');
        $container.removeData('jzsaGallerySuppressClick');

        if (!enabled) {
            return;
        }

        $container.addClass('jzsa-gallery-draggable');
        $shell.addClass('jzsa-gallery-draggable');

        var state = {
            active: false,
            moved: false,
            swipeTriggered: false,
            startX: 0,
            startY: 0,
            startScrollTop: 0,
            lastDeltaX: 0,
            lastDeltaY: 0,
            dragDirection: 0,
            dragSession: null
        };

        function suppressNextThumbClick() {
            $container.data('jzsaGallerySuppressClick', true);
            window.setTimeout(function() {
                $container.removeData('jzsaGallerySuppressClick');
            }, 180);
        }

        function stopDraggingState() {
            state.active = false;
            $container.removeClass('jzsa-gallery-grabbing');
            $shell.removeClass('jzsa-gallery-grabbing');
        }

        function finalizePaginationDragSession() {
            if (mode !== 'pagination' || !state.dragSession) {
                return false;
            }

            var threshold = Math.max(30, ($container.width() || 0) * 0.10);
            var hasDirectionalDelta =
                (state.dragDirection === 1 && state.lastDeltaX < 0) ||
                (state.dragDirection === -1 && state.lastDeltaX > 0);
            var shouldCommit = hasDirectionalDelta && Math.abs(state.lastDeltaX) >= threshold;
            var session = state.dragSession;

            state.dragSession = null;
            state.swipeTriggered = shouldCommit;

            if (typeof session.finish === 'function') {
                session.finish(shouldCommit);
            } else if (shouldCommit && onPageSwipe) {
                onPageSwipe(state.dragDirection);
            }

            suppressNextThumbClick();
            return true;
        }

        function isGalleryVideoTarget(target) {
            return $(target).closest('.jzsa-gallery-item-video .jzsa-video-wrapper').length > 0;
        }

        $container.on('dragstart' + ns, '.jzsa-gallery-thumb', function(e) {
            e.preventDefault();
        });

        $container.on('click' + ns, '.jzsa-gallery-thumb', function(e) {
            if ($container.data('jzsaGallerySuppressClick')) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });
        $container.on('click' + ns, '.jzsa-gallery-thumb-fs-btn', function(e) {
            if ($container.data('jzsaGallerySuppressClick')) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });

        $container.on('mousedown' + ns, function(e) {
            if (e.which !== 1) {
                return;
            }

            if (isGalleryVideoTarget(e.target)) {
                return;
            }

            if ($(e.target).closest('.swiper-button-prev, .swiper-button-next, .jzsa-gallery-thumb-fs-btn').length) {
                return;
            }

            state.active = true;
            state.moved = false;
            state.swipeTriggered = false;
            state.startX = e.pageX;
            state.startY = e.pageY;
            state.startScrollTop = $container.scrollTop();
            state.lastDeltaX = 0;
            state.lastDeltaY = 0;
            state.dragDirection = 0;
            state.dragSession = null;

            $container.addClass('jzsa-gallery-grabbing');
            $shell.addClass('jzsa-gallery-grabbing');
            e.preventDefault();
        });

        $(document).on('mousemove' + ns, function(e) {
            if (!state.active) {
                return;
            }

            var deltaX = e.pageX - state.startX;
            var deltaY = e.pageY - state.startY;
            state.lastDeltaX = deltaX;
            state.lastDeltaY = deltaY;

            if (Math.abs(deltaX) > 3 || Math.abs(deltaY) > 3) {
                state.moved = true;
            }

            if (mode === 'scroll') {
                $container.scrollTop(state.startScrollTop - deltaY);
                e.preventDefault();
                return;
            }

            if (mode === 'pagination') {
                var isHorizontalGesture = Math.abs(deltaX) > Math.abs(deltaY);

                if (onPageDragStart) {
                    if (!state.dragSession && !state.swipeTriggered && isHorizontalGesture && Math.abs(deltaX) >= 6) {
                        state.dragDirection = deltaX < 0 ? 1 : -1;
                        state.dragSession = onPageDragStart(state.dragDirection);
                        if (state.dragSession) {
                            suppressNextThumbClick();
                        }
                    }

                    if (state.dragSession && typeof state.dragSession.update === 'function') {
                        state.dragSession.update(deltaX);
                        e.preventDefault();
                        return;
                    }
                }

                if (!onPageDragStart && !state.swipeTriggered) {
                    var threshold = Math.max(30, ($container.width() || 0) * 0.10);
                    if (isHorizontalGesture && Math.abs(deltaX) >= threshold) {
                        state.swipeTriggered = true;
                        suppressNextThumbClick();
                        if (onPageSwipe) {
                            onPageSwipe(deltaX < 0 ? 1 : -1);
                        }
                        e.preventDefault();
                    }
                } else if (isHorizontalGesture) {
                    e.preventDefault();
                }
            }
        });

        $(document).on('mouseup' + ns, function() {
            if (!state.active) {
                return;
            }

            if (finalizePaginationDragSession()) {
                stopDraggingState();
                return;
            }

            if (state.moved || state.swipeTriggered) {
                suppressNextThumbClick();
            }
            stopDraggingState();
        });

        $(document).on('mouseleave' + ns, function() {
            if (!state.active) {
                return;
            }

            if (finalizePaginationDragSession()) {
                stopDraggingState();
                return;
            }

            if (state.moved || state.swipeTriggered) {
                suppressNextThumbClick();
            }
            stopDraggingState();
        });
    }

    /**
     * Create an interactive drag session for gallery page transitions.
     *
     * @param {jQuery}   $container         Gallery album element.
     * @param {number}   direction          +1 next page, -1 previous page.
     * @param {Function} renderIncomingPage Renders the target page into $container.
     * @param {Function} renderOutgoingPage Renders the current page into $container.
     * @param {Function} onFinish           Callback called with committed boolean.
     * @return {?Object} Session with update(deltaX) and finish(commit).
     */
    function createGalleryPageDragTransition($container, direction, renderIncomingPage, renderOutgoingPage, onFinish) {
        var DRAG_SETTLE_MS = 220;

        if (
            !$container ||
            !$container.length ||
            typeof renderIncomingPage !== 'function' ||
            typeof renderOutgoingPage !== 'function'
        ) {
            return null;
        }

        if ($container.data('jzsaGalleryAnimating')) {
            return null;
        }

        var $shell = ensureGalleryShell($container);
        var outgoingRect = $container[0] ? $container[0].getBoundingClientRect() : null;
        var outgoingHeight = outgoingRect ? outgoingRect.height : $container.outerHeight();
        var outgoingWidth = outgoingRect ? outgoingRect.width : $container.outerWidth();
        if (!outgoingWidth || !outgoingHeight) {
            return null;
        }

        var $outgoingSnapshot = createGalleryTransitionSnapshot($container);

        $shell.addClass('jzsa-gallery-transitioning');
        $container.data('jzsaGalleryAnimating', true);
        $container.css('visibility', 'hidden');

        renderIncomingPage();
        var $incomingSnapshot = createGalleryTransitionSnapshot($container);

        var stageHeight = outgoingHeight;
        if (stageHeight > 0) {
            $shell.css({
                height: stageHeight + 'px',
                minHeight: stageHeight + 'px'
            });
        }

        $shell.find('.jzsa-gallery-slide-stage').remove();

        var $stage = $('<div class="jzsa-gallery-slide-stage"></div>');
        var $track = $('<div class="jzsa-gallery-slide-track"></div>');
        var $panelA = $('<div class="jzsa-gallery-slide-panel"></div>');
        var $panelB = $('<div class="jzsa-gallery-slide-panel"></div>');
        var slideDistance = outgoingWidth;
        if (!slideDistance || slideDistance <= 0) {
            var shellRect = $shell[0] ? $shell[0].getBoundingClientRect() : null;
            slideDistance = shellRect ? shellRect.width : ($shell.width() || 0);
        }

        if (stageHeight > 0) {
            $stage.css('height', stageHeight + 'px');
            $track.css('height', stageHeight + 'px');
        }

        $track.css({
            width: (slideDistance * 2) + 'px',
            transitionDuration: '0ms'
        });
        $panelA.css({
            width: slideDistance + 'px',
            maxWidth: slideDistance + 'px',
            flex: '0 0 ' + slideDistance + 'px'
        });
        $panelB.css({
            width: slideDistance + 'px',
            maxWidth: slideDistance + 'px',
            flex: '0 0 ' + slideDistance + 'px'
        });

        var startTransform = direction > 0 ? 0 : -slideDistance;
        var endTransform = direction > 0 ? -slideDistance : 0;
        var currentTransform = startTransform;

        if (direction > 0) {
            $panelA.append($outgoingSnapshot);
            $panelB.append($incomingSnapshot);
        } else {
            $panelA.append($incomingSnapshot);
            $panelB.append($outgoingSnapshot);
        }

        $track.append($panelA, $panelB);
        $stage.append($track);
        $shell.append($stage);
        $track.css('transform', 'translateX(' + startTransform + 'px)');

        var finished = false;
        function cleanup(committed) {
            if (finished) {
                return;
            }
            finished = true;

            if (!committed) {
                renderOutgoingPage();
            }

            $stage.remove();
            $container.css('visibility', '');
            $shell.removeClass('jzsa-gallery-transitioning');
            $shell.css({
                height: '',
                minHeight: ''
            });
            // Restore bounded shell dimensions (when explicit width/height are set).
            ensureGalleryShell($container);
            $container.data('jzsaGalleryAnimating', false);

            if (typeof onFinish === 'function') {
                onFinish(committed);
            }
        }

        return {
            update: function(deltaX) {
                if (finished) {
                    return;
                }

                var nextTransform = startTransform + deltaX;
                if (nextTransform > 0) {
                    nextTransform = 0;
                } else if (nextTransform < -slideDistance) {
                    nextTransform = -slideDistance;
                }

                currentTransform = nextTransform;
                $track.css({
                    transitionDuration: '0ms',
                    transform: 'translateX(' + currentTransform + 'px)'
                });
            },
            finish: function(committed) {
                if (finished) {
                    return;
                }

                var target = committed ? endTransform : startTransform;
                $track.css('transition-duration', DRAG_SETTLE_MS + 'ms');
                runOnNextPaint(function() {
                    if (finished) {
                        return;
                    }
                    $track.css('transform', 'translateX(' + target + 'px)');
                });

                waitForTransitionEnd($track, DRAG_SETTLE_MS + 60, function() {
                    cleanup(!!committed);
                });
            }
        };
    }

    /**
     * Animate gallery page changes with a horizontal slide similar to single mode.
     *
     * @param {jQuery}  $container     Gallery album element.
     * @param {number}  direction      +1 next page, -1 previous page.
     * @param {Function} renderNewPage Callback that renders the next page into $container.
     */
    function animateGalleryPageTransition($container, direction, renderNewPage) {
        var GALLERY_PAGE_TRANSITION_MS = 600;

        if (!$container || !$container.length || typeof renderNewPage !== 'function') {
            return;
        }

        if ($container.data('jzsaGalleryAnimating')) {
            renderNewPage();
            return;
        }

        var $shell = ensureGalleryShell($container);
        var outgoingRect = $container[0] ? $container[0].getBoundingClientRect() : null;
        var outgoingHeight = outgoingRect ? outgoingRect.height : $container.outerHeight();
        var outgoingWidth = outgoingRect ? outgoingRect.width : $container.outerWidth();

        if (!outgoingWidth || !outgoingHeight) {
            renderNewPage();
            return;
        }

        var $outgoingSnapshot = createGalleryTransitionSnapshot($container);

        $shell.addClass('jzsa-gallery-transitioning');
        $container.data('jzsaGalleryAnimating', true);
        $container.css('visibility', 'hidden');

        renderNewPage();

        var $incomingSnapshot = createGalleryTransitionSnapshot($container);

        // Keep shell height fixed during transition to avoid temporary layout shifts.
        var stageHeight = outgoingHeight;
        if (stageHeight > 0) {
            $shell.css({
                height: stageHeight + 'px',
                minHeight: stageHeight + 'px'
            });
        }

        $shell.find('.jzsa-gallery-slide-stage').remove();

        var $stage = $('<div class="jzsa-gallery-slide-stage"></div>');
        var $track = $('<div class="jzsa-gallery-slide-track"></div>');
        var $panelA = $('<div class="jzsa-gallery-slide-panel"></div>');
        var $panelB = $('<div class="jzsa-gallery-slide-panel"></div>');
        var slideDistance = outgoingWidth;

        if (stageHeight > 0) {
            $stage.css('height', stageHeight + 'px');
            $track.css('height', stageHeight + 'px');
        }
        if (!slideDistance || slideDistance <= 0) {
            var shellRect = $shell[0] ? $shell[0].getBoundingClientRect() : null;
            slideDistance = shellRect ? shellRect.width : ($shell.width() || 0);
        }
        $track.css({
            width: (slideDistance * 2) + 'px',
            transitionDuration: GALLERY_PAGE_TRANSITION_MS + 'ms'
        });
        $panelA.css({
            width: slideDistance + 'px',
            maxWidth: slideDistance + 'px',
            flex: '0 0 ' + slideDistance + 'px'
        });
        $panelB.css({
            width: slideDistance + 'px',
            maxWidth: slideDistance + 'px',
            flex: '0 0 ' + slideDistance + 'px'
        });

        if (direction > 0) {
            // Next page: current content moves left, next content comes from right.
            $panelA.append($outgoingSnapshot);
            $panelB.append($incomingSnapshot);
            $track.css('transform', 'translateX(0px)');
        } else {
            // Previous page: current content moves right, previous content comes from left.
            $panelA.append($incomingSnapshot);
            $panelB.append($outgoingSnapshot);
            $track.css('transform', 'translateX(' + (-slideDistance) + 'px)');
        }

        $track.append($panelA, $panelB);
        $stage.append($track);
        $shell.append($stage);

        runOnNextPaint(function() {
            if (direction > 0) {
                $track.css('transform', 'translateX(' + (-slideDistance) + 'px)');
            } else {
                $track.css('transform', 'translateX(0px)');
            }
        });

        waitForTransitionEnd($track, GALLERY_PAGE_TRANSITION_MS + 80, function() {
            $stage.remove();
            $container.css('visibility', '');
            $shell.removeClass('jzsa-gallery-transitioning');
            $shell.css({
                height: '',
                minHeight: ''
            });
            // Restore bounded shell dimensions (when explicit width/height are set).
            ensureGalleryShell($container);
            $container.data('jzsaGalleryAnimating', false);
        });
    }

    /**
     * Build justified rows for all photos, preserving global photo indices.
     *
     * @param {jQuery} $container Gallery album element.
     * @param {Array}  allPhotos  Full photo array.
     * @return {Object} { rows, targetHeight, gap, containerWidth }.
     */
    function getJustifiedLayoutData($container, allPhotos) {
        var targetHeight = parseInt(readGalleryAttr($container, 'row-height'), 10) || 200;
        var gap = parseInt(readGalleryAttr($container, 'gap'), 10) || 4;
        var containerWidth = getGalleryContainerWidth($container);

        var photosWithRatios = allPhotos.map(function(photo, index) {
            return {
                photo: photo,
                ratio: parseAspectRatio(photo.preview || photo.full),
                index: index
            };
        });

        return {
            rows: buildJustifiedRows(photosWithRatios, containerWidth, targetHeight, gap),
            targetHeight: targetHeight,
            gap: gap,
            containerWidth: containerWidth
        };
    }

    /**
     * Clamp the page index so it always stays within valid bounds.
     *
     * @param {Object} state Pagination state object.
     */
    function clampGalleryPage(state) {
        if (state.currentPage < 0) {
            state.currentPage = 0;
        }
        if (state.currentPage > state.totalPages - 1) {
            state.currentPage = state.totalPages - 1;
        }
        if (state.currentPage < 0) {
            state.currentPage = 0;
        }
    }

    /**
     * Initialize a gallery-mode thumbnail gallery.
     * Renders thumbnails and attaches click-to-fullscreen handler.
     *
     * @param {Element} container The .jzsa-gallery-album DOM element.
     */
    function initializeGallery(container) {
        var $container = $(container);
        var $shell = ensureGalleryShell($container);
        var layout     = readGalleryAttr($container, 'layout') || 'grid';

        var allPhotosJson = $container.attr('data-all-photos');
        var allPhotos     = allPhotosJson ? JSON.parse(allPhotosJson) : [];

        if ($container.find('.jzsa-loader').length === 0) {
            $container.append(buildLoaderHtml('Loading content...'));
        }
        $container
            .addClass('jzsa-gallery-album')
            .removeClass('jzsa-loaded jzsa-loader-visible')
            .addClass('jzsa-loader-pending jzsa-gallery-loading');
        triggerGalleryIntroFade($container);

        // Honour the same old-iOS cap as the Swiper path
        if (isOldIosWebkit() && allPhotos.length > OLD_IOS_MAX_PHOTOS) {
            allPhotos = allPhotos.slice(0, OLD_IOS_MAX_PHOTOS);
        }

        var requestedGalleryRows = parseInt(readGalleryAttr($container, 'rows'), 10);
        var galleryRows = (!isNaN(requestedGalleryRows) && requestedGalleryRows > 0) ? requestedGalleryRows : 0;
        var galleryScrollable = readGalleryAttr($container, 'scrollable') === 'true';
        var requestedGallerySizingModel = (readGalleryAttr($container, 'sizing') || 'ratio').toLowerCase();
        var gallerySizing = requestedGallerySizingModel === 'fill' ? 'fill' : 'ratio';
        var interactionLock = $container.attr('data-interaction-lock') === 'true';
        var galleryDownloadWarningSizeBytes = getDownloadWarningSizeBytes($container);
        var gallerySlideshowMode = $container.attr('data-slideshow') || 'disabled';
        var gallerySlideshowEnabled = gallerySlideshowMode !== 'disabled';
        var requestedGallerySlideshowDelay = parseInt($container.attr('data-slideshow-delay'), 10);
        var gallerySlideshowDelay = (!isNaN(requestedGallerySlideshowDelay) && requestedGallerySlideshowDelay > 0)
            ? requestedGallerySlideshowDelay
            : 5;

        // Normalize for CSS selectors and downstream logic.
        writeGalleryAttr($container, 'sizing', gallerySizing);

        // Update data so the fullscreen slideshow gets the same capped photo list.
        $container.attr('data-all-photos', JSON.stringify(allPhotos));

        var paginationState = {
            currentPage: 0,
            totalPages: 1
        };
        var GALLERY_AUTOPLAY_RETRY_MS = 120;
        var GALLERY_AUTOPLAY_PROGRESS_EXTRA_MS = 500;
        var gallerySlideshowTimer = null;
        var galleryProgressCycleToken = 0;
        var gallerySlideshowPausedByHover = false;
        var gallerySlideshowPausedByUser = gallerySlideshowMode === 'manual';
        var slideshowId = null;
        var $slideshow = null;
        var galleryLoaderShownAt = 0;
        var galleryHasMarkedLoaded = false;
        var galleryLoaderShowTimer = null;
        var galleryLoaderCommitTimer = null;
        var galleryLoaderFallbackTimer = window.setTimeout(function() {
            markGalleryLoaded();
        }, 3500);

        function clearGalleryLoaderTimers() {
            if (galleryLoaderShowTimer) {
                window.clearTimeout(galleryLoaderShowTimer);
                galleryLoaderShowTimer = null;
            }
            if (galleryLoaderFallbackTimer) {
                window.clearTimeout(galleryLoaderFallbackTimer);
                galleryLoaderFallbackTimer = null;
            }
            if (galleryLoaderCommitTimer) {
                window.clearTimeout(galleryLoaderCommitTimer);
                galleryLoaderCommitTimer = null;
            }
        }

        function commitGalleryLoaded() {
            clearGalleryLoaderTimers();
            $container.find('.jzsa-gallery-thumb').off('.jzsaGalleryLoader');
            $container
                .removeClass('jzsa-loader-pending jzsa-loader-visible jzsa-gallery-loading')
                .addClass('jzsa-loaded');
        }

        function markGalleryLoaded() {
            if (galleryHasMarkedLoaded) {
                return;
            }

            galleryHasMarkedLoaded = true;
            var minVisibleRemaining = 0;
            if (galleryLoaderShownAt > 0) {
                minVisibleRemaining = LOADER_MIN_VISIBLE_MS - (Date.now() - galleryLoaderShownAt);
            }

            if (minVisibleRemaining > 0) {
                galleryLoaderCommitTimer = window.setTimeout(commitGalleryLoaded, minVisibleRemaining);
            } else {
                commitGalleryLoaded();
            }
        }

        function watchGalleryInitialThumbLoad() {
            if (galleryHasMarkedLoaded) {
                return;
            }

            var $firstThumb = $container.find('.jzsa-gallery-thumb').first();
            if (!$firstThumb.length) {
                window.setTimeout(markGalleryLoaded, 700);
                return;
            }

            var thumbEl = $firstThumb[0];
            $container.find('.jzsa-gallery-thumb').off('.jzsaGalleryLoader');
            if (thumbEl.complete && thumbEl.naturalWidth > 0) {
                markGalleryLoaded();
                return;
            }

            $firstThumb.one('load.jzsaGalleryLoader', function() {
                markGalleryLoaded();
            });
            $firstThumb.one('error.jzsaGalleryLoader', function() {
                window.setTimeout(markGalleryLoaded, 500);
            });
        }

        galleryLoaderShowTimer = window.setTimeout(function() {
            if (galleryHasMarkedLoaded) {
                return;
            }
            galleryLoaderShownAt = Date.now();
            $container.addClass('jzsa-loader-visible');
        }, LOADER_SHOW_DELAY_MS);

        function clearGalleryAutoplayTimer() {
            if (gallerySlideshowTimer) {
                window.clearTimeout(gallerySlideshowTimer);
                gallerySlideshowTimer = null;
            }
        }

        function shouldShowGallerySlideshowProgress() {
            var useScroller = galleryScrollable && galleryRows > 0;
            return gallerySlideshowEnabled && !gallerySlideshowPausedByUser && !useScroller && paginationState.totalPages > 1;
        }

        function canRunGalleryAutoplay() {
            if (!shouldShowGallerySlideshowProgress() || gallerySlideshowPausedByHover) {
                return false;
            }

            if ($slideshow && $slideshow.length && isFullscreen($slideshow[0])) {
                return false;
            }

            return true;
        }

        function setGalleryAutoplayProgressVisible(visible) {
            var controlsId = $container.attr('id') + '-gallery-controls';
            var $controls = $('#' + controlsId);
            var $progressContainer = $controls.find('.swiper-slideshow-progress');
            var $progressBar = $controls.find('.swiper-slideshow-progress-bar');

            if (!$progressContainer.length || !$progressBar.length) {
                return;
            }
            galleryProgressCycleToken++;

            $progressBar.css({
                transform: 'scaleX(1)',
                transition: 'none'
            });
            $progressContainer.css('display', visible ? 'block' : 'none');
        }

        function startGalleryAutoplayProgressCycle() {
            var controlsId = $container.attr('id') + '-gallery-controls';
            var $controls = $('#' + controlsId);
            var $progressContainer = $controls.find('.swiper-slideshow-progress');
            var $progressBar = $controls.find('.swiper-slideshow-progress-bar');
            var delayMs = gallerySlideshowDelay * MILLISECONDS_PER_SECOND;

            if (!$progressContainer.length || !$progressBar.length || delayMs <= 0) {
                return;
            }

            var progressDuration = delayMs + GALLERY_AUTOPLAY_PROGRESS_EXTRA_MS;
            if (progressDuration < 0) {
                progressDuration = delayMs;
            }
            var cycleToken = ++galleryProgressCycleToken;

            $progressContainer.css('display', 'block');
            $progressBar.css({
                transform: 'scaleX(1)',
                transition: 'none'
            });

            runOnNextPaint(function() {
                if (cycleToken !== galleryProgressCycleToken || !$progressBar.length) {
                    return;
                }
                $progressBar.css({
                    transform: 'scaleX(0)',
                    transition: 'transform ' + progressDuration + 'ms linear'
                });
            });
        }

        function scheduleGalleryAutoplay() {
            clearGalleryAutoplayTimer();

            if (!shouldShowGallerySlideshowProgress()) {
                setGalleryAutoplayProgressVisible(false);
                return;
            }

            if (!canRunGalleryAutoplay()) {
                setGalleryAutoplayProgressVisible(true);
                return;
            }

            if ($container.data('jzsaGalleryAnimating')) {
                setGalleryAutoplayProgressVisible(true);
                gallerySlideshowTimer = window.setTimeout(function() {
                    scheduleGalleryAutoplay();
                }, GALLERY_AUTOPLAY_RETRY_MS);
                return;
            }

            startGalleryAutoplayProgressCycle();
            gallerySlideshowTimer = window.setTimeout(function() {
                runOnNextPaint(function() {
                    if (!canRunGalleryAutoplay() || $container.data('jzsaGalleryAnimating')) {
                        scheduleGalleryAutoplay();
                        return;
                    }

                    var nextPage = paginationState.currentPage + 1;
                    if (nextPage >= paginationState.totalPages) {
                        nextPage = 0;
                    }

                    paginationState.currentPage = nextPage;
                    renderCurrentGalleryPage({
                        animate: true,
                        direction: 1
                    });
                });
            }, gallerySlideshowDelay * MILLISECONDS_PER_SECOND);
        }

        function syncGalleryAutoplayState() {
            clearGalleryAutoplayTimer();
            scheduleGalleryAutoplay();
        }

        var gallerySlideshowNamespace = '.jzsaGallerySlideshow-' + ($container.attr('id') || 'gallery');
        $shell.off('mouseenter' + gallerySlideshowNamespace + ' mouseleave' + gallerySlideshowNamespace);
        if (!interactionLock) {
            $shell.on('mouseenter' + gallerySlideshowNamespace, function() {
                gallerySlideshowPausedByHover = true;
                clearGalleryAutoplayTimer();
                scheduleGalleryAutoplay();
            });
            $shell.on('mouseleave' + gallerySlideshowNamespace, function() {
                gallerySlideshowPausedByHover = false;
                scheduleGalleryAutoplay();
            });
        }

        function renderCurrentGalleryPage(options) {
            // Pause any playing videos before re-rendering the page
            $container.find('.jzsa-video-player').each(function() {
                if (!this.paused) { this.pause(); }
            });

            var renderOptions = options || {};
            var useScroller = galleryScrollable && galleryRows > 0;
            var gap = parseInt(readGalleryAttr($container, 'gap'), 10) || 4;

            if (layout === 'justified') {
                var justified = getJustifiedLayoutData($container, allPhotos);
                $container[0].style.setProperty('--jzsa-gallery-gap', justified.gap + 'px');

                if (useScroller) {
                    renderJustifiedRows(
                        $container,
                        justified.rows,
                        justified.containerWidth,
                        justified.targetHeight,
                        justified.gap
                    );

                    var totalJustifiedRows = justified.rows.length;
                    var isJustifiedScrollable = totalJustifiedRows > galleryRows;
                    if (isJustifiedScrollable) {
                        var visibleHeight = (galleryRows * justified.targetHeight) + ((galleryRows - 1) * gap);
                        setGalleryScrollableState($container, true, visibleHeight);
                    } else {
                        setGalleryScrollableState($container, false);
                    }

                    paginationState.currentPage = 0;
                    paginationState.totalPages = 1;
                    removeGalleryPaginationControls($container);
                    setGalleryPaginationHeightState($container, false);
                    setupGalleryMouseInteractions($container, {
                        enabled: !interactionLock && isJustifiedScrollable,
                        mode: 'scroll'
                    });
                } else {
                    setGalleryScrollableState($container, false);

                    var rowsPerPage = galleryRows > 0 ? galleryRows : (justified.rows.length || 1);
                    paginationState.totalPages = justified.rows.length > 0 ? Math.ceil(justified.rows.length / rowsPerPage) : 1;
                    clampGalleryPage(paginationState);

                    var rowStart = paginationState.currentPage * rowsPerPage;
                    var rowsForPage = (galleryRows > 0)
                        ? justified.rows.slice(rowStart, rowStart + rowsPerPage)
                        : justified.rows;

                    var renderJustifiedPage = function() {
                        renderJustifiedRows(
                            $container,
                            rowsForPage,
                            justified.containerWidth,
                            justified.targetHeight,
                            justified.gap
                        );
                    };

                    if (renderOptions.animate) {
                        animateGalleryPageTransition($container, renderOptions.direction || 1, renderJustifiedPage);
                    } else {
                        renderJustifiedPage();
                    }

                    var fixedJustifiedRows = galleryRows > 0 ? galleryRows : rowsPerPage;
                    var fixedJustifiedHeight =
                        (fixedJustifiedRows * justified.targetHeight) +
                        ((fixedJustifiedRows - 1) * gap);
                    setGalleryPaginationHeightState(
                        $container,
                        paginationState.totalPages > 1 && fixedJustifiedRows > 0,
                        fixedJustifiedHeight
                    );

                    var onJustifiedPageChange = function(nextPage, direction) {
                        paginationState.currentPage = nextPage;
                        renderCurrentGalleryPage({
                            animate: true,
                            direction: direction
                        });
                    };

                    setupGalleryPaginationControls($container, paginationState, onJustifiedPageChange, {
                        showCounter: true,
                        showAutoplayProgress: shouldShowGallerySlideshowProgress(),
                        showSlideshowControls: gallerySlideshowEnabled,
                        isAutoplayRunning: function() {
                            return gallerySlideshowEnabled && !gallerySlideshowPausedByUser;
                        },
                        onToggleAutoplay: function() {
                            gallerySlideshowPausedByUser = !gallerySlideshowPausedByUser;
                            syncGalleryAutoplayState();
                            renderCurrentGalleryPage();
                        }
                    });
                    setupGalleryMouseInteractions($container, {
                        enabled: !interactionLock && paginationState.totalPages > 1,
                        mode: 'pagination',
                        onPageSwipe: function(direction) {
                            if ($container.data('jzsaGalleryAnimating')) {
                                return;
                            }

                            var nextPage = paginationState.currentPage + direction;
                            if (nextPage < 0) {
                                nextPage = paginationState.totalPages - 1;
                            } else if (nextPage > paginationState.totalPages - 1) {
                                nextPage = 0;
                            }

                            onJustifiedPageChange(nextPage, direction);
                        },
                        onPageDragStart: function(direction) {
                            if ($container.data('jzsaGalleryAnimating')) {
                                return null;
                            }

                            var currentPage = paginationState.currentPage;
                            var nextPage = currentPage + direction;
                            if (nextPage < 0) {
                                nextPage = paginationState.totalPages - 1;
                            } else if (nextPage > paginationState.totalPages - 1) {
                                nextPage = 0;
                            }

                            var nextRowStart = nextPage * rowsPerPage;
                            var nextRowsForPage = (galleryRows > 0)
                                ? justified.rows.slice(nextRowStart, nextRowStart + rowsPerPage)
                                : justified.rows;

                            return createGalleryPageDragTransition(
                                $container,
                                direction,
                                function() {
                                    renderJustifiedRows(
                                        $container,
                                        nextRowsForPage,
                                        justified.containerWidth,
                                        justified.targetHeight,
                                        justified.gap
                                    );
                                },
                                function() {
                                    renderJustifiedRows(
                                        $container,
                                        rowsForPage,
                                        justified.containerWidth,
                                        justified.targetHeight,
                                        justified.gap
                                    );
                                },
                                function(committed) {
                                    paginationState.currentPage = committed ? nextPage : currentPage;
                                    renderCurrentGalleryPage();
                                }
                            );
                        }
                    });
                }
            } else {
                var activeColumns = getUniformColumnsForViewport($container);
                var allItems = getGalleryPageItems(allPhotos, 0, allPhotos.length || 1);
                var explicitUniformHeight = parseInt(readGalleryAttr($container, 'explicit-height'), 10);
                var fillUniformRowHeight = 0;

                if (gallerySizing === 'fill' && galleryRows > 0 && !isNaN(explicitUniformHeight) && explicitUniformHeight > 0) {
                    fillUniformRowHeight = (explicitUniformHeight - ((galleryRows - 1) * gap)) / galleryRows;
                    if (!(fillUniformRowHeight > 0)) {
                        fillUniformRowHeight = 0;
                    }
                }

                if (useScroller) {
                    buildUniformGallery($container, allItems, {
                        tileHeight: fillUniformRowHeight
                    });

                    var totalUniformRows = activeColumns > 0 ? Math.ceil(allPhotos.length / activeColumns) : 0;
                    var isUniformScrollable = totalUniformRows > galleryRows;
                    if (isUniformScrollable) {
                        var rowHeight = fillUniformRowHeight;

                        if (!(rowHeight > 0)) {
                            var $firstThumb = $container.find('.jzsa-gallery-thumb').first();
                            rowHeight = $firstThumb.length ? $firstThumb.outerHeight() : 0;
                        }

                        if (!rowHeight || rowHeight <= 0) {
                            var containerWidth = getGalleryContainerWidth($container);
                            var cellWidth = (containerWidth - (gap * (activeColumns - 1))) / activeColumns;
                            rowHeight = cellWidth * 0.75; // 4:3 aspect ratio in grid layout
                        }

                        var visibleUniformHeight = (galleryRows * rowHeight) + ((galleryRows - 1) * gap);
                        setGalleryScrollableState($container, true, visibleUniformHeight);

                        // Second pass: once scrollbar appears, available width can
                        // shrink slightly, which changes thumbnail height. Re-measure
                        // and apply a corrected max-height to avoid showing a strip
                        // of the next row.
                        var adjustedRowHeight = fillUniformRowHeight;
                        if (!(adjustedRowHeight > 0)) {
                            var $adjustedThumb = $container.find('.jzsa-gallery-thumb').first();
                            adjustedRowHeight = $adjustedThumb.length ? $adjustedThumb.outerHeight() : 0;
                        }
                        if (adjustedRowHeight && adjustedRowHeight > 0) {
                            var adjustedVisibleHeight = (galleryRows * adjustedRowHeight) + ((galleryRows - 1) * gap) + 1;
                            setGalleryScrollableState($container, true, adjustedVisibleHeight);
                        }
                    } else {
                        setGalleryScrollableState($container, false);
                    }

                    paginationState.currentPage = 0;
                    paginationState.totalPages = 1;
                    removeGalleryPaginationControls($container);
                    setGalleryPaginationHeightState($container, false);
                    setupGalleryMouseInteractions($container, {
                        enabled: !interactionLock && isUniformScrollable,
                        mode: 'scroll'
                    });
                } else {
                    setGalleryScrollableState($container, false);

                    var photosPerPage = galleryRows > 0 ? (galleryRows * activeColumns) : allPhotos.length;
                    if (photosPerPage <= 0) {
                        photosPerPage = allPhotos.length > 0 ? allPhotos.length : 1;
                    }

                    paginationState.totalPages = allPhotos.length > 0 ? Math.ceil(allPhotos.length / photosPerPage) : 1;
                    clampGalleryPage(paginationState);

                    var pageItems = getGalleryPageItems(allPhotos, paginationState.currentPage, photosPerPage);
                    var shouldRenderPlaceholders = paginationState.totalPages > 1 && galleryRows > 0;
                    var placeholderCount = shouldRenderPlaceholders
                        ? Math.max(0, photosPerPage - pageItems.length)
                        : 0;
                    var pageTileHeight = fillUniformRowHeight;
                    var renderUniformPage = function() {
                        buildUniformGallery($container, pageItems, {
                            placeholderCount: placeholderCount,
                            tileHeight: pageTileHeight
                        });
                    };

                    if (renderOptions.animate) {
                        animateGalleryPageTransition($container, renderOptions.direction || 1, renderUniformPage);
                    } else {
                        renderUniformPage();
                    }

                    var fixedUniformRows = galleryRows > 0 ? galleryRows : Math.max(1, Math.ceil(allPhotos.length / activeColumns));
                    var fixedUniformRowHeight = pageTileHeight;
                    if (!(fixedUniformRowHeight > 0)) {
                        var $fixedThumb = $container.find('.jzsa-gallery-thumb').first();
                        fixedUniformRowHeight = $fixedThumb.length ? $fixedThumb.outerHeight() : 0;
                    }
                    if (!fixedUniformRowHeight || fixedUniformRowHeight <= 0) {
                        var fixedContainerWidth = getGalleryContainerWidth($container);
                        var fixedCellWidth = (fixedContainerWidth - (gap * (activeColumns - 1))) / activeColumns;
                        fixedUniformRowHeight = fixedCellWidth * 0.75; // 4:3 aspect ratio in grid layout
                    }

                    var fixedUniformHeight =
                        (fixedUniformRows * fixedUniformRowHeight) +
                        ((fixedUniformRows - 1) * gap);
                    setGalleryPaginationHeightState(
                        $container,
                        paginationState.totalPages > 1 && fixedUniformRows > 0,
                        fixedUniformHeight
                    );

                    var onUniformPageChange = function(nextPage, direction) {
                        paginationState.currentPage = nextPage;
                        renderCurrentGalleryPage({
                            animate: true,
                            direction: direction
                        });
                    };

                    setupGalleryPaginationControls($container, paginationState, onUniformPageChange, {
                        showCounter: true,
                        showAutoplayProgress: shouldShowGallerySlideshowProgress(),
                        showSlideshowControls: gallerySlideshowEnabled,
                        isAutoplayRunning: function() {
                            return gallerySlideshowEnabled && !gallerySlideshowPausedByUser;
                        },
                        onToggleAutoplay: function() {
                            gallerySlideshowPausedByUser = !gallerySlideshowPausedByUser;
                            syncGalleryAutoplayState();
                            renderCurrentGalleryPage();
                        }
                    });
                    setupGalleryMouseInteractions($container, {
                        enabled: !interactionLock && paginationState.totalPages > 1,
                        mode: 'pagination',
                        onPageSwipe: function(direction) {
                            if ($container.data('jzsaGalleryAnimating')) {
                                return;
                            }

                            var nextPage = paginationState.currentPage + direction;
                            if (nextPage < 0) {
                                nextPage = paginationState.totalPages - 1;
                            } else if (nextPage > paginationState.totalPages - 1) {
                                nextPage = 0;
                            }

                            onUniformPageChange(nextPage, direction);
                        },
                        onPageDragStart: function(direction) {
                            if ($container.data('jzsaGalleryAnimating')) {
                                return null;
                            }

                            var currentPage = paginationState.currentPage;
                            var nextPage = currentPage + direction;
                            if (nextPage < 0) {
                                nextPage = paginationState.totalPages - 1;
                            } else if (nextPage > paginationState.totalPages - 1) {
                                nextPage = 0;
                            }

                            var nextPageItems = getGalleryPageItems(allPhotos, nextPage, photosPerPage);
                            var nextPlaceholderCount = shouldRenderPlaceholders
                                ? Math.max(0, photosPerPage - nextPageItems.length)
                                : 0;

                            return createGalleryPageDragTransition(
                                $container,
                                direction,
                                function() {
                                    buildUniformGallery($container, nextPageItems, {
                                        placeholderCount: nextPlaceholderCount,
                                        tileHeight: pageTileHeight
                                    });
                                },
                                function() {
                                    buildUniformGallery($container, pageItems, {
                                        placeholderCount: placeholderCount,
                                        tileHeight: pageTileHeight
                                    });
                                },
                                function(committed) {
                                    paginationState.currentPage = committed ? nextPage : currentPage;
                                    renderCurrentGalleryPage();
                                }
                            );
                        }
                    });
                }
            }

            watchGalleryInitialThumbLoad();
            scheduleGalleryAutoplay();
        }

        renderCurrentGalleryPage();

        // Re-render gallery page on resize:
        // - justified layout depends on container width (re-render only when width changes)
        // - grid layout only needs re-render when the column breakpoint changes
        var resizeNamespace = 'resize.jzsa-gallery-' + $container.attr('id');
        $(window).off(resizeNamespace);
        var resizeTimer;
        var resizePending = false;
        var lastResizeColumns = layout !== 'justified' ? getUniformColumnsForViewport($container) : 0;
        var lastResizeWidth = layout === 'justified' ? getGalleryContainerWidth($container) : 0;
        $(window).on(resizeNamespace, function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // For grid layout, skip re-render if column count hasn't changed.
                if (layout !== 'justified') {
                    var currentColumns = getUniformColumnsForViewport($container);
                    if (currentColumns === lastResizeColumns) {
                        return;
                    }
                    lastResizeColumns = currentColumns;
                } else {
                    // For justified layout, skip re-render if container width hasn't changed.
                    // On iOS, browser chrome collapse/expand fires resize events but only changes
                    // the viewport height, not the content width. Without this guard, justified
                    // layout would destructively re-render on every scroll-triggered chrome toggle.
                    var currentWidth = getGalleryContainerWidth($container);
                    if (currentWidth === lastResizeWidth) {
                        return;
                    }
                    lastResizeWidth = currentWidth;
                }
                runOnNextPaint(function() {
                    if ($container.hasClass('jzsa-video-playing')) {
                        resizePending = true;
                        return;
                    }
                    renderCurrentGalleryPage();
                });
            }, 150);
        });

        // When video playback ends, flush any deferred resize re-render
        $container.on('jzsa:video-stopped', function() {
            if (resizePending) {
                resizePending = false;
                renderCurrentGalleryPage();
            }
        });

        // When the fullscreen slideshow exits, it fires this event so the
        // gallery can navigate to the page containing the photo the user
        // was last viewing.  All layout/pagination state lives in this
        // closure, so the computation happens here rather than in the
        // fullscreen exit handler.
        $container.on('jzsa:focus-index', function(_e, targetIndex) {
            if (typeof targetIndex !== 'number' || targetIndex < 0 || targetIndex >= allPhotos.length) {
                return;
            }

            // Scrollable gallery: scroll the item into view inside the container.
            // Use getBoundingClientRect() rather than offsetTop so the calculation
            // is correct for both grid (direct children) and justified layout
            // (items nested inside .jzsa-justified-row elements).
            var $item = $container.find('[data-index="' + targetIndex + '"]').first();
            if (galleryScrollable && galleryRows > 0 &&
                $container[0].scrollHeight > $container[0].clientHeight) {
                if ($item.length) {
                    var containerRect = $container[0].getBoundingClientRect();
                    var itemRect = $item[0].getBoundingClientRect();
                    $container[0].scrollTop = $container[0].scrollTop
                        + itemRect.top - containerRect.top
                        - ($container[0].clientHeight - itemRect.height) / 2;
                }
                return;
            }

            // Paginated gallery: compute the target page and re-render
            if (paginationState.totalPages <= 1) {
                // No pagination and no internal scroller - all items are in the
                // normal page flow.  Scroll the page itself to the target item.
                if ($item.length) {
                    $item[0].scrollIntoView({ block: 'center', behavior: 'instant' });
                }
                return;
            }

            var targetPage;
            if (layout === 'justified') {
                var justified = getJustifiedLayoutData($container, allPhotos);
                var rowsPerPage = galleryRows > 0 ? galleryRows : (justified.rows.length || 1);
                var photosSeen = 0;
                var targetRow = 0;
                for (var r = 0; r < justified.rows.length; r++) {
                    photosSeen += justified.rows[r].length;
                    if (targetIndex < photosSeen) {
                        targetRow = r;
                        break;
                    }
                }
                targetPage = Math.floor(targetRow / rowsPerPage);
            } else {
                var activeColumns = getUniformColumnsForViewport($container);
                var photosPerPage = galleryRows > 0 ? (galleryRows * activeColumns) : allPhotos.length;
                if (photosPerPage <= 0) {
                    photosPerPage = allPhotos.length > 0 ? allPhotos.length : 1;
                }
                targetPage = Math.floor(targetIndex / photosPerPage);
            }

            if (targetPage !== paginationState.currentPage) {
                paginationState.currentPage = targetPage;
                renderCurrentGalleryPage();
            }
        });

        // Build the fullscreen slideshow and initialize it eagerly (same as
        // player/carousel modes - Swiper is always ready, not lazily created).
        slideshowId = buildGallerySlideshow($container);
        $slideshow = $('#' + slideshowId);
        initializeSwiper($slideshow[0], 'slideshow');

        var fullscreenSyncNamespace = '.jzsaGalleryFullscreen-' + ($container.attr('id') || 'gallery');
        $(document).off(
            'fullscreenchange' + fullscreenSyncNamespace +
            ' webkitfullscreenchange' + fullscreenSyncNamespace +
            ' mozfullscreenchange' + fullscreenSyncNamespace +
            ' MSFullscreenChange' + fullscreenSyncNamespace
        );
        $(document).on(
            'fullscreenchange' + fullscreenSyncNamespace +
            ' webkitfullscreenchange' + fullscreenSyncNamespace +
            ' mozfullscreenchange' + fullscreenSyncNamespace +
            ' MSFullscreenChange' + fullscreenSyncNamespace,
            function() {
                window.setTimeout(syncGalleryAutoplayState, 80);
            }
        );

        $slideshow.off(
            'click' + fullscreenSyncNamespace +
            ' dblclick' + fullscreenSyncNamespace +
            ' touchend' + fullscreenSyncNamespace
        );
        $slideshow.on(
            'click' + fullscreenSyncNamespace +
            ' dblclick' + fullscreenSyncNamespace +
            ' touchend' + fullscreenSyncNamespace,
            function() {
                window.setTimeout(syncGalleryAutoplayState, 80);
            }
        );

        var fullscreenToggle =
            $container.attr('data-fullscreen-toggle') || 'button-only';
        if (interactionLock) {
            fullscreenToggle = 'disabled';
        }

        function openGalleryPlayerAtIndex(index) {
            if (interactionLock) {
                return;
            }

            var safeIndex = typeof index === 'number' && index >= 0 ? index : 0;
            var swiper = swipers[slideshowId];
            if (swiper) {
                if (swiper.params.loop && typeof swiper.slideToLoop === 'function') {
                    swiper.slideToLoop(safeIndex, 0, false);
                } else {
                    swiper.slideTo(safeIndex, 0, false);
                }
            }
            clearGalleryAutoplayTimer();
            toggleFullscreen($slideshow[0]);
        }

        function getGalleryPhotoIndexFromElement(targetEl) {
            var $target = $(targetEl);
            var direct = parseInt($target.attr('data-index'), 10);
            if (!isNaN(direct) && direct >= 0) {
                return direct;
            }

            var closest = parseInt($target.closest('[data-index]').attr('data-index'), 10);
            if (!isNaN(closest) && closest >= 0) {
                return closest;
            }

            return 0;
        }

        function openGalleryPlayerFromThumb(targetEl) {
            var index = getGalleryPhotoIndexFromElement(targetEl);
            openGalleryPlayerAtIndex(index);
        }

        function isGalleryVideoInteractionTarget(targetEl) {
            return $(targetEl).closest('.jzsa-gallery-item-video .jzsa-video-wrapper').length > 0;
        }

        if (!interactionLock && fullscreenToggle === 'click') {
            $container.on('click', '.jzsa-gallery-thumb', function(e) {
                if ($container.data('jzsaGallerySuppressClick')) {
                    return;
                }
                if (isGalleryVideoInteractionTarget(e.target)) {
                    return;
                }
                e.preventDefault();
                openGalleryPlayerFromThumb(this);
            });

            // Video items: click anywhere except plyr controls opens fullscreen.
            $container.on('click', '.jzsa-gallery-item-video', function(e) {
                if ($container.data('jzsaGallerySuppressClick')) {
                    return;
                }
                if (shouldIgnoreClick(e.target)) {
                    return;
                }
                e.preventDefault();
                openGalleryPlayerFromThumb(this);
            });
        } else if (!interactionLock && fullscreenToggle === 'double-click') {
            $container.on('dblclick', '.jzsa-gallery-thumb', function(e) {
                if (isGalleryVideoInteractionTarget(e.target)) {
                    return;
                }
                e.preventDefault();
                openGalleryPlayerFromThumb(this);
            });

            // Mobile/touch fallback for double-click mode.
            $container.on('touchend', '.jzsa-gallery-thumb', function(e) {
                if ($container.data('jzsaGallerySuppressClick')) {
                    return;
                }
                if (isGalleryVideoInteractionTarget(e.target)) {
                    return;
                }
                handleDoubleTap(e, function() {
                    openGalleryPlayerFromThumb(e.currentTarget || e.target);
                });
            });

            // Video items: double-click anywhere except plyr controls opens fullscreen.
            $container.on('dblclick', '.jzsa-gallery-item-video', function(e) {
                if (shouldIgnoreClick(e.target)) {
                    return;
                }
                e.preventDefault();
                openGalleryPlayerFromThumb(this);
            });

            // Video items: mobile/touch double-tap fallback.
            $container.on('touchend', '.jzsa-gallery-item-video', function(e) {
                if ($container.data('jzsaGallerySuppressClick')) {
                    return;
                }
                if (shouldIgnoreClick(e.target)) {
                    return;
                }
                handleDoubleTap(e, function() {
                    openGalleryPlayerFromThumb(e.currentTarget || e.target);
                });
            });
        }

        // Attach fullscreen button click/keyboard handlers (unless fullscreen is disabled).
        if (!interactionLock && fullscreenToggle !== 'disabled') {
            $container.on('click', '.jzsa-gallery-thumb-fs-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openGalleryPlayerFromThumb(this);
            });
            $container.on('keydown', '.jzsa-gallery-thumb-fs-btn', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ' && e.keyCode !== 13 && e.keyCode !== 32) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                openGalleryPlayerFromThumb(this);
            });
        }

        // Attach download button click handler on gallery thumbnails.
        if (!interactionLock) {
            $container.on('click', '.jzsa-gallery-thumb-download-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var index = getGalleryPhotoIndexFromElement(this);
                var photo = allPhotos[index];
                if (!photo) {
                    return;
                }

                var mediaType = photo.type === 'video' ? 'video' : 'photo';
                var mediaUrl = mediaType === 'video'
                    ? (photo.video || photo.full || photo.preview)
                    : (photo.full || photo.preview);
                if (!mediaUrl) {
                    return;
                }

                var filename = (mediaType === 'video' ? 'video-' : 'photo-') + (index + 1) + (mediaType === 'video' ? '.mp4' : '.jpg');
                var $btn = $(this);

                function restoreThumbButtonState() {
                    $btn.css('opacity', '');
                }

                function requestGalleryProxyDownload(allowLargeDownload) {
                    $btn.css('opacity', '0.5');
                    showDownloadStatus(allowLargeDownload ? 'Preparing large download...' : 'Preparing download...', false);

                    var requestData = {
                        action: 'jzsa_download_image',
                        nonce: jzsaAjax.downloadNonce,
                        media_url: mediaUrl,
                        image_url: mediaUrl,
                        filename: filename
                    };
                    if (galleryDownloadWarningSizeBytes !== null) {
                        requestData.warning_size_bytes = galleryDownloadWarningSizeBytes;
                    }
                    if (allowLargeDownload) {
                        requestData.allow_large_download = 'true';
                    }

                    $.ajax({
                        url: jzsaAjax.ajaxUrl,
                        type: 'POST',
                        data: requestData,
                        xhrFields: { responseType: 'blob' },
                        success: function(blob) {
                            detectDownloadErrorFromBlob(blob, function(blobErrorData) {
                                if (blobErrorData) {
                                    if (blobErrorData.requiresLargeDownloadConfirmation && !allowLargeDownload) {
                                        restoreThumbButtonState();
                                        if (confirmLargeDownload(blobErrorData)) {
                                            requestGalleryProxyDownload(true);
                                        } else {
                                            showDownloadStatus('Download canceled.', false);
                                        }
                                        return;
                                    }

                                    showDownloadErrorMessage(blobErrorData.message);
                                    restoreThumbButtonState();
                                    return;
                                }
                                downloadBlobToFile(blob, filename);
                                showDownloadStatus('Download started.', false);
                                restoreThumbButtonState();
                            });
                        },
                        error: function(xhr) {
                            var ajaxErrorData = getAjaxErrorData(xhr);
                            if (ajaxErrorData) {
                                if (ajaxErrorData.requiresLargeDownloadConfirmation && !allowLargeDownload) {
                                    restoreThumbButtonState();
                                    if (confirmLargeDownload(ajaxErrorData)) {
                                        requestGalleryProxyDownload(true);
                                    } else {
                                        showDownloadStatus('Download canceled.', false);
                                    }
                                    return;
                                }

                                showDownloadErrorMessage(ajaxErrorData.message);
                                restoreThumbButtonState();
                                return;
                            }
                            var link = document.createElement('a');
                            link.href = mediaUrl;
                            link.download = filename;
                            link.target = '_blank';
                            link.rel = 'noopener noreferrer';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            showDownloadStatus('Opening direct download...', false);
                            restoreThumbButtonState();
                        }
                    });
                }

                requestGalleryProxyDownload(false);
            });
        }

        // GALLERY TOUCH REVEAL: show per-item buttons on tap; ignore scroll gestures.
        // Passive listeners so iOS never has to wait for JS before committing a scroll frame.
        (function() {
            var galleryEl = $container[0];
            function closestItem(target) {
                return target && target.closest ? target.closest('.jzsa-gallery-item') : null;
            }
            galleryEl.addEventListener('touchstart', function(e) {
                var item = closestItem(e.target);
                if (!item) return;
                var t = e.touches[0];
                $(item).data('jzsaRevealTouchX', t.clientX).data('jzsaRevealTouchY', t.clientY);
            }, { passive: true });
            galleryEl.addEventListener('touchend', function(e) {
                var item = closestItem(e.target);
                if (!item) return;
                var $item = $(item);
                var startX = $item.data('jzsaRevealTouchX');
                var startY = $item.data('jzsaRevealTouchY');
                $item.removeData('jzsaRevealTouchX jzsaRevealTouchY');
                if (startX == null) return;
                var t = e.changedTouches[0];
                if (Math.abs(t.clientX - startX) > 8 || Math.abs(t.clientY - startY) > 8) return;
                $container.find('.jzsa-item-touched').not($item).each(function() {
                    clearTimeout($(this).data('jzsaItemTouchTimer'));
                    $(this).removeData('jzsaItemTouchTimer').removeClass('jzsa-item-touched');
                });
                $item.addClass('jzsa-item-touched');
                clearTimeout($item.data('jzsaItemTouchTimer'));
                $item.data('jzsaItemTouchTimer', setTimeout(function() {
                    $item.removeData('jzsaItemTouchTimer').removeClass('jzsa-item-touched');
                }, 3000));
            }, { passive: true });
            galleryEl.addEventListener('touchcancel', function(e) {
                var item = closestItem(e.target);
                if (!item) return;
                $(item).removeData('jzsaRevealTouchX jzsaRevealTouchY');
            }, { passive: true });
        }());

        $container.off('jzsa:exif-update.jzsaGalleryInfoRefresh');
        $container.on('jzsa:exif-update.jzsaGalleryInfoRefresh', function() {
            var latestPhotosJson = $container.attr('data-all-photos');
            if (latestPhotosJson) {
                try {
                    allPhotos = JSON.parse(latestPhotosJson);
                } catch (e) { /* ignore */ }
            }
            updateGalleryThumbnailInfoBoxes($container);
        });

        // Wave 2: schedule background metadata prefetch if any zone uses
        // filename/name or EXIF placeholders.
        var galleryZoneFormats = readInfoZoneFormats($container);
        scheduleExifPrefetch($container, galleryZoneFormats);

        jzsaDebug(
            '✅ Gallery initialized:',
            $container.attr('id'),
            '| layout:',
            layout,
            '| photos:',
            allPhotos.length,
            '| rows:',
            galleryRows > 0 ? galleryRows : 'all',
            '| scroll:',
            galleryScrollable ? 'true' : 'false',
            '| pages:',
            paginationState.totalPages
        );
    }

    // ============================================================================
    // GALLERY INITIALIZATION
    // ============================================================================

    function initializeAllGalleries() {
        $('.jzsa-album').not('.jzsa-gallery-slideshow, .jzsa-gallery-controls').each(function(index) {
            var $gallery = $(this);

            // Generate unique ID if not present
            if (!$gallery.attr('id')) {
                $gallery.attr('id', 'jzsa-album-' + (index + 1));
            }

            // Get mode
            var mode = $gallery.attr('data-mode') || 'gallery';

            // Dispatch to the correct initializer
            if (mode === 'gallery') {
                initializeGallery(this);
            } else {
                initializeSwiper(this, mode);
            }
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Wait for Swiper library to load
        if (typeof Swiper !== 'undefined') {
            // console.log('✅ Swiper library found, initializing galleries...');
            initializeAllGalleries();
        } else {
            // console.error('❌ Swiper library not loaded!');
        }
    });

    // Export for global access
    window.SharedGooglePhotos = {
        swipers: swipers,
        initialize: initializeSwiper,
        initializeGallery: initializeGallery,
        reinitialize: initializeAllGalleries
    };

})(jQuery);
