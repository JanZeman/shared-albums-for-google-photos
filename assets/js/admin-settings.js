/**
 * Admin Settings Page JavaScript
 *
 * @package JZSA_Shared_Albums
 * @since 1.0.0
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
