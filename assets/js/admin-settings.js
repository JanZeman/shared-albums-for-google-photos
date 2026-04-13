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

function jzsaApplyPreview( codeEl, triggerBtn, previewContainer, flashLabel, options ) {
	var shortcode = ( codeEl.textContent || '' ).trim();
	var opts = options || {};
	var silent = !! opts.silent;
	var ajaxConfig = ( typeof jzsaAjax !== 'undefined' && jzsaAjax && jzsaAjax.ajaxUrl && jzsaAjax.previewNonce ) ? jzsaAjax : jzsaAdminAjax;
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
		if ( ! silent ) {
			triggerBtn.textContent = 'Applying\u2026';
		}
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

			if ( triggerBtn && ! silent ) {
				jzsaFlashButton( triggerBtn, flashLabel || 'Applied!' );
			}

			if ( window.SharedGooglePhotos ) {
				var album = previewContainer.querySelector( '.jzsa-album' );
				if ( album ) {
					var mode = album.getAttribute( 'data-mode' ) || 'slider';
					if ( mode === 'gallery' && typeof window.SharedGooglePhotos.initializeGallery === 'function' ) {
						window.SharedGooglePhotos.initializeGallery( album );
					} else if ( typeof window.SharedGooglePhotos.initialize === 'function' ) {
						window.SharedGooglePhotos.initialize( album, mode );
					}
				}
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

function jzsaSchedulePlaygroundInitialPreview( codeEl, previewContainer, applyBtn ) {
	if ( ! codeEl || ! previewContainer ) {
		return;
	}

	var scheduleLoad = function () {
		jzsaApplyPreview( codeEl, applyBtn, previewContainer, '', { silent: true } );
	};
	window.setTimeout( scheduleLoad, 250 );
}

/**
 * Bind click handlers to copy buttons and wire up the Playground preview.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var blocks = document.querySelectorAll( '.jzsa-code-block' );

	blocks.forEach( function ( block ) {
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
			jzsaApplyPreview( codeEl, revertBtn, previewContainer, 'Reverted!' );
		} );

		// Apply: AJAX preview.
		applyBtn.addEventListener( 'click', function () {
			jzsaApplyPreview( codeEl, applyBtn, previewContainer );
		} );
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

	var playgroundCode = document.getElementById( 'jzsa-playground-shortcode' );
	if ( playgroundCode ) {
		var playgroundSection = playgroundCode.closest( '.jzsa-playground-section' );
		var playgroundPreview = playgroundSection ? playgroundSection.querySelector( '.jzsa-playground-preview' ) : null;
		var playgroundBlock = playgroundCode.closest( '.jzsa-code-block' );
		var playgroundApply = playgroundBlock ? playgroundBlock.querySelector( '[data-jzsa-action="apply"]' ) : null;
		if ( playgroundPreview && playgroundPreview.hasAttribute( 'data-initial-shortcode' ) ) {
			jzsaSchedulePlaygroundInitialPreview( playgroundCode, playgroundPreview, playgroundApply );
		}
	}
} );
