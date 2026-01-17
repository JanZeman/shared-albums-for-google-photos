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
 * Bind click handlers to all shortcode copy buttons on the settings page.
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
} );
