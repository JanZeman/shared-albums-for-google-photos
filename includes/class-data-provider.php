<?php
/**
 * Data Provider: Fetches and extracts photo data from Google Photos
 *
 * Input Phase - Responsible for getting data from Google Photos
 *
 * @package JZSA_Shared_Albums
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Photos Provider Class
 */
class JZSA_Data_Provider {

	/**
	 * Full link format pattern (recommended)
	 *
	 * @var string
	 */
	private $full_link_pattern = '/^https:\/\/photos\.google\.com(?:\/u\/\d+)?\/share\//';

	/**
	 * Short link format pattern (deprecated)
	 *
	 * @var string
	 */
	private $short_link_pattern = '/^https:\/\/photos\.app\.goo\.gl\//';

	/**
	 * Combined validation pattern
	 *
	 * @var string
	 */
	private $validation_pattern = '/^https:\/\/photos\.google\.com(?:\/u\/\d+)?\/share\/|^https:\/\/photos\.app\.goo\.gl\//';

	/**
	 * Validate album URL format
	 *
	 * @param string $url Album URL
	 * @return array Validation result [valid, is_deprecated, error_message]
	 */
	public function validate_url( $url ) {
		if ( empty( $url ) ) {
			return array(
				'valid'         => false,
				'is_deprecated' => false,
				'error'         => __( 'URL is required', 'janzeman-shared-albums-for-google-photos' ),
			);
		}

		if ( ! preg_match( $this->validation_pattern, $url ) ) {
			return array(
				'valid'         => false,
				'is_deprecated' => false,
				'error'         => __( 'Invalid Google Photos share URL', 'janzeman-shared-albums-for-google-photos' ),
			);
		}

		$is_deprecated = preg_match( $this->short_link_pattern, $url );

		return array(
			'valid'         => true,
			'is_deprecated' => $is_deprecated,
			'error'         => null,
		);
	}

