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

	public function test_migration_reports_modern_replacements_for_obsolete_viewer_syntax(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" fullscreen-toggle="click"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'fullscreen-toggle', $result['replacements'][0]['obsolete'] );
		$this->assertSame(
			array( 'viewer="fullscreen"', 'viewer-trigger="click"' ),
			$result['replacements'][0]['replacements']
		);
	}

	public function test_migration_explains_order_normalization_for_modern_syntax(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album viewer="both" link="https://photos.google.com/share/test" mode="slider"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="both"]',
			$result['shortcode']
		);
		$this->assertTrue( $result['changes']['orderNormalized'] );
		$this->assertSame( array(), $result['changes']['added'] );
		$this->assertSame( array(), $result['changes']['removed'] );
		$this->assertSame( array(), $result['changes']['changed'] );
	}

	public function test_migration_reports_when_modern_syntax_needs_no_changes(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="both"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( $result['changes']['orderNormalized'] );
		$this->assertSame( array(), $result['replacements'] );
		$this->assertSame( array(), $result['changes']['added'] );
		$this->assertSame( array(), $result['changes']['removed'] );
		$this->assertSame( array(), $result['changes']['changed'] );
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
		$this->assertStringNotContainsString( 'viewer-trigger=', $result['shortcode'] );
		$this->assertStringNotContainsString( 'fullscreen-toggle', $result['shortcode'] );
	}

	public function test_same_modern_lightbox_goal_preserves_obsolete_shared_click_trigger(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="lightbox" viewer-toggle="click" corner-radius="16" viewer-toggle="click"]',
			'lightbox',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertTrue( $result['behaviorPreserved'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="lightbox" viewer-trigger="click" corner-radius="16"]',
			$result['shortcode']
		);
	}

	public function test_same_modern_both_goal_preserves_existing_gesture_owner(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="both" lightbox-trigger="double-click"]',
			'both',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertTrue( $result['behaviorPreserved'] );
		$this->assertStringContainsString( 'viewer="both" lightbox-trigger="double-click"', $result['shortcode'] );
	}

	public function test_actual_2_3_7_shortcodes_preserve_viewer_behavior(): void {
		$cases = require dirname( __DIR__ ) . '/fixtures/shortcode-migrations-2.3.7.php';

		foreach ( $cases as $name => $case ) {
			$result = JZSA_Shortcode_Tools::migrate( $case['shortcode'], 'preserve', 'fullscreen' );

			$this->assertTrue( $result['ok'], $name );
			$this->assertTrue( $result['behaviorPreserved'], $name );
			$this->assertSame( $case['source_model'], $result['sourceModel'], $name );
			$this->assertSame( $case['current_viewer'], $result['currentViewer'], $name );
			$this->assertSame( $case['current_viewer'], $result['targetViewer'], $name );
			foreach ( $case['contains'] as $token ) {
				$this->assertStringContainsString( $token, $result['shortcode'], $name );
			}
			foreach ( $case['absent'] as $token ) {
				$this->assertStringNotContainsString( $token, $result['shortcode'], $name );
			}
		}
	}

	public function test_actual_2_3_7_shortcodes_keep_behavior_for_same_viewer_goal(): void {
		$cases = require dirname( __DIR__ ) . '/fixtures/shortcode-migrations-2.3.7.php';

		foreach ( $cases as $name => $case ) {
			if ( 'disabled' === $case['current_viewer'] ) {
				continue;
			}
			$result = JZSA_Shortcode_Tools::migrate( $case['shortcode'], $case['current_viewer'], 'fullscreen' );

			$this->assertTrue( $result['ok'], $name );
			$this->assertTrue( $result['behaviorPreserved'], $name );
			$this->assertSame( $case['current_viewer'], $result['targetViewer'], $name );
			foreach ( $case['contains'] as $token ) {
				$this->assertStringContainsString( $token, $result['shortcode'], $name );
			}
		}
	}

	public function test_actual_2_3_7_shortcodes_support_every_selectable_viewer_goal(): void {
		$cases = require dirname( __DIR__ ) . '/fixtures/shortcode-migrations-2.3.7.php';

		foreach ( $cases as $name => $case ) {
			foreach ( array( 'lightbox', 'fullscreen', 'both' ) as $goal ) {
				$result = JZSA_Shortcode_Tools::migrate( $case['shortcode'], $goal, 'fullscreen' );

				$this->assertTrue( $result['ok'], $name . ': ' . $goal );
				$this->assertSame( $goal, $result['targetViewer'], $name . ': ' . $goal );
				$this->assertStringContainsString( 'viewer="' . $goal . '"', $result['shortcode'], $name . ': ' . $goal );
			}
		}
	}

	public function test_parser_rejects_surrounding_or_unparsed_text(): void {
		$surrounding = JZSA_Shortcode_Tools::parse( 'before [jzsa-album link="https://photos.google.com/share/test"]' );
		$unparsed    = JZSA_Shortcode_Tools::parse( '[jzsa-album link="https://photos.google.com/share/test" broken text]' );

		$this->assertSame( 'invalid_shortcode_shape', $surrounding['errors'][0]['code'] );
		$this->assertSame( 'invalid_shortcode_syntax', $unparsed['errors'][0]['code'] );
	}

	public function test_format_normalizes_quotes_whitespace_names_and_order(): void {
		$result = JZSA_Shortcode_Tools::format(
			"  [JZSA-ALBUM   WIDTH = '600' VIEWER = 'lightbox' link = 'https://photos.google.com/share/test' mode = 'slider' info-top = 'A B']  "
		);

		$this->assertTrue( $result['ok'] );
		$this->assertTrue( $result['changed'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="lightbox" width="600" info-top="A B"]',
			$result['shortcode']
		);
	}

	public function test_migration_removes_whitespace_inside_link_only(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			"[jzsa-album link=\"https://photos.google.com/share/AF1QipOg3EA51ATc?\n  key=RGwySFNhbmhqMFBD\" mode=\"slider\" corner-radius=\"16\" info-top=\"Album title\"]",
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc?key=RGwySFNhbmhqMFBD" mode="slider" viewer="fullscreen" corner-radius="16" info-top="Album title"]',
			$result['shortcode']
		);
		$this->assertSame( 'link_whitespace_removed', $result['inputIssues'][0]['code'] );
	}

	public function test_community_variants_export_2_4_0_lightbox_for_2_3_7(): void {
		$result = JZSA_Shortcode_Tools::community_variants(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="lightbox" viewer-trigger="click" viewer-max-width="1100"]',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" lightbox-toggle="click" fullscreen-toggle="disabled" lightbox-max-width="1100" fullscreen-display-max-width="1100"]',
			$result['v2_3_7']
		);
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="lightbox" viewer-trigger="click" viewer-max-width="1100"]',
			$result['v2_4_0']
		);
	}

	public function test_community_variants_export_both_viewers_with_one_gesture_owner(): void {
		$result = JZSA_Shortcode_Tools::community_variants(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="both" lightbox-trigger="double-click" viewer-controls-color="#E63946"]',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'lightbox-toggle="double-click"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'fullscreen-toggle="button-only"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'lightbox-controls-color="#E63946"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'fullscreen-controls-color="#E63946"', $result['v2_3_7'] );
		$this->assertStringNotContainsString( 'viewer=', $result['v2_3_7'] );
		$this->assertStringNotContainsString( 'viewer-', $result['v2_3_7'] );
	}

	public function test_community_2_3_7_variant_uses_only_supported_viewer_groups(): void {
		$result = JZSA_Shortcode_Tools::community_variants(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="both" viewer-info-top="{item}" viewer-mosaic="true" viewer-corner-radius="12" lightbox-info-bottom="modern only"]',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'fullscreen-info-top="{item}"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'fullscreen-mosaic="true"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'lightbox-corner-radius="12"', $result['v2_3_7'] );
		$this->assertStringNotContainsString( 'lightbox-info-', $result['v2_3_7'] );
		$this->assertStringNotContainsString( 'lightbox-mosaic', $result['v2_3_7'] );
		$this->assertStringNotContainsString( 'fullscreen-corner-radius', $result['v2_3_7'] );
	}

	public function test_community_2_3_7_variant_blocks_sideways_inheritance_for_both_viewers(): void {
		$result = JZSA_Shortcode_Tools::community_variants(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="both" controls-color="#FFFFFF" fullscreen-controls-color="#E63946" fullscreen-image-fit="" lightbox-image-fit="cover"]',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'fullscreen-controls-color="#E63946"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'lightbox-controls-color="#FFFFFF"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'lightbox-image-fit="cover"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'fullscreen-image-fit="contain"', $result['v2_3_7'] );
	}

	public function test_community_variants_upgrade_2_3_7_submission_and_keep_compatible_copy(): void {
		$source = '[jzsa-album link="https://photos.google.com/share/test" fullscreen-toggle="double-click" fullscreen-source-width="800"]';
		$result = JZSA_Shortcode_Tools::community_variants( $source, 'fullscreen' );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'fullscreen-toggle="double-click"', $result['v2_3_7'] );
		$this->assertStringContainsString( 'viewer="fullscreen" viewer-trigger="double-click"', $result['v2_4_0'] );
		$this->assertStringContainsString( 'fullscreen-source-width="800"', $result['v2_3_7'] );
	}

	public function test_format_reports_canonical_shortcode_as_unchanged(): void {
		$shortcode = '[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="lightbox" width="600"]';
		$result = JZSA_Shortcode_Tools::format( $shortcode );

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( $result['changed'] );
		$this->assertSame( $shortcode, $result['shortcode'] );
	}

	public function test_format_preserves_legacy_and_unknown_parameters(): void {
		$result = JZSA_Shortcode_Tools::format(
			'[jzsa-album sparkle="yes" fullscreen-toggle="double-click" mode="slider" link="https://photos.google.com/share/test"]'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" fullscreen-toggle="double-click" sparkle="yes"]',
			$result['shortcode']
		);
		$this->assertStringNotContainsString( 'viewer=', $result['shortcode'] );
	}

	public function test_format_refuses_duplicate_parameters(): void {
		$result = JZSA_Shortcode_Tools::format(
			'[jzsa-album link="https://photos.google.com/share/first" link="https://photos.google.com/share/second"]'
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'duplicate_parameter', $result['issues'][0]['code'] );
	}

	public function test_format_refuses_semantically_invalid_viewer_combination(): void {
		$result = JZSA_Shortcode_Tools::format(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="both" viewer-trigger="click"]'
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'viewer_trigger_ambiguous', $result['issues'][0]['code'] );
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
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="lightbox" viewer-trigger="double-click"]',
			$result['shortcode']
		);
	}

	public function test_migration_uses_the_complete_canonical_parameter_order(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album width="600" viewer-trigger="double-click" link="https://photos.google.com/share/test" corner-radius="16" viewer="fullscreen" mode="slider" unknown-option="keep-me"]',
			'preserve',
			'lightbox'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="fullscreen" viewer-trigger="double-click" width="600" corner-radius="16" unknown-option="keep-me"]',
			$result['shortcode']
		);
	}

	public function test_migration_orders_mode_specific_trigger_after_viewer(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album fullscreen-image-fit="contain" lightbox-trigger="double-click" viewer="both" mode="carousel" link="https://photos.google.com/share/test" width="720"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" mode="carousel" viewer="both" lightbox-trigger="double-click" width="720" fullscreen-image-fit="contain"]',
			$result['shortcode']
		);
	}

	public function test_migration_skips_absent_mode_and_orders_remaining_known_parameters(): void {
		$result = JZSA_Shortcode_Tools::migrate(
			'[jzsa-album corner-radius="16" link="https://photos.google.com/share/test" viewer="lightbox" width="600"]',
			'preserve',
			'fullscreen'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="lightbox" width="600" corner-radius="16"]',
			$result['shortcode']
		);
	}

	public function test_format_orders_every_parameter_group_and_keeps_unknowns_last(): void {
		$result = JZSA_Shortcode_Tools::format(
			'[jzsa-album second-extension="2" album-cache-refresh="24" fullscreen-info-bottom="F" lightbox-max-width="900" viewer-controls-color="#fff" info-top="I" corner-radius="16" slideshow="auto" mosaic="true" gallery-gap="8" width="600" limit="12" viewer-trigger="button" viewer="lightbox" mode="slider" link="https://photos.google.com/share/test" first-extension="1"]'
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="lightbox" viewer-trigger="button" limit="12" width="600" gallery-gap="8" mosaic="true" slideshow="auto" corner-radius="16" info-top="I" viewer-controls-color="#fff" lightbox-max-width="900" fullscreen-info-bottom="F" album-cache-refresh="24" second-extension="2" first-extension="1"]',
			$result['shortcode']
		);
	}

	public function test_canonical_order_contains_every_parameter_accepted_by_validation_once(): void {
		$script = file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/admin-settings.js' );
		preg_match( '/var JZSA_KNOWN_PARAMS = \[(.*?)\];/s', $script, $known_match );
		preg_match( '/var JZSA_LEGACY_PARAMS = \[(.*?)\];/s', $script, $legacy_match );
		preg_match( '/var replacements = \{(.*?)\};/s', $script, $replacement_match );
		preg_match( '/var suffixes = \[(.*?)\];/s', $script, $suffix_match );

		$quoted_names = static function ( $source ) {
			preg_match_all( "/'([^']+)'/", $source, $matches );
			return $matches[1];
		};
		$accepted = array_merge(
			$quoted_names( $known_match[1] ),
			$quoted_names( $legacy_match[1] ),
			$quoted_names( $replacement_match[1] ),
			array_map(
				static function ( $suffix ) {
					return 'expanded-' . $suffix;
				},
				$quoted_names( $suffix_match[1] )
			)
		);
		$accepted = array_values( array_unique( $accepted ) );
		$order    = JZSA_Shortcode_Tools::canonical_attribute_order();

		$this->assertSame( $order, array_values( array_unique( $order ) ), 'The canonical order contains duplicate names.' );
		sort( $accepted );
		$sorted_order = $order;
		sort( $sorted_order );
		$this->assertSame( $accepted, $sorted_order );
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
