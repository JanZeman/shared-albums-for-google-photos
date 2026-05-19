<?php
/**
 * Live tests: call real Google Photos to detect format changes.
 *
 * These tests are intentionally excluded from the normal PHPUnit run.
 * Run them manually when you want to verify that Google has not changed
 * its response format:
 *
 *   vendor/bin/phpunit tests/Live/
 *
 * They are also useful to refresh fixture files when the format changes:
 * copy the raw HTML from a passing test output into tests/fixtures/google/album.html.
 *
 * Requirements: an active internet connection and the album must still be shared.
 */

use PHPUnit\Framework\TestCase;

class DataProviderLiveTest extends TestCase {

	private const ALBUM_URL       = 'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R';
	private const VIDEO_ALBUM_URL = 'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB';

	private JZSA_Data_Provider $provider;

	protected function setUp(): void {
		// Override wp_remote_get stub with a real HTTP call.
		$GLOBALS['jzsa_test_http_responses'] = array();
		$GLOBALS['jzsa_test_http_responses']['*'] = $this->fetchLive( self::ALBUM_URL );

		$this->provider = new JZSA_Data_Provider();
	}

	private function fetchLive( string $url ): array|WP_Error {
		$context = stream_context_create( array(
			'http' => array(
				'timeout'    => 30,
				'user_agent' => 'Mozilla/5.0 (compatible; PHPUnit live test)',
				'header'     => "Accept: text/html,application/xhtml+xml\r\n",
			),
			'ssl' => array(
				'verify_peer' => false,
			),
		) );

		$body = @file_get_contents( $url, false, $context );
		if ( false === $body ) {
			return new WP_Error( 'http_request_failed', 'file_get_contents failed for ' . $url );
		}
		return array( 'body' => $body );
	}

	public function test_live_album_can_be_fetched(): void {
		$result = $this->provider->fetch_album( self::ALBUM_URL );
		$this->assertTrue( $result['success'], 'Live fetch failed: ' . ( $result['error'] ?? 'unknown error' ) );
	}

	public function test_live_album_returns_title(): void {
		$result = $this->provider->fetch_album( self::ALBUM_URL );
		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['data']['title'] );
	}

	public function test_live_album_returns_photos(): void {
		$result = $this->provider->fetch_album( self::ALBUM_URL );
		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, $result['data']['count'], 'Album must contain at least one photo' );
	}

	public function test_live_album_urls_are_googleusercontent(): void {
		$result = $this->provider->fetch_album( self::ALBUM_URL );
		$this->assertTrue( $result['success'] );
		foreach ( $result['data']['photos'] as $item ) {
			$url = is_array( $item ) ? $item['url'] : $item;
			$this->assertStringContainsString( 'googleusercontent.com', $url,
				'All photo URLs must point to googleusercontent.com' );
		}
	}

	public function test_live_album_metadata_is_present_on_at_least_one_photo(): void {
		$result = $this->provider->fetch_album( self::ALBUM_URL );
		$this->assertTrue( $result['success'] );

		$enriched = array_filter( $result['data']['photos'], static function ( $item ) {
			return is_array( $item ) && isset( $item['id'] );
		} );
		$this->assertGreaterThan( 0, count( $enriched ),
			'At least one photo must be enriched with id/width/height metadata' );
	}

	// -------------------------------------------------------------------------
	// Video album live tests
	// -------------------------------------------------------------------------

	public function test_live_video_album_can_be_fetched(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = $this->fetchLive( self::VIDEO_ALBUM_URL );
		$result = $this->provider->fetch_album( self::VIDEO_ALBUM_URL );
		$this->assertTrue( $result['success'], 'Live video album fetch failed: ' . ( $result['error'] ?? 'unknown' ) );
	}

	public function test_live_video_album_contains_videos(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = $this->fetchLive( self::VIDEO_ALBUM_URL );
		$result = $this->provider->fetch_album( self::VIDEO_ALBUM_URL );
		$this->assertTrue( $result['success'] );

		$videos = array_filter( $result['data']['photos'], static function ( $item ) {
			return is_array( $item ) && ( $item['type'] ?? '' ) === 'video';
		} );
		$this->assertGreaterThan( 0, count( $videos ),
			'Video album must contain at least one item detected as video' );
	}
}
