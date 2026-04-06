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
	 * - Primary: <title> tag - contains the user's album name with a "- Google Photos" suffix.
	 * - Secondary: og:title meta tag - contains Google-appended noise (dates, camera icons, etc.).
	 *
	 * @param string $html HTML content
	 * @return string|null Album title or null
	 */
	private function extract_album_title( $html ) {
		// Primary source: <title> tag - contains only the user's album name
		if ( preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $match ) ) {
			$title = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$title = $this->clean_primary_album_title( $title );
			if ( ! empty( $title ) ) {
				return $title;
			}
		}

		// Secondary source: og:title meta tag - contains Google-appended noise
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
	 * After URL extraction (Stage 1), metadata stages run as purely additive
	 * parallel passes. If every metadata pass fails, Stage 1 output is
	 * unaffected and albums still render correctly.
	 *
	 * @param string $html HTML content
	 * @return array Media items (strings for images, arrays for videos)
	 */
	private function extract_photos( $html ) {
		$media  = array();
		$is_primary = false;

		// ── Stage 1: Primary URL extraction - NO CHANGE ───────────────────

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

		// ── Stage 0 + 2a/2b/2c: Metadata enrichment (purely additive) ────
		// If this entire block fails, $unique is returned as-is - identical
		// to previous behaviour.
		if ( $is_primary ) {
			$unique = $this->enrich_with_metadata( $html, $unique );
		}

		return $unique;
	}

	/**
	 * Enrich extracted media items with metadata from the album HTML.
	 *
	 * Runs as a purely additive pass after Stage 1 URL extraction.
	 * Plain string items are promoted to arrays; existing array items
	 * gain new keys. If no metadata is found, items pass through unchanged.
	 *
	 * @param string $html   Full HTML content.
	 * @param array  $items  Media items from Stage 1.
	 * @return array Enriched media items.
	 */
	private function enrich_with_metadata( $html, $items ) {
		// Stage 0: Build url → id + dimensions + filesize + timestamp lookup map.
		// Structure per photo: ["AF1Qip…", ["url", WIDTH, HEIGHT, null×5, […], [FILESIZE]], TIMESTAMP, "", TZ_OFFSET]
		$url_meta = array();
		if ( preg_match_all(
			'/\["(AF1Qip[^"]+)"\s*,\s*\["(https?:\/\/[^"]+googleusercontent\.com[^"]+)"\s*,\s*(\d+)\s*,\s*(\d+).*?\[(\d+)\].*?\]\s*,\s*(\d{10,13})\s*,\s*"[^"]*"\s*,\s*(\d+)/is',
			$html,
			$id_matches
		) ) {
			foreach ( $id_matches[1] as $i => $id ) {
				$raw_url  = $id_matches[2][ $i ];
				$base_url = preg_replace( '/=[^&]*$/', '', $raw_url );
				if ( ! isset( $url_meta[ $base_url ] ) ) {
					$url_meta[ $base_url ] = array(
						'id'        => $id,
						'width'     => (int) $id_matches[3][ $i ],
						'height'    => (int) $id_matches[4][ $i ],
						'filesize'  => (int) $id_matches[5][ $i ],
						'timestamp' => (int) $id_matches[6][ $i ],
						'tz_offset' => (int) $id_matches[7][ $i ],
					);
				}
			}
		}

		// If Stage 0 found nothing, return items unchanged.
		if ( empty( $url_meta ) ) {
			return $items;
		}

		// Stage 2c: EXIF extraction is done per media ID below, limited to the
		// current media entry so metadata from neighbouring photos cannot bleed
		// across when album order changes.
		$exif_map = array(); // id → [ 'camera' => ..., 'exif' => ... ]

		// Merge metadata into items.
		foreach ( $items as &$item ) {
			$base_url = is_array( $item ) ? $item['url'] : $item;
			if ( ! isset( $url_meta[ $base_url ] ) ) {
				continue;
			}

			$meta = $url_meta[ $base_url ];

			// Promote plain string items to arrays.
			if ( ! is_array( $item ) ) {
				$item = array( 'url' => $item );
			}

			// Media ID (needed by Wave 2 for individual photo page URLs).
			$item['id'] = $meta['id'];

			// Stage 2b: Timestamp, dimensions, filesize (always present alongside URL).
			$item['timestamp'] = $meta['timestamp'];
			$item['width']     = $meta['width'];
			$item['height']    = $meta['height'];
			$item['filesize']  = $meta['filesize'];

			// Stage 2a: Filename extraction (windowed search per media ID).
			$filename = $this->extract_filename_for_media_id( $html, $meta['id'] );
			if ( '' !== $filename ) {
				$item['filename'] = $filename;
			}

			// Stage 2c: EXIF (scoped to the current media record).
			if ( ! array_key_exists( $meta['id'], $exif_map ) ) {
				$exif_map[ $meta['id'] ] = $this->extract_album_exif_for_media_id( $html, $meta['id'] );
			}
			if ( ! empty( $exif_map[ $meta['id'] ] ) ) {
				$exif            = $exif_map[ $meta['id'] ];
				$item['camera']   = $exif['camera'];
				$item['exif']     = $exif['exif'];
				$item['aperture'] = $exif['aperture'];
				$item['shutter']  = $exif['shutter'];
				$item['focal']    = $exif['focal'];
				$item['iso']      = $exif['iso'];
			}
		}
		unset( $item );

		return $items;
	}

	/**
	 * Check whether a string looks like an original image/video filename.
	 *
	 * @param string $fn Candidate filename.
	 * @return bool
	 */
	private function is_plausible_original_filename( $fn ) {
		if ( '' === $fn || strlen( $fn ) > 220 ) {
			return false;
		}
		if ( preg_match( '/https?:\/\//i', $fn ) || false !== strpos( $fn, '//' ) ) {
			return false;
		}
		if ( preg_match( '#[\/\\\\]#', $fn ) ) {
			return false;
		}
		return (bool) preg_match( '/\.(?:jpe?g|png|heic|webp|mp4|mov|avi|mkv)$/iu', $fn );
	}

	/**
	 * Score a filename candidate - higher is more likely to be the real filename.
	 *
	 * @param string $fn Candidate filename.
	 * @return int Score.
	 */
	private function score_filename_candidate( $fn ) {
		$score = 1 + min( 15, (int) ( strlen( $fn ) / 12 ) );
		if ( preg_match( '/^[A-Za-z]{3,}\d{4}-\d{2}-\d{2}/', $fn ) ) {
			$score += 45;
		}
		if ( preg_match( '/^\w+\d{4}-\d{2}-\d{2}_/', $fn ) ) {
			$score += 40;
		}
		if ( false !== strpos( $fn, '_' ) ) {
			$score += 22;
		}
		if ( preg_match( '/^(DSC|IMG|DJI_|PXL_|Photo_|SAM_|_MG|VID_|MOV_)/i', $fn ) ) {
			$score += 18;
		}
		return $score;
	}

	/**
	 * Extract the chunk of HTML that belongs to a single media entry.
	 *
	 * Google Photos album HTML stores many media records back-to-back. Keeping
	 * searches inside one record prevents data from adjacent photos from being
	 * incorrectly attributed when regexes scan too far forward.
	 *
	 * @param string $html     Full HTML content.
	 * @param string $media_id AF1Qip… media ID.
	 * @return string
	 */
	private function extract_media_item_chunk( $html, $media_id ) {
		if ( '' === $media_id ) {
			return '';
		}

		$needle = '["' . $media_id . '"';
		$pos    = strpos( $html, $needle );
		if ( false === $pos ) {
			$needle = '"' . $media_id . '"';
			$pos    = strpos( $html, $needle );
			if ( false === $pos ) {
				return '';
			}
		}

		$next_pos = strpos( $html, '],["AF1Qip', $pos + strlen( $needle ) );
		if ( false !== $next_pos ) {
			return substr( $html, $pos, $next_pos - $pos + 1 );
		}

		return substr( $html, $pos, 35000 );
	}

	/**
	 * Extract the original filename for a media ID from the album HTML.
	 *
	 * Searches a ~35 KB window around the media ID for filename candidates.
	 * Three-level fallback: known protobuf key, any numeric key with a
	 * plausible value, quoted filename strings.
	 *
	 * @param string $html     Full HTML content.
	 * @param string $media_id AF1Qip… media ID.
	 * @return string Filename or empty string.
	 */
	private function extract_filename_for_media_id( $html, $media_id ) {
		$chunk = $this->extract_media_item_chunk( $html, $media_id );
		if ( '' === $chunk ) {
			return '';
		}

		// Method 1: Known historical protobuf key 101428965.
		if ( preg_match( '/"101428965"\s*:\s*\[\s*(?:\d+|null)\s*,\s*"([^"]+)"/', $chunk, $m ) ) {
			if ( $this->is_plausible_original_filename( $m[1] ) ) {
				return $m[1];
			}
		}

		// Method 2: Any numeric protobuf key with a plausible filename value.
		if ( preg_match_all(
			'/"(\d{5,12})"\s*:\s*\[\s*(?:\d+|null)\s*,\s*"([^"]*\.(?:jpg|jpeg|png|heic|webp|mp4|mov))"\]/iu',
			$chunk,
			$all,
			PREG_SET_ORDER
		) ) {
			$best       = '';
			$best_score = 0;
			foreach ( $all as $row ) {
				if ( ! $this->is_plausible_original_filename( $row[2] ) ) {
					continue;
				}
				$score = $this->score_filename_candidate( $row[2] );
				if ( $score > $best_score ) {
					$best_score = $score;
					$best       = $row[2];
				}
			}
			if ( '' !== $best ) {
				return $best;
			}
		}

		// Method 3: Last resort - quoted *.ext strings with a high score.
		if ( preg_match_all(
			'/"([A-Za-z0-9][^"]{0,180}\.(?:jpg|jpeg|png|heic|webp|mp4|mov))"/u',
			$chunk,
			$q,
			PREG_SET_ORDER
		) ) {
			$best       = '';
			$best_score = 0;
			foreach ( $q as $row ) {
				if ( ! $this->is_plausible_original_filename( $row[1] ) ) {
					continue;
				}
				$score = $this->score_filename_candidate( $row[1] );
				if ( $score > $best_score ) {
					$best_score = $score;
					$best       = $row[1];
				}
			}
			if ( '' !== $best && $best_score >= 30 ) {
				return $best;
			}
		}

		return '';
	}

	/**
	 * Extract EXIF metadata for a single media ID from album HTML.
	 *
	 * Shared album pages may contain multiple media records adjacent to each
	 * other, so this search is restricted to the current media entry.
	 *
	 * @param string $html     Full HTML content.
	 * @param string $media_id AF1Qip… media ID.
	 * @return array
	 */
	private function extract_album_exif_for_media_id( $html, $media_id ) {
		$chunk = $this->extract_media_item_chunk( $html, $media_id );
		if ( '' === $chunk ) {
			return array();
		}

		if ( ! preg_match(
			'/\[(\d+)\s*,\s*(\d+)\s*,\s*1\s*,\s*null\s*,\s*\["([^"]+)"\s*,\s*"([^"]+)"\s*,\s*(?:"[^"]*"|null)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*(\d+)\s*,\s*([\d.]+)/s',
			$chunk,
			$m
		) ) {
			return array();
		}

		$make     = $m[3];
		$model    = $m[4];
		$focal    = $m[5];
		$aperture = $m[6];
		$iso      = (int) $m[7];
		$shutter  = (float) $m[8];

		$shutter_display = $shutter < 1 && $shutter > 0
			? '1/' . round( 1 / $shutter )
			: round( $shutter, 1 ) . 's';

		return array(
			'camera'   => trim( $make . ' ' . $model ),
			'exif'     => sprintf( "\xC6\x92/%s \xC2\xB7 %s \xC2\xB7 %smm \xC2\xB7 ISO%d", $aperture, $shutter_display, $focal, $iso ),
			'aperture' => "\xC6\x92/" . $aperture,
			'shutter'  => $shutter_display,
			'focal'    => $focal . 'mm',
			'iso'      => 'ISO' . $iso,
		);
	}

	/**
	 * Extract EXIF metadata from an individual photo page HTML.
	 *
	 * Individual photo pages embed EXIF in a simpler structure than album pages:
	 * [width, height, 1, null, ["make", "model", "lens", focal, aperture, iso, shutter, null, N]]
	 *
	 * @param string $html HTML content of an individual photo page.
	 * @return array Associative array with EXIF fields, or empty array if not found.
	 */
	public function extract_individual_photo_meta( $html ) {
		if ( empty( $html ) ) {
			return array();
		}

		// Match the EXIF block: [w, h, 1, null, ["make", "model", ...]]
		if ( ! preg_match(
			'/\[(\d+)\s*,\s*(\d+)\s*,\s*1\s*,\s*null\s*,\s*\["([^"]+)"\s*,\s*"([^"]+)"\s*,\s*(?:"[^"]*"|null)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*(\d+)\s*,\s*([\d.]+)/s',
			$html,
			$m
		) ) {
			return array();
		}

		$make     = $m[3];
		$model    = $m[4];
		$focal    = $m[5];
		$aperture = $m[6];
		$iso      = (int) $m[7];
		$shutter  = (float) $m[8];

		$shutter_display = $shutter < 1 && $shutter > 0
			? '1/' . round( 1 / $shutter )
			: round( $shutter, 1 ) . 's';

		return array(
			'camera'   => trim( $make . ' ' . $model ),
			'aperture' => "\xC6\x92/" . $aperture,
			'shutter'  => $shutter_display,
			'focal'    => $focal . 'mm',
			'iso'      => 'ISO' . $iso,
		);
	}

	/**
	 * Extract direct media URLs from an individual photo page HTML.
	 *
	 * Returns the canonical image URL (when present) and the dedicated
	 * video-download URL for video items.
	 *
	 * @param string $html HTML content of an individual photo page.
	 * @return array{image?:string,video?:string}
	 */
	public function extract_individual_photo_media_urls( $html ) {
		$urls = array();

		if ( empty( $html ) ) {
			return $urls;
		}

		if ( preg_match( '/"((?:https:\/\/lh3\.googleusercontent\.com\/[^"]+?)(?:\\\\u003d|=)s0-d-ip)"/i', $html, $m ) ) {
			$urls['image'] = $this->decode_google_photos_url( $m[1] );
		} elseif ( preg_match( '/"(https:\/\/lh3\.googleusercontent\.com\/[^"]+)"/i', $html, $m ) ) {
			$urls['image'] = $this->decode_google_photos_url( $m[1] );
		}

		if ( preg_match( '/"(https:\/\/video-downloads\.googleusercontent\.com\/[^"\\\\]+)"/i', $html, $m ) ) {
			$urls['video'] = $this->decode_google_photos_url( $m[1] );
		}

		return $urls;
	}

	/**
	 * Decode Google Photos URL escapes found inside photo page HTML.
	 *
	 * @param string $url Escaped URL string.
	 * @return string
	 */
	private function decode_google_photos_url( $url ) {
		$decoded = str_replace(
			array( '\\u003d', '\\u0026', '\\/', '&amp;' ),
			array( '=', '&', '/', '&' ),
			$url
		);

		return html_entity_decode( $decoded, ENT_QUOTES );
	}
}
