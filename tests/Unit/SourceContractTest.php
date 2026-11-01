<?php
/**
 * Contract shared by every album source. Google Photos and iCloud albums are
 * parsed from their own fixtures, and both must hand the renderer the same
 * album and item shape, so every layout, order and placeholder works the same
 * way for both. A new source should be added to sourceAlbums().
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceContractTest extends TestCase {

	private const GOOGLE_DIR = __DIR__ . '/../fixtures/google';
	private const ICLOUD_DIR = __DIR__ . '/../fixtures/icloud';

	// Dates from 2000 to 2100, in milliseconds.
	private const MIN_TIMESTAMP = 946684800000;
	private const MAX_TIMESTAMP = 4102444800000;

	// Camera fields that the {camera}, {aperture}, ... placeholders read.
	private const EXIF_KEYS = array( 'camera', 'camera_make', 'camera_model', 'aperture', 'shutter', 'focal', 'iso' );

	protected function setUp(): void {
		$GLOBALS['jzsa_test_http_responses'] = array();
		$GLOBALS['jzsa_test_http_requests']  = array();
		$GLOBALS['jzsa_test_transients']     = array();
	}

	private static function googleAlbum( string $fixture ): array {
		$GLOBALS['jzsa_test_http_responses'] = array(
			'*' => array( 'body' => file_get_contents( self::GOOGLE_DIR . '/' . $fixture ) ),
		);
		return ( new JZSA_Data_Provider() )->fetch_album( 'https://photos.google.com/share/AF1QipTest?key=abc' );
	}

	private static function icloudAlbum(): array {
		$GLOBALS['jzsa_test_http_responses'] = array(
			'https://ckdatabasews.icloud.com/database/1/com.apple.photos.cloud/production/public/records/resolve?remapEnums=true' => array(
				'response' => array( 'code' => 200 ),
				'body'     => file_get_contents( self::ICLOUD_DIR . '/cloudkit-resolve.json' ),
			),
			'https://p65-ckdatabasews.icloud.com:443/database/1/com.apple.photos.cloud/production/shared/records/query?remapEnums=true&publicAccessAuthToken=anon%2Btoken%2F1' => array(
				'response' => array( 'code' => 200 ),
				'body'     => file_get_contents( self::ICLOUD_DIR . '/cloudkit-query.json' ),
			),
		);
		return ( new JZSA_ICloud_Provider() )->fetch_album( 'https://photos.icloud.com/shared/album/00TestAlbumGuid/' );
	}

	/**
	 * Fetch inside the test, because the HTTP stubs are reset in setUp().
	 */
	public static function sourceAlbums(): array {
		return array(
			'google photos'       => array( 'google', 'album.html' ),
			'google photos video' => array( 'google', 'album-video.html' ),
			'icloud'              => array( 'icloud', '' ),
		);
	}

	private static function fetch( string $source, string $fixture ): array {
		return 'google' === $source ? self::googleAlbum( $fixture ) : self::icloudAlbum();
	}

	#[DataProvider( 'sourceAlbums' )]
	public function test_album_has_title_photos_and_count( string $source, string $fixture ): void {
		$result = self::fetch( $source, $fixture );

		$this->assertTrue( $result['success'], (string) ( $result['error'] ?? '' ) );
		$this->assertIsString( $result['data']['title'] );
		$this->assertNotSame( '', $result['data']['title'] );
		$this->assertIsArray( $result['data']['photos'] );
		$this->assertNotEmpty( $result['data']['photos'] );
		$this->assertSame( array_keys( $result['data']['photos'] ), range( 0, count( $result['data']['photos'] ) - 1 ), 'photos must be a list' );
		$this->assertSame( count( $result['data']['photos'] ), $result['data']['count'] );
	}

	#[DataProvider( 'sourceAlbums' )]
	public function test_every_item_has_the_shared_fields( string $source, string $fixture ): void {
		$photos = self::fetch( $source, $fixture )['data']['photos'];
		$ids    = array();

		foreach ( $photos as $index => $item ) {
			$where = $source . ' item ' . $index;
			$this->assertMatchesRegularExpression( '#^https://#', $item['url'], $where . ' url' );
			$this->assertIsString( $item['id'], $where . ' id' );
			$this->assertNotSame( '', $item['id'], $where . ' id' );
			$ids[] = $item['id'];
			$this->assertIsInt( $item['width'], $where . ' width' );
			$this->assertIsInt( $item['height'], $where . ' height' );
			$this->assertGreaterThan( 0, $item['width'], $where . ' width' );
			$this->assertGreaterThan( 0, $item['height'], $where . ' height' );
			$this->assertContains( $item['type'] ?? 'image', array( 'image', 'video' ), $where . ' type' );

			// order="oldest"/"newest" compare timestamps across sources, so the unit must match.
			if ( isset( $item['timestamp'] ) ) {
				$this->assertIsInt( $item['timestamp'], $where . ' timestamp' );
				$this->assertGreaterThanOrEqual( self::MIN_TIMESTAMP, $item['timestamp'], $where . ' timestamp must be in milliseconds' );
				$this->assertLessThan( self::MAX_TIMESTAMP, $item['timestamp'], $where . ' timestamp must be in milliseconds' );
			}
		}
		$this->assertSame( $ids, array_unique( $ids ), $source . ' ids must be unique' );
	}

	public function test_both_sources_mark_videos_the_same_way(): void {
		$google = array_filter( self::googleAlbum( 'album-video.html' )['data']['photos'], static fn( $item ) => 'video' === ( $item['type'] ?? '' ) );
		$icloud = array_filter( self::icloudAlbum()['data']['photos'], static fn( $item ) => 'video' === ( $item['type'] ?? '' ) );

		$this->assertNotEmpty( $google );
		$this->assertNotEmpty( $icloud );
	}

	public function test_camera_info_uses_the_same_fields_and_formats(): void {
		$google = ( new JZSA_Data_Provider() )->extract_individual_photo_meta( file_get_contents( self::GOOGLE_DIR . '/photo-exif.html' ) );
		$icloud = self::icloudAlbum()['data']['photos'][0];

		foreach ( array( 'google' => $google, 'icloud' => $icloud ) as $source => $meta ) {
			foreach ( self::EXIF_KEYS as $key ) {
				$this->assertArrayHasKey( $key, $meta, $source . ' ' . $key );
				$this->assertNotSame( '', $meta[ $key ], $source . ' ' . $key );
			}
			$this->assertMatchesRegularExpression( '#^\xC6\x92/\d+(\.\d+)?$#', $meta['aperture'], $source . ' aperture' );
			$this->assertMatchesRegularExpression( '#^(1/\d+|\d+(\.\d+)?s)$#', $meta['shutter'], $source . ' shutter' );
			$this->assertMatchesRegularExpression( '#^\d+(\.\d+)?mm$#', $meta['focal'], $source . ' focal' );
			$this->assertMatchesRegularExpression( '#^ISO\d+$#', $meta['iso'], $source . ' iso' );
		}
	}

	public function test_date_order_mixes_sources_consistently(): void {
		$items = array_merge(
			array_slice( self::googleAlbum( 'album.html' )['data']['photos'], 0, 5 ),
			self::icloudAlbum()['data']['photos']
		);

		$sorted     = JZSA_Shared_Albums::sort_by_date( $items, 'oldest' );
		$timestamps = array_column( $sorted, 'timestamp' );
		$ascending  = $timestamps;
		sort( $ascending );
		$this->assertSame( $ascending, $timestamps );
	}
}
