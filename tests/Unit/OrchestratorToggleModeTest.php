<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Shared_Albums;

/**
 * Tests for fullscreen-toggle / lightbox-toggle interaction in parse_shortcode_config.
 *
 * The critical invariant: when neither viewer mode is explicit, lightbox becomes
 * the default and fullscreen stays disabled. When fullscreen is explicit, lightbox
 * stays off unless it is explicitly enabled too.
 */
class OrchestratorToggleModeTest extends TestCase {

    private JZSA_Shared_Albums $orchestrator;
    private ReflectionClass $reflection;

    protected function setUp(): void {
        // Pass a dummy plugin file path; the constructor only stores it.
        $this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
        $this->reflection   = new ReflectionClass( $this->orchestrator );
    }

    /**
     * Call a private method on the orchestrator via reflection.
     */
    private function invoke( string $method, mixed ...$args ): mixed {
        return $this->reflection->getMethod( $method )->invoke( $this->orchestrator, ...$args );
    }

    // -------------------------------------------------------------------------
    // parse_fullscreen_toggle_mode
    // -------------------------------------------------------------------------

	public function test_fullscreen_defaults_to_disabled_without_explicit_toggle(): void {
		$config = $this->invoke( 'parse_fullscreen_toggle_mode', [] );
		$this->assertSame( 'disabled', $config );
	}

    public function test_fullscreen_defaults_to_disabled_when_lightbox_is_set(): void {
        $atts = [ 'lightbox-toggle' => 'button-only' ];
        $config = $this->invoke( 'parse_fullscreen_toggle_mode', $atts );
        $this->assertSame( 'disabled', $config );
    }

    public function test_fullscreen_defaults_to_disabled_when_lightbox_is_click(): void {
        $atts = [ 'lightbox-toggle' => 'click' ];
        $config = $this->invoke( 'parse_fullscreen_toggle_mode', $atts );
        $this->assertSame( 'disabled', $config );
    }

    public function test_fullscreen_defaults_to_button_only_when_lightbox_is_disabled(): void {
		// lightbox-toggle="disabled" is a no-op; fullscreen should keep its default.
		$atts = [ 'lightbox-toggle' => 'disabled' ];
		$config = $this->invoke( 'parse_fullscreen_toggle_mode', $atts );
		$this->assertSame( 'button-only', $config );
	}

    public function test_fullscreen_defaults_to_button_only_when_lightbox_false_alias_is_used(): void {
        $atts = [ 'lightbox-toggle' => 'no' ];
        $config = $this->invoke( 'parse_fullscreen_toggle_mode', $atts );
        $this->assertSame( 'button-only', $config );
    }

    public function test_fullscreen_explicit_value_respected_alongside_lightbox(): void {
        $atts = [
            'lightbox-toggle'   => 'button-only',
            'fullscreen-toggle' => 'button-only',
        ];
        $config = $this->invoke( 'parse_fullscreen_toggle_mode', $atts );
        $this->assertSame( 'button-only', $config );
    }

    public function test_fullscreen_explicit_click_respected_alongside_lightbox(): void {
        $atts = [
            'lightbox-toggle'   => 'button-only',
            'fullscreen-toggle' => 'double-click',
        ];
        $config = $this->invoke( 'parse_fullscreen_toggle_mode', $atts );
        $this->assertSame( 'double-click', $config );
    }

    public function test_fullscreen_invalid_value_falls_back_to_button_only(): void {
        $atts = [ 'fullscreen-toggle' => 'banana' ];
        $config = $this->invoke( 'parse_fullscreen_toggle_mode', $atts );
        $this->assertSame( 'button-only', $config );
    }

    // -------------------------------------------------------------------------
    // parse_lightbox_toggle_mode
    // -------------------------------------------------------------------------

	public function test_lightbox_defaults_to_button_only_when_not_set(): void {
		$config = $this->invoke( 'parse_lightbox_toggle_mode', [] );
		$this->assertSame( 'button-only', $config );
	}

    public function test_lightbox_button_only_accepted(): void {
        $atts = [ 'lightbox-toggle' => 'button-only' ];
        $config = $this->invoke( 'parse_lightbox_toggle_mode', $atts );
        $this->assertSame( 'button-only', $config );
    }

    public function test_lightbox_click_accepted(): void {
        $atts = [ 'lightbox-toggle' => 'click' ];
        $config = $this->invoke( 'parse_lightbox_toggle_mode', $atts );
        $this->assertSame( 'click', $config );
    }

    public function test_lightbox_double_click_accepted(): void {
        $atts = [ 'lightbox-toggle' => 'double-click' ];
        $config = $this->invoke( 'parse_lightbox_toggle_mode', $atts );
        $this->assertSame( 'double-click', $config );
    }

    public function test_lightbox_true_alias_maps_to_click(): void {
        $atts = [ 'lightbox-toggle' => 'true' ];
        $config = $this->invoke( 'parse_lightbox_toggle_mode', $atts );
        $this->assertSame( 'click', $config );
    }

