<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Shared_Albums;

class OrchestratorMediaAjaxFakeProvider {
    public int $fetch_calls = 0;
    public array $album_result;

    public function __construct() {
        $this->album_result = array(
            'success'       => true,
            'is_deprecated' => false,
            'data'          => array(
                'title'  => 'Fresh Album',
                'photos' => array(
                    array( 'url' => 'https://lh3.googleusercontent.com/one', 'id' => 'ONE' ),
                    array( 'url' => 'https://lh3.googleusercontent.com/two', 'id' => 'TWO' ),
                ),
            ),
        );
    }

    public function fetch_album( string $url ): array {
        $this->fetch_calls++;
        return $this->album_result;
    }

    public function extract_individual_photo_meta( string $html ): array {
        return array(
            'camera_make' => 'Canon',
            'camera_model' => 'R5',
            'aperture' => 'f/2.8',
        );
    }

    public function extract_individual_photo_media_urls( string $html ): array {
        return array( 'image' => 'https://lh3.googleusercontent.com/from-html=s0' );
    }

    public function format_camera_display_name( string $make, string $model ): string {
        return trim( $make . ' ' . $model );
    }
}

/**
 * Tests media-related helper and AJAX guardrail behavior.
 */
class OrchestratorMediaAjaxTest extends TestCase {

    private JZSA_Shared_Albums $orchestrator;
    private ReflectionClass $reflection;
    private OrchestratorMediaAjaxFakeProvider $provider;

    protected function setUp(): void {
        $this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
        $this->reflection   = new ReflectionClass( $this->orchestrator );
        $this->provider     = new OrchestratorMediaAjaxFakeProvider();
        $this->reflection->getProperty( 'provider' )->setValue( $this->orchestrator, $this->provider );

        $_POST = array();
        $GLOBALS['jzsa_test_nonce_valid']    = true;
	        $GLOBALS['jzsa_test_transients']     = array();
	        $GLOBALS['jzsa_test_options']        = array();
	        $GLOBALS['jzsa_test_filters']        = array();
	        $GLOBALS['jzsa_test_http_responses'] = array();
	        $GLOBALS['jzsa_test_http_requests']  = array();
    }

    protected function tearDown(): void {
        $_POST = array();
        $GLOBALS['jzsa_test_nonce_valid'] = true;
    }

    private function invoke( string $method, mixed ...$args ): mixed {
        return $this->reflection->getMethod( $method )->invoke( $this->orchestrator, ...$args );
    }

    private function callAjax( string $method ): \JZSA_Test_JSON_Response {
        try {
            $this->orchestrator->$method();
        } catch ( \JZSA_Test_JSON_Response $response ) {
            return $response;
        }

        $this->fail( 'Expected ' . $method . ' to send a JSON response.' );
    }

    private function response( int $code, string $body = '', array $headers = array() ): array {
        return array(
            'response' => array( 'code' => $code ),
            'body'     => $body,
            'headers'  => $headers,
        );
    }

    public function test_build_photo_page_url_requires_share_key_and_encodes_media_id(): void {
        $url = $this->invoke(
            'build_photo_page_url',
            'https://photos.google.com/share/AF1QipAlbum?key=abc123',
            'photo id/with spaces'
        );

        $this->assertSame( 'https://photos.google.com/share/AF1QipAlbum/photo/photo%20id%2Fwith%20spaces?key=abc123', $url );
        $this->assertSame( '', $this->invoke( 'build_photo_page_url', 'https://photos.google.com/share/AF1QipAlbum', 'PHOTO' ) );
    }

    public function test_parse_content_disposition_filename_supports_utf8_and_strips_controls(): void {
        $filename = $this->invoke( 'parse_content_disposition_filename', "attachment; filename*=UTF-8''Summer%20%C3%B8%20Album.jpg" );
        $plain    = $this->invoke( 'parse_content_disposition_filename', "attachment; filename=\"bad\x01name.jpg\"" );

        $this->assertSame( 'Summer ø Album.jpg', $filename );
        $this->assertSame( 'badname.jpg', $plain );
    }

