<?php
/**
 * Plugin Name: Shared Albums for Google Photos (by JanZeman)
 * Plugin URI: https://github.com/JanZeman/shared-albums-for-google-photos
 * Author URI: https://github.com/JanZeman
 * Description: Display publicly shared Google Photos albums with a modern Swiper-based gallery viewer. Not affiliated with or endorsed by Google LLC.
 * Version: 2.3.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Jan Zeman
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: janzeman-shared-albums-for-google-photos
 * Domain Path: /languages
 *
 * @package JZSA_Shared_Albums
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'JZSA_VERSION', '2.3.0' );

// Community API URL. Local development can override this constant before the plugin loads:
// define( 'JZSA_COMMUNITY_API_URL', 'http://localhost:3000' );
if ( ! defined( 'JZSA_COMMUNITY_API_URL' ) ) {
	define( 'JZSA_COMMUNITY_API_URL', 'https://jzsa.janzeman.com' );
}

// Shared plugin-level read key. Sent by the WP admin proxy with every browse
// request so the community server can return preview_shortcode to all WP admins,
// regardless of whether they have personally connected to the community.
if ( ! defined( 'JZSA_COMMUNITY_PLUGIN_READ_KEY' ) ) {
	define( 'JZSA_COMMUNITY_PLUGIN_READ_KEY', 'bbeacfbe4c938d8216231bb5029ed18808eeecbc37d09a7b1503ba8bc7e7ead4' );
}

define( 'JZSA_PLUGIN_FILE', __FILE__ );
define( 'JZSA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JZSA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JZSA_VERSION_OPTION', 'jzsa_plugin_version' );

/**
 * Get the capability required to access plugin admin pages and admin AJAX actions.
 *
 * Defaulting to edit_pages allows Administrators and Editors on standard
 * WordPress installs, while keeping Authors, Contributors, and Subscribers out.
 *
 * @return string
 */
function jzsa_get_admin_capability() {
	return apply_filters( 'jzsa_admin_capability', 'edit_pages' );
}

/**
 * Shared frontend UI strings used by PHP-rendered markup and JS-rendered markup.
 *
 * @return array<string,string>
 */
function jzsa_get_frontend_i18n_strings() {
	return array(
		'playPauseSpace'        => __( 'Play/Pause (Space)', 'janzeman-shared-albums-for-google-photos' ),
		'playPause'             => __( 'Play/Pause', 'janzeman-shared-albums-for-google-photos' ),
		'pauseSlideshow'        => __( 'Pause slideshow', 'janzeman-shared-albums-for-google-photos' ),
		'resumeSlideshow'       => __( 'Resume slideshow', 'janzeman-shared-albums-for-google-photos' ),
		'previousGalleryPage'   => __( 'Previous gallery page', 'janzeman-shared-albums-for-google-photos' ),
		'nextGalleryPage'       => __( 'Next gallery page', 'janzeman-shared-albums-for-google-photos' ),
		'openInGooglePhotos'    => __( 'Open in Google Photos', 'janzeman-shared-albums-for-google-photos' ),
		'openAlbumGooglePhotos' => __( 'Open album in Google Photos', 'janzeman-shared-albums-for-google-photos' ),
		'openMediaFullscreen'   => __( 'Open media %d in fullscreen', 'janzeman-shared-albums-for-google-photos' ),
		'downloadCurrentMedia'  => __( 'Download current media', 'janzeman-shared-albums-for-google-photos' ),
		'downloadMedia'         => __( 'Download media %d', 'janzeman-shared-albums-for-google-photos' ),
		'largeDownloadWarning'  => __( 'This file is larger than the configured download warning threshold.', 'janzeman-shared-albums-for-google-photos' ),
		'openMediaLightbox'     => __( 'Open media %d in lightbox', 'janzeman-shared-albums-for-google-photos' ),
		'closeLightbox'         => __( 'Close', 'janzeman-shared-albums-for-google-photos' ),
		'lightboxDialogLabel'   => __( 'Photo viewer', 'janzeman-shared-albums-for-google-photos' ),
	);
}

/**
 * Load plugin classes
 */
require_once JZSA_PLUGIN_DIR . 'includes/class-data-provider.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-renderer.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-orchestrator.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-admin-pages.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-community.php';

/**
 * Clear album-level plugin-managed caches.
 *
 * This includes:
 * - album transients
 * - stored album expiry options
 *
 * @return array<string,int>
 */
