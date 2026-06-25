/**
 * Admin Settings Page JavaScript
 *
 * @package JZSA_Shared_Albums
 */

/**
 * Copy text to clipboard and provide visual feedback
 *
 * @param {HTMLElement} button - The button element clicked
 * @param {string} text - The text to copy to clipboard
 */
/**
 * Flash a button green with a confirmation label, then restore.
 */
function jzsaFlashButton( button, label, duration ) {
	var ms = duration || 1200;
	var origText = button.textContent;
	button.textContent = label;
	button.style.background = '#46b450';
	setTimeout( function () {
		button.textContent = origText;
		button.style.background = '';
	}, ms );
}

function jzsaCopyToClipboard( button, text ) {
	var textarea = document.createElement( 'textarea' );
	textarea.value = text;
	textarea.style.position = 'fixed';
	textarea.style.opacity = '0';
	document.body.appendChild( textarea );
	textarea.select();
	document.execCommand( 'copy' );
	document.body.removeChild( textarea );
	jzsaFlashButton( button, 'Copied!' );
}

function jzsaGetPreviewAjaxConfig() {
	if ( typeof jzsaAjax !== 'undefined' && jzsaAjax && jzsaAjax.ajaxUrl && jzsaAjax.previewNonce ) {
		return jzsaAjax;
	}

	if ( typeof jzsaAdminAjax !== 'undefined' && jzsaAdminAjax && jzsaAdminAjax.ajaxUrl && jzsaAdminAjax.previewNonce ) {
		return jzsaAdminAjax;
	}

	return null;
}

var jzsaLazyPreviewObserver = null;
var jzsaLazyPreviewBackgroundStarted = false;
var jzsaLazyPreviewShortcodes = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
var JZSA_MASKED_ALBUM_LINK = 'hidden-album-link';

function jzsaSetLazyPreviewShortcode( lazyPreviewEl, shortcode ) {
	if ( ! lazyPreviewEl ) {
		return;
	}
	if ( jzsaLazyPreviewShortcodes ) {
		jzsaLazyPreviewShortcodes.set( lazyPreviewEl, shortcode );
		return;
	}
	lazyPreviewEl.jzsaInitialShortcode = shortcode;
}

function jzsaGetLazyPreviewShortcode( lazyPreviewEl ) {
	if ( ! lazyPreviewEl ) {
		return '';
	}
	if ( jzsaLazyPreviewShortcodes && jzsaLazyPreviewShortcodes.has( lazyPreviewEl ) ) {
		return jzsaLazyPreviewShortcodes.get( lazyPreviewEl ) || '';
	}
	return lazyPreviewEl.jzsaInitialShortcode ||
		lazyPreviewEl.getAttribute( 'data-initial-shortcode' ) ||
		'';
}

function jzsaClearLazyPreviewShortcode( lazyPreviewEl ) {
	if ( jzsaLazyPreviewShortcodes && lazyPreviewEl ) {
		jzsaLazyPreviewShortcodes.delete( lazyPreviewEl );
	}
	if ( lazyPreviewEl ) {
		delete lazyPreviewEl.jzsaInitialShortcode;
	}
}

function jzsaInitializeInjectedPreview( mountEl ) {
	if ( ! mountEl || ! window.SharedGooglePhotos ) {
		return;
	}

	var album = mountEl.querySelector( '.jzsa-album' );
	if ( ! album ) {
		return;
	}

	// The AJAX render runs in a separate PHP request so wp_unique_id()
	// resets its counter, producing IDs that already exist on the page.
	// Reassign unique IDs before Swiper init so selectors resolve to the
	// newly injected elements, not previously rendered samples.
	var uniqueId = 'jzsa-preview-' + Date.now() + '-' + Math.floor( Math.random() * 1000 );
	album.id = uniqueId;

	var mosaicEl = mountEl.querySelector( '.jzsa-mosaic' );
	if ( mosaicEl ) {
		mosaicEl.id = uniqueId + '-mosaic';
	}

	var mode = album.getAttribute( 'data-mode' ) || 'slider';
	if ( mode === 'gallery' && typeof window.SharedGooglePhotos.initializeGallery === 'function' ) {
		window.SharedGooglePhotos.initializeGallery( album );
	} else if ( typeof window.SharedGooglePhotos.initialize === 'function' ) {
		window.SharedGooglePhotos.initialize( album, mode );
	}
}

/**
 * Shared Apply handler: sends a shortcode from a code element to the AJAX
 * preview endpoint and replaces the preview container HTML.
 *
 * @param {HTMLElement} codeEl           The <code> element with the shortcode text.
 * @param {HTMLElement} applyBtn         The Apply button (disabled during request).
 * @param {HTMLElement} previewContainer The container to update with the rendered HTML.
 */
/**
 * Highlight placeholders like {date} in red inside an editable code element.
 * Preserves cursor position across innerHTML replacements.
 */
function jzsaHighlightPlaceholders( codeEl ) {
	var text = codeEl.textContent || '';
	var escaped = text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	var highlighted = escaped.replace( /(\{[a-z_-]+\})/g, '<span class="jzsa-code-placeholder">$1</span>' );
	if ( highlighted === escaped ) {
		return; // No placeholders, skip innerHTML update.
	}

	// Save cursor offset.
	var sel = window.getSelection();
	var offset = 0;
	if ( sel && sel.rangeCount ) {
		var range = sel.getRangeAt( 0 );
		var pre = range.cloneRange();
		pre.selectNodeContents( codeEl );
		pre.setEnd( range.endContainer, range.endOffset );
		offset = pre.toString().length;
	}

	codeEl.innerHTML = highlighted;

	// Restore cursor.
	try {
		var walker = document.createTreeWalker( codeEl, NodeFilter.SHOW_TEXT, null, false );
		var charCount = 0;
		var node;
		while ( ( node = walker.nextNode() ) ) {
			var len = node.textContent.length;
			if ( charCount + len >= offset ) {
				var r = document.createRange();
				r.setStart( node, offset - charCount );
				r.collapse( true );
				sel.removeAllRanges();
				sel.addRange( r );
				break;
			}
			charCount += len;
		}
	} catch ( e ) { /* ignore */ }
}