    public function test_lightbox_false_alias_maps_to_disabled(): void {
        $atts = [ 'lightbox-toggle' => 'no' ];
        $config = $this->invoke( 'parse_lightbox_toggle_mode', $atts );
        $this->assertSame( 'disabled', $config );
    }

    public function test_lightbox_invalid_value_falls_back_to_disabled(): void {
        $atts = [ 'lightbox-toggle' => 'banana' ];
        $config = $this->invoke( 'parse_lightbox_toggle_mode', $atts );
        $this->assertSame( 'disabled', $config );
    }

    // -------------------------------------------------------------------------
    // paired_key (bidirectional fallback helper)
    // -------------------------------------------------------------------------

    public function test_paired_key_returns_primary_when_set(): void {
        $atts = [ 'fullscreen-controls-color' => '#ff0000' ];
        $key  = $this->invoke( 'paired_key', $atts, 'fullscreen-controls-color', 'lightbox-controls-color' );
        $this->assertSame( 'fullscreen-controls-color', $key );
    }

    public function test_paired_key_falls_back_to_secondary_when_primary_absent(): void {
        $atts = [ 'lightbox-controls-color' => '#0000ff' ];
        $key  = $this->invoke( 'paired_key', $atts, 'fullscreen-controls-color', 'lightbox-controls-color' );
        $this->assertSame( 'lightbox-controls-color', $key );
    }

    public function test_paired_key_returns_null_when_neither_set(): void {
        $key = $this->invoke( 'paired_key', [], 'fullscreen-controls-color', 'lightbox-controls-color' );
        $this->assertNull( $key );
    }

    public function test_paired_key_treats_empty_string_as_absent(): void {
        $atts = [ 'fullscreen-controls-color' => '' ];
        $key  = $this->invoke( 'paired_key', $atts, 'fullscreen-controls-color', 'lightbox-controls-color' );
        $this->assertNull( $key );
    }

    public function test_paired_key_prefers_primary_when_both_set(): void {
        $atts = [
            'fullscreen-controls-color' => '#ff0000',
            'lightbox-controls-color'   => '#0000ff',
        ];
        $key = $this->invoke( 'paired_key', $atts, 'fullscreen-controls-color', 'lightbox-controls-color' );
        $this->assertSame( 'fullscreen-controls-color', $key );
    }

    public function test_viewer_toggle_accepts_each_supported_token(): void {
        $cases = [
            'disabled'                => [ 'disabled', 'disabled', true ],
            'lightbox-button'         => [ 'button-only', 'disabled', true ],
            'lightbox-click'          => [ 'click', 'disabled', true ],
            'lightbox-double-click'   => [ 'double-click', 'disabled', true ],
            'fullscreen-button'       => [ 'disabled', 'button-only', true ],
            'fullscreen-click'        => [ 'disabled', 'click', true ],
            'fullscreen-double-click' => [ 'disabled', 'double-click', true ],
        ];

        foreach ( $cases as $raw => [ $lightbox, $fullscreen, $valid ] ) {
            $parsed = $this->invoke( 'parse_viewer_toggle', $raw );
            $this->assertSame( $lightbox, $parsed['lightbox'], $raw );
            $this->assertSame( $fullscreen, $parsed['fullscreen'], $raw );
            $this->assertSame( $valid, $parsed['valid'], $raw );
        }
    }

    public function test_viewer_toggle_accepts_safe_combination_in_any_order(): void {
        $first = $this->invoke( 'parse_viewer_toggle', 'lightbox-button, fullscreen-click' );
        $second = $this->invoke( 'parse_viewer_toggle', ' fullscreen-click , lightbox-button ' );

        $this->assertSame( $first, $second );
        $this->assertSame( 'button-only', $first['lightbox'] );
        $this->assertSame( 'click', $first['fullscreen'] );
        $this->assertTrue( $first['valid'] );
    }

    public function test_viewer_toggle_converts_competing_gestures_to_buttons(): void {
        $parsed = $this->invoke( 'parse_viewer_toggle', 'lightbox-click, fullscreen-double-click' );

        $this->assertSame( 'button-only', $parsed['lightbox'] );
        $this->assertSame( 'button-only', $parsed['fullscreen'] );
        $this->assertFalse( $parsed['valid'] );
    }

    public function test_viewer_toggle_disables_both_for_other_invalid_values(): void {
        $values = [
            '',
            'lightbox-click, lightbox-button',
            'disabled, fullscreen-button',
            'lightbox-hover',
        ];

        foreach ( $values as $raw ) {
            $parsed = $this->invoke( 'parse_viewer_toggle', $raw );
            $this->assertSame( 'disabled', $parsed['lightbox'], $raw );
            $this->assertSame( 'disabled', $parsed['fullscreen'], $raw );
            $this->assertFalse( $parsed['valid'], $raw );
        }
    }

