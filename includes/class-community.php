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

	const OPT_JWT           = 'jzsa_community_jwt';
	const OPT_DISPLAY_NAME  = 'jzsa_community_display_name';
	const OPT_DISPLAY_URL   = 'jzsa_community_display_url';
	const NONCE_NOTICE_KEY  = 'jzsa_community_notice_';
	const AUTH_CHALLENGE_PREFIX = 'jzsa_community_auth_challenge_';

	/**
	 * Constructor — register all hooks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Auth flow
		add_action( 'wp_ajax_jzsa_community_request_magic_link', array( $this, 'ajax_request_magic_link' ) );
		add_action( 'wp_ajax_jzsa_community_disconnect',         array( $this, 'ajax_disconnect' ) );

		// Browse & write
		add_action( 'wp_ajax_jzsa_community_browse',               array( $this, 'ajax_browse' ) );
		add_action( 'wp_ajax_jzsa_community_publish',              array( $this, 'ajax_publish' ) );
		add_action( 'wp_ajax_jzsa_community_delete_entry',         array( $this, 'ajax_delete_entry' ) );
		add_action( 'wp_ajax_jzsa_community_delete_account',       array( $this, 'ajax_delete_account' ) );
		add_action( 'wp_ajax_jzsa_community_update_display_name',  array( $this, 'ajax_update_display_name' ) );
		add_action( 'wp_ajax_jzsa_community_update_display_url',   array( $this, 'ajax_update_display_url' ) );
		add_action( 'wp_ajax_jzsa_community_load_my_entries',      array( $this, 'ajax_load_my_entries' ) );
		add_action( 'wp_ajax_jzsa_community_update_entry',         array( $this, 'ajax_update_entry' ) );
		add_action( 'wp_ajax_jzsa_community_interact',             array( $this, 'ajax_interact' ) );
		add_action( 'wp_ajax_jzsa_community_rate',                 array( $this, 'ajax_rate' ) );
	}

	/**
	 * Shared UI strings used by both PHP-rendered and JS-rendered community UI.
	 *
	 * @return array<string, string>
	 */
	private static function get_i18n_strings() {
		return array(
			'showcaseConsentLabel'    => __( 'Allow this example to be considered for a future public showcase.', 'janzeman-shared-albums-for-google-photos' ),
			'showcaseConsentHelp'     => __( 'If selected, this shortcode example and its rendered preview may later appear on a public plugin showcase outside this admin page. Description, sample page URL, and creator name are required for showcase consideration.', 'janzeman-shared-albums-for-google-photos' ),
			'showcaseRequiredBadge'   => __( 'Required for showcase', 'janzeman-shared-albums-for-google-photos' ),
			'showcaseRequiredMessage' => __( 'Description, sample page URL, and photographer / creator name are required for public showcase consideration.', 'janzeman-shared-albums-for-google-photos' ),
			'descriptionLabel'        => __( 'Description', 'janzeman-shared-albums-for-google-photos' ),
			'siteUrlLabel'            => __( 'Sample page URL', 'janzeman-shared-albums-for-google-photos' ),
			'photographerNameLabel'   => __( 'Photographer / creator name or nickname', 'janzeman-shared-albums-for-google-photos' ),
			'photographerBioLabel'    => __( 'Short bio / intro', 'janzeman-shared-albums-for-google-photos' ),
			'photographerBioHelp'     => __( 'A short note about the photographer, creator, studio, or website behind this example.', 'janzeman-shared-albums-for-google-photos' ),
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
	 * Cached result of verify_connection() for the current request.
	 * Prevents the double HTTP call that happens when enqueue_scripts() and
	 * render_content() both call verify_connection() on the same page load.
	 *
	 * @var string|null
	 */
	private static $cached_connection_state = null;

	/**
	 * Verify the stored JWT against the community server.
	 *
	 * Returns one of three states:
	 *   'connected'    — server confirmed the user exists and is not banned
	 *   'disconnected' — server returned 401/403 (token invalid/expired/banned); JWT cleared
	 *   'server_error' — server returned 5xx or was unreachable; JWT kept, error shown
	 *
	 * Result is cached for the duration of the current request so calling this
	 * method from both enqueue_scripts() and render_content() costs only one
	 * outbound HTTP request.
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
				'headers' => array(
					'Authorization' => 'Bearer ' . $jwt,
					'Accept'        => 'application/json',
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
			self::$cached_connection_state = 'connected';
			return self::$cached_connection_state;
		}

		if ( 401 === $code || 403 === $code ) {
			// Token is invalid, expired or account banned — clear it.
			delete_user_meta( get_current_user_id(), self::OPT_JWT );
			self::$cached_connection_state = 'disconnected';
			return self::$cached_connection_state;
		}

		// 5xx or unexpected — server is having issues, keep the JWT.
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
			<p><?php esc_html_e( 'Successfully connected to the JZSA Community!', 'janzeman-shared-albums-for-google-photos' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( 'disconnected' === $notice ) : ?>
		<div class="notice notice-info is-dismissible jzsa-community-notice">
			<p><?php esc_html_e( 'Disconnected from the JZSA Community.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
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
				<?php esc_html_e( 'This page is a friendly space for sharing album presentation ideas. Browse shortcode examples from other plugin users, learn from their settings, and adapt the layouts to make your own albums more useful, personal, or beautiful.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
			<div class="jzsa-community-audience-legend" aria-label="<?php esc_attr_e( 'Audience legend', 'janzeman-shared-albums-for-google-photos' ); ?>">
				<span class="jzsa-community-audience-chip jzsa-community-audience-chip--plugin">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<span>
						<strong><?php esc_html_e( 'Plugin community', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
						<?php esc_html_e( 'WordPress admins and site builders using this plugin can share and explore shortcode examples here.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</span>
				<span class="jzsa-community-audience-chip jzsa-community-audience-chip--public">
					<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
					<span>
						<strong><?php esc_html_e( 'Public showcase', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
						<?php esc_html_e( 'Selected examples may be featured on a public website page for broader audience, but only when the author has given consent.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</span>
			</div>
		</div>

		<!-- Section 2: Account -->
		<details class="jzsa-section jzsa-community-account-section jzsa-collapsible-section" <?php echo $just_connected ? 'open' : ''; ?>>
			<?php if ( $connected ) : ?>
				<summary class="jzsa-collapsible-summary">
					<?php esc_html_e( 'Your Plugin Community Account', 'janzeman-shared-albums-for-google-photos' ); ?>
					<span class="jzsa-summary-badge jzsa-summary-badge--connected">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Connected', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</summary>
			<?php else : ?>
				<summary class="jzsa-collapsible-summary">
					<?php esc_html_e( 'Your Plugin Community Account', 'janzeman-shared-albums-for-google-photos' ); ?>
					<span class="jzsa-summary-badge jzsa-summary-badge--disconnected">
						<?php esc_html_e( 'Not connected. Connect to publish or rate shortcode examples.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</summary>
			<?php endif; ?>
			<p class="jzsa-help-text" style="margin-bottom:12px;">
				<?php esc_html_e( 'Connect to publish your own shortcode examples, manage the examples you shared, and rate examples from other plugin users.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
			<?php if ( $connected ) : ?>
				<div class="jzsa-community-status jzsa-community-status--connected">
					<span class="dashicons dashicons-yes-alt" style="color:#46b450; font-size:22px; vertical-align:middle; margin-right:6px;"></span>
					<strong><?php esc_html_e( 'Connected to Community', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
					<button type="button" class="button button-link jzsa-community-disconnect-btn" style="margin-left:14px; color:#d63638;">
						<?php esc_html_e( 'Disconnect', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
					<button type="button" class="button button-link jzsa-community-delete-account-btn" style="margin-left:8px; color:#d63638;">
						<?php esc_html_e( 'Delete account', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
					<button type="button" class="button button-link jzsa-community-delete-account-entries-btn" style="margin-left:8px; color:#d63638;">
						<?php esc_html_e( 'Delete account &amp; all my entries', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
				</div>
				<!-- Display name row -->
				<div class="jzsa-community-display-name-row" style="margin-top:10px; display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
					<span style="font-size:13px; color:#50575e;"><?php esc_html_e( 'Display author name:', 'janzeman-shared-albums-for-google-photos' ); ?></span>
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
					<button type="button" class="button button-link" id="jzsa-display-name-edit-btn" style="font-size:13px;">
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
						<button type="button" class="button button-link" id="jzsa-display-name-generate-btn" title="<?php esc_attr_e( 'Generate a random nickname', 'janzeman-shared-albums-for-google-photos' ); ?>">
							<?php esc_html_e( '🎲 Generate nickname', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
						<span class="description" style="font-size:12px; color:#666; margin-top:4px; display:block;"><?php esc_html_e( 'Required, minimum 3 letters.', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						<span id="jzsa-display-name-result" class="jzsa-community-result" aria-live="polite"></span>
					</span>
				</div>
				<!-- Display URL row -->
				<div class="jzsa-community-display-url-row" style="margin-top:10px; display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
					<span style="font-size:13px; color:#50575e;"><?php esc_html_e( 'Display site URL:', 'janzeman-shared-albums-for-google-photos' ); ?></span>
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
					<button type="button" class="button button-link" id="jzsa-display-url-edit-btn" style="font-size:13px;">
						<?php esc_html_e( 'Edit', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
					<span id="jzsa-display-url-edit-row" style="display:none; align-items:center; gap:6px; flex-wrap:wrap;">
						<input type="url" id="jzsa-display-url-input" maxlength="2048"
							value="<?php echo esc_attr( get_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL, true ) ?: '' ); ?>"
							placeholder="<?php esc_attr_e( 'https://example.com', 'janzeman-shared-albums-for-google-photos' ); ?>"
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
					</span>
				</div>
			<?php else : ?>
				<p class="jzsa-help-text" style="margin-top:6px;">
					<?php esc_html_e( 'Privacy: Your email is never sent. Your site URL is verified, then stored only as a one-way hash.', 'janzeman-shared-albums-for-google-photos' ); ?>
				</p>
				<?php
				$current_user           = wp_get_current_user();
				$suggested_connect_name = sanitize_text_field( $current_user->display_name ?? '' );
				$suggested_connect_name = self::truncate_string( $suggested_connect_name, 50 );
				$suggested_connect_url  = self::normalize_display_url( home_url() );
				?>
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
					<button type="button" class="button button-link" id="jzsa-connect-display-name-generate-btn" style="white-space:nowrap;" title="<?php esc_attr_e( 'Generate a random nickname', 'janzeman-shared-albums-for-google-photos' ); ?>">
						<?php esc_html_e( '🎲 Generate nickname', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
				</p>
				<p class="jzsa-community-display-url-row" style="margin-top:10px; display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
					<label for="jzsa-connect-display-url" style="font-size:13px; color:#50575e;">
						<?php esc_html_e( 'Community display URL:', 'janzeman-shared-albums-for-google-photos' ); ?>
					</label>
					<input type="url" id="jzsa-connect-display-url" maxlength="2048"
						value="<?php echo esc_attr( $suggested_connect_url ); ?>"
						placeholder="<?php esc_attr_e( 'Optional public URL…', 'janzeman-shared-albums-for-google-photos' ); ?>"
						style="width:260px;">
					<span class="description">
						<?php esc_html_e( 'Optional display URL for your community profile.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</span>
				</p>
				<p style="margin-top:12px;">
					<button type="button" class="button button-primary jzsa-community-connect-btn">
						<?php esc_html_e( 'Connect to Plugin Community', 'janzeman-shared-albums-for-google-photos' ); ?>
					</button>
					<span class="jzsa-community-auth-status" aria-live="polite" style="margin-left:12px;"></span>
				</p>
			<?php endif; ?>
		</details>

		<?php if ( $connected ) : ?>
		<!-- Section 3: Share a Shortcode (connected only) -->
		<details class="jzsa-section jzsa-community-share-section jzsa-collapsible-section" id="jzsa-publish-details">
			<summary class="jzsa-collapsible-summary">
				<?php esc_html_e( 'Share a Shortcode Example', 'janzeman-shared-albums-for-google-photos' ); ?>
			</summary>
			<p class="jzsa-help-text" style="margin-top:0;">
				<?php esc_html_e( 'Share a gallery configuration example with other WordPress admins and site builders using this plugin. The goal is to show useful shortcode settings and rendered results that others can adapt.', 'janzeman-shared-albums-for-google-photos' ); ?>
			</p>
			<div class="jzsa-community-visibility-note">
				<span class="jzsa-community-audience-icon jzsa-community-audience-icon--plugin">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
				</span>
				<div>
					<strong><?php esc_html_e( 'Who sees what?', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
					<?php esc_html_e( 'Other WP admins see your text, tags, sample URL, masked shortcode, preview, and optional creator info. The real album link is stored only for editing and preview rendering.', 'janzeman-shared-albums-for-google-photos' ); ?>
				</div>
			</div>
			<table class="form-table jzsa-community-publish-table">
				<tr>
					<td colspan="2" class="jzsa-community-showcase-consent-cell">
						<label style="display:flex; align-items:center; gap:8px;">
							<input type="checkbox" id="jzsa-pub-showcase-consent" class="jzsa-pub-showcase-consent-toggle" value="1">
							<span class="jzsa-community-audience-icon jzsa-community-audience-icon--public">
								<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
							</span>
							<span><?php echo esc_html( $i18n['showcaseConsentLabel'] ); ?></span>
						</label>
						<p class="description" style="margin-top:6px;">
							<?php echo esc_html( $i18n['showcaseConsentHelp'] ); ?>
						</p>
					</td>
				</tr>
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
						<p class="description">
							<?php esc_html_e( 'Privacy note: Published shortcodes show link="[link]". The real album link is kept only for editing and preview rendering.', 'janzeman-shared-albums-for-google-photos' ); ?>
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
							<span class="required jzsa-showcase-required-badge" hidden aria-label="<?php esc_attr_e( 'required for showcase', 'janzeman-shared-albums-for-google-photos' ); ?>">
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
							<span class="required jzsa-showcase-required-badge" hidden aria-label="<?php esc_attr_e( 'required for showcase', 'janzeman-shared-albums-for-google-photos' ); ?>">
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
							<span class="required jzsa-showcase-required-badge" hidden aria-label="<?php esc_attr_e( 'required for showcase', 'janzeman-shared-albums-for-google-photos' ); ?>">
								<?php echo esc_html( $i18n['showcaseRequiredBadge'] ); ?>
							</span>
						</label>
					</th>
					<td>
						<input type="text" id="jzsa-pub-photographer-name" class="regular-text" maxlength="120">
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
			<div class="jzsa-community-showcase-consent-bottom">
				<label style="display:flex; align-items:center; gap:8px;">
					<input type="checkbox" id="jzsa-pub-showcase-consent-bottom" class="jzsa-pub-showcase-consent-toggle" value="1">
					<span class="jzsa-community-audience-icon jzsa-community-audience-icon--public">
						<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
					</span>
					<span><?php echo esc_html( $i18n['showcaseConsentLabel'] ); ?></span>
				</label>
				<p class="description" style="margin-top:6px;">
					<?php echo esc_html( $i18n['showcaseConsentHelp'] ); ?>
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

		<!-- Section 4: Shortcodes You Shared (connected only) -->
		<details class="jzsa-section jzsa-community-my-section jzsa-collapsible-section">
			<summary class="jzsa-collapsible-summary">
				<?php esc_html_e( 'Your Shared Examples', 'janzeman-shared-albums-for-google-photos' ); ?>
				<span class="jzsa-summary-badge" id="jzsa-my-entries-count"></span>
			</summary>
			<div id="jzsa-community-my-entries" class="jzsa-community-my-entries" aria-live="polite">
				<p class="jzsa-community-loading"><?php esc_html_e( 'Loading…', 'janzeman-shared-albums-for-google-photos' ); ?></p>
			</div>
		</details>
		<?php endif; ?>

		<!-- Section 5: Community Shortcodes (open by default) -->
		<details class="jzsa-section jzsa-community-browse-section jzsa-collapsible-section" open>
			<summary class="jzsa-collapsible-summary">
				<?php esc_html_e( 'Community Shortcode Examples', 'janzeman-shared-albums-for-google-photos' ); ?>
				<span class="jzsa-summary-badge" id="jzsa-community-entries-count"></span>
			</summary>
			<p class="jzsa-help-text" style="margin-top:0;">
				<?php esc_html_e( 'Browse shortcode examples shared by other plugin users. These examples are intended for WordPress admins and site builders looking for gallery layout ideas. Copy an example, replace the link parameter with your own album, and use Apply to preview small tweaks until the layout fits your site.', 'janzeman-shared-albums-for-google-photos' ); ?>
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
	 * Connect to the community: compute identity hash and exchange it for a JWT
	 * in a single server-to-server call. No email or OTP involved.
	 */
	public function ajax_request_magic_link() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$user          = wp_get_current_user();
		$site_url      = home_url();
		$challenge     = wp_generate_password( 40, false, false );
		$transient_key = self::AUTH_CHALLENGE_PREFIX . hash( 'sha256', $challenge );
		$identity_hash = hash( 'sha256', $user->user_email . '|' . $site_url );
		$display_name  = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		$display_url   = self::normalize_display_url( wp_unslash( $_POST['display_url'] ?? '' ) );

		if ( empty( $display_name ) || self::letter_count( $display_name ) < 3 ) {
			wp_send_json_error( __( 'Display name must contain at least 3 letters.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		if ( self::string_length( $display_name ) > 50 ) {
			wp_send_json_error( __( 'Display name must be 50 characters or fewer.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		if ( ! self::is_valid_display_url( $display_url ) ) {
			wp_send_json_error( __( 'Please enter a valid display URL, or leave it empty.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		set_transient( $transient_key, $challenge, 5 * MINUTE_IN_SECONDS );

		// Keep mutable profile data out of the auth/connect request. Display
		// names are updated only through /v1/me/display-name after auth.
		$connect_payload = array(
			'identity_hash'    => $identity_hash,
			'site_url'         => $site_url,
			'verification_url' => rest_url( 'jzsa/v1/community-challenge' ),
			'challenge'        => $challenge,
		);

		$response = wp_remote_post(
			JZSA_COMMUNITY_API_URL . '/v1/auth/connect',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $connect_payload ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			delete_transient( $transient_key );
			self::json_error_server_unreachable();
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			delete_transient( $transient_key );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			wp_send_json_error(
				$body['error'] ?? sprintf(
					/* translators: %d: HTTP status code */
					__( 'Community server returned an error (%d). Please try again.', 'janzeman-shared-albums-for-google-photos' ),
					$code
				)
			);
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$jwt  = $body['jwt'] ?? '';

		if ( empty( $jwt ) ) {
			delete_transient( $transient_key );
			wp_send_json_error( __( 'Invalid response from community server.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		update_user_meta( get_current_user_id(), self::OPT_JWT, $jwt );

		$server_display_name = sanitize_text_field( $body['display_name'] ?? '' );
		if ( '' !== $display_name && $display_name !== $server_display_name ) {
			$display_name_response = wp_remote_request(
				JZSA_COMMUNITY_API_URL . '/v1/me/display-name',
				array(
					'method'  => 'PUT',
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $jwt,
					),
					'body'    => wp_json_encode( array( 'display_name' => $display_name ) ),
					'timeout' => 10,
				)
			);

			if ( ! is_wp_error( $display_name_response ) && 200 === wp_remote_retrieve_response_code( $display_name_response ) ) {
				$display_name_body  = json_decode( wp_remote_retrieve_body( $display_name_response ), true );
				$server_display_name = sanitize_text_field( $display_name_body['display_name'] ?? $display_name );
			}
		}

		if ( '' !== $server_display_name ) {
			update_user_meta( get_current_user_id(), self::OPT_DISPLAY_NAME, $server_display_name );
		}

		$server_display_url = '';
		$display_url_response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/me/display-url',
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $jwt,
				),
				'body'    => wp_json_encode( array( 'display_url' => $display_url ) ),
				'timeout' => 10,
			)
		);

		if ( ! is_wp_error( $display_url_response ) && 200 === wp_remote_retrieve_response_code( $display_url_response ) ) {
			$display_url_body  = json_decode( wp_remote_retrieve_body( $display_url_response ), true );
			$server_display_url = sanitize_url( $display_url_body['display_url'] ?? '' );
		}

		if ( '' !== $server_display_url ) {
			update_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL, $server_display_url );
		} else {
			delete_user_meta( get_current_user_id(), self::OPT_DISPLAY_URL );
		}

		wp_send_json_success();
	}

	/**
	 * Disconnect: remove stored JWT.
	 */
	public function ajax_disconnect() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		delete_user_meta( get_current_user_id(), self::OPT_JWT );
		wp_send_json_success();
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
			'headers' => array(
				'X-JZSA-Plugin-Key' => JZSA_COMMUNITY_PLUGIN_READ_KEY,
				'Accept'            => 'application/json',
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
			'public_showcase_consent' => $consent,
		);

		$response = wp_remote_post(
			JZSA_COMMUNITY_API_URL . '/v1/entries',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $jwt,
				),
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
				'headers' => array( 'Authorization' => 'Bearer ' . $jwt ),
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
	 * Delete the connected account and all its entries (permanent).
	 */
	public function ajax_delete_account() {
		check_ajax_referer( 'jzsa_community', 'nonce' );

		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$jwt = self::get_jwt();
		if ( empty( $jwt ) ) {
			wp_send_json_error( __( 'Not connected.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$delete_entries = filter_var( wp_unslash( $_POST['delete_entries'] ?? false ), FILTER_VALIDATE_BOOLEAN );

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/me',
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Bearer ' . $jwt,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'delete_entries' => $delete_entries ) ),
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
			set_transient( self::NONCE_NOTICE_KEY . get_current_user_id(), 'disconnected', 60 );
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
				'headers' => array( 'Authorization' => 'Bearer ' . $jwt ),
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

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/entries/' . $entry_id,
			array(
				'method'  => 'PATCH',
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $jwt,
				),
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
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $jwt,
				),
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
			wp_send_json_error( __( 'Please enter a valid display URL, or leave it empty.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$response = wp_remote_request(
			JZSA_COMMUNITY_API_URL . '/v1/me/display-url',
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $jwt,
				),
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

		$headers = array( 'Content-Type' => 'application/json' );

		// Forward JWT if connected so the server can apply self-exclusion.
		$jwt = self::get_jwt();
		if ( $jwt ) {
			$headers['Authorization'] = 'Bearer ' . $jwt;
		}

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

		// Always return success — fire-and-forget, client should not be blocked.
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
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $jwt,
				),
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
