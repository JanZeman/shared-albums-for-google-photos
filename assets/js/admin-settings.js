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
 * Highlight {token} placeholders in red inside an editable code element.
 * Preserves cursor position across innerHTML replacements.
 */
function jzsaHighlightTokens( codeEl ) {
	var text = codeEl.textContent || '';
	var escaped = text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	var highlighted = escaped.replace( /(\{[a-z_-]+\})/g, '<span class="jzsa-token">$1</span>' );
	if ( highlighted === escaped ) {
		return; // No tokens, skip innerHTML update.
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

function jzsaApplyPreview( codeEl, applyBtn, previewContainer ) {
	var shortcode = ( codeEl.textContent || '' ).trim();
	if ( ! shortcode ) {
		return;
	}
	if ( typeof jzsaAjax === 'undefined' || ! jzsaAjax.ajaxUrl || ! jzsaAjax.previewNonce ) {
		return;
	}

	applyBtn.disabled = true;
	applyBtn.textContent = 'Applying…';
	previewContainer.style.opacity = '0.5';

	var params = new URLSearchParams();
	params.append( 'action', 'jzsa_shortcode_preview' );
	params.append( 'nonce', jzsaAjax.previewNonce );
	params.append( 'shortcode', shortcode );

	window.fetch( jzsaAjax.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: params.toString(),
	} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( data ) {
			applyBtn.disabled = false;
			applyBtn.textContent = 'Apply';
			previewContainer.style.opacity = '';

			if ( ! data || ! data.success || ! data.data || ! data.data.html ) {
				var msg = ( data && data.data && typeof data.data === 'string' ) ? data.data : 'Preview failed.';
				previewContainer.innerHTML = '<div class="jzsa-playground-error">' + msg + '</div>';
				return;
			}

			previewContainer.innerHTML = data.data.html;

			jzsaFlashButton( applyBtn, 'Applied!' );

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
			applyBtn.disabled = false;
			applyBtn.textContent = 'Apply';
			previewContainer.style.opacity = '';
			previewContainer.innerHTML = '<div class="jzsa-playground-error">Request failed.</div>';
		} );
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
		var btnCol = document.createElement( 'div' );
		btnCol.className = 'jzsa-code-block-btns';

		var copyBtn = document.createElement( 'button' );
		copyBtn.type = 'button';
		copyBtn.className = 'jzsa-action-btn';
		copyBtn.textContent = 'Copy';
		btnCol.appendChild( copyBtn );

		var applyBtn = null;
		var revertBtn = null;

		if ( hasPreview ) {
			applyBtn = document.createElement( 'button' );
			applyBtn.type = 'button';
			applyBtn.className = 'jzsa-action-btn';
			applyBtn.textContent = 'Apply';
			applyBtn.disabled = true;
			btnCol.appendChild( applyBtn );

			revertBtn = document.createElement( 'button' );
			revertBtn.type = 'button';
			revertBtn.className = 'jzsa-action-btn';
			revertBtn.textContent = 'Revert';
			revertBtn.disabled = true;
			btnCol.appendChild( revertBtn );
		}

		block.appendChild( btnCol );

		// Copy handler.
		copyBtn.addEventListener( 'click', function () {
			jzsaCopyToClipboard( copyBtn, codeEl.textContent || '' );
		} );

		if ( ! hasPreview ) {
			return;
		}

		// Enable/disable Apply+Revert when content changes; highlight tokens.
		codeEl.addEventListener( 'input', function () {
			var changed = ( codeEl.textContent || '' ) !== originalText;
			applyBtn.disabled = ! changed;
			revertBtn.disabled = ! changed;
			jzsaHighlightTokens( codeEl );
		} );

		// Highlight tokens on initial load.
		jzsaHighlightTokens( codeEl );

		// Revert with green flash.
		revertBtn.addEventListener( 'click', function () {
			codeEl.textContent = originalText;
			applyBtn.disabled = true;
			revertBtn.disabled = true;
			jzsaFlashButton( revertBtn, 'Reverted!' );
		} );

		// Apply: AJAX preview.
		applyBtn.addEventListener( 'click', function () {
			jzsaApplyPreview( codeEl, applyBtn, previewContainer );
		} );
	} );

	// Wire the Clear Cache button.
	var clearCacheBtn = document.getElementById( 'jzsa-clear-cache-btn' );
	var clearCacheResult = document.getElementById( 'jzsa-clear-cache-result' );

	if ( clearCacheBtn ) {
		clearCacheBtn.addEventListener( 'click', function () {
			if ( typeof jzsaAdminAjax === 'undefined' || ! jzsaAdminAjax.ajaxUrl ) {
				return;
			}

			clearCacheBtn.disabled = true;
			clearCacheBtn.textContent = 'Clearing…';
			if ( clearCacheResult ) {
				clearCacheResult.textContent = '';
				clearCacheResult.className = 'jzsa-tool-result';
			}

			var params = new URLSearchParams();
			params.append( 'action', 'jzsa_clear_cache' );
			params.append( 'nonce', jzsaAdminAjax.clearCacheNonce );

			window.fetch( jzsaAdminAjax.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: params.toString(),
			} )
				.then( function ( response ) { return response.json(); } )
				.then( function ( data ) {
					clearCacheBtn.disabled = false;
					clearCacheBtn.textContent = 'Clear Cache';
					if ( clearCacheResult ) {
						clearCacheResult.textContent = data.success ? data.data.message : ( data.data || 'Error clearing cache.' );
						clearCacheResult.className = 'jzsa-tool-result ' + ( data.success ? 'jzsa-tool-result--success' : 'jzsa-tool-result--error' );
					}
				} )
				.catch( function () {
					clearCacheBtn.disabled = false;
					clearCacheBtn.textContent = 'Clear Cache';
					if ( clearCacheResult ) {
						clearCacheResult.textContent = 'Request failed.';
						clearCacheResult.className = 'jzsa-tool-result jzsa-tool-result--error';
					}
				} );
		} );
	}

	// Playground: convert textarea into the same code-block + 3-button layout.
	var textarea = document.getElementById( 'jzsa-playground-shortcode' );
	if ( textarea ) {
		var playgroundSection = textarea.closest( '.jzsa-playground-section' );
		var playgroundPreview = playgroundSection ? playgroundSection.querySelector( '.jzsa-playground-preview' ) : null;
		var playgroundOriginal = textarea.value || '';

		// Replace textarea with a code-block layout.
		var pgBlock = document.createElement( 'div' );
		pgBlock.className = 'jzsa-code-block';

		var pgCode = document.createElement( 'code' );
		pgCode.contentEditable = 'true';
		pgCode.spellcheck = false;
		pgCode.className = 'jzsa-editable-code';
		pgCode.textContent = playgroundOriginal;
		pgBlock.appendChild( pgCode );

		var pgBtns = document.createElement( 'div' );
		pgBtns.className = 'jzsa-code-block-btns';

		var pgCopy = document.createElement( 'button' );
		pgCopy.type = 'button';
		pgCopy.className = 'jzsa-action-btn';
		pgCopy.textContent = 'Copy';
		pgBtns.appendChild( pgCopy );

		var pgApply = document.createElement( 'button' );
		pgApply.type = 'button';
		pgApply.className = 'jzsa-action-btn';
		pgApply.textContent = 'Apply';
		pgApply.disabled = true;
		pgBtns.appendChild( pgApply );

		var pgRevert = document.createElement( 'button' );
		pgRevert.type = 'button';
		pgRevert.className = 'jzsa-action-btn';
		pgRevert.textContent = 'Revert';
		pgRevert.disabled = true;
		pgBtns.appendChild( pgRevert );

		pgBlock.appendChild( pgBtns );

		// Also remove the screen-reader label.
		var pgLabel = playgroundSection ? playgroundSection.querySelector( 'label[for="jzsa-playground-shortcode"]' ) : null;
		if ( pgLabel ) {
			pgLabel.remove();
		}
		textarea.parentNode.insertBefore( pgBlock, textarea );
		textarea.remove();

		pgCopy.addEventListener( 'click', function () {
			jzsaCopyToClipboard( pgCopy, pgCode.textContent || '' );
		} );

		pgCode.addEventListener( 'input', function () {
			var changed = ( pgCode.textContent || '' ) !== playgroundOriginal;
			pgApply.disabled = ! changed;
			pgRevert.disabled = ! changed;
			jzsaHighlightTokens( pgCode );
		} );

		jzsaHighlightTokens( pgCode );

		pgRevert.addEventListener( 'click', function () {
			pgCode.textContent = playgroundOriginal;
			pgApply.disabled = true;
			pgRevert.disabled = true;
		} );

		pgApply.addEventListener( 'click', function () {
			if ( playgroundPreview ) {
				jzsaApplyPreview( pgCode, pgApply, playgroundPreview );
			}
		} );
	}
} );
