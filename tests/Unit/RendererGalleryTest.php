<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Renderer;

/**
 * Tests for gallery mode (mode="gallery") data attributes and container structure.
 */
class RendererGalleryTest extends TestCase {

    private JZSA_Renderer $renderer;

    protected function setUp(): void {
        $this->renderer = new JZSA_Renderer();
    }

    private function render( array $extra = [] ): string {
        return $this->renderer->render( array_merge( [ 'mode' => 'gallery' ], $extra ) );
    }

    // -------------------------------------------------------------------------
    // Container structure
    // -------------------------------------------------------------------------

    public function test_gallery_has_jzsa_gallery_album_class(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'jzsa-gallery-album', $html );
    }

    public function test_gallery_has_jzsa_loader_pending_class(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'jzsa-loader-pending', $html );
    }

    public function test_gallery_does_not_have_swiper_class(): void {
        $html = $this->render();
        // The swiper class belongs only to the slider container.
        // It is inside "jzsa-gallery-slideshow" children, not the top-level gallery div.
        $this->assertStringNotContainsString( 'class="jzsa-album swiper', $html );
    }

    public function test_gallery_does_not_render_swiper_wrapper(): void {
        $html = $this->render();
        $this->assertStringNotContainsString( 'class="swiper-wrapper"', $html );
    }

    public function test_gallery_mode_data_attr_always_present(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-mode="gallery"', $html );
    }

    // -------------------------------------------------------------------------
    // Gallery layout
    // -------------------------------------------------------------------------

    public function test_gallery_layout_defaults_to_grid(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-layout="grid"', $html );
    }

    public function test_gallery_layout_justified(): void {
        $html = $this->render( [ 'gallery-layout' => 'justified' ] );
        $this->assertStringContainsString( 'data-gallery-layout="justified"', $html );
    }

    public function test_gallery_sizing_defaults_to_ratio(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-sizing="ratio"', $html );
    }

    public function test_gallery_sizing_fixed(): void {
        $html = $this->render( [ 'gallery-sizing' => 'fixed' ] );
        $this->assertStringContainsString( 'data-gallery-sizing="fixed"', $html );
    }

    // -------------------------------------------------------------------------
    // Gallery columns (default 3/2/1)
    // -------------------------------------------------------------------------

    public function test_gallery_columns_defaults_to_3(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-columns="3"', $html );
    }

    public function test_gallery_columns_explicit_value(): void {
        $html = $this->render( [ 'gallery-columns' => '5' ] );
        $this->assertStringContainsString( 'data-gallery-columns="5"', $html );
    }

    public function test_gallery_columns_tablet_defaults_to_2(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-columns-tablet="2"', $html );
    }

    public function test_gallery_columns_tablet_explicit_value(): void {
        $html = $this->render( [ 'gallery-columns-tablet' => '3' ] );
        $this->assertStringContainsString( 'data-gallery-columns-tablet="3"', $html );
    }

    public function test_gallery_columns_mobile_defaults_to_1(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-columns-mobile="1"', $html );
    }

    public function test_gallery_columns_mobile_explicit_value(): void {
        $html = $this->render( [ 'gallery-columns-mobile' => '2' ] );
        $this->assertStringContainsString( 'data-gallery-columns-mobile="2"', $html );
    }

    // -------------------------------------------------------------------------
    // Gallery row height and rows
    // -------------------------------------------------------------------------

    public function test_gallery_row_height_defaults_to_200(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-row-height="200"', $html );
    }

    public function test_gallery_row_height_explicit(): void {
        $html = $this->render( [ 'gallery-row-height' => '300' ] );
        $this->assertStringContainsString( 'data-gallery-row-height="300"', $html );
    }

    public function test_gallery_rows_defaults_to_0(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-rows="0"', $html );
    }

    public function test_gallery_rows_explicit(): void {
        $html = $this->render( [ 'gallery-rows' => '3' ] );
        $this->assertStringContainsString( 'data-gallery-rows="3"', $html );
    }

    // -------------------------------------------------------------------------
    // Gallery scrollable
    // -------------------------------------------------------------------------

    public function test_gallery_scrollable_false_by_default(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-scrollable="false"', $html );
    }

    public function test_gallery_scrollable_true_when_enabled(): void {
        $html = $this->render( [ 'gallery-scrollable' => true ] );
        $this->assertStringContainsString( 'data-gallery-scrollable="true"', $html );
    }

    // -------------------------------------------------------------------------
    // Gallery buttons on mobile
    // -------------------------------------------------------------------------

    public function test_gallery_buttons_on_mobile_defaults_to_on_interaction(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-gallery-buttons-on-mobile="on-interaction"', $html );
    }

    public function test_gallery_buttons_on_mobile_always(): void {
        $html = $this->render( [ 'gallery-buttons-on-mobile' => 'always' ] );
        $this->assertStringContainsString( 'data-gallery-buttons-on-mobile="always"', $html );
    }

    // -------------------------------------------------------------------------
    // Gallery gap
    // -------------------------------------------------------------------------

    public function test_gallery_gap_emitted_when_set(): void {
        $html = $this->render( [ 'gallery-gap' => '10' ] );
        $this->assertStringContainsString( 'data-gallery-gap="10"', $html );
    }

    public function test_gallery_gap_not_emitted_when_absent(): void {
        $html = $this->render();
        $this->assertStringNotContainsString( 'data-gallery-gap', $html );
    }

    // -------------------------------------------------------------------------
    // Shared attributes also present in gallery mode
    // -------------------------------------------------------------------------

    public function test_gallery_has_active_bottom_center_false_by_default(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-has-active-bottom-center="false"', $html );
    }

    public function test_gallery_has_active_bottom_center_true_when_info_bottom_set(): void {
        $html = $this->render( [ 'info-bottom' => '{item}' ] );
        $this->assertStringContainsString( 'data-has-active-bottom-center="true"', $html );
    }

    public function test_gallery_lightbox_toggle_always_emitted(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'data-lightbox-toggle="disabled"', $html );
    }

    public function test_gallery_album_url_emitted(): void {
        $html = $this->render( [ 'album-url' => 'https://photos.google.com/share/test' ] );
        $this->assertStringContainsString( 'data-album-url="https://photos.google.com/share/test"', $html );
    }

    public function test_gallery_album_title_emitted(): void {
        $html = $this->render( [ 'album-title' => 'Summer 2024' ] );
        $this->assertStringContainsString( 'data-album-title="Summer 2024"', $html );
    }

    public function test_gallery_fullscreen_toggle_emitted(): void {
        $html = $this->render( [ 'fullscreen-toggle' => 'button-only' ] );
        $this->assertStringContainsString( 'data-fullscreen-toggle="button-only"', $html );
    }

    public function test_gallery_interaction_lock_emitted(): void {
        $html = $this->render( [ 'interaction-lock' => true ] );
        $this->assertStringContainsString( 'data-interaction-lock="true"', $html );
    }

    public function test_gallery_info_bottom_emitted(): void {
        $html = $this->render( [ 'gallery-info-bottom' => '{item}' ] );
        $this->assertStringContainsString( 'data-gallery-info-bottom="{item}"', $html );
    }

    public function test_gallery_info_bottom_empty_string_still_emitted(): void {
        $html = $this->render( [ 'gallery-info-bottom' => '' ] );
        $this->assertStringContainsString( 'data-gallery-info-bottom=""', $html );
    }

    public function test_gallery_inline_styles_with_background_color(): void {
        $html = $this->render( [ 'background-color' => '#123456' ] );
        $this->assertStringContainsString( '--gallery-bg-color: #123456', $html );
    }

    public function test_gallery_corner_radius_in_styles(): void {
        $html = $this->render( [ 'corner-radius' => '12' ] );
        $this->assertStringContainsString( '--jzsa-corner-radius: 12px', $html );
    }

    public function test_gallery_explicit_width_in_styles(): void {
        $html = $this->render( [
            'width'          => '900',
            'width-explicit' => true,
        ] );
        $this->assertStringContainsString( 'width: 900px', $html );
        $this->assertStringContainsString( 'max-width: 100%', $html );
    }
}