function jzsaApplyPreview( codeEl, triggerBtn, previewContainer, flashLabel, shortcodeOverride ) {
	var shortcode = shortcodeOverride || ( codeEl.textContent || '' ).trim();
	// Community browse cards display link="hidden-album-link" to mask the real URL.
	// Substitute the real URL back before sending to the preview AJAX endpoint.
	if ( ! shortcodeOverride && /\blink\s*=\s*["']hidden-album-link["']/i.test( shortcode ) && codeEl && codeEl.dataset && codeEl.dataset.revertShortcode ) {
		var urlMatch = codeEl.dataset.revertShortcode.match( /\blink\s*=\s*["']([^"']+)["']/i );
		if ( urlMatch ) {
			shortcode = shortcode.replace( /(\blink\s*=\s*["'])hidden-album-link(["'])/i, '$1' + urlMatch[ 1 ] + '$2' );
		}
	}
	var ajaxConfig = jzsaGetPreviewAjaxConfig();
	if ( ! shortcode ) {
		return;
	}
	if ( ! ajaxConfig || ! ajaxConfig.ajaxUrl || ! ajaxConfig.previewNonce ) {
		previewContainer.innerHTML = '<div class="jzsa-playground-error">Preview configuration missing.</div>';
		return;
	}

	var savedLabel = triggerBtn ? triggerBtn.textContent : '';
	if ( triggerBtn ) {
		triggerBtn.disabled = true;
		triggerBtn.textContent = 'Applying\u2026';
	}
	previewContainer.style.opacity = '0.5';

	var params = new URLSearchParams();
	params.append( 'action', 'jzsa_shortcode_preview' );
	params.append( 'nonce', ajaxConfig.previewNonce );
	params.append( 'shortcode', shortcode );

	window.fetch( ajaxConfig.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: params.toString(),
	} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( data ) {
			if ( triggerBtn ) {
				triggerBtn.disabled = false;
				triggerBtn.textContent = savedLabel;
			}
			previewContainer.style.opacity = '';

			if ( ! data || ! data.success || ! data.data || ! data.data.html ) {
				var msg = ( data && data.data && typeof data.data === 'string' ) ? data.data : 'Preview failed.';
				previewContainer.innerHTML = '<div class="jzsa-playground-error">' + msg + '</div>';
				return;
			}

			previewContainer.innerHTML = data.data.html;
			jzsaInitializeInjectedPreview( previewContainer );

			if ( triggerBtn ) {
				jzsaFlashButton( triggerBtn, flashLabel || 'Applied!' );
			}
		} )
		.catch( function () {
			if ( triggerBtn ) {
				triggerBtn.disabled = false;
				triggerBtn.textContent = savedLabel;
			}
			previewContainer.style.opacity = '';
			previewContainer.innerHTML = '<div class="jzsa-playground-error">Request failed.</div>';
		} );
}

function jzsaLoadLazyPreview( lazyPreviewEl ) {
	if ( ! lazyPreviewEl || ! lazyPreviewEl.isConnected ) {
		return Promise.resolve();
	}

	var state = lazyPreviewEl.getAttribute( 'data-lazy-state' );
	if ( 'loading' === state || 'loaded' === state ) {
		return Promise.resolve();
	}

	var shortcode = jzsaGetLazyPreviewShortcode( lazyPreviewEl );
	var ajaxConfig = jzsaGetPreviewAjaxConfig();
	if ( ! shortcode || ! ajaxConfig || ! ajaxConfig.ajaxUrl || ! ajaxConfig.previewNonce ) {
		return Promise.resolve();
	}

	lazyPreviewEl.setAttribute( 'data-lazy-state', 'loading' );
	lazyPreviewEl.classList.add( 'jzsa-lazy-preview--loading' );
	lazyPreviewEl.style.opacity = '0.5';

	var params = new URLSearchParams();
	params.append( 'action', 'jzsa_shortcode_preview' );
	params.append( 'nonce', ajaxConfig.previewNonce );
	params.append( 'shortcode', shortcode );

	return window.fetch( ajaxConfig.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: params.toString(),
	} )
		.then( function ( response ) { return response.json(); } )
		.then( function ( data ) {
			if ( ! lazyPreviewEl.isConnected ) {
				return;
			}

			lazyPreviewEl.style.opacity = '';

			if ( ! data || ! data.success || ! data.data || ! data.data.html ) {
				var msg = ( data && data.data && typeof data.data === 'string' ) ? data.data : 'Preview failed.';
				lazyPreviewEl.setAttribute( 'data-lazy-state', 'failed' );
				lazyPreviewEl.classList.remove( 'jzsa-lazy-preview--loading' );
				lazyPreviewEl.classList.add( 'jzsa-lazy-preview--error' );
				lazyPreviewEl.innerHTML = '<div class="jzsa-playground-error">' + msg + '</div>';
				return;
			}

			jzsaClearLazyPreviewShortcode( lazyPreviewEl );
			lazyPreviewEl.removeAttribute( 'data-initial-shortcode' );
			lazyPreviewEl.setAttribute( 'data-lazy-state', 'loaded' );
			lazyPreviewEl.classList.remove( 'jzsa-lazy-preview', 'jzsa-lazy-preview--loading', 'jzsa-lazy-preview--error' );
			lazyPreviewEl.innerHTML = data.data.html;
			jzsaInitializeInjectedPreview( lazyPreviewEl );
		} )
		.catch( function () {
			if ( ! lazyPreviewEl.isConnected ) {
				return;
			}

			lazyPreviewEl.style.opacity = '';
			lazyPreviewEl.setAttribute( 'data-lazy-state', 'failed' );
			lazyPreviewEl.classList.remove( 'jzsa-lazy-preview--loading' );
			lazyPreviewEl.classList.add( 'jzsa-lazy-preview--error' );
			lazyPreviewEl.innerHTML = '<div class="jzsa-playground-error">Request failed.</div>';
		} );
}

function jzsaStartBackgroundLazyPreviewQueue() {
	if ( jzsaLazyPreviewBackgroundStarted ) {
		return;
	}

	jzsaLazyPreviewBackgroundStarted = true;

	var initialDelayMs = 1200;
	var betweenLoadsMs = 700;

	var loadNext = function () {
		var pendingPreviews = document.querySelectorAll( '.jzsa-lazy-preview[data-lazy-state="pending"]' );
		var nextPreview = null;
		pendingPreviews.forEach( function ( preview ) {
			if ( ! nextPreview && jzsaGetLazyPreviewShortcode( preview ) ) {
				nextPreview = preview;
			}
		} );
		if ( ! nextPreview ) {
			return;
		}

		if ( jzsaLazyPreviewObserver ) {
			jzsaLazyPreviewObserver.unobserve( nextPreview );
		}

		jzsaLoadLazyPreview( nextPreview ).finally( function () {
			window.setTimeout( loadNext, betweenLoadsMs );
		} );
	};

	window.setTimeout( loadNext, initialDelayMs );
}

function jzsaScheduleBackgroundLazyPreviews() {
	var startQueue = function () {
		if ( 'requestIdleCallback' in window ) {
			window.requestIdleCallback( function () {
				jzsaStartBackgroundLazyPreviewQueue();
			}, { timeout: 4000 } );
			return;
		}

		jzsaStartBackgroundLazyPreviewQueue();
	};

	if ( document.readyState === 'complete' ) {
		startQueue();
		return;
	}

	window.addEventListener( 'load', startQueue, { once: true } );
}

function jzsaEnsureLazyPreviewObserver() {
	if ( jzsaLazyPreviewObserver ) {
		return true;
	}
	if ( ! ( 'IntersectionObserver' in window ) ) {
		return false;
	}
	jzsaLazyPreviewObserver = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			if ( ! entry.isIntersecting ) {
				return;
			}
			jzsaLazyPreviewObserver.unobserve( entry.target );
			jzsaLoadLazyPreview( entry.target );
		} );
	}, {
		rootMargin: '300px 0px',
		threshold: 0.01,
	} );
	return true;
}

