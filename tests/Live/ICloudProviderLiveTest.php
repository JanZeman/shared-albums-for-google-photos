<?php
/**
 * Live tests: call real Apple iCloud to detect format changes.
 *
 * Excluded from the normal PHPUnit run, like DataProviderLiveTest. Run them
 * manually to verify that Apple has not changed its shared album interface:
 *
 *   vendor/bin/phpunit tests/Live/ICloudProviderLiveTest.php
 *
 * They use the public Guide sample albums, so these must stay shared with
 * Public Website turned on. Requirements: an active internet connection.
 */

use PHPUnit\Framework\TestCase;

class ICloudProviderLiveTest extends TestCase {

	private const PHOTO_ALBUM_URL = 'https://photos.icloud.com/shared/album/0c63FLTi0WVdod42GljS2Mmgg';
	private const VIDEO_ALBUM_URL = 'https://photos.icloud.com/shared/album/0e7wlLKC7IuNKrCWXiTJgCAyQ';
	private const INFO_ALBUM_URL  = 'https://photos.icloud.com/shared/album/0c7OcXdjceb4X2lbpETJkDVrw';

	/** @var array<string, array> Albums fetched once per run. */
	private static array $albums = array();

	public static function setUpBeforeClass(): void {
		$GLOBALS['jzsa_test_http_handler'] = array( self::class, 'realRequest' );
	}

	public static function tearDownAfterClass(): void {
		unset( $GLOBALS['jzsa_test_http_handler'] );
	}

	/**
	 * Perform a real HTTP request in the shape wp_remote_* returns.
	 */
	public static function realRequest( string $method, string $url, array $args ): array|WP_Error {
		$headers = array();
		foreach ( (array) ( $args['headers'] ?? array() ) as $name => $value ) {
			$headers[] = $name . ': ' . $value;
		}
		$context = stream_context_create(
			array(
				'http' => array(
					'method'        => $method,
					'header'        => implode( "\r\n", $headers ),
					'content'       => (string) ( $args['body'] ?? '' ),
					'timeout'       => 30,
					'ignore_errors' => true,
				),
			)
		);
		$body = @file_get_contents( $url, false, $context );
		if ( false === $body ) {
			return new WP_Error( 'http_request_failed', 'Request failed for ' . $url );
		}
		$code = 0;
		foreach ( $http_response_header ?? array() as $line ) {
			if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $m ) ) {
				$code = (int) $m[1];
			}
		}
		return array(
			'response' => array( 'code' => $code ),
			'body'     => $body,
		);
	}

	private static function album( string $url ): array {
		if ( ! isset( self::$albums[ $url ] ) ) {
			self::$albums[ $url ] = ( new JZSA_ICloud_Provider() )->fetch_album( $url );
		}
		return self::$albums[ $url ];
	}

	public static function albumUrls(): array {
		return array(
			'photos' => array( self::PHOTO_ALBUM_URL ),
			'videos' => array( self::VIDEO_ALBUM_URL ),
			'info'   => array( self::INFO_ALBUM_URL ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'albumUrls' )]
	public function test_live_album_loads_with_items( string $url ): void {
		$result = self::album( $url );

		$this->assertTrue( $result['success'], 'Live fetch failed: ' . ( $result['error'] ?? 'unknown error' ) );
		$this->assertNotSame( '', $result['data']['title'] );
		$this->assertGreaterThan( 0, $result['data']['count'] );
		foreach ( $result['data']['photos'] as $item ) {
			$this->assertMatchesRegularExpression( '#^https://[^/]+\.icloud-content\.com/#', $item['url'] );
			$this->assertGreaterThan( 0, $item['width'] );
			$this->assertGreaterThan( 0, $item['height'] );
		}
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'albumUrls' )]
	public function test_live_album_never_contains_owner_details( string $url ): void {
		$json = wp_json_encode( self::album( $url ) );

		// The resolve response names the album owner; it must stay on the server.
		$this->assertDoesNotMatchRegularExpression( '#[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}#', $json );
		$this->assertStringNotContainsString( 'ownerIdentity', $json );
	}

	public function test_live_album_comes_newest_first_with_dates_in_milliseconds(): void {
		$photos     = self::album( self::INFO_ALBUM_URL )['data']['photos'];
		$timestamps = array_values( array_filter( array_column( $photos, 'timestamp' ) ) );

		$this->assertNotEmpty( $timestamps );
		foreach ( $timestamps as $timestamp ) {
			$this->assertGreaterThan( 946684800000, $timestamp, 'Timestamps must be in milliseconds' );
		}
		// Apple returns shared albums newest first; the Guide documents this.
		$sorted = $timestamps;
		rsort( $sorted );
		$this->assertSame( $sorted, $timestamps );
	}

	public function test_live_info_album_has_camera_info(): void {
		$photos = self::album( self::INFO_ALBUM_URL )['data']['photos'];

		$with_camera = array_filter( $photos, static fn( $item ) => ! empty( $item['camera'] ) && ! empty( $item['exif'] ) );
		$this->assertNotEmpty( $with_camera, 'At least one photo must have camera info' );
	}

	public function test_live_video_album_has_playable_videos(): void {
		$photos = self::album( self::VIDEO_ALBUM_URL )['data']['photos'];

		$videos = array_filter( $photos, static fn( $item ) => 'video' === ( $item['type'] ?? '' ) );
		$this->assertNotEmpty( $videos );
		foreach ( $videos as $video ) {
			$this->assertMatchesRegularExpression( '#^https://[^/]+\.icloud-content\.com/#', $video['video'] );
		}
	}
}
