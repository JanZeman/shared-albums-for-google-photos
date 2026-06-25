<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Renderer;

/**
 * Tests for lightbox-specific data attributes emitted by JZSA_Renderer.
 *
 * Lightbox attributes (data-lightbox-*) are only emitted when lightbox-toggle
 * is not 'disabled'. They control how the JS lightbox overlay behaves after
 * the album element is moved into the backdrop div.
 */
class RendererLightboxAttrTest extends TestCase {

    private JZSA_Renderer $renderer;

    protected function setUp(): void {
        $this->renderer = new JZSA_Renderer();
    }

    private function renderLightbox( array $extra = [] ): string {
        return $this->renderer->render( array_merge(
            [ 'lightbox-toggle' => 'button-only' ],
            $extra
        ) );
    }

    // -------------------------------------------------------------------------
    // data-lightbox-toggle always emitted (covered in RendererSliderTest too,
    // repeated here for completeness of this focused suite)
    // -------------------------------------------------------------------------

    public function test_lightbox_toggle_button_only_emitted(): void {
        $html = $this->renderLightbox();
        $this->assertStringContainsString( 'data-lightbox-toggle="button-only"', $html );
    }

    public function test_lightbox_toggle_click_emitted(): void {
        $html = $this->renderer->render( [ 'lightbox-toggle' => 'click' ] );
        $this->assertStringContainsString( 'data-lightbox-toggle="click"', $html );
    }

    public function test_lightbox_toggle_double_click_emitted(): void {
        $html = $this->renderer->render( [ 'lightbox-toggle' => 'double-click' ] );
        $this->assertStringContainsString( 'data-lightbox-toggle="double-click"', $html );
    }

    public function test_lightbox_toggle_disabled_still_emitted(): void {
        $html = $this->renderer->render( [] );
        $this->assertStringContainsString( 'data-lightbox-toggle="disabled"', $html );
    }

    // -------------------------------------------------------------------------
    // Lightbox-specific attributes only emitted when not disabled
    // -------------------------------------------------------------------------

    public function test_lightbox_image_fit_emitted_when_set(): void {
        $html = $this->renderLightbox( [ 'lightbox-image-fit' => 'contain' ] );
        $this->assertStringContainsString( 'data-lightbox-image-fit="contain"', $html );
    }

    public function test_lightbox_image_fit_not_emitted_when_disabled(): void {
        $html = $this->renderer->render( [
            'lightbox-toggle'   => 'disabled',
            'lightbox-image-fit' => 'contain',
        ] );
        $this->assertStringNotContainsString( 'data-lightbox-image-fit', $html );
    }