/**
 * Register a single element for lazy-loading.
 * Safe to call for dynamically inserted preview containers.
 * Falls back to immediate load when IntersectionObserver is unavailable.
 *
 * @param {HTMLElement} el
 */
function jzsaObserveLazyPreview( el ) {
	if ( jzsaEnsureLazyPreviewObserver() ) {
		jzsaLazyPreviewObserver.observe( el );
		jzsaScheduleBackgroundLazyPreviews();
	} else {
		jzsaLoadLazyPreview( el );
	}
}

function jzsaSetupLazyPreviews() {
	var lazyPreviews = document.querySelectorAll( '.jzsa-lazy-preview[data-initial-shortcode]' );
	if ( ! lazyPreviews.length ) {
		return;
	}

	jzsaEnsureLazyPreviewObserver();

	if ( jzsaLazyPreviewObserver ) {
		lazyPreviews.forEach( function ( lazyPreview ) {
			jzsaLazyPreviewObserver.observe( lazyPreview );
		} );
		jzsaScheduleBackgroundLazyPreviews();
		return;
	}

	lazyPreviews.forEach( function ( lazyPreview ) {
		jzsaLoadLazyPreview( lazyPreview );
	} );
}

/**
 * Known [jzsa-album] shortcode parameter names.
 *
 * Source of truth: the "Parameter" column of the tables in
 * includes/admin/reference-parameters.php. Keep this list in sync whenever a
 * documented shortcode parameter is added or removed.
 */
var JZSA_KNOWN_PARAMS = [
	'album-cache-refresh', 'album-title-halo-effect', 'background-color',
	'controls-color', 'corner-radius', 'download-size-warning',
	'expanded-background-color', 'expanded-controls-color',
	'expanded-corner-radius', 'expanded-image-fit', 'expanded-info-bottom',
	'expanded-info-font-color', 'expanded-info-font-family',
	'expanded-info-font-size', 'expanded-info-top',
	'expanded-info-top-secondary', 'expanded-max-height', 'expanded-max-width',
	'expanded-mosaic', 'expanded-mosaic-background',
	'expanded-mosaic-corner-radius', 'expanded-mosaic-count',
	'expanded-mosaic-gap', 'expanded-mosaic-layout',
	'expanded-mosaic-opacity', 'expanded-mosaic-position',
	'expanded-show-download-button', 'expanded-show-link-button',
	'expanded-show-navigation', 'expanded-slideshow',
	'expanded-slideshow-autoresume', 'expanded-slideshow-delay',
	'expanded-source-height', 'expanded-source-width', 'expanded-toggle',
	'expanded-video-controls-autohide', 'expanded-video-controls-color',
	'fullscreen-background-color', 'fullscreen-controls-color',
	'fullscreen-corner-radius',
	'fullscreen-display-max-height', 'fullscreen-display-max-width',
	'fullscreen-image-fit', 'fullscreen-info-bottom', 'fullscreen-info-font-color',
	'fullscreen-info-font-family', 'fullscreen-info-font-size',
	'fullscreen-info-top', 'fullscreen-info-top-secondary', 'fullscreen-mosaic',
	'fullscreen-mosaic-background', 'fullscreen-mosaic-corner-radius',
	'fullscreen-mosaic-count', 'fullscreen-mosaic-gap', 'fullscreen-mosaic-layout',
	'fullscreen-mosaic-opacity', 'fullscreen-mosaic-position',
	'fullscreen-show-download-button', 'fullscreen-show-link-button',
	'fullscreen-show-navigation', 'fullscreen-slideshow',
	'fullscreen-slideshow-autoresume', 'fullscreen-slideshow-delay',
	'fullscreen-source-height', 'fullscreen-source-width', 'fullscreen-toggle',
	'fullscreen-video-controls-autohide', 'fullscreen-video-controls-color',
	'gallery-buttons-on-mobile', 'gallery-columns', 'gallery-columns-mobile',
	'gallery-columns-tablet', 'gallery-gap', 'gallery-info-bottom',
	'gallery-info-bottom-halo-effect', 'gallery-layout', 'gallery-row-height',
	'gallery-rows', 'gallery-scrollable', 'gallery-sizing', 'height', 'image-fit',
	'info-bottom', 'info-bottom-halo-effect', 'info-bottom-text-align',
	'info-font-color', 'info-font-family', 'info-font-size', 'info-halo-effect',
	'info-text-align', 'info-top', 'info-top-halo-effect', 'info-top-secondary',
	'info-top-secondary-halo-effect', 'info-top-secondary-text-align',
	'info-top-text-align', 'info-wrap', 'interaction-lock',
	'lightbox-background-color', 'lightbox-controls-color',
	'lightbox-corner-radius', 'lightbox-image-fit', 'lightbox-max-height',
	'lightbox-info-bottom', 'lightbox-info-font-color',
	'lightbox-info-font-family', 'lightbox-info-font-size',
	'lightbox-info-top', 'lightbox-info-top-secondary',
	'lightbox-max-width', 'lightbox-mosaic', 'lightbox-mosaic-background',
	'lightbox-mosaic-corner-radius', 'lightbox-mosaic-count',
	'lightbox-mosaic-gap', 'lightbox-mosaic-layout',
	'lightbox-mosaic-opacity', 'lightbox-mosaic-position',
	'lightbox-show-download-button',
	'lightbox-show-link-button', 'lightbox-show-navigation', 'lightbox-slideshow',
	'lightbox-slideshow-autoresume', 'lightbox-slideshow-delay',
	'lightbox-source-height', 'lightbox-source-width', 'lightbox-toggle',
	'lightbox-video-controls-autohide', 'lightbox-video-controls-color', 'limit',
	'link', 'mode', 'mosaic', 'mosaic-background', 'mosaic-corner-radius',
	'mosaic-count', 'mosaic-gap', 'mosaic-opacity', 'mosaic-position',
	'show-download-button', 'show-link-button', 'show-navigation', 'show-videos',
	'slideshow', 'slideshow-autoresume', 'slideshow-delay', 'source-height',
	'source-width', 'start-at', 'video-controls-autohide', 'video-controls-color',
	'width'
];

