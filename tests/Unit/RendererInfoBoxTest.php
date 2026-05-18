<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Renderer;

/**
 * Tests for info-box parameters: format strings, typography, halo effects,
 * and the has-active-bottom-center flag.
 */
class RendererInfoBoxTest extends TestCase {

    private JZSA_Renderer $renderer;

    protected function setUp(): void {
        $this->renderer = new JZSA_Renderer();
    }

    private function render( array $config ): string {
        return $this->renderer->render( $config );
    }

    // -------------------------------------------------------------------------
    // Info box format strings
    // -------------------------------------------------------------------------

    public function test_info_bottom_emitted(): void {
        $html = $this->render( [ 'info-bottom' => '{item}' ] );
        $this->assertStringContainsString( 'data-info-bottom="{item}"', $html );
    }

    public function test_info_bottom_not_emitted_when_absent(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'data-info-bottom', $html );
    }

    public function test_info_top_emitted(): void {
        $html = $this->render( [ 'info-top' => '{items}' ] );
        $this->assertStringContainsString( 'data-info-top="{items}"', $html );
    }

    public function test_info_top_secondary_emitted(): void {
        $html = $this->render( [ 'info-top-secondary' => '{album-title}' ] );
        $this->assertStringContainsString( 'data-info-top-secondary="{album-title}"', $html );
    }

    public function test_fullscreen_info_bottom_emitted(): void {
        $html = $this->render( [ 'fullscreen-info-bottom' => '{item} of {items}' ] );
        $this->assertStringContainsString( 'data-fullscreen-info-bottom="{item} of {items}"', $html );
    }

    public function test_fullscreen_info_top_emitted(): void {
        $html = $this->render( [ 'fullscreen-info-top' => '{album-title}' ] );
        $this->assertStringContainsString( 'data-fullscreen-info-top="{album-title}"', $html );
    }

    public function test_fullscreen_info_top_secondary_emitted(): void {
        $html = $this->render( [ 'fullscreen-info-top-secondary' => 'Photo {item}' ] );
        $this->assertStringContainsString( 'data-fullscreen-info-top-secondary="Photo {item}"', $html );
    }

    // -------------------------------------------------------------------------
    // has-active-bottom-center
    // -------------------------------------------------------------------------

    public function test_has_active_bottom_center_false_by_default(): void {
        $html = $this->render( [] );
        $this->assertStringContainsString( 'data-has-active-bottom-center="false"', $html );
    }

    public function test_has_active_bottom_center_true_when_info_bottom_set(): void {
        $html = $this->render( [ 'info-bottom' => 'test' ] );
        $this->assertStringContainsString( 'data-has-active-bottom-center="true"', $html );
    }

    public function test_has_active_bottom_center_false_when_only_info_top_set(): void {
        $html = $this->render( [ 'info-top' => '{item}' ] );
        $this->assertStringContainsString( 'data-has-active-bottom-center="false"', $html );
    }

    // -------------------------------------------------------------------------
    // Typography: font-size
    // -------------------------------------------------------------------------

    public function test_info_font_size_emitted_as_data_attr(): void {
        $html = $this->render( [ 'info-font-size' => '14' ] );
        $this->assertStringContainsString( 'data-info-font-size="14"', $html );
    }

    public function test_info_font_size_emitted_as_css_custom_property(): void {
        $html = $this->render( [ 'info-font-size' => '14' ] );
        $this->assertStringContainsString( '--jzsa-info-font-size: 14px', $html );
    }

    public function test_fullscreen_info_font_size_emitted(): void {
        $html = $this->render( [ 'fullscreen-info-font-size' => '16' ] );
        $this->assertStringContainsString( 'data-fullscreen-info-font-size="16"', $html );
    }

    // -------------------------------------------------------------------------
    // Typography: font-family
    // -------------------------------------------------------------------------

    public function test_info_font_family_emitted_as_data_attr(): void {
        $html = $this->render( [ 'info-font-family' => 'Arial' ] );
        $this->assertStringContainsString( 'data-info-font-family="Arial"', $html );
    }

    public function test_info_font_family_emitted_as_css_custom_property(): void {
        $html = $this->render( [ 'info-font-family' => 'Arial' ] );
        $this->assertStringContainsString( '--jzsa-info-font-family: Arial', $html );
    }

    public function test_fullscreen_info_font_family_emitted(): void {
        $html = $this->render( [ 'fullscreen-info-font-family' => 'Georgia' ] );
        $this->assertStringContainsString( 'data-fullscreen-info-font-family="Georgia"', $html );
    }

    // -------------------------------------------------------------------------
    // Typography: font-color
    // -------------------------------------------------------------------------

    public function test_info_font_color_emitted_as_data_attr(): void {
        $html = $this->render( [ 'info-font-color' => '#ff0000' ] );
        $this->assertStringContainsString( 'data-info-font-color="#ff0000"', $html );
    }

    public function test_info_font_color_emitted_as_css_custom_property(): void {
        $html = $this->render( [ 'info-font-color' => '#ff0000' ] );
        $this->assertStringContainsString( '--jzsa-info-font-color: #ff0000', $html );
    }

    public function test_fullscreen_info_font_color_emitted(): void {
        $html = $this->render( [ 'fullscreen-info-font-color' => '#00ff00' ] );
        $this->assertStringContainsString( 'data-fullscreen-info-font-color="#00ff00"', $html );
    }

    // -------------------------------------------------------------------------
    // Text alignment
    // -------------------------------------------------------------------------

    public function test_info_text_align_left_emitted(): void {
        $html = $this->render( [ 'info-text-align' => 'left' ] );
        $this->assertStringContainsString( 'data-info-text-align="left"', $html );
    }

    public function test_info_text_align_right_emitted(): void {
        $html = $this->render( [ 'info-text-align' => 'right' ] );
        $this->assertStringContainsString( 'data-info-text-align="right"', $html );
    }

    public function test_info_text_align_center_not_emitted(): void {
        // Center is the default; emitting it would waste bytes.
        $html = $this->render( [ 'info-text-align' => 'center' ] );
        $this->assertStringNotContainsString( 'data-info-text-align', $html );
    }

    public function test_info_top_text_align_emitted(): void {
        $html = $this->render( [ 'info-top-text-align' => 'right' ] );
        $this->assertStringContainsString( 'data-info-top-text-align="right"', $html );
    }

    public function test_info_top_secondary_text_align_emitted(): void {
        $html = $this->render( [ 'info-top-secondary-text-align' => 'left' ] );
        $this->assertStringContainsString( 'data-info-top-secondary-text-align="left"', $html );
    }

    public function test_info_bottom_text_align_emitted(): void {
        $html = $this->render( [ 'info-bottom-text-align' => 'left' ] );
        $this->assertStringContainsString( 'data-info-bottom-text-align="left"', $html );
    }

    // -------------------------------------------------------------------------
    // Info wrap
    // -------------------------------------------------------------------------

    public function test_info_wrap_emitted_when_enabled(): void {
        $html = $this->render( [ 'info-wrap' => true ] );
        $this->assertStringContainsString( 'data-info-wrap="true"', $html );
    }

    public function test_info_wrap_not_emitted_when_absent(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'data-info-wrap', $html );
    }

    // -------------------------------------------------------------------------
    // Halo effects
    // -------------------------------------------------------------------------

    public function test_info_halo_effect_true(): void {
        $html = $this->render( [ 'info-halo-effect' => true ] );
        $this->assertStringContainsString( 'data-info-halo-effect="true"', $html );
    }

    public function test_info_top_halo_effect_true(): void {
        $html = $this->render( [ 'info-top-halo-effect' => true ] );
        $this->assertStringContainsString( 'data-info-top-halo-effect="true"', $html );
    }

    public function test_info_top_secondary_halo_effect_true(): void {
        $html = $this->render( [ 'info-top-secondary-halo-effect' => true ] );
        $this->assertStringContainsString( 'data-info-top-secondary-halo-effect="true"', $html );
    }

    public function test_info_bottom_halo_effect_true(): void {
        $html = $this->render( [ 'info-bottom-halo-effect' => true ] );
        $this->assertStringContainsString( 'data-info-bottom-halo-effect="true"', $html );
    }

    public function test_gallery_info_bottom_halo_effect_true(): void {
        $html = $this->render( [ 'gallery-info-bottom-halo-effect' => true ] );
        $this->assertStringContainsString( 'data-gallery-info-bottom-halo-effect="true"', $html );
    }

    public function test_album_title_halo_effect_true(): void {
        $html = $this->render( [ 'album-title-halo-effect' => true ] );
        $this->assertStringContainsString( 'data-album-title-halo-effect="true"', $html );
    }

    // -------------------------------------------------------------------------
    // Gallery mode also emits info attributes
    // -------------------------------------------------------------------------

    public function test_gallery_mode_emits_info_bottom(): void {
        $html = $this->renderer->render( [ 'mode' => 'gallery', 'info-bottom' => '{item}' ] );
        $this->assertStringContainsString( 'data-info-bottom="{item}"', $html );
    }

    public function test_gallery_mode_emits_info_font_size(): void {
        $html = $this->renderer->render( [ 'mode' => 'gallery', 'info-font-size' => '12' ] );
        $this->assertStringContainsString( 'data-info-font-size="12"', $html );
    }
}
