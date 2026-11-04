<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use JZSA_Renderer;
use PHPUnit\Framework\TestCase;

/**
 * album-heading: an optional heading with the album title above the album, in any mode.
 */
class RendererAlbumHeadingTest extends TestCase {

	private function render( array $config ): string {
		return ( new JZSA_Renderer() )->render( $config );
	}

	public function test_no_heading_by_default(): void {
		$this->assertStringNotContainsString( 'jzsa-album-heading', $this->render( array( 'mode' => 'gallery', 'album-title' => 'Blådal' ) ) );
	}

	public function test_heading_resolves_album_title_and_items_before_the_gallery(): void {
		$html = $this->render(
			array(
				'mode'          => 'gallery',
				'album-title'   => 'Blådal & <friends>',
				'photos'        => array( 'a', 'b', 'c' ),
				'album-heading' => '{album-title} ({items})',
			)
		);

		$this->assertStringContainsString( '<h3 class="jzsa-album-heading">Blådal &amp; &lt;friends&gt; (3)</h3>', $html );
		$this->assertLessThan( strpos( $html, 'jzsa-gallery-album' ), strpos( $html, 'jzsa-album-heading' ) );
	}

	public function test_heading_takes_the_album_width_in_every_mode(): void {
		$gallery = $this->render( array( 'mode' => 'gallery', 'album-title' => 'T', 'album-heading' => '{album-title}', 'width' => 560, 'width-explicit' => true ) );
		$this->assertStringContainsString( '<h3 class="jzsa-album-heading" style="width: 560px; max-width: 100%;">T</h3>', $gallery );

		// A gallery without an explicit width is full width, and so is its heading.
		$auto = $this->render( array( 'mode' => 'gallery', 'album-title' => 'T', 'album-heading' => '{album-title}', 'width' => 400 ) );
		$this->assertStringContainsString( '<h3 class="jzsa-album-heading">T</h3>', $auto );

		$slider = $this->render( array( 'mode' => 'slider', 'album-title' => 'T', 'album-heading' => '{album-title}', 'width' => 800 ) );
		$this->assertStringContainsString( '<h3 class="jzsa-album-heading" style="width: 800px; max-width: 100%;">T</h3>', $slider );
	}

	public function test_heading_alignment_and_indent(): void {
		$base = array( 'mode' => 'gallery', 'album-title' => 'T', 'album-heading' => '{album-title}' );

		$left = $this->render( $base + array( 'album-heading-text-align' => 'left', 'album-heading-indent' => 10 ) );
		$this->assertStringContainsString( '<h3 class="jzsa-album-heading" style="padding-left: 10px;">T</h3>', $left );

		$right = $this->render( $base + array( 'album-heading-text-align' => 'right', 'album-heading-indent' => 12 ) );
		$this->assertStringContainsString( '<h3 class="jzsa-album-heading" style="text-align: right; padding-right: 12px;">T</h3>', $right );

		// Centered text has no edge to keep away from, so the indent is not used.
		$center = $this->render( $base + array( 'album-heading-text-align' => 'center', 'album-heading-indent' => 10 ) );
		$this->assertStringContainsString( '<h3 class="jzsa-album-heading" style="text-align: center;">T</h3>', $center );
	}

	public function test_heading_is_skipped_when_it_resolves_to_nothing(): void {
		$this->assertStringNotContainsString( 'jzsa-album-heading', $this->render( array( 'mode' => 'gallery', 'album-heading' => '{album-title}' ) ) );
	}
}
