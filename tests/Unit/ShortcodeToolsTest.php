<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use JZSA_Shortcode_Tools;
use PHPUnit\Framework\TestCase;

class ShortcodeToolsTest extends TestCase {

	public function test_default_viewer_resolution_distinguishes_fresh_and_upgraded_sites(): void {
		$this->assertSame( 'lightbox', JZSA_Shortcode_Tools::resolve_initial_default_viewer( '', '', '2.4.0', true ) );
		$this->assertSame( 'fullscreen', JZSA_Shortcode_Tools::resolve_initial_default_viewer( '', '', '2.4.0' ) );
		$this->assertSame( 'fullscreen', JZSA_Shortcode_Tools::resolve_initial_default_viewer( '2.3.7', '', '2.4.0' ) );
		$this->assertSame( 'lightbox', JZSA_Shortcode_Tools::resolve_initial_default_viewer( '2.3.7', 'lightbox', '2.4.0' ) );
		$this->assertSame( 'fullscreen', JZSA_Shortcode_Tools::resolve_initial_default_viewer( '2.4.0', 'fullscreen', '2.4.0' ) );
	}

	public function test_missing_version_is_legacy_except_during_fresh_activation(): void {
		$this->assertTrue( JZSA_Shortcode_Tools::is_legacy_upgrade( '', '2.4.0' ) );
		$this->assertFalse( JZSA_Shortcode_Tools::is_legacy_upgrade( '', '2.4.0', true ) );
		$this->assertTrue( JZSA_Shortcode_Tools::is_legacy_upgrade( '2.0.11', '2.4.0' ) );
		$this->assertFalse( JZSA_Shortcode_Tools::is_legacy_upgrade( '2.4.0', '2.4.0' ) );
	}

	public function test_shared_trigger_is_invalid_with_both_viewers(): void {
		$issues = JZSA_Shortcode_Tools::validate_semantics(
			array( 'viewer' => 'both', 'viewer-trigger' => 'click' )
		);

		$this->assertSame( 'viewer_trigger_ambiguous', $issues[0]['code'] );
		$this->assertSame( 'error', $issues[0]['severity'] );
	}

	public function test_only_one_mode_specific_gesture_is_allowed(): void {
		$valid = JZSA_Shortcode_Tools::validate_semantics(
			array( 'viewer' => 'both', 'lightbox-trigger' => 'click' )
		);
		$invalid = JZSA_Shortcode_Tools::validate_semantics(
			array( 'viewer' => 'both', 'lightbox-trigger' => 'click', 'fullscreen-trigger' => 'double-click' )
		);

		$this->assertSame( array(), $valid );
		$this->assertSame( 'viewer_gesture_conflict', $invalid[0]['code'] );
	}

	public function test_preserve_migration_materializes_implicit_fullscreen(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertTrue( $result['behaviorPreserved'] );
		$this->assertStringContainsString( 'viewer="fullscreen"', $result['shortcode'] );
	}

	public function test_intentional_lightbox_migration_is_reported(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" fullscreen-toggle="click"]',
			'lightbox',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( $result['behaviorPreserved'] );
		$this->assertStringContainsString( 'viewer="lightbox"', $result['shortcode'] );
		$this->assertStringNotContainsString( 'fullscreen-toggle', $result['shortcode'] );
	}

	public function test_parser_rejects_surrounding_or_unparsed_text(): void {
		$surrounding = JZSA_Shortcode_Tools::parse( 'before [jzsa-album link="https://photos.google.com/share/test"]' );
		$unparsed    = JZSA_Shortcode_Tools::parse( '[jzsa-album link="https://photos.google.com/share/test" broken text]' );

		$this->assertSame( 'invalid_shortcode_shape', $surrounding['errors'][0]['code'] );
		$this->assertSame( 'invalid_shortcode_syntax', $unparsed['errors'][0]['code'] );
	}

	public function test_preserve_migration_materializes_legacy_sideways_values(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" fullscreen-toggle="button-only" fullscreen-source-width="800"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'viewer="fullscreen"', $result['shortcode'] );
		$this->assertStringContainsString( 'fullscreen-source-width="800"', $result['shortcode'] );
		$this->assertStringContainsString( 'lightbox-source-width="800"', $result['shortcode'] );
	}

	public function test_preserve_migration_keeps_single_gesture_owner_with_both_modes(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" lightbox-toggle="double-click" fullscreen-toggle="button-only"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'viewer="both"', $result['shortcode'] );
		$this->assertStringContainsString( 'lightbox-trigger="double-click"', $result['shortcode'] );
		$this->assertStringNotContainsString( 'fullscreen-trigger', $result['shortcode'] );
	}

	public function test_migration_rejects_conflicting_legacy_gestures(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" lightbox-toggle="click" fullscreen-toggle="double-click"]',
			'preserve',
			'fullscreen'
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'legacy_gesture_conflict', $result['issues'][0]['code'] );
	}

	public function test_explicit_target_resolves_conflicting_legacy_gesture_owner(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" lightbox-toggle="click" fullscreen-toggle="double-click"]',
			'lightbox',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( $result['behaviorPreserved'] );
		$this->assertStringContainsString( 'viewer="lightbox"', $result['shortcode'] );
		$this->assertStringContainsString( 'viewer-trigger="click"', $result['shortcode'] );
		$this->assertSame( 'warning', $result['issues'][0]['severity'] );
	}

	public function test_modern_migration_does_not_add_sideways_inheritance(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="both" fullscreen-image-fit="cover"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'modern', $result['sourceModel'] );
		$this->assertStringContainsString( 'fullscreen-image-fit="cover"', $result['shortcode'] );
		$this->assertStringNotContainsString( 'lightbox-image-fit', $result['shortcode'] );
	}

	public function test_legacy_shortcode_without_toggle_preserves_fullscreen_on_fresh_site(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" fullscreen-source-width="800"]',
			'preserve',
			'lightbox'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'legacy', $result['sourceModel'] );
		$this->assertSame( 'fullscreen', $result['currentViewer'] );
		$this->assertStringContainsString( 'viewer="fullscreen"', $result['shortcode'] );
	}

	public function test_modern_shared_trigger_without_viewer_uses_site_default(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" viewer-trigger="double-click"]',
			'preserve',
			'lightbox'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'lightbox', $result['currentViewer'] );
		$this->assertStringContainsString( 'viewer="lightbox"', $result['shortcode'] );
		$this->assertStringContainsString( 'viewer-trigger="double-click"', $result['shortcode'] );
	}

	public function test_migration_is_idempotent_on_its_modern_output(): void {
		$first = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" fullscreen-toggle="double-click" fullscreen-image-fit="cover"]',
			'preserve',
			'fullscreen'
		);
		$second = JZSA_Shortcode_Tools::migrate( $first['shortcode'], 'preserve', 'fullscreen' );

		$this->assertTrue( $first['ok'] );
		$this->assertTrue( $second['ok'] );
		$this->assertSame( $first['shortcode'], $second['shortcode'] );
	}
}
