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
	// Community browse cards display link="[link]" to mask the real URL.
	// Substitute the real URL back before sending to the preview AJAX endpoint.
	if ( ! shortcodeOverride && shortcode.indexOf( '[link]' ) !== -1 && codeEl && codeEl.dataset && codeEl.dataset.revertShortcode ) {
		var urlMatch = codeEl.dataset.revertShortcode.match( /\blink\s*=\s*["']([^"']+)["']/i );
		if ( urlMatch ) {
			shortcode = shortcode.replace( '[link]', urlMatch[ 1 ] );
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
	var hasPreview = previewContainer && previewContainer.classList.contains( 'jzsa-preview-container' );
	var originalText = codeEl.textContent || '';

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

	// Copy handler.
	copyBtn.addEventListener( 'click', function () {
		jzsaCopyToClipboard( copyBtn, codeEl.textContent || '' );
	} );

	if ( ! hasPreview ) {
		return;
	}

	// Keep placeholder highlighting live while editing.
	codeEl.addEventListener( 'input', function () {
		jzsaHighlightPlaceholders( codeEl );
	} );

	// Highlight placeholders on initial load.
	jzsaHighlightPlaceholders( codeEl );

	// Revert: restore original shortcode, re-highlight placeholders, and re-apply the preview.
	revertBtn.addEventListener( 'click', function () {
		codeEl.textContent = originalText;
		jzsaHighlightPlaceholders( codeEl );
		var revertOverride = codeEl.dataset.revertShortcode || undefined;
		jzsaApplyPreview( codeEl, revertBtn, previewContainer, 'Reverted!', revertOverride );
	} );

	// Apply: AJAX preview.
	applyBtn.addEventListener( 'click', function () {
		jzsaApplyPreview( codeEl, applyBtn, previewContainer );
	} );
}

/**
 * Bind click handlers to copy buttons and wire up the Playground preview.
 */
document.addEventListener( 'DOMContentLoaded', function () {
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
} );
