<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Renderer;

/**
 * Tests for conditional button rendering in slider/carousel mode.
 *
 * Covers: lightbox button, fullscreen button, dual-expand class, and
 * the various conditions that suppress each button.
 */
class RendererButtonsTest extends TestCase {

    private JZSA_Renderer $renderer;

    protected function setUp(): void {
        $this->renderer = new JZSA_Renderer();
    }

    private function render( array $config ): string {
        return $this->renderer->render( $config );
    }

    // -------------------------------------------------------------------------
    // Lightbox button (.swiper-button-lightbox)
    // -------------------------------------------------------------------------

    public function test_no_lightbox_button_when_lightbox_disabled(): void {
        $html = $this->render( [ 'lightbox-toggle' => 'disabled' ] );
        $this->assertStringNotContainsString( 'swiper-button-lightbox', $html );
    }

    public function test_no_lightbox_button_when_lightbox_not_set(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'swiper-button-lightbox', $html );
    }

    public function test_no_lightbox_button_in_click_mode(): void {
        // In click mode, clicking the slide itself opens the lightbox.
        // A dedicated button would be redundant.
        $html = $this->render( [ 'lightbox-toggle' => 'click' ] );
        $this->assertStringNotContainsString( 'swiper-button-lightbox', $html );
    }

    public function test_no_lightbox_button_in_double_click_mode(): void {
        $html = $this->render( [ 'lightbox-toggle' => 'double-click' ] );
        $this->assertStringNotContainsString( 'swiper-button-lightbox', $html );
    }

    public function test_lightbox_button_present_in_button_only_mode(): void {
        $html = $this->render( [ 'lightbox-toggle' => 'button-only' ] );
        $this->assertStringContainsString( 'swiper-button-lightbox', $html );
    }

    public function test_no_lightbox_button_in_carousel_mode_even_with_button_only(): void {
        // Carousel uses per-tile buttons; the global lightbox button is suppressed.
        $html = $this->render( [
            'mode'            => 'carousel',
            'lightbox-toggle' => 'button-only',
        ] );
        $this->assertStringNotContainsString( 'swiper-button-lightbox', $html );
    }

    public function test_no_lightbox_button_when_interaction_lock_active(): void {
        $html = $this->render( [
            'lightbox-toggle'  => 'button-only',
            'interaction-lock' => true,
        ] );
        $this->assertStringNotContainsString( 'swiper-button-lightbox', $html );
    }

    // -------------------------------------------------------------------------
    // Fullscreen button (.swiper-button-fullscreen)
    // -------------------------------------------------------------------------

    public function test_no_fullscreen_button_when_fullscreen_disabled(): void {
        $html = $this->render( [ 'fullscreen-toggle' => 'disabled' ] );
        $this->assertStringNotContainsString( 'swiper-button-fullscreen', $html );
    }

    public function test_no_fullscreen_button_when_fullscreen_not_set(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'swiper-button-fullscreen', $html );
    }

    public function test_fullscreen_button_present_in_button_only_mode(): void {
        $html = $this->render( [ 'fullscreen-toggle' => 'button-only' ] );
        $this->assertStringContainsString( 'swiper-button-fullscreen', $html );
    }

    public function test_fullscreen_button_present_in_click_mode(): void {
        $html = $this->render( [ 'fullscreen-toggle' => 'click' ] );
        $this->assertStringContainsString( 'swiper-button-fullscreen', $html );
    }

    public function test_fullscreen_button_present_in_double_click_mode(): void {
        $html = $this->render( [ 'fullscreen-toggle' => 'double-click' ] );
        $this->assertStringContainsString( 'swiper-button-fullscreen', $html );
    }

    // -------------------------------------------------------------------------
    // Dual expand (both buttons)
    // -------------------------------------------------------------------------

    public function test_both_buttons_present_in_dual_expand(): void {
        $html = $this->render( [
            'lightbox-toggle'   => 'button-only',
            'fullscreen-toggle' => 'button-only',
        ] );
        $this->assertStringContainsString( 'swiper-button-lightbox', $html );
        $this->assertStringContainsString( 'swiper-button-fullscreen', $html );
    }

    public function test_jzsa_has_dual_expand_class_when_both_enabled(): void {
        $html = $this->render( [
            'lightbox-toggle'   => 'button-only',
            'fullscreen-toggle' => 'button-only',
        ] );
        $this->assertStringContainsString( 'jzsa-has-dual-expand', $html );
    }

    public function test_no_dual_expand_class_when_only_fullscreen(): void {
        $html = $this->render( [ 'fullscreen-toggle' => 'button-only' ] );
        $this->assertStringNotContainsString( 'jzsa-has-dual-expand', $html );
    }

    public function test_no_dual_expand_class_when_only_lightbox(): void {
        $html = $this->render( [ 'lightbox-toggle' => 'button-only' ] );
        $this->assertStringNotContainsString( 'jzsa-has-dual-expand', $html );
    }

    public function test_no_dual_expand_class_when_both_disabled(): void {
        $html = $this->render( [] );
        $this->assertStringNotContainsString( 'jzsa-has-dual-expand', $html );
    }

    // -------------------------------------------------------------------------
    // Carousel + fullscreen (fullscreen button still renders in carousel mode)
    // -------------------------------------------------------------------------

    public function test_fullscreen_button_present_in_carousel_mode(): void {
        $html = $this->render( [
            'mode'              => 'carousel',
            'fullscreen-toggle' => 'button-only',
        ] );
        $this->assertStringContainsString( 'swiper-button-fullscreen', $html );
    }
}
