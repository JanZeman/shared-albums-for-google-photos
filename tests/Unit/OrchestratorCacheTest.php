<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Shared_Albums;

/**
 * Tests for cache key generation, expiry key generation, and
 * cache-refresh interval parsing in JZSA_Shared_Albums (the orchestrator).
 */
class OrchestratorCacheTest extends TestCase {

    private JZSA_Shared_Albums $orchestrator;
    private ReflectionClass $reflection;

    protected function setUp(): void {
        $this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
        $this->reflection   = new ReflectionClass( $this->orchestrator );
    }

    private function invoke( string $method, mixed ...$args ): mixed {
        return $this->reflection->getMethod( $method )->invoke( $this->orchestrator, ...$args );
    }

    // -------------------------------------------------------------------------
    // get_cache_key
    // -------------------------------------------------------------------------

    public function test_cache_key_has_jzsa_album_prefix(): void {
        $key = $this->invoke( 'get_cache_key', 'https://photos.google.com/share/ABC' );
        $this->assertStringStartsWith( 'jzsa_album_', $key );
    }

    public function test_cache_key_uses_md5_of_url(): void {
        $url = 'https://photos.google.com/share/ABC';
        $key = $this->invoke( 'get_cache_key', $url );
        $this->assertSame( 'jzsa_album_' . md5( $url ), $key );
    }

    public function test_cache_key_different_urls_produce_different_keys(): void {
        $key1 = $this->invoke( 'get_cache_key', 'https://photos.google.com/share/AAA' );
        $key2 = $this->invoke( 'get_cache_key', 'https://photos.google.com/share/BBB' );
        $this->assertNotSame( $key1, $key2 );
    }

    public function test_cache_key_same_url_produces_same_key(): void {
        $url  = 'https://photos.google.com/share/SAME';
        $key1 = $this->invoke( 'get_cache_key', $url );
        $key2 = $this->invoke( 'get_cache_key', $url );
        $this->assertSame( $key1, $key2 );
    }

    public function test_cache_key_includes_query_string_in_hash(): void {
        $url_no_key  = 'https://photos.google.com/share/ABC';
        $url_with_key = 'https://photos.google.com/share/ABC?key=XYZ';
        $key1 = $this->invoke( 'get_cache_key', $url_no_key );
        $key2 = $this->invoke( 'get_cache_key', $url_with_key );
        $this->assertNotSame( $key1, $key2 );
    }

    // -------------------------------------------------------------------------
    // get_expiration_key
    // -------------------------------------------------------------------------

    public function test_expiration_key_has_jzsa_expiry_prefix(): void {
        $key = $this->invoke( 'get_expiration_key', 'https://photos.google.com/share/ABC' );
        $this->assertStringStartsWith( 'jzsa_expiry_', $key );
    }

    public function test_expiration_key_uses_md5_of_url(): void {
        $url = 'https://photos.google.com/share/ABC';
        $key = $this->invoke( 'get_expiration_key', $url );
        $this->assertSame( 'jzsa_expiry_' . md5( $url ), $key );
    }

    public function test_expiration_key_differs_from_cache_key_for_same_url(): void {
        $url     = 'https://photos.google.com/share/ABC';
        $cache   = $this->invoke( 'get_cache_key', $url );
        $expiry  = $this->invoke( 'get_expiration_key', $url );
        $this->assertNotSame( $cache, $expiry );
    }

    // -------------------------------------------------------------------------
    // get_backup_cache_key
    // -------------------------------------------------------------------------

    public function test_backup_cache_key_has_jzsa_backup_album_prefix(): void {
        $key = $this->invoke( 'get_backup_cache_key', 'https://photos.google.com/share/ABC' );
        $this->assertStringStartsWith( 'jzsa_backup_album_', $key );
    }

    public function test_backup_cache_key_uses_md5_of_url(): void {
        $url = 'https://photos.google.com/share/ABC';
        $key = $this->invoke( 'get_backup_cache_key', $url );
        $this->assertSame( 'jzsa_backup_album_' . md5( $url ), $key );
    }

    public function test_backup_cache_key_differs_from_primary_cache_key(): void {
        $url     = 'https://photos.google.com/share/ABC';
        $primary = $this->invoke( 'get_cache_key', $url );
        $backup  = $this->invoke( 'get_backup_cache_key', $url );
        $this->assertNotSame( $primary, $backup );
    }

