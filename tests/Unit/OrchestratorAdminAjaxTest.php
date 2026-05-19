<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Shared_Albums;
use ReflectionClass;

/**
 * Tests admin AJAX guardrails and post-save cache invalidation.
 */
class OrchestratorAdminAjaxTest extends TestCase {

    private JZSA_Shared_Albums $orchestrator;
    private ReflectionClass $reflection;

    protected function setUp(): void {
        $this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
        $this->reflection   = new ReflectionClass( $this->orchestrator );

        $_POST = array();
        $GLOBALS['jzsa_test_current_user_can']      = true;
        $GLOBALS['jzsa_test_nonce_valid']           = true;
        $GLOBALS['jzsa_test_transients']            = array();
        $GLOBALS['jzsa_test_options']               = array();
	        $GLOBALS['jzsa_test_posts']                 = array();
	        $GLOBALS['jzsa_test_do_shortcode_calls']    = array();
	        $GLOBALS['jzsa_test_do_shortcode_output']   = '';
	        $GLOBALS['jzsa_test_clear_cache_calls']     = array();
	        $GLOBALS['jzsa_test_clear_album_result']    = array(
	            'album_transient_rows'      => 4,
	            'photo_meta_transient_rows' => 0,
	            'expiry_rows'               => 2,
	        );
	        $GLOBALS['jzsa_test_clear_photo_meta_result'] = array(
	            'album_transient_rows'      => 0,
	            'photo_meta_transient_rows' => 6,
	            'expiry_rows'               => 0,
	        );
    }

	    protected function tearDown(): void {
	        $_POST = array();
	        $GLOBALS['jzsa_test_current_user_can'] = false;
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

    public function test_shortcode_preview_rejects_user_without_capability(): void {
        $GLOBALS['jzsa_test_current_user_can'] = false;
        $_POST = array(
            'nonce' => 'ok',
            'shortcode' => '[jzsa-album link="https://photos.google.com/share/AF1QipAlbum?key=abc"]',
        );

        $response = $this->callAjax( 'handle_shortcode_preview' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Insufficient permissions', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_do_shortcode_calls'] );
    }

    public function test_shortcode_preview_rejects_unsupported_shortcode_before_rendering(): void {
        $_POST = array(
            'nonce' => 'ok',
            'shortcode' => '[gallery ids="1,2,3"]',
        );

        $response = $this->callAjax( 'handle_shortcode_preview' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Only the [jzsa-album] shortcode is supported in this preview.', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_do_shortcode_calls'] );
    }

    public function test_shortcode_preview_returns_rendered_html(): void {
        $shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipAlbum?key=abc"]';
        $GLOBALS['jzsa_test_do_shortcode_output'] = '<div class="jzsa-album">Preview</div>';
        $_POST = array(
            'nonce' => 'ok',
            'shortcode' => $shortcode,
        );

        $response = $this->callAjax( 'handle_shortcode_preview' );

        $this->assertTrue( $response->success );
        $this->assertSame( '<div class="jzsa-album">Preview</div>', $response->data['html'] );
        $this->assertSame( array( $shortcode ), $GLOBALS['jzsa_test_do_shortcode_calls'] );
    }

	    public function test_clear_cache_ajax_rejects_invalid_nonce_before_database_query(): void {
	        $GLOBALS['jzsa_test_nonce_valid'] = false;
	        $_POST = array(
	            'nonce' => 'bad',
            'scope' => 'all',
        );

        $response = $this->callAjax( 'handle_clear_cache' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Invalid nonce', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_clear_cache_calls'] );
	    }

	    public function test_clear_cache_ajax_defaults_unknown_scope_to_all(): void {
	        $_POST = array(
	            'nonce' => 'ok',
	            'scope' => 'everything-please',
        );

        $response = $this->callAjax( 'handle_clear_cache' );

	        $this->assertTrue( $response->success );
	        $this->assertSame( 'Cleared 2 cached albums and 3 cached photo metadata entries.', $response->data['message'] );
	        $this->assertSame( array( 'all', 'album', 'photo_meta' ), $GLOBALS['jzsa_test_clear_cache_calls'] );
	    }

	    public function test_clear_cache_ajax_reports_album_scope_count(): void {
	        $_POST = array(
	            'nonce' => 'ok',
	            'scope' => 'album',
        );

        $response = $this->callAjax( 'handle_clear_cache' );

	        $this->assertTrue( $response->success );
	        $this->assertSame( 'Cleared 2 cached albums.', $response->data['message'] );
	        $this->assertSame( array( 'album' ), $GLOBALS['jzsa_test_clear_cache_calls'] );
	    }

    public function test_clear_cache_deletes_album_and_photo_meta_caches_from_post_content(): void {
        $album_url = 'https://photos.google.com/share/AF1QipAlbum?key=abc';
        $photo_url = $this->invoke( 'build_photo_page_url', $album_url, 'PHOTO1' );
        $GLOBALS['jzsa_test_posts'][123] = (object) array(
            'post_content' => 'Before [jzsa-album link="' . $album_url . '"] after',
        );
        $GLOBALS['jzsa_test_transients'][ $this->invoke( 'get_cache_key', $album_url ) ] = array(
            'title' => 'Cached',
            'photos' => array(
                array( 'id' => 'PHOTO1', 'url' => 'https://lh3.googleusercontent.com/one' ),
                array( 'id' => 'PHOTO1', 'url' => 'https://lh3.googleusercontent.com/duplicate' ),
            ),
            'is_deprecated' => false,
        );
        $GLOBALS['jzsa_test_transients'][ $this->invoke( 'get_backup_cache_key', $album_url ) ] = array( 'title' => 'Backup' );
        $GLOBALS['jzsa_test_transients'][ $this->invoke( 'get_photo_meta_cache_key', $photo_url ) ] = array( 'filename' => 'one.jpg' );
        $GLOBALS['jzsa_test_options'][ $this->invoke( 'get_expiration_key', $album_url ) ] = 3600;

        $this->orchestrator->clear_cache( 123 );

        $this->assertSame( array(), $GLOBALS['jzsa_test_transients'] );
        $this->assertSame( array(), $GLOBALS['jzsa_test_options'] );
    }
}