/**
 * Backward-compatibility parameter aliases. Still accepted by the PHP shortcode
 * handler but intentionally undocumented, so they must not be flagged as
 * unknown parameters.
 */
var JZSA_LEGACY_PARAMS = [
	'cache-refresh', 'show-counter', 'show-title', 'fullscreen-show-counter',
	'fullscreen-show-title', 'gallery-page-bottom'
];

var JZSA_PARAM_SET = ( function () {
	var set = {};
	JZSA_KNOWN_PARAMS.concat( JZSA_LEGACY_PARAMS ).forEach( function ( name ) {
		set[ name ] = true;
	} );
	return set;
}() );

/**
 * Per-parameter value rules.
 *
 * Source of truth: the parse_* helpers in includes/class-orchestrator.php.
 * Parameters absent from this map accept free-form text (info strings, font
 * families) and have no value check. Keep the accepted sets and numeric bounds
 * in sync with the PHP parsers.
 */
var JZSA_VALUE_RULES = ( function () {
	var rules = {};
	var add = function ( names, rule ) {
		names.forEach( function ( name ) {
			rules[ name ] = rule;
		} );
	};

	add( [
		'show-navigation', 'show-link-button', 'show-download-button', 'show-videos',
		'fullscreen-show-navigation', 'fullscreen-show-link-button',
		'fullscreen-show-download-button', 'lightbox-show-navigation',
		'lightbox-show-link-button', 'lightbox-show-download-button',
		'video-controls-autohide', 'fullscreen-video-controls-autohide',
		'lightbox-video-controls-autohide', 'info-halo-effect', 'info-top-halo-effect',
		'info-top-secondary-halo-effect', 'info-bottom-halo-effect',
		'gallery-info-bottom-halo-effect', 'album-title-halo-effect', 'info-wrap',
		'interaction-lock', 'mosaic', 'fullscreen-mosaic', 'gallery-scrollable',
		'expanded-mosaic', 'expanded-show-navigation', 'expanded-show-link-button',
		'expanded-show-download-button', 'expanded-video-controls-autohide',
		'lightbox-mosaic',
		'show-counter', 'show-title', 'fullscreen-show-counter', 'fullscreen-show-title'
	], { type: 'bool' } );

	add( [ 'mode' ], { type: 'enum', values: [ 'gallery', 'slider', 'carousel' ] } );
	add( [ 'image-fit', 'fullscreen-image-fit', 'lightbox-image-fit', 'expanded-image-fit' ],
		{ type: 'enum', values: [ 'cover', 'contain' ] } );
	add( [ 'gallery-layout' ], { type: 'enum', values: [ 'grid', 'justified' ] } );
	add( [ 'gallery-sizing' ], { type: 'enum', values: [ 'ratio', 'fill' ] } );
	add( [ 'gallery-buttons-on-mobile' ],
		{ type: 'enum', values: [ 'on-interaction', 'always' ] } );
	add( [
		'mosaic-position', 'fullscreen-mosaic-position', 'lightbox-mosaic-position',
		'expanded-mosaic-position'
	],
		{ type: 'enum', values: [ 'top', 'bottom', 'left', 'right' ] } );
	add( [ 'fullscreen-mosaic-layout', 'lightbox-mosaic-layout', 'expanded-mosaic-layout' ],
		{ type: 'enum', values: [ 'outer', 'overlay' ] } );
	add( [ 'expanded-toggle' ], { type: 'expanded-toggle' } );
	add( [ 'fullscreen-toggle' ],
		{ type: 'enum', values: [ 'button-only', 'click', 'double-click', 'disabled' ] } );
	add( [ 'lightbox-toggle' ], {
		type: 'enum',
		values: [ 'disabled', 'button-only', 'click', 'double-click' ],
		accept: [ 'true', 'on', 'yes', '1', 'false', 'off', 'no', '0' ]
	} );
	add( [ 'slideshow', 'fullscreen-slideshow', 'lightbox-slideshow', 'expanded-slideshow' ],
		{ type: 'enum', values: [ 'auto', 'manual', 'disabled' ], accept: [ 'true', 'enabled' ] } );
	add( [
		'info-text-align', 'info-top-text-align', 'info-top-secondary-text-align',
		'info-bottom-text-align'
	], { type: 'enum', values: [ 'left', 'center', 'right' ] } );

	add( [
		'background-color', 'controls-color', 'video-controls-color', 'info-font-color',
		'fullscreen-background-color', 'fullscreen-controls-color',
		'fullscreen-video-controls-color', 'fullscreen-info-font-color',
		'lightbox-background-color', 'lightbox-controls-color',
		'lightbox-video-controls-color', 'lightbox-info-font-color',
		'lightbox-mosaic-background', 'mosaic-background', 'fullscreen-mosaic-background',
		'expanded-background-color', 'expanded-controls-color',
		'expanded-video-controls-color', 'expanded-info-font-color',
		'expanded-mosaic-background'
	], { type: 'color' } );

	add( [ 'limit' ], { type: 'int', min: 1 } );
	add( [ 'gallery-rows' ], { type: 'int', min: 0 } );
	add( [
		'corner-radius', 'mosaic-corner-radius', 'fullscreen-corner-radius',
		'fullscreen-mosaic-corner-radius', 'lightbox-mosaic-corner-radius',
		'expanded-corner-radius', 'expanded-mosaic-corner-radius'
	],
		{ type: 'int', min: 0 } );
	add( [ 'download-size-warning' ], { type: 'int', min: 0 } );
	add( [ 'album-cache-refresh', 'cache-refresh' ], { type: 'int', min: 1 } );
	add( [
		'source-width', 'source-height', 'fullscreen-source-width',
		'fullscreen-source-height', 'lightbox-source-width', 'lightbox-source-height',
		'fullscreen-display-max-width', 'fullscreen-display-max-height',
		'lightbox-max-width', 'lightbox-max-height',
		'expanded-source-width', 'expanded-source-height',
		'expanded-max-width', 'expanded-max-height'
	], { type: 'int', min: 1 } );
	add( [ 'gallery-columns', 'gallery-columns-tablet', 'gallery-columns-mobile' ],
		{ type: 'int', min: 1, max: 12 } );
	add( [ 'gallery-row-height' ], { type: 'int', min: 50, max: 800 } );
	add( [
		'gallery-gap', 'mosaic-gap', 'fullscreen-mosaic-gap',
		'lightbox-mosaic-gap', 'expanded-mosaic-gap'
	],
		{ type: 'int', min: 0, max: 100 } );
	add( [
		'info-font-size', 'fullscreen-info-font-size', 'lightbox-info-font-size',
		'expanded-info-font-size'
	],
		{ type: 'int', min: 8, max: 48 } );
	add( [ 'width', 'height' ], { type: 'int', min: 1, auto: true } );
	add( [
		'mosaic-count', 'fullscreen-mosaic-count', 'lightbox-mosaic-count',
		'expanded-mosaic-count'
	], { type: 'int', min: 1, auto: true } );

	add( [
		'mosaic-opacity', 'fullscreen-mosaic-opacity', 'lightbox-mosaic-opacity',
		'expanded-mosaic-opacity'
	], { type: 'opacity' } );
	add( [
		'slideshow-delay', 'fullscreen-slideshow-delay', 'lightbox-slideshow-delay',
		'expanded-slideshow-delay'
	],
		{ type: 'delay' } );
	add( [
		'slideshow-autoresume', 'fullscreen-slideshow-autoresume',
		'lightbox-slideshow-autoresume', 'expanded-slideshow-autoresume'
	], { type: 'autoresume' } );
	add( [ 'start-at' ], { type: 'startat' } );
	add( [ 'link' ], { type: 'url' } );

	return rules;
}() );