function jzsa_clear_album_caches() {
	global $wpdb;

	// Direct database queries are safe here as we are deleting only this plugin's own cache keys.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$deleted_album_rows = (int) $wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jzsa_album_%' OR option_name LIKE '_transient_timeout_jzsa_album_%' OR option_name LIKE '_transient_jzsa_backup_album_%' OR option_name LIKE '_transient_timeout_jzsa_backup_album_%'"
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$deleted_expiry_rows = (int) $wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'jzsa_expiry_%'"
	);

	return array(
		'album_transient_rows'      => $deleted_album_rows,
		'photo_meta_transient_rows' => 0,
		'expiry_rows'               => $deleted_expiry_rows,
	);
}

/**
 * Clear per-photo metadata caches.
 *
 * @return array<string,int>
 */
function jzsa_clear_photo_meta_caches() {
	global $wpdb;

	// Direct database queries are safe here as we are deleting only this plugin's own cache keys.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$deleted_photo_meta_rows = (int) $wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jzsa_photo_meta_%' OR option_name LIKE '_transient_timeout_jzsa_photo_meta_%'"
	);

	return array(
		'album_transient_rows'      => 0,
		'photo_meta_transient_rows' => $deleted_photo_meta_rows,
		'expiry_rows'               => 0,
	);
}

/**
 * Clear all plugin-managed caches.
 *
 * This includes:
 * - album transients
 * - per-photo metadata transients
 * - stored album expiry options
 *
 * @return array<string,int>
 */
function jzsa_clear_all_plugin_caches() {
	$album_result = jzsa_clear_album_caches();
	$photo_result = jzsa_clear_photo_meta_caches();

	return array(
		'album_transient_rows'      => (int) $album_result['album_transient_rows'],
		'photo_meta_transient_rows' => (int) $photo_result['photo_meta_transient_rows'],
		'expiry_rows'               => (int) $album_result['expiry_rows'],
	);
}

/**
 * Contribute this plugin's data practices to the site's Privacy Policy draft
 * (Tools → Privacy → Policy in wp-admin). Site owners can then review and
 * incorporate the text when publishing their own privacy page.
 */
function jzsa_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content  = '<h3>' . esc_html__( 'Core gallery feature', 'janzeman-shared-albums-for-google-photos' ) . '</h3>';
	$content .= '<p>' . esc_html__( 'The core gallery feature does not collect, store, or transmit any visitor data. It fetches publicly shared Google Photos albums from photos.google.com and image files from *.googleusercontent.com and caches the result locally in WordPress transients. No personal information is involved.', 'janzeman-shared-albums-for-google-photos' ) . '</p>';

	$content .= '<h3>' . esc_html__( 'Community Directory (optional, opt-in)', 'janzeman-shared-albums-for-google-photos' ) . '</h3>';
	$content .= '<p>' . esc_html__( 'The Community Directory is entirely optional. Browsing community examples makes read-only requests to jzsa.janzeman.com. Account, publishing, and rating features are active only when a WordPress administrator explicitly connects to the community.', 'janzeman-shared-albums-for-google-photos' ) . '</p>';

	$content .= '<ul>';
	$content .= '<li><strong>' . esc_html__( 'No email is transmitted.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'The WordPress admin email and site URL are combined and hashed on your server with SHA-256. Only this one-way hash is sent; the original email cannot be recovered from it.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Double-hashed identity.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'The community server applies a second cryptographic layer (HMAC-SHA256 with a server-side secret) before storing the hash, so the value stored in the database cannot be reversed even with direct database access.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Site verification.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'Your site home URL is transmitted during connection for verification purposes. The community server stores only a SHA-256 hash of the URL for abuse prevention (detecting multiple accounts from the same installation). The hash cannot be reversed.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Community profile.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'If you provide a community display name or display URL while connecting or later editing your community profile, those values are stored by the community server and may be shown publicly with your shared entries. These fields are optional and can be changed or cleared.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Published entry data.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'If you publish an entry, the following is stored on jzsa.janzeman.com: title, shortcode settings, the extracted Google Photos album link, optional description, optional tags, optional sample page URL, optional photographer or creator name, optional short bio, plugin version, and whether you opted into public showcase consideration. You control all of this data.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Author display.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'If you provide a photographer or creator name for an entry, it is shown as that entry\'s author. Otherwise, if you set a community display name, that display name is shown as the author. Entry sample URLs are shown when provided; otherwise, your community display URL may be shown. You can change or remove these values at any time.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Album-link masking.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'Public community responses replace the Google Photos URL in the shortcode with [link]. The real album link is retained by the community server so authenticated users can render a live preview. It is never shown in plain text on the public browse page.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Anonymous interaction signals.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'When a community entry is previewed, copied, or rated, an event may be recorded. The community server hashes the IP address it sees together with the current date (SHA-256) and never stores it in plain text. Because requests are proxied through WordPress, this is normally the WordPress server\'s IP, not a visitor\'s browser IP.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Star ratings.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'Ratings are stored linked to the user\'s identity hash, not to any directly personal data.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'Account deletion.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'You can delete your community account at any time from the plugin admin page. Account deletion removes the stored identity hash, site hash, display name, display URL, and ratings you submitted. You can choose whether published entries are preserved as community examples or hidden at the same time.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '<li><strong>' . esc_html__( 'No tracking.', 'janzeman-shared-albums-for-google-photos' ) . '</strong> ' . esc_html__( 'The community server does not use cookies, analytics, or advertising.', 'janzeman-shared-albums-for-google-photos' ) . '</li>';
	$content .= '</ul>';

	wp_add_privacy_policy_content(
		__( 'Shared Albums for Google Photos', 'janzeman-shared-albums-for-google-photos' ),
		wp_kses_post( $content )
	);
}
add_action( 'admin_init', 'jzsa_add_privacy_policy_content' );

