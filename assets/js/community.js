/**
 * Community Page JavaScript
 *
 * Handles: Browse entries, Sign in / Sign out, Publish, Delete entry,
 *          Delete community account.
 *
 * All write calls proxy through WP AJAX so the JWT stays server-side.
 *
 * @package JZSA_Shared_Albums
 */

( function () {
	'use strict';

	/* -----------------------------------------------------------------------
	 * State
	 * -------------------------------------------------------------------- */

	var currentPage = 1;
	var currentQuery = '';
	var currentSort = 'interactions';
	var totalPages = 1;

	/* Set of entry IDs that belong to the current user (populated by renderMyEntries).
	 * Used to disable self-rating on community browse cards. */
	var myEntryIds = new Set();

	/* -----------------------------------------------------------------------
	 * DOM helpers
	 * -------------------------------------------------------------------- */

	function qs( selector ) {
		return document.querySelector( selector );
	}

	function isCommunityConnected() {
		return jzsaCommunity.isConnected === true ||
			jzsaCommunity.isConnected === 'true' ||
			jzsaCommunity.isConnected === '1' ||
			jzsaCommunity.isConnected === 1;
	}

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function maskShortcodeAlbumLink( shortcode ) {
		return String( shortcode || '' ).replace(
			/(\blink\s*=\s*)(["'])(.*?)\2/i,
			'$1$2hidden-album-link$2'
		);
	}

	function i18n( key ) {
		return ( jzsaCommunity.i18n && jzsaCommunity.i18n[ key ] ) || '';
	}

	function showcaseRequiredMessage() {
		return i18n( 'showcaseRequiredMessage' ) ||
			'Sample page URL and entry info are required for the public site.';
	}

	function syncShowcaseRequiredState( consentEl, requiredControls, requiredBadges ) {
		var isRequired = !! ( consentEl && consentEl.checked );
		( requiredControls || [] ).forEach( function ( control ) {
			if ( ! control ) {
				return;
			}
			control.required = isRequired;
			control.setAttribute( 'aria-required', isRequired ? 'true' : 'false' );
		} );
		( requiredBadges || [] ).forEach( function ( badge ) {
			if ( badge ) {
				badge.hidden = ! isRequired;
			}
		} );
	}

	function getPublishShortcode() {
		var el = qs( '#jzsa-pub-shortcode' );
		if ( ! el ) {
			return '';
		}
		if ( typeof el.value === 'string' ) {
			return el.value;
		}
		return el.textContent || '';
	}

	function setPublishShortcode( shortcode ) {
		var el = qs( '#jzsa-pub-shortcode' );
		if ( ! el ) {
			return;
		}
		if ( typeof el.value === 'string' ) {
			el.value = shortcode;
			return;
		}
		el.textContent = shortcode;
		if ( typeof jzsaHighlightPlaceholders === 'function' ) {
			jzsaHighlightPlaceholders( el );
		}
		// Programmatic textContent changes do not fire 'input'; dispatch one so
		// the shared code-block validation refreshes for the new shortcode.
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	function normalizeUrlInput( value ) {
		value = String( value || '' ).trim();
		if ( value && ! /^https?:\/\//i.test( value ) ) {
			value = 'https://' + value;
		}
		return value;
	}

	function isValidHttpUrl( value ) {
		try {
			var parsed = new URL( value );
			return /^https?:$/i.test( parsed.protocol ) && parsed.hostname.indexOf( '.' ) !== -1;
		} catch ( e ) {
			return false;
		}
	}

	function formatDisplayUrl( value ) {
		return String( value || '' ).replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
	}

	function countLetters( value ) {
		var matches = String( value || '' ).match( /\p{L}/gu );
		return matches ? matches.length : 0;
	}

	function extractCommunityAlbumLink( shortcode ) {
		shortcode = String( shortcode || '' ).trim();
		if ( ! /^\[jzsa-album\b[^\]]*\]$/i.test( shortcode ) ) {
			return '';
		}
		var match = shortcode.match( /\blink\s*=\s*(["'])(https:\/\/photos\.google\.com\/share\/[A-Za-z0-9_-]+(?:\?key=[A-Za-z0-9_-]+)?)\1/i );
		return match ? match[ 2 ] : '';
	}

	function validateCommunityEntryFields( data ) {
		if ( ! data.title || data.title.length < 10 ) {
			return 'Title must be at least 10 characters.';
		}
		if ( data.title.length > 120 ) {
			return 'Title must be 120 characters or fewer.';
		}
		if ( ! data.shortcode || data.shortcode.length < 10 || data.shortcode.length > 2000 ) {
			return 'Shortcode must be a valid [jzsa-album] shortcode.';
		}
		if ( ! extractCommunityAlbumLink( data.shortcode ) ) {
			return 'Shortcode must include a valid Google Photos share URL in the link parameter.';
		}
		if ( ! data.description || data.description.length < 10 ) {
			return 'Description must be at least 10 characters.';
		}
		if ( data.description.length > 500 ) {
			return 'Description must be 500 characters or fewer.';
		}
		if ( data.siteUrl && ! isValidHttpUrl( data.siteUrl ) ) {
			return 'Please enter a valid sample page URL (e.g. https://yoursite.com/page).';
		}
		if ( data.entryInfo.length > 256 ) {
			return 'Entry info must be 256 characters or fewer.';
		}
		var tags = data.tags ? data.tags.split( /[\s,]+/ ).map( function ( tag ) {
			return tag.trim();
		} ).filter( Boolean ) : [];
		if ( tags.length < 1 ) {
			return 'Add at least one tag.';
		}
		if ( tags.length > 5 ) {
			return 'Use no more than 5 tags.';
		}
		for ( var i = 0; i < tags.length; i++ ) {
			if ( ! /^[a-z0-9][a-z0-9-]{1,29}$/i.test( tags[ i ] ) ) {
				return 'Tags must be 2-30 characters and use only letters, numbers, and hyphens.';
			}
		}
		if ( data.showcaseConsent && ( ! data.siteUrl || ! data.entryInfo ) ) {
			return showcaseRequiredMessage();
		}
		return '';
	}

	/* -----------------------------------------------------------------------
	 * WP AJAX wrapper
	 * -------------------------------------------------------------------- */

	/**
	 * Send a POST request to admin-ajax.php.
	 *
	 * @param {string} action  WP AJAX action name.
	 * @param {Object} data    Extra POST fields.
	 * @param {Object} options Fetch options.
	 * @return {Promise<Object>} Parsed JSON response.
	 */
	function ajaxPost( action, data, options ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', jzsaCommunity.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			var val = data[ key ];
			if ( Array.isArray( val ) ) {
				val.forEach( function ( v ) {
					body.append( key + '[]', v );
				} );
			} else {
				body.append( key, val );
			}
		} );

		var fetchOptions = { method: 'POST', body: body };
		if ( options && options.keepalive ) {
			fetchOptions.keepalive = true;
		}

		return fetch( jzsaCommunity.ajaxUrl, fetchOptions )
			.then( function ( r ) {
				return r.clone().text().then( function ( raw ) {
					var parsed;
					try {
						parsed = JSON.parse( raw );
					} catch ( e ) {
						console.error( '[JZSA community] Non-JSON response for action "' + action + '":', raw.substring( 0, 500 ) );
						throw e;
					}
					if ( parsed && ! parsed.success ) {
						console.warn( '[JZSA community] action "' + action + '" returned error:', parsed.data );
					}
					return parsed;
				} );
			} );
	}

	/* -----------------------------------------------------------------------
	 * Entry block builder - used for both Community and My Shortcodes
	 * -------------------------------------------------------------------- */

	var ACTION_POINTS = {
		copy: 10,
		apply: 10,
		revert: 5,
		fullscreen_open: 5,
		click_link_button: 5,
		click_download_button: 5,
		fullscreen_next: 1,
		photo_next: 1,
	};
	var BATCHED_INTERACTION_SIZE = 5;
	var BATCHED_INTERACTION_ACTIONS = {
		fullscreen_next: true,
		photo_next: true,
	};
	var interactionBatches = {};

	function bumpScore( block, points ) {
		if ( ! block || points <= 0 ) {
			return;
		}
		var scoreEl = block.querySelector( '.jzsa-community-entry-score' );
		if ( scoreEl ) {
			var newScore = ( parseInt( scoreEl.getAttribute( 'data-score' ) || '0', 10 ) ) + points;
			scoreEl.setAttribute( 'data-score', String( newScore ) );
			scoreEl.textContent = newScore + ( newScore === 1 ? ' interaction point' : ' interaction points' );
		} else {
			var footer = block.querySelector( '.jzsa-community-entry-footer' );
			if ( footer ) {
				var newScoreEl = document.createElement( 'span' );
				newScoreEl.className = 'jzsa-community-entry-score';
				newScoreEl.setAttribute( 'data-score', String( points ) );
				newScoreEl.textContent = points + ( points === 1 ? ' interaction point' : ' interaction points' );
				newScoreEl.title = 'Based on copy, apply, revert and preview interactions';
				footer.insertBefore( newScoreEl, footer.firstChild );
			}
		}
	}

	function sendInteraction( entryId, actionType, count, keepalive ) {
		if ( ! entryId ) {
			return;
		}
		count = Math.max( 1, parseInt( count || '1', 10 ) || 1 );
		ajaxPost( 'jzsa_community_interact', {
			entry_id: entryId,
			action_type: actionType,
			count: count,
		}, { keepalive: !! keepalive } ).catch( function () {} );
	}

	function queueInteraction( entryId, actionType ) {
		if ( ! BATCHED_INTERACTION_ACTIONS[ actionType ] ) {
			sendInteraction( entryId, actionType, 1 );
			return;
		}

		var key = entryId + ':' + actionType;
		interactionBatches[ key ] = ( interactionBatches[ key ] || 0 ) + 1;

		while ( interactionBatches[ key ] >= BATCHED_INTERACTION_SIZE ) {
			sendInteraction( entryId, actionType, BATCHED_INTERACTION_SIZE );
			interactionBatches[ key ] -= BATCHED_INTERACTION_SIZE;
		}
	}

	function flushInteractionBatches() {
		Object.keys( interactionBatches ).forEach( function ( key ) {
			var count = interactionBatches[ key ];
			if ( count <= 0 ) {
				return;
			}
			var parts = key.split( ':' );
			sendInteraction( parts[ 0 ], parts[ 1 ], count, true );
			interactionBatches[ key ] = 0;
		} );
	}

	window.addEventListener( 'beforeunload', flushInteractionBatches );
	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'hidden' ) {
			flushInteractionBatches();
		}
	} );

	function updateStarDisplay( starsEl, avgRating, userRating ) {
		if ( ! starsEl ) {
			return;
		}
		var filled = ( avgRating !== null && avgRating !== undefined )
			? Math.round( Number( avgRating ) ) : 0;
		starsEl.querySelectorAll( '.jzsa-star' ).forEach( function ( star, i ) {
			var val = i + 1;
			star.classList.toggle( 'is-filled', val <= filled );
			star.classList.toggle( 'is-mine',
				userRating !== null && userRating !== undefined && val === Number( userRating ) );
		} );
		if ( userRating ) {
			starsEl.setAttribute( 'data-user-rating', String( userRating ) );
		}
	}

	function sendRating( entryId, rating, starsEl, ratingCountEl ) {
		ajaxPost( 'jzsa_community_rate', {
			entry_id: entryId,
			rating: rating,
		} ).then( function ( res ) {
			if ( res.success && res.data ) {
				updateStarDisplay( starsEl, res.data.avg_rating, rating );
				if ( ratingCountEl ) {
					var avg = res.data.avg_rating
						? Number( res.data.avg_rating ).toFixed( 1 ) : '';
					var cnt = Number( res.data.rating_count ) || 0;
					ratingCountEl.textContent = avg
						? avg + ' \u2605 (' + cnt + ')'
						: cnt + ( cnt === 1 ? ' rating' : ' ratings' );
				}
			}
		} ).catch( function () {} );
	}

	function showRatingTooltip( starsEl, message ) {
		var wrap = starsEl.closest( '.jzsa-community-entry-rating' );
		if ( ! wrap ) { return; }
		var existing = wrap.querySelector( '.jzsa-rating-tooltip' );
		if ( existing ) { existing.remove(); }
		var tip = document.createElement( 'span' );
		tip.className = 'jzsa-rating-tooltip';
		tip.textContent = message;
		wrap.appendChild( tip );
		setTimeout( function () { tip.remove(); }, 2500 );
	}

	// Opens the community account <details> section, scrolls it into view, and
	// focuses the email input. Invoked from the inline "Sign in to rate" hint
	// and from the disconnected click on the rating stars.
	function focusSignInForm() {
		var section = document.querySelector( '.jzsa-community-account-section' );
		if ( section && section.tagName === 'DETAILS' && ! section.open ) {
			section.open = true;
		}
		var emailEl = document.getElementById( 'jzsa-connect-email' );
		var target  = emailEl || section;
		if ( target && target.scrollIntoView ) {
			target.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
		if ( emailEl ) {
			setTimeout( function () { emailEl.focus(); }, 300 );
		}
	}

	/**
	 * Build a full jzsa-sample entry block (DOM element, not HTML string).
	 * Includes h3 title, meta/tags, description, code block, lazy preview.
	 * For My Shortcodes entries also adds Delete + Save buttons.
	 *
	 * @param {Object}  entry      Entry data from the API.
	 * @param {boolean} isMyEntry  Whether this belongs to the current user.
	 * @return {HTMLElement}
	 */
	function buildEntryBlock( entry, isMyEntry ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'jzsa-sample-card jzsa-community-entry' + ( isMyEntry ? ' jzsa-community-my-entry' : '' );
		wrap.setAttribute( 'data-entry-id', String( entry.id ) );
		var hue = Math.floor( Math.random() * 360 );
		wrap.style.backgroundColor = 'hsl(' + hue + ', 55%, 95%)';
		if ( ! isMyEntry ) {
			// no-op - keep the block for future my-entry-specific styles
		}

		// ---- Header row: title (+ inline tags) + community metadata ----
		var header = document.createElement( 'div' );
		header.className = 'jzsa-community-entry-header';

		var entryTags = Array.isArray( entry.tags )
			? entry.tags
			: ( entry.tags ? String( entry.tags ).split( ',' ).map( function ( t ) { return t.trim(); } ).filter( Boolean ) : [] );

		var titleEl = document.createElement( 'h3' );
		titleEl.textContent = entry.title;
		if ( ! isMyEntry && entryTags.length ) {
			var tagsInline = document.createElement( 'span' );
			tagsInline.className = 'jzsa-community-entry-tags-inline';
			tagsInline.textContent = '(' + entryTags.join( ', ' ) + ')';
			titleEl.appendChild( document.createTextNode( ' ' ) );
			titleEl.appendChild( tagsInline );
		}

		if ( ! isMyEntry ) {
			var headerLeft = document.createElement( 'div' );
			headerLeft.className = 'jzsa-community-entry-header-left';
			headerLeft.appendChild( titleEl );
			if ( entry.description ) {
				var descEl = document.createElement( 'p' );
				descEl.className = 'jzsa-community-entry-description';
				descEl.textContent = entry.description;
				headerLeft.appendChild( descEl );
			}
			header.appendChild( headerLeft );
		} else {
			header.appendChild( titleEl );
		}

		if ( ! isMyEntry ) {
			var authorName = ( entry.author && entry.author.display_name ) ? entry.author.display_name : 'Anonymous';
			var authorEl = document.createElement( 'span' );
			authorEl.className = 'jzsa-community-entry-author';

			var authorLabel = document.createTextNode( 'Author: ' + authorName );
			authorEl.appendChild( authorLabel );

			var authorUrl = entry.site_url || '';
			if ( authorUrl ) {
				var entrySep = document.createTextNode( ' · ' );
				var entryLink = document.createElement( 'a' );
				entryLink.href = authorUrl;
				entryLink.target = '_blank';
				entryLink.rel = 'noopener noreferrer';
				entryLink.textContent = formatDisplayUrl( authorUrl );
				entryLink.className = 'jzsa-community-entry-site-link';
				authorEl.appendChild( entrySep );
				authorEl.appendChild( entryLink );
			}

			var headerRight = document.createElement( 'div' );
			headerRight.className = 'jzsa-community-entry-header-right';
			headerRight.appendChild( authorEl );

			var statsLine = document.createElement( 'div' );
			statsLine.className = 'jzsa-community-entry-footer';

			var score = Number( entry.interaction_score ) || 0;
			var scoreEl = document.createElement( 'span' );
			scoreEl.className = 'jzsa-community-entry-score';
			scoreEl.setAttribute( 'data-score', String( score ) );
			scoreEl.textContent = score + ( score === 1 ? ' interaction point' : ' interaction points' );
			scoreEl.title = 'Based on copy, apply, revert and preview interactions';

			var ratingWrap = document.createElement( 'span' );
			ratingWrap.className = 'jzsa-community-entry-rating';

			var starsEl = document.createElement( 'span' );
			var isOwnEntry = myEntryIds.has( String( entry.id ) );
			if ( isOwnEntry ) { ratingWrap.classList.add( 'is-own' ); }
			// Stars are visually disabled both for own entries and for
			// signed-out visitors (avoids pretending the click will record a
			// rating). The dedicated "Sign in to rate" row below is the
			// primary CTA; clicking the grayed stars also routes there as a
			// fallback (see ratingWrapEl and starsEl click handlers).
			starsEl.className = 'jzsa-community-entry-stars'
				+ ( ( isOwnEntry || ! isCommunityConnected() ) ? ' is-disabled' : '' );
			if ( isOwnEntry ) {
				starsEl.title = 'You cannot rate your own sample';
			} else if ( ! isCommunityConnected() ) {
				starsEl.title = 'Sign in to rate this sample';
			}
			for ( var si = 1; si <= 5; si++ ) {
				var starBtn = document.createElement( 'button' );
				starBtn.type = 'button';
				starBtn.className = 'jzsa-star';
				starBtn.setAttribute( 'data-value', String( si ) );
				starBtn.setAttribute( 'aria-label', si + ' star' );
				starBtn.textContent = '\u2605';
				starsEl.appendChild( starBtn );
			}
			updateStarDisplay( starsEl, entry.avg_rating, null );

			var ratingCount = document.createElement( 'span' );
			ratingCount.className = 'jzsa-community-entry-rating-count';
			var rCount = Number( entry.rating_count ) || 0;
			if ( isOwnEntry ) {
				var rAvgOwn = entry.avg_rating ? Number( entry.avg_rating ).toFixed( 1 ) : '';
				ratingCount.textContent = rAvgOwn
					? rAvgOwn + ' \u2605 (' + rCount + ')'
					: rCount > 0 ? rCount + ( rCount === 1 ? ' rating' : ' ratings' ) : 'No ratings yet';
			} else if ( rCount > 0 ) {
				var rAvg = entry.avg_rating ? Number( entry.avg_rating ).toFixed( 1 ) : '';
				ratingCount.textContent = rAvg
					? rAvg + ' \u2605 (' + rCount + ')'
					: rCount + ( rCount === 1 ? ' rating' : ' ratings' );
			} else if ( isCommunityConnected() ) {
				// Signed in and viewing someone else's entry with no ratings yet.
				// Frame it as an invitation instead of a status report.
				ratingCount.textContent = 'Be the first to rate';
			} else {
				ratingCount.textContent = 'No ratings yet';
			}

			ratingWrap.appendChild( starsEl );
			ratingWrap.appendChild( ratingCount );
			statsLine.appendChild( scoreEl );
			statsLine.appendChild( ratingWrap );
			headerRight.appendChild( statsLine );

			// Anonymous-visitor cue on its own row beneath the stars. Hidden
			// for signed-in users and for own entries. Clicking opens the
			// account section and focuses the email input.
			if ( ! isCommunityConnected() && ! isOwnEntry ) {
				var signinHintRow = document.createElement( 'div' );
				signinHintRow.className = 'jzsa-community-entry-signin-hint-row';
				signinHintRow.style.cssText = 'text-align:right; margin-top:2px;';
				var signinHint = document.createElement( 'button' );
				signinHint.type = 'button';
				signinHint.className = 'jzsa-community-rating-signin-hint';
				signinHint.textContent = 'Sign in to rate';
				signinHint.style.cssText = 'background:none; border:0; padding:0; margin:0; font-size:12px; font-style:italic; color:#2271b1; cursor:pointer; text-decoration:underline;';
				signinHint.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					focusSignInForm();
				} );
				signinHintRow.appendChild( signinHint );
				headerRight.appendChild( signinHintRow );
			}

			header.appendChild( headerRight );
		}

		wrap.appendChild( header );

		if ( ! isMyEntry ) {
			// ---- Code block ----
			var codeBlock = document.createElement( 'div' );
			codeBlock.className = 'jzsa-code-block';
			var codeEl = document.createElement( 'code' );
			codeEl.textContent = maskShortcodeAlbumLink( entry.shortcode );
			codeBlock.appendChild( codeEl );
			wrap.appendChild( codeBlock );

			// ---- Lazy preview container ----
			var preview = document.createElement( 'div' );
			preview.className = 'jzsa-preview-container jzsa-lazy-preview';
			var previewShortcode = entry.preview_shortcode || entry.shortcode;
			codeEl.dataset.revertShortcode = previewShortcode;
			if ( typeof jzsaSetLazyPreviewShortcode === 'function' ) {
				jzsaSetLazyPreviewShortcode( preview, previewShortcode );
			} else {
				preview.jzsaInitialShortcode = previewShortcode;
			}
			preview.setAttribute( 'data-lazy-state', 'pending' );
			wrap.appendChild( preview );
		} else {
			// ---- Edit form (my entries only) ----
			var saveRow = document.createElement( 'div' );
			saveRow.className = 'jzsa-community-my-entry-save-row';
			var editTable = document.createElement( 'table' );
			editTable.className = 'form-table jzsa-community-publish-table jzsa-community-my-entry-edit-table';
			var editBody = document.createElement( 'tbody' );
			editTable.appendChild( editBody );

			function appendTableRow( labelEl, controlEls, options ) {
				var row = document.createElement( 'tr' );
				var th = document.createElement( 'th' );
				th.scope = 'row';
				var td = document.createElement( 'td' );
				( controlEls || [] ).forEach( function ( el ) {
					td.appendChild( el );
				} );
				if ( labelEl ) {
					th.appendChild( labelEl );
					row.appendChild( th );
					row.appendChild( td );
				} else {
					td.colSpan = 2;
					if ( options && options.cellClass ) {
						td.className = options.cellClass;
					}
					row.appendChild( td );
				}
				editBody.appendChild( row );
			}

			function createLabel( htmlFor, text, requiredText ) {
				var label = document.createElement( 'label' );
				label.htmlFor = htmlFor;
				label.textContent = text;
				if ( requiredText ) {
					var badge = document.createElement( 'span' );
					badge.className = requiredText === 'showcase' ? 'required jzsa-showcase-required-badge' : 'required';
					if ( requiredText === 'showcase' ) {
						badge.hidden = true;
						badge.setAttribute( 'aria-label', 'required for the public site' );
						badge.textContent = i18n( 'showcaseRequiredBadge' ) || 'Required for the public site';
					} else {
						badge.setAttribute( 'aria-label', 'required' );
						badge.textContent = 'Required';
					}
					label.appendChild( badge );
				}
				return label;
			}

			function createConsentBlock( checkboxId ) {
				var wrapEl = document.createElement( 'div' );
				wrapEl.className = 'jzsa-community-my-entry-consent-block';
				var label = document.createElement( 'label' );
				label.style.display = 'flex';
				label.style.alignItems = 'center';
				label.style.gap = '8px';
				var checkbox = document.createElement( 'input' );
				checkbox.type = 'checkbox';
				checkbox.id = checkboxId;
				checkbox.className = 'jzsa-community-my-entry-consent-checkbox';
				checkbox.checked = entry.public_showcase_consent ? true : false;
				var text = document.createElement( 'span' );
				text.textContent = i18n( 'showcaseConsentLabel' );
				label.appendChild( checkbox );
				label.appendChild( text );
					var help = document.createElement( 'p' );
					help.className = 'description';
					help.style.flex = '1';
					help.style.margin = '0';
					help.textContent = i18n( 'showcaseConsentHelp' );
					var helpIcon = document.createElement( 'span' );
					helpIcon.className = 'dashicons dashicons-admin-site-alt3';
					helpIcon.style.color = '#d97706';
					helpIcon.style.marginTop = '2px';
					helpIcon.setAttribute( 'aria-hidden', 'true' );
					var helpBox = document.createElement( 'div' );
					helpBox.className = 'jzsa-community-showcase-scope-warning';
					helpBox.style.display = 'flex';
					helpBox.style.gap = '10px';
					helpBox.style.alignItems = 'flex-start';
					helpBox.style.padding = '10px 12px';
					helpBox.style.marginTop = '8px';
					helpBox.style.marginBottom = '12px';
					helpBox.style.marginLeft = '28px';
					helpBox.style.borderLeft = '4px solid #d97706';
					helpBox.style.background = '#fffbeb';
					helpBox.style.borderRadius = '3px';
					helpBox.appendChild( helpIcon );
					helpBox.appendChild( help );
					wrapEl.appendChild( label );
					wrapEl.appendChild( helpBox );
					return { wrapper: wrapEl, checkbox: checkbox };
				}

			var titleInput = document.createElement( 'input' );
			titleInput.type = 'text';
			titleInput.className = 'regular-text jzsa-community-my-entry-title-input';
			titleInput.id = 'jzsa-my-entry-title-' + entry.id;
			titleInput.maxLength = 120;
			titleInput.value = entry.title || '';
			appendTableRow(
				createLabel( titleInput.id, 'Title', 'required' ),
				[ titleInput ]
			);

			var ownedCodeBlock = document.createElement( 'div' );
			ownedCodeBlock.className = 'jzsa-code-block jzsa-community-publish-shortcode-block';
			var ownedCodeEl = document.createElement( 'code' );
			ownedCodeEl.id = 'jzsa-my-entry-shortcode-' + entry.id;
			ownedCodeEl.textContent = entry.shortcode;
			ownedCodeBlock.appendChild( ownedCodeEl );
			var shortcodeHelp = document.createElement( 'p' );
			shortcodeHelp.className = 'description';
			shortcodeHelp.textContent = 'Edit your shortcode below and click the Apply button to preview your changes.';
			var shortcodeDestinationHelp = document.createElement( 'p' );
			shortcodeDestinationHelp.className = 'description';
			shortcodeDestinationHelp.textContent = 'This shortcode sample is always shared on the Plugin page. Leave the public site showcase option enabled only if this same entry may also be considered for the future public website.';
			var shortcodePrivacy = document.createElement( 'p' );
			shortcodePrivacy.className = 'description';
			shortcodePrivacy.textContent = 'Privacy note: Published shortcodes show link="hidden-album-link" instead of the real album link. The unmasked link is kept only for editing and preview rendering.';
			var ownedPreview = document.createElement( 'div' );
			ownedPreview.className = 'jzsa-preview-container jzsa-lazy-preview jzsa-community-publish-preview';
			if ( typeof jzsaSetLazyPreviewShortcode === 'function' ) {
				jzsaSetLazyPreviewShortcode( ownedPreview, entry.shortcode );
			} else {
				ownedPreview.jzsaInitialShortcode = entry.shortcode;
			}
			ownedPreview.setAttribute( 'data-lazy-state', 'pending' );
			appendTableRow(
				createLabel( ownedCodeEl.id, 'Shortcode', 'required' ),
				[ shortcodeHelp, shortcodeDestinationHelp, shortcodePrivacy, ownedCodeBlock, ownedPreview ]
			);

			var descriptionInput = document.createElement( 'textarea' );
			descriptionInput.className = 'large-text jzsa-community-my-entry-description-input';
			descriptionInput.id = 'jzsa-my-entry-description-' + entry.id;
			descriptionInput.maxLength = 500;
			descriptionInput.rows = 2;
			descriptionInput.value = entry.description || '';
			var descriptionLabel = createLabel( descriptionInput.id, i18n( 'descriptionLabel' ) || 'Description', 'required' );
			appendTableRow( descriptionLabel, [ descriptionInput ] );

			var tagsInput = document.createElement( 'input' );
			tagsInput.type = 'text';
			tagsInput.className = 'regular-text jzsa-community-my-entry-tags-input';
			tagsInput.id = 'jzsa-my-entry-tags-' + entry.id;
			tagsInput.value = entryTags.join( ', ' );
			tagsInput.placeholder = 'slider, dark, mosaic  (comma or space separated, min 1, max 5)';
			appendTableRow(
				createLabel( tagsInput.id, 'Tags', 'required' ),
				[ tagsInput ]
			);

			var urlInput = document.createElement( 'input' );
			urlInput.type = 'url';
			urlInput.className = 'regular-text jzsa-community-my-entry-site-url-input';
			urlInput.id = 'jzsa-my-entry-site-url-' + entry.id;
			urlInput.maxLength = 2048;
			urlInput.placeholder = 'https://yoursite.com/page-with-album';
			urlInput.value = entry.site_url || '';
			var urlRequiredHelp = document.createElement( 'p' );
			urlRequiredHelp.className = 'description';
			urlRequiredHelp.textContent = 'A link to the gallery page on your site. The future public site showcase will feature this link prominently so visitors can see the full context on your site and potentially send you more traffic.';
			var urlLabel = createLabel( urlInput.id, i18n( 'siteUrlLabel' ) || 'Sample page URL', 'showcase' );
			var urlRequiredBadge = urlLabel.querySelector( '.jzsa-showcase-required-badge' );
			appendTableRow( urlLabel, [ urlInput, urlRequiredHelp ] );

			var bioInput = document.createElement( 'textarea' );
			bioInput.className = 'large-text jzsa-community-my-entry-info-input';
			bioInput.id = 'jzsa-my-entry-info-' + entry.id;
			bioInput.maxLength = 256;
			bioInput.rows = 2;
			bioInput.value = entry.entry_info || '';
			var bioHelp = document.createElement( 'p' );
			bioHelp.className = 'description';
			bioHelp.textContent = i18n( 'entryInfoHelp' );
			appendTableRow(
				createLabel( bioInput.id, i18n( 'entryInfoLabel' ), 'showcase' ),
				[ bioInput, bioHelp ]
			);
			var bioRequiredBadge = editTable.querySelector( 'label[for="' + bioInput.id + '"] .jzsa-showcase-required-badge' );

			saveRow.appendChild( editTable );

			var consentBlock = createConsentBlock( 'jzsa-my-entry-consent-' + entry.id );
			consentBlock.wrapper.className += ' jzsa-community-showcase-consent-cell';
			saveRow.appendChild( consentBlock.wrapper );

			function syncOwnedShowcaseConsent( checked ) {
				consentBlock.checkbox.checked   = checked;
				syncShowcaseRequiredState(
					consentBlock.checkbox,
					[ urlInput, bioInput ],
					[ urlRequiredBadge, bioRequiredBadge ]
				);
			}
			syncOwnedShowcaseConsent( entry.public_showcase_consent ? true : false );
			consentBlock.checkbox.addEventListener( 'change', function () {
				syncOwnedShowcaseConsent( consentBlock.checkbox.checked, { fromUserAction: true } );
			} );

			var saveBtn = document.createElement( 'button' );
			saveBtn.type = 'button';
			saveBtn.className = 'button button-primary jzsa-community-save-entry-btn';
			saveBtn.textContent = 'Save changes';
			var deleteBtn = document.createElement( 'button' );
			deleteBtn.type = 'button';
			deleteBtn.className = 'button button-link-delete jzsa-community-delete-entry-btn';
			deleteBtn.textContent = 'Delete';
			var saveResult = document.createElement( 'span' );
			saveResult.className = 'jzsa-community-result';
			saveResult.setAttribute( 'aria-live', 'polite' );
			var actionRow = document.createElement( 'p' );
			actionRow.className = 'jzsa-community-my-entry-actions';
			actionRow.appendChild( saveBtn );
			actionRow.appendChild( deleteBtn );
			actionRow.appendChild( saveResult );
			saveRow.appendChild( actionRow );
			wrap.appendChild( saveRow );
		}

		return wrap;
	}

	/**
	 * Wire a newly built entry block: set up code block (copy/apply/revert),
	 * observe lazy preview, and attach delete + save handlers for My Shortcodes.
	 *
	 * @param {HTMLElement} block      The entry block element.
	 * @param {HTMLElement} container  Parent container (for empty-state update on delete).
	 */
	function initEntryBlock( block, container ) {
		// Code block: copy / apply / revert
		var entryId = block.getAttribute( 'data-entry-id' );
		var isOwned = block.classList.contains( 'jzsa-community-my-entry' );
		var codeBlock = block.querySelector( '.jzsa-code-block' );
		if ( codeBlock && typeof jzsaSetupCodeBlock === 'function' ) {
			jzsaSetupCodeBlock( codeBlock );
			// Track copy/apply/revert for non-owned community entries
			if ( entryId && ! isOwned ) {
				var btnCol = codeBlock.querySelector( '.jzsa-code-block-btns' );
				if ( btnCol ) {
					var actionMap = { Copy: 'copy', Apply: 'apply', Revert: 'revert' };
					btnCol.querySelectorAll( '.jzsa-action-btn' ).forEach( function ( btn ) {
						var action = actionMap[ ( btn.textContent || '' ).trim() ];
						if ( action ) {
							btn.addEventListener( 'click', function () {
								if ( myEntryIds.has( entryId ) ) { return; }
								bumpScore( block, ACTION_POINTS[ action ] || 0 );
								queueInteraction( entryId, action );
							} );
						}
					} );
				}
			}
		}

		// Lazy preview - register with the shared IntersectionObserver
		var previewEl = block.querySelector( '.jzsa-lazy-preview' );
		if ( previewEl && typeof jzsaObserveLazyPreview === 'function' ) {
			jzsaObserveLazyPreview( previewEl );
			// Track fullscreen, link/download button, and navigation interactions
			if ( entryId && ! isOwned ) {
				// Use capture:true so Swiper's own stopPropagation on nav buttons doesn't block us
				previewEl.addEventListener( 'click', function ( e ) {
					if ( myEntryIds.has( entryId ) ) { return; }
					var t = e.target;
					while ( t && t !== previewEl ) {
						if ( t.classList.contains( 'swiper-button-fullscreen' ) ) {
							bumpScore( block, ACTION_POINTS.fullscreen_open );
							queueInteraction( entryId, 'fullscreen_open' );
							return;
						}
						if ( t.classList.contains( 'swiper-button-external-link' ) ) {
							bumpScore( block, ACTION_POINTS.click_link_button );
							queueInteraction( entryId, 'click_link_button' );
							return;
						}
						if ( t.classList.contains( 'swiper-button-download' ) ) {
							bumpScore( block, ACTION_POINTS.click_download_button );
							queueInteraction( entryId, 'click_download_button' );
							return;
						}
						if (
							t.classList.contains( 'swiper-button-next' ) ||
							t.classList.contains( 'swiper-button-prev' )
						) {
							var act = document.fullscreenElement ? 'fullscreen_next' : 'photo_next';
							bumpScore( block, ACTION_POINTS[ act ] );
							queueInteraction( entryId, act );
							return;
						}
						t = t.parentElement;
					}
				}, true );
			}
		}

		// ---- Delete (My Shortcodes only) ----
		var deleteBtn = block.querySelector( '.jzsa-community-delete-entry-btn' );
		if ( deleteBtn ) {
			deleteBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Delete this shortcode? This cannot be undone.' ) ) {
					return;
				}
				var entryId = block.getAttribute( 'data-entry-id' );
				deleteBtn.disabled = true;

				ajaxPost( 'jzsa_community_delete_entry', { entry_id: entryId } )
					.then( function ( res ) {
						if ( res.success ) {
							block.remove();
							if ( container && ! container.querySelector( '.jzsa-community-my-entry' ) ) {
								container.innerHTML = '<p class="jzsa-community-empty">You haven\u2019t shared any shortcodes yet.</p>';
							}
							// Refresh community browse so the deleted entry disappears
							currentPage = 1;
							loadEntries();
						} else {
							deleteBtn.disabled = false;
							window.alert( res.data || 'Could not delete.' );
						}
					} )
					.catch( function () {
						deleteBtn.disabled = false;
						window.alert( 'Could not reach the server.' );
					} );
			} );
		}

		// ---- Save shortcode (My Shortcodes only) ----
		var saveBtn   = block.querySelector( '.jzsa-community-save-entry-btn' );
		var resultEl  = block.querySelector( '.jzsa-community-my-entry-save-row .jzsa-community-result' );
		var codeEl    = block.querySelector( '.jzsa-code-block code' );
		var titleInput = block.querySelector( '.jzsa-community-my-entry-title-input' );
		var descInput = block.querySelector( '.jzsa-community-my-entry-description-input' );
		var tagsInput = block.querySelector( '.jzsa-community-my-entry-tags-input' );
		var urlInput  = block.querySelector( '.jzsa-community-my-entry-site-url-input' );
		var bioInput = block.querySelector( '.jzsa-community-my-entry-info-input' );
		var consentCheckbox = block.querySelector( '.jzsa-community-my-entry-consent-checkbox' );
		if ( urlInput ) {
			urlInput.addEventListener( 'blur', function () {
				var val = urlInput.value.trim();
				if ( val && ! /^https?:\/\//i.test( val ) ) {
					urlInput.value = 'https://' + val;
				}
			} );
		}
		if ( saveBtn && codeEl ) {
			saveBtn.addEventListener( 'click', function () {
				var title = titleInput ? titleInput.value.trim() : '';
				var shortcode = ( codeEl.textContent || '' ).trim();
				var entryId = block.getAttribute( 'data-entry-id' );
				var tags = tagsInput ? tagsInput.value.trim() : '';
				var siteUrl = normalizeUrlInput( urlInput ? urlInput.value : '' );
				var showcaseConsent = consentCheckbox ? consentCheckbox.checked : false;
				var description = descInput ? descInput.value.trim() : '';
				var entryInfo = bioInput ? bioInput.value.trim() : '';
				var validationError = validateCommunityEntryFields( {
					title: title,
					shortcode: shortcode,
					description: description,
					tags: tags,
					siteUrl: siteUrl,
					entryInfo: entryInfo,
					showcaseConsent: showcaseConsent,
				} );
				if ( validationError ) {
					setResult( resultEl, validationError, false );
					return;
				}
				saveBtn.disabled = true;
				saveBtn.textContent = 'Saving\u2026';
				setResult( resultEl, '', null );

				ajaxPost( 'jzsa_community_update_entry', {
					entry_id: entryId,
					title: title,
					shortcode: shortcode,
					description: description,
					tags: tags,
					site_url: siteUrl,
					entry_info: entryInfo,
					public_showcase_consent: showcaseConsent,
				} )
					.then( function ( res ) {
						saveBtn.disabled = false;
						saveBtn.textContent = 'Save changes';
						if ( res.success ) {
							var titleEl = block.querySelector( '.jzsa-community-entry-header h3' );
							if ( titleEl ) {
								titleEl.textContent = title;
							}
							setResult( resultEl, '\u2705 Saved!', true );
							// Refresh community browse so updated shortcode is visible
							currentPage = 1;
							loadEntries();
						} else {
							setResult( resultEl, '\u274c ' + formatPublishError( res.data ), false );
						}
					} )
					.catch( function () {
						saveBtn.disabled = false;
						saveBtn.textContent = 'Save changes';
						setResult( resultEl, '\u274c Could not reach the server.', false );
					} );
			} );
		}

		// ---- Star rating init (community entries only) ----
		var starsEl = block.querySelector( '.jzsa-community-entry-stars' );
		var ratingCountEl = block.querySelector( '.jzsa-community-entry-rating-count' );
		if ( starsEl ) {
			var entryIdForRating = block.getAttribute( 'data-entry-id' );
			var ratingWrapEl = starsEl.closest( '.jzsa-community-entry-rating' );
			if ( ratingWrapEl ) {
				ratingWrapEl.addEventListener( 'click', function ( event ) {
					if ( ! isCommunityConnected() ) {
						// Don't hijack clicks on the inline "Sign in to rate"
						// affordance - it has its own handler that already
						// scrolls + focuses, and we want the default action
						// (focus) to land naturally.
						if ( event.target.closest( '.jzsa-community-rating-signin-hint' ) ) {
							return;
						}
						event.preventDefault();
						event.stopPropagation();
						focusSignInForm();
					}
				}, true );
			}
			starsEl.addEventListener( 'mouseleave', function () {
				starsEl.querySelectorAll( '.jzsa-star' ).forEach( function ( s ) {
					s.classList.remove( 'is-hovered' );
				} );
			} );
			starsEl.addEventListener( 'click', function ( event ) {
				var clickedStar = event.target.closest( '.jzsa-star' );
				if ( ! clickedStar || ! starsEl.contains( clickedStar ) ) {
					return;
				}
				if ( ! isCommunityConnected() ) {
					focusSignInForm();
					return;
				}
				if ( myEntryIds.has( entryIdForRating ) ) {
					showRatingTooltip( starsEl, 'You cannot rate your own sample' );
					return;
				}
				if ( starsEl.classList.contains( 'is-disabled' ) ) { return; }
				var val = parseInt( clickedStar.getAttribute( 'data-value' ), 10 );
				sendRating( entryIdForRating, val, starsEl, ratingCountEl );
			} );
			starsEl.querySelectorAll( '.jzsa-star' ).forEach( function ( star ) {
				star.addEventListener( 'mouseenter', function () {
					if ( starsEl.classList.contains( 'is-disabled' ) ) { return; }
					var val = parseInt( star.getAttribute( 'data-value' ), 10 );
					starsEl.querySelectorAll( '.jzsa-star' ).forEach( function ( s, i ) {
						s.classList.toggle( 'is-hovered', i < val );
					} );
				} );
			} );
		}
	}

	/* -----------------------------------------------------------------------
	 * Browse / entry cards
	 * -------------------------------------------------------------------- */

	function renderEntries( data ) {
		var container = qs( '#jzsa-community-entries' );
		var pagination = qs( '#jzsa-community-pagination' );

		if ( ! container ) {
			return;
		}

		// Update the summary count badge
		var countBadge = qs( '#jzsa-community-entries-count' );
		if ( countBadge ) {
			var total = data && data.meta && data.meta.total ? data.meta.total : 0;
			if ( total > 0 ) {
				countBadge.textContent = total + ' published';
				countBadge.style.display = '';
			} else {
				countBadge.textContent = '';
				countBadge.style.display = 'none';
			}
		}

		if ( ! data || ! data.data || data.data.length === 0 ) {
			container.innerHTML = '<p class="jzsa-community-empty">No community shortcodes found yet. Be the first to share!</p>';
			if ( pagination ) {
				pagination.innerHTML = '';
			}
			return;
		}

		container.innerHTML = '';

		data.data.forEach( function ( entry ) {
			var block = buildEntryBlock( entry, false );
			container.appendChild( block );
			initEntryBlock( block, container );
		} );

		if ( pagination ) {
			totalPages = data.meta ? Math.ceil( data.meta.total / 12 ) : 1;
			renderPagination( pagination );
		}
	}

	function renderPagination( container ) {
		if ( totalPages <= 1 ) {
			container.innerHTML = '';
			return;
		}

		var html = '';

		if ( currentPage > 1 ) {
			html += '<button type="button" class="button jzsa-community-page-btn" data-page="' + ( currentPage - 1 ) + '">&larr; Previous</button>';
		}

		html += '<span class="jzsa-community-pagination-info">Page ' + currentPage + ' of ' + totalPages + '</span>';

		if ( currentPage < totalPages ) {
			html += '<button type="button" class="button jzsa-community-page-btn" data-page="' + ( currentPage + 1 ) + '">Next &rarr;</button>';
		}

		container.innerHTML = html;

		container.querySelectorAll( '.jzsa-community-page-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				currentPage = parseInt( btn.getAttribute( 'data-page' ), 10 );
				loadEntries();
			} );
		} );
	}

	function showOfflineBanner() {
		var banner = qs( '#jzsa-community-offline' );
		if ( banner ) {
			banner.style.display = '';
		}
		document.querySelectorAll( '.jzsa-section' ).forEach( function ( section ) {
			section.style.display = 'none';
		} );
	}

	function showServerErrorBanner() {
		var banner = qs( '#jzsa-community-server-error' );
		if ( banner ) {
			banner.style.display = '';
		}
		document.querySelectorAll( '.jzsa-section' ).forEach( function ( section ) {
			section.style.display = 'none';
		} );
	}

	function loadEntries() {
		var container = qs( '#jzsa-community-entries' );
		if ( ! container ) {
			return;
		}

		container.innerHTML = '<p class="jzsa-community-loading">Loading\u2026</p>';

		ajaxPost( 'jzsa_community_browse', { page: currentPage, q: currentQuery, sort: currentSort } )
			.then( function ( res ) {
				if ( res.success ) {
					renderEntries( res.data );
				} else if ( res.data && res.data.code === 'server_error' ) {
					showServerErrorBanner();
				} else if ( res.data && res.data.code === 'server_unreachable' ) {
					showOfflineBanner();
				} else {
					container.innerHTML =
						'<p class="jzsa-community-error">Could not load entries: ' +
						escHtml( res.data || 'Unknown error' ) + '</p>';
				}
			} )
			.catch( function ( err ) {
				console.error( '[JZSA community] loadEntries fetch/parse error:', err );
				showOfflineBanner();
			} );
	}

	/* -----------------------------------------------------------------------
	 * My Shortcodes section
	 * -------------------------------------------------------------------- */

	function renderMyEntries( entries ) {
		var container = qs( '#jzsa-community-my-entries' );
		if ( ! container ) {
			return;
		}

		// Update the summary badge with the published count
		var countBadge = qs( '#jzsa-my-entries-count' );
		if ( countBadge ) {
			if ( ! entries || entries.length === 0 ) {
				countBadge.textContent = "You haven\u2019t published anything yet";
				countBadge.style.display = '';
			} else {
				countBadge.textContent = entries.length + ( entries.length === 1 ? ' published' : ' published' );
				countBadge.style.display = '';
			}
		}

		if ( ! entries || entries.length === 0 ) {
			container.innerHTML =
				'<p class="jzsa-community-empty">You haven\u2019t published anything yet. Use \u201cShare a Shortcode\u201d above to get started.</p>';
			return;
		}

		container.innerHTML = '';

		entries.forEach( function ( entry ) {
			myEntryIds.add( String( entry.id ) );
			var block = buildEntryBlock( entry, true );
			container.appendChild( block );
			initEntryBlock( block, container );
		} );

		// Retroactively apply own-entry styles to any browse cards already rendered
		var browseContainer = qs( '#jzsa-community-entries' );
		if ( browseContainer ) {
			myEntryIds.forEach( function ( id ) {
				var browseBlock = browseContainer.querySelector( '[data-entry-id="' + id + '"]' );
				if ( ! browseBlock ) { return; }
				var ratingWrap = browseBlock.querySelector( '.jzsa-community-entry-rating' );
				if ( ratingWrap ) { ratingWrap.classList.add( 'is-own' ); }
				var starsEl = browseBlock.querySelector( '.jzsa-community-entry-stars' );
				if ( starsEl ) { starsEl.classList.add( 'is-disabled' ); }
			} );
		}
	}

	function loadMyEntries() {
		var container = qs( '#jzsa-community-my-entries' );
		if ( ! container ) {
			return;
		}

		container.innerHTML = '<p class="jzsa-community-loading">Loading\u2026</p>';

		ajaxPost( 'jzsa_community_load_my_entries', {} )
			.then( function ( res ) {
				if ( res.success ) {
					renderMyEntries( res.data );
				} else if ( res.data && res.data.code === 'server_error' ) {
					showServerErrorBanner();
				} else if ( res.data && res.data.code === 'server_unreachable' ) {
					showOfflineBanner();
				} else {
					container.innerHTML =
						'<p class="jzsa-community-error">Could not load your shortcodes: ' +
						escHtml( res.data || 'Unknown error' ) + '</p>';
				}
			} )
			.catch( function ( err ) {
				console.error( '[JZSA community] loadMyEntries fetch/parse error:', err );
				showOfflineBanner();
			} );
	}

	/* -----------------------------------------------------------------------
	 * Auth - Sign in
	 * -------------------------------------------------------------------- */

	var SIGNIN_POLL_INTERVAL_MS = 3000;
	var SIGNIN_POLL_TIMEOUT_MS  = 16 * 60 * 1000; // matches backend pending TTL + a small buffer

	function initConnect() {
		var btn           = qs( '.jzsa-community-connect-btn' );
		var statusEl      = qs( '.jzsa-community-auth-status' );
		var emailEl       = qs( '#jzsa-connect-email' );
		var displayNameEl = qs( '#jzsa-connect-display-name' );
		var generateBtn   = qs( '#jzsa-connect-display-name-generate-btn' );
		var pendingPanel  = qs( '.jzsa-community-pending-panel' );
		var pendingEmail  = qs( '.jzsa-community-pending-email' );
		var pendingStatus = qs( '.jzsa-community-pending-status' );
		var pendingCancel = qs( '.jzsa-community-pending-cancel-btn' );

		if ( ! btn ) {
			return;
		}

		if ( generateBtn ) {
			generateBtn.addEventListener( 'click', function () {
				displayNameEl.value = generateNickname();
				displayNameEl.focus();
			} );
		}

		function setStatus( msg, kind ) {
			if ( ! statusEl ) { return; }
			statusEl.textContent = msg ? '\u274c ' + msg : '';
			statusEl.className = 'jzsa-community-auth-status' + ( kind === 'error' ? ' is-error' : '' );
			// On any sign-in error, surface the recovery option so the user
			// doesn't have to discover the doubly-nested "Trouble signing in?"
			// disclosure on their own. Harmless if already open.
			if ( kind === 'error' && msg ) {
				var recoveryEl = document.querySelector( '.jzsa-community-signin-recovery' );
				if ( recoveryEl && recoveryEl.tagName === 'DETAILS' ) {
					recoveryEl.open = true;
				}
			}
		}

		function resetButton() {
			btn.disabled = false;
			btn.textContent = 'Sign in to community';
		}

		function reloadAsConnected() {
			var url = new URL( window.location.href );
			url.searchParams.set( 'jzsa_just_connected', '1' );
			window.location.href = url.toString();
		}

		// Roughly 3-second poll loop. Stops on success, on terminal error
		// (expired / not_found), or after SIGNIN_POLL_TIMEOUT_MS.
		var pollTimer    = null;
		var pollDeadline = 0;

		function stopPolling() {
			if ( pollTimer ) {
				clearTimeout( pollTimer );
				pollTimer = null;
			}
		}

		function poll() {
			if ( Date.now() > pollDeadline ) {
				stopPolling();
				if ( pendingStatus ) {
					pendingStatus.textContent = 'Confirmation link expired. Please try again.';
				}
				return;
			}

			ajaxPost( 'jzsa_community_signin_poll', {} )
				.then( function ( res ) {
					if ( res.success && res.data && res.data.state === 'connected' ) {
						stopPolling();
						reloadAsConnected();
						return;
					}
					if ( res.success && res.data && res.data.state === 'pending' ) {
						if ( pendingStatus ) {
							pendingStatus.textContent = 'Waiting for confirmation\u2026';
						}
						pollTimer = setTimeout( poll, SIGNIN_POLL_INTERVAL_MS );
						return;
					}
					// Terminal error: expired or not_found.
					stopPolling();
					var state = ( res.data && res.data.state ) || 'unknown';
					if ( pendingStatus ) {
						pendingStatus.textContent = state === 'expired'
							? 'Confirmation link expired. Please try again.'
							: 'Sign-in cancelled. Please try again.';
					}
				} )
				.catch( function () {
					// Transient network error \u2014 keep polling rather than giving up.
					if ( pendingStatus ) {
						pendingStatus.textContent = 'Could not reach the server, retrying\u2026';
					}
					pollTimer = setTimeout( poll, SIGNIN_POLL_INTERVAL_MS );
				} );
		}

		function startPolling( email ) {
			if ( pendingPanel ) { pendingPanel.style.display = ''; }
			if ( pendingEmail ) { pendingEmail.textContent = email || 'your email'; }
			if ( pendingStatus ) { pendingStatus.textContent = 'Waiting for confirmation\u2026'; }
			pollDeadline = Date.now() + SIGNIN_POLL_TIMEOUT_MS;
			pollTimer = setTimeout( poll, SIGNIN_POLL_INTERVAL_MS );
		}

		if ( pendingCancel ) {
			pendingCancel.addEventListener( 'click', function () {
				stopPolling();
				if ( pendingPanel ) { pendingPanel.style.display = 'none'; }
				resetButton();
			} );
		}

		btn.addEventListener( 'click', function () {
			var email       = emailEl ? emailEl.value.trim().toLowerCase() : '';
			var displayName = displayNameEl ? displayNameEl.value.trim() : '';

			if ( ! email || email.indexOf( '@' ) < 1 ) {
				setStatus( 'Please enter a valid email address.', 'error' );
				return;
			}
			if ( ! displayName || countLetters( displayName ) < 3 ) {
				setStatus( 'Display name is required, minimum 3 letters.', 'error' );
				return;
			}
			if ( displayName.length > 50 ) {
				setStatus( 'Display name must be 50 characters or fewer.', 'error' );
				return;
			}
			btn.disabled = true;
			btn.textContent = 'Signing in\u2026';
			setStatus( '' );

			ajaxPost( 'jzsa_community_signin_start', {
				email: email,
				display_name: displayName,
			} )
				.then( function ( res ) {
					if ( ! res.success ) {
						resetButton();
						setStatus( res.data || 'Failed to sign in.', 'error' );
						return;
					}
					if ( res.data && res.data.state === 'connected' ) {
						// Idempotent reconnect \u2014 install already authorized for this email.
						reloadAsConnected();
						return;
					}
					if ( res.data && res.data.state === 'pending' ) {
						// Verification email sent. Start polling for the JWT.
						startPolling( res.data.email || email );
						return;
					}
					resetButton();
					setStatus( 'Unexpected server response.', 'error' );
				} )
				.catch( function () {
					resetButton();
					setStatus( 'Could not reach the server. Please try again.', 'error' );
				} );
		} );
	}

	/* -----------------------------------------------------------------------
	 * Auth - Reset this site's community state (last-resort recovery)
	 * -------------------------------------------------------------------- */

	function initResetInstallState() {
		var btn = qs( '.jzsa-community-reset-install-btn' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			if ( ! confirm(
				'Reset this WordPress site\'s community state?\n\n' +
				'This will:\n' +
				'  - forget this site\'s local credential (you and any other WP admin will appear signed out)\n' +
				'  - regenerate the site\'s identifier so the next sign-in starts fresh\n\n' +
				'This will NOT:\n' +
				'  - delete your community account\n' +
				'  - delete any sample you have already published\n\n' +
				'Use only when sign-in keeps failing for an unclear reason. Continue?'
			) ) {
				return;
			}

			btn.disabled = true;
			ajaxPost( 'jzsa_community_reset_install_state', {} )
				.then( function ( res ) {
					if ( res.success ) {
						window.location.reload();
					} else {
						btn.disabled = false;
						alert( res.data || 'Could not reset.' );
					}
				} )
				.catch( function () {
					btn.disabled = false;
					alert( 'Could not reach the server.' );
				} );
		} );
	}

	/* -----------------------------------------------------------------------
	 * Auth - Sign out (local) and Delete account (destructive, server-side)
	 * -------------------------------------------------------------------- */

	function initAccountActions() {
		var signOutBtn      = qs( '.jzsa-community-signout-btn' );
		var deleteAccountBtn = qs( '.jzsa-community-delete-account-btn' );
		if ( ! signOutBtn && ! deleteAccountBtn ) {
			return;
		}

		// Sign out is a low-risk, local-only action: it just clears the JWT
		// on this WP install. The install stays authorized on the account,
		// so signing in again from this site does not require email
		// confirmation. No confirm dialog: that would be annoying for a
		// routine action equivalent to closing a browser tab.
		if ( signOutBtn ) {
			signOutBtn.addEventListener( 'click', function () {
				signOutBtn.disabled = true;
				ajaxPost( 'jzsa_community_signout', {} )
					.then( function ( res ) {
						if ( res.success ) {
							window.location.reload();
						} else {
							signOutBtn.disabled = false;
							alert( res.data || 'Could not sign out.' );
						}
					} )
					.catch( function () {
						signOutBtn.disabled = false;
						alert( 'Could not reach the server.' );
					} );
			} );
		}

		// Delete account is permanent and cascades across every install. The
		// confirm dialog spells out exactly what is being destroyed.
		if ( deleteAccountBtn ) {
			deleteAccountBtn.addEventListener( 'click', function () {
				if ( ! confirm(
					'Delete your jzsa community account?\n\n' +
					'This will permanently remove:\n' +
					'  • Your account and email from our database\n' +
					'  • All shortcodes you have shared in the community\n' +
					'  • All ratings you have given to other community shortcodes\n' +
					'  • Access from any other WordPress sites you have signed in from\n\n' +
					'This cannot be undone. If you change your mind later you can ' +
					'create a new account, but past content will not return.\n\n' +
					'Continue?'
				) ) {
					return;
				}

				deleteAccountBtn.disabled = true;

				ajaxPost( 'jzsa_community_delete_account', {} )
					.then( function ( res ) {
						if ( res.success ) {
							window.location.reload();
						} else {
							deleteAccountBtn.disabled = false;
							alert( res.data || 'Could not delete account.' );
						}
					} )
					.catch( function () {
						deleteAccountBtn.disabled = false;
						alert( 'Could not reach the server.' );
					} );
			} );
		}
	}

	/* -----------------------------------------------------------------------
	 * Your authorized sites - list + remove
	 * -------------------------------------------------------------------- */

	function escHtml( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function formatRelative( iso ) {
		if ( ! iso ) { return ''; }
		var then = new Date( iso ).getTime();
		if ( isNaN( then ) ) { return ''; }
		var diff = Math.max( 0, Date.now() - then );
		var sec  = Math.floor( diff / 1000 );
		if ( sec < 60 )      { return 'just now'; }
		if ( sec < 3600 )    { return Math.floor( sec / 60 ) + 'm ago'; }
		if ( sec < 86400 )   { return Math.floor( sec / 3600 ) + 'h ago'; }
		if ( sec < 2592000 ) { return Math.floor( sec / 86400 ) + 'd ago'; }
		return new Date( iso ).toISOString().slice( 0, 10 );
	}

	function normalizeInstallSiteKey( inst ) {
		var raw = String( ( inst && inst.site_url ) || '' ).trim().toLowerCase();
		if ( ! raw ) {
			return 'install:' + String( ( inst && inst.id ) || '' );
		}

		try {
			var parsed = new URL( /^https?:\/\//i.test( raw ) ? raw : 'https://' + raw );
			var host = parsed.hostname.replace( /^www\./, '' );
			var path = parsed.pathname.replace( /\/+$/, '' );
			return host + path;
		} catch ( e ) {
			return raw.replace( /^https?:\/\//i, '' ).replace( /^www\./, '' ).replace( /\/+$/, '' );
		}
	}

	function installTimestamp( inst ) {
		var lastSeen = Date.parse( inst && inst.last_seen_at ? inst.last_seen_at : '' );
		var added    = Date.parse( inst && inst.added_at ? inst.added_at : '' );
		return isNaN( lastSeen ) ? ( isNaN( added ) ? 0 : added ) : lastSeen;
	}

	function preferInstall( current, candidate ) {
		if ( ! current ) {
			return candidate;
		}
		if ( candidate && candidate.is_current && ! current.is_current ) {
			return candidate;
		}
		if ( current.is_current && ! ( candidate && candidate.is_current ) ) {
			return current;
		}
		if ( installTimestamp( candidate ) > installTimestamp( current ) ) {
			return candidate;
		}
		return current;
	}

	function dedupeInstalls( installs ) {
		var bySite = {};
		( installs || [] ).forEach( function ( inst ) {
			var key = normalizeInstallSiteKey( inst );
			bySite[ key ] = preferInstall( bySite[ key ], inst );
		} );

		return Object.keys( bySite ).map( function ( key ) {
			return bySite[ key ];
		} ).sort( function ( a, b ) {
			if ( a.is_current && ! b.is_current ) { return -1; }
			if ( b.is_current && ! a.is_current ) { return 1; }
			return installTimestamp( b ) - installTimestamp( a );
		} );
	}

	function renderInstalls( installs, container ) {
		installs = dedupeInstalls( installs );

		if ( ! installs || installs.length === 0 ) {
			container.innerHTML = '<p class="jzsa-help-text" style="color:#666;">No authorized sites yet.</p>';
			return;
		}

		var rows = installs.map( function ( inst ) {
			var siteUrl = String( inst.site_url || '' );
			// Display: strip the scheme and any trailing slash to make the
			// URL easier to scan ("shoes.com" reads better than the full
			// "https://shoes.com/"). The full URL stays in the data attribute
			// for the confirm dialog.
			var siteUrlDisplay = siteUrl
				? siteUrl.replace( /^https?:\/\//i, '' ).replace( /\/+$/, '' )
				: 'Untitled site';
			var primary = inst.label || siteUrlDisplay;
			var secondary = inst.label && siteUrl ? siteUrl : '';
			var lastSeen = formatRelative( inst.last_seen_at );
			var current = inst.is_current
				? '<span style="color:#1a7a3a; font-weight:600; margin-left:8px;">(this site)</span>'
				: '';
			var btn = inst.is_current
				? '<button type="button" class="button" disabled title="Use Sign out to leave this site, or Delete account to leave the whole account.">Remove</button>'
				: '<button type="button" class="button jzsa-community-remove-install-btn" data-install-id="' + inst.id + '" data-install-label="' + escHtml( primary ) + '" style="color:#d63638; border-color:#d63638;">Remove</button>';

			return '' +
				'<div class="jzsa-community-install-row" data-install-id="' + inst.id + '" style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid #f0f0f1;">' +
					'<div>' +
						'<div style="font-weight:600;">' + escHtml( primary ) + current + '</div>' +
						'<div style="font-size:12px; color:#666;">' +
							( secondary ? escHtml( secondary ) + ' · ' : '' ) +
							( lastSeen ? 'last used ' + escHtml( lastSeen ) : '' ) +
						'</div>' +
					'</div>' +
					'<div>' + btn + '</div>' +
				'</div>';
		} );
		container.innerHTML = rows.join( '' );
	}

	function loadInstalls() {
		var container = qs( '#jzsa-community-installs-list' );
		if ( ! container ) { return; }

		ajaxPost( 'jzsa_community_list_installs', {} )
			.then( function ( res ) {
				if ( res.success && res.data && Array.isArray( res.data.installs ) ) {
					renderInstalls( res.data.installs, container );
				} else {
					container.innerHTML = '<p class="jzsa-help-text" style="color:#a04;">Could not load your authorized sites.</p>';
				}
			} )
			.catch( function () {
				container.innerHTML = '<p class="jzsa-help-text" style="color:#a04;">Could not reach the server.</p>';
			} );
	}

	function initInstalls() {
		var container = qs( '#jzsa-community-installs-list' );
		var section   = qs( '.jzsa-community-installs-section' );
		if ( ! container || ! section ) {
			return;
		}

		// Lazy load when the section is first opened, so signed-in users
		// who never expand it don't trigger an API call.
		var loaded = false;
		section.addEventListener( 'toggle', function () {
			if ( section.open && ! loaded ) {
				loaded = true;
				loadInstalls();
			}
		} );

		// Delegate Remove clicks (rows are rendered into innerHTML).
		container.addEventListener( 'click', function ( ev ) {
			var btn = ev.target.closest && ev.target.closest( '.jzsa-community-remove-install-btn' );
			if ( ! btn ) { return; }
			var id    = btn.getAttribute( 'data-install-id' );
			var label = btn.getAttribute( 'data-install-label' ) || ( 'Site #' + id );

			if ( ! confirm(
				'Remove "' + label + '" from your community account?\n\n' +
				'That site will no longer be able to publish, edit, or rate. Signing in again from it will require a fresh email confirmation.'
			) ) {
				return;
			}

			btn.disabled = true;
			ajaxPost( 'jzsa_community_remove_install', { install_id: id } )
				.then( function ( res ) {
					if ( res.success ) {
						loadInstalls();
					} else {
						btn.disabled = false;
						var msg = ( res.data && res.data.message ) || res.data || 'Could not remove that site.';
						alert( msg );
					}
				} )
				.catch( function () {
					btn.disabled = false;
					alert( 'Could not reach the server.' );
				} );
		} );
	}

	/* -----------------------------------------------------------------------
	 * Search
	 * -------------------------------------------------------------------- */

	function initSearch() {
		var input = qs( '#jzsa-community-search' );
		var searchBtn = qs( '#jzsa-community-search-btn' );

		if ( ! input || ! searchBtn ) {
			return;
		}

		function doSearch() {
			currentQuery = input.value.trim();
			currentPage = 1;
			loadEntries();
		}

		searchBtn.addEventListener( 'click', doSearch );

		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				doSearch();
			}
		} );
	}

	var FIELD_LABELS = {
		title:       'Title',
		shortcode:   'Shortcode',
		description: i18n( 'descriptionLabel' ) || 'Description',
		tags:        'Tags',
		site_url:    i18n( 'siteUrlLabel' ) || 'Sample page URL',
	};

	/**
	 * Format a publish/update error response into a human-readable string.
	 * Handles both plain strings and structured { message, details } objects.
	 */
	function formatPublishError( data ) {
		if ( ! data ) {
			return 'Could not publish entry.';
		}
		if ( typeof data === 'string' ) {
			return data;
		}

		var fieldErrors = data.details && data.details.fieldErrors;
		if ( ! fieldErrors ) {
			return data.message || 'Could not publish entry.';
		}

		var parts = [];
		Object.keys( fieldErrors ).forEach( function ( field ) {
			var msgs = fieldErrors[ field ];
			if ( msgs && msgs.length ) {
				var label = FIELD_LABELS[ field ] || field;
				parts.push( label + ': ' + msgs.join( ', ' ) );
			}
		} );

		if ( parts.length === 0 ) {
			return data.message || 'Could not publish entry.';
		}

		return parts.join( ' \u2014 ' );
	}

	/* -----------------------------------------------------------------------
	 * Publish form
	 * -------------------------------------------------------------------- */

	function initPublish() {
		var btn = qs( '#jzsa-community-publish-btn' );
		var resultEl = qs( '#jzsa-publish-result' );
		var showcaseConsentEl = qs( '#jzsa-pub-showcase-consent' );
		var showcaseConsentToggles = Array.prototype.slice.call(
			document.querySelectorAll( '.jzsa-pub-showcase-consent-toggle' )
		);
		var publishRequiredControls = [
			qs( '#jzsa-pub-site-url' ),
			qs( '#jzsa-pub-entry-info' ),
		];
		var publishRequiredBadges = Array.prototype.slice.call(
			document.querySelectorAll( '.jzsa-community-publish-table .jzsa-showcase-required-badge' )
		);

		if ( ! btn ) {
			return;
		}

		function syncPublishShowcaseConsent( checked ) {
			showcaseConsentToggles.forEach( function ( toggle ) {
				toggle.checked = checked;
			} );
			syncShowcaseRequiredState( showcaseConsentEl, publishRequiredControls, publishRequiredBadges );
		}

		syncPublishShowcaseConsent( showcaseConsentEl ? showcaseConsentEl.checked : false );
		showcaseConsentToggles.forEach( function ( toggle ) {
			toggle.addEventListener( 'change', function () {
				syncPublishShowcaseConsent( toggle.checked );
			} );
		} );

		btn.addEventListener( 'click', function () {
			var title     = ( qs( '#jzsa-pub-title' ) || {} ).value || '';
			var shortcode = getPublishShortcode();
			var desc      = ( qs( '#jzsa-pub-description' ) || {} ).value || '';
			var tags      = ( qs( '#jzsa-pub-tags' ) || {} ).value || '';
			var siteUrl   = ( qs( '#jzsa-pub-site-url' ) || {} ).value || '';
			var entryInfo = ( qs( '#jzsa-pub-entry-info' ) || {} ).value || '';
			var showcaseConsent = ( qs( '#jzsa-pub-showcase-consent' ) || {} ).checked || false;
			title     = title.trim();
			shortcode = shortcode.trim();
			desc      = desc.trim();
			siteUrl   = normalizeUrlInput( siteUrl );
			entryInfo = entryInfo.trim();

			var validationError = validateCommunityEntryFields( {
				title: title,
				shortcode: shortcode,
				description: desc,
				tags: tags,
				siteUrl: siteUrl,
				entryInfo: entryInfo,
				showcaseConsent: showcaseConsent,
			} );
			if ( validationError ) {
				setResult( resultEl, validationError, false );
				return;
			}

			btn.disabled = true;
			btn.textContent = 'Publishing\u2026';
			setResult( resultEl, '', null );

			ajaxPost( 'jzsa_community_publish', {
				title:                     title,
				shortcode:                 shortcode,
				description:               desc,
				tags:                      tags,
				site_url:                  siteUrl,
				entry_info:                entryInfo,
				public_showcase_consent:   showcaseConsent,
			} )
				.then( function ( res ) {
					btn.disabled = false;
					btn.textContent = 'Publish to Community';

					if ( res.success ) {
						setResult( resultEl, '\u2705 Published! Your shortcode is now live in the directory. See the sections below and find it there.', true );
						// Clear form
						var fields = [ '#jzsa-pub-title', '#jzsa-pub-description', '#jzsa-pub-tags', '#jzsa-pub-site-url', '#jzsa-pub-entry-info' ];
						fields.forEach( function ( sel ) {
							var el = qs( sel );
							if ( el ) {
								el.value = '';
							}
						} );
						setPublishShortcode( '' );
						var previewEl = qs( '#jzsa-pub-preview' );
						if ( previewEl ) {
							previewEl.innerHTML = '';
						}
						var consentEl = qs( '#jzsa-pub-showcase-consent' );
						if ( consentEl ) {
							syncPublishShowcaseConsent( false );
						}
						// Reload browse and my entries so the new entry appears
						currentPage = 1;
						loadEntries();
						loadMyEntries();
					} else {
						setResult( resultEl, '\u274c ' + formatPublishError( res.data ), false );
					}
				} )
				.catch( function () {
					btn.disabled = false;
					btn.textContent = 'Publish to Community';
					setResult( resultEl, '\u274c Could not reach the server. Please try again.', false );
				} );
		} );
	}

	function setResult( el, msg, success ) {
		if ( ! el ) {
			return;
		}
		el.textContent = msg;
		if ( success === true ) {
			el.className = 'jzsa-community-result is-success';
		} else if ( success === false ) {
			el.className = 'jzsa-community-result is-error';
		} else {
			el.className = 'jzsa-community-result';
		}
	}

	/* -----------------------------------------------------------------------
	 * Display name - inline edit widget
	 * -------------------------------------------------------------------- */

	var NICKNAME_ADJECTIVES = [
		'Swift', 'Quiet', 'Bold', 'Calm', 'Bright', 'Dark', 'Silver', 'Amber',
		'Crimson', 'Azure', 'Golden', 'Misty', 'Rusty', 'Snowy', 'Stormy', 'Sunny',
	];
	var NICKNAME_NOUNS = [
		'Fox', 'Pine', 'Wolf', 'Hawk', 'Owl', 'Bear', 'Lynx', 'Raven',
		'Crane', 'Fern', 'Cedar', 'Stone', 'River', 'Cloud', 'Spark', 'Breeze',
	];

	function generateNickname() {
		var adj  = NICKNAME_ADJECTIVES[ Math.floor( Math.random() * NICKNAME_ADJECTIVES.length ) ];
		var noun = NICKNAME_NOUNS[ Math.floor( Math.random() * NICKNAME_NOUNS.length ) ];
		return adj + noun;
	}

	function initDisplayName() {
		var editBtn    = qs( '#jzsa-display-name-edit-btn' );
		var editRow    = qs( '#jzsa-display-name-edit-row' );
		var viewSpan   = qs( '#jzsa-display-name-view' );
		var input      = qs( '#jzsa-display-name-input' );
		var saveBtn    = qs( '#jzsa-display-name-save-btn' );
		var cancelBtn  = qs( '#jzsa-display-name-cancel-btn' );
		var generateBtn = qs( '#jzsa-display-name-generate-btn' );
		var resultEl   = qs( '#jzsa-display-name-result' );

		if ( ! editBtn || ! editRow || ! viewSpan || ! input ) {
			return;
		}

		function showEdit() {
			editBtn.style.display = 'none';
			editRow.style.display = 'inline-flex';
			input.focus();
			input.select();
		}

		function hideEdit() {
			editRow.style.display = 'none';
			editBtn.style.display = '';
			if ( resultEl ) {
				resultEl.textContent = '';
				resultEl.className = 'jzsa-community-result';
			}
		}

		editBtn.addEventListener( 'click', showEdit );

		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', function () {
				// Restore original value on cancel
				input.value = jzsaCommunity.displayName || '';
				hideEdit();
			} );
		}

		if ( generateBtn ) {
			generateBtn.addEventListener( 'click', function () {
				input.value = generateNickname();
				input.focus();
			} );
		}

		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				if ( saveBtn ) saveBtn.click();
			} else if ( e.key === 'Escape' ) {
				if ( cancelBtn ) cancelBtn.click();
			}
		} );

		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function () {
				var name = input.value.trim();
				
				if ( ! name || countLetters( name ) < 3 ) {
					setResult( resultEl, 'Required, minimum 3 letters.', false );
					return;
				}
				if ( name.length > 50 ) {
					setResult( resultEl, 'Must be 50 characters or fewer.', false );
					return;
				}

				saveBtn.disabled = true;
				saveBtn.textContent = 'Saving\u2026';

				ajaxPost( 'jzsa_community_update_display_name', { display_name: name } )
					.then( function ( res ) {
						saveBtn.disabled = false;
						saveBtn.textContent = 'Save';

						if ( res.success ) {
							var saved = ( res.data && res.data.display_name ) ? res.data.display_name : name;
							// Update the inline view
							viewSpan.textContent = saved;
							// Update the JS-side cache so cancel restores to new name
							jzsaCommunity.displayName = saved;
							input.value = saved;
							setResult( resultEl, '\u2705 Saved!', true );
							// Auto-hide after a moment
							setTimeout( hideEdit, 1500 );
						} else {
							setResult( resultEl, '\u274c ' + ( res.data || 'Could not save.' ), false );
						}
					} )
					.catch( function () {
						saveBtn.disabled = false;
						saveBtn.textContent = 'Save';
						setResult( resultEl, '\u274c Could not reach the server.', false );
					} );
			} );
		}
	}

	function initSort() {
		var sortBtns = document.querySelectorAll( '.jzsa-community-sort-btn' );
		sortBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var sort = btn.getAttribute( 'data-sort' );
				if ( ! sort || sort === currentSort ) {
					return;
				}
				currentSort = sort;
				sortBtns.forEach( function ( b ) {
					b.classList.toggle( 'jzsa-community-sort-btn--active', b === btn );
				} );
				currentPage = 1;
				loadEntries();
			} );
		} );
	}

	/* -----------------------------------------------------------------------
	 * Dev helper - fill form with random data
	 * -------------------------------------------------------------------- */

	var DEV_ALBUM_LINKS = [
		'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R',
		'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB',
		'https://photos.google.com/share/AF1QipP01V2WM2fQU0yULcm5tnV4zi-9XEO2Qg7idoHWvD2_bU8aKnrDignNSucfRaMy_w?key=LUlWRm9YdEhnSEtMUGI2MnFIcDRyVElweTJkS0FR',
	];

	var DEV_MODES    = [ 'slider', 'gallery', 'carousel' ];
	var DEV_TAGS     = [ 'slider', 'gallery', 'dark', 'mosaic', 'minimal', 'fullscreen', 'demo' ];
	var DEV_ADJECTIVES = [ 'Dark', 'Minimal', 'Classic', 'Compact', 'Wide', 'Tall', 'Simple', 'Bold' ];
	var DEV_NOUNS    = [ 'Slider', 'Gallery', 'Carousel', 'Viewer', 'Strip', 'Layout', 'Setup' ];

	function devRandom( arr ) {
		return arr[ Math.floor( Math.random() * arr.length ) ];
	}

	function devRandomInt( min, max ) {
		return Math.floor( Math.random() * ( max - min + 1 ) ) + min;
	}

	function devShuffle( arr ) {
		var a = arr.slice();
		for ( var i = a.length - 1; i > 0; i-- ) {
			var j = Math.floor( Math.random() * ( i + 1 ) );
			var tmp = a[ i ]; a[ i ] = a[ j ]; a[ j ] = tmp;
		}
		return a;
	}

	function devGenerateShortcode() {
		var link = devRandom( DEV_ALBUM_LINKS );
		var mode = devRandom( DEV_MODES );
		var radius = devRandom( [ '0', '8', '16', '24' ] );
		var limit  = devRandomInt( 6, 24 );
		var extras = '';
		if ( Math.random() > 0.5 ) {
			extras += ' mosaic="true"';
		}
		if ( Math.random() > 0.6 ) {
			extras += ' show-download-button="true"';
		}
		return '[jzsa-album link="' + link + '" mode="' + mode + '" limit="' + limit + '" corner-radius="' + radius + '"' + extras + ']';
	}

	/* -----------------------------------------------------------------------
	 * Dev: Fill publish form (local env only)
	 * -------------------------------------------------------------------- */

	function initDevFill() {
		var btn = qs( '#jzsa-community-dev-fill-btn' );
		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			var title = devRandom( DEV_ADJECTIVES ) + ' ' + devRandom( DEV_NOUNS ) + ' #' + devRandomInt( 1, 999 );
			var shortcode = devGenerateShortcode();
			var tags = devShuffle( DEV_TAGS ).slice( 0, devRandomInt( 1, 3 ) ).join( ', ' );
			var descriptions = [
				'A clean configuration for everyday use.',
				'Great for travel and nature albums.',
				'Minimal setup, maximum impact.',
				'Showcases the mosaic strip nicely.',
				'Works well on dark-themed sites.',
			];
			var description = devRandom( descriptions );

			var titleEl = qs( '#jzsa-pub-title' );
			var scEl    = qs( '#jzsa-pub-shortcode' );
			var descEl  = qs( '#jzsa-pub-description' );
			var tagsEl  = qs( '#jzsa-pub-tags' );
			var resultEl = qs( '#jzsa-publish-result' );

			if ( titleEl ) titleEl.value = title;
			if ( scEl )    setPublishShortcode( shortcode );
			if ( descEl )  descEl.value  = description;
			if ( tagsEl )  tagsEl.value  = tags;
			if ( resultEl ) resultEl.innerHTML = '';

		} );
	}

	/* -----------------------------------------------------------------------
	 * Init
	 * -------------------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		loadEntries();
		initSearch();
		initSort();
		initConnect();
		initResetInstallState();
		initAccountActions();
		initInstalls();
		initPublish();
		initDisplayName();
		initDevFill();

		// Load the current user's own entries when connected
		if ( isCommunityConnected() ) {
			loadMyEntries();
		}
	} );

} )();