/**
 * Check a color string against the formats accepted by parse_color() in PHP:
 * the keyword "transparent", a 6-digit hex code, or an rgb()/rgba()/hsl()/hsla()
 * function.
 */
function jzsaIsValidColor( color ) {
	if ( 'transparent' === color.toLowerCase() ) {
		return true;
	}
	if ( /^#[0-9a-f]{6}$/i.test( color ) ) {
		return true;
	}
	if ( /^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+))?\s*\)$/i.test( color ) ) {
		return true;
	}
	if ( /^hsla?\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(?:,\s*(?:0|1|0?\.\d+))?\s*\)$/i.test( color ) ) {
		return true;
	}
	return false;
}

/**
 * Validate a single parameter value against its rule.
 *
 * @param {string} name     Parameter name (lowercase).
 * @param {string} rawValue The value as written in the shortcode.
 * @return {string|null} A human-readable problem, or null when the value is fine.
 */
function jzsaValidateValue( name, rawValue ) {
	var rule = JZSA_VALUE_RULES[ name ];
	if ( ! rule ) {
		return null;
	}

	var value = ( rawValue || '' ).trim();
	if ( '' === value ) {
		// An empty value behaves like an omitted parameter in PHP.
		return null;
	}
	var lower = value.toLowerCase();

	if ( 'expanded-toggle' === rule.type ) {
		var tokens = lower.split( ',' ).map( function ( token ) {
			return token.trim();
		} );
		var allowedTokens = [
			'lightbox-button', 'lightbox-click', 'lightbox-double-click',
			'fullscreen-button', 'fullscreen-click', 'fullscreen-double-click'
		];
		if ( tokens.length === 1 && tokens[ 0 ] === 'disabled' ) {
			return null;
		}
		if ( tokens.indexOf( 'disabled' ) !== -1 ) {
			return 'Parameter "expanded-toggle" cannot combine "disabled" with another token.';
		}
		var seenModes = {};
		var clickModes = 0;
		for ( var tokenIndex = 0; tokenIndex < tokens.length; tokenIndex++ ) {
			var token = tokens[ tokenIndex ];
			if ( allowedTokens.indexOf( token ) === -1 ) {
				return 'Parameter "expanded-toggle" contains invalid token "' + token +
					'". Use lightbox-button, lightbox-click, lightbox-double-click, ' +
					'fullscreen-button, fullscreen-click, fullscreen-double-click, or disabled.';
			}
			var mode = token.indexOf( 'lightbox-' ) === 0 ? 'lightbox' : 'fullscreen';
			if ( seenModes[ mode ] ) {
				return 'Parameter "expanded-toggle" can contain at most one ' + mode + ' token.';
			}
			seenModes[ mode ] = true;
			if ( token.indexOf( '-click' ) !== -1 ) {
				clickModes++;
			}
		}
		if ( clickModes > 1 ) {
			return 'Parameter "expanded-toggle" cannot give both lightbox and fullscreen a click gesture. ' +
				'Use a button token for at least one mode.';
		}
		return null;
	}

	if ( 'bool' === rule.type ) {
		if ( 'true' !== lower && 'false' !== lower ) {
			return 'Parameter "' + name + '" expects true or false, but got "' + value + '".';
		}
		return null;
	}

	if ( 'enum' === rule.type ) {
		if ( rule.values.indexOf( lower ) !== -1 ) {
			return null;
		}
		if ( rule.accept && rule.accept.indexOf( lower ) !== -1 ) {
			return null;
		}
		return 'Parameter "' + name + '" expects one of: ' + rule.values.join( ', ' ) +
			' - but got "' + value + '".';
	}

	if ( 'color' === rule.type ) {
		if ( ! jzsaIsValidColor( value ) ) {
			return 'Parameter "' + name + '" expects a color such as #1A2B3C, ' +
				'rgb(...), hsl(...), or transparent - but got "' + value + '".';
		}
		return null;
	}

	if ( 'int' === rule.type ) {
		if ( rule.auto && 'auto' === lower ) {
			return null;
		}
		if ( ! /^-?\d+$/.test( value ) ) {
			return 'Parameter "' + name + '" expects a whole number' +
				( rule.auto ? ' or "auto"' : '' ) + ', but got "' + value + '".';
		}
		var intVal = parseInt( value, 10 );
		if ( undefined !== rule.min && intVal < rule.min ) {
			return 'Parameter "' + name + '" must be ' + rule.min +
				' or greater, but got ' + intVal + '.';
		}
		if ( undefined !== rule.max && intVal > rule.max ) {
			return 'Parameter "' + name + '" must be ' + rule.max +
				' or lower, but got ' + intVal + '.';
		}
		return null;
	}

	if ( 'opacity' === rule.type ) {
		if ( ! /^\d*\.?\d+$/.test( value ) ) {
			return 'Parameter "' + name + '" expects a number between 0 and 1, but got "' + value + '".';
		}
		var opacity = parseFloat( value );
		if ( opacity < 0 || opacity > 1 ) {
			return 'Parameter "' + name + '" must be between 0 and 1, but got ' + value + '.';
		}
		return null;
	}

	if ( 'delay' === rule.type ) {
		if ( ! /^\d+$/.test( value ) && ! /^\d+\s*-\s*\d+$/.test( value ) ) {
			return 'Parameter "' + name + '" expects a number of seconds or a range ' +
				'like "4-12", but got "' + value + '".';
		}
		return null;
	}

	if ( 'autoresume' === rule.type ) {
		if ( 'disabled' === lower ) {
			return null;
		}
		if ( ! /^\d+$/.test( value ) || parseInt( value, 10 ) < 1 ) {
			return 'Parameter "' + name + '" expects a positive number of seconds ' +
				'or "disabled", but got "' + value + '".';
		}
		return null;
	}

	if ( 'startat' === rule.type ) {
		if ( 'random' === lower ) {
			return null;
		}
		if ( ! /^\d+$/.test( value ) || parseInt( value, 10 ) < 1 ) {
			return 'Parameter "start-at" expects a slide number (1 or greater) ' +
				'or "random", but got "' + value + '".';
		}
		return null;
	}

	if ( 'url' === rule.type ) {
		// Allow masked album links and {placeholder} tokens used in samples.
		if ( JZSA_MASKED_ALBUM_LINK === lower || value.indexOf( '{' ) !== -1 ) {
			return null;
		}
		if ( ! /^https?:\/\//i.test( value ) ) {
			return 'The "link" value should be a full Google Photos album share URL ' +
				'starting with "https://".';
		}
		return null;
	}

	return null;
}

