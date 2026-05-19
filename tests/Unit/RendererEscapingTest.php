<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Renderer;

/**
 * Tests for renderer escaping at the HTML attribute/error-message boundary.
 */
class RendererEscapingTest extends TestCase {

    private JZSA_Renderer $renderer;

    protected function setUp(): void {
        $this->renderer = new JZSA_Renderer();
    }

    private function render( array $config ): string {
        return $this->renderer->render( $config );
    }

    public function test_slider_album_title_is_escaped_in_data_attribute(): void {
        $html = $this->render( array( 'album-title' => 'Summer "Best" <script>alert(1)</script>' ) );

        $this->assertStringContainsString( 'data-album-title="Summer &quot;Best&quot; &lt;script&gt;alert(1)&lt;/script&gt;"', $html );
        $this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
    }

    public function test_gallery_album_title_is_escaped_in_data_attribute(): void {
        $html = $this->render(
            array(
                'mode'        => 'gallery',
                'album-title' => "Jane's <strong>Album</strong>",
            )
        );

        $this->assertStringContainsString( 'data-album-title="Jane&#039;s &lt;strong&gt;Album&lt;/strong&gt;"', $html );
        $this->assertStringNotContainsString( '<strong>Album</strong>', $html );
    }

    public function test_slider_info_format_strings_are_escaped(): void {
        $html = $this->render(
            array(
                'info-top'           => 'Taken by "Jane" <img src=x>',
                'info-top-secondary' => "Camera's <b>model</b>",
                'info-bottom'        => '<span onclick="x()">Caption</span>',
            )
        );

        $this->assertStringContainsString( 'data-info-top="Taken by &quot;Jane&quot; &lt;img src=x&gt;"', $html );
        $this->assertStringContainsString( 'data-info-top-secondary="Camera&#039;s &lt;b&gt;model&lt;/b&gt;"', $html );
        $this->assertStringContainsString( 'data-info-bottom="&lt;span onclick=&quot;x()&quot;&gt;Caption&lt;/span&gt;"', $html );
        $this->assertStringNotContainsString( '<span onclick="x()">', $html );
    }

    public function test_gallery_info_format_strings_are_escaped(): void {
        $html = $this->render(
            array(
                'mode'                => 'gallery',
                'fullscreen-info-top' => 'Fullscreen <script>alert(1)</script>',
                'gallery-info-bottom' => 'Gallery "caption" <em>raw</em>',
            )
        );

        $this->assertStringContainsString( 'data-fullscreen-info-top="Fullscreen &lt;script&gt;alert(1)&lt;/script&gt;"', $html );
        $this->assertStringContainsString( 'data-gallery-info-bottom="Gallery &quot;caption&quot; &lt;em&gt;raw&lt;/em&gt;"', $html );
        $this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
    }

    public function test_photo_json_payload_is_escaped_for_single_quoted_attribute(): void {
        $html = $this->render(
            array(
                'photos' => array(
                    array(
                        'url'   => 'https://example.com/photo.jpg',
                        'title' => "Jane's <img src=x onerror=\"alert(1)\">",
                    ),
                ),
            )
        );

        $this->assertStringContainsString( 'data-all-photos=', $html );
        $this->assertStringContainsString( 'Jane&#039;s &lt;img src=x onerror=\&quot;alert(1)\&quot;&gt;', $html );
        $this->assertStringNotContainsString( '<img src=x onerror="alert(1)">', $html );
    }

    public function test_numeric_attributes_are_cast_to_integers(): void {
        $html = $this->render(
            array(
                'slideshow-delay'       => '3000" autofocus="autofocus',
                'download-size-warning' => '500000<script>',
                'mosaic-count'          => '7 onclick="x"',
            )
        );

        $this->assertStringContainsString( 'data-download-size-warning="500000"', $html );
        $this->assertStringContainsString( 'data-mosaic-count="7"', $html );
        $this->assertStringContainsString( 'data-slideshow-delay="3000&quot; autofocus=&quot;autofocus"', $html );
        $this->assertStringNotContainsString( 'onclick="x"', $html );
    }

    public function test_render_error_escapes_title(): void {
        $html = $this->renderer->render_error( '<script>alert(1)</script>', 'Plain message.' );

        $this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
        $this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
    }

    public function test_render_error_allows_supported_help_link_markup(): void {
        $html = $this->renderer->render_error(
            'Problem',
            'Read the docs.',
            '<a href="https://example.com/docs" target="_blank" rel="noopener">Help</a>'
        );

        $this->assertStringContainsString( '<a href="https://example.com/docs" target="_blank" rel="noopener">Help</a>', $html );
    }
}
