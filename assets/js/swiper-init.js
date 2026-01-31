/**
 * Swiper Gallery Initialization for Shared Albums for Google Photos (by JanZeman)
 */
(function($) {
'use strict';

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

	var swipers = {};

    // ============================================================================
    // FULLSCREEN FUNCTIONALITY
    // ============================================================================

    // Fullscreen toggle function (with iPhone pseudo-fullscreen fallback)
    function toggleFullscreen(element, showHints) {
        var showHintsFn = showHints;
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
            } else {
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
    function createHintSystem(galleryId, fullScreenSwitch, fullScreenNavigation) {
        var HINTS_STORAGE_KEY = 'jzsa-hints-counter'; // Global counter for all albums
        var MAX_HINT_DISPLAYS = 2; // Maximum number of times to show hints
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

                // Build hint message - single-click first, then double-click
                var hints = [];

                // Single-click hints
                if (fullScreenSwitch === 'single-click') {
                    hints.push('Click anywhere to exit full screen');
                }
                if (fullScreenNavigation === 'single-click') {
                    hints.push('Click anywhere to navigate faster');
                }

                // Add a line break between the hints
                hints.push('');

                // Double-click hints
                if (fullScreenSwitch === 'double-click') {
                    hints.push('Double-click to exit full screen');
                }
                if (fullScreenNavigation === 'double-click') {
                    hints.push('Double-click anywhere to navigate faster');
                }

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

    // Helper: Detect Android (for platform-specific workarounds)
    function isAndroid() {
        var ua = window.navigator.userAgent || '';
        return /Android/i.test(ua);
    }

    // Helper: Detect iOS devices (iPhone, iPad, iPod, and iPadOS with Mac-like UA)
    // Used for pseudo fullscreen fallback and for old-iOS layout workarounds.
    function isIosDevice() {
        var ua = window.navigator.userAgent || '';
        var platform = window.navigator.platform || '';
        var isAppleMobile = /iPad|iPhone|iPod/i.test(ua);
        var isTouchMac = platform === 'MacIntel' && window.navigator.maxTouchPoints > 1;
        return isAppleMobile || isTouchMac;
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
    function enterPseudoFullscreen(element) {
        if (!element) {
            return false;
        }
        var $el = $(element);
        if ($el.hasClass('jzsa-pseudo-fullscreen')) {
            return true;
        }
        $el.addClass('jzsa-pseudo-fullscreen jzsa-is-fullscreen');
        $('html, body').addClass('jzsa-no-scroll');
        return true;
    }

    function exitPseudoFullscreen(element) {
        if (!element) {
            return;
        }
        $(element).removeClass('jzsa-pseudo-fullscreen jzsa-is-fullscreen');
        $('html, body').removeClass('jzsa-no-scroll');
    }

    // Helper: Check if click should be ignored (clicked on UI element)
    function shouldIgnoreClick(target) {
        return $(target).closest('.swiper-button-next, .swiper-button-prev, .swiper-button-fullscreen, .swiper-button-external-link, .swiper-button-download, .swiper-button-play-pause, .swiper-pagination').length > 0;
    }

    // Helper: Double-tap detection for touch devices
    var DOUBLE_TAP_MAX_DELAY = 300; // ms - maximum time between taps to be considered a double-tap
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

    // Helper: Build slides HTML structure (for photo array)
    function buildSlidesHtml(photos) {
        var html = '';
        photos.forEach(function(photo) {
            // Photo format: object with preview and full URLs
            var previewUrl = photo.preview || photo.full;
            var fullUrl = photo.full;

            html += '<div class="swiper-slide">' +
                '<div class="swiper-zoom-container">' +
                '<img src="' + previewUrl + '" ' +
                (previewUrl !== fullUrl ? 'data-full-src="' + fullUrl + '" ' : '') +
                'alt="Photo" class="jzsa-progressive-image" />' +
                '</div>' +
                '</div>';
        });
        return html;
    }

    // Helper: Apply fullscreen autoplay settings immediately (for Android compatibility)
    function applyFullscreenAutoplaySettings(swiper, params) {
        // Only run this workaround on Android devices where fullscreenchange events
        // are known to be unreliable.
		if (!params.fullScreenAutoplay || !isAndroid()) {
			return;
		}

		jzsaDebug('🔍 Applying fullscreen autoplay settings immediately (Android workaround)');

        // Stop current autoplay if running
        if (swiper.autoplay && swiper.autoplay.running) {
            swiper.autoplay.stop();
        }

        // Update autoplay delay for fullscreen mode
			var newDelay = params.fullScreenAutoplayDelay * MILLISECONDS_PER_SECOND;
			jzsaDebug('🔍 Setting fullscreen autoplay delay to:', newDelay, 'ms (', params.fullScreenAutoplayDelay, 's)');

        // Update both params and the active autoplay object
        swiper.params.autoplay.delay = newDelay;
        if (swiper.autoplay) {
            swiper.autoplay.delay = newDelay;
        }

        // Start fullscreen autoplay after a short delay to ensure fullscreen is active
        setTimeout(function() {
				if (!params.autoplayPausedByInteraction && swiper.autoplay) {
					swiper.autoplay.start();
					jzsaDebug('▶️  Fullscreen autoplay started immediately (delay: ' + params.fullScreenAutoplayDelay + 's)');
				}
        }, 100);

        // ANDROID WORKAROUND: Some Android browsers don't fire fullscreen change events reliably.
        // Poll for fullscreen state and apply settings if events don't fire within 300ms
			var fullscreenCheckTimeout = setTimeout(function() {
				var nowFullscreen = isFullscreen();

				if (nowFullscreen && params.fullScreenAutoplay) {
					jzsaDebug('⚠️  Fullscreen change event did not fire - applying settings via fallback (Android workaround)');

                // Ensure settings are applied
                if (swiper.autoplay && swiper.autoplay.running) {
                    swiper.autoplay.stop();
                }

                var newDelay = params.fullScreenAutoplayDelay * MILLISECONDS_PER_SECOND;
                swiper.params.autoplay.delay = newDelay;
                if (swiper.autoplay) {
                    swiper.autoplay.delay = newDelay;
                }

					if (!params.autoplayPausedByInteraction) {
						swiper.autoplay.start();
						jzsaDebug('▶️  Fullscreen autoplay started via fallback (delay: ' + params.fullScreenAutoplayDelay + 's)');
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

        if (fullscreenElement === containerElement) {
			// Entering fullscreen - switch to fullscreen autoplay settings
			var logPrefix = params.browserPrefix ? ' (' + params.browserPrefix + ')' : '';
			jzsaDebug('🔍 Fullscreen entered for gallery' + logPrefix + ':', params.galleryId);

            // For carousel-to-player, switch layout to a single slide in
            // fullscreen while keeping the preview in carousel mode.
            if (params.mode === 'carousel-to-player') {
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

            if (!params.browserPrefix) {
                // Only log detailed debug info for standard API (avoid log spam)
                console.log('🔍 fullScreenAutoplay:', params.fullScreenAutoplay);
                console.log('🔍 fullScreenAutoplayDelay:', params.fullScreenAutoplayDelay);
            }

            if (params.fullScreenAutoplay) {
                // Stop current autoplay if running
                if (swiper.autoplay && swiper.autoplay.running) {
                    swiper.autoplay.stop();
                }
                // Update autoplay delay for fullscreen mode
                var newDelay = params.fullScreenAutoplayDelay * MILLISECONDS_PER_SECOND;

			if (!params.browserPrefix) {
				jzsaDebug('🔍 Setting fullscreen autoplay delay to:', newDelay, 'ms (', params.fullScreenAutoplayDelay, 's)');
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

                // Start fullscreen autoplay if enabled and not paused by interaction
				if (!params.autoplayPausedByInteraction) {
					swiper.autoplay.start();
					jzsaDebug('▶️  Fullscreen autoplay started (delay: ' + params.fullScreenAutoplayDelay + 's' + logPrefix + ')');
				}
            }
        } else if (!fullscreenElement && swiper) {
			// Exiting fullscreen (this gallery was in fullscreen before) - switch back to normal autoplay settings
			var logPrefix = params.browserPrefix ? ' (' + params.browserPrefix + ')' : '';
			jzsaDebug('🔍 Fullscreen exited for gallery' + logPrefix + ':', params.galleryId);

            // For carousel-to-player, restore the original multi-slide layout
            // but keep the same logical photo index the user was viewing in
            // fullscreen. We must capture the realIndex *before* changing
            // slidesPerView/breakpoints, because Swiper may adjust indices when
            // layout changes.
            if (params.mode === 'carousel-to-player' && params.originalSlidesPerView != null) {
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

            // Attempt to auto-correct WordPress grid item width if iOS Safari/WebKit
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

            // Remove fullscreen class
            $(containerElement).removeClass('jzsa-is-fullscreen');

            params.autoplayPausedByInteraction = false;

            if (params.autoplay) {
                // Stop current autoplay if running
                if (swiper.autoplay && swiper.autoplay.running) {
                    swiper.autoplay.stop();
                }
                // Restore normal autoplay delay
                var normalDelay = params.autoplayDelay * MILLISECONDS_PER_SECOND;
                swiper.params.autoplay.delay = normalDelay;
                if (swiper.autoplay) {
                    swiper.autoplay.delay = normalDelay;
                }
                // Start normal autoplay
                swiper.autoplay.start();
                console.log('▶️  Normal autoplay restored (delay: ' + params.autoplayDelay + 's' + logPrefix + ')');
            } else if (swiper.autoplay && swiper.autoplay.running) {
                // Stop autoplay if it was only enabled in fullscreen mode
                swiper.autoplay.stop();
                console.log('⏸️  Autoplay stopped (not enabled in normal mode' + logPrefix + ')');
            }
        }
    }

    // Helper: Navigate based on click position
    function navigateByPosition(swiper, clickX, containerWidth) {
        if (clickX < containerWidth / 2) {
            swiper.slidePrev();
        } else {
            swiper.slideNext();
        }
    }

    // Helper: Pause autoplay on user interaction
    function pauseAutoplayOnInteraction(swiper, params) {
        // Only pause if autoplay is currently running
        if (swiper.autoplay && swiper.autoplay.running) {
            swiper.autoplay.stop();
            params.autoplayPausedByInteraction = true;
			jzsaDebug('⏸️  Autoplay paused by user interaction');

            // Clear any existing inactivity timer
            if (params.inactivityTimer) {
                clearTimeout(params.inactivityTimer);
            }

            // Set inactivity timer to resume autoplay after configured timeout
            var timeoutMs = (params.autoplayInactivityTimeout || 30) * 1000;
            params.inactivityTimer = setTimeout(function() {
				if (params.autoplayPausedByInteraction && swiper.autoplay && !swiper.autoplay.running) {
					jzsaDebug('▶️  Resuming autoplay after ' + (params.autoplayInactivityTimeout || 30) + ' seconds of inactivity');
                    params.autoplayPausedByInteraction = false;
                    swiper.autoplay.start();
                }
            }, timeoutMs);
        }
    }


    // Helper: Setup fullscreen button
    function setupFullscreenButton(swiper, $container, params) {
        var $fullscreenBtn = $container.find('.swiper-button-fullscreen');
		$fullscreenBtn.on('click', function(e) {
			e.stopPropagation();

			// Check if we're entering or exiting fullscreen
			var isCurrentlyFullscreen = isFullscreen();

			if (!isCurrentlyFullscreen) {
				// About to enter fullscreen - apply fullscreen autoplay settings immediately (Android workaround)
				jzsaDebug('🔍 Fullscreen button clicked - entering fullscreen');
                applyFullscreenAutoplaySettings(swiper, {
                    fullScreenAutoplay: params.fullScreenAutoplay,
                    fullScreenAutoplayDelay: params.fullScreenAutoplayDelay,
                    autoplayPausedByInteraction: params.autoplayPausedByInteraction
                });
            }

            toggleFullscreen($container[0], params.showHintsOnFullscreen);
        });
    }

    // Helper: Setup download button
    function setupDownloadButton(swiper, $container) {
        var $downloadBtn = $container.find('.swiper-button-download');
        if ($downloadBtn.length === 0) {
            return; // Download button not enabled
        }

        $downloadBtn.on('click', function(e) {
            e.stopPropagation();

            // Get current active slide
            var activeSlide = swiper.slides[swiper.activeIndex];
            if (!activeSlide) {
                return;
            }

            // Get the full resolution image URL from the slide
            var $img = $(activeSlide).find('img');
            var imageUrl = $img.attr('src');

            if (!imageUrl) {
                return;
            }

            // Google Photos doesn't allow direct downloads due to CORS
            // We need to download via WordPress AJAX proxy
            var filename = 'photo-' + (swiper.activeIndex + 1) + '.jpg';

            // Show loading state
            var originalTitle = $downloadBtn.attr('title');
            $downloadBtn.attr('title', 'Downloading...');
            $downloadBtn.css('opacity', '0.5');

            // Use WordPress AJAX to proxy the download
            $.ajax({
                url: jzsaAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jzsa_download_image',
                    nonce: jzsaAjax.nonce,
                    image_url: imageUrl,
                    filename: filename
                },
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(blob) {
                    // Create download link from blob
                    var url = window.URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);

                    // Restore button state
                    $downloadBtn.attr('title', originalTitle);
                    $downloadBtn.css('opacity', '1');
                },
                error: function(xhr, status, error) {
                    console.error('Download failed:', error);

                    // Fallback: Try direct link with download attribute
                    var link = document.createElement('a');
                    link.href = imageUrl;
                    link.download = filename;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Restore button state
                    $downloadBtn.attr('title', originalTitle);
                    $downloadBtn.css('opacity', '1');
                }
            });
        });
    }

    // Helper: Setup autoplay progress bar
    function setupAutoplayProgress(swiper, $container) {
        var $progressBar = $container.find('.swiper-autoplay-progress-bar');
        var $progressContainer = $container.find('.swiper-autoplay-progress');
        var progressInterval = null;

        // Hide progress bar initially if autoplay is not running
        if (!swiper.autoplay || !swiper.autoplay.running) {
            $progressContainer.css('display', 'none');
        }

        function startProgress() {
            // Clear any existing interval
            if (progressInterval) {
                clearInterval(progressInterval);
            }

            // Get current autoplay delay and transition speed
            var delay = swiper.params.autoplay.delay;
            var speed = swiper.params.speed || 600; // Swiper's transition speed

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

            // Force reflow to apply the reset
            $progressBar[0].offsetHeight;

            // Adjust animation duration so slide transition starts just as progress completes
            // Make progress bar last almost the entire delay, ending right when slide transitions
            var progressDuration = delay + 500;
            if (progressDuration < 0) progressDuration = delay;

            // Start animation
            $progressBar.css({
                'transform': 'scaleX(0)',
                'transition': 'transform ' + progressDuration + 'ms linear'
            });
        }

        function stopProgress() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }

            // Reset to full width and hide
            $progressBar.css({
                'transform': 'scaleX(1)',
                'transition': 'none'
            });
            $progressContainer.css('display', 'none');
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
    function setupPlayPauseButton(swiper, $container, progressBar) {
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
        // When entering fullscreen from carousel-to-player, ensure we focus the
        // exact slide the user clicked in the preview (multi-slide) view.
        function focusClickedSlide(e) {
            if (params.mode !== 'carousel-to-player') {
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

        // FULLSCREEN SWITCH HANDLERS (works both in and out of fullscreen)
		if (params.fullScreenSwitch === 'single-click') {
			$container.on('click', function(e) {
				if (!shouldIgnoreClick(e.target)) {
					// Only toggle fullscreen if navigation is NOT also single-click in fullscreen
					if (params.fullScreenNavigation !== 'single-click' || !isFullscreen()) {
						e.preventDefault();

						// If entering fullscreen, apply autoplay settings immediately (Android workaround)
						if (!isFullscreen()) {
							focusClickedSlide(e);
							jzsaDebug('🔍 Single-click entering fullscreen');
                            applyFullscreenAutoplaySettings(swiper, {
                                fullScreenAutoplay: params.fullScreenAutoplay,
                                fullScreenAutoplayDelay: params.fullScreenAutoplayDelay,
                                autoplayPausedByInteraction: params.autoplayPausedByInteraction
                            });
                        }

                        toggleFullscreen($container[0], params.showHintsOnFullscreen);
                    }
                }
            });
		} else if (params.fullScreenSwitch === 'double-click') {
			// Desktop double-click
			$container.on('dblclick', function(e) {
				if (!shouldIgnoreClick(e.target)) {
					// Only toggle fullscreen if navigation is NOT also double-click in fullscreen
					if (params.fullScreenNavigation !== 'double-click' || !isFullscreen()) {
						e.preventDefault();

						// If entering fullscreen, apply autoplay settings immediately (Android workaround)
						if (!isFullscreen()) {
							focusClickedSlide(e);
							jzsaDebug('🔍 Double-click entering fullscreen');
                            applyFullscreenAutoplaySettings(swiper, {
                                fullScreenAutoplay: params.fullScreenAutoplay,
                                fullScreenAutoplayDelay: params.fullScreenAutoplayDelay,
                                autoplayPausedByInteraction: params.autoplayPausedByInteraction
                            });
                        }

                        toggleFullscreen($container[0], params.showHintsOnFullscreen);
                    }
                }
            });

			// Mobile double-tap
			$container.on('touchend', function(e) {
				if (!shouldIgnoreClick(e.target)) {
					handleDoubleTap(e, function(evt) {
						if (params.fullScreenNavigation !== 'double-click' || !isFullscreen()) {
							// If entering fullscreen, apply autoplay settings immediately (Android workaround)
							if (!isFullscreen()) {
								focusClickedSlide(evt);
								jzsaDebug('🔍 Double-tap entering fullscreen');
                                applyFullscreenAutoplaySettings(swiper, {
                                    fullScreenAutoplay: params.fullScreenAutoplay,
                                    fullScreenAutoplayDelay: params.fullScreenAutoplayDelay,
                                    autoplayPausedByInteraction: params.autoplayPausedByInteraction
                                });
                            }

                            toggleFullscreen($container[0], params.showHintsOnFullscreen);
                        }
                    });
                }
            });
        }
    }

    // Helper: Setup navigation handlers
    function setupNavigationHandlers(swiper, $container, fullScreenNavigation, fullscreenChangeParams) {
        // NAVIGATION HANDLERS (only works in fullscreen)
        if (fullScreenNavigation === 'single-click') {
            $container.on('click', function(e) {
                if (!shouldIgnoreClick(e.target) && isFullscreen()) {
                    e.preventDefault();
                    pauseAutoplayOnInteraction(swiper, fullscreenChangeParams);
                    var containerWidth = $container.width();
                    var clickX = e.pageX - $container.offset().left;
                    navigateByPosition(swiper, clickX, containerWidth);
                }
            });
        } else if (fullScreenNavigation === 'double-click') {
            // Desktop double-click
            $container.on('dblclick', function(e) {
                if (!shouldIgnoreClick(e.target) && isFullscreen()) {
                    e.preventDefault();
                    pauseAutoplayOnInteraction(swiper, fullscreenChangeParams);
                    var containerWidth = $container.width();
                    var clickX = e.pageX - $container.offset().left;
                    navigateByPosition(swiper, clickX, containerWidth);
                }
            });

            // Mobile double-tap
            $container.on('touchend', function(e) {
                if (!shouldIgnoreClick(e.target)) {
                    handleDoubleTap(e, function(evt) {
                        if (isFullscreen()) {
                            pauseAutoplayOnInteraction(swiper, fullscreenChangeParams);
                            var containerWidth = $container.width();
                            var touch = evt.changedTouches ? evt.changedTouches[0] : evt;
                            var clickX = touch.pageX - $container.offset().left;
                            navigateByPosition(swiper, clickX, containerWidth);
                        }
                    });
                }
            });
        }
    }

    // Helper: Setup progressive image loading
    function setupProgressiveImageLoading(swiper) {
        // Progressive image loading - load full-res image when slide becomes active
        function loadFullImage($img) {
            var fullSrc = $img.attr('data-full-src');
            if (fullSrc && !$img.data('full-loaded')) {
                var tempImg = new Image();
                tempImg.onload = function() {
                    $img.attr('src', fullSrc);
                    $img.data('full-loaded', true);
                    $img.addClass('jzsa-full-loaded');
                    $img.removeClass('jzsa-image-error');
                };
                tempImg.onerror = function() {
                    // Mark image as failed to load
                    $img.addClass('jzsa-image-error');
                    $img.data('full-loaded', 'error');
                    console.warn('Failed to load image:', fullSrc);
                };
                tempImg.src = fullSrc;
            }
        }

        // Load full image for initial slide
        var $initialImg = $(swiper.slides[swiper.activeIndex]).find('.jzsa-progressive-image');
        loadFullImage($initialImg);

        // Load full images for adjacent slides (preload next/prev)
        swiper.on('slideChange', function() {
            var currentIndex = swiper.activeIndex;

            // Load current slide
            var $currentImg = $(swiper.slides[currentIndex]).find('.jzsa-progressive-image');
            loadFullImage($currentImg);

            // Preload next slide
            if (swiper.slides[currentIndex + 1]) {
                var $nextImg = $(swiper.slides[currentIndex + 1]).find('.jzsa-progressive-image');
                loadFullImage($nextImg);
            }

            // Preload previous slide
            if (swiper.slides[currentIndex - 1]) {
                var $prevImg = $(swiper.slides[currentIndex - 1]).find('.jzsa-progressive-image');
                loadFullImage($prevImg);
            }
        });
    }

    // Helper: Build Swiper configuration object
    function buildSwiperConfig(params) {
        var config = {
            // Initial slide
            initialSlide: params.initialSlide,
            // Navigation
            navigation: {
                nextEl: '#' + params.galleryId + ' .swiper-button-next',
                prevEl: '#' + params.galleryId + ' .swiper-button-prev',
            },

        // Pagination always lives at the bottom; content depends on title/counter settings.
        pagination: (function() {
            var base = {
                el: '#' + params.galleryId + ' .swiper-pagination'
            };

            base.type = 'custom';
            base.renderCustom = function(swiper, current, total) {
                var hasTitle = !!(params.showTitle && params.albumTitle);
                var parts = [];

                if (hasTitle) {
                    parts.push(params.albumTitle);
                }

                if (params.showCounter) {
                    // In carousel modes (including carousel-to-player preview), show
                    // all currently visible photo indices, e.g. "4-6 / 41".
                    if (params.mode === 'carousel' || params.mode === 'carousel-to-player') {
                        var slidesPerView = swiper.params.slidesPerView || 1;
                        var realIndex = (typeof swiper.realIndex === 'number') ? swiper.realIndex : (current - 1);
                        var visible = [];
                        var maxVisible = Math.min(slidesPerView, total);

                        for (var i = 0; i < maxVisible; i++) {
                            var idx = realIndex + i;
                            if (idx >= total) {
                                if (swiper.params.loop) {
                                    idx = idx % total;
                                } else {
                                    break;
                                }
                            }
                            visible.push(idx + 1); // 1-based for humans
                        }

                        if (visible.length === 0) {
                            // Fallback: show current index if something went wrong
                            parts.push(current + ' / ' + total);
                        } else if (visible.length === 1) {
                            parts.push(visible[0] + ' / ' + total);
                        } else {
                            parts.push(visible[0] + '-' + visible[visible.length - 1] + ' / ' + total);
                        }
                    } else {
                        // Player mode (single slide): keep classic "current / total".
                        parts.push(current + ' / ' + total);
                    }
                }

                return parts.join(':   ');
            };

            return base;
        })(),

            // Zoom - enables pinch to zoom and double-click to zoom
            zoom: {
                maxRatio: params.ZOOM_MAX_RATIO,
                minRatio: params.ZOOM_MIN_RATIO,
            },

            // Autoplay - enable if either normal mode or fullscreen mode has autoplay enabled
            autoplay: (params.autoplay || params.fullScreenAutoplay) ? {
                delay: params.autoplayDelay * MILLISECONDS_PER_SECOND,
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
            grabCursor: true,

            // Keyboard control
            keyboard: {
                enabled: true,
            },

            // Mouse wheel
            mousewheel: false,
        };

        // Mode-specific configuration
        if (params.mode === 'carousel' || params.mode === 'carousel-to-player') {
            // Carousel mode (and carousel-to-player): Show multiple slides at once
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
            // Player mode: Single photo viewer with zoom
            config.slidesPerView = 1;
            config.spaceBetween = 0;
            config.centeredSlides = true;

            // On iOS (especially older devices), use fade instead of slide to avoid
            // transient black frames during transform-based slide transitions.
            if (isIosDevice()) {
                config.effect = 'fade';
                config.fadeEffect = { crossFade: true };
            } else {
                config.effect = 'slide';
            }

            // Disable zoom if double-click is used for fullscreen switch or navigation
            // (to avoid conflicts with Swiper's default double-click zoom)
            if (params.fullScreenSwitch === 'double-click' || params.fullScreenNavigation === 'double-click') {
                config.zoom = false;
            } else {
                // Enable zoom for single photo viewing (pinch still works)
                config.zoom = {
                    maxRatio: params.ZOOM_MAX_RATIO,
                    minRatio: params.ZOOM_MIN_RATIO,
                };
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
        var DEFAULT_AUTOPLAY_DELAY_FALLBACK = 5; // seconds - fallback autoplay delay if not specified

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
        var totalCount = parseInt($container.attr('data-total-count')) || allPhotos.length;

        // On older iOS/WebKit stacks, very large galleries (e.g. 300 photos) can
        // be unstable. Cap the number of photos there, but allow the full set
        // everywhere else.
        if (isOldIosWebkit() && allPhotos.length > OLD_IOS_MAX_PHOTOS) {
            allPhotos = allPhotos.slice(0, OLD_IOS_MAX_PHOTOS);
            console.log('[JZSA] Old iOS/WebKit detected – capping photos to', OLD_IOS_MAX_PHOTOS, 'out of', totalCount);
        }

        var config = {
            // Photo data
            allPhotos: allPhotos,
            totalCount: totalCount,

            // Autoplay settings
            autoplay: $container.attr('data-autoplay') === 'true',
            autoplayDelay: parseInt($container.attr('data-autoplay-delay')) || DEFAULT_AUTOPLAY_DELAY_FALLBACK,
            fullScreenAutoplay: $container.attr('data-full-screen-autoplay') === 'true',
            fullScreenAutoplayDelay: parseInt($container.attr('data-full-screen-autoplay-delay')) || 3,
            autoplayInactivityTimeout: parseInt($container.attr('data-autoplay-inactivity-timeout')) || 30,

            // Display settings
            loop: true, // Always loop
            fullScreenSwitch: $container.attr('data-full-screen-switch') || 'double-click',
            fullScreenNavigation: $container.attr('data-full-screen-navigation') || 'single-click',
            startAt: $container.attr('data-start-at') || 'random',
            showTitle: $container.attr('data-show-title') === 'true',
            showCounter: $container.attr('data-show-counter') === 'true',
            albumTitle: $container.attr('data-album-title') || '',
            initialSlide: 0
        };

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
        var autoplay = config.autoplay;
        var autoplayDelay = config.autoplayDelay;
        var fullScreenAutoplay = config.fullScreenAutoplay;
        var fullScreenAutoplayDelay = config.fullScreenAutoplayDelay;
        var autoplayInactivityTimeout = config.autoplayInactivityTimeout;
        var loop = config.loop;
        var fullScreenSwitch = config.fullScreenSwitch;
        var fullScreenNavigation = config.fullScreenNavigation;
        var startAt = config.startAt;
        var showTitle = config.showTitle;
        var showCounter = config.showCounter;
        var albumTitle = config.albumTitle;
        var initialSlide = config.initialSlide;

        console.log('📸 Initializing Swiper for gallery:', galleryId);
        console.log('  - Mode:', mode);
        console.log('  - Total photos:', totalCount);
        console.log('  - Initial photos loaded:', allPhotos.length);
        console.log('  - startAt setting:', startAt, '=> initial slide index (0-based):', initialSlide, '/', totalCount);

        // Debug: Log configuration values
        console.log('🔍 Configuration debug:');
        console.log('  - data-full-screen-autoplay-delay attribute:', $container.attr('data-full-screen-autoplay-delay'));
        console.log('  - fullScreenAutoplayDelay parsed:', fullScreenAutoplayDelay);
        console.log('  - fullScreenAutoplayDelay in ms:', fullScreenAutoplayDelay * MILLISECONDS_PER_SECOND);

        // Build and insert slides HTML
        var slidesHtml = buildSlidesHtml(allPhotos);
        $container.find('.swiper-wrapper').html(slidesHtml);

        // --------------------------------------------------------------------
        // Loading overlay: show a subtle loader until the first image is ready
        // --------------------------------------------------------------------

        if ($container.find('.jzsa-loader').length === 0) {
            var loaderHtml = '' +
                '<div class="jzsa-loader">' +
                    '<div class="jzsa-loader-inner">' +
                        '<div class="jzsa-loader-spinner"></div>' +
                        '<div class="jzsa-loader-text">Loading photos...</div>' +
                    '</div>' +
                '</div>';
            $container.append(loaderHtml);
        }

        var jzsaHasMarkedLoaded = false;
        function markGalleryLoaded() {
            if (jzsaHasMarkedLoaded) return;
            jzsaHasMarkedLoaded = true;
            $container.addClass('jzsa-loaded');
        }

        // Hide loader when the initial preview image finishes loading, with a
        // small fallback timeout so we never leave the overlay up forever.
        var $initialPreviewImg = $container.find('.jzsa-progressive-image').first();
        if ($initialPreviewImg.length) {
            var imgEl = $initialPreviewImg[0];
            if (imgEl.complete && imgEl.naturalWidth > 0) {
                markGalleryLoaded();
            } else {
                $initialPreviewImg.one('load', function() {
                    markGalleryLoaded();
                });
                $initialPreviewImg.one('error', function() {
                    setTimeout(markGalleryLoaded, 800);
                });
            }
        } else {
            // If there is no image, avoid keeping the loader forever.
            setTimeout(markGalleryLoaded, 800);
        }

        // Swiper configuration - gather all parameters
        var swiperConfig = buildSwiperConfig({
            mode: mode,
            galleryId: galleryId,
            initialSlide: initialSlide,
            showTitle: showTitle,
            showCounter: showCounter,
            albumTitle: albumTitle,
            ZOOM_MAX_RATIO: ZOOM_MAX_RATIO,
            ZOOM_MIN_RATIO: ZOOM_MIN_RATIO,
            autoplay: autoplay,
            fullScreenAutoplay: fullScreenAutoplay,
            autoplayDelay: autoplayDelay,
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
            fullScreenSwitch: fullScreenSwitch,
            fullScreenNavigation: fullScreenNavigation
        });

        // Initialize Swiper
        var swiper = new Swiper('#' + galleryId, swiperConfig);
        swipers[galleryId] = swiper;

        // If normal mode autoplay is disabled but fullscreen autoplay is enabled, stop autoplay initially
        if (!autoplay && fullScreenAutoplay && swiper.autoplay && swiper.autoplay.running) {
            swiper.autoplay.stop();
            console.log('⏸️  Autoplay stopped (only enabled in fullscreen mode)');
        }

        // Create hint system for click/double-click gestures (only if at least one is enabled)
        var showHintsOnFullscreen = null;
        if (fullScreenSwitch !== 'button-only' || fullScreenNavigation !== 'buttons-only') {
            showHintsOnFullscreen = createHintSystem(galleryId, fullScreenSwitch, fullScreenNavigation);
        }

        var autoplayPausedByInteraction = false;

        // ------------------------------------------------------------------------
        // Fullscreen change event listeners (all browser prefixes)
        // ------------------------------------------------------------------------

        // Create params object for handleFullscreenChange
        var fullscreenChangeParams = {
            galleryId: galleryId,
            mode: mode,
            fullScreenAutoplay: fullScreenAutoplay,
            fullScreenAutoplayDelay: fullScreenAutoplayDelay,
            autoplay: autoplay,
            autoplayDelay: autoplayDelay,
            autoplayPausedByInteraction: autoplayPausedByInteraction,
            autoplayInactivityTimeout: autoplayInactivityTimeout,
            browserPrefix: null,
            // For carousel-to-player: remember original layout so we can
            // temporarily switch to a single-slide view in fullscreen.
            originalSlidesPerView: null,
            originalBreakpoints: null,
            originalCenteredSlides: null
        };

        // Fullscreen change event listeners - Standard API (Chrome, Firefox, Edge)
        document.addEventListener('fullscreenchange', function() {
            fullscreenChangeParams.browserPrefix = null;
            fullscreenChangeParams.autoplayPausedByInteraction = autoplayPausedByInteraction;
            handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
        });

        // Webkit prefix (Safari, older Chrome/Android)
        document.addEventListener('webkitfullscreenchange', function() {
            fullscreenChangeParams.browserPrefix = 'webkit';
            fullscreenChangeParams.autoplayPausedByInteraction = autoplayPausedByInteraction;
            handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
        });

        // Mozilla prefix (Firefox)
        document.addEventListener('mozfullscreenchange', function() {
            fullscreenChangeParams.browserPrefix = 'moz';
            fullscreenChangeParams.autoplayPausedByInteraction = autoplayPausedByInteraction;
            handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
        });

        // MS prefix (old IE/Edge)
        document.addEventListener('MSFullscreenChange', function() {
            fullscreenChangeParams.browserPrefix = 'ms';
            fullscreenChangeParams.autoplayPausedByInteraction = autoplayPausedByInteraction;
            handleFullscreenChange($container[0], swiper, fullscreenChangeParams);
        });

        // ------------------------------------------------------------------------
        // Fullscreen switch handlers (click/double-click to enter/exit fullscreen)
        // ------------------------------------------------------------------------

        var fullscreenParams = {
            mode: mode,
            fullScreenSwitch: fullScreenSwitch,
            fullScreenNavigation: fullScreenNavigation,
            fullScreenAutoplay: fullScreenAutoplay,
            fullScreenAutoplayDelay: fullScreenAutoplayDelay,
            autoplayPausedByInteraction: autoplayPausedByInteraction,
            showHintsOnFullscreen: showHintsOnFullscreen
        };

        setupFullscreenButton(swiper, $container, fullscreenParams);
        setupDownloadButton(swiper, $container);
        var progressBar = setupAutoplayProgress(swiper, $container);
        var togglePlayPause = setupPlayPauseButton(swiper, $container, progressBar);
        setupFullscreenSwitchHandlers(swiper, $container, fullscreenParams);

        // ------------------------------------------------------------------------
        // Image error handling - Add error handlers to all images
        // ------------------------------------------------------------------------

        $container.find('.jzsa-progressive-image').each(function() {
            var $img = $(this);
            // Handle errors on the preview image
            this.onerror = function() {
                $img.addClass('jzsa-image-error');
                console.warn('Failed to load preview image:', $img.attr('src'));
            };
        });

        // ------------------------------------------------------------------------
        // Navigation handlers (click/double-click to navigate in fullscreen)
        // ------------------------------------------------------------------------

        setupNavigationHandlers(swiper, $container, fullScreenNavigation, fullscreenChangeParams);

        // ------------------------------------------------------------------------
        // Carousel-to-player mode
        // ------------------------------------------------------------------------

        // For now, carousel-to-player uses the same Swiper configuration as
        // carousel. Fullscreen still works via the standard fullscreen button
        // and behaves like the regular gallery; no extra lightbox logic.
        if (mode === 'carousel-to-player') {
            jzsaDebug('Carousel-to-player mode: using standard carousel behaviour for gallery', galleryId);
        }

        // ------------------------------------------------------------------------
        // Pause autoplay when user clicks navigation buttons
        // ------------------------------------------------------------------------

        $container.find('.swiper-button-next, .swiper-button-prev').on('click', function() {
            pauseAutoplayOnInteraction(swiper, fullscreenChangeParams);
        });

        // ------------------------------------------------------------------------
        // Pause autoplay on swipe/touch gestures
        // ------------------------------------------------------------------------

        swiper.on('touchStart', function() {
            pauseAutoplayOnInteraction(swiper, fullscreenChangeParams);
        });

        // ------------------------------------------------------------------------
        // Keyboard handlers
        // ------------------------------------------------------------------------

        $(document).on('keydown', function(e) {
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

        setupProgressiveImageLoading(swiper);

        console.log('✅ Swiper initialized:', galleryId);
        console.log('  - Normal mode autoplay:', autoplay ? 'Enabled (delay: ' + autoplayDelay + 's)' : 'Disabled');
        console.log('  - Fullscreen mode autoplay:', fullScreenAutoplay ? 'Enabled (delay: ' + fullScreenAutoplayDelay + 's)' : 'Disabled');
        console.log('  - Loop: Always enabled');
        console.log('  - Zoom: Double-click or pinch to zoom');
        console.log('  - Fullscreen: ' + (fullScreenSwitch === 'button-only' ? 'Button only' : fullScreenSwitch === 'double-click' ? 'Double-click or button' : 'Click or button'));
        console.log('  - Progressive loading: Preview → Full resolution');

        return swiper;
    }

    // ============================================================================
    // GALLERY INITIALIZATION
    // ============================================================================

    function initializeAllGalleries() {
        $('.jzsa-album').each(function(index) {
            var $gallery = $(this);

            // Generate unique ID if not present
            if (!$gallery.attr('id')) {
                $gallery.attr('id', 'jzsa-album-' + (index + 1));
            }

            // Get mode
            var mode = $gallery.attr('data-mode') || 'player';

            // Initialize with the mode
            initializeSwiper(this, mode);
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Wait for Swiper library to load
        if (typeof Swiper !== 'undefined') {
            console.log('✅ Swiper library found, initializing galleries...');
            initializeAllGalleries();
        } else {
            console.error('❌ Swiper library not loaded!');
        }
    });

    // Export for global access
    window.SharedGooglePhotos = {
        swipers: swipers,
        initialize: initializeSwiper,
        reinitialize: initializeAllGalleries
    };

})(jQuery);
