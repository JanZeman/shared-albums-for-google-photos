<?php
/**
 * iCloud Provider: Fetches photo data from public Apple iCloud Shared Albums
 *
 * Input Phase - same result shape as JZSA_Data_Provider, so the orchestrator
 * and renderer stay source-agnostic.
 *
 * SPIKE: uses the undocumented sharedstreams endpoints that the iCloud web
 * viewer itself calls (webstream + webasseturls). Apple may change them
 * without notice, the same way Google may change its album HTML.
 *
 * @package JZSA_Shared_Albums
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * iCloud Shared Albums Provider Class
 */
class JZSA_ICloud_Provider {

	/**
	 * Public shared album link, e.g. https://www.icloud.com/sharedalbum/#B0aGWZuqDGtB9r
	 *
	 * @var string
	 */
	private $url_pattern = '#^https?://(?:www\.)?icloud\.com/sharedalbum/(?:[a-z_-]+/)?\#([A-Za-z0-9]+)#i';

	/**
	 * Characters used by Apple to encode the storage partition in the token.
	 *
	 * @var string
	 */
	const BASE62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	/**
	 * Max photo GUIDs per webasseturls request (the web viewer uses small batches).
	 *
	 * @var int
	 */
	const ASSET_URL_BATCH = 25;

	/**
	 * Whether a URL is a public iCloud Shared Album link.
	 *
	 * @param string $url Album URL.
	 * @return bool
	 */
	public static function is_icloud_url( $url ) {
		return is_string( $url ) && (bool) preg_match( '#^https?://(?:www\.)?icloud\.com/sharedalbum/#i', $url );
	}

	/**
	 * Extract the album token from the URL fragment.
	 *
	 * @param string $url Album URL.
	 * @return string Token or empty string.
	 */
	public function extract_token( $url ) {
		return preg_match( $this->url_pattern, (string) $url, $m ) ? $m[1] : '';
	}

	/**
	 * Build the sharedstreams base URL for a token.
	 *
	 * The storage partition is base62-encoded in the token: one character after
	 * a leading "A", otherwise two characters after the first.
	 *
	 * @param string $token Album token.
	 * @param string $host  Optional host returned by a 330 redirect.
	 * @return string
	 */
	public function get_base_url( $token, $host = '' ) {
		if ( '' === $host ) {
			$digits    = 'A' === $token[0] ? substr( $token, 1, 1 ) : substr( $token, 1, 2 );
			$partition = 0;
			foreach ( str_split( $digits ) as $char ) {
				$partition = $partition * 62 + strpos( self::BASE62, $char );
			}
			$host = sprintf( 'p%02d-sharedstreams.icloud.com', $partition );
		}

		return 'https://' . $host . '/' . $token . '/sharedstreams/';
	}

	/**
	 * Validate album URL format
	 *
	 * @param string $url Album URL
	 * @return array Validation result [valid, is_deprecated, error_message]
	 */
	public function validate_url( $url ) {
		if ( '' === $this->extract_token( $url ) ) {
			return array(
				'valid'         => false,
				'is_deprecated' => false,
				'error'         => __( 'Invalid iCloud Shared Album URL', 'janzeman-shared-albums-for-google-photos' ),
			);
		}

		return array(
			'valid'         => true,
			'is_deprecated' => false,
			'error'         => null,
		);
	}