    public function test_normalize_media_url_for_filename_decodes_google_escapes(): void {
        $photo = $this->invoke( 'normalize_media_url_for_filename', 'https:\/\/lh3.googleusercontent.com\/abc\\u003dw800-h600\\u0026x=1', 'photo' );
        $video = $this->invoke( 'normalize_media_url_for_filename', 'https:\/\/lh3.googleusercontent.com\/video\\u003ddv', 'video' );

        $this->assertSame( 'https://lh3.googleusercontent.com/abc=d', $photo );
        $this->assertSame( 'https://lh3.googleusercontent.com/video=dv', $video );
    }

    public function test_filter_public_photo_meta_returns_empty_array_for_non_array_input(): void {
        $this->assertSame( array(), $this->invoke( 'filter_public_photo_meta', false ) );
        $this->assertSame( array(), $this->invoke( 'filter_public_photo_meta', null ) );
        $this->assertSame( array(), $this->invoke( 'filter_public_photo_meta', 'not-an-array' ) );
    }

    public function test_filter_public_photo_meta_removes_internal_flags_and_rebuilds_camera_name(): void {
        $meta = $this->invoke(
            'filter_public_photo_meta',
            array(
                '_fetched_exif' => true,
                '_fetched_filename' => true,
                'camera_make' => 'Nikon',
                'camera_model' => 'Z8',
                'filename' => 'photo.jpg',
            )
        );

        $this->assertArrayNotHasKey( '_fetched_exif', $meta );
        $this->assertArrayNotHasKey( '_fetched_filename', $meta );
        $this->assertSame( 'Nikon Z8', $meta['camera'] );
        $this->assertSame( 'photo.jpg', $meta['filename'] );
    }

