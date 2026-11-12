<?php
/**
 * iCloud Provider: Fetches photo data from public Apple iCloud Shared Albums
 *
 * Input Phase - same result shape as JZSA_Data_Provider, so the orchestrator
 * and renderer stay source-agnostic.
 *
 * Uses the undocumented endpoints that the iCloud web viewers
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
	 * CloudKit file types of movie renditions.
	 */
	const MOVIE_TYPES = array( 'com.apple.quicktime-movie', 'public.mpeg-4' );

	/**
	 * Movie originals (AVI, WMV, MPEG-1/2, Matroska, ...) that iCloud stores without
	 * any MP4 or MOV rendition. Browsers cannot play them, so they are shown as
	 * stills and flagged for the admin-only notice.
	 */
	const UNPLAYABLE_MOVIE_TYPES = array(
		'public.avi',
		'public.mpeg',
		'public.mpeg-2-video',
		'public.mpeg-2-transport-stream',
		'public.3gpp',
		'public.3gpp2',
		'public.dv-movie',
		'com.microsoft.windows-media-wmv',
		'org.matroska.mkv',
		'com.adobe.flash.video',
		'org.webmproject.webm',
		'public.movie',
		'public.video',
	);

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
	 * @param string $url           Album URL
	 * @param bool   $with_captions Also load captions; media URL refreshes skip them to save a request.
	 * @return array Result [success, data, error]
	 */
	public function fetch_album( $url, $with_captions = true ) {
		$validation = $this->validate_url( $url );
		if ( ! $validation['valid'] ) {
			return $this->error( $validation['error'] );
		}

		$short_guid = $this->extract_short_guid( $url );
		if ( '' !== $short_guid ) {
			return $this->with_media_refs( $this->fetch_cloudkit_album( $short_guid, $with_captions ), $url );
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

		$result = array(
			'success'       => true,
			'is_deprecated' => false,
			'data'          => array(
				'title'  => isset( $stream['streamName'] ) ? (string) $stream['streamName'] : null,
				'photos' => $photos,
				'count'  => count( $photos ),
			),
			'error'         => null,
		);

		return $this->with_media_refs( $result, $url );
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

			$video_rendition = '';

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
						$video           = $assets[ $checksum ];
						$video_w         = $width;
						$video_rendition = (string) $key;
					}
					continue;
				}

				$sources[] = array(
					'url'       => $assets[ $checksum ],
					'width'     => $width,
					'height'    => $height,
					'rendition' => (string) $key,
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
				$item['video']           = $video;
				$item['video_rendition'] = $video_rendition;
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
	 * The resolved zone and token are reused while the token is valid, so a
	 * refresh within that time costs one request instead of two.
	 *
	 * @param string $short_guid    Album ID from the link.
	 * @param bool   $with_captions Also query the upload posts that carry captions.
	 * @return array Result [success, data, error]
	 */
	public function fetch_cloudkit_album( $short_guid, $with_captions = true ) {
		$resolve_key = 'jzsa_icloud_resolve_' . md5( $short_guid );
		$album       = get_transient( $resolve_key );
		$from_cache  = is_array( $album );
		if ( ! $from_cache ) {
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

			// Keep only what later requests need; the full answer also names the owner.
			$album      = array(
				'zoneID'                => $album['zoneID'],
				'anonymousPublicAccess' => $album['anonymousPublicAccess'],
				'share'                 => array( 'fields' => array( 'cloudkit.title' => $album['share']['fields']['cloudkit.title'] ?? null ) ),
			);
			$token_life = isset( $album['anonymousPublicAccess']['tokenTTL'] ) ? (int) ( $album['anonymousPublicAccess']['tokenTTL'] / 1000 ) : 0;
			if ( $token_life > 300 ) {
				set_transient( $resolve_key, $album, $token_life - 120 );
			}
		}

		$query_url = rtrim( $album['anonymousPublicAccess']['databasePartition'], '/' ) . self::CLOUDKIT_DATABASE
			. 'shared/records/query?remapEnums=true&publicAccessAuthToken=' . rawurlencode( $album['anonymousPublicAccess']['token'] );
		$records = $this->query_cloudkit_zone( $query_url, $album['zoneID'], 'CPLAssetAndMasterByAssetDateWithoutHiddenOrDeleted' );
		if ( is_string( $records ) && $from_cache ) {
			// The stored token may have been revoked early: resolve once more.
			delete_transient( $resolve_key );
			return $this->fetch_cloudkit_album( $short_guid, $with_captions );
		}
		if ( is_string( $records ) ) {
			return $this->error( $records );
		}

		// Upload comments ("posts") carry the captions; without them the album still shows.
		$posts = $with_captions ? $this->query_cloudkit_zone( $query_url, $album['zoneID'], 'CPLPostByPostTimestamp' ) : array();
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
	 * Image renditions a browser can show from a record's res<Name>Res fields.
	 *
	 * @param array  $fields   Record fields.
	 * @param string $filename Original filename, for the download name in the URL.
	 * @return array Sources: url, width, height, rendition, type.
	 */
	private function image_sources( $fields, $filename ) {
		$sources = array();
		foreach ( $fields as $key => $field ) {
			if ( ! preg_match( '/^res(.+)Res$/', $key, $m ) || empty( $field['value']['downloadURL'] ) ) {
				continue;
			}
			$name = $m[1];
			$type = isset( $fields[ 'res' . $name . 'FileType' ]['value'] ) ? (string) $fields[ 'res' . $name . 'FileType' ]['value'] : '';
			if ( ! in_array( $type, array( 'public.jpeg', 'public.png', 'com.compuserve.gif' ), true ) ) {
				continue;
			}
			$sources[] = array(
				'url'       => str_replace( '${f}', rawurlencode( $this->rendition_filename( $filename, $type ) ), $field['value']['downloadURL'] ),
				'width'     => isset( $fields[ 'res' . $name . 'Width' ]['value'] ) ? (int) $fields[ 'res' . $name . 'Width' ]['value'] : 0,
				'height'    => isset( $fields[ 'res' . $name . 'Height' ]['value'] ) ? (int) $fields[ 'res' . $name . 'Height' ]['value'] : 0,
				'rendition' => $name,
				'type'      => $type,
			);
		}
		return $sources;
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

			$video_rendition = '';
			$video_type      = '';

			// A Live Photo is a still original with short movie renditions next to it
			// (OriginalVidCompl, VidMed, VidSmall). Only an asset whose original is a
			// movie is a video; a Live Photo is shown as the still photo.
			$original_type = isset( $master['resOriginalFileType']['value'] ) ? (string) $master['resOriginalFileType']['value'] : '';
			$is_still      = '' !== $original_type && ! in_array( $original_type, self::MOVIE_TYPES, true );

			foreach ( $master as $key => $field ) {
				if ( ! preg_match( '/^res(.+)Res$/', $key, $m ) || empty( $field['value']['downloadURL'] ) ) {
					continue;
				}

				$name   = $m[1];
				$type   = isset( $master[ 'res' . $name . 'FileType' ]['value'] ) ? (string) $master[ 'res' . $name . 'FileType' ]['value'] : '';
				$width  = isset( $master[ 'res' . $name . 'Width' ]['value'] ) ? (int) $master[ 'res' . $name . 'Width' ]['value'] : 0;
				$height = isset( $master[ 'res' . $name . 'Height' ]['value'] ) ? (int) $master[ 'res' . $name . 'Height' ]['value'] : 0;
				$url    = str_replace( '${f}', rawurlencode( $this->rendition_filename( $filename, $type ) ), $field['value']['downloadURL'] );

				if ( in_array( $type, self::MOVIE_TYPES, true ) ) {
					if ( $is_still ) {
						continue;
					}
					$rank = 'Original' === $name ? 0 : $width;
					if ( $rank > $video_w ) {
						$video           = $url;
						$video_w         = $rank;
						$video_rendition = $name;
						$video_type      = $type;
					}
					continue;
				}

				if ( ! in_array( $type, array( 'public.jpeg', 'public.png', 'com.compuserve.gif' ), true ) ) {
					continue;
				}

				$sources[] = array(
					'url'       => $url,
					'width'     => $width,
					'height'    => $height,
					'rendition' => $name,
					'type'      => $type,
				);
			}

			// A photo edited in Photos (rotated, cropped, filtered) carries its rendered
			// edit as renditions on the asset itself (resJPEGFullRes, ...). The master's
			// renditions, the original above all, are the unedited photo, so the edit wins.
			if ( '' === $video ) {
				$edited = $this->image_sources( $fields, $filename );
				if ( ! empty( $edited ) ) {
					$sources = $edited;
				}
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

			// Originals keep the camera's EXIF rotation; 5 to 8 mean the picture is shown turned by 90 degrees.
			$largest     = end( $sources );
			$orientation = isset( $fields['orientation']['value'] ) ? (int) $fields['orientation']['value'] : 1;
			if ( 'Original' === $largest['rendition'] && $orientation >= 5 && $orientation <= 8 ) {
				$largest = array_merge(
					$largest,
					array(
						'width'  => $largest['height'],
						'height' => $largest['width'],
					)
				);
			}
			$item = array(
				'url'     => $largest['url'],
				'type'    => '' !== $video ? 'video' : 'image',
				'id'      => isset( $record['recordName'] ) ? (string) $record['recordName'] : '',
				'width'   => $largest['width'],
				'height'  => $largest['height'],
				'sources' => $sources,
			);

			if ( '' !== $video ) {
				$item['video']           = $video;
				$item['video_rendition'] = $video_rendition;
			}
			if ( '' === $video && $is_still && ( in_array( $original_type, self::UNPLAYABLE_MOVIE_TYPES, true ) || preg_match( '/[.-](movie|video)$/', $original_type ) ) ) {
				// Admin hint only: the plugin shows these as photos (iCloud web shows a warning icon).
				// Keep the original name (MVI_0001.AVI), not the .jpg name the still downloads as.
				$item['unplayable_video'] = '' !== $filename ? $filename : $original_type;
			}
			if ( '' !== $filename ) {
				// Name the file after what a download gets: the video, or the largest image rendition.
				$item['filename'] = '' !== $video ? $this->rendition_filename( $filename, $video_type ) : $this->rendition_filename( $filename, $largest['type'] );
			}

			$batch_id = isset( $fields['assetBatchId']['value'] ) ? (string) $fields['assetBatchId']['value'] : '';
			$caption  = isset( $posts[ $batch_id ] ) ? $posts[ $batch_id ] : '';
			if ( '' !== $caption ) {
				$item['description'] = $caption;
			}
			if ( ! empty( $fields['assetDate']['value'] ) ) {
				$item['timestamp'] = (int) $fields['assetDate']['value'];
			}
			$item = array_merge( $item, $this->exif_fields( $master ) );

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * Tag every item with the album it came from.
	 *
	 * iCloud media URLs expire after about 15 minutes, so the orchestrator
	 * renders stable redirect URLs built from this reference plus the
	 * rendition name instead of the URLs themselves.
	 *
	 * @param array  $result    Fetch result.
	 * @param string $album_url Album URL as given in the shortcode.
	 * @return array
	 */
	private function with_media_refs( $result, $album_url ) {
		if ( empty( $result['success'] ) ) {
			return $result;
		}

		foreach ( $result['data']['photos'] as &$item ) {
			$item['media_album'] = $album_url;
		}
		unset( $item );

		return $result;
	}

	/**
	 * File name to put into a CloudKit download URL.
	 *
	 * Apple derives the response Content-Type from this name, so a video's
	 * JPEG poster must not keep the ".mp4" of the original file name.
	 *
	 * @param string $filename Original file name ('' when unknown).
	 * @param string $type     Rendition file type (UTI).
	 * @return string
	 */
	private function rendition_filename( $filename, $type ) {
		$extensions = array(
			'public.jpeg'               => 'jpg',
			'public.png'                => 'png',
			'com.compuserve.gif'        => 'gif',
			'public.mpeg-4'             => 'mp4',
			'com.apple.quicktime-movie' => 'mov',
		);
		$base       = '' !== $filename ? pathinfo( $filename, PATHINFO_FILENAME ) : 'media';

		return isset( $extensions[ $type ] ) ? $base . '.' . $extensions[ $type ] : ( '' !== $filename ? $filename : 'media' );
	}

	/**
	 * Camera placeholders from the master's image properties.
	 *
	 * CloudKit sends the original's EXIF as a binary plist in mediaMetaDataEnc,
	 * so no extra request is needed. Only camera values are kept; GPS and the
	 * rest never leave the server.
	 *
	 * @param array $master Master record fields.
	 * @return array
	 */
	private function exif_fields( $master ) {
		if ( isset( $master['mediaMetaDataType']['value'] ) && 'CGImageProperties' !== $master['mediaMetaDataType']['value'] ) {
			return array();
		}
		if ( empty( $master['mediaMetaDataEnc']['value'] ) || ! is_string( $master['mediaMetaDataEnc']['value'] ) ) {
			return array();
		}

		$properties = self::parse_binary_plist( (string) base64_decode( $master['mediaMetaDataEnc']['value'], true ) );
		if ( ! is_array( $properties ) ) {
			return array();
		}

		$tiff = isset( $properties['{TIFF}'] ) && is_array( $properties['{TIFF}'] ) ? $properties['{TIFF}'] : array();
		$exif = isset( $properties['{Exif}'] ) && is_array( $properties['{Exif}'] ) ? $properties['{Exif}'] : array();
		$iso  = isset( $exif['ISOSpeedRatings'] ) ? $exif['ISOSpeedRatings'] : null;
		if ( is_array( $iso ) ) {
			$iso = isset( $iso[0] ) ? $iso[0] : null;
		}

		$number = static function ( $value ) {
			return is_int( $value ) || is_float( $value ) ? (float) $value : null;
		};

		$provider = new JZSA_Data_Provider();

		return $provider->format_exif_fields(
			isset( $tiff['Make'] ) && is_string( $tiff['Make'] ) ? $tiff['Make'] : '',
			isset( $tiff['Model'] ) && is_string( $tiff['Model'] ) ? $tiff['Model'] : '',
			$number( isset( $exif['FNumber'] ) ? $exif['FNumber'] : null ),
			$number( isset( $exif['ExposureTime'] ) ? $exif['ExposureTime'] : null ),
			$number( isset( $exif['FocalLength'] ) ? $exif['FocalLength'] : null ),
			null === $number( $iso ) ? null : (int) $iso
		);
	}

	/**
	 * Decode an Apple binary property list (bplist00).
	 *
	 * Supports what image properties use: dictionaries, arrays, strings,
	 * integers, reals, booleans and data. Returns null for anything malformed.
	 *
	 * @param string $data Raw plist bytes.
	 * @return mixed|null
	 */
	public static function parse_binary_plist( $data ) {
		$length = strlen( $data );
		if ( $length < 40 || 0 !== strpos( $data, 'bplist00' ) ) {
			return null;
		}

		$trailer     = substr( $data, -32 );
		$offset_size = ord( $trailer[6] );
		$ref_size    = ord( $trailer[7] );
		$count       = self::read_plist_int( substr( $trailer, 8, 8 ) );
		$top         = self::read_plist_int( substr( $trailer, 16, 8 ) );
		$table       = self::read_plist_int( substr( $trailer, 24, 8 ) );
		if ( $offset_size < 1 || $ref_size < 1 || $count < 1 || $top >= $count || $table + $count * $offset_size > $length - 32 ) {
			return null;
		}

		$offsets = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$offsets[] = self::read_plist_int( substr( $data, $table + $i * $offset_size, $offset_size ) );
		}

		return self::read_plist_object( $data, $offsets, $ref_size, $top, 0 );
	}

	/**
	 * Read one object of a binary plist.
	 *
	 * @param string $data     Raw plist bytes.
	 * @param array  $offsets  Object offsets.
	 * @param int    $ref_size Object reference size in bytes.
	 * @param int    $index    Object index.
	 * @param int    $depth    Nesting depth, to stop on cycles.
	 * @return mixed|null
	 */
	private static function read_plist_object( $data, $offsets, $ref_size, $index, $depth ) {
		if ( $depth > 16 || ! isset( $offsets[ $index ] ) || $offsets[ $index ] >= strlen( $data ) ) {
			return null;
		}

		$pos    = $offsets[ $index ];
		$marker = ord( $data[ $pos ] );
		$type   = $marker >> 4;
		$info   = $marker & 0x0F;

		if ( 0x0 === $type ) {
			return 0x9 === $info ? true : ( 0x8 === $info ? false : null );
		}
		if ( 0x1 === $type ) {
			$bytes = 1 << $info;
			$value = self::read_plist_int( substr( $data, $pos + 1, $bytes ) );
			// 8-byte integers are signed.
			return ( 8 === $bytes && $value > PHP_INT_MAX ) ? null : $value;
		}
		if ( 0x2 === $type ) {
			$bytes = 1 << $info;
			$raw   = substr( $data, $pos + 1, $bytes );
			if ( ( 4 !== $bytes && 8 !== $bytes ) || strlen( $raw ) !== $bytes ) {
				return null;
			}
			// Plists are big-endian; unpack's 'f'/'d' use machine order (PHP 7.0 has no 'G'/'E').
			if ( "\x01\x00" === pack( 'S', 1 ) ) {
				$raw = strrev( $raw );
			}
			$value = unpack( 4 === $bytes ? 'f' : 'd', $raw );
			return (float) $value[1];
		}

		$size  = $info;
		$start = $pos + 1;
		if ( 0xF === $info && in_array( $type, array( 0x4, 0x5, 0x6, 0xA, 0xD ), true ) ) {
			$size_marker = ord( $data[ $start ] );
			if ( 0x1 !== ( $size_marker >> 4 ) ) {
				return null;
			}
			$bytes = 1 << ( $size_marker & 0x0F );
			$size  = self::read_plist_int( substr( $data, $start + 1, $bytes ) );
			$start = $start + 1 + $bytes;
		}

		switch ( $type ) {
			case 0x4:
				return substr( $data, $start, $size );
			case 0x5:
				return substr( $data, $start, $size );
			case 0x6:
				$raw = substr( $data, $start, $size * 2 );
				return function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $raw, 'UTF-8', 'UTF-16BE' ) : '';
			case 0xA:
				$list = array();
				for ( $i = 0; $i < $size; $i++ ) {
					$ref    = self::read_plist_int( substr( $data, $start + $i * $ref_size, $ref_size ) );
					$list[] = self::read_plist_object( $data, $offsets, $ref_size, $ref, $depth + 1 );
				}
				return $list;
			case 0xD:
				$dict = array();
				for ( $i = 0; $i < $size; $i++ ) {
					$key_ref   = self::read_plist_int( substr( $data, $start + $i * $ref_size, $ref_size ) );
					$value_ref = self::read_plist_int( substr( $data, $start + ( $size + $i ) * $ref_size, $ref_size ) );
					$key       = self::read_plist_object( $data, $offsets, $ref_size, $key_ref, $depth + 1 );
					if ( is_string( $key ) ) {
						$dict[ $key ] = self::read_plist_object( $data, $offsets, $ref_size, $value_ref, $depth + 1 );
					}
				}
				return $dict;
		}

		return null;
	}

	/**
	 * Read a big-endian unsigned integer.
	 *
	 * @param string $bytes Raw bytes.
	 * @return int
	 */
	private static function read_plist_int( $bytes ) {
		$value = 0;
		$len   = strlen( $bytes );
		for ( $i = 0; $i < $len; $i++ ) {
			$value = $value * 256 + ord( $bytes[ $i ] );
		}

		return (int) $value;
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
