<?php
/**
 * Plugin Name: Shared Albums for Google Photos (by JanZeman)
 * Plugin URI: https://github.com/JanZeman/shared-albums-for-google-photos
 * Author URI: https://github.com/JanZeman
 * Description: Display publicly shared Google Photos albums with a modern Swiper-based gallery viewer. Not affiliated with or endorsed by Google LLC.
 * Version: 2.1.8
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
define( 'JZSA_VERSION', '2.1.8' );
define( 'JZSA_PLUGIN_FILE', __FILE__ );
define( 'JZSA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JZSA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JZSA_VERSION_OPTION', 'jzsa_plugin_version' );

/**
 * Load plugin classes
 */
require_once JZSA_PLUGIN_DIR . 'includes/class-data-provider.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-renderer.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-orchestrator.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-admin-pages.php';

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
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jzsa_album_%' OR option_name LIKE '_transient_timeout_jzsa_album_%'"
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
 * Initialize the plugin
 */
function jzsa_init_plugin() {
	// Initialize the main orchestrator with plugin file path
	new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );

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