    public function test_fetch_photo_meta_rejects_invalid_nonce_before_http_request(): void {
        $GLOBALS['jzsa_test_nonce_valid'] = false;
        $_POST = array( 'nonce' => 'bad' );

        $response = $this->callAjax( 'handle_fetch_photo_meta' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Invalid nonce', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_fetch_photo_meta_cache_hit_returns_public_meta_without_http_request(): void {
        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
	        $cache_key = $this->invoke( 'get_photo_meta_cache_key', $photo_url );
	        $GLOBALS['jzsa_test_transients'][ $cache_key ] = array(
	            '_fetched_exif' => true,
	            '_fetched_filename' => true,
	            'camera_make' => 'Sony',
	            'camera_model' => 'A7',
	            'aperture' => 'f/2',
	            'shutter' => '1/100',
	            'focal' => '35 mm',
	            'iso' => '100',
	            'filename' => 'sony.jpg',
	        );
        $_POST = array(
            'nonce' => 'ok',
            'photo_url' => $photo_url,
            'need_exif' => 'true',
            'need_filename' => 'true',
        );

        $response = $this->callAjax( 'handle_fetch_photo_meta' );

        $this->assertTrue( $response->success );
        $this->assertSame( 'Sony A7', $response->data['camera'] );
        $this->assertSame( 'sony.jpg', $response->data['filename'] );
	        $this->assertArrayNotHasKey( '_fetched_exif', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_fetch_photo_meta_rejects_missing_and_invalid_photo_url_before_http_request(): void {
	        $_POST = array( 'nonce' => 'ok' );
	        $missing = $this->callAjax( 'handle_fetch_photo_meta' );

	        $_POST = array(
	            'nonce' => 'ok',
	            'photo_url' => 'https://example.com/share/not-google/photo/PHOTO1',
	        );
	        $invalid = $this->callAjax( 'handle_fetch_photo_meta' );

	        $this->assertFalse( $missing->success );
	        $this->assertSame( 'Missing photo URL', $missing->data );
	        $this->assertFalse( $invalid->success );
	        $this->assertSame( 'Invalid photo URL', $invalid->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_fetch_photo_meta_fetches_exif_description_and_caches_result(): void {
	        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
	        $GLOBALS['jzsa_test_http_responses'][ $photo_url ] = $this->response( 200, '<html>photo page</html>' );
	        $_POST = array(
	            'nonce' => 'ok',
	            'photo_url' => $photo_url,
	            'need_exif' => 'true',
	            'need_description' => 'true',
	            'need_filename' => 'false',
	        );

	        $response = $this->callAjax( 'handle_fetch_photo_meta' );
	        $cached = $GLOBALS['jzsa_test_transients'][ $this->invoke( 'get_photo_meta_cache_key', $photo_url ) ];

	        $this->assertTrue( $response->success );
	        $this->assertSame( 'Canon R5', $response->data['camera'] );
	        $this->assertSame( 'f/2.8', $response->data['aperture'] );
	        $this->assertArrayNotHasKey( '_fetched_exif', $response->data );
	        $this->assertTrue( $cached['_fetched_exif'] );
	        $this->assertTrue( $cached['_fetched_description'] );
	        $this->assertCount( 1, $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_fetch_photo_meta_empty_response_returns_error_without_caching(): void {
	        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
	        $GLOBALS['jzsa_test_http_responses'][ $photo_url ] = $this->response( 200, '' );
	        $_POST = array(
	            'nonce' => 'ok',
	            'photo_url' => $photo_url,
	            'need_exif' => 'true',
	            'need_filename' => 'false',
	        );

	        $response = $this->callAjax( 'handle_fetch_photo_meta' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Empty response', $response->data );
	        $this->assertArrayNotHasKey( $this->invoke( 'get_photo_meta_cache_key', $photo_url ), $GLOBALS['jzsa_test_transients'] );
	    }

    public function test_fetch_photo_meta_wp_error_returns_fetch_failed_without_caching(): void {
        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
        $GLOBALS['jzsa_test_http_responses'][ $photo_url ] = new \WP_Error( 'timeout', 'Connection timed out' );
        $_POST = array(
            'nonce' => 'ok',
            'photo_url' => $photo_url,
            'need_exif' => 'true',
            'need_filename' => 'false',
        );

        $response = $this->callAjax( 'handle_fetch_photo_meta' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Fetch failed', $response->data );
        $this->assertArrayNotHasKey( $this->invoke( 'get_photo_meta_cache_key', $photo_url ), $GLOBALS['jzsa_test_transients'] );
    }

	    public function test_fetch_photo_meta_refetches_partial_exif_cache_entries(): void {
	        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
	        $cache_key = $this->invoke( 'get_photo_meta_cache_key', $photo_url );
	        $GLOBALS['jzsa_test_transients'][ $cache_key ] = array(
	            '_fetched_exif' => true,
	            'camera_make' => 'Old',
	            'camera_model' => 'Camera',
	        );
	        $GLOBALS['jzsa_test_http_responses'][ $photo_url ] = $this->response( 200, '<html>fresh photo page</html>' );
	        $_POST = array(
	            'nonce' => 'ok',
	            'photo_url' => $photo_url,
	            'need_exif' => 'true',
	            'need_filename' => 'false',
	        );

	        $response = $this->callAjax( 'handle_fetch_photo_meta' );

	        $this->assertTrue( $response->success );
	        $this->assertSame( 'Canon R5', $response->data['camera'] );
	        $this->assertSame( 'Canon', $GLOBALS['jzsa_test_transients'][ $cache_key ]['camera_make'] );
	        $this->assertCount( 1, $GLOBALS['jzsa_test_http_requests'] );
	    }

    public function test_fetch_photo_meta_refetches_camera_string_only_entry_without_make_model(): void {
        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
        $cache_key = $this->invoke( 'get_photo_meta_cache_key', $photo_url );
        $GLOBALS['jzsa_test_transients'][ $cache_key ] = array(
            '_fetched_exif' => true,
            'camera'        => 'Old Canon',
            // Intentionally missing camera_make and camera_model keys
        );
        $GLOBALS['jzsa_test_http_responses'][ $photo_url ] = $this->response( 200, '<html>fresh photo page</html>' );
        $_POST = array(
            'nonce'         => 'ok',
            'photo_url'     => $photo_url,
            'need_exif'     => 'true',
            'need_filename' => 'false',
        );

        $response = $this->callAjax( 'handle_fetch_photo_meta' );

        $this->assertTrue( $response->success );
        $this->assertSame( 'Canon R5', $response->data['camera'] );
        $this->assertCount( 1, $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_fetch_photo_meta_uses_legacy_exif_data_without_state_flags(): void {
        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
        $cache_key = $this->invoke( 'get_photo_meta_cache_key', $photo_url );
        $GLOBALS['jzsa_test_transients'][ $cache_key ] = array(
            'camera'   => 'Legacy Camera',
            'aperture' => 'f/4',
            'shutter'  => '1/100',
            'focal'    => '35mm',
            'iso'      => 'ISO800',
            // Intentionally no _fetched_exif key
        );
        $_POST = array(
            'nonce'         => 'ok',
            'photo_url'     => $photo_url,
            'need_exif'     => 'true',
            'need_filename' => 'false',
        );

        $response = $this->callAjax( 'handle_fetch_photo_meta' );

        $this->assertTrue( $response->success );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
        $this->assertSame( 'f/4', $response->data['aperture'] );
    }

    public function test_fetch_photo_meta_uses_legacy_description_without_state_flag(): void {
        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
        $cache_key = $this->invoke( 'get_photo_meta_cache_key', $photo_url );
        $GLOBALS['jzsa_test_transients'][ $cache_key ] = array(
            'description' => 'A caption for this photo',
            // Intentionally no _fetched_description key
        );
        $_POST = array(
            'nonce'            => 'ok',
            'photo_url'        => $photo_url,
            'need_exif'        => 'false',
            'need_description' => 'true',
            'need_filename'    => 'false',
        );

        $response = $this->callAjax( 'handle_fetch_photo_meta' );

        $this->assertTrue( $response->success );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
        $this->assertSame( 'A caption for this photo', $response->data['description'] );
    }

    public function test_fetch_photo_meta_uses_legacy_filename_without_state_flag(): void {
        $photo_url = 'https://photos.google.com/share/AF1QipAlbum/photo/PHOTO1?key=abc';
        $cache_key = $this->invoke( 'get_photo_meta_cache_key', $photo_url );
        $GLOBALS['jzsa_test_transients'][ $cache_key ] = array(
            'filename' => 'portrait.jpg',
            // Intentionally no _fetched_filename key
        );
        $_POST = array(
            'nonce'         => 'ok',
            'photo_url'     => $photo_url,
            'need_exif'     => 'false',
            'need_filename' => 'true',
        );

        $response = $this->callAjax( 'handle_fetch_photo_meta' );

        $this->assertTrue( $response->success );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
        $this->assertSame( 'portrait.jpg', $response->data['filename'] );
    }

    public function test_fetch_album_chunk_rejects_invalid_nonce(): void {
        $GLOBALS['jzsa_test_nonce_valid'] = false;
        $_POST = array( 'nonce' => 'bad', 'album_url' => 'https://photos.google.com/share/x' );

        $response = $this->callAjax( 'handle_fetch_album_chunk' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Invalid nonce', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_fetch_album_chunk_rejects_missing_album_url(): void {
        $_POST = array( 'nonce' => 'ok' );

        $response = $this->callAjax( 'handle_fetch_album_chunk' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Missing album URL', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_fetch_album_chunk_uses_cached_album_and_returns_offset_payload(): void {
        $album_url = 'https://photos.google.com/share/AF1QipAlbum?key=abc';
        $cache_key = $this->invoke( 'get_cache_key', $album_url );
        $GLOBALS['jzsa_test_transients'][ $cache_key ] = array(
            'title' => 'Cached',
            'is_deprecated' => false,
            'photos' => array(
                array( 'url' => 'https://lh3.googleusercontent.com/one', 'id' => 'ONE' ),
                array( 'url' => 'https://lh3.googleusercontent.com/two', 'id' => 'TWO' ),
                array( 'url' => 'https://lh3.googleusercontent.com/three', 'id' => 'THREE' ),
            ),
        );
        $_POST = array(
            'nonce' => 'ok',
            'album_url' => $album_url,
            'offset' => '1',
            'count' => '1',
            'limit' => '3',
            'show_videos' => 'true',
            'source_width' => '800',
            'source_height' => '600',
            'fullscreen_source_width' => '1920',
            'fullscreen_source_height' => '1440',
        );

        $response = $this->callAjax( 'handle_fetch_album_chunk' );

        $this->assertTrue( $response->success );
        $this->assertSame( 1, $response->data['offset'] );
        $this->assertSame( 3, $response->data['total_count'] );
        $this->assertCount( 1, $response->data['photos'] );
	        $this->assertSame( 'TWO', $response->data['photos'][0]['id'] );
	        $this->assertSame( 1, $response->data['photos'][0]['globalIndex'] );
	        $this->assertSame( 0, $this->provider->fetch_calls );
	    }

	    public function test_fetch_album_chunk_fetches_cache_miss_and_clamps_negative_offset(): void {
	        $album_url = 'https://photos.google.com/share/AF1QipAlbum?key=abc';
	        $_POST = array(
	            'nonce' => 'ok',
	            'album_url' => $album_url,
	            'offset' => '-10',
	            'count' => '1',
	            'limit' => '2',
	            'show_videos' => 'true',
	        );

	        $response = $this->callAjax( 'handle_fetch_album_chunk' );

	        $this->assertTrue( $response->success );
	        $this->assertSame( 0, $response->data['offset'] );
	        $this->assertSame( 2, $response->data['total_count'] );
	        $this->assertSame( 'ONE', $response->data['photos'][0]['id'] );
	        $this->assertSame( 1, $this->provider->fetch_calls );
	        $this->assertArrayHasKey( $this->invoke( 'get_cache_key', $album_url ), $GLOBALS['jzsa_test_transients'] );
	    }

	    public function test_fetch_album_chunk_returns_unable_to_load_when_success_but_empty_photos(): void {
        $album_url = 'https://photos.google.com/share/AF1QipAlbum?key=abc';
        $this->provider->album_result = array(
            'success'       => true,
            'is_deprecated' => false,
            'data'          => array(
                'title'  => 'Empty Album',
                'photos' => array(),
            ),
        );
        $_POST = array(
            'nonce'     => 'ok',
            'album_url' => $album_url,
        );

        $response = $this->callAjax( 'handle_fetch_album_chunk' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Unable to load album', $response->data );
    }

    public function test_fetch_album_chunk_returns_provider_error_without_cache(): void {
	        $album_url = 'https://photos.google.com/share/AF1QipAlbum?key=abc';
	        $this->provider->album_result = array(
	            'success' => false,
	            'error' => 'Album unavailable',
	        );
	        $_POST = array(
	            'nonce' => 'ok',
	            'album_url' => $album_url,
	        );

	        $response = $this->callAjax( 'handle_fetch_album_chunk' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Album unavailable', $response->data );
	        $this->assertSame( 1, $this->provider->fetch_calls );
	        $this->assertArrayNotHasKey( $this->invoke( 'get_cache_key', $album_url ), $GLOBALS['jzsa_test_transients'] );
	    }

    public function test_refresh_urls_rejects_invalid_nonce(): void {
        $GLOBALS['jzsa_test_nonce_valid'] = false;
        $_POST = array( 'nonce' => 'bad', 'album_url' => 'https://photos.google.com/share/x' );

        $response = $this->callAjax( 'handle_refresh_urls' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Invalid nonce', $response->data );
        $this->assertSame( 0, $this->provider->fetch_calls );
    }

    public function test_refresh_urls_rejects_missing_album_url(): void {
        $_POST = array( 'nonce' => 'ok' );

        $response = $this->callAjax( 'handle_refresh_urls' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Missing album URL', $response->data );
        $this->assertSame( 0, $this->provider->fetch_calls );
    }

    public function test_refresh_urls_updates_primary_and_backup_caches(): void {
        $album_url = 'https://photos.google.com/share/AF1QipAlbum?key=abc';
        $expiry_key = $this->invoke( 'get_expiration_key', $album_url );
        $GLOBALS['jzsa_test_options'][ $expiry_key ] = 3600;
        $_POST = array(
            'nonce' => 'ok',
            'album_url' => $album_url,
        );

        $response = $this->callAjax( 'handle_refresh_urls' );

        $this->assertTrue( $response->success );
        $this->assertCount( 2, $response->data['photos'] );
        $this->assertSame( 1, $this->provider->fetch_calls );
        $primary = $GLOBALS['jzsa_test_transients'][ $this->invoke( 'get_cache_key', $album_url ) ];
        $backup  = $GLOBALS['jzsa_test_transients'][ $this->invoke( 'get_backup_cache_key', $album_url ) ];
	        $this->assertSame( 'Fresh Album', $primary['title'] );
	        $this->assertSame( $primary, $backup );
	    }

	    public function test_refresh_urls_returns_provider_error_without_mutating_cache(): void {
	        $album_url = 'https://photos.google.com/share/AF1QipAlbum?key=abc';
	        $cache_key = $this->invoke( 'get_cache_key', $album_url );
	        $GLOBALS['jzsa_test_transients'][ $cache_key ] = array(
	            'title' => 'Existing',
	            'photos' => array( array( 'url' => 'https://lh3.googleusercontent.com/old', 'id' => 'OLD' ) ),
	            'is_deprecated' => false,
	        );
	        $this->provider->album_result = array(
	            'success' => false,
	            'error' => 'Refresh failed',
	        );
	        $_POST = array(
	            'nonce' => 'ok',
	            'album_url' => $album_url,
	        );

	        $response = $this->callAjax( 'handle_refresh_urls' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Refresh failed', $response->data );
	        $this->assertSame( 'Existing', $GLOBALS['jzsa_test_transients'][ $cache_key ]['title'] );
	        $this->assertArrayNotHasKey( $this->invoke( 'get_backup_cache_key', $album_url ), $GLOBALS['jzsa_test_transients'] );
	    }

    public function test_download_rejects_invalid_nonce_before_http_request(): void {
        $GLOBALS['jzsa_test_nonce_valid'] = false;
        $_POST = array( 'nonce' => 'bad', 'media_url' => 'https://lh3.googleusercontent.com/photo' );

        $response = $this->callAjax( 'handle_download_image' );

        $this->assertFalse( $response->success );
        $this->assertSame( 403, $response->status_code );
        $this->assertSame( 'Invalid nonce', $response->data['message'] );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_download_rejects_missing_and_non_google_media_urls_before_http_request(): void {
        $_POST = array( 'nonce' => 'ok' );
        $missing = $this->callAjax( 'handle_download_image' );

        $_POST = array(
            'nonce' => 'ok',
            'media_url' => 'https://example.com/not-google.jpg',
        );
        $invalid_host = $this->callAjax( 'handle_download_image' );

        $this->assertFalse( $missing->success );
        $this->assertSame( 400, $missing->status_code );
        $this->assertSame( 'Missing media URL', $missing->data['message'] );
        $this->assertFalse( $invalid_host->success );
        $this->assertSame( 400, $invalid_host->status_code );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

	    public function test_download_requires_large_download_confirmation_from_content_length(): void {
	        $media_url = 'https://lh3.googleusercontent.com/photo=w800-h600';
	        $GLOBALS['jzsa_test_http_responses'][ $media_url ] = $this->response(
            200,
            'abc',
            array(
                'content-length' => '5000',
                'content-type' => 'image/jpeg',
            )
        );
        $_POST = array(
            'nonce' => 'ok',
            'media_url' => $media_url,
            'filename' => 'photo.jpg',
            'warning_size_bytes' => '100',
        );

        $response = $this->callAjax( 'handle_download_image' );

        $this->assertFalse( $response->success );
	        $this->assertSame( 413, $response->status_code );
	        $this->assertTrue( $response->data['requires_large_download_confirmation'] );
	        $this->assertSame( 5000, $response->data['actual_size_bytes'] );
	    }

	    public function test_download_rejects_content_length_above_hard_limit(): void {
	        $media_url = 'https://lh3.googleusercontent.com/photo=w800-h600';
	        $GLOBALS['jzsa_test_filters']['jzsa_max_download_hard_limit'] = 100;
	        $GLOBALS['jzsa_test_http_responses'][ $media_url ] = $this->response(
	            200,
	            'abc',
	            array(
	                'content-length' => '5000',
	                'content-type' => 'image/jpeg',
	            )
	        );
	        $_POST = array(
	            'nonce' => 'ok',
	            'media_url' => $media_url,
	            'filename' => 'photo.jpg',
	            'warning_size_bytes' => '0',
	        );

	        $response = $this->callAjax( 'handle_download_image' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 413, $response->status_code );
	        $this->assertTrue( $response->data['exceeds_hard_download_limit'] );
	        $this->assertSame( 100, $response->data['hard_limit_bytes'] );
	        $this->assertSame( 5000, $response->data['actual_size_bytes'] );
	    }

	    public function test_download_reports_http_fetch_failure(): void {
	        $media_url = 'https://lh3.googleusercontent.com/photo=w800-h600';
	        $GLOBALS['jzsa_test_http_responses'][ $media_url ] = new \WP_Error( 'timeout', 'Connection timed out' );
	        $_POST = array(
	            'nonce' => 'ok',
	            'media_url' => $media_url,
	        );

	        $response = $this->callAjax( 'handle_download_image' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 502, $response->status_code );
	        $this->assertStringContainsString( 'Connection timed out', $response->data['message'] );
	    }

	    public function test_download_requires_large_download_confirmation_from_body_size(): void {
	        $media_url = 'https://lh3.googleusercontent.com/photo=w800-h600';
	        $GLOBALS['jzsa_test_http_responses'][ $media_url ] = $this->response(
	            200,
	            str_repeat( 'a', 120 ),
	            array( 'content-type' => 'image/jpeg' )
	        );
	        $_POST = array(
	            'nonce' => 'ok',
	            'media_url' => $media_url,
	            'filename' => 'photo.jpg',
	            'warning_size_bytes' => '100',
	        );

	        $response = $this->callAjax( 'handle_download_image' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 413, $response->status_code );
	        $this->assertTrue( $response->data['requires_large_download_confirmation'] );
	        $this->assertSame( 120, $response->data['actual_size_bytes'] );
	    }

	    public function test_download_rejects_body_size_above_hard_limit(): void {
	        $media_url = 'https://lh3.googleusercontent.com/photo=w800-h600';
	        $GLOBALS['jzsa_test_filters']['jzsa_max_download_hard_limit'] = 100;
	        $GLOBALS['jzsa_test_http_responses'][ $media_url ] = $this->response(
	            200,
	            str_repeat( 'a', 120 ),
	            array( 'content-type' => 'image/jpeg' )
	        );
	        $_POST = array(
	            'nonce' => 'ok',
	            'media_url' => $media_url,
	            'filename' => 'photo.jpg',
	            'warning_size_bytes' => '0',
	        );

	        $response = $this->callAjax( 'handle_download_image' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 413, $response->status_code );
	        $this->assertTrue( $response->data['exceeds_hard_download_limit'] );
	        $this->assertSame( 100, $response->data['hard_limit_bytes'] );
	        $this->assertSame( 120, $response->data['actual_size_bytes'] );
	    }

	    public function test_download_allow_large_download_bypasses_content_length_warning(): void {
        $media_url = 'https://lh3.googleusercontent.com/photo=w800-h600';
        $GLOBALS['jzsa_test_http_responses'][ $media_url ] = $this->response(
            200,
            '',
            array(
                'content-length' => '5000',
                'content-type'   => 'image/jpeg',
            )
        );
        $_POST = array(
            'nonce'               => 'ok',
            'media_url'           => $media_url,
            'warning_size_bytes'  => '100',
            'allow_large_download' => 'true',
        );

        $response = $this->callAjax( 'handle_download_image' );

        $this->assertFalse( $response->success );
        $this->assertSame( 502, $response->status_code );
        $this->assertSame( 'Empty media data', $response->data['message'] );
    }

    public function test_download_accepts_legacy_image_url_parameter_for_validation_and_fetch(): void {
	        $media_url = 'https://lh3.googleusercontent.com/photo=w800-h600';
	        $GLOBALS['jzsa_test_http_responses'][ $media_url ] = $this->response(
	            200,
	            '',
	            array( 'content-type' => 'image/jpeg' )
	        );
	        $_POST = array(
	            'nonce' => 'ok',
	            'image_url' => $media_url,
	            'filename' => 'photo.jpg',
	        );

	        $response = $this->callAjax( 'handle_download_image' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 502, $response->status_code );
	        $this->assertSame( 'Empty media data', $response->data['message'] );
	        $this->assertSame( $media_url, $GLOBALS['jzsa_test_http_requests'][0]['url'] );
	    }
}