	/**
	 * Fetch album data from Google Photos
	 *
	 * @param string $url Album URL
	 * @return array Result [success, data, error]
	 */
	public function fetch_album( $url ) {
		// Validate before fetching
		$validation = $this->validate_url( $url );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => $validation['error'],
			);
		}

		// Fetch HTML content
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => sprintf(
					/* translators: %s: error message returned from WordPress HTTP API */
					__( 'Failed to fetch album: %s', 'janzeman-shared-albums-for-google-photos' ),
					$response->get_error_message()
				),
			);
		}

		$html = wp_remote_retrieve_body( $response );

		if ( empty( $html ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Empty response from Google Photos', 'janzeman-shared-albums-for-google-photos' ),
			);
		}

		// Extract album metadata
		$title  = $this->extract_album_title( $html );
		$photos = $this->extract_photos( $html );

		if ( empty( $photos ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'No photos found in album', 'janzeman-shared-albums-for-google-photos' ),
			);
		}

		return array(
			'success'       => true,
			'is_deprecated' => $validation['is_deprecated'],
			'data'          => array(
				'title'  => $title,
				'photos' => $photos,
				'count'  => count( $photos ),
			),
			'error'         => null,
		);
	}

	/**
	 * Extract album title from HTML
	 *
	 * Two sources are tried in order:
	 * - Primary: <title> tag — contains the user's album name with a "- Google Photos" suffix.
	 * - Secondary: og:title meta tag — contains Google-appended noise (dates, camera icons, etc.).
	 *
	 * @param string $html HTML content
	 * @return string|null Album title or null
	 */
	private function extract_album_title( $html ) {
		// Primary source: <title> tag — contains only the user's album name
		if ( preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $match ) ) {
			$title = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$title = $this->clean_primary_album_title( $title );
			if ( ! empty( $title ) ) {
				return $title;
			}
		}

		// Secondary source: og:title meta tag — contains Google-appended noise
		// (dates, camera icons, separators) so we clean it up aggressively
		if ( preg_match( '/<\s*meta\s+property=["\']og:title["\']\s+content=["\']([^"\']+)["\']/i', $html, $match ) ) {
			$title = $match[1];
		} elseif ( preg_match( '/<\s*meta\s+content=["\']([^"\']+)["\']\s+property=["\']og:title["\']/i', $html, $match ) ) {
			$title = $match[1];
		} else {
			return null;
		}

		return $this->clean_secondary_album_title( $title );
	}

	/**
	 * Clean the primary album title (from <title> tag)
	 *
	 * Only removes the "- Google Photos" suffix that Google appends.
	 * The rest of the title is the user's original album name and is preserved as-is.
	 *
	 * @param string $title Raw title from <title> tag
	 * @return string Cleaned title
	 */
	private function clean_primary_album_title( $title ) {
		$title = preg_replace( '/\s*-\s*Google Photos\s*$/i', '', $title );
		return trim( $title );
	}

	/**
	 * Clean the secondary album title (from og:title meta tag)
	 *
	 * Google's og:title contains noise appended to the album name:
	 * dates ("· Sunday, Mar 22"), camera icons (📸), camera models, etc.
	 * We strip these aggressively since the user's original title is buried in there.
	 *
	 * @param string $title Raw title from og:title meta tag
	 * @return string Cleaned title
	 */
	private function clean_secondary_album_title( $title ) {
		// Remove emoji characters (camera icons, etc.)
		$title = preg_replace( '/[\x{1F300}-\x{1F9FF}]/u', '', $title );

		// Remove full date patterns with day of week: "Saturday, Jan 29, 2005" or "Monday, January 15, 2024"
		$title = preg_replace( '/\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4}\b/i', '', $title );

		// Remove date patterns like "Jan 2024", "January 2024", "Jan 29, 2005"
		$title = preg_replace( '/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4}\b/i', '', $title );
		$title = preg_replace( '/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4}\b/i', '', $title );

		// Remove ISO date format: "2024-01-15"
		$title = preg_replace( '/\b\d{4}-\d{2}-\d{2}\b/', '', $title );

		// Remove slash date format: "01/15/2024"
		$title = preg_replace( '/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/', '', $title );

		// Remove camera model info (e.g., "Canon EOS", "iPhone 14", etc.)
		$title = preg_replace( '/\b(?:Canon|Nikon|Sony|iPhone|Samsung|Pixel)\s+[A-Z0-9\s]+/i', '', $title );

		// Remove common separators and extra whitespace
		$title = preg_replace( '/\s*[-–—|•·,:]\s*/', ' ', $title );
		$title = preg_replace( '/\s+/', ' ', $title );

		// Trim whitespace
		$title = trim( $title );

		return $title;
	}

	/**
	 * Extract media (photos and videos) base URLs from HTML content.
	 * Uses multiple extraction strategies for robustness.
	 *
	 * Returns an array of media items. Each item is either a plain base URL
	 * string (image) or an associative array with 'url' and 'type' keys when
	 * the item is detected as a video.
	 *
	 * @param string $html HTML content
	 * @return array Media items (strings for images, arrays for videos)
	 */
	private function extract_photos( $html ) {
		$media  = array();
		$is_primary = false;

		// Primary strategy: Extract URLs followed by numeric dimensions (with offsets for video detection)
		if ( preg_match_all( '/\"(https?:\/\/[^\"]+)\"\s*,\s*\d+\s*,\s*\d+/i', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			$is_primary = true;
			$count      = count( $matches[1] );

			for ( $i = 0; $i < $count; $i++ ) {
				$url    = $matches[1][ $i ][0];
				$offset = $matches[1][ $i ][1];

				if ( false === strpos( $url, 'googleusercontent.com' ) ) {
					continue;
				}

				$base = preg_replace( '/=[^&]*$/', '', $url );

				// Detect video: check the text between this match and the next for video indicators.
				if ( $i + 1 < $count ) {
					$next_offset    = $matches[1][ $i + 1 ][1];
					$context_length = min( $next_offset - $offset, 5000 );
				} else {
					$context_length = min( 5000, strlen( $html ) - $offset );
				}
				$context  = substr( $html, $offset, $context_length );
				$has_video_downloads = false !== strpos( $context, 'video-downloads' );
				// `video-downloads` can appear once at album level; require URL repetition to tie it to this item.
				$has_item_video_download = $has_video_downloads && substr_count( $context, $base ) > 1;

				$is_video = ( $has_item_video_download
					|| false !== strpos( $context, '"VIDEO"' )
					|| false !== strpos( $context, '"video/mp4"' )
					// Newer payloads expose per-item video metadata under this key.
					|| false !== strpos( $context, '"76647426"' ) );

				if ( $is_video ) {
					$media[] = array(
						'url'  => $base,
						'type' => 'video',
					);
				} else {
					$media[] = $base;
				}
			}
		}

		// Fallback: Extract URLs in array format if primary strategy fails
		if ( ! $is_primary && preg_match_all( '/\[\"(https?:\/\/[^\"]+)\"\]/i', $html, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				if ( false === strpos( $url, 'googleusercontent.com' ) ) {
					continue;
				}
				$media[] = preg_replace( '/=[^&]*$/', '', $url );
			}
		}

		// Remove duplicates (keyed by base URL) and reindex
		$seen   = array();
		$unique = array();
		foreach ( $media as $item ) {
			$key = is_array( $item ) ? $item['url'] : $item;
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$unique[]     = $item;
			}
		}

		return $unique;
	}
}