    // -------------------------------------------------------------------------
    // get_photo_meta_cache_key
    // -------------------------------------------------------------------------

    public function test_photo_meta_key_has_jzsa_photo_meta_prefix(): void {
        $key = $this->invoke( 'get_photo_meta_cache_key', 'https://photos.google.com/photo/ABC123' );
        $this->assertStringStartsWith( 'jzsa_photo_meta_', $key );
    }

    public function test_photo_meta_key_extracts_id_segment_from_url(): void {
        $url  = 'https://photos.google.com/photo/ABC123';
        $key  = $this->invoke( 'get_photo_meta_cache_key', $url );
        $this->assertSame( 'jzsa_photo_meta_' . md5( 'ABC123' ), $key );
    }

    public function test_photo_meta_key_ignores_query_string_after_photo_id(): void {
        $url_clean = 'https://photos.google.com/photo/ABC123';
        $url_query = 'https://photos.google.com/photo/ABC123?foo=bar';
        $key1 = $this->invoke( 'get_photo_meta_cache_key', $url_clean );
        $key2 = $this->invoke( 'get_photo_meta_cache_key', $url_query );
        $this->assertSame( $key1, $key2 );
    }

    public function test_photo_meta_key_falls_back_to_full_url_hash_when_no_id_segment(): void {
        $url = 'https://example.com/not-a-photo-url';
        $key = $this->invoke( 'get_photo_meta_cache_key', $url );
        $this->assertSame( 'jzsa_photo_meta_' . md5( $url ), $key );
    }

    public function test_photo_meta_key_different_ids_produce_different_keys(): void {
        $key1 = $this->invoke( 'get_photo_meta_cache_key', 'https://photos.google.com/photo/AAA' );
        $key2 = $this->invoke( 'get_photo_meta_cache_key', 'https://photos.google.com/photo/BBB' );
        $this->assertNotSame( $key1, $key2 );
    }

    // -------------------------------------------------------------------------
    // parse_cache_refresh
    // -------------------------------------------------------------------------

    public function test_cache_refresh_defaults_to_168_hours(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [] );
        $this->assertSame( 168, $hours );
    }

    public function test_cache_refresh_reads_album_cache_refresh_attribute(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [ 'album-cache-refresh' => '24' ] );
        $this->assertSame( 24, $hours );
    }

    public function test_cache_refresh_falls_back_to_legacy_cache_refresh_attribute(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [ 'cache-refresh' => '48' ] );
        $this->assertSame( 48, $hours );
    }

    public function test_cache_refresh_prefers_album_cache_refresh_over_legacy(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [
            'album-cache-refresh' => '12',
            'cache-refresh'       => '48',
        ] );
        $this->assertSame( 12, $hours );
    }

    public function test_cache_refresh_value_zero_falls_back_to_default(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [ 'album-cache-refresh' => '0' ] );
        $this->assertSame( 168, $hours );
    }

    public function test_cache_refresh_negative_value_falls_back_to_default(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [ 'album-cache-refresh' => '-5' ] );
        $this->assertSame( 168, $hours );
    }

    public function test_cache_refresh_non_numeric_string_falls_back_to_default(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [ 'album-cache-refresh' => 'never' ] );
        $this->assertSame( 168, $hours );
    }

    public function test_cache_refresh_value_1_is_minimum_valid(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [ 'album-cache-refresh' => '1' ] );
        $this->assertSame( 1, $hours );
    }

    public function test_cache_refresh_large_value_accepted(): void {
        $hours = $this->invoke( 'parse_cache_refresh', [ 'album-cache-refresh' => '720' ] );
        $this->assertSame( 720, $hours );
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function test_default_cache_refresh_is_168_hours(): void {
        $this->assertSame( 168, JZSA_Shared_Albums::DEFAULT_CACHE_REFRESH );
    }

    public function test_backup_cache_ttl_is_7_days(): void {
        $this->assertSame( 7 * DAY_IN_SECONDS, JZSA_Shared_Albums::BACKUP_CACHE_TTL );
    }

    public function test_photo_meta_cache_ttl_is_30_days(): void {
        $this->assertSame( 30 * DAY_IN_SECONDS, JZSA_Shared_Albums::PHOTO_META_CACHE_TTL );
    }
}