function jzsaEscapeHtml( str ) {
	return String( str )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

/**
 * Levenshtein edit distance between two strings.
 */
function jzsaLevenshtein( a, b ) {
	var m = a.length;
	var n = b.length;
	if ( ! m ) {
		return n;
	}
	if ( ! n ) {
		return m;
	}
	var prev = [];
	var i;
	var j;
	for ( j = 0; j <= n; j++ ) {
		prev[ j ] = j;
	}
	for ( i = 1; i <= m; i++ ) {
		var cur = [ i ];
		for ( j = 1; j <= n; j++ ) {
			var cost = a.charAt( i - 1 ) === b.charAt( j - 1 ) ? 0 : 1;
			cur[ j ] = Math.min( cur[ j - 1 ] + 1, prev[ j ] + 1, prev[ j - 1 ] + cost );
		}
		prev = cur;
	}
	return prev[ n ];
}

/**
 * Suggest the closest known parameter name for a typo, or null when nothing is
 * close enough to be a confident suggestion.
 */
function jzsaSuggestParam( name ) {
	var best = null;
	// Allow looser matches for longer names, but stay strict for short ones so
	// unrelated words (e.g. "zoom") do not get a misleading suggestion.
	var maxDist = Math.max( 1, Math.floor( name.length / 4 ) );
	var bestDist = maxDist + 1;
	JZSA_KNOWN_PARAMS.forEach( function ( known ) {
		var dist = jzsaLevenshtein( name, known );
		if ( dist < bestDist ) {
			bestDist = dist;
			best = known;
		}
	} );
	return best;
}

/**
 * Validate the syntax of a [jzsa-album] shortcode string.
 *
 * Performs purely client-side checks: bracket balance, the shortcode tag name,
 * quote matching, malformed key=value pairs, duplicate attributes, a missing
 * link, and unknown parameter names. It does not validate parameter values.
 *
 * @param {string} raw The raw shortcode text.
 * @return {{state: string, errors: string[], warnings: string[]}}
 */
function jzsaValidateShortcode( raw ) {
	var errors = [];
	var warnings = [];
	// The replace() below swaps non-breaking spaces (emitted by contentEditable)
	// for plain spaces so tokenization and \s matching behave predictably.
	var text = ( raw || '' ).replace( /\u00A0/g, ' ' ).trim();

	if ( ! text ) {
		// A pristine empty field is not an error; the message area stays hidden.
		return { state: 'empty', errors: errors, warnings: warnings };
	}

	// Single quote-aware pass: track quote state and record the positions of
	// brackets that sit outside quoted values (so a "]" inside an info string
	// never trips the structural checks).
	var quote = null;
	var opens = [];
	var closes = [];
	var i;
	for ( i = 0; i < text.length; i++ ) {
		var ch = text.charAt( i );
		if ( quote ) {
			if ( ch === quote ) {
				quote = null;
			}
		} else if ( ch === '"' || ch === '\'' ) {
			quote = ch;
		} else if ( ch === '[' ) {
			opens.push( i );
		} else if ( ch === ']' ) {
			closes.push( i );
		}
	}

	if ( quote ) {
		errors.push( 'Unterminated ' + ( quote === '"' ? 'double' : 'single' ) +
			' quote (' + quote + ') - every opening quote needs a matching closing one.' );
		return { state: 'error', errors: errors, warnings: warnings };
	}

	if ( ! opens.length || ! closes.length ) {
		errors.push( 'Shortcode must be wrapped in square brackets: [jzsa-album ...].' );
		return { state: 'error', errors: errors, warnings: warnings };
	}
	if ( opens.length > 1 || closes.length > 1 ) {
		errors.push( 'Enter exactly one [jzsa-album] shortcode.' );
	}
	if ( text.charAt( 0 ) !== '[' ) {
		errors.push( 'Shortcode must start with "[".' );
	}
	if ( text.charAt( text.length - 1 ) !== ']' ) {
		errors.push( 'Shortcode must end with "]".' );
	}
	if ( opens[ 0 ] > closes[ 0 ] ) {
		errors.push( 'A closing "]" appears before the opening "[".' );
		return { state: 'error', errors: errors, warnings: warnings };
	}

	var inner = text.slice( opens[ 0 ] + 1, closes[ closes.length - 1 ] );

	// Tag name.
	var tagMatch = inner.match( /^\s*([a-z0-9_-]*)/i );
	var tag = tagMatch ? tagMatch[ 1 ] : '';
	if ( tag !== 'jzsa-album' ) {
		if ( ! tag ) {
			errors.push( 'Missing shortcode name. Expected [jzsa-album ...].' );
		} else {
			errors.push( 'Unknown shortcode name "' + tag + '". Expected "jzsa-album".' );
		}
	}

	var attrText = inner.slice( tagMatch ? tagMatch[ 0 ].length : 0 );

	// Quotes are verified balanced above, so attribute tokenization is safe.
	var attrRe = /([\w-]+)\s*=\s*"([^"]*)"|([\w-]+)\s*=\s*'([^']*)'|([\w-]+)\s*=\s*([^\s'"\]]+)|"([^"]*)"|'([^']*)'|(\S+)/g;
	var seen = {};
	var attrValues = {};
	var hasLink = false;
	var match;
	while ( ( match = attrRe.exec( attrText ) ) ) {
		var name = match[ 1 ] || match[ 3 ] || match[ 5 ];
		if ( name ) {
			name = name.toLowerCase();
			if ( seen[ name ] ) {
				warnings.push( 'Parameter "' + name + '" is set more than once - only the last value is used.' );
			}
			seen[ name ] = true;

			var rawValue = ( match[ 2 ] !== undefined ) ? match[ 2 ]
				: ( match[ 4 ] !== undefined ) ? match[ 4 ]
					: ( match[ 6 ] !== undefined ) ? match[ 6 ] : '';
			attrValues[ name ] = rawValue;

			if ( name === 'link' ) {
				hasLink = true;
				if ( ! rawValue.trim() ) {
					errors.push( 'The "link" parameter is empty - paste a Google Photos album share URL.' );
					continue;
				}
			} else if ( ! JZSA_PARAM_SET[ name ] ) {
				var suggestion = jzsaSuggestParam( name );
				warnings.push( 'Unknown parameter "' + name + '"' +
					( suggestion ? ' - did you mean "' + suggestion + '"?' : '.' ) );
				continue;
			}

			// Known parameter: check that its value matches the expected type.
			var valueIssue = jzsaValidateValue( name, rawValue );
			if ( valueIssue ) {
				if ( name === 'expanded-toggle' ) {
					errors.push( valueIssue );
				} else {
					warnings.push( valueIssue );
				}
			}
			continue;
		}

		// Bare token with no "name=" prefix.
		var bareWord = match[ 9 ];
		if ( bareWord !== undefined ) {
			if ( /^https?:\/\//i.test( bareWord ) ) {
				hasLink = true; // Positional album URL.
			} else if ( bareWord.indexOf( '=' ) !== -1 ) {
				var partial = bareWord.split( '=' )[ 0 ];
				errors.push( 'Parameter "' + partial + '" has an empty or malformed value. Use ' +
					partial + '="value".' );
			} else {
				warnings.push( 'Stray value "' + bareWord +
					'" has no parameter name and is ignored. Use name="value".' );
			}
			continue;
		}

		// Bare quoted token.
		var bareQuoted = ( match[ 7 ] !== undefined ) ? match[ 7 ] : match[ 8 ];
		if ( bareQuoted !== undefined ) {
			if ( /^https?:\/\//i.test( bareQuoted ) ) {
				hasLink = true;
			} else {
				warnings.push( 'Stray quoted value has no parameter name and is ignored. Use name="value".' );
			}
		}
	}

	if ( ! hasLink ) {
		errors.push( 'Missing required "link" parameter. Add link="https://photos.google.com/share/...".' );
	}

	if ( ( attrValues[ 'gallery-layout' ] || '' ).trim().toLowerCase() === 'justified' ) {
		var ignoredJustifiedParams = [
			'gallery-columns',
			'gallery-columns-tablet',
			'gallery-columns-mobile',
			'gallery-sizing'
		].filter( function ( paramName ) {
			return seen[ paramName ];
		} );

		if ( ignoredJustifiedParams.length ) {
			warnings.push(
				'Parameter "gallery-layout" is "justified", so ' +
				ignoredJustifiedParams.join( ', ' ) +
				' ' + ( ignoredJustifiedParams.length === 1 ? 'is' : 'are' ) +
				' ignored. Justified layout uses gallery-row-height, gallery-gap, container width, and photo aspect ratios instead.'
			);
		}
	}

	var ltToggle = '';
	var fsToggle = '';
	var expandedToggle = ( attrValues[ 'expanded-toggle' ] || '' ).trim().toLowerCase();
	if ( expandedToggle && expandedToggle !== 'disabled' ) {
		expandedToggle.split( ',' ).forEach( function ( rawToken ) {
			var token = rawToken.trim();
			var trigger = token.indexOf( 'double-click' ) !== -1
				? 'double-click'
				: ( token.indexOf( '-click' ) !== -1 ? 'click' : 'button-only' );
			if ( token.indexOf( 'lightbox-' ) === 0 ) {
				ltToggle = trigger;
			}
			if ( token.indexOf( 'fullscreen-' ) === 0 ) {
				fsToggle = trigger;
			}
		} );
	}
	if ( seen[ 'lightbox-toggle' ] ) {
		ltToggle = ( attrValues[ 'lightbox-toggle' ] || '' ).trim().toLowerCase();
	}
	if ( seen[ 'fullscreen-toggle' ] ) {
		fsToggle = ( attrValues[ 'fullscreen-toggle' ] || '' ).trim().toLowerCase();
	}
	var clickValues = [ 'click', 'double-click' ];
	if ( clickValues.indexOf( ltToggle ) !== -1 && clickValues.indexOf( fsToggle ) !== -1 ) {
		var conflictMessage =
			'Lightbox and fullscreen cannot both use a click gesture because they compete for the same tap. ' +
			'Use a button trigger for at least one mode.';
		if ( expandedToggle ) {
			errors.push( conflictMessage );
		} else {
			warnings.push(
				'Both lightbox-toggle and fullscreen-toggle are set to a click action ("' + ltToggle + '" and "' + fsToggle + '"). ' +
				'They would compete for the same tap, so the plugin automatically treats fullscreen-toggle as "button-only": ' +
				'lightbox keeps the click trigger and fullscreen gets its own button in the corner. ' +
				'To silence this warning, set fullscreen-toggle="button-only" explicitly.'
			);
		}
	}

	var state = errors.length ? 'error' : ( warnings.length ? 'warning' : 'ok' );
	return { state: state, errors: errors, warnings: warnings };
}