	/**
	 * Fetch album data from iCloud
	 *
	 * @param string $url Album URL
	 * @return array Result [success, data, error]
	 */
	public function fetch_album( $url ) {
		$validation = $this->validate_url( $url );
		if ( ! $validation['valid'] ) {
			return $this->error( $validation['error'] );
		}

		$token  = $this->extract_token( $url );
		$base   = $this->get_base_url( $token );
		$stream = $this->post_json( $base . 'webstream', array( 'streamCtag' => null ) );

		// Wrong partition: Apple answers 330 with the right host in the body.
		if ( is_array( $stream ) && ! empty( $stream['X-Apple-MMe-Host'] ) ) {
			$base   = $this->get_base_url( $token, $stream['X-Apple-MMe-Host'] );
			$stream = $this->post_json( $base . 'webstream', array( 'streamCtag' => null ) );
		}

		if ( is_string( $stream ) ) {
			return $this->error( $stream );
		}
		if ( empty( $stream['photos'] ) || ! is_array( $stream['photos'] ) ) {
			return $this->error( __( 'No photos found in album', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$guids  = array_values( array_filter( array_column( $stream['photos'], 'photoGuid' ) ) );
		$assets = array();
		foreach ( array_chunk( $guids, self::ASSET_URL_BATCH ) as $batch ) {
			$response = $this->post_json( $base . 'webasseturls', array( 'photoGuids' => $batch ) );
			if ( is_string( $response ) ) {
				return $this->error( $response );
			}
			$assets = $this->merge_asset_urls( $assets, $response );
		}

		$photos = $this->build_items( $stream['photos'], $assets );
		if ( empty( $photos ) ) {
			return $this->error( __( 'No photos found in album', 'janzeman-shared-albums-for-google-photos' ) );
		}

		return array(
			'success'       => true,
			'is_deprecated' => false,
			'data'          => array(
				'title'  => isset( $stream['streamName'] ) ? (string) $stream['streamName'] : null,
				'photos' => $photos,
				'count'  => count( $photos ),
			),
			'error'         => null,
		);
	}

	/**
	 * Resolve a webasseturls response into a checksum → absolute URL map.
	 *
	 * @param array $assets   Map collected so far.
	 * @param array $response Decoded webasseturls response.
	 * @return array
	 */
	public function merge_asset_urls( $assets, $response ) {
		$locations = isset( $response['locations'] ) && is_array( $response['locations'] ) ? $response['locations'] : array();
		$items     = isset( $response['items'] ) && is_array( $response['items'] ) ? $response['items'] : array();

		foreach ( $items as $checksum => $item ) {
			if ( empty( $item['url_path'] ) || empty( $item['url_location'] ) ) {
				continue;
			}
			$location = isset( $locations[ $item['url_location'] ] ) ? $locations[ $item['url_location'] ] : array();
			$scheme   = ! empty( $location['scheme'] ) ? $location['scheme'] : 'https';
			$host     = ! empty( $location['hosts'][0] ) ? $location['hosts'][0] : $item['url_location'];

			$assets[ $checksum ] = $scheme . '://' . $host . $item['url_path'];
		}

		return $assets;
	}

	/**
	 * Turn webstream photos plus resolved asset URLs into media items.
	 *
	 * Each item carries fixed-size renditions under 'sources' (iCloud cannot
	 * resize on the fly the way Google's =w…-h… suffixes do).
	 *
	 * @param array $stream_photos `photos` from the webstream response.
	 * @param array $assets        Checksum → URL map.
	 * @return array Media items.
	 */
	public function build_items( $stream_photos, $assets ) {
		$items = array();

		foreach ( $stream_photos as $photo ) {
			if ( empty( $photo['derivatives'] ) || ! is_array( $photo['derivatives'] ) ) {
				continue;
			}

			$is_video = isset( $photo['mediaAssetType'] ) && 'video' === $photo['mediaAssetType'];
			$sources  = array();
			$video    = '';
			$video_w  = 0;

			foreach ( $photo['derivatives'] as $key => $derivative ) {
				$checksum = isset( $derivative['checksum'] ) ? $derivative['checksum'] : '';
				if ( '' === $checksum || empty( $assets[ $checksum ] ) ) {
					continue;
				}

				$width  = isset( $derivative['width'] ) ? (int) $derivative['width'] : 0;
				$height = isset( $derivative['height'] ) ? (int) $derivative['height'] : 0;

				// Video derivatives are keyed by quality ("720p", "360p"); the poster frame is an image.
				if ( $is_video && 'PosterFrame' !== $key ) {
					if ( $width >= $video_w ) {
						$video   = $assets[ $checksum ];
						$video_w = $width;
					}
					continue;
				}

				$sources[] = array(
					'url'    => $assets[ $checksum ],
					'width'  => $width,
					'height' => $height,
				);
			}

			if ( empty( $sources ) ) {
				continue;
			}

			usort(
				$sources,
				static function ( $a, $b ) {
					return $a['width'] - $b['width'];
				}
			);

			$largest = end( $sources );
			$item    = array(
				'url'     => $largest['url'],
				'type'    => $is_video && '' !== $video ? 'video' : 'image',
				'id'      => isset( $photo['photoGuid'] ) ? (string) $photo['photoGuid'] : '',
				'width'   => ! empty( $photo['width'] ) ? (int) $photo['width'] : $largest['width'],
				'height'  => ! empty( $photo['height'] ) ? (int) $photo['height'] : $largest['height'],
				'sources' => $sources,
			);

			if ( 'video' === $item['type'] ) {
				$item['video'] = $video;
			}
			if ( isset( $photo['caption'] ) && '' !== trim( (string) $photo['caption'] ) ) {
				$item['description'] = trim( (string) $photo['caption'] );
			}
			if ( ! empty( $photo['dateCreated'] ) ) {
				$timestamp = strtotime( (string) $photo['dateCreated'] );
				if ( false !== $timestamp ) {
					$item['timestamp'] = $timestamp * 1000;
				}
			}

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * POST a JSON body and decode the JSON answer.
	 *
	 * @param string $url  Endpoint.
	 * @param array  $body Request body.
	 * @return array|string Decoded body, or an error message.
	 */
	private function post_json( $url, $body ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'text/plain',
					'Origin'       => 'https://www.icloud.com',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return sprintf(
				/* translators: %s: error message returned from WordPress HTTP API */
				__( 'Failed to fetch album: %s', 'janzeman-shared-albums-for-google-photos' ),
				$response->get_error_message()
			);
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ( 200 !== $code && 330 !== $code ) || ! is_array( $decoded ) ) {
			return __( 'Could not fetch the iCloud Shared Album', 'janzeman-shared-albums-for-google-photos' );
		}

		return $decoded;
	}

	/**
	 * Build a failed fetch result.
	 *
	 * @param string $message Error message.
	 * @return array
	 */
	private function error( $message ) {
		return array(
			'success' => false,
			'data'    => null,
			'error'   => $message,
		);
	}
}
