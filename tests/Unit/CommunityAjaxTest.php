<?php

use PHPUnit\Framework\TestCase;

class CommunityAjaxTest extends TestCase {

    private JZSA_Community $community;

    protected function setUp(): void {
        $this->community = new JZSA_Community();
        $_POST = array();
        $GLOBALS['jzsa_test_current_user_can'] = true;
        $GLOBALS['jzsa_test_current_user_id']  = 1;
        $GLOBALS['jzsa_test_user_meta']        = array();
        $GLOBALS['jzsa_test_transients']       = array();
	        $GLOBALS['jzsa_test_http_responses']   = array();
	        $GLOBALS['jzsa_test_http_requests']    = array();
	        $GLOBALS['jzsa_test_rest_routes']      = array();
	        $this->resetConnectionCache();
	    }

    protected function tearDown(): void {
        $_POST = array();
        $this->resetConnectionCache();
    }

    private function resetConnectionCache(): void {
        $reflection = new ReflectionClass( JZSA_Community::class );
        $prop = $reflection->getProperty( 'cached_connection_state' );
        $prop->setValue( null, null );
    }

	    private function response( int $code, array $body = array() ): array {
	        return array(
	            'response' => array( 'code' => $code ),
	            'body'     => wp_json_encode( $body ),
	        );
	    }

	    private function rawResponse( int $code, string $body = '' ): array {
	        return array(
	            'response' => array( 'code' => $code ),
	            'body'     => $body,
	        );
	    }

    private function callAjax( string $method ): JZSA_Test_JSON_Response {
        try {
            $this->community->$method();
        } catch ( JZSA_Test_JSON_Response $response ) {
            return $response;
        }

        $this->fail( 'Expected ' . $method . ' to send a JSON response.' );
    }

    private function connect( string $jwt = 'jwt-token' ): void {
        update_user_meta( 1, JZSA_Community::OPT_JWT, $jwt );
    }

    private function validEntryPost( array $overrides = array() ): array {
        return array_merge(
            array(
                'title'                   => 'My Album',
                'shortcode'               => '[jzsa-album link="https://photos.google.com/share/AF1QipTest"]',
                'description'             => 'Description',
                'tags'                    => 'landscape,travel',
                'site_url'                => 'https://example.com/gallery',
                'photographer_name'       => 'Jane Doe',
                'photographer_bio'        => 'Bio',
                'public_showcase_consent' => 'false',
            ),
            $overrides
        );
    }

    public function test_verify_connection_without_jwt_is_disconnected(): void {
        $this->assertSame( 'disconnected', JZSA_Community::verify_connection() );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_verify_connection_connected_sends_bearer_token(): void {
        $this->connect();
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 200 );

        $this->assertSame( 'connected', JZSA_Community::verify_connection() );

        $request = $GLOBALS['jzsa_test_http_requests'][0];
        $this->assertSame( 'GET', $request['method'] );
        $this->assertSame( 'Bearer jwt-token', $request['args']['headers']['Authorization'] );
    }

    public function test_verify_connection_unauthorized_clears_jwt(): void {
        $this->connect();
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 401 );