/**
 * Initialize the plugin
 */
function jzsa_init_plugin() {
	// Initialize the main orchestrator with plugin file path
	new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
	new JZSA_Community();

	// Initialize admin pages (admin only).
	if ( is_admin() ) {
		new JZSA_Admin_Pages();
	}
}
add_action( 'init', 'jzsa_init_plugin' );

/**
 * Clear plugin-managed caches once per plugin version bump.
 *
 * Plugin updates do not trigger the activation hook, so compare the stored
 * version against the current code version on load and invalidate stale
 * transients exactly once when they differ.
 */
function jzsa_maybe_run_version_migration() {
	$stored_version = get_option( JZSA_VERSION_OPTION, '' );

	if ( JZSA_VERSION === $stored_version ) {
		return;
	}

	jzsa_clear_all_plugin_caches();

	if ( '' === $stored_version ) {
		add_option( JZSA_VERSION_OPTION, JZSA_VERSION, '', false );
		return;
	}

	update_option( JZSA_VERSION_OPTION, JZSA_VERSION, false );
}
add_action( 'plugins_loaded', 'jzsa_maybe_run_version_migration' );

/**
 * Activation hook
 */
function jzsa_activate() {
	// Clear all plugin caches on activation.
	jzsa_clear_all_plugin_caches();
	update_option( JZSA_VERSION_OPTION, JZSA_VERSION, false );

	// Set a transient to redirect to the Guide page after activation.
	set_transient( 'jzsa_activation_redirect', true, 30 );
}
register_activation_hook( __FILE__, 'jzsa_activate' );

/**
 * Redirect to the Guide page after activation.
 */
function jzsa_activation_redirect() {
	// Only do this once after activation
	if ( get_transient( 'jzsa_activation_redirect' ) ) {
		delete_transient( 'jzsa_activation_redirect' );

		// Don't redirect if activating multiple plugins at once
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress core parameter, read-only check
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Redirect to the canonical Guide page.
		wp_safe_redirect( JZSA_Admin_Pages::get_guide_page_url() );
		exit;
	}
}
add_action( 'admin_init', 'jzsa_activation_redirect' );

/**
 * Add plugin quick links to the plugin listing page.
 *
 * @param array $links Existing plugin action links
 * @return array Modified plugin action links
 */
function jzsa_add_plugin_action_links( $links ) {
	$guide_link = sprintf(
		'<a href="%s">%s</a>',
		JZSA_Admin_Pages::get_guide_page_url(),
		esc_html__( 'Guide', 'janzeman-shared-albums-for-google-photos' )
	);
	$parameters_link = sprintf(
		'<a href="%s">%s</a>',
		JZSA_Admin_Pages::get_shortcode_parameters_page_url(),
		esc_html__( 'Parameters', 'janzeman-shared-albums-for-google-photos' )
	);
	$placeholders_link = sprintf(
		'<a href="%s">%s</a>',
		JZSA_Admin_Pages::get_placeholders_page_url(),
		esc_html__( 'Placeholders', 'janzeman-shared-albums-for-google-photos' )
	);

	array_unshift( $links, $guide_link, $parameters_link, $placeholders_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'jzsa_add_plugin_action_links' );

/**
 * Deactivation hook
 */
function jzsa_deactivate() {
	// Clear all plugin transients on deactivation.
	jzsa_clear_all_plugin_caches();
}
register_deactivation_hook( __FILE__, 'jzsa_deactivate' );
