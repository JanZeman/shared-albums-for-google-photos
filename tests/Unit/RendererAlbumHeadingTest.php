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

	public function test_no_album_link_by_default(): void {
		$this->assertStringNotContainsString( 'jzsa-album-link', $this->render( array( 'mode' => 'gallery', 'album-url' => 'https://photos.icloud.com/shared/album/abc' ) ) );
	}

	public function test_album_link_opens_the_album_below_the_gallery(): void {
		$html = $this->render(
			array(
				'mode'                  => 'gallery',
				'album-title'           => 'Blådal',
				'album-url'             => 'https://photos.icloud.com/shared/album/abc',
				'album-link'            => 'Open {album-title} in iCloud',
				'album-link-text-align' => 'center',
				'width'                 => 966,
				'width-explicit'        => true,
			)
		);

		$this->assertStringContainsString( '<p class="jzsa-album-link" style="width: 966px; max-width: 100%; text-align: center;"><a href="https://photos.icloud.com/shared/album/abc" target="_blank" rel="noopener noreferrer">Open Blådal in iCloud</a></p>', $html );
		$this->assertGreaterThan( strpos( $html, 'jzsa-gallery-album' ), strpos( $html, 'jzsa-album-link' ) );
	}

	public function test_album_link_follows_slider_albums_too(): void {
		$html = $this->render( array( 'mode' => 'slider', 'album-url' => 'https://photos.google.com/share/x', 'album-link' => 'Open album' ) );
		$this->assertStringEndsWith( '<p class="jzsa-album-link"><a href="https://photos.google.com/share/x" target="_blank" rel="noopener noreferrer">Open album</a></p>', $html );
	}

	public function test_album_link_alignment_and_indent(): void {
		$base = array( 'mode' => 'gallery', 'album-url' => 'https://photos.icloud.com/shared/album/abc', 'album-link' => 'Open' );

		$right = $this->render( $base + array( 'album-link-text-align' => 'right', 'album-link-indent' => 12 ) );
		$this->assertStringContainsString( '<p class="jzsa-album-link" style="text-align: right; padding-right: 12px;">', $right );

		$center = $this->render( $base + array( 'album-link-text-align' => 'center', 'album-link-indent' => 12 ) );
		$this->assertStringContainsString( '<p class="jzsa-album-link" style="text-align: center;">', $center );
	}

	public function test_heading_and_link_on_the_same_side_share_a_row(): void {
		$html = $this->render(
			array(
				'mode'                  => 'gallery',
				'album-title'           => 'T',
				'album-url'             => 'https://photos.icloud.com/shared/album/abc',
				'album-heading'         => '{album-title}',
				'album-link'            => 'Open',
				'album-link-position'   => 'top',
				'album-link-text-align' => 'right',
				'album-link-indent'     => 8,
				'width'                 => 600,
				'width-explicit'        => true,
			)
		);

		$this->assertStringStartsWith(
			'<div class="jzsa-album-bar jzsa-album-bar-top" style="width: 600px; max-width: 100%;">'
			. '<h3 class="jzsa-album-heading jzsa-album-bar-left">T</h3>'
			. '<p class="jzsa-album-link jzsa-album-bar-right" style="text-align: right; padding-right: 8px;"><a href="https://photos.icloud.com/shared/album/abc" target="_blank" rel="noopener noreferrer">Open</a></p>'
			. '</div>',
			$html
		);
		$this->assertStringNotContainsString( 'jzsa-album-bar-bottom', $html );
	}

	public function test_heading_can_move_below_the_album(): void {
		$html = $this->render( array( 'mode' => 'slider', 'album-title' => 'T', 'album-heading' => '{album-title}', 'album-heading-position' => 'bottom' ) );
		$this->assertStringEndsWith( '<h3 class="jzsa-album-heading">T</h3>', $html );
	}

	public function test_a_lone_text_on_a_shared_side_keeps_its_plain_markup(): void {
		// The link has no album URL, so the heading is alone at the top.
		$html = $this->render( array( 'mode' => 'gallery', 'album-title' => 'T', 'album-heading' => '{album-title}', 'album-link' => 'Open', 'album-link-position' => 'top' ) );
		$this->assertStringStartsWith( '<h3 class="jzsa-album-heading">T</h3>', $html );
		$this->assertStringNotContainsString( 'jzsa-album-bar', $html );
	}
}
