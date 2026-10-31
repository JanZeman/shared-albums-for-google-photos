<?php
/**
 * iCloud Provider: Fetches photo data from public Apple iCloud Shared Albums
 *
 * Input Phase - same result shape as JZSA_Data_Provider, so the orchestrator
 * and renderer stay source-agnostic.
 *
 * SPIKE: uses the undocumented endpoints that the iCloud web viewers
 * themselves call. Old public website links (icloud.com/sharedalbum/#B0...)
 * go through sharedstreams (webstream + webasseturls). iOS 27 album links
 * (photos.icloud.com/shared/album/<id>) go through CloudKit (records/resolve
 * + records/query). Apple may change them without notice, the same way
 * Google may change its album HTML.
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
	 * CloudKit database of the Photos app.
	 *
	 * @var string
	 */
	const CLOUDKIT_DATABASE = '/database/1/com.apple.photos.cloud/production/';

	/**
	 * CloudKit host that resolves album link IDs.
	 *
	 * @var string
	 */
	const CLOUDKIT_RESOLVE_HOST = 'https://ckdatabasews.icloud.com';

	/**
	 * Records per records/query page (one photo is two records).
	 *
	 * @var int
	 */
	const CLOUDKIT_PAGE_SIZE = 200;

	/**
	 * Safety cap on records/query pages per album.
	 *
	 * @var int
	 */
	const CLOUDKIT_MAX_PAGES = 50;

	/**
	 * Whether a URL is a public iCloud Shared Album link.
	 *
	 * @param string $url Album URL.
	 * @return bool
	 */
	public static function is_icloud_url( $url ) {
		return is_string( $url ) && (bool) preg_match( '#^https?://(?:www\.|photos\.)?icloud\.com/(?:sharedalbum/|shared/album/)#i', $url );
	}

	/**
	 * Extract the album ID from an iOS 27 album link.
	 *
	 * @param string $url Album URL, e.g. https://photos.icloud.com/shared/album/00cEia2cjYMKOziKR1LDIzXIA
	 * @return string Album ID or empty string.
	 */
	public function extract_short_guid( $url ) {
		return preg_match( '#^https?://(?:www\.|photos\.)?icloud\.com/shared/album/([A-Za-z0-9_-]+)#i', (string) $url, $m ) ? $m[1] : '';
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
		if ( '' === $this->extract_token( $url ) && '' === $this->extract_short_guid( $url ) ) {
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

		$short_guid = $this->extract_short_guid( $url );
		if ( '' !== $short_guid ) {
			return $this->fetch_cloudkit_album( $short_guid );
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
	 * Fetch an iOS 27 album link through CloudKit.
	 *
	 * records/resolve turns the link ID into the album zone plus a short-lived
	 * anonymous token; records/query then lists the zone's assets.
	 *
	 * @param string $short_guid Album ID from the link.
	 * @return array Result [success, data, error]
	 */
	public function fetch_cloudkit_album( $short_guid ) {
		$resolved = $this->post_json(
			self::CLOUDKIT_RESOLVE_HOST . self::CLOUDKIT_DATABASE . 'public/records/resolve?remapEnums=true',
			array( 'shortGUIDs' => array( array( 'value' => $short_guid ) ) ),
			'https://photos.icloud.com'
		);
		if ( is_string( $resolved ) ) {
			return $this->error( $resolved );
		}

		$album = isset( $resolved['results'][0] ) && is_array( $resolved['results'][0] ) ? $resolved['results'][0] : array();
		if ( empty( $album['zoneID'] ) || empty( $album['anonymousPublicAccess']['token'] ) || empty( $album['anonymousPublicAccess']['databasePartition'] ) ) {
			return $this->error( __( 'This iCloud album is not shared with "Anyone with the link"', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$query_url = rtrim( $album['anonymousPublicAccess']['databasePartition'], '/' ) . self::CLOUDKIT_DATABASE
			. 'shared/records/query?remapEnums=true&publicAccessAuthToken=' . rawurlencode( $album['anonymousPublicAccess']['token'] );
		$records = $this->query_cloudkit_zone( $query_url, $album['zoneID'], 'CPLAssetAndMasterByAssetDateWithoutHiddenOrDeleted' );
		if ( is_string( $records ) ) {
			return $this->error( $records );
		}

		// Upload comments ("posts") carry the captions; without them the album still shows.
		$posts = $this->query_cloudkit_zone( $query_url, $album['zoneID'], 'CPLPostByPostTimestamp' );
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				if ( isset( $post['recordType'] ) && 'CPLPost' === $post['recordType'] ) {
					$records[] = $post;
				}
			}
		}

		$photos = $this->build_cloudkit_items( $records );
		if ( empty( $photos ) ) {
			return $this->error( __( 'No photos found in album', 'janzeman-shared-albums-for-google-photos' ) );
		}

		return array(
			'success'       => true,
			'is_deprecated' => false,
			'data'          => array(
				'title'  => isset( $album['share']['fields']['cloudkit.title']['value'] ) ? (string) $album['share']['fields']['cloudkit.title']['value'] : null,
				'photos' => $photos,
				'count'  => count( $photos ),
			),
			'error'         => null,
		);
	}

	/**
	 * Run a CloudKit records/query over the album zone, following continuation markers.
	 *
	 * @param string $query_url   records/query endpoint including the anonymous token.
	 * @param array  $zone_id     Album zone from records/resolve.
	 * @param string $record_type Query record type.
	 * @return array|string Records, or an error message.
	 */
	private function query_cloudkit_zone( $query_url, $zone_id, $record_type ) {
		$query = array(
			'query'        => array(
				'recordType' => $record_type,
				'filterBy'   => array(
					array(
						'fieldName'  => 'direction',
						'comparator' => 'EQUALS',
						'fieldValue' => array(
							'value' => 'ASCENDING',
							'type'  => 'STRING',
						),
					),
				),
			),
			'zoneID'       => $zone_id,
			'resultsLimit' => self::CLOUDKIT_PAGE_SIZE,
		);

		$records = array();
		for ( $page = 0; $page < self::CLOUDKIT_MAX_PAGES; $page++ ) {
			$response = $this->post_json( $query_url, $query, 'https://photos.icloud.com' );
			if ( is_string( $response ) ) {
				return $response;
			}
			if ( ! empty( $response['records'] ) && is_array( $response['records'] ) ) {
				$records = array_merge( $records, $response['records'] );
			}
			if ( empty( $response['continuationMarker'] ) ) {
				break;
			}
			$query['continuationMarker'] = $response['continuationMarker'];
		}

		return $records;
	}

	/**
	 * Turn CloudKit CPLAsset + CPLMaster records into media items.
	 *
	 * Renditions are the master's res<Name>Res fields (resJPEGThumbRes,
	 * resJPEGMedRes, resOriginalRes, resVidMedRes, ...). Image renditions the
	 * browser can show go to 'sources'; the widest video rendition is the video,
	 * preferring the transcoded ones over a possibly huge original. The caption
	 * is the comment of the upload (CPLPost) the asset was added with.
	 *
	 * @param array $records CPLAsset, CPLMaster and CPLPost records, assets in album order.
	 * @return array Media items.
	 */
	public function build_cloudkit_items( $records ) {
		$masters = array();
		$posts   = array();
		foreach ( $records as $record ) {
			if ( ! isset( $record['recordType'], $record['recordName'] ) ) {
				continue;
			}
			if ( 'CPLMaster' === $record['recordType'] ) {
				$masters[ $record['recordName'] ] = isset( $record['fields'] ) ? $record['fields'] : array();
			} elseif ( 'CPLPost' === $record['recordType'] && isset( $record['fields'] ) ) {
				$posts[ $record['recordName'] ] = $this->decode_cloudkit_string( $record['fields'], 'caption' );
			}
		}

		$items = array();
		foreach ( $records as $record ) {
			if ( ! isset( $record['recordType'] ) || 'CPLAsset' !== $record['recordType'] ) {
				continue;
			}

			$fields     = isset( $record['fields'] ) ? $record['fields'] : array();
			$master_ref = isset( $fields['masterRef']['value']['recordName'] ) ? $fields['masterRef']['value']['recordName'] : '';
			if ( '' === $master_ref || ! isset( $masters[ $master_ref ] ) ) {
				continue;
			}

			$master   = $masters[ $master_ref ];
			$filename = $this->decode_cloudkit_string( $master, 'filenameEnc' );
			$sources  = array();
			$video    = '';
			$video_w  = -1;

			foreach ( $master as $key => $field ) {
				if ( ! preg_match( '/^res(.+)Res$/', $key, $m ) || empty( $field['value']['downloadURL'] ) ) {
					continue;
				}

				$name   = $m[1];
				$type   = isset( $master[ 'res' . $name . 'FileType' ]['value'] ) ? (string) $master[ 'res' . $name . 'FileType' ]['value'] : '';
				$width  = isset( $master[ 'res' . $name . 'Width' ]['value'] ) ? (int) $master[ 'res' . $name . 'Width' ]['value'] : 0;
				$height = isset( $master[ 'res' . $name . 'Height' ]['value'] ) ? (int) $master[ 'res' . $name . 'Height' ]['value'] : 0;
				$url    = str_replace( '${f}', rawurlencode( '' !== $filename ? $filename : 'media' ), $field['value']['downloadURL'] );

				if ( in_array( $type, array( 'com.apple.quicktime-movie', 'public.mpeg-4' ), true ) ) {
					$rank = 'Original' === $name ? 0 : $width;
					if ( $rank > $video_w ) {
						$video   = $url;
						$video_w = $rank;
					}
					continue;
				}

				if ( ! in_array( $type, array( 'public.jpeg', 'public.png', 'com.compuserve.gif' ), true ) ) {
					continue;
				}

				$sources[] = array(
					'url'    => $url,
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
				'type'    => '' !== $video ? 'video' : 'image',
				'id'      => isset( $record['recordName'] ) ? (string) $record['recordName'] : '',
				'width'   => $largest['width'],
				'height'  => $largest['height'],
				'sources' => $sources,
			);

			if ( '' !== $video ) {
				$item['video'] = $video;
			}

			$batch_id = isset( $fields['assetBatchId']['value'] ) ? (string) $fields['assetBatchId']['value'] : '';
			$caption  = isset( $posts[ $batch_id ] ) ? $posts[ $batch_id ] : '';
			if ( '' !== $caption ) {
				$item['description'] = $caption;
			}
			if ( ! empty( $fields['assetDate']['value'] ) ) {
				$item['timestamp'] = (int) $fields['assetDate']['value'];
			}

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * Read a CloudKit text field; *Enc fields are base64 (ENCRYPTED_BYTES).
	 *
	 * @param array  $fields Record fields.
	 * @param string $key    Field name.
	 * @return string
	 */
	private function decode_cloudkit_string( $fields, $key ) {
		if ( empty( $fields[ $key ]['value'] ) || ! is_string( $fields[ $key ]['value'] ) ) {
			return '';
		}

		$value = $fields[ $key ]['value'];
		if ( isset( $fields[ $key ]['type'] ) && 'ENCRYPTED_BYTES' === $fields[ $key ]['type'] ) {
			$value = (string) base64_decode( $value, true );
		}

		return trim( $value );
	}

	/**
	 * POST a JSON body and decode the JSON answer.
	 *
	 * @param string $url    Endpoint.
	 * @param array  $body   Request body.
	 * @param string $origin Origin the endpoint expects.
	 * @return array|string Decoded body, or an error message.
	 */
	private function post_json( $url, $body, $origin = 'https://www.icloud.com' ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'text/plain',
					'Origin'       => $origin,
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
