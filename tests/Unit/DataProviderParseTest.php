<?php
/**
 * Tests for JZSA_Data_Provider: URL extraction, metadata enrichment, and
 * video detection. The fixture album HTML is stored in tests/fixtures/google/
 * so these tests never call live Google Photos.
 */

use PHPUnit\Framework\TestCase;

class DataProviderParseTest extends TestCase {

	private JZSA_Data_Provider $provider;
	private static string $album_html;
	private static string $album_video_html;
	private static string $photo_image_html;
	private static string $photo_video_html;

	private const FIXTURE_DIR = __DIR__ . '/../fixtures/google';

	public static function setUpBeforeClass(): void {
		$fixtures = array(
			'album_html'       => self::FIXTURE_DIR . '/album.html',
			'album_video_html' => self::FIXTURE_DIR . '/album-video.html',
			'photo_image_html' => self::FIXTURE_DIR . '/photo-image.html',
			'photo_video_html' => self::FIXTURE_DIR . '/photo-video.html',
		);
		foreach ( $fixtures as $prop => $path ) {
			if ( ! file_exists( $path ) ) {
				self::markTestSkipped( 'Fixture not found: ' . $path );
			}
			self::$$prop = file_get_contents( $path );
		}
	}

	protected function setUp(): void {
		$this->provider = new JZSA_Data_Provider();
		// Reset any HTTP stubs from previous tests.
		$GLOBALS['jzsa_test_http_responses'] = array();
	}

	// -------------------------------------------------------------------------
	// URL validation
	// -------------------------------------------------------------------------

	public function test_validate_url_accepts_valid_full_link(): void {
		$result = $this->provider->validate_url(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['is_deprecated'] );
		$this->assertNull( $result['error'] );
	}

	public function test_validate_url_accepts_u_scoped_link(): void {
		$result = $this->provider->validate_url(
			'https://photos.google.com/u/0/share/AF1QipOg3EA51ATc'
		);
		$this->assertTrue( $result['valid'] );
	}

	public function test_validate_url_marks_short_link_as_deprecated(): void {
		$result = $this->provider->validate_url( 'https://photos.app.goo.gl/abc123' );
		$this->assertTrue( $result['valid'] );
		$this->assertNotEmpty( $result['is_deprecated'] );
	}

