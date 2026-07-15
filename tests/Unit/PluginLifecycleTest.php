<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class PluginLifecycleTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['jzsa_test_options']           = array();
		$GLOBALS['jzsa_test_transients']        = array();
		$GLOBALS['jzsa_test_clear_cache_calls'] = array();
	}

	public function test_fresh_activation_initializes_lightbox_without_migration_notice(): void {
		jzsa_activate();

		$this->assertSame( 'lightbox', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
		$this->assertSame( JZSA_VERSION, get_option( JZSA_VERSION_OPTION ) );
		$this->assertFalse( get_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, false ) );
		$this->assertTrue( get_transient( 'jzsa_activation_redirect' ) );
	}

	public function test_activation_from_legacy_version_initializes_fullscreen_and_notice(): void {
		update_option( JZSA_VERSION_OPTION, '2.3.7' );

		jzsa_activate();

		$this->assertSame( 'fullscreen', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
		$this->assertSame( JZSA_VERSION, get_option( JZSA_VERSION_OPTION ) );
		$this->assertSame( '1', get_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION ) );
	}

	public function test_active_pre_2_1_install_without_stored_version_is_treated_as_legacy(): void {
		jzsa_maybe_run_version_migration();

		$this->assertSame( 'fullscreen', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
		$this->assertSame( JZSA_VERSION, get_option( JZSA_VERSION_OPTION ) );
		$this->assertSame( '1', get_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION ) );
	}

	public function test_normal_upgrade_preserves_an_existing_viewer_choice(): void {
		update_option( JZSA_VERSION_OPTION, '2.3.7' );
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'lightbox' );

		jzsa_maybe_run_version_migration();

		$this->assertSame( 'lightbox', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
		$this->assertSame( '1', get_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION ) );
	}

	public function test_reactivation_preserves_an_existing_viewer_choice(): void {
		update_option( JZSA_VERSION_OPTION, JZSA_VERSION );
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'fullscreen' );

		jzsa_activate();

		$this->assertSame( 'fullscreen', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
		$this->assertFalse( get_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, false ) );
	}

	public function test_version_migration_is_idempotent(): void {
		update_option( JZSA_VERSION_OPTION, '2.3.7' );

		jzsa_maybe_run_version_migration();
		$cache_calls = $GLOBALS['jzsa_test_clear_cache_calls'];
		jzsa_maybe_run_version_migration();

		$this->assertSame( $cache_calls, $GLOBALS['jzsa_test_clear_cache_calls'] );
		$this->assertSame( 'fullscreen', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
		$this->assertSame( JZSA_VERSION, get_option( JZSA_VERSION_OPTION ) );
	}

	public function test_invalid_viewer_value_falls_back_according_to_installation_history(): void {
		update_option( JZSA_VERSION_OPTION, '2.3.7' );
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'invalid' );

		jzsa_maybe_run_version_migration();
		$this->assertSame( 'fullscreen', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );

		$GLOBALS['jzsa_test_options'] = array(
			JZSA_VERSION_OPTION        => JZSA_VERSION,
			JZSA_DEFAULT_VIEWER_OPTION => 'invalid',
		);
		jzsa_maybe_run_version_migration();

		$this->assertSame( 'lightbox', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
	}
}