        $this->assertSame( 'disconnected', JZSA_Community::verify_connection() );
        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
    }

    public function test_verify_connection_server_error_keeps_jwt(): void {
        $this->connect();
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = new WP_Error( 'down', 'offline' );

        $this->assertSame( 'server_error', JZSA_Community::verify_connection() );
        $this->assertSame( 'jwt-token', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
    }

    public function test_verify_connection_5xx_response_returns_server_error_without_clearing_jwt(): void {
        $this->connect();
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 503 );

        $this->assertSame( 'server_error', JZSA_Community::verify_connection() );
        $this->assertSame( 'jwt-token', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
    }

    public function test_verify_connection_forbidden_response_clears_jwt(): void {
        $this->connect();
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 403 );

        $this->assertSame( 'disconnected', JZSA_Community::verify_connection() );
        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
    }

    public function test_browse_sanitizes_page_sort_and_forwards_jwt(): void {
        $this->connect();
        $_POST = array(
            'page' => '-3',
            'q'    => ' slider ',
            'tag'  => ' Dark ',
            'sort' => 'invalid-sort',
        );
        $url = JZSA_COMMUNITY_API_URL . '/v1/entries?per_page=12&page=3&q=slider&tag=Dark&sort=newest';
        $GLOBALS['jzsa_test_http_responses'][ $url ] = $this->response( 200, array( 'entries' => array( array( 'id' => 7 ) ) ) );

        $response = $this->callAjax( 'ajax_browse' );

        $this->assertTrue( $response->success );
        $this->assertSame( 7, $response->data['entries'][0]['id'] );
        $request = $GLOBALS['jzsa_test_http_requests'][0];
        $this->assertSame( $url, $request['url'] );
        $this->assertSame( 'test-read-key', $request['args']['headers']['X-JZSA-Plugin-Key'] );
        $this->assertSame( 'Bearer jwt-token', $request['args']['headers']['Authorization'] );
    }

    public function test_browse_server_error_returns_code(): void {
        $GLOBALS['jzsa_test_http_responses']['*'] = $this->response( 500 );

        $response = $this->callAjax( 'ajax_browse' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'server_error', $response->data['code'] );
    }

    public function test_browse_unauthorized_returns_403_before_http_request(): void {
        $GLOBALS['jzsa_test_current_user_can'] = false;

        $response = $this->callAjax( 'ajax_browse' );

        $this->assertFalse( $response->success );
        $this->assertSame( 403, $response->status_code );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_browse_wp_error_returns_server_unreachable_code(): void {
        $GLOBALS['jzsa_test_http_responses']['*'] = new WP_Error( 'http_request_failed', 'timeout' );

        $response = $this->callAjax( 'ajax_browse' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'server_unreachable', $response->data['code'] );
    }

    public function test_browse_without_jwt_omits_authorization_header(): void {
        $url = JZSA_COMMUNITY_API_URL . '/v1/entries?per_page=12&page=1&sort=newest';
        $GLOBALS['jzsa_test_http_responses'][ $url ] = $this->response( 200, array( 'entries' => array() ) );

        $response = $this->callAjax( 'ajax_browse' );

        $this->assertTrue( $response->success );
        $request = $GLOBALS['jzsa_test_http_requests'][0];
        $this->assertArrayNotHasKey( 'Authorization', $request['args']['headers'] );
        $this->assertSame( 'test-read-key', $request['args']['headers']['X-JZSA-Plugin-Key'] );
    }

    public function test_publish_requires_connection_before_http_request(): void {
        $response = $this->callAjax( 'ajax_publish' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'Not connected', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_publish_success_posts_normalized_payload(): void {
        $this->connect();
        $_POST = $this->validEntryPost(
            array(
                'tags'                    => ' Landscape,TRAVEL ',
                'site_url'                => 'example.com/gallery',
                'public_showcase_consent' => 'true',
            )
        );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries' ] = $this->response( 201, array( 'id' => 44 ) );

        $response = $this->callAjax( 'ajax_publish' );

        $this->assertTrue( $response->success );
        $this->assertSame( 44, $response->data['id'] );

        $request = $GLOBALS['jzsa_test_http_requests'][0];
        $payload = json_decode( $request['args']['body'], true );
        $this->assertSame( 'POST', $request['method'] );
        $this->assertSame( 'Bearer jwt-token', $request['args']['headers']['Authorization'] );
        $this->assertSame( array( 'landscape', 'travel' ), $payload['tags'] );
        $this->assertSame( 'https://example.com/gallery', $payload['site_url'] );
        $this->assertTrue( $payload['public_showcase_consent'] );
    }

    public function test_publish_validation_error_happens_before_http_request(): void {
        $this->connect();
        $_POST = $this->validEntryPost( array( 'tags' => 'a,b,c,d,e,f' ) );

        $response = $this->callAjax( 'ajax_publish' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'Use no more than 5 tags', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

	    public function test_publish_server_validation_error_returns_message_and_details(): void {
	        $this->connect();
	        $_POST = $this->validEntryPost();
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries' ] = $this->response(
            422,
            array(
                'error'   => 'Invalid shortcode.',
                'details' => array( 'shortcode' => 'Unsupported option.' ),
            )
        );

        $response = $this->callAjax( 'ajax_publish' );

        $this->assertFalse( $response->success );
	        $this->assertSame( 'Invalid shortcode.', $response->data['message'] );
	        $this->assertSame( 'Unsupported option.', $response->data['details']['shortcode'] );
	    }

	    public function test_publish_malformed_error_body_falls_back_to_status_message(): void {
	        $this->connect();
	        $_POST = $this->validEntryPost();
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries' ] = $this->rawResponse( 503, 'not-json' );

	        $response = $this->callAjax( 'ajax_publish' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Server error (503).', $response->data['message'] );
	        $this->assertNull( $response->data['details'] );
	    }

    public function test_publish_wp_error_returns_unreachable(): void {
        $this->connect();
        $_POST = $this->validEntryPost();
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries' ] =
            new WP_Error( 'http_request_failed', 'timeout' );

        $response = $this->callAjax( 'ajax_publish' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'community server', $response->data );
    }

    public function test_delete_entry_success_uses_delete_method(): void {
        $this->connect();
        $_POST = array( 'entry_id' => '42' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/42' ] = $this->response( 204 );

        $response = $this->callAjax( 'ajax_delete_entry' );

        $this->assertTrue( $response->success );
        $this->assertSame( 'DELETE', $GLOBALS['jzsa_test_http_requests'][0]['method'] );
    }

    public function test_delete_entry_rejects_invalid_id_before_http_request(): void {
        $this->connect();
        $_POST = array( 'entry_id' => '0' );

        $response = $this->callAjax( 'ajax_delete_entry' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'Invalid entry ID', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_delete_account_success_clears_local_connection_state(): void {
        $this->connect();
        update_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, 'Jane' );
        update_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, 'https://example.com' );
        $_POST = array( 'delete_entries' => 'true' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 204 );

        $response = $this->callAjax( 'ajax_delete_account' );

        $this->assertTrue( $response->success );
        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, true ) );
        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, true ) );
        $this->assertSame( 'disconnected', get_transient( JZSA_Community::NONCE_NOTICE_KEY . '1' ) );

        $payload = json_decode( $GLOBALS['jzsa_test_http_requests'][0]['args']['body'], true );
        $this->assertTrue( $payload['delete_entries'] );
    }

    public function test_delete_account_failure_keeps_local_connection_state(): void {
        $this->connect();
        update_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, 'Jane' );
        $_POST = array( 'delete_entries' => 'false' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 409 );

        $response = $this->callAjax( 'ajax_delete_account' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'Could not delete account', $response->data );
        $this->assertSame( 'jwt-token', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
        $this->assertSame( 'Jane', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, true ) );
    }

    public function test_delete_account_wp_error_returns_unreachable(): void {
        $this->connect();
        $_POST = array( 'delete_entries' => 'false' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] =
            new WP_Error( 'http_request_failed', 'timeout' );

        $response = $this->callAjax( 'ajax_delete_account' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'community server', $response->data );
        $this->assertSame( 'jwt-token', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
    }

	    public function test_load_my_entries_returns_entries_array(): void {
	        $this->connect();
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 200, array(
            'entries' => array( array( 'id' => 12 ) ),
        ) );

        $response = $this->callAjax( 'ajax_load_my_entries' );

	        $this->assertTrue( $response->success );
	        $this->assertSame( 12, $response->data[0]['id'] );
	    }

	    public function test_load_my_entries_non_200_uses_server_error_message(): void {
	        $this->connect();
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 403, array(
	            'error' => 'Session expired.',
	        ) );

	        $response = $this->callAjax( 'ajax_load_my_entries' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Session expired.', $response->data );
	    }

    public function test_load_my_entries_not_connected_returns_error_before_http_request(): void {
        $response = $this->callAjax( 'ajax_load_my_entries' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'Not connected', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_load_my_entries_server_error_returns_server_error_code(): void {
        $this->connect();
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->response( 503 );

        $response = $this->callAjax( 'ajax_load_my_entries' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'server_error', $response->data['code'] );
    }

    public function test_update_entry_omits_consent_when_not_posted(): void {
        $this->connect();
        $_POST = $this->validEntryPost(
            array(
                'entry_id'    => '9',
                'description' => '',
                'tags'        => 'one,two',
            )
        );
        unset( $_POST['public_showcase_consent'] );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/9' ] = $this->response( 200 );

        $response = $this->callAjax( 'ajax_update_entry' );

        $this->assertTrue( $response->success );
        $this->assertSame( 'PATCH', $GLOBALS['jzsa_test_http_requests'][0]['method'] );
        $payload = json_decode( $GLOBALS['jzsa_test_http_requests'][0]['args']['body'], true );
        $this->assertArrayNotHasKey( 'public_showcase_consent', $payload );
    }

    public function test_update_entry_includes_explicit_false_consent(): void {
        $this->connect();
        $_POST = $this->validEntryPost(
            array(
                'entry_id'                => '9',
                'public_showcase_consent' => 'false',
            )
        );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/9' ] = $this->response( 200 );

        $response = $this->callAjax( 'ajax_update_entry' );

        $this->assertTrue( $response->success );
        $payload = json_decode( $GLOBALS['jzsa_test_http_requests'][0]['args']['body'], true );
        $this->assertArrayHasKey( 'public_showcase_consent', $payload );
        $this->assertFalse( $payload['public_showcase_consent'] );
    }

    public function test_update_entry_rejects_invalid_id_before_http_request(): void {
        $this->connect();
        $_POST = $this->validEntryPost( array( 'entry_id' => '0' ) );

        $response = $this->callAjax( 'ajax_update_entry' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'Invalid entry ID', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_update_display_name_success_saves_server_value(): void {
        $this->connect();
        $_POST = array( 'display_name' => 'Jane Doe' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-name' ] = $this->response( 200, array(
            'display_name' => 'Jane Server',
        ) );

        $response = $this->callAjax( 'ajax_update_display_name' );

        $this->assertTrue( $response->success );
        $this->assertSame( 'Jane Server', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, true ) );
        $this->assertSame( 'Jane Server', $response->data['display_name'] );
    }

	    public function test_update_display_name_rejects_too_few_letters_before_http_request(): void {
	        $this->connect();
	        $_POST = array( 'display_name' => 'J2' );

        $response = $this->callAjax( 'ajax_update_display_name' );

        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'at least 3 letters', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_update_display_name_server_error_does_not_overwrite_local_meta(): void {
	        $this->connect();
	        update_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, 'Original Name' );
	        $_POST = array( 'display_name' => 'Jane Doe' );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-name' ] = $this->response( 409, array(
	            'error' => 'Name already exists.',
	        ) );

	        $response = $this->callAjax( 'ajax_update_display_name' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Name already exists.', $response->data );
	        $this->assertSame( 'Original Name', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, true ) );
	    }

    public function test_update_display_name_wp_error_returns_unreachable(): void {
        $this->connect();
        $_POST = array( 'display_name' => 'Jane Doe' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-name' ] =
            new WP_Error( 'http_request_failed', 'timeout' );

        $response = $this->callAjax( 'ajax_update_display_name' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'community server', $response->data );
    }

    public function test_update_display_url_empty_deletes_local_meta(): void {
        $this->connect();
        update_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, 'https://old.example' );
        $_POST = array( 'display_url' => '' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-url' ] = $this->response( 200, array(
            'display_url' => '',
        ) );

        $response = $this->callAjax( 'ajax_update_display_url' );

        $this->assertTrue( $response->success );
        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, true ) );
        $this->assertSame( '', $response->data['display_url'] );
    }

    public function test_update_display_url_adds_https_before_sending(): void {
        $this->connect();
        $_POST = array( 'display_url' => 'example.com/profile' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-url' ] = $this->response( 200, array(
            'display_url' => 'https://example.com/profile',
        ) );

        $response = $this->callAjax( 'ajax_update_display_url' );

        $this->assertTrue( $response->success );
        $payload = json_decode( $GLOBALS['jzsa_test_http_requests'][0]['args']['body'], true );
        $this->assertSame( 'https://example.com/profile', $payload['display_url'] );
        $this->assertSame( 'https://example.com/profile', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, true ) );
    }

    public function test_update_display_url_rejects_invalid_url_before_http_request(): void {
        $this->connect();
        $_POST = array( 'display_url' => 'http://' );

        $response = $this->callAjax( 'ajax_update_display_url' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'valid display URL', $response->data );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_update_display_url_wp_error_returns_unreachable(): void {
        $this->connect();
        $_POST = array( 'display_url' => 'example.com' );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-url' ] =
            new WP_Error( 'http_request_failed', 'timeout' );

        $response = $this->callAjax( 'ajax_update_display_url' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'community server', $response->data );
    }

	    public function test_interact_clamps_count_and_does_not_require_jwt(): void {
	        $_POST = array(
	            'entry_id'    => '77',
            'action_type' => 'copy',
            'count'       => '999',
        );
        $GLOBALS['jzsa_test_http_responses']['*'] = new WP_Error( 'down', 'offline' );

        $response = $this->callAjax( 'ajax_interact' );

        $this->assertTrue( $response->success );
        $request = $GLOBALS['jzsa_test_http_requests'][0];
        $payload = json_decode( $request['args']['body'], true );
        $this->assertSame( JZSA_COMMUNITY_API_URL . '/v1/entries/77/interact', $request['url'] );
	        $this->assertSame( 5, $payload['count'] );
	        $this->assertArrayNotHasKey( 'Authorization', $request['args']['headers'] );
	    }

	    public function test_interact_connected_forwards_jwt_and_raises_zero_count_to_one(): void {
	        $this->connect();
	        $_POST = array(
	            'entry_id'    => '77',
	            'action_type' => 'download',
	            'count'       => '0',
	        );
	        $GLOBALS['jzsa_test_http_responses']['*'] = $this->response( 200 );

	        $response = $this->callAjax( 'ajax_interact' );

	        $this->assertTrue( $response->success );
	        $request = $GLOBALS['jzsa_test_http_requests'][0];
	        $payload = json_decode( $request['args']['body'], true );
	        $this->assertSame( 'Bearer jwt-token', $request['args']['headers']['Authorization'] );
	        $this->assertSame( 1, $payload['count'] );
	        $this->assertSame( 'download', $payload['action_type'] );
	    }

    public function test_rate_rejects_out_of_range_rating_before_http_request(): void {
        $this->connect();
        $_POST = array(
            'entry_id' => '77',
            'rating'   => '6',
        );

        $response = $this->callAjax( 'ajax_rate' );

        $this->assertFalse( $response->success );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_rate_success_returns_server_body(): void {
        $this->connect();
        $_POST = array(
            'entry_id' => '77',
            'rating'   => '4',
        );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/77/rate' ] = $this->response( 200, array(
            'rating_avg' => 4.5,
        ) );

        $response = $this->callAjax( 'ajax_rate' );

        $this->assertTrue( $response->success );
        $this->assertSame( 4.5, $response->data['rating_avg'] );
        $payload = json_decode( $GLOBALS['jzsa_test_http_requests'][0]['args']['body'], true );
        $this->assertSame( 4, $payload['rating'] );
    }

    public function test_rate_server_error_returns_server_message(): void {
        $this->connect();
        $_POST = array(
            'entry_id' => '77',
            'rating'   => '4',
        );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/77/rate' ] = $this->response( 429, array(
            'error' => 'Rate limit exceeded.',
        ) );

        $response = $this->callAjax( 'ajax_rate' );

        $this->assertFalse( $response->success );
        $this->assertSame( 'Rate limit exceeded.', $response->data );
    }

    public function test_rate_requires_connection(): void {
        $_POST = array(
            'entry_id' => '77',
            'rating'   => '4',
        );

        $response = $this->callAjax( 'ajax_rate' );

        $this->assertFalse( $response->success );
        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
    }

    public function test_rate_wp_error_returns_unreachable(): void {
        $this->connect();
        $_POST = array(
            'entry_id' => '77',
            'rating'   => '3',
        );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/77/rate' ] =
            new WP_Error( 'http_request_failed', 'timeout' );

        $response = $this->callAjax( 'ajax_rate' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( 'community server', $response->data );
    }

    public function test_rate_server_error_without_error_field_falls_back_to_status_message(): void {
        $this->connect();
        $_POST = array(
            'entry_id' => '77',
            'rating'   => '2',
        );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/77/rate' ] =
            $this->rawResponse( 500, 'Internal Server Error' );

        $response = $this->callAjax( 'ajax_rate' );

        $this->assertFalse( $response->success );
        $this->assertStringContainsString( '500', $response->data );
    }

	    public function test_request_magic_link_rejects_invalid_display_name_before_http_request(): void {
	        $_POST = array(
	            'display_name' => '12',
            'display_url'  => '',
        );

        $response = $this->callAjax( 'ajax_request_magic_link' );

        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'at least 3 letters', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_request_magic_link_invalid_server_body_clears_challenge_and_keeps_disconnected(): void {
	        $_POST = array(
	            'display_name' => 'Jane Doe',
	            'display_url'  => '',
	        );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/auth/connect' ] = $this->response( 200, array(
	            'display_name' => 'Jane Doe',
	        ) );

	        $response = $this->callAjax( 'ajax_request_magic_link' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Invalid response from community server.', $response->data );
	        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_transients'] );
	    }

	    public function test_request_magic_link_partial_profile_sync_still_connects_with_local_fallbacks(): void {
	        $_POST = array(
	            'display_name' => 'Jane Doe',
	            'display_url'  => 'example.com/profile',
	        );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/auth/connect' ] = $this->response( 200, array(
	            'jwt'          => 'server-jwt',
	            'display_name' => 'Old Name',
	        ) );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-name' ] = new WP_Error( 'timeout', 'offline' );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-url' ] = $this->response( 500, array(
	            'error' => 'Nope',
	        ) );

	        $response = $this->callAjax( 'ajax_request_magic_link' );

	        $this->assertTrue( $response->success );
	        $this->assertSame( 'server-jwt', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
	        $this->assertSame( 'Old Name', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, true ) );
	        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, true ) );
	        $this->assertCount( 3, $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_request_magic_link_success_saves_jwt_profile_and_transient(): void {
	        $_POST = array(
	            'display_name' => 'Jane Doe',
            'display_url'  => 'example.com/profile',
        );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/auth/connect' ] = $this->response( 200, array(
            'jwt'          => 'server-jwt',
            'display_name' => 'Old Name',
        ) );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-name' ] = $this->response( 200, array(
            'display_name' => 'Jane Server',
        ) );
        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-url' ] = $this->response( 200, array(
            'display_url' => 'https://example.com/profile',
        ) );

        $response = $this->callAjax( 'ajax_request_magic_link' );

        $this->assertTrue( $response->success );
        $this->assertSame( 'server-jwt', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
        $this->assertSame( 'Jane Server', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_NAME, true ) );
        $this->assertSame( 'https://example.com/profile', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, true ) );

        $connect_payload = json_decode( $GLOBALS['jzsa_test_http_requests'][0]['args']['body'], true );
        $this->assertArrayHasKey( 'challenge', $connect_payload );
        $this->assertStringStartsWith( JZSA_Community::AUTH_CHALLENGE_PREFIX, array_key_first( $GLOBALS['jzsa_test_transients'] ) );

	        $url_payload = json_decode( $GLOBALS['jzsa_test_http_requests'][2]['args']['body'], true );
	        $this->assertSame( 'https://example.com/profile', $url_payload['display_url'] );
	    }

	    public function test_register_rest_routes_registers_public_challenge_endpoint(): void {
	        $this->community->register_rest_routes();

	        $this->assertCount( 1, $GLOBALS['jzsa_test_rest_routes'] );
	        $route = $GLOBALS['jzsa_test_rest_routes'][0];
	        $this->assertSame( 'jzsa/v1', $route['namespace'] );
	        $this->assertSame( '/community-challenge', $route['route'] );
	        $this->assertSame( 'GET', $route['args']['methods'] );
	        $this->assertSame( '__return_true', $route['args']['permission_callback'] );
	        $this->assertTrue( $route['args']['args']['challenge']['required'] );
	    }

	    public function test_rest_community_challenge_rejects_missing_challenge(): void {
	        $response = $this->community->rest_community_challenge(
	            new class {
	                public function get_param( string $key ): string {
	                    return '';
	                }
	            }
	        );

	        $this->assertInstanceOf( WP_Error::class, $response );
	        $this->assertSame( 'jzsa_missing_challenge', $response->get_error_code() );
	        $this->assertSame( array( 'status' => 400 ), $response->get_error_data() );
	    }

	    public function test_rest_community_challenge_rejects_invalid_or_reused_challenge(): void {
	        $challenge = 'challenge-token';
	        $request = new class( $challenge ) {
	            public function __construct( private string $challenge ) {}
	            public function get_param( string $key ): string {
	                return $this->challenge;
	            }
	        };

	        $invalid = $this->community->rest_community_challenge( $request );
	        $this->assertInstanceOf( WP_Error::class, $invalid );
	        $this->assertSame( 'jzsa_invalid_challenge', $invalid->get_error_code() );
	        $this->assertSame( array( 'status' => 404 ), $invalid->get_error_data() );

	        $transient_key = JZSA_Community::AUTH_CHALLENGE_PREFIX . hash( 'sha256', $challenge );
	        $GLOBALS['jzsa_test_transients'][ $transient_key ] = 'different-token';
	        $mismatch = $this->community->rest_community_challenge( $request );

	        $this->assertInstanceOf( WP_Error::class, $mismatch );
	        $this->assertSame( 'jzsa_invalid_challenge', $mismatch->get_error_code() );
	    }

	    public function test_rest_community_challenge_returns_and_consumes_valid_challenge(): void {
	        $challenge = 'valid-challenge-token';
	        $transient_key = JZSA_Community::AUTH_CHALLENGE_PREFIX . hash( 'sha256', $challenge );
	        $GLOBALS['jzsa_test_transients'][ $transient_key ] = $challenge;
	        $request = new class( $challenge ) {
	            public function __construct( private string $challenge ) {}
	            public function get_param( string $key ): string {
	                return $this->challenge;
	            }
	        };

	        $response = $this->community->rest_community_challenge( $request );

	        $this->assertIsArray( $response );
	        $this->assertSame( 'janzeman-shared-albums-for-google-photos', $response['plugin'] );
	        $this->assertSame( JZSA_VERSION, $response['version'] );
	        $this->assertSame( $challenge, $response['challenge'] );
	        $this->assertArrayNotHasKey( $transient_key, $GLOBALS['jzsa_test_transients'] );

	        $reused = $this->community->rest_community_challenge( $request );
	        $this->assertInstanceOf( WP_Error::class, $reused );
	        $this->assertSame( 'jzsa_invalid_challenge', $reused->get_error_code() );
	    }

	    // -------------------------------------------------------------------------
	    // delete_entry: server error and unreachable paths
	    // -------------------------------------------------------------------------

	    public function test_delete_entry_server_error_returns_status_message(): void {
	        $this->connect();
	        $_POST = array( 'entry_id' => '42' );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/42' ] = $this->response( 409 );

	        $response = $this->callAjax( 'ajax_delete_entry' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( '409', $response->data );
	    }

	    public function test_delete_entry_wp_error_returns_unreachable(): void {
	        $this->connect();
	        $_POST = array( 'entry_id' => '42' );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/42' ] = new WP_Error( 'timeout', 'Request timed out' );

	        $response = $this->callAjax( 'ajax_delete_entry' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'community server', $response->data );
	    }

	    public function test_delete_entry_requires_connection(): void {
	        $_POST = array( 'entry_id' => '42' );

	        $response = $this->callAjax( 'ajax_delete_entry' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'Not connected', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    // -------------------------------------------------------------------------
	    // update_entry: server error and unreachable paths
	    // -------------------------------------------------------------------------

	    public function test_update_entry_server_error_uses_body_error_field(): void {
	        $this->connect();
	        $_POST = $this->validEntryPost( array( 'entry_id' => '9' ) );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/9' ] = $this->response( 400, array(
	            'error' => 'Title already taken.',
	        ) );

	        $response = $this->callAjax( 'ajax_update_entry' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Title already taken.', $response->data );
	    }

	    public function test_update_entry_server_error_falls_back_to_status_message(): void {
	        $this->connect();
	        $_POST = $this->validEntryPost( array( 'entry_id' => '9' ) );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/9' ] = $this->rawResponse( 500, 'Internal Server Error' );

	        $response = $this->callAjax( 'ajax_update_entry' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( '500', $response->data );
	    }

	    public function test_update_entry_wp_error_returns_unreachable(): void {
	        $this->connect();
	        $_POST = $this->validEntryPost( array( 'entry_id' => '9' ) );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/entries/9' ] = new WP_Error( 'timeout', 'offline' );

	        $response = $this->callAjax( 'ajax_update_entry' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'community server', $response->data );
	    }

	    // -------------------------------------------------------------------------
	    // interact: invalid parameters rejected before HTTP request
	    // -------------------------------------------------------------------------

	    public function test_interact_rejects_missing_entry_id(): void {
	        $_POST = array( 'action_type' => 'copy', 'count' => '1' );

	        $response = $this->callAjax( 'ajax_interact' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_interact_rejects_missing_action_type(): void {
	        $_POST = array( 'entry_id' => '77', 'count' => '1' );

	        $response = $this->callAjax( 'ajax_interact' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    // -------------------------------------------------------------------------
	    // load_my_entries: WP_Error path
	    // -------------------------------------------------------------------------

	    public function test_load_my_entries_wp_error_returns_unreachable(): void {
	        $this->connect();
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = new WP_Error( 'timeout', 'Request timed out' );

	        $response = $this->callAjax( 'ajax_load_my_entries' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'community server', $response->data );
	    }

	    // -------------------------------------------------------------------------
	    // update_display_url: server error does not overwrite local meta
	    // -------------------------------------------------------------------------

	    public function test_update_display_url_server_error_does_not_overwrite_local_meta(): void {
	        $this->connect();
	        update_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, 'https://old.example' );
	        $_POST = array( 'display_url' => 'https://new.example' );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me/display-url' ] = $this->response( 422, array(
	            'error' => 'Invalid URL format.',
	        ) );

	        $response = $this->callAjax( 'ajax_update_display_url' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Invalid URL format.', $response->data );
	        $this->assertSame( 'https://old.example', get_user_meta( 1, JZSA_Community::OPT_DISPLAY_URL, true ) );
	    }

	    // -------------------------------------------------------------------------
	    // request_magic_link: display name / URL validation and HTTP error branches
	    // -------------------------------------------------------------------------

	    public function test_request_magic_link_display_name_over_50_chars_rejected(): void {
	        $_POST = array(
	            'display_name' => str_repeat( 'a', 51 ),
	            'display_url'  => '',
	        );

	        $response = $this->callAjax( 'ajax_request_magic_link' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( '50 characters', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_request_magic_link_invalid_display_url_rejected(): void {
	        $_POST = array(
	            'display_name' => 'Jane Doe',
	            'display_url'  => 'http://',
	        );

	        $response = $this->callAjax( 'ajax_request_magic_link' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'valid display URL', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_request_magic_link_wp_error_from_connect_clears_transient_and_returns_unreachable(): void {
	        $_POST = array(
	            'display_name' => 'Jane Doe',
	            'display_url'  => '',
	        );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/auth/connect' ] = new WP_Error( 'timeout', 'offline' );

	        $response = $this->callAjax( 'ajax_request_magic_link' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'community server', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_transients'] );
	        $this->assertSame( '', get_user_meta( 1, JZSA_Community::OPT_JWT, true ) );
	    }

	    public function test_request_magic_link_server_non_200_returns_body_error_message(): void {
	        $_POST = array(
	            'display_name' => 'Jane Doe',
	            'display_url'  => '',
	        );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/auth/connect' ] = $this->response( 409, array(
	            'error' => 'Identity already registered.',
	        ) );

	        $response = $this->callAjax( 'ajax_request_magic_link' );

	        $this->assertFalse( $response->success );
	        $this->assertSame( 'Identity already registered.', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_transients'] );
	    }

	    public function test_request_magic_link_server_non_200_falls_back_to_status_code(): void {
	        $_POST = array(
	            'display_name' => 'Jane Doe',
	            'display_url'  => '',
	        );
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/auth/connect' ] = $this->rawResponse( 503, 'Service Unavailable' );

	        $response = $this->callAjax( 'ajax_request_magic_link' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( '503', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_transients'] );
	    }

	    // -------------------------------------------------------------------------
	    // update_display_name: empty and over-length rejections
	    // -------------------------------------------------------------------------

	    public function test_update_display_name_empty_rejected(): void {
	        $this->connect();
	        $_POST = array( 'display_name' => '' );

	        $response = $this->callAjax( 'ajax_update_display_name' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'cannot be empty', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    public function test_update_display_name_over_50_chars_rejected(): void {
	        $this->connect();
	        $_POST = array( 'display_name' => str_repeat( 'a', 51 ) );

	        $response = $this->callAjax( 'ajax_update_display_name' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( '50 characters', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    // -------------------------------------------------------------------------
	    // delete_account: no JWT guard
	    // -------------------------------------------------------------------------

	    public function test_delete_account_requires_connection(): void {
	        $_POST = array( 'delete_entries' => 'true' );

	        $response = $this->callAjax( 'ajax_delete_account' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( 'Not connected', $response->data );
	        $this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	    }

	    // -------------------------------------------------------------------------
	    // load_my_entries: non-200 without error field falls back to status code
	    // -------------------------------------------------------------------------

	    public function test_load_my_entries_non_200_without_error_field_uses_status_code(): void {
	        $this->connect();
	        $GLOBALS['jzsa_test_http_responses'][ JZSA_COMMUNITY_API_URL . '/v1/me' ] = $this->rawResponse( 403, 'Forbidden' );

	        $response = $this->callAjax( 'ajax_load_my_entries' );

	        $this->assertFalse( $response->success );
	        $this->assertStringContainsString( '403', $response->data );
	    }

	    // -------------------------------------------------------------------------
	    // browse: page=0 is raised to 1
	    // -------------------------------------------------------------------------

	    public function test_browse_page_zero_defaults_to_page_one(): void {
	        $url = JZSA_COMMUNITY_API_URL . '/v1/entries?per_page=12&page=1&sort=newest';
	        $_POST = array( 'page' => '0' );
	        $GLOBALS['jzsa_test_http_responses'][ $url ] = $this->response( 200, array( 'entries' => array() ) );

	        $response = $this->callAjax( 'ajax_browse' );

	        $this->assertTrue( $response->success );
	        $this->assertSame( $url, $GLOBALS['jzsa_test_http_requests'][0]['url'] );
	    }
	}