	public function test_validate_url_rejects_empty_string(): void {
		$result = $this->provider->validate_url( '' );
		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['error'] );
	}

	public function test_validate_url_rejects_arbitrary_url(): void {
		$result = $this->provider->validate_url( 'https://example.com/album' );
		$this->assertFalse( $result['valid'] );
	}

	// -------------------------------------------------------------------------
	// fetch_album error paths
	// -------------------------------------------------------------------------

	public function test_fetch_album_returns_error_for_invalid_url(): void {
		$result = $this->provider->fetch_album( 'https://example.com/not-google' );
		$this->assertFalse( $result['success'] );
		$this->assertNull( $result['data'] );
		$this->assertNotEmpty( $result['error'] );
	}

	public function test_fetch_album_returns_error_on_wp_error(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = new WP_Error( 'http_request_failed', 'Connection refused' );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipTest' );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Connection refused', $result['error'] );
	}

	public function test_fetch_album_returns_error_on_empty_body(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => '' );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipTest' );
		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['error'] );
	}

	public function test_fetch_album_returns_error_when_no_photos_found(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => '<html><title>Empty</title></html>' );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipTest' );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'No photos', $result['error'] );
	}

	// -------------------------------------------------------------------------
	// fetch_album against fixture - high-level
	// -------------------------------------------------------------------------

	public function test_fetch_album_fixture_returns_success(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['success'], 'fetch_album should succeed on fixture HTML' );
		$this->assertIsArray( $result['data'] );
	}

	public function test_fetch_album_fixture_extracts_title(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertSame( 'Photo Album Sample', $result['data']['title'] );
	}

	public function test_fetch_album_fixture_extracts_44_photos(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertSame( 44, $result['data']['count'] );
		$this->assertCount( 44, $result['data']['photos'] );
	}

	public function test_fetch_album_fixture_no_videos_detected(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$videos = array_filter( $result['data']['photos'], static function ( $item ) {
			return is_array( $item ) && ( $item['type'] ?? '' ) === 'video';
		} );
		$this->assertCount( 0, $videos, 'No items should be flagged as video' );
	}

	// -------------------------------------------------------------------------
	// URL quality: base URLs have no dimension suffix
	// -------------------------------------------------------------------------

	public function test_fetch_album_fixture_urls_have_no_equals_suffix(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		foreach ( $result['data']['photos'] as $item ) {
			$url = is_array( $item ) ? $item['url'] : $item;
			$this->assertStringNotContainsString( '=', substr( $url, strpos( $url, '/pw/' ) ?: 0 ),
				'Base URLs must not contain a =w or =s dimension suffix' );
		}
	}

	public function test_fetch_album_fixture_all_urls_are_googleusercontent(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		foreach ( $result['data']['photos'] as $item ) {
			$url = is_array( $item ) ? $item['url'] : $item;
			$this->assertStringContainsString( 'googleusercontent.com', $url );
		}
	}

	// -------------------------------------------------------------------------
	// Metadata enrichment (Stage 0): first photo
	// -------------------------------------------------------------------------

	public function test_fetch_album_fixture_first_photo_has_metadata(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$first = $result['data']['photos'][0];
		$this->assertIsArray( $first, 'First photo should be enriched to an array' );
		$this->assertArrayHasKey( 'id', $first );
		$this->assertArrayHasKey( 'width', $first );
		$this->assertArrayHasKey( 'height', $first );
		$this->assertArrayHasKey( 'filesize', $first );
		$this->assertArrayHasKey( 'timestamp', $first );
	}

	public function test_fetch_album_fixture_first_photo_dimensions_are_correct(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$first = $result['data']['photos'][0];
		$this->assertSame( 1351, $first['width'] );
		$this->assertSame( 2020, $first['height'] );
	}

	public function test_fetch_album_fixture_first_photo_filesize_is_correct(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$first = $result['data']['photos'][0];
		$this->assertSame( 7043475, $first['filesize'] );
	}

	public function test_fetch_album_fixture_first_photo_id_starts_with_AF1Qip(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$first = $result['data']['photos'][0];
		$this->assertStringStartsWith( 'AF1Qip', $first['id'] );
	}

	public function test_fetch_album_fixture_first_photo_timestamp_is_numeric(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$first = $result['data']['photos'][0];
		$this->assertIsInt( $first['timestamp'] );
		$this->assertGreaterThan( 0, $first['timestamp'] );
	}

	// -------------------------------------------------------------------------
	// Video album fixture (album-video.html)
	// -------------------------------------------------------------------------

	public function test_fetch_video_album_fixture_returns_success(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_video_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB'
		);
		$this->assertTrue( $result['success'], 'Video album fetch should succeed' );
	}

	public function test_fetch_video_album_fixture_extracts_title(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_video_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB'
		);
		$this->assertSame( 'Video Album Sample', $result['data']['title'] );
	}

	public function test_fetch_video_album_fixture_extracts_24_items(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_video_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB'
		);
		$this->assertSame( 24, $result['data']['count'] );
	}

	public function test_fetch_video_album_fixture_detects_11_videos(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_video_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB'
		);
		$videos = array_filter( $result['data']['photos'], static function ( $item ) {
			return is_array( $item ) && ( $item['type'] ?? '' ) === 'video';
		} );
		$this->assertSame( 11, count( $videos ) );
	}

	public function test_fetch_video_album_fixture_first_item_is_video(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_video_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB'
		);
		$first = $result['data']['photos'][0];
		$this->assertIsArray( $first );
		$this->assertSame( 'video', $first['type'] );
	}

	public function test_fetch_video_album_fixture_first_video_has_metadata(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_video_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB'
		);
		$first = $result['data']['photos'][0];
		$this->assertSame( 'AF1QipNRG8bLyakrC6GbVjgvXtCqxEDBNoNyxNXlRvmR', $first['id'] );
		$this->assertSame( 1920, $first['width'] );
		$this->assertSame( 1080, $first['height'] );
		$this->assertSame( 14804968, $first['filesize'] );
	}

	// -------------------------------------------------------------------------
	// Individual photo page - image fixture (photo-image.html)
	// -------------------------------------------------------------------------

	public function test_extract_media_urls_from_image_photo_page(): void {
		$urls = $this->provider->extract_individual_photo_media_urls( self::$photo_image_html );
		$this->assertArrayHasKey( 'image', $urls );
		$this->assertStringContainsString( 'googleusercontent.com', $urls['image'] );
		$this->assertArrayNotHasKey( 'video', $urls );
	}

	public function test_image_photo_page_url_does_not_contain_escape(): void {
		$urls = $this->provider->extract_individual_photo_media_urls( self::$photo_image_html );
		$this->assertArrayHasKey( 'image', $urls );
		$this->assertStringNotContainsString( '\\u003d', $urls['image'],
			'Escape sequences should be decoded in the returned URL' );
	}

	// -------------------------------------------------------------------------
	// Individual photo page - video fixture (photo-video.html)
	// -------------------------------------------------------------------------

	public function test_extract_media_urls_from_video_photo_page(): void {
		$urls = $this->provider->extract_individual_photo_media_urls( self::$photo_video_html );
		$this->assertArrayHasKey( 'video', $urls );
		$this->assertStringContainsString( 'video-downloads.googleusercontent.com', $urls['video'] );
	}

	// -------------------------------------------------------------------------
	// Video detection with synthetic HTML
	// -------------------------------------------------------------------------

	public function test_video_detection_via_VIDEO_marker(): void {
		$url     = 'https://lh3.googleusercontent.com/pw/VIDEOtest001';
		$url2    = 'https://lh3.googleusercontent.com/pw/VIDEOtest002';
		$html    = '<title>Test - Google Photos</title><script>'
			. '"' . $url . '",1920,1080,"VIDEO","some-other-stuff"'
			. '"' . $url2 . '",1920,1080'
			. '</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipVIDEOtest' );

		if ( ! $result['success'] ) {
			$this->markTestSkipped( 'Synthetic HTML did not yield photos: ' . $result['error'] );
		}

		$videos = array_filter( $result['data']['photos'], static function ( $item ) {
			return is_array( $item ) && ( $item['type'] ?? '' ) === 'video';
		} );
		$this->assertGreaterThanOrEqual( 1, count( $videos ), '"VIDEO" marker should trigger video detection' );
	}

	public function test_video_detection_via_video_mp4_marker(): void {
		$url  = 'https://lh3.googleusercontent.com/pw/MP4test001';
		$url2 = 'https://lh3.googleusercontent.com/pw/MP4test002';
		$html = '<title>Test - Google Photos</title><script>'
			. '"' . $url . '",1920,1080,"video/mp4","some-data"'
			. '"' . $url2 . '",1920,1080'
			. '</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipMP4test' );

		if ( ! $result['success'] ) {
			$this->markTestSkipped( 'Synthetic HTML did not yield photos: ' . $result['error'] );
		}

		$videos = array_filter( $result['data']['photos'], static function ( $item ) {
			return is_array( $item ) && ( $item['type'] ?? '' ) === 'video';
		} );
		$this->assertGreaterThanOrEqual( 1, count( $videos ), '"video/mp4" marker should trigger video detection' );
	}

	// -------------------------------------------------------------------------
	// Fallback URL extraction (no dimension pattern)
	// -------------------------------------------------------------------------

	public function test_fallback_extraction_when_no_dimension_pattern(): void {
		$url  = 'https://lh3.googleusercontent.com/pw/fallback001';
		$html = '<title>Fallback - Google Photos</title><script>["' . $url . '"]</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipFallback' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['data']['count'] );
		$photos = $result['data']['photos'];
		$first  = is_array( $photos[0] ) ? $photos[0]['url'] : $photos[0];
		$this->assertSame( $url, $first );
	}

	// -------------------------------------------------------------------------
	// Title extraction edge cases
	// -------------------------------------------------------------------------

	public function test_title_extraction_strips_google_photos_suffix(): void {
		$html = '<html><title>My Vacation - Google Photos</title></html>'
			. '<script>"https://lh3.googleusercontent.com/pw/edge001",100,200</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipEdge001' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'My Vacation', $result['data']['title'] );
	}

	public function test_title_falls_back_to_og_title_when_no_title_tag(): void {
		$html = '<html>'
			. '<meta property="og:title" content="Fallback Title - Google Photos"/>'
			. '<script>"https://lh3.googleusercontent.com/pw/ogtitle001",100,200</script>'
			. '</html>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipOgTitle' );

		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['data']['title'] );
	}

	// -------------------------------------------------------------------------
	// Camera display name formatting
	// -------------------------------------------------------------------------

	public function test_format_camera_display_name_deduplicates_make(): void {
		$name = $this->provider->format_camera_display_name( 'NIKON CORPORATION', 'NIKON D90' );
		$this->assertSame( 'NIKON D90', $name );
	}

	public function test_format_camera_display_name_concatenates_when_no_overlap(): void {
		$name = $this->provider->format_camera_display_name( 'Canon', 'EOS 5D' );
		$this->assertSame( 'Canon EOS 5D', $name );
	}

	public function test_format_camera_display_name_handles_empty_make(): void {
		$name = $this->provider->format_camera_display_name( '', 'iPhone 14' );
		$this->assertSame( 'iPhone 14', $name );
	}

	public function test_format_camera_display_name_handles_empty_model(): void {
		$name = $this->provider->format_camera_display_name( 'samsung', '' );
		$this->assertSame( 'Samsung', $name );
	}

	// -------------------------------------------------------------------------
	// Individual photo meta extraction
	// -------------------------------------------------------------------------

	public function test_extract_individual_photo_meta_returns_empty_on_empty_html(): void {
		$meta = $this->provider->extract_individual_photo_meta( '' );
		$this->assertIsArray( $meta );
		$this->assertEmpty( $meta );
	}

	public function test_extract_individual_photo_meta_parses_exif(): void {
		// Minimal individual photo page structure mirroring Google Photos format.
		$html = '[4032,3024,1,null,["Canon","EOS 90D",null,50.0,2.8,400,0.0025,null,1]]';
		$meta = $this->provider->extract_individual_photo_meta( $html );

		$this->assertArrayHasKey( 'camera', $meta );
		$this->assertArrayHasKey( 'aperture', $meta );
		$this->assertArrayHasKey( 'shutter', $meta );
		$this->assertArrayHasKey( 'focal', $meta );
		$this->assertArrayHasKey( 'iso', $meta );
		$this->assertSame( 'ISO400', $meta['iso'] );
		$this->assertStringContainsString( 'mm', $meta['focal'] );
	}

	public function test_extract_individual_photo_media_urls_returns_image_url(): void {
		$base = 'https://lh3.googleusercontent.com/pw/individual_img';
		$html = '"' . $base . '=s0-d-ip"';
		$urls = $this->provider->extract_individual_photo_media_urls( $html );
		$this->assertArrayHasKey( 'image', $urls );
		$this->assertStringContainsString( 'lh3.googleusercontent.com', $urls['image'] );
	}
}
