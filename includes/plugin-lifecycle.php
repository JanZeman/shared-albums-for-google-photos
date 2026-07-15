<?php
/**
 * Plugin activation and version migration lifecycle.
 *
 * @package JZSA_Shared_Albums
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize versioned options and clear stale caches once per upgrade.
 */
function jzsa_maybe_run_version_migration() {
	$stored_version = get_option( JZSA_VERSION_OPTION, '' );
	$default_viewer = get_option( JZSA_DEFAULT_VIEWER_OPTION, '' );
	$is_upgrade     = JZSA_Shortcode_Tools::is_legacy_upgrade( $stored_version, JZSA_VIEWER_MIGRATION_CUTOFF_VERSION );

	if ( ! in_array( $default_viewer, array( 'lightbox', 'fullscreen' ), true ) ) {
		$initial_default = JZSA_Shortcode_Tools::resolve_initial_default_viewer(
			$stored_version,
			$default_viewer,
			JZSA_VIEWER_MIGRATION_CUTOFF_VERSION
		);
		update_option( JZSA_DEFAULT_VIEWER_OPTION, $initial_default, false );
	}

	if ( JZSA_VERSION === $stored_version ) {
		return;
	}

	jzsa_clear_all_plugin_caches();

	if ( $is_upgrade ) {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1', false );
	}

	if ( '' === $stored_version ) {
		add_option( JZSA_VERSION_OPTION, JZSA_VERSION, '', false );
		return;
	}

	update_option( JZSA_VERSION_OPTION, JZSA_VERSION, false );
}
add_action( 'plugins_loaded', 'jzsa_maybe_run_version_migration' );

/**
 * Initialize activation state without overwriting an existing viewer choice.
 */
function jzsa_activate() {
	jzsa_clear_all_plugin_caches();
	$stored_version = get_option( JZSA_VERSION_OPTION, '' );
	$default_viewer = get_option( JZSA_DEFAULT_VIEWER_OPTION, '' );
	$is_upgrade     = JZSA_Shortcode_Tools::is_legacy_upgrade( $stored_version, JZSA_VIEWER_MIGRATION_CUTOFF_VERSION, true );

	if ( ! in_array( $default_viewer, array( 'lightbox', 'fullscreen' ), true ) ) {
		update_option(
			JZSA_DEFAULT_VIEWER_OPTION,
			JZSA_Shortcode_Tools::resolve_initial_default_viewer( $stored_version, $default_viewer, JZSA_VIEWER_MIGRATION_CUTOFF_VERSION, true ),
			false
		);
	}
	if ( $is_upgrade ) {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1', false );
	}
	update_option( JZSA_VERSION_OPTION, JZSA_VERSION, false );

	if ( class_exists( 'JZSA_Community' ) ) {
		JZSA_Community::ensure_install_secret();
	}

	set_transient( 'jzsa_activation_redirect', true, 30 );
}
register_activation_hook( JZSA_PLUGIN_FILE, 'jzsa_activate' );