/**
 * Render a validation result into its message area below a code block.
 */
function jzsaRenderValidation( validationEl, result ) {
	validationEl.classList.remove(
		'jzsa-code-validation--ok',
		'jzsa-code-validation--warning',
		'jzsa-code-validation--error'
	);

	if ( 'empty' === result.state || 'ok' === result.state ) {
		// Nothing to report: leaving the element empty collapses it entirely
		// via the :empty CSS rule, so only problems are ever shown.
		validationEl.textContent = '';
		return;
	}

	validationEl.classList.add( 'jzsa-code-validation--' + result.state );

	var items = '';
	result.errors.forEach( function ( msg ) {
		items += '<li>\u2715 ' + jzsaEscapeHtml( msg ) + '</li>';
	} );
	result.warnings.forEach( function ( msg ) {
		items += '<li>\u26A0 ' + jzsaEscapeHtml( msg ) + '</li>';
	} );
	validationEl.innerHTML = '<ul class="jzsa-code-validation__list">' + items + '</ul>';
}

/**
 * Set up Copy/Apply/Revert on a single .jzsa-code-block.
 * Safe to call on dynamically created blocks after page load.
 *
 * @param {HTMLElement} block
 */
function jzsaSetupCodeBlock( block ) {
	var codeEl = block.querySelector( 'code' );
	if ( ! codeEl ) {
		return;
	}

	var previewContainer = block.nextElementSibling;
	// A prior init may have inserted the validation area between the block and
	// its preview; skip past it so the preview is still found on a repeat call.
	if ( previewContainer && previewContainer.classList.contains( 'jzsa-code-validation' ) ) {
		previewContainer = previewContainer.nextElementSibling;
	}
	var hasPreview = previewContainer && previewContainer.classList.contains( 'jzsa-preview-container' );
	var originalText = codeEl.textContent || '';

	// Assigned in the editable branch below; left null for non-editable blocks.
	var runValidation = null;

	// Make editable if there's a preview to update.
	if ( hasPreview ) {
		codeEl.contentEditable = 'true';
		codeEl.spellcheck = false;
		codeEl.classList.add( 'jzsa-editable-code' );
	}

	// Build button column: Copy + (Apply + Revert if preview exists).
	var btnCol = block.querySelector( '.jzsa-code-block-btns' );
	var copyBtn = btnCol ? btnCol.querySelector( '[data-jzsa-action="copy"]' ) : null;

	var applyBtn = null;
	var revertBtn = null;

	if ( ! btnCol ) {
		btnCol = document.createElement( 'div' );
		btnCol.className = 'jzsa-code-block-btns';
		block.appendChild( btnCol );
	}

	if ( ! copyBtn ) {
		copyBtn = document.createElement( 'button' );
		copyBtn.type = 'button';
		copyBtn.className = 'jzsa-action-btn';
		copyBtn.textContent = 'Copy';
		if ( ! btnCol.contains( copyBtn ) ) {
			btnCol.appendChild( copyBtn );
		}
	}

	if ( hasPreview ) {
		applyBtn = btnCol.querySelector( '[data-jzsa-action="apply"]' );
		revertBtn = btnCol.querySelector( '[data-jzsa-action="revert"]' );

		if ( ! applyBtn ) {
			applyBtn = document.createElement( 'button' );
			applyBtn.type = 'button';
			applyBtn.className = 'jzsa-action-btn';
			applyBtn.textContent = 'Apply';
			btnCol.appendChild( applyBtn );
		}

		if ( ! revertBtn ) {
			revertBtn = document.createElement( 'button' );
			revertBtn.type = 'button';
			revertBtn.className = 'jzsa-action-btn';
			revertBtn.textContent = 'Revert';
			btnCol.appendChild( revertBtn );
		}
	}

	// Copy handler. Refresh validation on Copy so problems surface even when
	// the user never edited the shortcode.
	copyBtn.addEventListener( 'click', function () {
		if ( runValidation ) {
			runValidation();
		}
		jzsaCopyToClipboard( copyBtn, codeEl.textContent || '' );
	} );

	if ( ! hasPreview ) {
		return;
	}

	// Live validation area, inserted just below the editable code block.
	var validationEl = block.nextElementSibling;
	if ( ! validationEl || ! validationEl.classList.contains( 'jzsa-code-validation' ) ) {
		validationEl = document.createElement( 'div' );
		validationEl.className = 'jzsa-code-validation';
		validationEl.setAttribute( 'aria-live', 'polite' );
		block.insertAdjacentElement( 'afterend', validationEl );
	}
	runValidation = function () {
		jzsaRenderValidation( validationEl, jzsaValidateShortcode( codeEl.textContent || '' ) );
	};

	// Keep placeholder highlighting and validation live while editing.
	codeEl.addEventListener( 'input', function () {
		jzsaHighlightPlaceholders( codeEl );
		runValidation();
	} );

	// Highlight placeholders and validate on initial load.
	jzsaHighlightPlaceholders( codeEl );
	runValidation();

	// Revert: restore original shortcode, re-highlight placeholders, and re-apply the preview.
	revertBtn.addEventListener( 'click', function () {
		codeEl.textContent = originalText;
		jzsaHighlightPlaceholders( codeEl );
		runValidation();
		var revertOverride = codeEl.dataset.revertShortcode || undefined;
		jzsaApplyPreview( codeEl, revertBtn, previewContainer, 'Reverted!', revertOverride );
	} );

	// Apply: AJAX preview.
	applyBtn.addEventListener( 'click', function () {
		runValidation();
		jzsaApplyPreview( codeEl, applyBtn, previewContainer );
	} );
}

