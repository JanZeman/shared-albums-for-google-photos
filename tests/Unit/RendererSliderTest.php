<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Renderer;

/**
 * Tests for slider/carousel mode data attributes and inline styles.
 *
 * Each test calls JZSA_Renderer::render() with a minimal config and asserts
 * that the resulting HTML contains (or does not contain) the expected string.
 */
class RendererSliderTest extends TestCase {

    private JZSA_Renderer $renderer;

    protected function setUp(): void {
        $this->renderer = new JZSA_Renderer();
    }

    private function render( array $config ): string {
        return $this->renderer->render( $config );
    }

    // -------------------------------------------------------------------------
    // HTML structure
    // -------------------------------------------------------------------------

    public function test_slider_has_jzsa_album_swiper_classes(): void {
        $html = $this->render( [] );
        $this->assertStringContainsString( 'class="jzsa-album swiper jzsa-loader-pending"', $html );
    }

    public function test_slider_includes_swiper_wrapper(): void {
        $html = $this->render( [] );
        $this->assertStringContainsString( 'class="swiper-wrapper"', $html );
    }

    public function test_slider_includes_swiper_prev_next(): void {
        $html = $this->render( [] );
        $this->assertStringContainsString( 'class="swiper-button-prev"', $html );
        $this->assertStringContainsString( 'class="swiper-button-next"', $html );
    }

    public function test_slider_includes_swiper_pagination(): void {
        $html = $this->render( [] );
        $this->assertStringContainsString( 'class="swiper-pagination"', $html );
    }

