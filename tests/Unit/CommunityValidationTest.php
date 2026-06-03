<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Community;

/**
 * Tests for JZSA_Community private validation helpers.
 *
 * All tested methods are private static, accessed via ReflectionClass.
 */
class CommunityValidationTest extends TestCase {

    private ReflectionClass $reflection;

    /** A minimal valid [jzsa-album] shortcode with a real-pattern Google Photos URL. */
    private const VALID_SC = '[jzsa-album link="https://photos.google.com/share/AF1QipTest123"]';

    protected function setUp(): void {
        $this->reflection = new ReflectionClass( JZSA_Community::class );
    }

    private function callStatic( string $method, mixed ...$args ): mixed {
        $m = $this->reflection->getMethod( $method );
        return $m->invoke( null, ...$args );
    }

    // -------------------------------------------------------------------------
    // validate_community_entry_payload: helpers
    // -------------------------------------------------------------------------

	    private function validate(
	        string $title             = 'Valid Title',
	        string $shortcode         = self::VALID_SC,
	        string $description       = '',
	        string $tags_raw          = '',
	        string $entry_url         = '',
	        string $photographer_bio  = '',
	        bool   $consent           = false
	    ): string {
	        return $this->callStatic(
	            'validate_community_entry_payload',
	            $title, $shortcode, $description, $tags_raw, $entry_url,
	            $photographer_bio, $consent
	        );
	    }

    // -------------------------------------------------------------------------
    // validate_community_entry_payload: happy path
    // -------------------------------------------------------------------------

    public function test_valid_minimal_payload_returns_no_error(): void {
        $result = $this->validate();
        $this->assertSame( '', $result );
    }

    public function test_valid_payload_with_all_fields_filled(): void {
        $result = $this->validate(
	            title:             'A great album',
	            description:       'Some description text',
	            tags_raw:          'landscape, travel',
	            entry_url:         'https://example.com/page',
	            photographer_bio:  'Landscape photographer',
	            consent:           true
	        );
        $this->assertSame( '', $result );
    }

    // -------------------------------------------------------------------------
    // Title validation
    // -------------------------------------------------------------------------

    public function test_title_too_short_2_chars_fails(): void {
        $result = $this->validate( title: 'AB' );
        $this->assertNotSame( '', $result );
        $this->assertStringContainsString( '3', $result );
    }

    public function test_title_exactly_3_chars_passes(): void {
        $result = $this->validate( title: 'ABC' );
        $this->assertSame( '', $result );
    }

    public function test_title_empty_fails(): void {
        $result = $this->validate( title: '' );
        $this->assertNotSame( '', $result );
    }

    public function test_title_exactly_120_chars_passes(): void {
        $result = $this->validate( title: str_repeat( 'a', 120 ) );
        $this->assertSame( '', $result );
    }

    public function test_title_121_chars_fails(): void {
        $result = $this->validate( title: str_repeat( 'a', 121 ) );
        $this->assertNotSame( '', $result );
        $this->assertStringContainsString( '120', $result );
    }

    // -------------------------------------------------------------------------
    // Shortcode validation
    // -------------------------------------------------------------------------

    public function test_shortcode_too_short_fails(): void {
        $result = $this->validate( shortcode: '[jzsa]' );
        $this->assertNotSame( '', $result );
    }

    public function test_shortcode_empty_fails(): void {
        $result = $this->validate( shortcode: '' );
        $this->assertNotSame( '', $result );
    }

    public function test_shortcode_without_google_photos_link_fails(): void {
        $result = $this->validate( shortcode: '[jzsa-album link="https://example.com/not-photos"]' );
        $this->assertNotSame( '', $result );
    }

    public function test_valid_shortcode_with_query_string_passes(): void {
        $sc     = '[jzsa-album link="https://photos.google.com/share/AF1Qip_test?key=ABC123"]';
        $result = $this->validate( shortcode: $sc );
        $this->assertSame( '', $result );
    }

    public function test_shortcode_over_2000_chars_fails(): void {
        $long = '[jzsa-album link="https://photos.google.com/share/AF1Qip" ' . str_repeat( 'x="y" ', 500 ) . ']';
        $result = $this->validate( shortcode: $long );
        $this->assertNotSame( '', $result );
    }

    public function test_shortcode_without_jzsa_album_tag_fails(): void {
        $result = $this->validate( shortcode: '[other-tag link="https://photos.google.com/share/AF1Qip"]' );
        $this->assertNotSame( '', $result );
    }

    // -------------------------------------------------------------------------
    // Description validation
    // -------------------------------------------------------------------------

    public function test_description_500_chars_passes(): void {
        $result = $this->validate( description: str_repeat( 'a', 500 ) );
        $this->assertSame( '', $result );
    }

    public function test_description_501_chars_fails(): void {
        $result = $this->validate( description: str_repeat( 'a', 501 ) );
        $this->assertNotSame( '', $result );
        $this->assertStringContainsString( '500', $result );
    }

    public function test_description_empty_passes(): void {
        $result = $this->validate( description: '' );
        $this->assertSame( '', $result );
    }

