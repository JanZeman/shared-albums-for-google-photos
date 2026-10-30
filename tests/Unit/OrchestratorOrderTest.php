<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Renderer;
use JZSA_Shared_Albums;

/**
 * Tests for the order="shuffle" photo order.
 */
class OrchestratorOrderTest extends TestCase {

    private JZSA_Shared_Albums $orchestrator;
    private ReflectionClass $reflection;

    protected function setUp(): void {
        $this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
        $this->reflection   = new ReflectionClass( $this->orchestrator );
    }

    private function config( array $atts ): array {
        return $this->reflection
            ->getMethod( 'parse_shortcode_config' )
            ->invoke( $this->orchestrator, $atts, 'https://photos.google.com/share/AF1QipTest' );
    }

    private function invoke( string $method, mixed ...$args ): mixed {
        return $this->reflection->getMethod( $method )->invoke( $this->orchestrator, ...$args );
    }

    private function items( int $count ): array {
        $items = array();
        for ( $i = 0; $i < $count; $i++ ) {
            $items[] = array( 'url' => 'https://lh3.googleusercontent.com/p' . $i, 'id' => 'ID' . $i );
        }
        return $items;
    }

    public function test_order_defaults_to_album(): void {
        $this->assertSame( 'album', $this->config( array() )['order'] );
    }

    public function test_order_accepts_shuffle_case_insensitively(): void {
        $this->assertSame( 'shuffle', $this->config( array( 'order' => 'shuffle' ) )['order'] );
        $this->assertSame( 'shuffle', $this->config( array( 'order' => ' Shuffle ' ) )['order'] );
        $this->assertSame( 'shuffle', $this->config( array( 'order' => 'shuffled' ) )['order'] );
    }

    public function test_unknown_order_falls_back_to_album(): void {
        $this->assertSame( 'album', $this->config( array( 'order' => 'random' ) )['order'] );
    }

    public function test_seeded_shuffle_is_a_permutation(): void {
        $items    = $this->items( 50 );
        $shuffled = JZSA_Shared_Albums::seeded_shuffle( $items, 12345 );

        $this->assertCount( 50, $shuffled );
        $this->assertNotSame( $items, $shuffled );
        $this->assertEqualsCanonicalizing( $items, $shuffled );
    }

    public function test_seeded_shuffle_repeats_for_the_same_seed(): void {
        $items = $this->items( 50 );

        $this->assertSame(
            JZSA_Shared_Albums::seeded_shuffle( $items, 987654 ),
            JZSA_Shared_Albums::seeded_shuffle( $items, 987654 )
        );
        $this->assertNotSame(
            JZSA_Shared_Albums::seeded_shuffle( $items, 987654 ),
            JZSA_Shared_Albums::seeded_shuffle( $items, 987655 )
        );
    }

    public function test_seeded_shuffle_handles_zero_seed_and_tiny_lists(): void {
        $this->assertCount( 10, JZSA_Shared_Albums::seeded_shuffle( $this->items( 10 ), 0 ) );
        $this->assertSame( array(), JZSA_Shared_Albums::seeded_shuffle( array(), 5 ) );
        $this->assertSame( $this->items( 1 ), JZSA_Shared_Albums::seeded_shuffle( $this->items( 1 ), 5 ) );
    }

    public function test_album_order_keeps_items_untouched(): void {
        $items = $this->items( 20 );

        $this->assertSame( $items, $this->invoke( 'apply_photo_order', $items, array( 'order' => 'album' ) ) );
    }

    public function test_shuffle_order_reorders_items_before_the_limit(): void {
        $items    = $this->items( 40 );
        $shuffled = $this->invoke( 'apply_photo_order', $items, array( 'order' => 'shuffle' ) );

        $this->assertEqualsCanonicalizing( $items, $shuffled );
    }

    public function test_renderer_marks_shuffled_slider_and_gallery(): void {
        $renderer = new JZSA_Renderer();

        $this->assertStringContainsString( 'data-order="shuffle"', $renderer->render( array( 'mode' => 'slider', 'order' => 'shuffle' ) ) );
        $this->assertStringContainsString( 'data-order="shuffle"', $renderer->render( array( 'mode' => 'gallery', 'order' => 'shuffle' ) ) );
        $this->assertStringNotContainsString( 'data-order=', $renderer->render( array( 'mode' => 'slider', 'order' => 'album' ) ) );
        $this->assertStringNotContainsString( 'data-order=', $renderer->render( array( 'mode' => 'gallery' ) ) );
    }
}
