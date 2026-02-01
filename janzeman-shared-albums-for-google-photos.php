<?php
/**
 * Plugin Name: Shared Albums for Google Photos (by JanZeman)
 * Plugin URI: https://github.com/JanZeman/shared-albums-for-google-photos
 * Author URI: https://github.com/JanZeman
 * Description: Display publicly shared Google Photos albums with a modern Swiper-based gallery viewer. Not affiliated with or endorsed by Google LLC.
 * Version: 1.0.5
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
define( 'JZSA_VERSION', '1.0.5' );
define( 'JZSA_PLUGIN_FILE', __FILE__ );
define( 'JZSA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JZSA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin classes
 */
require_once JZSA_PLUGIN_DIR . 'includes/class-data-provider.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-renderer.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-orchestrator.php';
require_once JZSA_PLUGIN_DIR . 'includes/class-settings-page.php';

/**
 * Initialize the plugin
 */
function jzsa_init_plugin() {
	// Initialize the main orchestrator with plugin file path
	new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );

	// Initialize settings page (admin only)
	if ( is_admin() ) {
		new JZSA_Settings_Page();
	}
}
add_action( 'init', 'jzsa_init_plugin' );

/**
 * Activation hook
 */
function jzsa_activate() {
	// Clear any cached album data on activation.
	global $wpdb;
	// Direct database query is safe here as we are deleting only this plugin's own transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jzsa_album_%' OR option_name LIKE '_transient_timeout_jzsa_album_%'" );

	// Set a transient to redirect to settings page after activation
	set_transient( 'jzsa_activation_redirect', true, 30 );
}
register_activation_hook( __FILE__, 'jzsa_activate' );

/**
 * Redirect to settings page after activation
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

		// Redirect to settings page
		wp_safe_redirect( admin_url( 'options-general.php?page=janzeman-shared-albums-for-google-photos' ) );
		exit;
	}
}
add_action( 'admin_init', 'jzsa_activation_redirect' );

/**
 * Add Settings link to plugin listing page
 *
 * @param array $links Existing plugin action links
 * @return array Modified plugin action links
 */
function jzsa_add_settings_link( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'options-general.php?page=janzeman-shared-albums-for-google-photos' ),
		esc_html__( 'Settings', 'janzeman-shared-albums-for-google-photos' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'jzsa_add_settings_link' );

/**
 * Deactivation hook
 */
function jzsa_deactivate() {
	// Clear all plugin transients on deactivation.
	global $wpdb;
	// Direct database query is safe here as we are deleting only this plugin's own transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jzsa_album_%' OR option_name LIKE '_transient_timeout_jzsa_album_%'" );
}
register_deactivation_hook( __FILE__, 'jzsa_deactivate' );
