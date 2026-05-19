<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Renderer;

/**
 * Tests for admin-only renderer warnings:
 * the deprecation notice and the mosaic-in-gallery-mode notice.
 *
 * Both notices require the current user to be logged in AND have admin
 * capability, so each test sets/clears jzsa_test_is_user_logged_in
 * and jzsa_test_current_user_can around the assertion.
 */
class RendererWarningsTest extends TestCase {

	private JZSA_Renderer $renderer;

	protected function setUp(): void {
		$this->renderer = new JZSA_Renderer();
		$GLOBALS['jzsa_test_is_user_logged_in'] = false;
		$GLOBALS['jzsa_test_current_user_can']  = false;
	}

	protected function tearDown(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = false;
		$GLOBALS['jzsa_test_current_user_can']  = false;
	}

	private function render( array $config ): string {
		return $this->renderer->render( $config );
	}

	// -------------------------------------------------------------------------
	// Deprecation notice
	// -------------------------------------------------------------------------

	public function test_deprecation_notice_shown_to_logged_in_admin(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'show-deprecation-warning' => true ) );

		$this->assertStringContainsString( 'jzsa-warning', $html );
		$this->assertStringContainsString( 'Short Link Detected', $html );
	}

	public function test_deprecation_notice_not_shown_when_not_logged_in(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = false;

		$html = $this->render( array( 'show-deprecation-warning' => true ) );

		$this->assertStringNotContainsString( 'Short Link Detected', $html );
	}

	public function test_deprecation_notice_not_shown_when_flag_is_false(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'show-deprecation-warning' => false ) );

		$this->assertStringNotContainsString( 'Short Link Detected', $html );
	}

	// -------------------------------------------------------------------------
	// Mosaic-in-gallery-mode notice
	// -------------------------------------------------------------------------

	public function test_mosaic_mode_notice_shown_when_mosaic_in_gallery_mode_for_admin(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'mode' => 'gallery', 'mosaic' => true ) );

		$this->assertStringContainsString( 'jzsa-warning', $html );
		$this->assertStringContainsString( 'Mosaic Requires Slider or Carousel Mode', $html );
	}

	public function test_mosaic_mode_notice_not_shown_when_not_logged_in(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = false;

		$html = $this->render( array( 'mode' => 'gallery', 'mosaic' => true ) );

		$this->assertStringNotContainsString( 'Mosaic Requires Slider or Carousel Mode', $html );
	}

	public function test_mosaic_mode_notice_not_shown_in_slider_mode(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'mode' => 'slider', 'mosaic' => true ) );

		$this->assertStringNotContainsString( 'Mosaic Requires Slider or Carousel Mode', $html );
	}
}
