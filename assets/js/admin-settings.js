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
function jzsaCopyToClipboard( button, text ) {
	// Create temporary textarea
	var textarea = document.createElement( 'textarea' );
	textarea.value = text;
	textarea.style.position = 'fixed';
	textarea.style.opacity = '0';
	document.body.appendChild( textarea );

	// Select and copy
	textarea.select();
	document.execCommand( 'copy' );
	document.body.removeChild( textarea );

	// Visual feedback
	var originalText = button.textContent;
	button.textContent = 'Copied!';
	button.style.background = '#46b450';

	setTimeout( function() {
		button.textContent = originalText;
		button.style.background = '';
	}, 2000 );
}

/**
 * Request a shortcode preview for the Playground via AJAX and update the
 * preview container. This step intentionally does NOT re-initialize Swiper
 * on the result; it is purely about checking that the shortcode renders
 * without errors.
 */
function jzsaRunPlaygroundPreview() {
	var textarea = document.getElementById( 'jzsa-playground-shortcode' );
	var preview  = document.querySelector( '.jzsa-playground-preview' );

	if ( ! textarea || ! preview ) {
		return;
	}

	var shortcode = textarea.value.trim();
	if ( ! shortcode ) {
		preview.innerHTML = '';
		return;
	}

	// Show a very small loading state.
	preview.innerHTML = '<p class="jzsa-help-text">Loading preview…</p>';

	if ( typeof jzsaAjax === 'undefined' || ! jzsaAjax.ajaxUrl || ! jzsaAjax.previewNonce ) {
		preview.innerHTML = '<div class="jzsa-playground-error">Preview is not available – AJAX settings are missing.</div>';
		return;
	}

	var params = new URLSearchParams();
	params.append( 'action', 'jzsa_shortcode_preview' );
	params.append( 'nonce', jzsaAjax.previewNonce );
	params.append( 'shortcode', shortcode );

	window.fetch( jzsaAjax.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
		},
		body: params.toString(),
	} )
		.then( function ( response ) {
			return response.json();
		} )
		.then( function ( data ) {
			if ( ! data || typeof data.success === 'undefined' ) {
				preview.innerHTML = '<div class="jzsa-playground-error">Unexpected response from server.</div>';
				return;
			}

			if ( ! data.success ) {
				var message = '';
				if ( data.data && typeof data.data === 'string' ) {
					message = data.data;
				} else if ( data.data && data.data.message ) {
					message = data.data.message;
				} else {
					message = 'Preview failed.';
				}
				preview.innerHTML = '<div class="jzsa-playground-error"></div>';
				var errorEl = preview.querySelector( '.jzsa-playground-error' );
				if ( errorEl ) {
					errorEl.textContent = message;
				}
				return;
			}

			if ( ! data.data || typeof data.data.html === 'undefined' ) {
				preview.innerHTML = '<div class=\"jzsa-playground-error\">Preview did not return any HTML.</div>';
				return;
			}

			// Replace preview HTML.
			preview.innerHTML = data.data.html;

			// Step 2b: initialize Swiper for the newly rendered gallery, using the
			// same initializer as the front‑end. This makes the preview fully
			// interactive, but we only do it once per Update click.
			if ( window.SharedGooglePhotos && typeof window.SharedGooglePhotos.initialize === 'function' ) {
				var album = preview.querySelector( '.jzsa-album' );
				if ( album ) {
					var mode = album.getAttribute( 'data-mode' ) || 'single';
					window.SharedGooglePhotos.initialize( album, mode );
				}
			}
		} )
		.catch( function ( error ) {
			preview.innerHTML = '<div class="jzsa-playground-error">Unable to contact the server for preview.</div>';
			if ( window.console && console.error ) {
				console.error( 'JZSA Shortcode Playground preview error:', error );
			}
		} );
}

/**
 * Bind click handlers to copy buttons and wire up the Playground preview.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var blocks = document.querySelectorAll( '.jzsa-code-block' );

	blocks.forEach( function ( block ) {
		var button = block.querySelector( '.jzsa-copy-btn' );
		var codeEl = block.querySelector( 'code' );

		if ( ! button || ! codeEl ) {
			return;
		}

		button.addEventListener( 'click', function () {
			// Use the visible code content as the text to copy.
			jzsaCopyToClipboard( button, codeEl.textContent || '' );
		} );
	} );

	// Step 2: wire the Playground textarea to an explicit "Update preview" button.
	var textarea = document.getElementById( 'jzsa-playground-shortcode' );
	var previewButton = null;

	if ( textarea ) {
		previewButton = document.createElement( 'button' );
		previewButton.type = 'button';
		previewButton.className = 'button button-primary jzsa-playground-run';
		previewButton.textContent = 'Update preview';

		// Insert the button just after the textarea.
		textarea.parentNode.insertBefore( previewButton, textarea.nextSibling );

		previewButton.addEventListener( 'click', function () {
			jzsaRunPlaygroundPreview();
		} );
	}
} );
