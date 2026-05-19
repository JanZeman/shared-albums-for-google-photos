<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Shared_Albums;

/**
 * Tests for fullscreen-toggle / lightbox-toggle interaction in parse_shortcode_config.
 *
 * The critical invariant: when lightbox-toggle is set and fullscreen-toggle is not,
 * fullscreen must default to "disabled" so the lightbox button is shown alone.
 * When both are set explicitly, both values are respected independently.
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

    public function test_fullscreen_defaults_to_button_only_without_lightbox(): void {
        $config = $this->invoke( 'parse_fullscreen_toggle_mode', [] );
        $this->assertSame( 'button-only', $config );
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

    public function test_lightbox_defaults_to_disabled_when_not_set(): void {
        $config = $this->invoke( 'parse_lightbox_toggle_mode', [] );
        $this->assertSame( 'disabled', $config );
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
}
