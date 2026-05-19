<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Shared_Albums;

class OrchestratorShortcodeFakeProvider {
    public int $calls = 0;
    public array $result;

    public function __construct( array $result ) {
        $this->result = $result;
    }

    public function fetch_album( string $url ): array {
        $this->calls++;
        return $this->result;
    }

    public function format_camera_display_name( string $make, string $model ): string {
        return trim( $make . ' ' . $model );
    }
}

class OrchestratorShortcodeFakeRenderer {
    public ?array $last_config = null;
    public array $errors = array();

    public function render( array $config ): string {
        $this->last_config = $config;
        return 'rendered:' . ( $config['album-title'] ?? '' ) . ':' . count( $config['photos'] ?? array() );
    }

    public function render_error( string $title, string $message, string $help = '' ): string {
        $this->errors[] = compact( 'title', 'message', 'help' );
        return 'error:' . $title;
    }
}

/**
 * Tests the public shortcode flow with fake provider/renderer dependencies.
 */
class OrchestratorShortcodeTest extends TestCase {

    private const ALBUM_URL = 'https://photos.google.com/share/AF1QipShortcode';

    private JZSA_Shared_Albums $orchestrator;
    private ReflectionClass $reflection;
    private OrchestratorShortcodeFakeProvider $provider;
    private OrchestratorShortcodeFakeRenderer $renderer;

    protected function setUp(): void {
        $GLOBALS['jzsa_test_transients'] = array();
        $GLOBALS['jzsa_test_options']    = array();
        $GLOBALS['jzsa_test_doing_ajax'] = false;
        unset( $GLOBALS['jzsa_test_nocache_headers_sent'] );

        $this->provider = new OrchestratorShortcodeFakeProvider( $this->successResult() );
        $this->renderer = new OrchestratorShortcodeFakeRenderer();
        $this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
        $this->reflection   = new ReflectionClass( $this->orchestrator );

        $this->setPrivateProperty( 'provider', $this->provider );
        $this->setPrivateProperty( 'renderer', $this->renderer );
    }

    private function setPrivateProperty( string $name, mixed $value ): void {
        $property = $this->reflection->getProperty( $name );
        $property->setValue( $this->orchestrator, $value );
    }

    private function invoke( string $method, mixed ...$args ): mixed {
        return $this->reflection->getMethod( $method )->invoke( $this->orchestrator, ...$args );
    }

    private function cacheKey(): string {
        return $this->invoke( 'get_cache_key', self::ALBUM_URL );
    }

    private function expiryKey(): string {
        return $this->invoke( 'get_expiration_key', self::ALBUM_URL );
    }

    private function backupKey(): string {
        return $this->invoke( 'get_backup_cache_key', self::ALBUM_URL );
    }

    private function successResult(): array {
        return array(
            'success'       => true,
            'is_deprecated' => false,
            'data'          => array(
                'title'  => 'Fixture Album',
                'photos' => array(
                    array(
                        'url'       => 'https://lh3.googleusercontent.com/image-one',
                        'id'        => 'PHOTO1',
                        'timestamp' => 1700000000,
                    ),
                    array(
                        'url'  => 'https://lh3.googleusercontent.com/video-one',
                        'id'   => 'VIDEO1',
                        'type' => 'video',
                    ),
                ),
            ),
        );
    }

    public function test_empty_shortcode_attributes_return_null_without_fetching(): void {
        $this->assertNull( $this->orchestrator->handle_shortcode( array() ) );
        $this->assertSame( 0, $this->provider->calls );
        $this->assertNull( $this->renderer->last_config );
    }

    public function test_missing_album_link_returns_null_without_fetching(): void {
        $this->assertNull( $this->orchestrator->handle_shortcode( array( 'mode' => 'slider' ) ) );
        $this->assertSame( 0, $this->provider->calls );
        $this->assertNull( $this->renderer->last_config );
    }

    public function test_fresh_fetch_caches_album_data_and_renders_prepared_photos(): void {
        $html = $this->orchestrator->handle_shortcode(
            array(
                'link'        => self::ALBUM_URL,
                'mode'        => 'slider',
                'show-videos' => 'false',
                'limit'       => '1',
            )
        );

        $this->assertSame( 'rendered:Fixture Album:1', $html );
        $this->assertSame( 1, $this->provider->calls );
        $this->assertSame( 604800, $GLOBALS['jzsa_test_options'][ $this->expiryKey() ] );

        $cached = $GLOBALS['jzsa_test_transients'][ $this->cacheKey() ];
        $this->assertSame( 'Fixture Album', $cached['title'] );
        $this->assertSame( $this->provider->result['data']['photos'], $cached['photos'] );
        $this->assertSame( $cached, $GLOBALS['jzsa_test_transients'][ $this->backupKey() ] );

        $config = $this->renderer->last_config;
        $this->assertSame( self::ALBUM_URL, $config['album-url'] );
        $this->assertSame( 'Fixture Album', $config['album-title'] );
        $this->assertFalse( $config['show-deprecation-warning'] );
        $this->assertCount( 1, $config['photos'] );
        $this->assertSame( 'https://lh3.googleusercontent.com/image-one=w1920-h1440', $config['photos'][0]['full'] );
        $this->assertSame( 'https://lh3.googleusercontent.com/image-one=w800-h600', $config['photos'][0]['preview'] );
        $this->assertSame( 'PHOTO1', $config['photos'][0]['id'] );
    }

