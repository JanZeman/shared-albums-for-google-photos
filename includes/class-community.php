<?php
/**
 * Community Class
 *
 * Handles the "Share Your Shortcode" community directory feature.
 * All write operations proxy through WP AJAX so the JWT never touches client JS.
 *
 * @package JZSA_Shared_Albums
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Community Class
 */
class JZSA_Community {

	const OPT_JWT             = 'jzsa_community_jwt';
	const OPT_DISPLAY_NAME    = 'jzsa_community_display_name';
	const OPT_DISPLAY_URL     = 'jzsa_community_display_url';
	const OPT_INSTALL_SECRET  = 'jzsa_install_secret';
	const OPT_SHOWCASE_WARNING_DISMISSED = 'jzsa_showcase_warning_dismissed';
	const NONCE_NOTICE_KEY    = 'jzsa_community_notice_';
	const AUTH_CHALLENGE_PREFIX = 'jzsa_community_auth_challenge_';
	const SIGNIN_PENDING_PREFIX = 'jzsa_community_signin_pending_';

	/**
	 * Constructor. Registers all hooks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Auth flow
		add_action( 'wp_ajax_jzsa_community_signin_start', array( $this, 'ajax_signin_start' ) );
		add_action( 'wp_ajax_jzsa_community_signin_poll',  array( $this, 'ajax_signin_poll' ) );

		// Local-only recovery: clears all community state from this WP install
		// so the user can start over without the API needing to be reachable.
		add_action( 'wp_ajax_jzsa_community_reset_install_state', array( $this, 'ajax_reset_install_state' ) );

		// Install management
		add_action( 'wp_ajax_jzsa_community_list_installs',   array( $this, 'ajax_list_installs' ) );
		add_action( 'wp_ajax_jzsa_community_remove_install',  array( $this, 'ajax_remove_install' ) );

		// Browse & write
		add_action( 'wp_ajax_jzsa_community_browse',               array( $this, 'ajax_browse' ) );
		add_action( 'wp_ajax_jzsa_community_publish',              array( $this, 'ajax_publish' ) );
		add_action( 'wp_ajax_jzsa_community_delete_entry',         array( $this, 'ajax_delete_entry' ) );
		add_action( 'wp_ajax_jzsa_community_signout',              array( $this, 'ajax_signout' ) );
		add_action( 'wp_ajax_jzsa_community_delete_account',       array( $this, 'ajax_delete_account' ) );
		add_action( 'wp_ajax_jzsa_community_update_display_name',  array( $this, 'ajax_update_display_name' ) );
		add_action( 'wp_ajax_jzsa_community_update_display_url',   array( $this, 'ajax_update_display_url' ) );
		add_action( 'wp_ajax_jzsa_community_load_my_entries',      array( $this, 'ajax_load_my_entries' ) );
		add_action( 'wp_ajax_jzsa_community_update_entry',         array( $this, 'ajax_update_entry' ) );
		add_action( 'wp_ajax_jzsa_community_interact',             array( $this, 'ajax_interact' ) );
		add_action( 'wp_ajax_jzsa_community_rate',                 array( $this, 'ajax_rate' ) );

		// One-shot UI preference: dismiss the yellow scope warning above the
		// showcase consent checkbox once the user has read it.
		add_action( 'wp_ajax_jzsa_community_dismiss_showcase_warning', array( $this, 'ajax_dismiss_showcase_warning' ) );
	}

	/**
	 * Shared UI strings used by both PHP-rendered and JS-rendered community UI.
	 *
	 * @return array<string, string>
	 */
	private static function get_i18n_strings() {
		return array(
			'showcaseConsentLabel'       => __( 'Allow this sample to be considered for the future Photo lovers community.', 'janzeman-shared-albums-for-google-photos' ),
			'showcaseConsentHelp'        => __( 'If selected, this shortcode sample and its rendered preview may later appear in the Photo lovers community (a future, externally-visible site that does not exist yet). Description, sample page URL, and creator name are required for consideration.', 'janzeman-shared-albums-for-google-photos' ),
			'showcaseShowShortcodeLabel' => __( 'Also show the shortcode (not only the photos).', 'janzeman-shared-albums-for-google-photos' ),
			'showcaseShowShortcodeHelp'  => __( 'Default: shown. Uncheck if you want the Photo lovers community to display only your photos and not the underlying shortcode. The WP admins community on this page is unaffected; it always shows the masked shortcode.', 'janzeman-shared-albums-for-google-photos' ),
			'showcaseRequiredBadge'      => __( 'Required for Photo lovers community', 'janzeman-shared-albums-for-google-photos' ),
			'showcaseRequiredMessage'    => __( 'Description, sample page URL, and photographer / creator name are required for Photo lovers community consideration.', 'janzeman-shared-albums-for-google-photos' ),
			'descriptionLabel'           => __( 'Description', 'janzeman-shared-albums-for-google-photos' ),
			'siteUrlLabel'               => __( 'Sample page URL', 'janzeman-shared-albums-for-google-photos' ),
			'photographerNameLabel'      => __( 'Photographer / creator name or nickname', 'janzeman-shared-albums-for-google-photos' ),
			'photographerBioLabel'       => __( 'Short bio / intro', 'janzeman-shared-albums-for-google-photos' ),
			'photographerBioHelp'        => __( 'A short note about the photographer, creator, studio, or website behind this sample.', 'janzeman-shared-albums-for-google-photos' ),
		);
	}

	/**
	 * Truncate a sanitized string to a character limit.
	 *
	 * @param string $value String to truncate.
	 * @param int    $max   Maximum character count.
	 * @return string
	 */
	private static function truncate_string( $value, $max ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}