    // -------------------------------------------------------------------------
    // Tags validation
    // -------------------------------------------------------------------------

    public function test_five_tags_passes(): void {
        $result = $this->validate( tags_raw: 'aaa, bbb, ccc, ddd, eee' );
        $this->assertSame( '', $result );
    }

    public function test_six_tags_fails(): void {
        $result = $this->validate( tags_raw: 'aaa, bbb, ccc, ddd, eee, fff' );
        $this->assertNotSame( '', $result );
        $this->assertStringContainsString( '5', $result );
    }

    public function test_empty_tags_passes(): void {
        $result = $this->validate( tags_raw: '' );
        $this->assertSame( '', $result );
    }

    public function test_tag_exactly_2_chars_passes(): void {
        $result = $this->validate( tags_raw: 'ab' );
        $this->assertSame( '', $result );
    }

    public function test_tag_1_char_fails(): void {
        $result = $this->validate( tags_raw: 'a' );
        $this->assertNotSame( '', $result );
    }

    public function test_tag_30_chars_passes(): void {
        $result = $this->validate( tags_raw: str_repeat( 'a', 30 ) );
        $this->assertSame( '', $result );
    }

    public function test_tag_31_chars_fails(): void {
        $result = $this->validate( tags_raw: str_repeat( 'a', 31 ) );
        $this->assertNotSame( '', $result );
    }

    public function test_tag_with_hyphens_passes(): void {
        $result = $this->validate( tags_raw: 'my-tag' );
        $this->assertSame( '', $result );
    }

    public function test_tag_starting_with_hyphen_fails(): void {
        $result = $this->validate( tags_raw: '-mytag' );
        $this->assertNotSame( '', $result );
    }

    public function test_tag_with_spaces_fails(): void {
        $result = $this->validate( tags_raw: 'my tag' );
        // "my tag" after normalization splits on comma so becomes one tag "my tag" with a space.
        $this->assertNotSame( '', $result );
    }

    public function test_tags_with_empty_segments_are_ignored(): void {
        $result = $this->validate( tags_raw: 'landscape,, travel, ' );
        $this->assertSame( '', $result );
    }

    public function test_tags_are_normalized_to_lowercase_trimmed_values(): void {
        $tags = $this->callStatic( 'normalize_community_tags', ' Landscape,TRAVEL , night-shots ' );
        $this->assertSame( array( 'landscape', 'travel', 'night-shots' ), $tags );
    }

    public function test_duplicate_tags_count_toward_maximum(): void {
        $result = $this->validate( tags_raw: 'one,one,two,three,four,five' );
        $this->assertNotSame( '', $result );
        $this->assertStringContainsString( '5', $result );
    }

    // -------------------------------------------------------------------------
    // Entry URL validation
    // -------------------------------------------------------------------------

    public function test_empty_entry_url_passes(): void {
        $result = $this->validate( entry_url: '' );
        $this->assertSame( '', $result );
    }

    public function test_valid_https_entry_url_passes(): void {
        $result = $this->validate( entry_url: 'https://example.com/gallery' );
        $this->assertSame( '', $result );
    }

    public function test_valid_http_entry_url_passes(): void {
        $result = $this->validate( entry_url: 'http://example.com/gallery' );
        $this->assertSame( '', $result );
    }

    public function test_ftp_url_fails(): void {
        $result = $this->validate( entry_url: 'ftp://example.com/file' );
        $this->assertNotSame( '', $result );
    }

    public function test_url_without_dot_in_host_fails(): void {
        $result = $this->validate( entry_url: 'https://localhost/page' );
        $this->assertNotSame( '', $result );
    }

    public function test_invalid_url_format_fails(): void {
        $result = $this->validate( entry_url: 'not-a-url' );
        $this->assertNotSame( '', $result );
    }

    public function test_https_url_with_query_and_fragment_passes(): void {
        $result = $this->validate( entry_url: 'https://example.com/gallery?album=1#photo-2' );
        $this->assertSame( '', $result );
    }

    public function test_javascript_entry_url_fails(): void {
        $result = $this->validate( entry_url: 'javascript:alert(1)' );
        $this->assertNotSame( '', $result );
    }

    // -------------------------------------------------------------------------
	    // Photographer bio
	    // -------------------------------------------------------------------------

	    public function test_photographer_bio_500_chars_passes(): void {
	        $result = $this->validate( photographer_bio: str_repeat( 'a', 500 ) );
	        $this->assertSame( '', $result );
    }

    public function test_photographer_bio_501_chars_fails(): void {
        $result = $this->validate( photographer_bio: str_repeat( 'a', 501 ) );
        $this->assertNotSame( '', $result );
        $this->assertStringContainsString( '500', $result );
    }

    // -------------------------------------------------------------------------
	    // Consent requires description and URL
	    // -------------------------------------------------------------------------

    public function test_consent_true_without_required_fields_fails(): void {
        $result = $this->validate( consent: true );
        $this->assertNotSame( '', $result );
    }

