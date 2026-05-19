<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Shared_Albums;

/**
 * Tests for the narrow progressive slider loading activation rules.
 */
class OrchestratorProgressiveTest extends TestCase {

    private JZSA_Shared_Albums $orchestrator;
    private ReflectionClass $reflection;

    protected function setUp(): void {
        $this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
        $this->reflection   = new ReflectionClass( $this->orchestrator );
    }

    private function invoke( string $method, mixed ...$args ): mixed {
        return $this->reflection->getMethod( $method )->invoke( $this->orchestrator, ...$args );
    }

    private function photos( int $count, bool $with_video = false ): array {
        $photos = array();
        for ( $i = 0; $i < $count; $i++ ) {
            $photo = array(
                'full'    => 'https://example.test/full-' . $i,
                'preview' => 'https://example.test/preview-' . $i,
            );
            if ( $with_video && 0 === $i ) {
                $photo['type'] = 'video';
                $photo['video'] = 'https://example.test/video-' . $i;
            }
            $photos[] = $photo;
        }
        return $photos;
    }

    private function baseConfig( array $overrides = array() ): array {
        return array_merge(
            array(
                'mode'        => 'slider',
                'mosaic'      => false,
                'photos'      => $this->photos( 61 ),
                'show-videos' => false,
            ),
            $overrides
        );
    }

    public function test_large_plain_slider_uses_progressive_loading(): void {
        $config = $this->invoke( 'apply_progressive_slider_loading_config', $this->baseConfig() );

        $this->assertTrue( $config['progressive-loading'] );
        $this->assertSame( 61, $config['progressive-total-count'] );
        $this->assertSame( 24, $config['progressive-initial-chunk-size'] );
        $this->assertSame( 24, $config['progressive-chunk-size'] );
        $this->assertSame( array(), $config['photos'] );
    }

    public function test_exact_threshold_does_not_use_progressive_loading(): void {
        $config = $this->invoke(
            'apply_progressive_slider_loading_config',
            $this->baseConfig( array( 'photos' => $this->photos( 60 ) ) )
        );

        $this->assertArrayNotHasKey( 'progressive-loading', $config );
        $this->assertCount( 60, $config['photos'] );
    }

    public function test_gallery_mode_never_uses_progressive_loading(): void {
        $config = $this->invoke(
            'apply_progressive_slider_loading_config',
            $this->baseConfig( array( 'mode' => 'gallery' ) )
        );

        $this->assertArrayNotHasKey( 'progressive-loading', $config );
    }

    public function test_mosaic_slider_never_uses_progressive_loading(): void {
        $config = $this->invoke(
            'apply_progressive_slider_loading_config',
            $this->baseConfig( array( 'mosaic' => true ) )
        );

        $this->assertArrayNotHasKey( 'progressive-loading', $config );
    }

    public function test_video_album_never_uses_progressive_loading(): void {
        $config = $this->invoke(
            'apply_progressive_slider_loading_config',
            $this->baseConfig( array( 'photos' => $this->photos( 61, true ) ) )
        );

        $this->assertArrayNotHasKey( 'progressive-loading', $config );
    }

    public function test_prepare_photo_chunk_returns_visible_indexes_after_video_filtering(): void {
        $base_items = array(
            array( 'url' => 'https://lh3.googleusercontent.com/one', 'id' => 'ONE' ),
            array( 'url' => 'https://lh3.googleusercontent.com/video', 'id' => 'VIDEO', 'type' => 'video' ),
            array( 'url' => 'https://lh3.googleusercontent.com/two', 'id' => 'TWO' ),
            array( 'url' => 'https://lh3.googleusercontent.com/three', 'id' => 'THREE' ),
        );

        $chunk = $this->invoke(
            'prepare_photo_chunk',
            $base_items,
            'https://photos.google.com/share/AF1QipTest',
            1,
            2,
            1920,
            1440,
            800,
            600,
            10,
            false
        );

        $this->assertCount( 2, $chunk['photos'] );
        $this->assertSame( 3, $chunk['total_count'] );
        $this->assertSame( 'TWO', $chunk['photos'][0]['id'] );
        $this->assertSame( 1, $chunk['photos'][0]['globalIndex'] );
        $this->assertSame( 'THREE', $chunk['photos'][1]['id'] );
        $this->assertSame( 2, $chunk['photos'][1]['globalIndex'] );
    }

    public function test_prepare_photo_chunk_returns_empty_when_offset_exceeds_total(): void {
        $base_items = array(
            array( 'url' => 'https://lh3.googleusercontent.com/one', 'id' => 'ONE' ),
            array( 'url' => 'https://lh3.googleusercontent.com/two', 'id' => 'TWO' ),
            array( 'url' => 'https://lh3.googleusercontent.com/three', 'id' => 'THREE' ),
        );

        $chunk = $this->invoke(
            'prepare_photo_chunk',
            $base_items,
            'https://photos.google.com/share/AF1QipTest',
            10,
            5,
            1920,
            1440,
            800,
            600,
            10,
            false
        );

        $this->assertSame( array(), $chunk['photos'] );
        $this->assertSame( 3, $chunk['total_count'] );
    }

    public function test_prepare_photo_chunk_reports_has_more_when_more_visible_items_remain(): void {
        $base_items = array(
            'https://lh3.googleusercontent.com/one',
            'https://lh3.googleusercontent.com/two',
            'https://lh3.googleusercontent.com/three',
        );

        $chunk = $this->invoke(
            'prepare_photo_chunk',
            $base_items,
            'https://photos.google.com/share/AF1QipTest',
            0,
            1,
            1920,
            1440,
            800,
            600,
            10,
            true
        );

        $this->assertCount( 1, $chunk['photos'] );
        $this->assertSame( 3, $chunk['total_count'] );
        $this->assertSame( 0, $chunk['photos'][0]['globalIndex'] );
    }
}
