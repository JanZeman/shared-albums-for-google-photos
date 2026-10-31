<?php
/**
 * Tests for JZSA_ICloud_Provider (SPIKE): URL parsing, partition host,
 * webstream + webasseturls parsing, and the orchestrator's fixed-rendition
 * size picking. Fixtures in tests/fixtures/icloud/ follow the shape of the
 * public sharedstreams and CloudKit endpoints, so these tests never call
 * live iCloud. The HTTP stub answers by URL, so both CloudKit queries (assets
 * and posts) get the same fixture, which holds every record type.
 */

use PHPUnit\Framework\TestCase;

class ICloudProviderTest extends TestCase {

	private const ALBUM_URL = 'https://www.icloud.com/sharedalbum/#B0aGWZuqDGtB9r';
	private const BASE_URL  = 'https://p23-sharedstreams.icloud.com/B0aGWZuqDGtB9r/sharedstreams/';

	private const CLOUDKIT_ALBUM_URL = 'https://photos.icloud.com/shared/album/00TestAlbumGuid/';
	private const CLOUDKIT_RESOLVE   = 'https://ckdatabasews.icloud.com/database/1/com.apple.photos.cloud/production/public/records/resolve?remapEnums=true';
	private const CLOUDKIT_QUERY     = 'https://p65-ckdatabasews.icloud.com:443/database/1/com.apple.photos.cloud/production/shared/records/query?remapEnums=true&publicAccessAuthToken=anon%2Btoken%2F1';

	private const FIXTURE_DIR = __DIR__ . '/../fixtures/icloud';

	private JZSA_ICloud_Provider $provider;

	protected function setUp(): void {
		$this->provider                       = new JZSA_ICloud_Provider();
		$GLOBALS['jzsa_test_http_responses'] = array();
		$GLOBALS['jzsa_test_http_requests']  = array();
		$GLOBALS['jzsa_test_transients']     = array();
	}

	private function fixture( string $name ): string {
		return file_get_contents( self::FIXTURE_DIR . '/' . $name );
	}

