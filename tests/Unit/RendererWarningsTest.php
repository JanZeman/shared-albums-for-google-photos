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

	// -------------------------------------------------------------------------
	// Unknown parameter notice
	// -------------------------------------------------------------------------

	public function test_unknown_attribute_notice_lists_every_name(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'unknown-attributes' => array( 'viewer-max-widht', 'gallry-rows' ) ) );

		$this->assertStringContainsString( 'jzsa-warning', $html );
		$this->assertStringContainsString( '<code>viewer-max-widht</code>', $html );
		$this->assertStringContainsString( '<code>gallry-rows</code>', $html );
	}

	public function test_unknown_attribute_notice_always_recommends_the_playground(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'unknown-attributes' => array( 'viewer-max-widht' ) ) );

		$this->assertStringContainsString( 'Playground', $html );
		$this->assertStringContainsString( '#jzsa-playground-shortcode', $html );
	}

	public function test_unknown_attribute_notice_counts_singular_and_plural(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$one  = $this->render( array( 'unknown-attributes' => array( 'a-typo' ) ) );
		$more = $this->render( array( 'unknown-attributes' => array( 'a-typo', 'b-typo' ) ) );

		$this->assertStringContainsString( '1 Parameter Was Ignored', $one );
		$this->assertStringContainsString( '2 Parameters Were Ignored', $more );
	}

	public function test_unknown_attribute_notice_escapes_the_reported_name(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'unknown-attributes' => array( '<script>alert(1)</script>' ) ) );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_unknown_attribute_notice_not_shown_to_visitors(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = false;

		$html = $this->render( array( 'unknown-attributes' => array( 'viewer-max-widht' ) ) );

		$this->assertStringNotContainsString( 'viewer-max-widht', $html );
	}

	public function test_unknown_attribute_notice_not_shown_when_nothing_is_unknown(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'unknown-attributes' => array() ) );

		$this->assertStringNotContainsString( 'Were Ignored', $html );
	}

	// -------------------------------------------------------------------------
	// Unplayable video notice
	// -------------------------------------------------------------------------

	public function test_unplayable_video_notice_names_the_videos_and_the_fix(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'unplayable-videos' => array( 'MVI_6384.AVI', 'MVI_6385.AVI' ) ) );

		$this->assertStringContainsString( '2 Videos Cannot Be Played', $html );
		$this->assertStringContainsString( '<code>MVI_6384.AVI</code>', $html );
		$this->assertStringContainsString( 'MP4 or MOV', $html );
		$this->assertStringContainsString( '#jzsa-plugin-cache', $html );
	}

	public function test_unplayable_video_notice_lists_at_most_three_names(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = true;
		$GLOBALS['jzsa_test_current_user_can']  = true;

		$html = $this->render( array( 'unplayable-videos' => array( 'a.avi', 'b.avi', 'c.avi', 'd.avi' ) ) );

		$this->assertStringContainsString( '4 Videos Cannot Be Played', $html );
		$this->assertStringContainsString( '<code>c.avi</code> and 1 more', $html );
		$this->assertStringNotContainsString( 'd.avi', $html );
	}

	public function test_unplayable_video_notice_not_shown_to_visitors(): void {
		$GLOBALS['jzsa_test_is_user_logged_in'] = false;

		$html = $this->render( array( 'unplayable-videos' => array( 'MVI_6384.AVI' ) ) );

		$this->assertStringNotContainsString( 'MVI_6384.AVI', $html );
	}
}