/**
 * Bind click handlers to copy buttons and wire up the Playground preview.
 */
function jzsaInitAdminSettings() {
	var blocks = document.querySelectorAll( '.jzsa-code-block' );

	blocks.forEach( function ( block ) {
		jzsaSetupCodeBlock( block );
	} );

	// Apply random pastel backgrounds to guide-page sample blocks.
	document.querySelectorAll( '.jzsa-sample-card' ).forEach( function ( el ) {
		el.style.background = 'hsl(' + Math.floor( Math.random() * 360 ) + ', 55%, 95%)';
	} );

	// Wire the Clear Cache buttons.
	var clearCacheBtns = document.querySelectorAll( '[data-jzsa-clear-cache-scope]' );
	var clearCacheResult = document.getElementById( 'jzsa-clear-cache-result' );

	if ( clearCacheBtns.length ) {
		clearCacheBtns.forEach( function ( clearCacheBtn ) {
			clearCacheBtn.addEventListener( 'click', function () {
				if ( typeof jzsaAdminAjax === 'undefined' || ! jzsaAdminAjax.ajaxUrl ) {
					return;
				}

			clearCacheBtns.forEach( function ( button ) {
				button.disabled = true;
			} );
			clearCacheBtn.textContent = 'Clearing…';
			if ( clearCacheResult ) {
				clearCacheResult.textContent = '';
				clearCacheResult.className = 'jzsa-cache-result';
			}

				var params = new URLSearchParams();
				params.append( 'action', 'jzsa_clear_cache' );
				params.append( 'nonce', jzsaAdminAjax.clearCacheNonce );
				params.append( 'scope', clearCacheBtn.getAttribute( 'data-jzsa-clear-cache-scope' ) || 'all' );

				window.fetch( jzsaAdminAjax.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: params.toString(),
				} )
					.then( function ( response ) { return response.json(); } )
					.then( function ( data ) {
						clearCacheBtns.forEach( function ( button ) {
							button.disabled = false;
							button.textContent = button.getAttribute( 'data-jzsa-idle-label' ) || button.textContent;
						} );
						if ( clearCacheResult ) {
							clearCacheResult.textContent = data.success ? data.data.message : ( data.data || 'Error clearing cache.' );
							clearCacheResult.className = 'jzsa-cache-result ' + ( data.success ? 'jzsa-cache-result--success' : 'jzsa-cache-result--error' );
						}
					} )
					.catch( function () {
						clearCacheBtns.forEach( function ( button ) {
							button.disabled = false;
							button.textContent = button.getAttribute( 'data-jzsa-idle-label' ) || button.textContent;
						} );
						if ( clearCacheResult ) {
							clearCacheResult.textContent = 'Request failed.';
							clearCacheResult.className = 'jzsa-cache-result jzsa-cache-result--error';
						}
					} );
			} );
		} );
	}

	jzsaSetupLazyPreviews();
}

// Run on DOMContentLoaded, or immediately if the document already finished
// parsing. A bare DOMContentLoaded listener silently never fires when the
// script is loaded late, deferred, or async (e.g. by a caching/optimization
// plugin, or a changed script-loading strategy) - which leaves the Playground
// with no validation and no Copy/Apply/Revert wiring.
if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', jzsaInitAdminSettings );
} else {
	jzsaInitAdminSettings();
}
