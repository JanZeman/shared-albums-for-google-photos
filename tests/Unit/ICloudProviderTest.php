<?php
/**
 * Tests for JZSA_ICloud_Provider: URL parsing, partition host,
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
		$this->assertSame( 'Test Album', $result['data']['title'] );
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
		$this->assertSame( 'IMG 1.jpg', $photos[0]['filename'] );
		// The HEIC original is not offered, so the name follows the JPEG rendition.
		$this->assertSame( 'IMG_2.jpg', $photos[1]['filename'] );
		$this->assertSame( 'IMG_3.mov', $photos[2]['filename'] );
		// Captions come from the upload post whose name is the asset's batch ID.
		$this->assertSame( 'Dog', $photos[1]['description'] );

		$this->assertSame( 'video', $photos[2]['type'] );
		// The transcoded rendition wins over the larger original.
		$this->assertSame( 'https://cvws.icloud-content.com/B/vmed3/IMG_3.mov?o=1', $photos[2]['video'] );
		$this->assertSame( 'https://cvws.icloud-content.com/B/poster3/IMG_3.jpg?o=1', $photos[2]['url'] );
		$this->assertArrayNotHasKey( 'description', $photos[2] );
	}

	public function test_cloudkit_album_is_read_across_result_pages(): void {
		$full    = json_decode( $this->fixture( 'cloudkit-query.json' ), true );
		$records = $full['records'];
		$page    = static function ( array $records, string $marker = '' ): array {
			$body = array( 'records' => $records );
			if ( '' !== $marker ) {
				$body['continuationMarker'] = $marker;
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( $body ),
			);
		};
		$GLOBALS['jzsa_test_http_responses'] = array(
			self::CLOUDKIT_RESOLVE => array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->fixture( 'cloudkit-resolve.json' ),
			),
			// Assets in two pages, then the caption posts in one.
			self::CLOUDKIT_QUERY   => array(
				'sequence' => array(
					$page( array_slice( $records, 0, 2 ), 'PAGE-2' ),
					$page( array_slice( $records, 2 ) ),
					$page( $records ),
				),
			),
		);

		$result = $this->provider->fetch_album( self::CLOUDKIT_ALBUM_URL );

		$this->assertTrue( $result['success'], (string) $result['error'] );
		$this->assertSame( array( 'A1', 'A2', 'A3' ), array_column( $result['data']['photos'], 'id' ) );
		$queries = array_map(
			static function ( $request ) {
				return json_decode( $request['args']['body'], true );
			},
			array_slice( $GLOBALS['jzsa_test_http_requests'], 1 )
		);
		$this->assertCount( 3, $queries );
		$this->assertArrayNotHasKey( 'continuationMarker', $queries[0] );
		$this->assertSame( 'PAGE-2', $queries[1]['continuationMarker'] );
		$this->assertSame( 'CPLPostByPostTimestamp', $queries[2]['query']['recordType'] );
	}

	public function test_camera_info_comes_from_the_original_image_properties(): void {
		$this->stubCloudKitAlbum();

		$photos = $this->provider->fetch_album( self::CLOUDKIT_ALBUM_URL )['data']['photos'];

		$this->assertSame( 'Canon EOS 5D Mark II', $photos[0]['camera'] );
		$this->assertSame( 'Canon', $photos[0]['camera_make'] );
		$this->assertSame( "\xC6\x92/5.6", $photos[0]['aperture'] );
		$this->assertSame( '1/320', $photos[0]['shutter'] );
		$this->assertSame( '400mm', $photos[0]['focal'] );
		$this->assertSame( 'ISO250', $photos[0]['iso'] );
		$this->assertSame( "\xC6\x92/5.6 \xC2\xB7 1/320 \xC2\xB7 400mm \xC2\xB7 ISO250", $photos[0]['exif'] );
		// Location and the other image properties never reach the page.
		$this->assertStringNotContainsString( '55.6761', wp_json_encode( $photos ) );
		// Without image properties there is no camera info.
		$this->assertArrayNotHasKey( 'camera', $photos[1] );
	}

	public function test_binary_plist_parser_rejects_malformed_data(): void {
		$this->assertNull( JZSA_ICloud_Provider::parse_binary_plist( '' ) );
		$this->assertNull( JZSA_ICloud_Provider::parse_binary_plist( 'bplist00' . str_repeat( "\xFF", 40 ) ) );
		$this->assertNull( JZSA_ICloud_Provider::parse_binary_plist( str_repeat( 'x', 64 ) ) );
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
			'urls'        => array( 'A1' => array( 'JPEGThumb' => 'https://evil.example/x.jpg' ) ),
			'fresh_until' => time() + 600,
		);

		$this->assertSame( '', $orchestrator->resolve_media_url( self::CLOUDKIT_ALBUM_URL, 'A1', 'JPEGThumb' ) );
	}

	private function callMediaUrls( JZSA_Shared_Albums $orchestrator, array $get ): \JZSA_Test_JSON_Response {
		$_GET = $get;
		try {
			$orchestrator->handle_media_urls();
		} catch ( \JZSA_Test_JSON_Response $response ) {
			return $response;
		} finally {
			$_GET = array();
		}
		$this->fail( 'Expected handle_media_urls to send a JSON response.' );
	}

	public function test_media_urls_answer_the_requested_renditions_of_a_rendered_album(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->cacheCloudKitAlbum();
		$expiry = time() + 900;
		$GLOBALS['jzsa_test_transients'][ 'jzsa_media_' . md5( self::CLOUDKIT_ALBUM_URL ) ] = array(
			'urls'        => array(
				'A1' => array(
					'JPEGThumb' => 'https://cvws.icloud-content.com/B/t1/a.jpg?o=1&e=' . $expiry,
					'JPEGMed'   => 'https://cvws.icloud-content.com/B/m1/a.jpg?o=1&e=' . $expiry,
					'Original'  => 'https://cvws.icloud-content.com/B/o1/a.jpg?o=1&e=' . ( $expiry - 300 ),
				),
				'A2' => array( 'JPEGMed' => 'https://evil.example/x.jpg' ),
			),
			'fresh_until' => time() + 600,
		);

		$response = $this->callMediaUrls( $orchestrator, array( 'album' => self::CLOUDKIT_ALBUM_URL, 'r' => 'JPEGMed,JPEGThumb' ) );

		$this->assertTrue( $response->success );
		$this->assertEquals(
			(object) array(
				'A1' => array(
					'JPEGThumb' => 'https://cvws.icloud-content.com/B/t1/a.jpg?o=1&e=' . $expiry,
					'JPEGMed'   => 'https://cvws.icloud-content.com/B/m1/a.jpg?o=1&e=' . $expiry,
				),
			),
			$response->data['urls']
		);
		// Lifetime of the earliest returned URL, minus the safety buffer; the Original is not returned.
		$this->assertEqualsWithDelta( 900 - JZSA_Shared_Albums::MEDIA_URL_EXPIRY_BUFFER, $response->data['ttl'], 2 );
	}

	public function test_media_urls_refuse_albums_the_plugin_has_not_rendered(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->stubCloudKitAlbum();

		$this->assertFalse( $this->callMediaUrls( $orchestrator, array( 'album' => self::CLOUDKIT_ALBUM_URL, 'r' => 'JPEGMed' ) )->success );
		$this->assertFalse( $this->callMediaUrls( $orchestrator, array( 'album' => 'https://photos.google.com/share/AF1Qip?key=abc', 'r' => 'JPEGMed' ) )->success );
		$this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
	}

	public function test_media_urls_need_renditions(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->cacheCloudKitAlbum();

		$this->assertFalse( $this->callMediaUrls( $orchestrator, array( 'album' => self::CLOUDKIT_ALBUM_URL, 'r' => ',%' ) )->success );
	}

	public function test_rotated_original_reports_displayed_dimensions(): void {
		$records = array(
			array(
				'recordName' => 'M1',
				'recordType' => 'CPLMaster',
				'fields'     => array(
					'resOriginalRes'      => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/orig/${f}' ) ),
					'resOriginalFileType' => array( 'value' => 'public.jpeg' ),
					'resOriginalWidth'    => array( 'value' => 4032 ),
					'resOriginalHeight'   => array( 'value' => 3024 ),
				),
			),
			array(
				'recordName' => 'A1',
				'recordType' => 'CPLAsset',
				'fields'     => array(
					'masterRef'   => array( 'value' => array( 'recordName' => 'M1' ) ),
					'orientation' => array( 'value' => 6 ),
				),
			),
		);

		$photos = $this->provider->build_cloudkit_items( $records );

		$this->assertSame( array( 3024, 4032 ), array( $photos[0]['width'], $photos[0]['height'] ) );
	}

	public function test_a_photo_edited_in_photos_uses_its_rendered_edit(): void {
		$records = array(
			array(
				'recordName' => 'M1',
				'recordType' => 'CPLMaster',
				'fields'     => array(
					'filenameEnc'         => array( 'value' => base64_encode( '20261010_124125.jpg' ) ),
					'resOriginalRes'      => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/orig/${f}' ) ),
					'resOriginalFileType' => array( 'value' => 'public.jpeg' ),
					'resOriginalWidth'    => array( 'value' => 3024 ),
					'resOriginalHeight'   => array( 'value' => 3024 ),
				),
			),
			array(
				'recordName' => 'A1',
				'recordType' => 'CPLAsset',
				'fields'     => array(
					'masterRef'            => array( 'value' => array( 'recordName' => 'M1' ) ),
					'adjustmentType'       => array( 'value' => 'com.apple.photo' ),
					'resJPEGFullRes'       => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/full/${f}' ) ),
					'resJPEGFullFileType'  => array( 'value' => 'public.jpeg' ),
					'resJPEGFullWidth'     => array( 'value' => 1565 ),
					'resJPEGFullHeight'    => array( 'value' => 1565 ),
					'resJPEGThumbRes'      => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/thumb/${f}' ) ),
					'resJPEGThumbFileType' => array( 'value' => 'public.jpeg' ),
					'resJPEGThumbWidth'    => array( 'value' => 414 ),
					'resJPEGThumbHeight'   => array( 'value' => 414 ),
				),
			),
		);

		$photos = $this->provider->build_cloudkit_items( $records );

		// The original is the unedited photo (here upside down), so only the edit is offered.
		$this->assertStringStartsWith( 'https://cvws.icloud-content.com/B/full/', $photos[0]['url'] );
		$this->assertSame( array( 'JPEGThumb', 'JPEGFull' ), array_column( $photos[0]['sources'], 'rendition' ) );
		$this->assertSame( array( 1565, 1565 ), array( $photos[0]['width'], $photos[0]['height'] ) );
	}

	public function test_an_avi_video_without_web_renditions_is_a_flagged_still(): void {
		$records = array(
			array(
				'recordName' => 'M1',
				'recordType' => 'CPLMaster',
				'fields'     => array(
					'filenameEnc'          => array( 'value' => base64_encode( 'MVI_6384.AVI' ), 'type' => 'ENCRYPTED_BYTES' ),
					'resOriginalRes'       => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/orig/${f}' ) ),
					'resOriginalFileType'  => array( 'value' => 'public.avi' ),
					'resOriginalWidth'     => array( 'value' => 640 ),
					'resOriginalHeight'    => array( 'value' => 480 ),
					'resJPEGMedRes'        => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/med/${f}' ) ),
					'resJPEGMedFileType'   => array( 'value' => 'public.jpeg' ),
					'resJPEGMedWidth'      => array( 'value' => 640 ),
					'resJPEGMedHeight'     => array( 'value' => 480 ),
				),
			),
			array(
				'recordName' => 'M2',
				'recordType' => 'CPLMaster',
				'fields'     => array(
					'filenameEnc'         => array( 'value' => base64_encode( 'IMG_0001.HEIC' ) ),
					'resJPEGMedRes'       => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/med2/${f}' ) ),
					'resJPEGMedFileType'  => array( 'value' => 'public.jpeg' ),
					'resJPEGMedWidth'     => array( 'value' => 640 ),
					'resJPEGMedHeight'    => array( 'value' => 480 ),
					'resOriginalFileType' => array( 'value' => 'public.heic' ),
				),
			),
			array(
				'recordName' => 'A1',
				'recordType' => 'CPLAsset',
				'fields'     => array( 'masterRef' => array( 'value' => array( 'recordName' => 'M1' ) ) ),
			),
			array(
				'recordName' => 'A2',
				'recordType' => 'CPLAsset',
				'fields'     => array( 'masterRef' => array( 'value' => array( 'recordName' => 'M2' ) ) ),
			),
		);

		$photos = $this->provider->build_cloudkit_items( $records );

		$this->assertSame( 'image', $photos[0]['type'] );
		$this->assertSame( 'MVI_6384.AVI', $photos[0]['unplayable_video'] );
		$this->assertArrayNotHasKey( 'unplayable_video', $photos[1] );
	}

	public function test_live_photo_is_a_still_photo_and_a_real_video_stays_a_video(): void {
		$master = static function ( $name, $original_type, $width ) {
			$fields = array(
				'filenameEnc'         => array( 'value' => base64_encode( $name ) ),
				'resOriginalRes'      => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/orig/${f}' ) ),
				'resOriginalFileType' => array( 'value' => $original_type ),
				'resOriginalWidth'    => array( 'value' => $width ),
				'resOriginalHeight'   => array( 'value' => 1080 ),
				'resJPEGThumbRes'     => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/thumb/${f}' ) ),
				'resJPEGThumbFileType' => array( 'value' => 'public.jpeg' ),
				'resJPEGThumbWidth'   => array( 'value' => 360 ),
				'resJPEGThumbHeight'  => array( 'value' => 270 ),
				'resVidMedRes'        => array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/vid/${f}' ) ),
				'resVidMedFileType'   => array( 'value' => 'com.apple.quicktime-movie' ),
				'resVidMedWidth'      => array( 'value' => 1280 ),
				'resVidMedHeight'     => array( 'value' => 720 ),
			);
			if ( 'public.jpeg' === $original_type ) {
				// Live Photos also carry the paired movie of the original.
				$fields['resOriginalVidComplRes']      = array( 'value' => array( 'downloadURL' => 'https://cvws.icloud-content.com/B/compl/${f}' ) );
				$fields['resOriginalVidComplFileType'] = array( 'value' => 'com.apple.quicktime-movie' );
			}
			return $fields;
		};
		$records = array(
			array( 'recordName' => 'M1', 'recordType' => 'CPLMaster', 'fields' => $master( 'IMG_1394.JPG', 'public.jpeg', 1440 ) ),
			array( 'recordName' => 'M2', 'recordType' => 'CPLMaster', 'fields' => $master( 'rafting.mp4', 'public.mpeg-4', 1920 ) ),
			array( 'recordName' => 'A1', 'recordType' => 'CPLAsset', 'fields' => array( 'masterRef' => array( 'value' => array( 'recordName' => 'M1' ) ) ) ),
			array( 'recordName' => 'A2', 'recordType' => 'CPLAsset', 'fields' => array( 'masterRef' => array( 'value' => array( 'recordName' => 'M2' ) ) ) ),
		);

		$items = $this->provider->build_cloudkit_items( $records );

		$this->assertSame( 'image', $items[0]['type'] );
		$this->assertArrayNotHasKey( 'video', $items[0] );
		$this->assertStringStartsWith( 'https://cvws.icloud-content.com/B/orig/', $items[0]['url'] );
		$this->assertSame( 'video', $items[1]['type'] );
		$this->assertSame( 'VidMed', $items[1]['video_rendition'] );
	}

	public function test_download_proxy_resolves_stable_media_urls(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->stubCloudKitAlbum();
		$this->cacheCloudKitAlbum();

		$stable = 'https://site.example/?jzsa_media=1&album=' . rawurlencode( self::CLOUDKIT_ALBUM_URL ) . '&id=A1&r=Original';
		$this->assertSame( 'https://cvws.icloud-content.com/B/orig1/IMG%201.jpg?o=1', $orchestrator->resolve_stable_media_url( $stable ) );

		// Other URLs, including look-alikes on other hosts, pass through unchanged.
		$google = 'https://lh3.googleusercontent.com/abc=d';
		$this->assertSame( $google, $orchestrator->resolve_stable_media_url( $google ) );
		$foreign = 'https://evil.example/?jzsa_media=1&album=x&id=A1&r=Original';
		$this->assertSame( $foreign, $orchestrator->resolve_stable_media_url( $foreign ) );
	}

	public function test_media_url_lifetime_keeps_a_buffer_before_apple_expiry(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$method       = ( new ReflectionClass( $orchestrator ) )->getMethod( 'media_url_lifetime' );

		$lifetime = $method->invoke( $orchestrator, 'https://cvws.icloud-content.com/B/x/a.jpg?o=1&e=' . ( time() + 900 ) );
		$this->assertEqualsWithDelta( 720, $lifetime, 2 );
		$this->assertSame( 0, $method->invoke( $orchestrator, 'https://cvws.icloud-content.com/B/x/a.jpg?e=' . ( time() + 60 ) ) );
		$this->assertSame( 480, $method->invoke( $orchestrator, 'https://cvws.icloud-content.com/B/x/a.jpg?o=1' ) );
	}

	public function test_icloud_fetch_errors_do_not_mention_google(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );

		$html = $orchestrator->handle_shortcode( array( 'link' => 'https://photos.icloud.com/shared/album/' ) );

		$this->assertStringContainsString( 'Invalid iCloud Album URL', $html );
		$this->assertStringNotContainsString( 'Google', $html );
	}

	public function test_stale_media_urls_are_served_while_another_request_refreshes(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->cacheCloudKitAlbum();
		$map_key = 'jzsa_media_' . md5( self::CLOUDKIT_ALBUM_URL );
		$GLOBALS['jzsa_test_transients'][ $map_key ] = array(
			'urls'        => array( 'A1' => array( 'JPEGThumb' => 'https://cvws.icloud-content.com/B/old/a.jpg' ) ),
			'fresh_until' => time() - 10,
		);
		add_option( $map_key . '_lock', time() );

		$this->assertSame( 'https://cvws.icloud-content.com/B/old/a.jpg', $orchestrator->resolve_media_url( self::CLOUDKIT_ALBUM_URL, 'A1', 'JPEGThumb' ) );
		$this->assertSame( array(), $GLOBALS['jzsa_test_http_requests'] );
		delete_option( $map_key . '_lock' );
	}

	public function test_stale_media_map_refresh_skips_captions_and_reuses_the_resolved_token(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->stubCloudKitAlbum();
		$this->cacheCloudKitAlbum();
		$this->provider->fetch_album( self::CLOUDKIT_ALBUM_URL );
		$GLOBALS['jzsa_test_http_requests'] = array();
		$GLOBALS['jzsa_test_transients'][ 'jzsa_media_' . md5( self::CLOUDKIT_ALBUM_URL ) ] = array(
			'urls'        => array(),
			'fresh_until' => time() - 10,
		);

		$url = $orchestrator->resolve_media_url( self::CLOUDKIT_ALBUM_URL, 'A1', 'JPEGThumb' );

		$this->assertSame( 'https://cvws.icloud-content.com/B/thumb1/IMG%201.jpg?o=1', $url );
		// One asset query: no resolve (token reused), no caption query.
		$this->assertCount( 1, $GLOBALS['jzsa_test_http_requests'] );
		$this->assertSame( self::CLOUDKIT_QUERY, $GLOBALS['jzsa_test_http_requests'][0]['url'] );
	}
}