		return substr( $value, 0, $max );
	}

	/**
	 * Return a string character count.
	 *
	 * @param string $value String to measure.
	 * @return int
	 */
	private static function string_length( $value ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value );
		}

		return strlen( $value );
	}

	/**
	 * Count letters in a string.
	 *
	 * @param string $value String to inspect.
	 * @return int
	 */
	private static function letter_count( $value ) {
		if ( preg_match_all( '/\p{L}/u', $value, $matches ) ) {
			return count( $matches[0] );
		}

		return 0;
	}

	/**
	 * Extract the Google Photos album link from a community shortcode.
	 *
	 * @param string $shortcode Shortcode text.
	 * @return string
	 */
	private static function extract_community_shortcode_album_link( $shortcode ) {
		if ( ! preg_match( '/^\s*\[jzsa-album\b[^\]]*\]\s*$/i', $shortcode ) ) {
			return '';
		}

		if ( preg_match( '/\blink\s*=\s*([\'"])(https:\/\/photos\.google\.com\/share\/[A-Za-z0-9_-]+(?:\?key=[A-Za-z0-9_-]+)?)\1/i', $shortcode, $matches ) ) {
			return $matches[2];
		}

		return '';
	}

	/**
	 * Normalize the comma-separated community tags.
	 *
	 * @param string $tags_raw Raw tag text.
	 * @return array
	 */
	private static function normalize_community_tags( $tags_raw ) {
		return array_values(
			array_filter(
				array_map( 'strtolower', array_map( 'trim', explode( ',', $tags_raw ) ) )
			)
		);
	}

	/**
	 * Validate the optional public sample URL.
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	private static function is_valid_community_sample_url( $url ) {
		if ( '' === $url ) {
			return true;
		}

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$parts  = wp_parse_url( $url );
		$scheme = strtolower( $parts['scheme'] ?? '' );
		$host   = $parts['host'] ?? '';

		return in_array( $scheme, array( 'http', 'https' ), true ) && false !== strpos( $host, '.' );
	}

	/**
	 * Normalize an optional display URL. Empty value disables the public URL.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private static function normalize_display_url( $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		return sanitize_url( $url );
	}

	/**
	 * Whether a display URL is valid or empty.
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	private static function is_valid_display_url( $url ) {
		if ( '' === $url ) {
			return true;
		}

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$parts  = wp_parse_url( $url );
		$scheme = strtolower( $parts['scheme'] ?? '' );
		$host   = $parts['host'] ?? '';

		return in_array( $scheme, array( 'http', 'https' ), true ) && '' !== $host;
	}

	/**
	 * Validate a community publish/update payload before proxying it to the API.
	 *
	 * @param string $title             Entry title.
	 * @param string $shortcode         Shortcode text.
	 * @param string $description       Entry description.
	 * @param string $tags_raw          Raw comma-separated tags.
	 * @param string $entry_url         Sample page URL.
	 * @param string $photographer_name Photographer / creator name.
	 * @param string $photographer_bio  Photographer bio.
	 * @param bool   $consent           Public showcase consent.
	 * @return string Error message, or empty string when valid.
	 */
	private static function validate_community_entry_payload( $title, $shortcode, $description, $tags_raw, $entry_url, $photographer_name, $photographer_bio, $consent ) {
		if ( '' === $title || self::string_length( $title ) < 3 ) {
			return __( 'Title must be at least 3 characters.', 'janzeman-shared-albums-for-google-photos' );
		}

		if ( self::string_length( $title ) > 120 ) {
			return __( 'Title must be 120 characters or fewer.', 'janzeman-shared-albums-for-google-photos' );
		}

		if ( '' === $shortcode || self::string_length( $shortcode ) < 10 || self::string_length( $shortcode ) > 2000 ) {
			return __( 'Shortcode must be a valid [jzsa-album] shortcode.', 'janzeman-shared-albums-for-google-photos' );
		}

		if ( ! self::extract_community_shortcode_album_link( $shortcode ) ) {
			return __( 'Shortcode must include a valid Google Photos share URL in the link parameter.', 'janzeman-shared-albums-for-google-photos' );
		}

		if ( self::string_length( $description ) > 500 ) {
			return __( 'Description must be 500 characters or fewer.', 'janzeman-shared-albums-for-google-photos' );
		}

		if ( ! self::is_valid_community_sample_url( $entry_url ) ) {
			return __( 'Please enter a valid sample page URL (e.g. https://yoursite.com/page).', 'janzeman-shared-albums-for-google-photos' );
		}

		if ( self::string_length( $photographer_name ) > 120 ) {
			return __( 'Photographer / creator name must be 120 characters or fewer.', 'janzeman-shared-albums-for-google-photos' );
		}

		if ( self::string_length( $photographer_bio ) > 500 ) {
			return __( 'Short bio must be 500 characters or fewer.', 'janzeman-shared-albums-for-google-photos' );
		}

		$tags = self::normalize_community_tags( $tags_raw );
		if ( count( $tags ) > 5 ) {
			return __( 'Use no more than 5 tags.', 'janzeman-shared-albums-for-google-photos' );
		}

		foreach ( $tags as $tag ) {
			if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{1,29}$/i', $tag ) ) {
				return __( 'Tags must be 2-30 characters and use only letters, numbers, and hyphens.', 'janzeman-shared-albums-for-google-photos' );
			}
		}

		if ( $consent && ( empty( $description ) || empty( $entry_url ) || empty( $photographer_name ) ) ) {
			$i18n = self::get_i18n_strings();
			return $i18n['showcaseRequiredMessage'];
		}

		return '';
	}

	/**
	 * Register the public challenge endpoint used by the community server to
	 * verify that a connect request comes from a site running this plugin.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'jzsa/v1',
			'/community-challenge',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_community_challenge' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'challenge' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Return a short-lived connect challenge if it was generated by an admin.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_community_challenge( $request ) {
		$challenge = sanitize_text_field( $request->get_param( 'challenge' ) );
		if ( empty( $challenge ) ) {
			return new WP_Error( 'jzsa_missing_challenge', 'Missing challenge.', array( 'status' => 400 ) );
		}

		$transient_key = self::AUTH_CHALLENGE_PREFIX . hash( 'sha256', $challenge );
		$stored        = get_transient( $transient_key );
		if ( ! is_string( $stored ) || ! hash_equals( $stored, $challenge ) ) {
			return new WP_Error( 'jzsa_invalid_challenge', 'Invalid challenge.', array( 'status' => 404 ) );
		}

		delete_transient( $transient_key );

		return rest_ensure_response(
			array(
				'plugin'    => 'janzeman-shared-albums-for-google-photos',
				'version'   => JZSA_VERSION,
				'challenge' => $challenge,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Static helpers
	// -------------------------------------------------------------------------

	/**
	 * Whether the current WP user is connected to the community (JWT stored in user meta).
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return (bool) get_user_meta( get_current_user_id(), self::OPT_JWT, true );
	}

	/**
	 * Return the stored JWT, or empty string if not connected.
	 *
	 * @return string
	 */
	public static function get_jwt() {
		return (string) get_user_meta( get_current_user_id(), self::OPT_JWT, true );
	}

	/**
	 * Ensure this WP install has a random install secret in wp_options.
	 *
	 * Generated once on first call (typically at plugin activation, but the
	 * lazy fallback in get_install_secret_hash() covers upgrades from
	 * pre-secret versions). 256 bits of entropy, stored hex-encoded, never
	 * autoloaded, never sent to the browser or to the API; only its
	 * SHA-256 hash leaves the server.
	 *
	 * @return void
	 */
	public static function ensure_install_secret() {
		$existing = get_option( self::OPT_INSTALL_SECRET, '' );
		if ( '' !== $existing ) {
			return;
		}
		update_option(
			self::OPT_INSTALL_SECRET,
			bin2hex( random_bytes( 32 ) ),
			false // not autoloaded
		);
	}

	/**
	 * Return SHA-256(install_secret) for the X-JZSA-Install header.
	 * Generates the secret on demand if missing (upgrade path safety net).
	 *
	 * @return string 64-char lowercase hex string
	 */
	public static function get_install_secret_hash() {
		$secret = (string) get_option( self::OPT_INSTALL_SECRET, '' );
		if ( '' === $secret ) {
			self::ensure_install_secret();
			$secret = (string) get_option( self::OPT_INSTALL_SECRET, '' );
		}
		return hash( 'sha256', $secret );
	}

	/**
	 * Build the default header set for outbound community API calls.
	 *
	 * Always includes X-JZSA-Install. Optionally adds Bearer auth and a
	 * JSON Content-Type. Use this for every wp_remote_* call to the
	 * community API so per-install authorization is uniform.
	 *
	 * @param array{auth?: bool, json?: bool} $opts
	 * @return array<string, string>
	 */
	public static function api_headers( array $opts = array() ) {
		$headers = array(
			'X-JZSA-Install' => self::get_install_secret_hash(),
		);
		if ( ! empty( $opts['auth'] ) ) {
			$jwt = self::get_jwt();
			if ( '' !== $jwt ) {
				$headers['Authorization'] = 'Bearer ' . $jwt;
			}
		}
		if ( ! empty( $opts['json'] ) ) {
			$headers['Content-Type'] = 'application/json';
		}
		return $headers;
	}

	/**
	 * Cached result of verify_connection() for the current request.
	 * Prevents the double HTTP call that happens when enqueue_scripts() and
	 * render_content() both call verify_connection() on the same page load.
	 *
	 * @var string|null
	 */
	private static $cached_connection_state = null;

	/**
	 * Mirror display_name and display_url from a successful /v1/me response
	 * into the current user's meta. The server is the source of truth; if a
	 * field comes back empty or null, the local mirror is cleared so the UI
	 * reflects that state rather than showing a stale value.
	 *
	 * @param array $response Raw wp_remote_get response from /v1/me.
	 */
	private static function sync_profile_meta_from_me_response( $response ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( array_key_exists( 'display_name', $body ) ) {
			$name = sanitize_text_field( (string) ( $body['display_name'] ?? '' ) );
			if ( '' !== $name ) {
				update_user_meta( $user_id, self::OPT_DISPLAY_NAME, $name );
			} else {
				delete_user_meta( $user_id, self::OPT_DISPLAY_NAME );
			}
		}

		if ( array_key_exists( 'display_url', $body ) ) {
			$url = sanitize_url( (string) ( $body['display_url'] ?? '' ) );
			if ( '' !== $url ) {
				update_user_meta( $user_id, self::OPT_DISPLAY_URL, $url );
			} else {
				delete_user_meta( $user_id, self::OPT_DISPLAY_URL );
			}
		}
	}

	/**
	 * Verify the stored JWT against the community server.
	 *
	 * Returns one of three states:
	 *   'connected'    : server confirmed the user exists and is not banned
	 *   'disconnected' : server returned 401/403 (token invalid/expired/banned); JWT cleared
	 *   'server_error' : server returned 5xx or was unreachable; JWT kept, error shown
	 *
	 * Result is cached for the duration of the current request so calling this
	 * method from both enqueue_scripts() and render_content() costs only one
	 * outbound HTTP request.
	 *
	 * On a successful response, display_name and display_url from the server
	 * are mirrored into user meta so the local cache stays in sync with the
	 * authoritative value. If the API is unreachable, user meta is left alone
	 * and the UI falls back to whatever was last cached.
	 *
	 * @return string 'connected' | 'disconnected' | 'server_error'
	 */
	public static function verify_connection() {
		if ( null !== self::$cached_connection_state ) {
			return self::$cached_connection_state;
		}

		$jwt = self::get_jwt();

		if ( empty( $jwt ) ) {
			self::$cached_connection_state = 'disconnected';
			return self::$cached_connection_state;
		}

		$response = wp_remote_get(
			JZSA_COMMUNITY_API_URL . '/v1/me',
			array(
				'headers' => array_merge(
					self::api_headers( array( 'auth' => true ) ),
					array( 'Accept' => 'application/json' )
				),
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::$cached_connection_state = 'server_error';
			return self::$cached_connection_state;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			self::sync_profile_meta_from_me_response( $response );
			self::$cached_connection_state = 'connected';
			return self::$cached_connection_state;
		}

		if ( 401 === $code || 403 === $code ) {
			// Token is invalid, expired, account banned, or the install row
			// was reassigned to another community account (silent takeover on
			// the same WP install). Clear the local JWT and stash a one-shot
			// transient explaining WHY so the next page render can show a
			// meaningful notice instead of just landing on a logged-out state.
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$api_error_key = is_array( $body ) && isset( $body['error'] ) ? (string) $body['error'] : '';
			switch ( $api_error_key ) {
				case 'install_not_authorized':
				case 'install_missing':
					$notice_value = 'session_replaced';
					break;
				case 'Account not found':
					$notice_value = 'account_deleted_remote';
					break;
				case 'Account is banned':
					$notice_value = 'account_banned';
					break;
				default:
					$notice_value = 'token_invalid';
					break;
			}
			set_transient( self::NONCE_NOTICE_KEY . get_current_user_id(), $notice_value, 5 * MINUTE_IN_SECONDS );
			delete_user_meta( get_current_user_id(), self::OPT_JWT );
			self::$cached_connection_state = 'disconnected';
			return self::$cached_connection_state;
		}

		// 5xx or unexpected: server is having issues, keep the JWT.
		self::$cached_connection_state = 'server_error';
		return self::$cached_connection_state;
	}

	/**
	 * Send a JSON error response indicating the community server is unreachable.
	 * Centralised so the message is defined exactly once across all AJAX handlers.
	 */
	private static function json_error_server_unreachable() {
		wp_send_json_error( __( 'Could not reach the community server.', 'janzeman-shared-albums-for-google-photos' ) );
	}

	// -------------------------------------------------------------------------
	// Script / style enqueue
	// -------------------------------------------------------------------------

	/**
	 * Enqueue community JS and CSS on the Community page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( JZSA_Admin_Pages::COMMUNITY_SLUG !== $page ) {
			return;
		}

		$script_path = plugin_dir_path( JZSA_PLUGIN_FILE ) . 'assets/js/community.js';
		$style_path  = plugin_dir_path( JZSA_PLUGIN_FILE ) . 'assets/css/community.css';
		$script_ver  = file_exists( $script_path ) ? JZSA_VERSION . '.' . intval( filemtime( $script_path ) ) : JZSA_VERSION;
		$style_ver   = file_exists( $style_path ) ? JZSA_VERSION . '.' . intval( filemtime( $style_path ) ) : JZSA_VERSION;

		wp_enqueue_script(
			'jzsa-community',
			plugins_url( 'assets/js/community.js', JZSA_PLUGIN_FILE ),
			array( 'jzsa-admin-settings' ),
			$script_ver,
			true
		);

		wp_localize_script(
			'jzsa-community',
			'jzsaCommunity',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'jzsa_community' ),
				'isConnected' => 'connected' === self::verify_connection(),
				'displayName' => get_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME, true ) ?: '',
				'displayUrl'  => get_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL, true ) ?: '',
				'isLocalEnv'  => function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type(),
				'i18n'        => self::get_i18n_strings(),
			)
		);

		wp_enqueue_style(
			'jzsa-community',
			plugins_url( 'assets/css/community.css', JZSA_PLUGIN_FILE ),
			array(),
			$style_ver
		);
	}

	// -------------------------------------------------------------------------
	// Page content renderer (called by JZSA_Admin_Pages)
	// -------------------------------------------------------------------------

	/**
	 * Render the community page content (inside the page shell + nav).
	 */
	public static function render_content() {
		$connection_state = self::verify_connection();
		$connected        = 'connected' === $connection_state;
		$i18n             = self::get_i18n_strings();

		// Show one-shot transient notice after a successful magic-link verification
		$notice_key     = self::NONCE_NOTICE_KEY . get_current_user_id();
		$notice         = get_transient( $notice_key );
		$just_connected = 'connected' === $notice;
		if ( $notice ) {
			delete_transient( $notice_key );
		}
		?>

		<?php if ( 'connected' === $notice ) : ?>
		<div class="notice notice-success is-dismissible jzsa-community-notice">
			<p><?php esc_html_e( 'Signed in to community.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( 'signed_out' === $notice ) : ?>
		<div class="notice notice-info is-dismissible jzsa-community-notice">
			<p><?php esc_html_e( 'Signed out of this site.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( 'account_deleted' === $notice ) : ?>
		<div class="notice notice-info is-dismissible jzsa-community-notice">
			<p><?php esc_html_e( 'Community account permanently deleted.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
		</div>
		<?php endif; ?>

		<?php
		// Notices set by verify_connection() when the API rejected the stored
		// JWT. Each one tells the user WHY they're suddenly signed out, so
		// they don't think the page is broken.
		?>
		<?php if ( 'session_replaced' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible jzsa-community-notice">
			<p>
				<strong><?php esc_html_e( 'Signed out: another community account took over this WordPress install.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
				<?php esc_html_e( 'Only one community account can be bound to a given WordPress install at a time. Someone (possibly you, from another WP admin user) signed in to a different community account from this site, which replaced your binding. Sign in again with your email to restore access. Anything you previously published is still on your community account.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( 'account_deleted_remote' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible jzsa-community-notice">
			<p>
				<strong><?php esc_html_e( 'Signed out: your community account no longer exists.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
				<?php esc_html_e( 'The community server reports that this account has been deleted. If you did not delete it yourself, you can create a fresh account with the same or a different email below.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( 'account_banned' === $notice ) : ?>
		<div class="notice notice-error is-dismissible jzsa-community-notice">
			<p>
				<strong><?php esc_html_e( 'Signed out: this community account has been suspended.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
				<?php esc_html_e( 'Please contact the plugin author if you believe this is a mistake.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( 'token_invalid' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible jzsa-community-notice">
			<p>
				<strong><?php esc_html_e( 'Signed out: your community session expired or became invalid.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
				<?php esc_html_e( 'Sign in again with your email to continue.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( 'server_error' === $connection_state ) : ?>
		<div class="notice notice-error is-dismissible jzsa-community-notice">
			<p>
				<strong><?php esc_html_e( 'Community server error.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
				<?php esc_html_e( 'Something is wrong with the community server. Please try again later. If the problem persists, please report it.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<!-- Offline banner: shown by JS when the server cannot be reached -->
		<div id="jzsa-community-offline" class="jzsa-community-offline-banner" style="display:none;" role="alert">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<strong><?php esc_html_e( 'Community service unreachable.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
				<p><?php esc_html_e( 'We cannot reach the community server right now. Please try again later.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
			</div>
		</div>

		<!-- Error banner: shown by JS when the server returns a 5xx error -->
		<div id="jzsa-community-server-error" class="jzsa-community-error-banner" style="display:none;" role="alert">
			<span class="dashicons dashicons-dismiss"></span>
			<div>
				<strong><?php esc_html_e( 'Community server error.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
				<p><?php esc_html_e( 'Something is wrong with the community server. Please report this error.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
			</div>
		</div>

		<br>

		<div class="jzsa-community-intro" style="margin:0px 24px 16px 24px;">
			<p class="jzsa-help-text" style="margin:0;">
				<?php esc_html_e( 'This page is a friendly space for sharing album presentation ideas. Browse shortcode samples from other plugin users, learn from their settings, and adapt the layouts to make your own albums more useful, personal, or beautiful.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
			<?php if ( $connected ) : // Two-audience legend explains who sees what you share. Only meaningful once the user is signed in and considering publishing, so it stays hidden for signed-out visitors. ?>
			<p class="jzsa-community-audience-caption" style="margin:14px 0 6px; font-weight:600; color:#1d2327;">
				<?php esc_html_e( 'Where your shared samples can appear', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
			<div class="jzsa-community-audience-legend" aria-label="<?php esc_attr_e( 'Audience legend', 'janzeman-shared-albums-for-google-photos' ); ?>">
				<span class="jzsa-community-audience-chip jzsa-community-audience-chip--plugin">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<span>
						<strong><?php esc_html_e( 'WP admins community', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
						<span class="jzsa-audience-chip-status" style="display:inline-block; margin-left:6px; padding:1px 6px; font-size:11px; font-weight:600; color:#1e7a3b; background:#e6f4ea; border-radius:10px;"><?php esc_html_e( 'This plugin page (live)', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						<br>
						<?php esc_html_e( 'Other WordPress admins and site builders see your shortcode samples and settings here. Focus: This plugin, the shortcodes, the technicalities of how the gallery is built.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</span>
				<span class="jzsa-community-audience-chip jzsa-community-audience-chip--public">
					<span class="dashicons dashicons-camera-alt" aria-hidden="true"></span>
					<span>
						<strong><?php esc_html_e( 'Photo lovers community', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
						<span class="jzsa-audience-chip-status" style="display:inline-block; margin-left:6px; padding:1px 6px; font-size:11px; font-weight:600; color:#92400e; background:#fef3c7; border-radius:10px;"><?php esc_html_e( 'External website (does not exist yet)', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						<br>
						<?php esc_html_e( 'A future, externally-visible site where selected albums may be featured for a broader photography-loving audience. Focus: The content, the gallery itself, your beautiful photos, only with your explicit consent.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</span>
			</div>
			<?php endif; ?>
		</div>

		<!-- Section 2: Account -->
		<?php
		// Open by default when the visitor isn't signed in - otherwise the
		// only sign-in CTA is the small summary badge of a collapsed
		// <details>, which first-time visitors miss. Also open right after a
		// successful confirmation so the "you're in" state is visible without
		// an extra click.
		$account_open_attr = ( $just_connected || ! $connected ) ? 'open' : '';
		?>
		<details class="jzsa-section jzsa-community-account-section jzsa-collapsible-section" <?php echo esc_attr( $account_open_attr ); ?>>
			<?php if ( $connected ) : ?>
				<summary class="jzsa-collapsible-summary">
					<?php esc_html_e( 'Your Community Account', 'janzeman-shared-albums-for-google-photos' ); ?>
					<span class="jzsa-summary-badge jzsa-summary-badge--connected">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Signed in', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</summary>
			<?php else : ?>
				<summary class="jzsa-collapsible-summary">
					<?php esc_html_e( 'Your Community Account', 'janzeman-shared-albums-for-google-photos' ); ?>
					<span class="jzsa-summary-badge jzsa-summary-badge--disconnected">
						<?php esc_html_e( 'Not signed in. Sign in to publish or rate shortcode samples.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</summary>
			<?php endif; ?>
			<p class="jzsa-help-text" style="margin-bottom:12px;">
				<?php esc_html_e( 'Sign in to publish your own shortcode samples, manage the samples you shared, and rate samples from other plugin users.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
			<?php if ( $connected ) : ?>
				<div class="jzsa-community-status jzsa-community-status--connected">
					<span class="dashicons dashicons-yes-alt" style="color:#46b450; font-size:22px; vertical-align:middle; margin-right:6px;"></span>
					<strong><?php esc_html_e( 'Signed in to community', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
					<button type="button" class="button button-secondary jzsa-community-signout-btn" style="margin-left:14px;">
						<?php esc_html_e( 'Sign out of this site', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
				</div>
				<p class="jzsa-help-text" style="margin-top:6px; margin-bottom:0;">
					<?php esc_html_e( 'Signing out clears the local credential on this site. You can sign back in any time without re-confirming by email.', 'janzeman-shared-albums-for-google-photos' ); ?>
				</p>
				<!-- Display name row -->
				<div class="jzsa-community-display-name-row" style="margin-top:10px; display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
					<span style="font-size:13px; color:#50575e;"><?php esc_html_e( 'Your name/nick:', 'janzeman-shared-albums-for-google-photos' ); ?></span>
					<span id="jzsa-display-name-view" style="font-weight:600;">
						<?php
						$saved_name = get_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME, true ) ?: '';
						if ( $saved_name ) {
							echo esc_html( $saved_name );
						} else {
							echo '<em style="color:#999;">' . esc_html__( 'Not set', 'janzeman-shared-albums-for-google-photos' ) . '</em>';
						}
						?>
					</span>
					<button type="button" class="button-link" id="jzsa-display-name-edit-btn" style="font-size:13px;">
						<?php esc_html_e( 'Edit', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
					<span id="jzsa-display-name-edit-row" style="display:none; align-items:center; gap:6px; flex-wrap:wrap;">
						<input type="text" id="jzsa-display-name-input" maxlength="50"
							value="<?php echo esc_attr( get_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME, true ) ?: '' ); ?>"
							placeholder="<?php esc_attr_e( 'Your name or nickname…', 'janzeman-shared-albums-for-google-photos' ); ?>"
							style="width:220px;">
						<button type="button" class="button button-primary" id="jzsa-display-name-save-btn">
							<?php esc_html_e( 'Save', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
						<button type="button" class="button" id="jzsa-display-name-cancel-btn">
							<?php esc_html_e( 'Cancel', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
						<button type="button" class="button-link" id="jzsa-display-name-generate-btn" title="<?php esc_attr_e( 'Generate a random nickname', 'janzeman-shared-albums-for-google-photos' ); ?>">
							<?php esc_html_e( '🎲 Generate nickname', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
						<span class="description" style="font-size:12px; color:#666; margin-top:4px; display:block;"><?php esc_html_e( 'Required, minimum 3 letters.', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						<span id="jzsa-display-name-result" class="jzsa-community-result" aria-live="polite"></span>
					</span>
				</div>
				<!-- Community profile link row -->
				<div class="jzsa-community-display-url-row" style="margin-top:10px; display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
					<span style="font-size:13px; color:#50575e;"><?php esc_html_e( 'Your profile link:', 'janzeman-shared-albums-for-google-photos' ); ?></span>
					<span id="jzsa-display-url-view" style="font-weight:600;">
						<?php
						$saved_url = get_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL, true ) ?: '';
						if ( $saved_url ) {
							echo esc_html( preg_replace( '#^https?://#i', '', untrailingslashit( $saved_url ) ) );
						} else {
							echo '<em style="color:#999;">' . esc_html__( 'Not set', 'janzeman-shared-albums-for-google-photos' ) . '</em>';
						}
						?>
					</span>
					<button type="button" class="button-link" id="jzsa-display-url-edit-btn" style="font-size:13px;">
						<?php esc_html_e( 'Edit', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
					<span id="jzsa-display-url-edit-row" style="display:none; align-items:center; gap:6px; flex-wrap:wrap;">
						<input type="url" id="jzsa-display-url-input" maxlength="2048"
							value="<?php echo esc_attr( get_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL, true ) ?: '' ); ?>"
							placeholder="<?php esc_attr_e( 'https://your-portfolio.example', 'janzeman-shared-albums-for-google-photos' ); ?>"
							style="width:260px;">
						<button type="button" class="button button-primary" id="jzsa-display-url-save-btn">
							<?php esc_html_e( 'Save', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
						<button type="button" class="button" id="jzsa-display-url-clear-btn">
							<?php esc_html_e( 'Disable', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
						<button type="button" class="button" id="jzsa-display-url-cancel-btn">
							<?php esc_html_e( 'Cancel', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
						<span id="jzsa-display-url-result" class="jzsa-community-result" aria-live="polite"></span>
						<span class="description" style="display:block; margin-top:4px;">
							<?php esc_html_e( 'Shown next to your name on every sample you publish in the community gallery. A personal link about you (portfolio, social profile, personal site), not the WordPress site this plugin runs on.', 'janzeman-shared-albums-for-google-photos' ); ?>
						</span>
					</span>
				</div>
				<!-- Your authorized sites -->
				<details class="jzsa-community-installs-section jzsa-collapsible-section" style="margin-top:20px;">
					<summary class="jzsa-collapsible-summary">
						<?php esc_html_e( 'Your authorized sites', 'janzeman-shared-albums-for-google-photos' ); ?>
					</summary>
					<p class="jzsa-help-text" style="margin-top:6px;">
						<?php esc_html_e( 'WordPress sites you have signed in from. Removing a site revokes its access; signing in again from that site will require a fresh email confirmation. To leave the account from this site, use Sign out (keeps it authorized) or Delete account (permanent).', 'janzeman-shared-albums-for-google-photos' ); ?>
					</p>
					<div id="jzsa-community-installs-list" class="jzsa-community-installs-list" aria-live="polite">
						<p class="jzsa-help-text" style="color:#666;">
							<?php esc_html_e( 'Loading…', 'janzeman-shared-albums-for-google-photos' ); ?>
						</p>
					</div>
				</details>

				<!-- Destructive panel: delete the whole community account -->
				<details class="jzsa-community-danger-zone" style="margin-top:24px; border-top:1px solid #dcdcde; padding-top:12px;">
					<summary style="cursor:pointer; color:#d63638; font-weight:600;">
						<?php esc_html_e( 'Delete community account', 'janzeman-shared-albums-for-google-photos' ); ?>
					</summary>
					<p class="jzsa-help-text" style="margin-top:8px;">
						<?php esc_html_e( 'Permanently removes your account and email from our database, all shortcodes you have shared in the community, all ratings you have given to other community shortcodes, and access from any other WordPress sites you have signed in from. This cannot be undone.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</p>
					<p style="margin-top:10px;">
						<button type="button" class="button jzsa-community-delete-account-btn" style="color:#d63638; border-color:#d63638;">
							<?php esc_html_e( 'Delete my community account', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
					</p>
				</details>
			<?php else : ?>
				<p class="jzsa-help-text" style="margin-top:6px;">
					<?php esc_html_e( 'Privacy: Your email is used to confirm it is really you and is stored to identify your account. You can delete your account from this page at any time to permanently remove it.', 'janzeman-shared-albums-for-google-photos' ); ?>
				</p>
				<?php
				$current_user            = wp_get_current_user();
				$suggested_connect_name  = sanitize_text_field( $current_user->display_name ?? '' );
				$suggested_connect_name  = self::truncate_string( $suggested_connect_name, 50 );
				$suggested_connect_email = sanitize_email( $current_user->user_email ?? '' );
				// Community profile link is intentionally NOT prefilled. It is a personal
				// presentation link (portfolio, social profile, a personal site)
				// shown next to the author name on every published sample, and
				// is usually not the current WP site. Prefilling with home_url()
				// would push users toward the wrong default.
				?>
				<p class="jzsa-community-email-row" style="margin-top:10px; display:flex; align-items:center; flex-wrap:nowrap; gap:6px;">
					<label for="jzsa-connect-email" style="font-size:13px; color:#50575e; white-space:nowrap;">
						<?php esc_html_e( 'Email:', 'janzeman-shared-albums-for-google-photos' ); ?>
					</label>
					<input type="email" id="jzsa-connect-email" maxlength="254" required
						value="<?php echo esc_attr( $suggested_connect_email ); ?>"
						placeholder="<?php esc_attr_e( 'you@example.com', 'janzeman-shared-albums-for-google-photos' ); ?>"
						style="width:260px;">
					<span class="description" style="white-space:nowrap;">
						<?php esc_html_e( 'Required. We email you a one-time confirmation link.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</p>
				<p class="jzsa-help-text" style="margin-top:4px; margin-bottom:8px;">
					<?php esc_html_e( 'Running multiple WordPress sites? Use the same email on each to keep everything under one community account.', 'janzeman-shared-albums-for-google-photos' ); ?>
				</p>
				<p class="jzsa-community-display-name-row" style="margin-top:10px; display:flex; align-items:center; flex-wrap:nowrap; gap:6px;">
					<label for="jzsa-connect-display-name" style="font-size:13px; color:#50575e; white-space:nowrap;">
						<?php esc_html_e( 'Community display name:', 'janzeman-shared-albums-for-google-photos' ); ?>
					</label>
					<input type="text" id="jzsa-connect-display-name" maxlength="50"
						value="<?php echo esc_attr( $suggested_connect_name ); ?>"
						placeholder="<?php esc_attr_e( 'Your name or nickname…', 'janzeman-shared-albums-for-google-photos' ); ?>"
						style="width:220px;">
					<span class="description" style="white-space:nowrap;">
						<?php esc_html_e( 'Required, minimum 3 letters.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
					<button type="button" class="button-link" id="jzsa-connect-display-name-generate-btn" style="white-space:nowrap;" title="<?php esc_attr_e( 'Generate a random nickname', 'janzeman-shared-albums-for-google-photos' ); ?>">
						<?php esc_html_e( '🎲 Generate nickname', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
				</p>
				<p class="jzsa-community-display-url-row" style="margin-top:10px; display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
					<label for="jzsa-connect-display-url" style="font-size:13px; color:#50575e;">
						<?php esc_html_e( 'Community profile link:', 'janzeman-shared-albums-for-google-photos' ); ?>
					</label>
					<input type="url" id="jzsa-connect-display-url" maxlength="2048"
						value=""
						placeholder="<?php esc_attr_e( 'https://your-portfolio.example', 'janzeman-shared-albums-for-google-photos' ); ?>"
						style="width:260px;">
					<span class="description">
						<?php esc_html_e( 'Optional. A personal link about you, shown next to your name on every sample you publish in the community gallery. Most people use their portfolio, social profile, or personal site, not the WordPress site this plugin runs on.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</p>
				<p class="jzsa-help-text" style="margin-top:4px; margin-bottom:8px; font-style:italic;">
					<?php esc_html_e( 'If you already have a community account under this email, your existing display name and community profile link will be kept. Anything you type above is only used if this is a new account.', 'janzeman-shared-albums-for-google-photos' ); ?>
				</p>
				<p style="margin-top:12px;">
					<button type="button" class="button button-primary jzsa-community-connect-btn">
						<?php esc_html_e( 'Sign in to community', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
					<span class="jzsa-community-auth-status" aria-live="polite" style="margin-left:12px;"></span>
				</p>
				<div class="jzsa-community-pending-panel" style="display:none; margin-top:18px; padding:16px 18px 16px 16px; border:2px solid #d63638; border-left-width:6px; border-radius:6px; background:#fdf2f2; box-shadow:0 1px 0 rgba(0,0,0,0.04);">
					<div style="display:flex; gap:14px; align-items:flex-start;">
						<span class="dashicons dashicons-email-alt" aria-hidden="true" style="color:#d63638; font-size:32px; width:32px; height:32px; flex-shrink:0; margin-top:2px;"></span>
						<div style="flex:1; min-width:0;">
							<p style="margin:0 0 6px; font-size:15px; color:#1d2327;">
								<strong style="font-size:16px;"><?php esc_html_e( 'Check your email.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
								<?php esc_html_e( 'We sent a one-time confirmation link to', 'janzeman-shared-albums-for-google-photos' ); ?>
								<span class="jzsa-community-pending-email" style="font-weight:700; color:#b32d2e;"></span>.
							</p>
							<p class="jzsa-help-text" style="margin:0 0 6px; color:#3c434a;">
								<?php esc_html_e( 'Click the link in the email to finish signing in. This page will update automatically. The link is valid for 15 minutes.', 'janzeman-shared-albums-for-google-photos' ); ?>
							</p>
							<p class="jzsa-help-text" style="margin:0 0 10px; color:#3c434a; font-size:12px;">
								<?php esc_html_e( 'Email not arriving? Check your spam / junk folder. Some providers (Hotmail, Outlook, Yahoo) delay automated mail by several minutes. Microsoft and Yahoo email addresses are the most common offenders.', 'janzeman-shared-albums-for-google-photos' ); ?>
							</p>
							<p style="margin:0; display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
								<button type="button" class="button-link jzsa-community-pending-cancel-btn">
									<?php esc_html_e( 'Use a different email address', 'janzeman-shared-albums-for-google-photos' ); ?>
								</button>
								<span class="jzsa-community-pending-status" aria-live="polite" style="color:#50575e; font-size:12px;"></span>
							</p>
						</div>
					</div>
				</div>
				<details class="jzsa-community-signin-recovery" style="margin-top:18px; font-size:13px;">
					<summary style="cursor:pointer; color:#50575e;">
						<?php esc_html_e( 'Trouble signing in?', 'janzeman-shared-albums-for-google-photos' ); ?>
					</summary>
					<p class="jzsa-help-text" style="margin:8px 0;">
						<?php esc_html_e( 'If signing in keeps failing for an unclear reason, you can reset this WordPress site\'s community state and start over. This does NOT delete any community account; it only forgets this site\'s local credential and identifier so the next sign-in starts fresh. Any sample you have already published stays on your community account and remains visible to others.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</p>
					<p style="margin:8px 0 0;">
						<button type="button" class="button jzsa-community-reset-install-btn" style="color:#d63638; border-color:#d63638;">
							<?php esc_html_e( 'Reset this site\'s community state', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
					</p>
				</details>
			<?php endif; ?>
		</details>

		<?php if ( $connected ) : ?>
		<!-- Section 3: Share a New Sample (connected only) -->
		<details class="jzsa-section jzsa-community-share-section jzsa-collapsible-section" id="jzsa-publish-details">
			<summary class="jzsa-collapsible-summary">
				<?php esc_html_e( 'Share a New Sample', 'janzeman-shared-albums-for-google-photos' ); ?>
			</summary>
			<p class="jzsa-help-text" style="margin-top:0;">
				<?php esc_html_e( 'Share a gallery configuration sample with other WordPress admins and site builders using this plugin. The goal is to show useful shortcode settings and rendered results that others can adapt.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
			<div class="jzsa-community-visibility-note">
				<span class="jzsa-community-audience-icon jzsa-community-audience-icon--plugin">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
				</span>
				<div>
					<strong><?php esc_html_e( 'Who sees what?', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
					<?php esc_html_e( 'Other WP admins see what you decide to share below.', 'janzeman-shared-albums-for-google-photos' ); ?>
					<strong><?php esc_html_e( 'They do not see the real album link as plain text', 'janzeman-shared-albums-for-google-photos' ); ?></strong><?php esc_html_e( '; published shortcodes replace it with ', 'janzeman-shared-albums-for-google-photos' ); ?><strong><?php esc_html_e( 'link="hidden-album-link"', 'janzeman-shared-albums-for-google-photos' ); ?></strong><?php esc_html_e( '. The unmasked link is stored only for editing and preview rendering.', 'janzeman-shared-albums-for-google-photos' ); ?>
				</div>
			</div>
			<table class="form-table jzsa-community-publish-table">
				<tr>
					<th scope="row">
						<label for="jzsa-pub-title">
							<?php esc_html_e( 'Title', 'janzeman-shared-albums-for-google-photos' ); ?>
							<span class="required" aria-label="<?php esc_attr_e( 'required', 'janzeman-shared-albums-for-google-photos' ); ?>">
								<?php esc_html_e( 'Required', 'janzeman-shared-albums-for-google-photos' ); ?>
							</span>
						</label>
					</th>
					<td>
						<input type="text" id="jzsa-pub-title" class="regular-text" maxlength="120"
							placeholder="<?php esc_attr_e( 'e.g. Dark slider with mosaic strip', 'janzeman-shared-albums-for-google-photos' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jzsa-pub-shortcode">
							<?php esc_html_e( 'Shortcode', 'janzeman-shared-albums-for-google-photos' ); ?>
							<span class="required" aria-label="<?php esc_attr_e( 'required', 'janzeman-shared-albums-for-google-photos' ); ?>">
								<?php esc_html_e( 'Required', 'janzeman-shared-albums-for-google-photos' ); ?>
							</span>
						</label>
					</th>
					<td>
						<p class="description">
							<?php esc_html_e( 'Paste your shortcode below and click the Apply button to preview what you are about to share.', 'janzeman-shared-albums-for-google-photos' ); ?>
						</p>
						<div class="jzsa-code-block jzsa-community-publish-shortcode-block">
							<code id="jzsa-pub-shortcode" data-placeholder='[jzsa-album link="https://photos.google.com/share/..." mode="slider"]' aria-label="<?php esc_attr_e( 'Shortcode', 'janzeman-shared-albums-for-google-photos' ); ?>"></code>
						</div>
						<div id="jzsa-pub-preview" class="jzsa-preview-container jzsa-playground-preview jzsa-community-publish-preview" aria-live="polite"></div>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jzsa-pub-description">
							<?php echo esc_html( $i18n['descriptionLabel'] ); ?>
							<span class="required jzsa-showcase-required-badge" hidden aria-label="<?php esc_attr_e( 'required for Photo lovers community', 'janzeman-shared-albums-for-google-photos' ); ?>">
								<?php echo esc_html( $i18n['showcaseRequiredBadge'] ); ?>
							</span>
						</label>
					</th>
					<td>
						<textarea id="jzsa-pub-description" class="large-text" rows="2" maxlength="500" placeholder="<?php esc_attr_e( 'What makes this shortcode interesting or special? A short note helps others decide if it fits their needs.', 'janzeman-shared-albums-for-google-photos' ); ?>"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jzsa-pub-tags"><?php esc_html_e( 'Tags', 'janzeman-shared-albums-for-google-photos' ); ?></label>
					</th>
					<td>
						<input type="text" id="jzsa-pub-tags" class="regular-text"
							placeholder="<?php esc_attr_e( 'slider, dark, mosaic  (comma-separated, max 5)', 'janzeman-shared-albums-for-google-photos' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jzsa-pub-site-url">
							<?php echo esc_html( $i18n['siteUrlLabel'] ); ?>
							<span class="required jzsa-showcase-required-badge" hidden aria-label="<?php esc_attr_e( 'required for Photo lovers community', 'janzeman-shared-albums-for-google-photos' ); ?>">
								<?php echo esc_html( $i18n['showcaseRequiredBadge'] ); ?>
							</span>
						</label>
					</th>
					<td>
						<input type="url" id="jzsa-pub-site-url" class="regular-text" maxlength="2048"
							placeholder="<?php esc_attr_e( 'https://yoursite.com/page-with-album', 'janzeman-shared-albums-for-google-photos' ); ?>">
						<p class="description">
							<?php esc_html_e( 'A link to the page on your site where this shortcode is used. Shown publicly on your community entry.', 'janzeman-shared-albums-for-google-photos' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jzsa-pub-photographer-name">
							<?php echo esc_html( $i18n['photographerNameLabel'] ); ?>
							<span class="required jzsa-showcase-required-badge" hidden aria-label="<?php esc_attr_e( 'required for Photo lovers community', 'janzeman-shared-albums-for-google-photos' ); ?>">
								<?php echo esc_html( $i18n['showcaseRequiredBadge'] ); ?>
							</span>
						</label>
					</th>
					<td>
						<?php
						// Prefill from the user's community display name so the entry
						// author matches what the user already set in the Account section
						// above. Editable per entry (e.g. for a guest photographer's
						// album published from the same account).
						$photographer_default = get_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME, true ) ?: '';
						?>
						<input type="text" id="jzsa-pub-photographer-name" class="regular-text" maxlength="120"
							value="<?php echo esc_attr( $photographer_default ); ?>">
						<p class="description">
							<?php esc_html_e( 'Shown as the author of this sample. Defaults to your community display name; override only if a different person created this specific album.', 'janzeman-shared-albums-for-google-photos' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jzsa-pub-photographer-bio"><?php echo esc_html( $i18n['photographerBioLabel'] ); ?></label>
					</th>
					<td>
						<textarea id="jzsa-pub-photographer-bio" class="large-text" rows="2" maxlength="500"></textarea>
						<p class="description">
							<?php echo esc_html( $i18n['photographerBioHelp'] ); ?>
						</p>
					</td>
				</tr>
			</table>
			<div class="jzsa-community-showcase-consent-cell">
				<?php
				// The yellow scope warning. Shown in full on first visit so the user
				// reads the FUTURE / external nature once; collapsed to a small "?"
				// pill on subsequent visits via the OPT_SHOWCASE_WARNING_DISMISSED
				// user meta. Both elements are rendered server-side; JS only flips
				// inline display so re-expanding never needs another HTTP round-trip.
				$showcase_warning_dismissed = (bool) get_user_meta( get_current_user_id(), self::OPT_SHOWCASE_WARNING_DISMISSED, true );
				$warning_initial_display    = $showcase_warning_dismissed ? 'none'         : 'flex';
				$compact_initial_display    = $showcase_warning_dismissed ? 'inline-flex' : 'none';
				?>
				<button type="button" class="jzsa-community-showcase-scope-warning-compact" style="display:<?php echo esc_attr( $compact_initial_display ); ?>; align-items:center; gap:6px; padding:4px 10px; margin-bottom:10px; font-size:12px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:12px; cursor:pointer;" aria-expanded="false">
					<span class="dashicons dashicons-editor-help" style="font-size:14px; width:14px; height:14px;" aria-hidden="true"></span>
					<?php esc_html_e( 'Why a separate Photo lovers community?', 'janzeman-shared-albums-for-google-photos' ); ?>
				</button>
				<div class="jzsa-community-showcase-scope-warning" style="display:<?php echo esc_attr( $warning_initial_display ); ?>; gap:10px; align-items:flex-start; padding:10px 12px; margin-bottom:12px; border-left:4px solid #d97706; background:#fffbeb; border-radius:3px;">
					<span class="dashicons dashicons-camera-alt" style="color:#d97706; margin-top:2px;" aria-hidden="true"></span>
					<div style="flex:1;">
						<strong><?php esc_html_e( 'The settings below are about the future Photo lovers community, not this plugin page.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
						<p class="description" style="margin:4px 0 0;">
							<?php esc_html_e( 'The Photo lovers community will be a separate, externally-visible site so anyone on the internet can read it - not only WP admins. These checkboxes only affect whether your sample is eligible to appear there, and whether your shortcode shows up next to your photos when it does. They have no effect on what other WordPress admins see on this very page.', 'janzeman-shared-albums-for-google-photos' ); ?>
						</p>
					</div>
					<button type="button" class="jzsa-community-showcase-scope-warning-dismiss" aria-label="<?php esc_attr_e( 'Got it, hide this explanation', 'janzeman-shared-albums-for-google-photos' ); ?>" style="background:none; border:0; cursor:pointer; color:#92400e; padding:2px 4px; font-size:13px; white-space:nowrap; align-self:flex-start;">
						<span class="dashicons dashicons-no-alt" style="font-size:18px; width:18px; height:18px;" aria-hidden="true"></span>
						<?php esc_html_e( 'Got it', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
				</div>
				<label style="display:flex; align-items:center; gap:8px;">
					<input type="checkbox" id="jzsa-pub-showcase-consent" class="jzsa-pub-showcase-consent-toggle" value="1" checked>
					<span class="jzsa-community-audience-icon jzsa-community-audience-icon--public">
						<span class="dashicons dashicons-camera-alt" aria-hidden="true"></span>
					</span>
					<span><?php echo esc_html( $i18n['showcaseConsentLabel'] ); ?></span>
				</label>
				<p class="description" style="margin-top:6px;">
					<?php echo esc_html( $i18n['showcaseConsentHelp'] ); ?>
				</p>
				<label class="jzsa-community-showcase-shortcode-visibility">
					<input type="checkbox" id="jzsa-pub-showcase-show-shortcode" class="jzsa-pub-showcase-show-shortcode-toggle" value="1" checked>
					<span><?php echo esc_html( $i18n['showcaseShowShortcodeLabel'] ); ?></span>
				</label>
				<p class="description jzsa-community-showcase-shortcode-visibility-help">
					<?php echo esc_html( $i18n['showcaseShowShortcodeHelp'] ); ?>
				</p>
			</div>
			<p style="margin-top:12px;">
				<button type="button" class="button button-primary" id="jzsa-community-publish-btn">
					<?php esc_html_e( 'Publish to Community', 'janzeman-shared-albums-for-google-photos' ); ?>
				</button>
				<?php if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) : ?>
				<button type="button" class="button button-secondary" id="jzsa-community-dev-fill-btn" style="margin-left:8px;" title="Dev only: fills the form with random data">
					🎲 <?php esc_html_e( 'Fill Random', 'janzeman-shared-albums-for-google-photos' ); ?>
				</button>
				<?php endif; ?>
				<span id="jzsa-publish-result" class="jzsa-community-result" aria-live="polite" style="margin-left:12px;"></span>
			</p>
		</details>

		<!-- Section 4: Edit / Delete Your Published Samples (connected only) -->
		<details class="jzsa-section jzsa-community-my-section jzsa-collapsible-section">
			<summary class="jzsa-collapsible-summary">
				<?php esc_html_e( 'Edit / Delete Your Published Samples', 'janzeman-shared-albums-for-google-photos' ); ?>
				<span class="jzsa-summary-badge" id="jzsa-my-entries-count"></span>
			</summary>
			<div id="jzsa-community-my-entries" class="jzsa-community-my-entries" aria-live="polite">
				<p class="jzsa-community-loading"><?php esc_html_e( 'Loading…', 'janzeman-shared-albums-for-google-photos' ); ?></p>
			</div>
		</details>
		<?php endif; ?>

		<!-- Section 5: Community Samples (open by default) -->
		<details class="jzsa-section jzsa-community-browse-section jzsa-collapsible-section" open>
			<summary class="jzsa-collapsible-summary">
				<?php esc_html_e( 'Community Samples', 'janzeman-shared-albums-for-google-photos' ); ?>
				<span class="jzsa-summary-badge" id="jzsa-community-entries-count"></span>
			</summary>
			<p class="jzsa-help-text" style="margin-top:0;">
				<?php esc_html_e( 'Browse shortcode samples shared by other plugin users. These samples are intended for WordPress admins and site builders looking for gallery layout ideas. Copy a sample, replace the link parameter with your own album, and use Apply to preview small tweaks until the layout fits your site.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
			<div class="jzsa-community-search-row">
				<input type="search" id="jzsa-community-search" class="regular-text"
					placeholder="<?php esc_attr_e( 'Search titles, descriptions and tags…', 'janzeman-shared-albums-for-google-photos' ); ?>">
				<button type="button" class="button" id="jzsa-community-search-btn">
					<?php esc_html_e( 'Search', 'janzeman-shared-albums-for-google-photos' ); ?>
				</button>
			</div>
			<div class="jzsa-community-sort-row">
				<span class="jzsa-community-sort-label"><?php esc_html_e( 'Sort:', 'janzeman-shared-albums-for-google-photos' ); ?></span>
				<button type="button" class="button jzsa-community-sort-btn jzsa-community-sort-btn--active" data-sort="interactions">
					<?php esc_html_e( 'Most Used', 'janzeman-shared-albums-for-google-photos' ); ?>
				</button>
				<button type="button" class="button jzsa-community-sort-btn" data-sort="rating">
					<?php esc_html_e( 'Top Rated', 'janzeman-shared-albums-for-google-photos' ); ?>
				</button>
				<button type="button" class="button jzsa-community-sort-btn" data-sort="newest">
					<?php esc_html_e( 'Newest', 'janzeman-shared-albums-for-google-photos' ); ?>
				</button>
			</div>
			<div id="jzsa-community-entries" class="jzsa-community-entries" aria-live="polite">
				<p class="jzsa-community-loading"><?php esc_html_e( 'Loading…', 'janzeman-shared-albums-for-google-photos' ); ?></p>
			</div>
			<div id="jzsa-community-pagination" class="jzsa-community-pagination"></div>
		</details>

		<details class="jzsa-section jzsa-community-grow-section jzsa-collapsible-section" open>
			<summary class="jzsa-collapsible-summary">
				<?php esc_html_e( 'Help This Plugin Grow', 'janzeman-shared-albums-for-google-photos' ); ?>
			</summary>
			<?php
			$admin_pages_bottom = new JZSA_Admin_Pages();
			$admin_pages_bottom->render_unhappy_section();
			$admin_pages_bottom->render_happy_section();
			?>
		</details>

		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * Sign in to community. Starts the email-verification flow.
	 *
	 * Sends email + install_secret_hash + display_name + display_url +
	 * site_url + verification_url + challenge to /v1/auth/start. The backend
	 * either issues a JWT immediately (idempotent reconnect - this install
	 * is already authorized for this email) or queues a pending verification
	 * and emails the user a one-time confirmation link. In the second case
	 * the JS polls ajax_signin_poll until the user clicks the link.
	 */
	public function ajax_signin_start() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		$display_url  = self::normalize_display_url( wp_unslash( $_POST['display_url'] ?? '' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( __( 'Please enter a valid email address.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}
		if ( '' === $display_name || self::letter_count( $display_name ) < 3 ) {
			wp_send_json_error( __( 'Display name must contain at least 3 letters.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}
		if ( self::string_length( $display_name ) > 50 ) {
			wp_send_json_error( __( 'Display name must be 50 characters or fewer.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}
		if ( ! self::is_valid_display_url( $display_url ) ) {
			wp_send_json_error( __( 'Please enter a valid URL for your community profile link, or leave it empty.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$site_url      = home_url();
		$challenge     = wp_generate_password( 40, false, false );
		$transient_key = self::AUTH_CHALLENGE_PREFIX . hash( 'sha256', $challenge );
		set_transient( $transient_key, $challenge, 5 * MINUTE_IN_SECONDS );

		$payload = array(
			'email'               => $email,
			'display_name'        => $display_name,
			'site_url'            => $site_url,
			'install_secret_hash' => self::get_install_secret_hash(),
			'verification_url'    => rest_url( 'jzsa/v1/community-challenge' ),
			'challenge'           => $challenge,
		);
		if ( '' !== $display_url ) {
			$payload['display_url'] = $display_url;
		}

		$response = wp_remote_post(
			JZSA_COMMUNITY_API_URL . '/v1/auth/start',
			array(
				'headers' => self::api_headers( array( 'json' => true ) ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			delete_transient( $transient_key );
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( 200 !== $code ) {
			delete_transient( $transient_key );
			$api_error = isset( $body['error'] ) ? (string) $body['error'] : '';
			$message   = self::signin_error_message( $api_error, $code );
			wp_send_json_error( $message );
			return;
		}

		$state = isset( $body['state'] ) ? (string) $body['state'] : '';

		if ( 'connected' === $state ) {
			// Idempotent reconnect: install was already authorized for this email.
			self::store_signin_response( $body, $display_name, $display_url );
			wp_send_json_success( array( 'state' => 'connected' ) );
			return;
		}

		if ( 'pending' === $state ) {
			$pending_id = isset( $body['pending_id'] ) ? (int) $body['pending_id'] : 0;
			if ( $pending_id <= 0 ) {
				wp_send_json_error( __( 'Invalid response from community server.', 'janzeman-shared-albums-for-google-photos' ) );
				return;
			}
			// Hold the pending id locally so the polling loop can resume even if
			// the user reloads the admin page mid-flight (transient TTL gives a
			// 16-minute window - just past the backend's 15-minute pending TTL).
			set_transient(
				self::SIGNIN_PENDING_PREFIX . get_current_user_id(),
				$pending_id,
				16 * MINUTE_IN_SECONDS
			);
			wp_send_json_success( array( 'state' => 'pending', 'email' => $email ) );
			return;
		}

		delete_transient( $transient_key );
		wp_send_json_error( __( 'Unexpected response from community server.', 'janzeman-shared-albums-for-google-photos' ) );
	}

	/**
	 * Poll the backend for an in-progress sign-in. The JS calls this every
	 * few seconds after ajax_signin_start returned state=pending. The first
	 * call after the user clicks the confirmation link returns the JWT.
	 */
	public function ajax_signin_poll() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$transient_key = self::SIGNIN_PENDING_PREFIX . get_current_user_id();
		$pending_id    = (int) get_transient( $transient_key );
		if ( $pending_id <= 0 ) {
			wp_send_json_error( array( 'state' => 'not_found' ) );
			return;
		}

		$response = wp_remote_get(
			JZSA_COMMUNITY_API_URL . '/v1/auth/poll?pending_id=' . $pending_id,
			array(
				'headers' => self::api_headers(),
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( 202 === $code ) {
			wp_send_json_success( array( 'state' => 'pending' ) );
			return;
		}

		if ( 200 === $code ) {
			delete_transient( $transient_key );
			self::store_signin_response( $body );
			wp_send_json_success( array( 'state' => 'connected' ) );
			return;
		}

		// 404 = pending not found, 410 = expired. Either way, give up locally.
		delete_transient( $transient_key );
		$state = ( 410 === $code ) ? 'expired' : 'not_found';
		wp_send_json_error( array( 'state' => $state ) );
	}

	/**
	 * Translate a /auth/start API error code into a user-facing message.
	 * Falls back to a generic "server returned an error (NNN)" line when
	 * the code is unrecognized so new backend errors do not surface as
	 * blank strings.
	 *
	 * @param string $api_error The `error` field from the API JSON, or empty string.
	 * @param int    $http_code The HTTP status the API returned.
	 * @return string A localized, user-facing error message.
	 */
	private static function signin_error_message( string $api_error, int $http_code ): string {
		// HTTP-status-first branches catch broad categories (rate limits,
		// transport errors) regardless of which specific body.error code the
		// API put in the response.
		if ( 429 === $http_code ) {
			return __( 'Too many sign-in attempts in a short period. Please wait a few minutes and try again.', 'janzeman-shared-albums-for-google-photos' );
		}
		switch ( $api_error ) {
			case 'email_send_failed':
				return __( 'Email sending failed. Please try again in a moment. If the problem persists, please report a bug.', 'janzeman-shared-albums-for-google-photos' );
			case 'site_verification_failed':
				return __( 'We could not verify that this site is running the plugin. Make sure the plugin is active and reachable, then try again.', 'janzeman-shared-albums-for-google-photos' );
			case 'too_many_installs':
				return __( 'You have reached the maximum number of authorized sites on this account. Remove an old one from "Your authorized sites" before adding another.', 'janzeman-shared-albums-for-google-photos' );
			case 'account_banned':
				return __( 'This community account has been suspended. Please contact support.', 'janzeman-shared-albums-for-google-photos' );
		}
		return sprintf(
			/* translators: %d: HTTP status code */
			__( 'Community server returned an error (%d). Please try again.', 'janzeman-shared-albums-for-google-photos' ),
			$http_code
		);
	}

	/**
	 * Persist the JWT + profile fields returned by /auth/start (connected
	 * branch) or /auth/poll. Server-side display_name/url win when both are
	 * present. The account is the source of truth across all installs.
	 *
	 * @param array  $body       Parsed JSON response from the API.
	 * @param string $typed_name Optional fallback used only if the API did
	 *                           not return a value (fresh signup).
	 * @param string $typed_url  Same as $typed_name, for display_url.
	 */
	private static function store_signin_response( array $body, string $typed_name = '', string $typed_url = '' ) {
		$jwt = isset( $body['jwt'] ) ? (string) $body['jwt'] : '';
		if ( '' === $jwt ) {
			return;
		}
		update_user_meta( get_current_user_id(), self::OPT_JWT, $jwt );

		$server_name = isset( $body['display_name'] ) ? sanitize_text_field( (string) $body['display_name'] ) : '';
		$name        = '' !== $server_name ? $server_name : $typed_name;
		if ( '' !== $name ) {
			update_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME, $name );
		}

		$server_url = isset( $body['display_url'] ) ? sanitize_url( (string) $body['display_url'] ) : '';
		$url        = '' !== $server_url ? $server_url : $typed_url;
		if ( '' !== $url ) {
			update_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL, $url );
		} else {
			delete_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL );
		}
	}

	/**
	 * Browse community entries (proxy to backend GET /v1/entries).
	 */
	public function ajax_browse() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$page = absint( $_POST['page'] ?? 1 );
		if ( $page < 1 ) {
			$page = 1;
		}

		$q   = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
		$tag = sanitize_text_field( wp_unslash( $_POST['tag'] ?? '' ) );

		$sort          = sanitize_text_field( wp_unslash( $_POST['sort'] ?? 'newest' ) );
		$allowed_sorts = array( 'newest', 'interactions', 'rating' );
		if ( ! in_array( $sort, $allowed_sorts, true ) ) {
			$sort = 'newest';
		}

		$url = JZSA_COMMUNITY_API_URL . '/v1/entries?per_page=12&page=' . $page;
		if ( $q ) {
			$url .= '&q=' . rawurlencode( $q );
		}
		if ( $tag ) {
			$url .= '&tag=' . rawurlencode( $tag );
		}
		$url .= '&sort=' . rawurlencode( $sort );

		$request_args = array(
			'timeout' => 10,
			'headers' => array_merge(
				self::api_headers(),
				array(
					'X-JZSA-Plugin-Key' => JZSA_COMMUNITY_PLUGIN_READ_KEY,
					'Accept'            => 'application/json',
				)
			),
		);
		$jwt = self::get_jwt();
		if ( ! empty( $jwt ) ) {
			$request_args['headers']['Authorization'] = 'Bearer ' . $jwt;
		}

		$response = wp_remote_get( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'code' => 'server_unreachable' ) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 500 ) {
			wp_send_json_error( array( 'code' => 'server_error' ) );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_success( $body );
	}

	/**
	 * Publish an entry (proxy to backend POST /v1/entries).
	 */
	public function ajax_publish() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not connected. Please connect to the community first.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$shortcode   = sanitize_textarea_field( wp_unslash( $_POST['shortcode'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$tags_raw    = sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) );
		$entry_url   = sanitize_url( wp_unslash( $_POST['site_url'] ?? '' ) );
		$photographer_name = sanitize_text_field( wp_unslash( $_POST['photographer_name'] ?? '' ) );
		$photographer_bio  = sanitize_textarea_field( wp_unslash( $_POST['photographer_bio'] ?? '' ) );
		$consent     = filter_var( wp_unslash( $_POST['public_showcase_consent'] ?? false ), FILTER_VALIDATE_BOOLEAN );
		// Default TRUE: most authors want the shortcode visible alongside
		// their photos on the showcase. Stored unconditionally; the
		// showcase renderer ANDs it with consent at display time.
		$show_shortcode = array_key_exists( 'public_showcase_show_shortcode', $_POST )
			? filter_var( wp_unslash( $_POST['public_showcase_show_shortcode'] ), FILTER_VALIDATE_BOOLEAN )
			: true;
		if ( ! empty( $entry_url ) && ! preg_match( '#^https?://#i', $entry_url ) ) {
			$entry_url = 'https://' . $entry_url;
		}

		$validation_error = self::validate_community_entry_payload( $title, $shortcode, $description, $tags_raw, $entry_url, $photographer_name, $photographer_bio, $consent );
		if ( $validation_error ) {
			wp_send_json_error( $validation_error );
			return;
		}

		$photographer_name = self::truncate_string( $photographer_name, 120 );
		$photographer_bio  = self::truncate_string( $photographer_bio, 500 );
		$tags = self::normalize_community_tags( $tags_raw );
		$tags = array_slice( $tags, 0, 5 );

		$payload = array(
			'title'                   => $title,
			'shortcode'               => $shortcode,
			'description'             => $description,
			'tags'                    => $tags,
			'plugin_version'          => JZSA_VERSION,
			'site_url'                => $entry_url ?: null,
			'photographer_name'       => $photographer_name ?: null,
			'photographer_bio'        => $photographer_bio ?: null,
			'public_showcase_consent'        => $consent,
			'public_showcase_show_shortcode' => $show_shortcode,
		);

		$response = wp_remote_post(
			JZSA_COMMUNITY_API_URL . '/v1/entries',
			array(
				'headers' => self::api_headers( array( 'auth' => true, 'json' => true ) ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 201 === $code ) {
			wp_send_json_success( $body );
		} else {
			$msg = $body['error'] ?? sprintf(
				/* translators: %d: HTTP status code */
				__( 'Server error (%d).', 'janzeman-shared-albums-for-google-photos' ),
				$code
			);
			wp_send_json_error( array(
				'message' => $msg,
				'details' => $body['details'] ?? null,
			) );
		}
	}

	/**
	 * Delete one of the user's own entries.
	 */
	public function ajax_delete_entry() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not connected.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$entry_id = absint( $_POST['entry_id'] ?? 0 );
		if ( ! $entry_id ) {
			wp_send_json_error( __( 'Invalid entry ID.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/entries/' . $entry_id,
			array(
				'method'  => 'DELETE',
				'headers' => self::api_headers( array( 'auth' => true ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 204 === $code || 200 === $code ) {
			wp_send_json_success();
		} else {
			wp_send_json_error(
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Could not delete entry (code %d).', 'janzeman-shared-albums-for-google-photos' ),
					$code
				)
			);
		}
	}

	/**
	 * List authorized installs for the current account (proxy to
	 * GET /v1/me/installs). Used by the "Your authorized sites" panel.
	 */
	public function ajax_list_installs() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not signed in.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_get(
			JZSA_COMMUNITY_API_URL . '/v1/me/installs',
			array(
				'headers' => self::api_headers( array( 'auth' => true ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			wp_send_json_error(
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Could not load your authorized sites (%d).', 'janzeman-shared-albums-for-google-photos' ),
					$code
				)
			);
			return;
		}

		wp_send_json_success( $body );
	}

	/**
	 * Revoke one authorized install (proxy to DELETE /v1/me/installs/:id).
	 * The backend refuses to revoke the install making the request (409
	 * cannot_revoke_current_install). For that case the user should Sign
	 * out or Delete account instead.
	 */
	public function ajax_remove_install() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not signed in.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$install_id = absint( $_POST['install_id'] ?? 0 );
		if ( $install_id <= 0 ) {
			wp_send_json_error( __( 'Invalid install id.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/me/installs/' . $install_id,
			array(
				'method'  => 'DELETE',
				'headers' => self::api_headers( array( 'auth' => true ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 204 === $code ) {
			wp_send_json_success();
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		wp_send_json_error(
			array(
				'code'    => $body['error'] ?? null,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Could not remove that site (%d).', 'janzeman-shared-albums-for-google-photos' ),
					$code
				),
			)
		);
	}

	/**
	 * Local-only recovery: reset all community state for this WP install.
	 *
	 * Removes jzsa_install_secret from wp_options (a new one regenerates on
	 * next sign-in, with a different install_secret_hash, so the API treats
	 * this WP as a fresh install with no possible conflict). Also clears
	 * every WP user's jzsa_community_* user_meta on this install, because
	 * any cached JWTs are now keyed to the old install hash and would fail
	 * on the next /v1/me anyway.
	 *
	 * Does not call the API. Works even when the API is unreachable. The
	 * previous user_installs row on the API side is left as an orphan that
	 * the original account owner can clean up from their "Your authorized
	 * sites" list on any other WP install they have.
	 *
	 * Last-resort recovery affordance for users stuck in any sign-in dead
	 * end. The takeover behavior in /auth/confirm covers the common cases
	 * automatically; this handler is the manual escape hatch for anything
	 * the automatic recovery does not.
	 */
	public function ajax_reset_install_state() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		delete_option( self::OPT_INSTALL_SECRET );

		// Drop community JWT + profile fields for ALL WP users on this install.
		// They are all invalid the moment install_secret changes, so leaving
		// them around would just produce phantom "Signed in" badges.
		global $wpdb;
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => self::OPT_JWT ) );
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => self::OPT_DISPLAY_NAME ) );
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => self::OPT_DISPLAY_URL ) );

		// Sweep any in-flight sign-in transients so a half-finished attempt
		// doesn't keep its pending_id around after the reset.
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_' . self::SIGNIN_PENDING_PREFIX ) . '%',
			$wpdb->esc_like( '_transient_timeout_' . self::SIGNIN_PENDING_PREFIX ) . '%',
			$wpdb->esc_like( '_transient_' . self::AUTH_CHALLENGE_PREFIX ) . '%',
			$wpdb->esc_like( '_transient_timeout_' . self::AUTH_CHALLENGE_PREFIX ) . '%'
		) );

		wp_send_json_success();
	}

	/**
	 * Sign out of this site (local-only).
	 *
	 * Clears the JWT and cached profile from this WP install's user_meta.
	 * Does not touch the community backend; the install remains authorized
	 * on the account, so signing in again from this site does not require a
	 * fresh email confirmation. Use ajax_delete_account for permanent
	 * account removal.
	 */
	public function ajax_signout() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		delete_user_meta( get_current_user_id(), self::OPT_JWT );
		delete_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME );
		delete_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL );
		set_transient( self::NONCE_NOTICE_KEY . get_current_user_id(), 'signed_out', 60 );
		wp_send_json_success();
	}

	/**
	 * Delete the community account (permanent, hard delete server-side).
	 *
	 * Calls DELETE /v1/me which cascade-removes the user row, their
	 * authorized installs, their published entries, their ratings, and the
	 * interaction events on their entries. No way to undo.
	 */
	public function ajax_delete_account() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not signed in.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/me',
			array(
				'method'  => 'DELETE',
				'headers' => self::api_headers( array( 'auth' => true ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 204 === $code || 200 === $code ) {
			delete_user_meta( get_current_user_id(), self::OPT_JWT );
			delete_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME );
			delete_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL );
			set_transient( self::NONCE_NOTICE_KEY . get_current_user_id(), 'account_deleted', 60 );
			wp_send_json_success();
		} else {
			wp_send_json_error(
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Could not delete account (code %d).', 'janzeman-shared-albums-for-google-photos' ),
					$code
				)
			);
		}
	}

	/**
	 * Load the current user's own entries (proxy to GET /v1/me).
	 */
	public function ajax_load_my_entries() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not connected.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_get(
			JZSA_COMMUNITY_API_URL . '/v1/me',
			array(
				'headers' => self::api_headers( array( 'auth' => true ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 500 ) {
			wp_send_json_error( array( 'code' => 'server_error' ) );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code ) {
			wp_send_json_success( $body['entries'] ?? array() );
		} else {
			$msg = $body['error'] ?? sprintf(
				/* translators: %d: HTTP status code */
				__( 'Server error (%d).', 'janzeman-shared-albums-for-google-photos' ),
				$code
			);
			wp_send_json_error( $msg );
		}
	}

	/**
	 * Update one of the user's own community entries.
	 */
	public function ajax_update_entry() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not connected.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$entry_id  = absint( $_POST['entry_id'] ?? 0 );
		$title     = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$shortcode = sanitize_textarea_field( wp_unslash( $_POST['shortcode'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$tags_raw  = sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) );
		$entry_url = sanitize_url( wp_unslash( $_POST['site_url'] ?? '' ) );
		$photographer_name = sanitize_text_field( wp_unslash( $_POST['photographer_name'] ?? '' ) );
		$photographer_bio  = sanitize_textarea_field( wp_unslash( $_POST['photographer_bio'] ?? '' ) );
		if ( ! empty( $entry_url ) && ! preg_match( '#^https?://#i', $entry_url ) ) {
			$entry_url = 'https://' . $entry_url;
		}
		$consent = array_key_exists( 'public_showcase_consent', $_POST )
			? filter_var( wp_unslash( $_POST['public_showcase_consent'] ), FILTER_VALIDATE_BOOLEAN )
			: null;
		$show_shortcode = array_key_exists( 'public_showcase_show_shortcode', $_POST )
			? filter_var( wp_unslash( $_POST['public_showcase_show_shortcode'] ), FILTER_VALIDATE_BOOLEAN )
			: null;

		if ( ! $entry_id ) {
			wp_send_json_error( __( 'Invalid entry ID.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$validation_error = self::validate_community_entry_payload( $title, $shortcode, $description, $tags_raw, $entry_url, $photographer_name, $photographer_bio, true === $consent );
		if ( $validation_error ) {
			wp_send_json_error( $validation_error );
			return;
		}

		$photographer_name = self::truncate_string( $photographer_name, 120 );
		$photographer_bio  = self::truncate_string( $photographer_bio, 500 );
		$tags = self::normalize_community_tags( $tags_raw );
		$tags = array_slice( $tags, 0, 5 );

		$body = array(
			'title'              => $title,
			'shortcode'          => $shortcode,
			'description'        => $description,
			'tags'               => $tags,
			'site_url'           => $entry_url ?: null,
			'photographer_name'  => $photographer_name ?: null,
			'photographer_bio'   => $photographer_bio ?: null,
		);
		if ( $consent !== null ) {
			$body['public_showcase_consent'] = $consent;
		}
		// show_shortcode is the author's stated preference, stored
		// unconditionally. The showcase renderer ANDs it with consent at
		// render time so it does not need to be coupled here.
		if ( $show_shortcode !== null ) {
			$body['public_showcase_show_shortcode'] = $show_shortcode;
		}

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/entries/' . $entry_id,
			array(
				'method'  => 'PATCH',
				'headers' => self::api_headers( array( 'auth' => true, 'json' => true ) ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code ) {
			wp_send_json_success();
		} else {
			$msg = $body['error'] ?? sprintf(
				/* translators: %d: HTTP status code */
				__( 'Server error (%d).', 'janzeman-shared-albums-for-google-photos' ),
				$code
			);
			wp_send_json_error( $msg );
		}
	}

	/**
	 * Update display name: save to backend and to wp_options.
	 */
	public function ajax_update_display_name() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not connected.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );

		if ( empty( $display_name ) ) {
			wp_send_json_error( __( 'Display name cannot be empty.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		if ( self::letter_count( $display_name ) < 3 ) {
			wp_send_json_error( __( 'Display name must contain at least 3 letters.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		if ( self::string_length( $display_name ) > 50 ) {
			wp_send_json_error( __( 'Display name must be 50 characters or fewer.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/me/display-name',
			array(
				'method'  => 'PUT',
				'headers' => self::api_headers( array( 'auth' => true, 'json' => true ) ),
				'body'    => wp_json_encode( array( 'display_name' => $display_name ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code ) {
			// Mirror in user meta so we can display it without a round-trip
			update_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME, sanitize_text_field( $body['display_name'] ?? $display_name ) );
			wp_send_json_success( array( 'display_name' => $body['display_name'] ?? $display_name ) );
		} else {
			$msg = $body['error'] ?? sprintf(
				/* translators: %d: HTTP status code */
				__( 'Server error (%d).', 'janzeman-shared-albums-for-google-photos' ),
				$code
			);
			wp_send_json_error( $msg );
		}
	}

	/**
	 * Update display URL: save to backend and mirror to user meta.
	 */
	public function ajax_update_display_url() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not connected.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$display_url = self::normalize_display_url( wp_unslash( $_POST['display_url'] ?? '' ) );

		if ( ! self::is_valid_display_url( $display_url ) ) {
			wp_send_json_error( __( 'Please enter a valid URL for your community profile link, or leave it empty.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/me/display-url',
			array(
				'method'  => 'PUT',
				'headers' => self::api_headers( array( 'auth' => true, 'json' => true ) ),
				'body'    => wp_json_encode( array( 'display_url' => $display_url ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code ) {
			$saved_url = sanitize_url( $body['display_url'] ?? '' );
			if ( '' !== $saved_url ) {
				update_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL, $saved_url );
			} else {
				delete_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL );
			}
			wp_send_json_success( array( 'display_url' => $saved_url ) );
		} else {
			$msg = $body['error'] ?? sprintf(
				/* translators: %d: HTTP status code */
				__( 'Server error (%d).', 'janzeman-shared-albums-for-google-photos' ),
				$code
			);
			wp_send_json_error( $msg );
		}
	}

	/**
	 * Persist the user's "I've read the showcase scope warning" state so the
	 * next page load can render the compact collapsed pill instead of the
	 * full yellow banner. Stored as a user meta (per-admin), not site-wide,
	 * because the publish form is shown per-admin and dismissals shouldn't
	 * propagate between admins. One-shot, fully reversible (the warning can
	 * be re-expanded on demand via the compact "?" pill).
	 */
	public function ajax_dismiss_showcase_warning() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		update_user_meta( get_current_user_id(), self::OPT_SHOWCASE_WARNING_DISMISSED, 1 );
		wp_send_json_success();
	}

	/**
	 * Track an interaction event (fire-and-forget proxy to backend).
	 * No connection required; JWT is forwarded if available for self-exclusion.
	 */
	public function ajax_interact() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$entry_id    = absint( $_POST['entry_id'] ?? 0 );
		$action_type = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? '' ) );
		$count       = absint( $_POST['count'] ?? 1 );

		if ( ! $entry_id || ! $action_type ) {
			wp_send_json_error( 'Invalid parameters.' );
			return;
		}

		if ( $count < 1 ) {
			$count = 1;
		}

		$count = min( $count, 5 );

		// Forward JWT if connected so the server can apply self-exclusion.
		// Always send X-JZSA-Install so optionalAuth can tie the JWT to this install.
		$headers = self::api_headers( array( 'auth' => true, 'json' => true ) );

		wp_remote_post(
			JZSA_COMMUNITY_API_URL . '/v1/entries/' . $entry_id . '/interact',
			array(
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'action_type' => $action_type,
						'count'       => $count,
					)
				),
				'timeout' => 5,
			)
		);

		// Always return success - fire-and-forget, client should not be blocked.
		wp_send_json_success();
	}

	/**
	 * Submit a star rating for a community entry (requires connection).
	 */
	public function ajax_rate() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'You need to connect to the community to rate entries.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$entry_id = absint( $_POST['entry_id'] ?? 0 );
		$rating   = absint( $_POST['rating'] ?? 0 );

		if ( ! $entry_id || $rating < 1 || $rating > 5 ) {
			wp_send_json_error( __( 'Invalid parameters.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_post(
			JZSA_COMMUNITY_API_URL . '/v1/entries/' . $entry_id . '/rate',
			array(
				'headers' => self::api_headers( array( 'auth' => true, 'json' => true ) ),
				'body'    => wp_json_encode( array( 'rating' => $rating ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$msg = $body['error'] ?? sprintf(
				/* translators: %d: HTTP status code */
				__( 'Server error (%d).', 'janzeman-shared-albums-for-google-photos' ),
				$code
			);
			wp_send_json_error( $msg );
			return;
		}

		wp_send_json_success( $body );
	}

}