    public function test_lightbox_max_width_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-max-width' => '1200' ] );
        $this->assertStringContainsString( 'data-lightbox-max-width="1200"', $html );
    }

    public function test_lightbox_max_width_not_emitted_when_disabled(): void {
        $html = $this->renderer->render( [
            'lightbox-toggle'    => 'disabled',
            'lightbox-max-width' => '1200',
        ] );
        $this->assertStringNotContainsString( 'data-lightbox-max-width', $html );
    }

    public function test_lightbox_max_height_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-max-height' => '800' ] );
        $this->assertStringContainsString( 'data-lightbox-max-height="800"', $html );
    }

    public function test_lightbox_background_color_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-background-color' => '#111111' ] );
        $this->assertStringContainsString( 'data-lightbox-background-color="#111111"', $html );
    }

    public function test_lightbox_corner_radius_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-corner-radius' => '12' ] );
        $this->assertStringContainsString( 'data-lightbox-corner-radius="12"', $html );
    }

    // -------------------------------------------------------------------------
    // Lightbox display settings (applied by JS when overlay opens)
    // -------------------------------------------------------------------------

    public function test_lightbox_controls_color_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-controls-color' => '#ffffff' ] );
        $this->assertStringContainsString( 'data-lightbox-controls-color="#ffffff"', $html );
    }

    public function test_lightbox_video_controls_color_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-video-controls-color' => '#aabbcc' ] );
        $this->assertStringContainsString( 'data-lightbox-video-controls-color="#aabbcc"', $html );
    }

    public function test_lightbox_video_controls_autohide_true(): void {
        $html = $this->renderLightbox( [ 'lightbox-video-controls-autohide' => true ] );
        $this->assertStringContainsString( 'data-lightbox-video-controls-autohide="true"', $html );
    }

    public function test_lightbox_video_controls_autohide_false(): void {
        $html = $this->renderLightbox( [ 'lightbox-video-controls-autohide' => false ] );
        $this->assertStringContainsString( 'data-lightbox-video-controls-autohide="false"', $html );
    }

    public function test_lightbox_show_navigation_true(): void {
        $html = $this->renderLightbox( [ 'lightbox-show-navigation' => true ] );
        $this->assertStringContainsString( 'data-lightbox-show-navigation="true"', $html );
    }

    public function test_lightbox_show_navigation_false(): void {
        $html = $this->renderLightbox( [ 'lightbox-show-navigation' => false ] );
        $this->assertStringContainsString( 'data-lightbox-show-navigation="false"', $html );
    }

    public function test_lightbox_show_link_button_true(): void {
        $html = $this->renderLightbox( [ 'lightbox-show-link-button' => true ] );
        $this->assertStringContainsString( 'data-lightbox-show-link-button="true"', $html );
    }

    public function test_lightbox_show_link_button_false(): void {
        $html = $this->renderLightbox( [ 'lightbox-show-link-button' => false ] );
        $this->assertStringContainsString( 'data-lightbox-show-link-button="false"', $html );
    }

    public function test_lightbox_show_download_button_true(): void {
        $html = $this->renderLightbox( [ 'lightbox-show-download-button' => true ] );
        $this->assertStringContainsString( 'data-lightbox-show-download-button="true"', $html );
    }

    // -------------------------------------------------------------------------
    // Lightbox slideshow settings
    // -------------------------------------------------------------------------

    public function test_lightbox_slideshow_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-slideshow' => 'auto' ] );
        $this->assertStringContainsString( 'data-lightbox-slideshow="auto"', $html );
    }

    public function test_lightbox_slideshow_delay_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-slideshow-delay' => '3000' ] );
        $this->assertStringContainsString( 'data-lightbox-slideshow-delay="3000"', $html );
    }

    public function test_lightbox_slideshow_autoresume_emitted(): void {
        $html = $this->renderLightbox( [ 'lightbox-slideshow-autoresume' => 'true' ] );
        $this->assertStringContainsString( 'data-lightbox-slideshow-autoresume="true"', $html );
    }

    public function test_lightbox_info_overrides_are_emitted(): void {
        $html = $this->renderLightbox( [
            'lightbox-info-top'           => '{description}',
            'lightbox-info-top-secondary' => '{camera}',
            'lightbox-info-bottom'        => '{item} / {items}',
            'lightbox-info-font-size'     => 18,
            'lightbox-info-font-family'   => 'Georgia, serif',
            'lightbox-info-font-color'    => '#aabbcc',
        ] );

        $this->assertStringContainsString( 'data-lightbox-info-top="{description}"', $html );
        $this->assertStringContainsString( 'data-lightbox-info-top-secondary="{camera}"', $html );
        $this->assertStringContainsString( 'data-lightbox-info-bottom="{item} / {items}"', $html );
        $this->assertStringContainsString( 'data-lightbox-info-font-size="18"', $html );
        $this->assertStringContainsString( 'data-lightbox-info-font-family="Georgia, serif"', $html );
        $this->assertStringContainsString( 'data-lightbox-info-font-color="#aabbcc"', $html );
    }

    public function test_lightbox_mosaic_overrides_are_emitted(): void {
        $html = $this->renderLightbox( [
            'lightbox-mosaic'               => true,
            'lightbox-mosaic-position'      => 'left',
            'lightbox-mosaic-layout'        => 'overlay',
            'lightbox-mosaic-count'         => 6,
            'lightbox-mosaic-gap'           => 5,
            'lightbox-mosaic-opacity'       => 0.5,
            'lightbox-mosaic-background'    => '#112233',
            'lightbox-mosaic-corner-radius' => 9,
        ] );

        $this->assertStringContainsString( 'data-lightbox-mosaic="true"', $html );
        $this->assertStringContainsString( 'data-lightbox-mosaic-position="left"', $html );
        $this->assertStringContainsString( 'data-lightbox-mosaic-layout="overlay"', $html );
        $this->assertStringContainsString( 'data-lightbox-mosaic-count="6"', $html );
        $this->assertStringContainsString( 'data-lightbox-mosaic-gap="5"', $html );
        $this->assertStringContainsString( 'data-lightbox-mosaic-opacity="0.5"', $html );
        $this->assertStringContainsString( 'data-lightbox-mosaic-background="#112233"', $html );
        $this->assertStringContainsString( 'data-lightbox-mosaic-corner-radius="9"', $html );
    }

    // -------------------------------------------------------------------------
    // Lightbox attributes absent when disabled (spot-check additional ones)
    // -------------------------------------------------------------------------

    public function test_no_lightbox_attrs_when_interaction_lock_set(): void {
        $html = $this->renderer->render( [
            'lightbox-toggle'    => 'button-only',
            'lightbox-max-width' => '1000',
            'interaction-lock'   => true,
        ] );
        // interaction-lock forces lightbox-toggle to disabled, so lightbox attrs are suppressed.
        $this->assertStringNotContainsString( 'data-lightbox-max-width', $html );
        $this->assertStringContainsString( 'data-lightbox-toggle="disabled"', $html );
    }
}