    public function test_gallery_mode_not_emitted_when_absent(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'data-mode="gallery"', $html );
    }

    public function test_mode_slider_emitted(): void {
        $html = $this->render( [ 'mode' => 'slider' ] );
        $this->assertStringContainsString( 'data-mode="slider"', $html );
    }

    public function test_mode_carousel_emitted(): void {
        $html = $this->render( [ 'mode' => 'carousel' ] );
        $this->assertStringContainsString( 'data-mode="carousel"', $html );
    }

    // -------------------------------------------------------------------------
    // Inline styles
    // -------------------------------------------------------------------------

    public function test_width_emits_style_with_max_width(): void {
        $html = $this->render( [ 'width' => '800' ] );
        $this->assertStringContainsString( 'width: 800px', $html );
        $this->assertStringContainsString( 'max-width: 100%', $html );
    }

    public function test_height_emits_style(): void {
        $html = $this->render( [ 'height' => '500' ] );
        $this->assertStringContainsString( 'height: 500px', $html );
    }

    public function test_background_color_emits_css_custom_property(): void {
        $html = $this->render( [ 'background-color' => '#000000' ] );
        $this->assertStringContainsString( '--gallery-bg-color: #000000', $html );
    }

    public function test_controls_color_emits_css_custom_property(): void {
        $html = $this->render( [ 'controls-color' => '#ffffff' ] );
        $this->assertStringContainsString( '--jzsa-controls-color: #ffffff', $html );
    }

    public function test_video_controls_color_emits_css_custom_property(): void {
        $html = $this->render( [ 'video-controls-color' => '#aabbcc' ] );
        $this->assertStringContainsString( '--jzsa-video-controls-color: #aabbcc', $html );
    }

    public function test_corner_radius_emits_css_custom_property(): void {
        $html = $this->render( [ 'corner-radius' => '8' ] );
        $this->assertStringContainsString( '--jzsa-corner-radius: 8px', $html );
    }

    public function test_no_style_when_width_is_auto(): void {
        $html = $this->render( [ 'width' => 'auto' ] );
        $this->assertStringNotContainsString( 'width: ', $html );
    }

    // -------------------------------------------------------------------------
    // Slideshow
    // -------------------------------------------------------------------------

    public function test_slideshow_auto_emitted(): void {
        $html = $this->render( [ 'slideshow' => 'auto' ] );
        $this->assertStringContainsString( 'data-slideshow="auto"', $html );
    }

    public function test_slideshow_disabled_emitted(): void {
        $html = $this->render( [ 'slideshow' => 'disabled' ] );
        $this->assertStringContainsString( 'data-slideshow="disabled"', $html );
    }

    public function test_slideshow_delay_emitted(): void {
        $html = $this->render( [ 'slideshow-delay' => '3000' ] );
        $this->assertStringContainsString( 'data-slideshow-delay="3000"', $html );
    }

    public function test_slideshow_autoresume_emitted(): void {
        $html = $this->render( [ 'slideshow-autoresume' => 'true' ] );
        $this->assertStringContainsString( 'data-slideshow-autoresume="true"', $html );
    }

    // -------------------------------------------------------------------------
    // Boolean attributes
    // -------------------------------------------------------------------------

    public function test_show_navigation_true(): void {
        $html = $this->render( [ 'show-navigation' => true ] );
        $this->assertStringContainsString( 'data-show-navigation="true"', $html );
    }

    public function test_show_navigation_false(): void {
        $html = $this->render( [ 'show-navigation' => false ] );
        $this->assertStringContainsString( 'data-show-navigation="false"', $html );
    }

    public function test_show_link_button_true(): void {
        $html = $this->render( [ 'show-link-button' => true ] );
        $this->assertStringContainsString( 'data-show-link-button="true"', $html );
    }

    public function test_show_download_button_true(): void {
        $html = $this->render( [ 'show-download-button' => true ] );
        $this->assertStringContainsString( 'data-show-download-button="true"', $html );
    }

    public function test_interaction_lock_true(): void {
        $html = $this->render( [ 'interaction-lock' => true ] );
        $this->assertStringContainsString( 'data-interaction-lock="true"', $html );
    }

    public function test_video_controls_autohide_true(): void {
        $html = $this->render( [ 'video-controls-autohide' => true ] );
        $this->assertStringContainsString( 'data-video-controls-autohide="true"', $html );
    }

    // -------------------------------------------------------------------------
    // String attributes
    // -------------------------------------------------------------------------

    public function test_image_fit_contain(): void {
        $html = $this->render( [ 'image-fit' => 'contain' ] );
        $this->assertStringContainsString( 'data-image-fit="contain"', $html );
    }

    public function test_fullscreen_image_fit_emitted(): void {
        $html = $this->render( [ 'fullscreen-image-fit' => 'cover' ] );
        $this->assertStringContainsString( 'data-fullscreen-image-fit="cover"', $html );
    }

    public function test_album_url_emitted(): void {
        $html = $this->render( [ 'album-url' => 'https://photos.google.com/share/test' ] );
        $this->assertStringContainsString( 'data-album-url="https://photos.google.com/share/test"', $html );
    }

    public function test_album_title_emitted(): void {
        $html = $this->render( [ 'album-title' => 'My Vacation' ] );
        $this->assertStringContainsString( 'data-album-title="My Vacation"', $html );
    }

    public function test_fullscreen_toggle_emitted(): void {
        $html = $this->render( [ 'fullscreen-toggle' => 'button-only' ] );
        $this->assertStringContainsString( 'data-fullscreen-toggle="button-only"', $html );
    }

    public function test_fullscreen_corner_radius_emitted(): void {
        $html = $this->render( [ 'fullscreen-corner-radius' => 14 ] );
        $this->assertStringContainsString( 'data-fullscreen-corner-radius="14"', $html );
    }

    public function test_start_at_emitted(): void {
        $html = $this->render( [ 'start-at' => '3' ] );
        $this->assertStringContainsString( 'data-start-at="3"', $html );
    }

    public function test_start_at_empty_string_not_emitted(): void {
        $html = $this->render( [ 'start-at' => '' ] );
        $this->assertStringNotContainsString( 'data-start-at', $html );
    }

    public function test_download_size_warning_emitted(): void {
        $html = $this->render( [ 'download-size-warning' => '10' ] );
        $this->assertStringContainsString( 'data-download-size-warning="10"', $html );
    }

    public function test_background_color_emitted_as_data_attr(): void {
        $html = $this->render( [ 'background-color' => '#111111' ] );
        $this->assertStringContainsString( 'data-background-color="#111111"', $html );
    }

    public function test_controls_color_emitted_as_data_attr(): void {
        $html = $this->render( [ 'controls-color' => '#222222' ] );
        $this->assertStringContainsString( 'data-controls-color="#222222"', $html );
    }

    public function test_fullscreen_background_color_emitted(): void {
        $html = $this->render( [ 'fullscreen-background-color' => '#333333' ] );
        $this->assertStringContainsString( 'data-fullscreen-background-color="#333333"', $html );
    }

    public function test_fullscreen_controls_color_emitted(): void {
        $html = $this->render( [ 'fullscreen-controls-color' => '#444444' ] );
        $this->assertStringContainsString( 'data-fullscreen-controls-color="#444444"', $html );
    }

    // -------------------------------------------------------------------------
    // has-active-bottom-center
    // -------------------------------------------------------------------------

    public function test_has_active_bottom_center_false_by_default(): void {
        $html = $this->render( [] );
        $this->assertStringContainsString( 'data-has-active-bottom-center="false"', $html );
    }

    public function test_has_active_bottom_center_true_when_info_bottom_set(): void {
        $html = $this->render( [ 'info-bottom' => '{item}' ] );
        $this->assertStringContainsString( 'data-has-active-bottom-center="true"', $html );
    }

    // -------------------------------------------------------------------------
    // lightbox-toggle always emitted
    // -------------------------------------------------------------------------

    public function test_lightbox_toggle_disabled_emitted_by_default(): void {
        $html = $this->render( [] );
        $this->assertStringContainsString( 'data-lightbox-toggle="disabled"', $html );
    }

    public function test_lightbox_toggle_button_only_emitted(): void {
        $html = $this->render( [ 'lightbox-toggle' => 'button-only' ] );
        $this->assertStringContainsString( 'data-lightbox-toggle="button-only"', $html );
    }

    public function test_lightbox_toggle_overridden_to_disabled_by_interaction_lock(): void {
        $html = $this->render( [
            'lightbox-toggle'  => 'button-only',
            'interaction-lock' => true,
        ] );
        $this->assertStringContainsString( 'data-lightbox-toggle="disabled"', $html );
    }

    // -------------------------------------------------------------------------
    // info-text-align
    // -------------------------------------------------------------------------

    public function test_info_text_align_left_emitted(): void {
        $html = $this->render( [ 'info-text-align' => 'left' ] );
        $this->assertStringContainsString( 'data-info-text-align="left"', $html );
    }

    public function test_info_text_align_center_not_emitted(): void {
        $html = $this->render( [ 'info-text-align' => 'center' ] );
        $this->assertStringNotContainsString( 'data-info-text-align', $html );
    }

    // -------------------------------------------------------------------------
    // External link and download buttons (rendered when album-url set)
    // -------------------------------------------------------------------------

    public function test_external_link_button_rendered_when_link_button_and_url_set(): void {
        $html = $this->render( [
            'show-link-button' => true,
            'album-url'        => 'https://photos.google.com/share/test',
        ] );
        $this->assertStringContainsString( 'class="swiper-button-external-link"', $html );
    }

    public function test_external_link_button_not_rendered_without_url(): void {
        $html = $this->render( [ 'show-link-button' => true ] );
        $this->assertStringNotContainsString( 'class="swiper-button-external-link"', $html );
    }

    public function test_download_button_rendered_when_show_download_button(): void {
        $html = $this->render( [ 'show-download-button' => true ] );
        $this->assertStringContainsString( 'class="swiper-button-download"', $html );
    }

    public function test_download_button_not_rendered_without_flag(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'class="swiper-button-download"', $html );
    }

    // -------------------------------------------------------------------------
    // Responsive aspect ratio
    // -------------------------------------------------------------------------

    public function test_responsive_ar_attribute_emitted_when_both_dimensions_explicit(): void {
        $html = $this->render( [
            'width'          => '800',
            'height'         => '600',
            'width-explicit' => true,
            'height-explicit' => true,
        ] );
        $this->assertStringContainsString( 'data-responsive-ar="true"', $html );
        $this->assertStringContainsString( '--jzsa-ar: 800 / 600', $html );
    }

    public function test_responsive_ar_not_emitted_without_explicit_flags(): void {
        $html = $this->render( [ 'width' => '800', 'height' => '600' ] );
        $this->assertStringNotContainsString( 'data-responsive-ar', $html );
    }
}