	    public function test_consent_true_with_all_required_fields_passes(): void {
	        $result = $this->validate(
	            description:       'My description',
	            entry_url:         'https://example.com/page',
	            consent:           true
	        );
	        $this->assertSame( '', $result );
    }

    public function test_consent_true_missing_description_fails(): void {
	        $result = $this->validate(
	            description:       '',
	            entry_url:         'https://example.com/page',
	            consent:           true
	        );
	        $this->assertNotSame( '', $result );
    }

    public function test_consent_true_missing_url_fails(): void {
	        $result = $this->validate(
	            description:       'My description',
	            entry_url:         '',
	            consent:           true
	        );
	        $this->assertNotSame( '', $result );
	    }

    // -------------------------------------------------------------------------
    // is_valid_community_sample_url
    // -------------------------------------------------------------------------

    public function test_empty_url_is_valid(): void {
        $this->assertTrue( $this->callStatic( 'is_valid_community_sample_url', '' ) );
    }

    public function test_https_url_with_dot_is_valid(): void {
        $this->assertTrue( $this->callStatic( 'is_valid_community_sample_url', 'https://example.com/path' ) );
    }

    public function test_http_url_with_dot_is_valid(): void {
        $this->assertTrue( $this->callStatic( 'is_valid_community_sample_url', 'http://example.com' ) );
    }

    public function test_localhost_without_dot_is_invalid(): void {
        $this->assertFalse( $this->callStatic( 'is_valid_community_sample_url', 'https://localhost' ) );
    }

    public function test_ftp_url_is_invalid(): void {
        $this->assertFalse( $this->callStatic( 'is_valid_community_sample_url', 'ftp://example.com' ) );
    }

    public function test_plain_text_is_invalid(): void {
        $this->assertFalse( $this->callStatic( 'is_valid_community_sample_url', 'not a url' ) );
    }

    // -------------------------------------------------------------------------
	    // extract_community_shortcode_album_link
    // -------------------------------------------------------------------------

    public function test_extract_returns_link_from_valid_shortcode(): void {
        $link = $this->callStatic( 'extract_community_shortcode_album_link', self::VALID_SC );
        $this->assertSame( 'https://photos.google.com/share/AF1QipTest123', $link );
    }

    public function test_extract_returns_empty_for_non_jzsa_shortcode(): void {
        $link = $this->callStatic( 'extract_community_shortcode_album_link', '[other-shortcode link="https://photos.google.com/share/ABC"]' );
        $this->assertSame( '', $link );
    }

    public function test_extract_returns_empty_for_non_google_photos_url(): void {
        $link = $this->callStatic( 'extract_community_shortcode_album_link', '[jzsa-album link="https://example.com/not-photos"]' );
        $this->assertSame( '', $link );
    }

    public function test_extract_accepts_url_with_key_parameter(): void {
        $sc   = '[jzsa-album link="https://photos.google.com/share/AF1Qip?key=XYZ123"]';
        $link = $this->callStatic( 'extract_community_shortcode_album_link', $sc );
        $this->assertSame( 'https://photos.google.com/share/AF1Qip?key=XYZ123', $link );
    }

    public function test_extract_accepts_single_quoted_link(): void {
        $sc   = "[jzsa-album link='https://photos.google.com/share/AF1QipSingleQuote']";
        $link = $this->callStatic( 'extract_community_shortcode_album_link', $sc );
        $this->assertSame( 'https://photos.google.com/share/AF1QipSingleQuote', $link );
    }

    public function test_extract_returns_empty_for_http_google_photos_link(): void {
        $link = $this->callStatic( 'extract_community_shortcode_album_link', '[jzsa-album link="http://photos.google.com/share/AF1Qip"]' );
        $this->assertSame( '', $link );
    }

    public function test_extract_returns_empty_when_extra_content_follows_shortcode(): void {
        $link = $this->callStatic( 'extract_community_shortcode_album_link', self::VALID_SC . ' trailing text' );
        $this->assertSame( '', $link );
    }

    public function test_extract_returns_empty_for_plain_text(): void {
        $link = $this->callStatic( 'extract_community_shortcode_album_link', 'not a shortcode' );
        $this->assertSame( '', $link );
    }

    // -------------------------------------------------------------------------
    // letter_count
    // -------------------------------------------------------------------------

    public function test_letter_count_ascii_string(): void {
        $count = $this->callStatic( 'letter_count', 'Hello' );
        $this->assertSame( 5, $count );
    }

    public function test_letter_count_excludes_digits_and_spaces(): void {
        $count = $this->callStatic( 'letter_count', 'abc 123 !@#' );
        $this->assertSame( 3, $count );
    }

    public function test_letter_count_empty_string(): void {
        $count = $this->callStatic( 'letter_count', '' );
        $this->assertSame( 0, $count );
    }

    public function test_letter_count_unicode_letters(): void {
        // 'Ovo je test' has 9 letters: O,v,o,j,e,t,e,s,t (spaces and nothing else excluded)
        $count = $this->callStatic( 'letter_count', 'Ovo je test' );
        $this->assertSame( 9, $count );
    }
}