	private function stubAlbum(): void {
		$GLOBALS['jzsa_test_http_responses'] = array(
			self::BASE_URL . 'webstream'    => array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->fixture( 'webstream.json' ),
			),
			self::BASE_URL . 'webasseturls' => array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->fixture( 'webasseturls.json' ),
			),
		);
	}

	public function test_recognizes_icloud_shared_album_urls(): void {
		$this->assertTrue( JZSA_ICloud_Provider::is_icloud_url( self::ALBUM_URL ) );
		$this->assertTrue( JZSA_ICloud_Provider::is_icloud_url( 'https://icloud.com/sharedalbum/en-us/#B0aGWZuqDGtB9r' ) );
		$this->assertTrue( JZSA_ICloud_Provider::is_icloud_url( self::CLOUDKIT_ALBUM_URL ) );
		$this->assertFalse( JZSA_ICloud_Provider::is_icloud_url( 'https://photos.google.com/share/AF1Qip?key=abc' ) );
	}

	public function test_extracts_token_from_fragment(): void {
		$this->assertSame( 'B0aGWZuqDGtB9r', $this->provider->extract_token( self::ALBUM_URL ) );
		$this->assertSame( 'B0aGWZuqDGtB9r', $this->provider->extract_token( 'https://www.icloud.com/sharedalbum/en-us/#B0aGWZuqDGtB9r' ) );
		$this->assertSame( '', $this->provider->extract_token( 'https://www.icloud.com/sharedalbum/' ) );
	}

	public function test_partition_host_is_decoded_from_token(): void {
		// B-tokens use two base62 characters after the first: "0a" = 0 * 62 + 36.
		$this->assertSame( 'https://p36-sharedstreams.icloud.com/B0aGWZuqDGtB9r/sharedstreams/', $this->provider->get_base_url( 'B0aGWZuqDGtB9r' ) );
		// A-tokens use a single character after the "A".
		$this->assertSame( 'https://p05-sharedstreams.icloud.com/A5xyz/sharedstreams/', $this->provider->get_base_url( 'A5xyz' ) );
		$this->assertSame( 'https://p23-sharedstreams.icloud.com/B0aGWZuqDGtB9r/sharedstreams/', $this->provider->get_base_url( 'B0aGWZuqDGtB9r', 'p23-sharedstreams.icloud.com' ) );
	}

	public function test_follows_partition_redirect_and_parses_album(): void {
		$this->stubAlbum();
		$GLOBALS['jzsa_test_http_responses']['https://p36-sharedstreams.icloud.com/B0aGWZuqDGtB9r/sharedstreams/webstream'] = array(
			'response' => array( 'code' => 330 ),
			'body'     => '{"X-Apple-MMe-Host":"p23-sharedstreams.icloud.com"}',
		);

		$result = $this->provider->fetch_album( self::ALBUM_URL );

		$this->assertTrue( $result['success'], (string) $result['error'] );
		$this->assertSame( 'Spike Album', $result['data']['title'] );
		$this->assertSame( 3, $result['data']['count'] );

		$photos = $result['data']['photos'];
		$this->assertSame( 'image', $photos[0]['type'] );
		$this->assertSame( 'Sunset at the lake', $photos[0]['description'] );
		$this->assertSame( 'https://cvws.icloud-content.com/S/img1-large.jpg?e=1', $photos[0]['url'] );
		$this->assertSame( array( 342, 2049 ), array_column( $photos[0]['sources'], 'width' ) );
		$this->assertSame( 4032, $photos[0]['width'] );
		$this->assertSame( strtotime( '2026-06-01T18:30:00Z' ) * 1000, $photos[0]['timestamp'] );

		$this->assertArrayNotHasKey( 'description', $photos[1] );

		$this->assertSame( 'video', $photos[2]['type'] );
		$this->assertSame( 'https://cvws.icloud-content.com/S/vid1-720.mp4?e=1', $photos[2]['video'] );
		$this->assertSame( 'https://cvws.icloud-content.com/S/vid1-poster.jpg?e=1', $photos[2]['url'] );
		$this->assertSame( 'Boat ride', $photos[2]['description'] );
	}

	public function test_requests_asset_urls_for_all_photo_guids(): void {
		$this->stubAlbum();
		$GLOBALS['jzsa_test_http_responses']['https://p36-sharedstreams.icloud.com/B0aGWZuqDGtB9r/sharedstreams/webstream'] = array(
			'response' => array( 'code' => 330 ),
			'body'     => '{"X-Apple-MMe-Host":"p23-sharedstreams.icloud.com"}',
		);

		$this->provider->fetch_album( self::ALBUM_URL );

		$last = end( $GLOBALS['jzsa_test_http_requests'] );
		$this->assertSame( self::BASE_URL . 'webasseturls', $last['url'] );
		$this->assertSame(
			array( 'photoGuids' => array( 'GUID-IMAGE-1', 'GUID-IMAGE-2', 'GUID-VIDEO-1' ) ),
			json_decode( $last['args']['body'], true )
		);
	}

	public function test_invalid_url_fails_without_http(): void {
		$result = $this->provider->fetch_album( 'https://www.icloud.com/sharedalbum/' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	}

	public function test_orchestrator_picks_fixed_renditions_instead_of_google_suffixes(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$method       = ( new ReflectionClass( $orchestrator ) )->getMethod( 'prepare_photo_urls' );

		$this->stubAlbum();
		$GLOBALS['jzsa_test_http_responses']['https://p36-sharedstreams.icloud.com/B0aGWZuqDGtB9r/sharedstreams/webstream'] = array(
			'response' => array( 'code' => 330 ),
			'body'     => '{"X-Apple-MMe-Host":"p23-sharedstreams.icloud.com"}',
		);
		$items = $this->provider->fetch_album( self::ALBUM_URL )['data']['photos'];

		$photos = $method->invoke( $orchestrator, $items, 1920, 1080, 300, 200 );

		// Expiring iCloud URLs are replaced by stable redirect URLs naming the picked rendition.
		// 400x400 thumbs do not fit the 342px rendition, so the next one up is used.
		$this->assertSame( $this->mediaUrl( 'GUID-IMAGE-1', '2049' ), $photos[0]['thumb'] );
		$this->assertSame( $this->mediaUrl( 'GUID-IMAGE-1', '342' ), $photos[0]['preview'] );
		$this->assertSame( $this->mediaUrl( 'GUID-IMAGE-1', '2049' ), $photos[0]['full'] );
		$this->assertSame( 'Sunset at the lake', $photos[0]['description'] );
		// Only a small rendition exists: it is used for every size.
		$this->assertSame( $this->mediaUrl( 'GUID-IMAGE-2', '342' ), $photos[1]['full'] );
		$this->assertSame( 'video', $photos[2]['type'] );
		$this->assertSame( $this->mediaUrl( 'GUID-VIDEO-1', '720p' ), $photos[2]['video'] );
		$this->assertStringNotContainsString( '=w', $photos[2]['full'] );
	}

	private function stubCloudKitAlbum(): void {
		$GLOBALS['jzsa_test_http_responses'] = array(
			self::CLOUDKIT_RESOLVE => array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->fixture( 'cloudkit-resolve.json' ),
			),
			self::CLOUDKIT_QUERY   => array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->fixture( 'cloudkit-query.json' ),
			),
		);
	}

	public function test_extracts_short_guid_from_ios27_album_link(): void {
		$this->assertSame( '00TestAlbumGuid', $this->provider->extract_short_guid( self::CLOUDKIT_ALBUM_URL ) );
		$this->assertSame( '', $this->provider->extract_short_guid( self::ALBUM_URL ) );
	}

	public function test_parses_cloudkit_album(): void {
		$this->stubCloudKitAlbum();

		$result = $this->provider->fetch_album( self::CLOUDKIT_ALBUM_URL );

		$this->assertTrue( $result['success'], (string) $result['error'] );
		$this->assertSame( 'CloudKit Album', $result['data']['title'] );
		// The asset whose master is missing is skipped.
		$this->assertSame( 3, $result['data']['count'] );
		$this->assertSame(
			array( 'shortGUIDs' => array( array( 'value' => '00TestAlbumGuid' ) ) ),
			json_decode( $GLOBALS['jzsa_test_http_requests'][0]['args']['body'], true )
		);
		$this->assertSame(
			array( 'CPLAssetAndMasterByAssetDateWithoutHiddenOrDeleted', 'CPLPostByPostTimestamp' ),
			array_map(
				static function ( $request ) {
					return json_decode( $request['args']['body'], true )['query']['recordType'];
				},
				array_slice( $GLOBALS['jzsa_test_http_requests'], 1 )
			)
		);

		$photos = $result['data']['photos'];
		$this->assertSame( 'image', $photos[0]['type'] );
		$this->assertSame( 'A1', $photos[0]['id'] );
		$this->assertSame( 'Sunset at the lake', $photos[0]['description'] );
		$this->assertSame( 'https://cvws.icloud-content.com/B/orig1/IMG%201.jpg?o=1', $photos[0]['url'] );
		$this->assertSame( array( 480, 1920 ), array_column( $photos[0]['sources'], 'width' ) );
		$this->assertSame( 1657907163000, $photos[0]['timestamp'] );

		// HEIC originals cannot be shown in browsers, so the JPEG renditions are used.
		$this->assertSame( array( 360, 3024 ), array_column( $photos[1]['sources'], 'width' ) );
		$this->assertSame( 'https://cvws.icloud-content.com/B/full2/IMG_2.jpg?o=1', $photos[1]['url'] );
		// Captions come from the upload post whose name is the asset's batch ID.
		$this->assertSame( 'Dog', $photos[1]['description'] );

		$this->assertSame( 'video', $photos[2]['type'] );
		// The transcoded rendition wins over the larger original.
		$this->assertSame( 'https://cvws.icloud-content.com/B/vmed3/IMG_3.mov?o=1', $photos[2]['video'] );
		$this->assertSame( 'https://cvws.icloud-content.com/B/poster3/IMG_3.jpg?o=1', $photos[2]['url'] );
		$this->assertArrayNotHasKey( 'description', $photos[2] );
	}

	public function test_cloudkit_album_without_anonymous_access_fails(): void {
		$GLOBALS['jzsa_test_http_responses'][ self::CLOUDKIT_RESOLVE ] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"results":[{"zoneID":{"zoneName":"SharedCollection-TEST"},"requireAppleLogin":true}]}',
		);

		$result = $this->provider->fetch_album( self::CLOUDKIT_ALBUM_URL );

		$this->assertFalse( $result['success'] );
		$this->assertCount( 1, $GLOBALS['jzsa_test_http_requests'] );
	}

	private function mediaUrl( string $id, string $rendition ): string {
		return 'https://site.example/?jzsa_media=1&album=' . rawurlencode( self::ALBUM_URL ) . '&id=' . $id . '&r=' . $rendition;
	}

	private function cacheCloudKitAlbum(): void {
		$GLOBALS['jzsa_test_transients'][ 'jzsa_album_' . md5( self::CLOUDKIT_ALBUM_URL ) ] = array( 'photos' => array() );
	}

	public function test_media_redirect_resolves_fresh_url_and_reuses_it(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->stubCloudKitAlbum();
		$this->cacheCloudKitAlbum();

		$url = $orchestrator->resolve_media_url( self::CLOUDKIT_ALBUM_URL, 'A1', 'JPEGThumb' );
		$this->assertSame( 'https://cvws.icloud-content.com/B/thumb1/IMG%201.jpg?o=1', $url );
		$requests = count( $GLOBALS['jzsa_test_http_requests'] );

		$this->assertSame(
			'https://cvws.icloud-content.com/B/vmed3/IMG_3.mov?o=1',
			$orchestrator->resolve_media_url( self::CLOUDKIT_ALBUM_URL, 'A3', 'VidMed' )
		);
		// The second lookup is served from the fresh URL map.
		$this->assertCount( $requests, $GLOBALS['jzsa_test_http_requests'] );

		$this->assertSame( '', $orchestrator->resolve_media_url( self::CLOUDKIT_ALBUM_URL, 'A1', 'Missing' ) );
	}

	public function test_media_redirect_refuses_albums_the_plugin_has_not_rendered(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->stubCloudKitAlbum();

		$this->assertSame( '', $orchestrator->resolve_media_url( self::CLOUDKIT_ALBUM_URL, 'A1', 'JPEGThumb' ) );
		$this->assertSame( '', $orchestrator->resolve_media_url( 'https://photos.google.com/share/AF1Qip?key=abc', 'A1', 'JPEGThumb' ) );
		$this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	}

	public function test_media_redirect_only_targets_icloud_media_hosts(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->cacheCloudKitAlbum();
		$GLOBALS['jzsa_test_transients'][ 'jzsa_media_' . md5( self::CLOUDKIT_ALBUM_URL ) ] = array(
			'A1' => array( 'JPEGThumb' => 'https://evil.example/x.jpg' ),
		);

		$this->assertSame( '', $orchestrator->resolve_media_url( self::CLOUDKIT_ALBUM_URL, 'A1', 'JPEGThumb' ) );
	}
}
