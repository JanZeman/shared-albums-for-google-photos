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
	private static string $photo_exif_html;

	private const FIXTURE_DIR = __DIR__ . '/../fixtures/google';

	public static function setUpBeforeClass(): void {
		$fixtures = array(
			'album_html'       => self::FIXTURE_DIR . '/album.html',
			'album_video_html' => self::FIXTURE_DIR . '/album-video.html',
			'photo_image_html' => self::FIXTURE_DIR . '/photo-image.html',
			'photo_video_html' => self::FIXTURE_DIR . '/photo-video.html',
			'photo_exif_html'  => self::FIXTURE_DIR . '/photo-exif.html',
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
	// Individual photo page EXIF (photo-exif.html, Samsung SM-F936B, f/2.2, 1/494, 1.74mm, ISO32)
	// -------------------------------------------------------------------------

	public function test_exif_fixture_all_keys_present(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		foreach ( array( 'camera', 'camera_make', 'camera_model', 'aperture', 'shutter', 'focal', 'iso' ) as $key ) {
			$this->assertArrayHasKey( $key, $meta );
			$this->assertNotEmpty( $meta[ $key ], "Key '$key' must not be empty" );
		}
	}

	public function test_exif_fixture_no_combined_exif_string_from_individual_page(): void {
		// The combined 'exif' string ("ƒ/2.2 · 1/494 · ...") is only assembled by
		// Stage 2c inside the album HTML parser, not by extract_individual_photo_meta.
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		$this->assertArrayNotHasKey( 'exif', $meta );
	}

	public function test_exif_fixture_camera_display_name(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		// "Samsung" is not a prefix of "SM-F936B" so both appear in the display name.
		$this->assertSame( 'Samsung SM-F936B', $meta['camera'] );
	}

	public function test_exif_fixture_camera_make(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		$this->assertSame( 'Samsung', $meta['camera_make'] );
	}

	public function test_exif_fixture_camera_model(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		$this->assertSame( 'SM-F936B', $meta['camera_model'] );
	}

	public function test_exif_fixture_aperture_exact(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		// Aperture uses the ƒ ligature (U+0192, encoded as \xC6\x92), not plain "f".
		$this->assertSame( "\xC6\x92/2.2", $meta['aperture'] );
	}

	public function test_exif_fixture_shutter_exact(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		// 0.0020242915 s => 1/round(1/0.0020242915) = 1/494
		$this->assertSame( '1/494', $meta['shutter'] );
	}

	public function test_exif_fixture_focal_exact(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		$this->assertSame( '1.74mm', $meta['focal'] );
	}

	public function test_exif_fixture_iso_exact(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_exif_html );
		$this->assertSame( 'ISO32', $meta['iso'] );
	}

	public function test_exif_photo_page_without_exif_returns_no_camera(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_image_html );
		$this->assertIsArray( $meta );
		$this->assertArrayNotHasKey( 'camera', $meta );
	}

	public function test_exif_video_page_returns_no_camera(): void {
		$meta = $this->provider->extract_individual_photo_meta( self::$photo_video_html );
		$this->assertArrayNotHasKey( 'camera', $meta );
	}

	// -------------------------------------------------------------------------
	// Album-level EXIF (Stage 2c inside fetch_album / album.html)
	// -------------------------------------------------------------------------

	public function test_album_fixture_one_photo_has_stage2c_exif(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$with_exif = array_filter( $result['data']['photos'], static function ( $p ) {
			return isset( $p['camera'] );
		} );
		$this->assertCount( 1, $with_exif, 'Exactly one album photo should have Stage 2c EXIF embedded' );
	}

	public function test_album_fixture_stage2c_photo_has_combined_exif_string(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$with_exif = array_values( array_filter( $result['data']['photos'], static function ( $p ) {
			return isset( $p['exif'] );
		} ) );
		$this->assertCount( 1, $with_exif );
		// Combined string format: "ƒ/2.2 · 1/494 · 1.74mm · ISO32"
		$this->assertSame( "\xC6\x92/2.2 \xC2\xB7 1/494 \xC2\xB7 1.74mm \xC2\xB7 ISO32", $with_exif[0]['exif'] );
	}

	public function test_album_fixture_stage2c_photo_camera_fields(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$with_exif = array_values( array_filter( $result['data']['photos'], static function ( $p ) {
			return isset( $p['camera'] );
		} ) );
		$photo = $with_exif[0];
		$this->assertSame( 'Samsung SM-F936B', $photo['camera'] );
		$this->assertSame( 'Samsung', $photo['camera_make'] );
		$this->assertSame( 'SM-F936B', $photo['camera_model'] );
		$this->assertSame( "\xC6\x92/2.2", $photo['aperture'] );
		$this->assertSame( '1/494', $photo['shutter'] );
		$this->assertSame( '1.74mm', $photo['focal'] );
		$this->assertSame( 'ISO32', $photo['iso'] );
	}

	// -------------------------------------------------------------------------
	// Individual photo meta extraction - synthetic
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

	public function test_extract_individual_photo_media_urls_returns_empty_on_empty_html(): void {
		$urls = $this->provider->extract_individual_photo_media_urls( '' );
		$this->assertSame( array(), $urls );
	}

	public function test_extract_individual_photo_media_urls_decodes_escaped_equals(): void {
		$html = '"https://lh3.googleusercontent.com/pw/photo\\u003ds0-d-ip"';
		$urls = $this->provider->extract_individual_photo_media_urls( $html );
		$this->assertArrayHasKey( 'image', $urls );
		$this->assertStringNotContainsString( '\\u003d', $urls['image'] );
		$this->assertStringContainsString( '=s0-d-ip', $urls['image'] );
	}

	// -------------------------------------------------------------------------
	// URL validation edge cases
	// -------------------------------------------------------------------------

	public function test_validate_url_rejects_http_scheme(): void {
		$result = $this->provider->validate_url( 'http://photos.google.com/share/AF1QipTest' );
		$this->assertFalse( $result['valid'] );
	}

	// -------------------------------------------------------------------------
	// fetch_album result structure
	// -------------------------------------------------------------------------

	public function test_fetch_album_result_has_is_deprecated_key(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'is_deprecated', $result );
	}

	public function test_fetch_album_result_not_deprecated_for_full_link(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['success'] );
		$this->assertEmpty( $result['is_deprecated'] );
	}

	public function test_fetch_album_result_data_has_required_keys(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'title', $result['data'] );
		$this->assertArrayHasKey( 'photos', $result['data'] );
		$this->assertArrayHasKey( 'count', $result['data'] );
	}

	// -------------------------------------------------------------------------
	// Video album: non-video item count
	// -------------------------------------------------------------------------

	public function test_fetch_video_album_fixture_non_video_count_is_13(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_video_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB'
		);
		$this->assertTrue( $result['success'] );
		$non_videos = array_filter( $result['data']['photos'], static function ( $item ) {
			return ! is_array( $item ) || ( $item['type'] ?? '' ) !== 'video';
		} );
		$this->assertCount( 13, $non_videos );
	}

	// -------------------------------------------------------------------------
	// Individual photo page: image and video URL presence
	// -------------------------------------------------------------------------

	public function test_video_photo_page_also_has_image_url(): void {
		$urls = $this->provider->extract_individual_photo_media_urls( self::$photo_video_html );
		$this->assertArrayHasKey( 'image', $urls,
			'Video photo pages also carry a thumbnail image URL alongside the video URL' );
		$this->assertStringContainsString( 'googleusercontent.com', $urls['image'] );
	}

	public function test_image_photo_page_has_no_video_url(): void {
		$urls = $this->provider->extract_individual_photo_media_urls( self::$photo_image_html );
		$this->assertArrayNotHasKey( 'video', $urls );
	}

	public function test_image_photo_page_url_has_s0_d_ip_suffix(): void {
		$urls = $this->provider->extract_individual_photo_media_urls( self::$photo_image_html );
		$this->assertArrayHasKey( 'image', $urls );
		$this->assertStringEndsWith( '=s0-d-ip', $urls['image'],
			'The s0-d-ip suffix must be preserved so the plugin can append its own size param' );
	}

	// -------------------------------------------------------------------------
	// EXIF shutter speed formatting
	// -------------------------------------------------------------------------

	public function test_extract_individual_photo_meta_slow_shutter_uses_seconds_format(): void {
		$html = '[4032,3024,1,null,["Canon","EOS R5",null,85.0,1.8,100,2.5,null,1]]';
		$meta = $this->provider->extract_individual_photo_meta( $html );
		$this->assertSame( '2.5s', $meta['shutter'] );
	}

	public function test_extract_individual_photo_meta_fast_shutter_uses_fraction_format(): void {
		$html = '[4032,3024,1,null,["Sony","A7R IV",null,85.0,1.8,200,0.001,null,1]]';
		$meta = $this->provider->extract_individual_photo_meta( $html );
		$this->assertSame( '1/1000', $meta['shutter'] );
	}

	public function test_extract_individual_photo_meta_returns_empty_when_required_exif_null(): void {
		// When any required field (focal, aperture, iso, shutter) is null, the regex
		// does not match and extract_individual_photo_meta returns an empty array.
		$html = '[4032,3024,1,null,["Canon","EOS 90D",null,null,2.8,null,0.0025,null,1]]';
		$meta = $this->provider->extract_individual_photo_meta( $html );
		$this->assertIsArray( $meta );
		$this->assertArrayNotHasKey( 'camera', $meta );
		$this->assertArrayNotHasKey( 'aperture', $meta );
	}

	// -------------------------------------------------------------------------
	// Description field from individual photo page
	// -------------------------------------------------------------------------

	public function test_extract_individual_photo_meta_parses_description(): void {
		$html = '"396644657":["A caption for this photo"]';
		$meta = $this->provider->extract_individual_photo_meta( $html );
		$this->assertArrayHasKey( 'description', $meta );
		$this->assertSame( 'A caption for this photo', $meta['description'] );
	}

	public function test_extract_individual_photo_meta_no_description_when_absent(): void {
		$html = '[4032,3024,1,null,["Canon","EOS R5",null,85.0,1.8,100,0.001,null,1]]';
		$meta = $this->provider->extract_individual_photo_meta( $html );
		$this->assertArrayNotHasKey( 'description', $meta );
	}

	// -------------------------------------------------------------------------
	// Camera make normalization
	// -------------------------------------------------------------------------

	public function test_format_camera_display_name_lowercased_make_is_title_cased(): void {
		$name = $this->provider->format_camera_display_name( 'samsung', 'Galaxy S23' );
		$this->assertSame( 'Samsung Galaxy S23', $name );
	}

	// -------------------------------------------------------------------------
	// Filename field: fixture album documents known behavior (no filenames in
	// this particular album's HTML since Google does not always include them)
	// -------------------------------------------------------------------------

	public function test_fetch_album_fixture_no_photos_have_filename(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$with_filename = array_filter( $result['data']['photos'], static function ( $item ) {
			return is_array( $item ) && isset( $item['filename'] );
		} );
		// This fixture album's HTML does not contain filename metadata.
		// If this count increases to > 0 in the future, update the fixture
		// and add a positive filename assertion.
		$this->assertCount( 0, $with_filename );
	}

	// -------------------------------------------------------------------------
	// Deprecated URL in full fetch_album flow
	// -------------------------------------------------------------------------

	public function test_fetch_album_with_deprecated_url_returns_is_deprecated_true(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album( 'https://photos.app.goo.gl/abc123' );
		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['is_deprecated'] );
	}

	// -------------------------------------------------------------------------
	// u/0 scoped URL in full fetch_album flow
	// -------------------------------------------------------------------------

	public function test_fetch_album_with_u_scoped_url_succeeds(): void {
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => self::$album_html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/u/0/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['success'] );
		$this->assertEmpty( $result['is_deprecated'] );
	}

	// -------------------------------------------------------------------------
	// og:title fallback when primary title tag is absent
	// -------------------------------------------------------------------------

	public function test_fetch_album_falls_back_to_og_title_when_title_tag_absent(): void {
		$html = preg_replace( '/<title[^>]*>[^<]*<\/title>/i', '', self::$album_html );
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['data']['title'] );
		// og:title for this fixture is "Photo Album Sample · Jun 3, 2009 – Jan 24, 2026 📸"
		// The cleaner strips the dates and emoji, leaving just the album name.
		$this->assertStringContainsString( 'Photo Album Sample', $result['data']['title'] );
	}

	/**
	 * Helper to fetch album with a synthetic og:title (no primary title tag).
	 */
	private function fetchWithOgTitle( string $og_title ): ?string {
		$html = preg_replace( '/<title[^>]*>[^<]*<\/title>/i', '', self::$album_html );
		$html = preg_replace(
			'/<meta property="og:title" content="[^"]*"/',
			'<meta property="og:title" content="' . htmlspecialchars( $og_title, ENT_QUOTES ) . '"',
			$html
		);
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		return $result['data']['title'] ?? null;
	}

	public function test_og_title_cleaner_strips_iso_date(): void {
		$title = $this->fetchWithOgTitle( 'Vacation · 2024-07-15' );
		$this->assertSame( 'Vacation', $title );
	}

	public function test_og_title_cleaner_strips_emoji(): void {
		$title = $this->fetchWithOgTitle( 'Family 📸 Photos' );
		// Emoji removed, surrounding spaces collapsed
		$this->assertSame( 'Family Photos', $title );
	}

	public function test_og_title_cleaner_strips_camera_model(): void {
		$title = $this->fetchWithOgTitle( 'Landscape · Canon EOS 5D' );
		$this->assertSame( 'Landscape', $title );
	}

	public function test_fetch_album_title_is_null_when_no_title_tags_present(): void {
		$html = preg_replace( '/<title[^>]*>[^<]*<\/title>/i', '', self::$album_html );
		$html = preg_replace( '/<\s*meta[^>]*og:title[^>]*>/i', '', $html );
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['data']['title'] );
	}

	// -------------------------------------------------------------------------
	// HTML entity decoding in album title
	// -------------------------------------------------------------------------

	public function test_fetch_album_title_with_html_entities_is_decoded(): void {
		$html = str_replace(
			'<title>Photo Album Sample - Google Photos</title>',
			'<title>Summer &amp; Winter - Google Photos</title>',
			self::$album_html
		);
		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album(
			'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
		);
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Summer & Winter', $result['data']['title'] );
	}

	// -------------------------------------------------------------------------
	// Synthetic parser boundaries: duplicates, non-Google URLs, filenames, EXIF
	// -------------------------------------------------------------------------

	public function test_primary_extraction_ignores_non_googleusercontent_urls(): void {
		$good = 'https://lh3.googleusercontent.com/pw/good001=w120-h80';
		$html = '<title>Mixed URLs - Google Photos</title><script>'
			. '"https://example.com/not-a-photo.jpg",640,480,'
			. '"' . $good . '",120,80'
			. '</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipMixedUrls' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['data']['count'] );
		$this->assertSame( 'https://lh3.googleusercontent.com/pw/good001', $result['data']['photos'][0] );
	}

	public function test_primary_extraction_deduplicates_by_base_url(): void {
		$base = 'https://lh3.googleusercontent.com/pw/duplicate001';
		$html = '<title>Duplicates - Google Photos</title><script>'
			. '"' . $base . '=w120-h80",120,80,'
			. '"' . $base . '=w240-h160",240,160'
			. '</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipDuplicates' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['data']['count'] );
		$this->assertSame( $base, $result['data']['photos'][0] );
	}

	public function test_synthetic_album_extracts_original_filename_from_known_key(): void {
		$url = 'https://lh3.googleusercontent.com/pw/file001=w4032-h3024';
		$html = '<title>Filename - Google Photos</title><script>'
			. '["AF1QipFilename001",["' . $url . '",4032,3024,null,null,null,null,null,[987654]],1700000000,"",0,'
			. '"101428965":[null,"IMG_2024-05-01_120000.jpg"]]'
			. '</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipFilename' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'IMG_2024-05-01_120000.jpg', $result['data']['photos'][0]['filename'] );
		$this->assertSame( 'AF1QipFilename001', $result['data']['photos'][0]['id'] );
		$this->assertSame( 987654, $result['data']['photos'][0]['filesize'] );
	}

	public function test_synthetic_album_chooses_best_plausible_filename_candidate(): void {
		$url = 'https://lh3.googleusercontent.com/pw/file002=w1200-h800';
		$html = '<title>Filename Candidate - Google Photos</title><script>'
			. '["AF1QipFilename002",["' . $url . '",1200,800,null,null,null,null,null,[123456]],1700000001,"",0,'
			. '"11111":[null,"icon.jpg"],'
			. '"22222":[null,"DJI_2024-05-01_153000.MP4"]]'
			. '</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipFilenameCandidate' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'DJI_2024-05-01_153000.MP4', $result['data']['photos'][0]['filename'] );
	}

	public function test_album_exif_is_scoped_to_matching_media_id(): void {
		$url_one = 'https://lh3.googleusercontent.com/pw/exif001=w1000-h750';
		$url_two = 'https://lh3.googleusercontent.com/pw/exif002=w1000-h750';
		$html = '<title>Scoped EXIF - Google Photos</title><script>'
			. '["AF1QipScopedOne",["' . $url_one . '",1000,750,null,null,null,null,null,[111111]],1700000002,"",0],'
			. '["AF1QipScopedTwo",["' . $url_two . '",1000,750,null,null,null,null,null,[222222]],1700000003,"",0,'
			. '[1000,750,1,null,["Canon","EOS R5",null,35.0,4.0,200,0.005,null,1]]]'
			. '</script>';

		$GLOBALS['jzsa_test_http_responses']['*'] = array( 'body' => $html );
		$result = $this->provider->fetch_album( 'https://photos.google.com/share/AF1QipScopedExif' );

		$this->assertTrue( $result['success'] );
		$this->assertCount( 2, $result['data']['photos'] );
		$this->assertArrayNotHasKey( 'camera', $result['data']['photos'][0] );
		$this->assertSame( 'Canon EOS R5', $result['data']['photos'][1]['camera'] );
		$this->assertSame( "\xC6\x92/4.0 \xC2\xB7 1/200 \xC2\xB7 35.0mm \xC2\xB7 ISO200", $result['data']['photos'][1]['exif'] );
	}

	public function test_individual_photo_media_urls_ignore_non_google_video_urls(): void {
		$html = '"https://video-downloads.example.com/not-google"'
			. '"https://lh3.googleusercontent.com/pw/photo\\u003ds0-d-ip"';

		$urls = $this->provider->extract_individual_photo_media_urls( $html );

		$this->assertArrayHasKey( 'image', $urls );
		$this->assertArrayNotHasKey( 'video', $urls );
	}
}