    public function test_viewer_defaults_leave_legacy_attributes_unchanged(): void {
        $atts = [
            'lightbox-toggle'          => 'click',
            'fullscreen-toggle'        => 'button-only',
            'fullscreen-controls-color' => '#112233',
        ];

        $this->assertSame( $atts, $this->invoke( 'apply_viewer_attribute_defaults', $atts ) );
    }

    public function test_viewer_defaults_fill_both_modes_and_keep_specific_overrides(): void {
        $atts = [
            'viewer-toggle'          => 'lightbox-button, fullscreen-click',
            'viewer-max-width'       => '900',
            'viewer-controls-color'  => '#112233',
            'lightbox-max-width'       => '700',
            'fullscreen-controls-color' => '#445566',
        ];

        $normalized = $this->invoke( 'apply_viewer_attribute_defaults', $atts );

        $this->assertSame( 'button-only', $normalized['lightbox-toggle'] );
        $this->assertSame( 'click', $normalized['fullscreen-toggle'] );
        $this->assertSame( '700', $normalized['lightbox-max-width'] );
        $this->assertSame( '900', $normalized['fullscreen-display-max-width'] );
        $this->assertSame( '#112233', $normalized['lightbox-controls-color'] );
        $this->assertSame( '#445566', $normalized['fullscreen-controls-color'] );
    }

    public function test_every_viewer_setting_maps_to_both_concrete_modes(): void {
        $pairs = [
            'viewer-max-height'             => [ 'fullscreen-display-max-height', 'lightbox-max-height' ],
            'viewer-source-width'           => [ 'fullscreen-source-width', 'lightbox-source-width' ],
            'viewer-source-height'          => [ 'fullscreen-source-height', 'lightbox-source-height' ],
            'viewer-image-fit'              => [ 'fullscreen-image-fit', 'lightbox-image-fit' ],
            'viewer-background-color'       => [ 'fullscreen-background-color', 'lightbox-background-color' ],
            'viewer-corner-radius'          => [ 'fullscreen-corner-radius', 'lightbox-corner-radius' ],
            'viewer-video-controls-color'   => [ 'fullscreen-video-controls-color', 'lightbox-video-controls-color' ],
            'viewer-video-controls-autohide' => [ 'fullscreen-video-controls-autohide', 'lightbox-video-controls-autohide' ],
            'viewer-show-navigation'        => [ 'fullscreen-show-navigation', 'lightbox-show-navigation' ],
            'viewer-show-link-button'       => [ 'fullscreen-show-link-button', 'lightbox-show-link-button' ],
            'viewer-show-download-button'   => [ 'fullscreen-show-download-button', 'lightbox-show-download-button' ],
            'viewer-slideshow'              => [ 'fullscreen-slideshow', 'lightbox-slideshow' ],
            'viewer-slideshow-delay'        => [ 'fullscreen-slideshow-delay', 'lightbox-slideshow-delay' ],
            'viewer-slideshow-autoresume'   => [ 'fullscreen-slideshow-autoresume', 'lightbox-slideshow-autoresume' ],
            'viewer-info-bottom'            => [ 'fullscreen-info-bottom', 'lightbox-info-bottom' ],
            'viewer-info-top'               => [ 'fullscreen-info-top', 'lightbox-info-top' ],
            'viewer-info-top-secondary'     => [ 'fullscreen-info-top-secondary', 'lightbox-info-top-secondary' ],
            'viewer-info-font-size'         => [ 'fullscreen-info-font-size', 'lightbox-info-font-size' ],
            'viewer-info-font-family'       => [ 'fullscreen-info-font-family', 'lightbox-info-font-family' ],
            'viewer-info-font-color'        => [ 'fullscreen-info-font-color', 'lightbox-info-font-color' ],
            'viewer-mosaic'                 => [ 'fullscreen-mosaic', 'lightbox-mosaic' ],
            'viewer-mosaic-position'        => [ 'fullscreen-mosaic-position', 'lightbox-mosaic-position' ],
            'viewer-mosaic-layout'          => [ 'fullscreen-mosaic-layout', 'lightbox-mosaic-layout' ],
            'viewer-mosaic-count'           => [ 'fullscreen-mosaic-count', 'lightbox-mosaic-count' ],
            'viewer-mosaic-gap'             => [ 'fullscreen-mosaic-gap', 'lightbox-mosaic-gap' ],
            'viewer-mosaic-opacity'         => [ 'fullscreen-mosaic-opacity', 'lightbox-mosaic-opacity' ],
            'viewer-mosaic-background'      => [ 'fullscreen-mosaic-background', 'lightbox-mosaic-background' ],
            'viewer-mosaic-corner-radius'   => [ 'fullscreen-mosaic-corner-radius', 'lightbox-mosaic-corner-radius' ],
        ];

        foreach ( $pairs as $viewer => [ $fullscreen, $lightbox ] ) {
            $normalized = $this->invoke( 'apply_viewer_attribute_defaults', [ $viewer => 'test-value' ] );
            $this->assertSame( 'test-value', $normalized[ $fullscreen ], $viewer );
            $this->assertSame( 'test-value', $normalized[ $lightbox ], $viewer );
        }
    }
}