    public function test_positional_shortcode_link_is_accepted(): void {
        $html = $this->orchestrator->handle_shortcode( array( self::ALBUM_URL ) );

        $this->assertSame( 'rendered:Fixture Album:1', $html );
        $this->assertSame( self::ALBUM_URL, $this->renderer->last_config['album-url'] );
    }

    public function test_cache_hit_uses_cached_data_without_provider_call(): void {
        $GLOBALS['jzsa_test_transients'][ $this->cacheKey() ] = array(
            'title'         => 'Cached Album',
            'photos'        => array(
                'https://lh3.googleusercontent.com/cached-one',
                array(
                    'url'  => 'https://lh3.googleusercontent.com/cached-video',
                    'type' => 'video',
                    'id'   => 'VID',
                ),
            ),
            'is_deprecated' => true,
        );
        $GLOBALS['jzsa_test_options'][ $this->expiryKey() ] = 604800;

        $html = $this->orchestrator->handle_shortcode(
            array(
                'link'        => self::ALBUM_URL,
                'show-videos' => 'true',
            )
        );

        $this->assertSame( 'rendered:Cached Album:2', $html );
        $this->assertSame( 0, $this->provider->calls );
        $this->assertSame( 'Cached Album', $this->renderer->last_config['album-title'] );
        $this->assertTrue( $this->renderer->last_config['show-deprecation-warning'] );
        $this->assertSame( 'video', $this->renderer->last_config['photos'][1]['type'] );
        $this->assertStringEndsWith( '=dv', $this->renderer->last_config['photos'][1]['video'] );
    }

    public function test_changed_cache_duration_forces_refetch(): void {
        $GLOBALS['jzsa_test_transients'][ $this->cacheKey() ] = array(
            'title'         => 'Old Cache',
            'photos'        => array( 'https://lh3.googleusercontent.com/old' ),
            'is_deprecated' => false,
        );
        $GLOBALS['jzsa_test_options'][ $this->expiryKey() ] = 3600;

        $html = $this->orchestrator->handle_shortcode( array( 'link' => self::ALBUM_URL ) );

        $this->assertSame( 'rendered:Fixture Album:1', $html );
        $this->assertSame( 1, $this->provider->calls );
        $this->assertSame( 'Fixture Album', $GLOBALS['jzsa_test_transients'][ $this->cacheKey() ]['title'] );
    }

    public function test_stale_backup_is_used_when_fresh_fetch_fails(): void {
        $this->provider->result = array(
            'success' => false,
            'error'   => 'Temporary outage',
        );
        $GLOBALS['jzsa_test_transients'][ $this->backupKey() ] = array(
            'title'         => 'Backup Album',
            'photos'        => array( 'https://lh3.googleusercontent.com/backup-one' ),
            'is_deprecated' => false,
        );

        $html = $this->orchestrator->handle_shortcode( array( 'link' => self::ALBUM_URL ) );

        $this->assertSame( 'rendered:Backup Album:1', $html );
        $this->assertSame( 1, $this->provider->calls );
        $this->assertSame( 'Backup Album', $this->renderer->last_config['album-title'] );
        $this->assertSame( 'Backup Album', $GLOBALS['jzsa_test_transients'][ $this->cacheKey() ]['title'] );
        $this->assertSame( 604800, $GLOBALS['jzsa_test_options'][ $this->expiryKey() ] );
    }

    public function test_fetch_failure_without_backup_renders_error_and_no_cache_headers(): void {
        $this->provider->result = array(
            'success' => false,
            'error'   => 'Invalid URL',
        );

        $html = $this->orchestrator->handle_shortcode( array( 'link' => self::ALBUM_URL ) );

        $this->assertSame( 'error:Invalid Google Photos URL', $html );
        $this->assertSame( 1, $this->provider->calls );
        $this->assertSame( 'Invalid Google Photos URL', $this->renderer->errors[0]['title'] );
        $this->assertTrue( $GLOBALS['jzsa_test_nocache_headers_sent'] );
        $this->assertArrayNotHasKey( $this->cacheKey(), $GLOBALS['jzsa_test_transients'] );
    }

    public function test_fetch_failure_with_no_photos_error_renders_no_photos_title(): void {
        $this->provider->result = array(
            'success' => false,
            'error'   => 'No photos found in album',
        );

        $html = $this->orchestrator->handle_shortcode( array( 'link' => self::ALBUM_URL ) );

        $this->assertSame( 'No Photos Found', $this->renderer->errors[0]['title'] );
    }

    public function test_fetch_failure_with_generic_error_renders_unable_to_load_title(): void {
        $this->provider->result = array(
            'success' => false,
            'error'   => 'Failed to fetch album: timeout',
        );

        $html = $this->orchestrator->handle_shortcode( array( 'link' => self::ALBUM_URL ) );

        $this->assertSame( 'Unable to Load Album', $this->renderer->errors[0]['title'] );
    }

    public function test_fresh_fetch_with_deprecated_url_sets_deprecation_warning(): void {
        $this->provider->result = array(
            'success'       => true,
            'is_deprecated' => true,
            'data'          => array(
                'title'  => 'Old Album',
                'photos' => array(
                    array(
                        'url' => 'https://lh3.googleusercontent.com/img-one',
                        'id'  => 'PHOTO1',
                    ),
                ),
            ),
        );

        $this->orchestrator->handle_shortcode( array( 'link' => self::ALBUM_URL ) );

        $this->assertTrue( $this->renderer->last_config['show-deprecation-warning'] );
        $cached = $GLOBALS['jzsa_test_transients'][ $this->cacheKey() ];
        $this->assertTrue( (bool) $cached['is_deprecated'] );
    }
}
