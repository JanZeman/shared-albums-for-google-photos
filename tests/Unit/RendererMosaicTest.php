<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Renderer;

/**
 * Tests for mosaic strip parameters (mosaic="true" on slider/carousel mode).
 *
 * Mosaic wraps the main Swiper in a .jzsa-gallery-wrapper and appends a
 * .jzsa-mosaic Swiper thumbnail strip. Position ('left', 'right', 'top',
 * 'bottom') drives the CSS class on the wrapper.
 */
class RendererMosaicTest extends TestCase {

    private JZSA_Renderer $renderer;

    protected function setUp(): void {
        $this->renderer = new JZSA_Renderer();
    }

    private function render( array $config ): string {
        return $this->renderer->render( $config );
    }

    // -------------------------------------------------------------------------
    // Mosaic wrapper
    // -------------------------------------------------------------------------

    public function test_no_mosaic_wrapper_when_mosaic_not_set(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'jzsa-gallery-wrapper', $html );
    }

    public function test_mosaic_wrapper_present_when_mosaic_enabled(): void {
        $html = $this->render( [ 'mosaic' => true ] );
        $this->assertStringContainsString( 'jzsa-gallery-wrapper', $html );
    }

    public function test_mosaic_position_defaults_to_right(): void {
        $html = $this->render( [ 'mosaic' => true ] );
        $this->assertStringContainsString( 'jzsa-mosaic-right', $html );
    }

    public function test_mosaic_position_left(): void {
        $html = $this->render( [ 'mosaic' => true, 'mosaic-position' => 'left' ] );
        $this->assertStringContainsString( 'jzsa-mosaic-left', $html );
        $this->assertStringNotContainsString( 'jzsa-mosaic-right', $html );
    }

    public function test_mosaic_position_top(): void {
        $html = $this->render( [ 'mosaic' => true, 'mosaic-position' => 'top' ] );
        $this->assertStringContainsString( 'jzsa-mosaic-top', $html );
    }

    public function test_mosaic_position_bottom(): void {
        $html = $this->render( [ 'mosaic' => true, 'mosaic-position' => 'bottom' ] );
        $this->assertStringContainsString( 'jzsa-mosaic-bottom', $html );
    }

    // -------------------------------------------------------------------------
    // Mosaic strip element
    // -------------------------------------------------------------------------

    public function test_mosaic_strip_swiper_present_when_enabled(): void {
        $html = $this->render( [ 'mosaic' => true ] );
        $this->assertStringContainsString( 'class="jzsa-mosaic swiper"', $html );
    }

    public function test_mosaic_strip_not_present_when_disabled(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'class="jzsa-mosaic swiper"', $html );
    }

    public function test_mosaic_strip_id_is_gallery_id_plus_suffix(): void {
        $html = $this->render( [ 'mosaic' => true ] );
        // The strip ID pattern is {gallery-id}-mosaic.
        $this->assertMatchesRegularExpression( '/id="jzsa-gallery-\d+-mosaic"/', $html );
    }

    // -------------------------------------------------------------------------
    // Mosaic data attributes on the main album element
    // -------------------------------------------------------------------------

    public function test_data_mosaic_true_when_enabled(): void {
        $html = $this->render( [ 'mosaic' => true ] );
        $this->assertStringContainsString( 'data-mosaic="true"', $html );
    }

    public function test_data_mosaic_false_when_disabled(): void {
        $html = $this->render( [ 'mosaic' => false ] );
        $this->assertStringContainsString( 'data-mosaic="false"', $html );
    }

    public function test_data_mosaic_position_emitted_when_set(): void {
        $html = $this->render( [ 'mosaic' => true, 'mosaic-position' => 'left' ] );
        $this->assertStringContainsString( 'data-mosaic-position="left"', $html );
    }

    public function test_data_mosaic_count_emitted(): void {
        $html = $this->render( [ 'mosaic' => true, 'mosaic-count' => '5' ] );
        $this->assertStringContainsString( 'data-mosaic-count="5"', $html );
    }

    public function test_data_mosaic_gap_emitted(): void {
        $html = $this->render( [ 'mosaic' => true, 'mosaic-gap' => '8' ] );
        $this->assertStringContainsString( 'data-mosaic-gap="8"', $html );
    }

    public function test_data_mosaic_opacity_emitted(): void {
        $html = $this->render( [ 'mosaic' => true, 'mosaic-opacity' => '0.5' ] );
        $this->assertStringContainsString( 'data-mosaic-opacity="0.5"', $html );
    }

    public function test_data_mosaic_background_emitted(): void {
        $html = $this->render( [ 'mosaic' => true, 'mosaic-background' => '#000000' ] );
        $this->assertStringContainsString( 'data-mosaic-background="#000000"', $html );
    }

    // -------------------------------------------------------------------------
    // Fullscreen mosaic attributes
    // -------------------------------------------------------------------------

    public function test_data_fullscreen_mosaic_true_when_enabled(): void {
        $html = $this->render( [ 'fullscreen-mosaic' => true ] );
        $this->assertStringContainsString( 'data-fullscreen-mosaic="true"', $html );
    }

    public function test_data_fullscreen_mosaic_position_emitted(): void {
        $html = $this->render( [ 'fullscreen-mosaic-position' => 'bottom' ] );
        $this->assertStringContainsString( 'data-fullscreen-mosaic-position="bottom"', $html );
    }

    public function test_data_fullscreen_mosaic_layout_emitted(): void {
        $html = $this->render( [ 'fullscreen-mosaic-layout' => 'grid' ] );
        $this->assertStringContainsString( 'data-fullscreen-mosaic-layout="grid"', $html );
    }

    public function test_data_fullscreen_mosaic_count_emitted(): void {
        $html = $this->render( [ 'fullscreen-mosaic-count' => '6' ] );
        $this->assertStringContainsString( 'data-fullscreen-mosaic-count="6"', $html );
    }

    public function test_data_fullscreen_mosaic_gap_emitted(): void {
        $html = $this->render( [ 'fullscreen-mosaic-gap' => '4' ] );
        $this->assertStringContainsString( 'data-fullscreen-mosaic-gap="4"', $html );
    }

    public function test_mosaic_corner_radius_in_styles(): void {
        $html = $this->render( [ 'mosaic-corner-radius' => '6' ] );
        $this->assertStringContainsString( '--jzsa-mosaic-corner-radius: 6px', $html );
    }

    public function test_fullscreen_mosaic_corner_radius_in_styles(): void {
        $html = $this->render( [ 'fullscreen-mosaic-corner-radius' => '10' ] );
        $this->assertStringContainsString( '--jzsa-fullscreen-mosaic-corner-radius: 10px', $html );
    }

    // -------------------------------------------------------------------------
    // Mosaic ignored in gallery mode (the warning is only for admins)
    // -------------------------------------------------------------------------

    public function test_mosaic_wrapper_not_rendered_in_gallery_mode(): void {
        $html = $this->render( [ 'mode' => 'gallery', 'mosaic' => true ] );
        $this->assertStringNotContainsString( 'jzsa-gallery-wrapper', $html );
        $this->assertStringNotContainsString( 'jzsa-mosaic-', $html );
    }
}
