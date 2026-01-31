<?php
/**
 * Main Plugin Orchestrator
 *
 * Orchestrates interaction between data provider and renderer
 * Handles WordPress integration, caching, and shortcode processing
 *
 * Smart Caching Strategy:
 * - Cache key based on MD5(URL) for global album caching
 * - Stores only base photo URLs (without dimensions) in cache
 * - Tracks cache expiration separately in wp_options
 * - Allows same album to be reused across multiple posts
 * - Invalidates cache if CACHE_DURATION constant changes
 *
 * @package JZSA_Shared_Albums
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Orchestrator Class
 */
class JZSA_Shared_Albums {


	/**
	 * Cache duration in seconds (24 hours)
	 *
	 * @var int
	 */
	const CACHE_DURATION = 86400;

	/**
	 * Default gallery dimensions
	 *
	 * @var int
	 */
	const DEFAULT_WIDTH = 267;
	const DEFAULT_HEIGHT = 200;

	/**
	 * Default full-resolution image dimensions (fetched from Google Photos)
	 *
	 * @var int
	 */
	const DEFAULT_IMAGE_WIDTH = 1920;
	const DEFAULT_IMAGE_HEIGHT = 1440;

	/**
	 * Default preview/thumbnail image dimensions (for progressive loading)
	 *
	 * @var int
	 */
	const DEFAULT_PREVIEW_WIDTH = 800;
	const DEFAULT_PREVIEW_HEIGHT = 600;

	/**
	 * Maximum number of photos to load from album (absolute upper bound).
	 *
	 * @var int
	 */
	const MAX_PHOTOS = 300;

	/**
	 * Default maximum number of photos per album when not overridden via shortcode.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_PHOTOS_PER_ALBUM = 300;

	/**
	 * Default autoplay delay range (in seconds) - for normal mode
	 *
	 * @var string
	 */
	const DEFAULT_AUTOPLAY_DELAY_RANGE = '4-12';

	/**
	 * Default fullscreen autoplay delay (in seconds) - can be single value or range
	 *
	 * @var string
	 */
	const DEFAULT_FULLSCREEN_AUTOPLAY_DELAY = '3';

	/**
	 * Default autoplay inactivity timeout (in seconds) - time after which autoplay resumes after user interaction
	 *
	 * @var string
	 */
	const DEFAULT_AUTOPLAY_INACTIVITY_TIMEOUT = '30';

	/**
	 * Data provider instance
	 *
	 * @var JZSA_Data_Provider
	 */
	private $provider;

	/**
	 * Renderer instance
	 *
	 * @var JZSA_Renderer
	 */
	private $renderer;

	/**
	 * Plugin base file path
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Constructor - Initialize plugin
	 *
	 * @param string $plugin_file Plugin base file path
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->provider    = new JZSA_Data_Provider();
		$this->renderer    = new JZSA_Renderer();

		add_shortcode( 'jzsa-album', array( $this, 'handle_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'save_post', array( $this, 'clear_cache' ) );
		add_action( 'wp_ajax_jzsa_download_image', array( $this, 'handle_download_image' ) );
		add_action( 'wp_ajax_nopriv_jzsa_download_image', array( $this, 'handle_download_image' ) );

		// Also load front-end gallery assets on our settings page so the sample
		// shortcode preview works inside the admin.
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

/**
	 * Enqueue front-end gallery assets on the plugin's settings page in admin.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_janzeman-shared-albums-for-google-photos' !== $hook ) {
			return;
		}

		$this->enqueue_assets();
	}

/**
	 * Enqueue CSS and JavaScript assets
	 *
	 */
	public function enqueue_assets() {
		// Swiper library (bundled locally)
		wp_enqueue_style(
			'swiper-css',
			plugins_url( 'assets/vendor/swiper/swiper-bundle.min.css', $this->plugin_file ),
			array(),
			'11.0.0'
		);

		wp_enqueue_script(
			'swiper-js',
			plugins_url( 'assets/vendor/swiper/swiper-bundle.min.js', $this->plugin_file ),
			array(),
			'11.0.0',
			true
		);

		// Custom assets
		wp_enqueue_style(
			'jzsa-style',
			plugins_url( 'assets/css/swiper-style.css', $this->plugin_file ),
			array( 'swiper-css' ),
			JZSA_VERSION
		);

		wp_enqueue_script(
			'jzsa-init',
			plugins_url( 'assets/js/swiper-init.js', $this->plugin_file ),
			array( 'jquery', 'swiper-js' ),
			JZSA_VERSION,
			true
		);

		// Localize script for AJAX
		wp_localize_script(
			'jzsa-init',
			'jzsaAjax',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'jzsa_download_nonce' ),
			)
		);
	}

	/**
	 * Handle shortcode rendering
	 *
	 * @param array $atts Shortcode attributes
	 * @return string|null Rendered HTML or null
	 */
	public function handle_shortcode( $atts ) {
		if ( empty( $atts ) ) {
			return null;
		}

		// Extract album URL
		$album_url = isset( $atts['link'] ) ? $atts['link'] : ( isset( $atts[0] ) ? $atts[0] : null );

		if ( empty( $album_url ) ) {
			return null;
		}

		// Parse configuration from shortcode attributes
		$config = $this->parse_shortcode_config( $atts, $album_url );

		// Smart caching: Check cache and expiration tracking
		$cache_key      = $this->get_cache_key( $album_url );
		$expiry_key     = $this->get_expiration_key( $album_url );
		$cached_data    = get_transient( $cache_key );
		$stored_expiry  = get_option( $expiry_key, 0 );
		$should_refresh = false;

		// Refresh if no cache OR if cache duration setting changed
		if ( false === $cached_data || (int) $stored_expiry !== self::CACHE_DURATION ) {
			$should_refresh = true;
		}

		if ( ! $should_refresh && false !== $cached_data ) {
			// Use cached data - merge with current config
			$config['photos'] = $this->prepare_photo_urls(
				$cached_data['photos'],
				$config['image-width'],
				$config['image-height'],
				self::DEFAULT_PREVIEW_WIDTH,
				self::DEFAULT_PREVIEW_HEIGHT,
				$config['max-photos-per-album']
			);
			$config['album-title']              = $cached_data['title'] ?? null;
			$config['show-deprecation-warning'] = $cached_data['is_deprecated'];

			return $this->renderer->render( $config );
		}

		// Fetch fresh data from provider (INPUT PHASE)
		$result = $this->provider->fetch_album( $album_url );

		if ( ! $result['success'] ) {
			return $this->render_fetch_error( $result['error'] );
		}

		// Cache the fetched BASE photo URLs (without dimensions) and title
		// This allows re-rendering with different sizes without re-fetching
		set_transient(
			$cache_key,
			array(
				'title'         => $result['data']['title'] ?? null,
				'photos'        => $result['data']['photos'], // Base URLs without dimensions
				'is_deprecated' => $result['is_deprecated'],
			),
			self::CACHE_DURATION
		);

		// Store expiration duration for tracking
		update_option( $expiry_key, self::CACHE_DURATION, false );

		// Prepare photos with dimensions and max count
		$config['photos'] = $this->prepare_photo_urls(
			$result['data']['photos'],
			$config['image-width'],
			$config['image-height'],
			self::DEFAULT_PREVIEW_WIDTH,
			self::DEFAULT_PREVIEW_HEIGHT,
			$config['max-photos-per-album']
		);

		$config['album-title']              = $result['data']['title'] ?? null;
		$config['show-deprecation-warning'] = $result['is_deprecated'];

		// Render output (OUTPUT PHASE)
		return $this->renderer->render( $config );
	}

	/**
	 * Parse shortcode attributes into configuration array
	 *
	 * @param array  $atts Shortcode attributes
	 * @param string $url  Album URL
	 * @return array Configuration
	 */
	private function parse_shortcode_config( $atts, $url ) {
		$config = array(
			// URL
			'album-url' => $url,

			// Dimensions
			'width'          => $this->parse_dimension( $atts, 'width', self::DEFAULT_WIDTH ),
			'height'         => $this->parse_dimension( $atts, 'height', self::DEFAULT_HEIGHT ),
			'image-width'    => isset( $atts['image-width'] ) ? intval( $atts['image-width'] ) : self::DEFAULT_IMAGE_WIDTH,
			'image-height'   => isset( $atts['image-height'] ) ? intval( $atts['image-height'] ) : self::DEFAULT_IMAGE_HEIGHT,
			// Autoplay (normal mode)
			'autoplay'       => $this->parse_bool( $atts, 'autoplay', true ),
			'autoplay-delay' => $this->parse_delay_range( isset( $atts['autoplay-delay'] ) ? $atts['autoplay-delay'] : self::DEFAULT_AUTOPLAY_DELAY_RANGE ),
			'start-at'       => $this->parse_start_at( $atts ),

			// Fullscreen autoplay (fullscreen mode only)
			'full-screen-autoplay'       => $this->parse_bool( $atts, 'full-screen-autoplay', true ),
			'full-screen-autoplay-delay' => $this->parse_delay_range( isset( $atts['full-screen-autoplay-delay'] ) ? $atts['full-screen-autoplay-delay'] : self::DEFAULT_FULLSCREEN_AUTOPLAY_DELAY ),

			// Autoplay inactivity timeout
			'autoplay-inactivity-timeout' => isset( $atts['autoplay-inactivity-timeout'] ) ? intval( $atts['autoplay-inactivity-timeout'] ) : intval( self::DEFAULT_AUTOPLAY_INACTIVITY_TIMEOUT ),

			// Display
			'mode'             => $this->parse_mode( $atts ),
			'background-color' => $this->parse_color( $atts ),
			'image-fit'       => $this->parse_image_fit( $atts ),
			'full-screen-switch'     => $this->parse_fullscreen_switch_mode( $atts ),
			'full-screen-navigation' => $this->parse_fullscreen_navigation_mode( $atts ),
			'show-title'             => $this->parse_bool( $atts, 'show-title', false ),
			'show-counter'           => $this->parse_show_counter( $atts ),
			'show-link-button'       => $this->parse_bool( $atts, 'show-link-button', false ),
			'show-download-button'   => $this->parse_bool( $atts, 'show-download-button', false ),

			// Photo count
			'max-photos-per-album'    => $this->parse_max_photos( $atts ),
		);

		return $config;
	}

	/**
	 * Parse dimension attribute
	 *
	 * @param array  $atts    Attributes
	 * @param string $key     Attribute key
	 * @param int    $default Default value
	 * @return int|string Dimension value or 'auto'
	 */
	private function parse_dimension( $atts, $key, $default ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return $default;
		}

		if ( 'auto' === strtolower( $atts[ $key ] ) ) {
			return 'auto';
		}

		$value = intval( $atts[ $key ] );
		return $value > 0 ? $value : $default;
	}

	/**
	 * Parse boolean attribute
	 *
	 * @param array  $atts    Attributes
	 * @param string $key     Attribute key
	 * @param bool   $default Default value
	 * @return bool Boolean value
	 */
	private function parse_bool( $atts, $key, $default ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return $default;
		}

		return 'true' === strtolower( $atts[ $key ] );
	}

	/**
	 * Parse counter visibility.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return bool
	 */
	private function parse_show_counter( $atts ) {
		// Preferred parameter: show-counter (defaults to true when omitted).
		if ( isset( $atts['show-counter'] ) ) {
			return $this->parse_bool( $atts, 'show-counter', true );
		}

		// Default: counter visible.
		return true;
	}

	/**
	 * Parse starting slide position.
	 *
	 * New parameter: start-at="random" (default) or a 1-based slide number.
	 * Legacy support: legacy boolean flag mapping to "random" or "1".
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string "random" or a numeric string >= 1
	 */
	private function parse_start_at( $atts ) {
		// New parameter takes precedence.
		if ( isset( $atts['start-at'] ) ) {
			$value = strtolower( trim( (string) $atts['start-at'] ) );

			if ( '' === $value || 'random' === $value ) {
				return 'random';
			}

			if ( is_numeric( $value ) ) {
				$number = intval( $value );
				if ( $number >= 1 ) {
					return (string) $number;
				}
			}

			// Fallback to random on invalid input.
			return 'random';
		}

		// Backward compatibility: interpret legacy boolean flag.
		if ( isset( $atts['start-at-random-photo'] ) ) {
			$random_flag = $this->parse_bool( $atts, 'start-at-random-photo', true );
			return $random_flag ? 'random' : '1';
		}

		// Default.
		return 'random';
	}

	/**
	 * Parse image fit mode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string One of 'contain', 'cover', or 'stretch'.
	 */
	private function parse_image_fit( $atts ) {
		if ( ! isset( $atts['image-fit'] ) ) {
			// Default: cover (matches previous default cropping behaviour)
			return 'cover';
		}

		$value = strtolower( trim( (string) $atts['image-fit'] ) );

		if ( in_array( $value, array( 'contain', 'cover', 'stretch' ), true ) ) {
			return $value;
		}

		// Fallback to cover on invalid input.
		return 'cover';
	}

	/**
	 * Parse delay range attribute (supports ranges like "4-12" or single values like "3")
	 *
	 * @param string|int $value Delay value (can be range like "4-12" or single value)
	 * @return int Delay in seconds (random value if range provided)
	 */
	private function parse_delay_range( $value ) {
		$delay = strval( $value );

		if ( strpos( $delay, '-' ) !== false ) {
			list( $min, $max ) = explode( '-', $delay, 2 );
			return wp_rand( intval( $min ), intval( $max ) );
		}

		return intval( $delay );
	}

	/**
	 * Parse background color attribute
	 *
	 * @param array $atts Attributes
	 * @return string|null Color value or null
	 */
	private function parse_color( $atts ) {
		if ( ! isset( $atts['background-color'] ) ) {
			return '#FFFFFF';
		}

		$color = $atts['background-color'];

		if ( 'transparent' === strtolower( $color ) ) {
			return 'transparent';
		}

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $color ) ) {
			return $color;
		}

		return '#FFFFFF';
	}

	/**
	 * Parse mode attribute
	 *
	 * @param array $atts Attributes
	 * @return string Mode: 'carousel' or 'player'
	 */
	private function parse_mode( $atts ) {
		if ( ! isset( $atts['mode'] ) ) {
			// Default to 'player'
			return 'player';
		}

		$mode = strtolower( trim( $atts['mode'] ) );

		// Valid modes: 'carousel', 'player', 'carousel-to-player'
		$valid_modes = array( 'carousel', 'player', 'carousel-to-player' );

		if ( in_array( $mode, $valid_modes, true ) ) {
			return $mode;
		}

		// Default fallback
		return 'player';
	}

	/**
	 * Parse full screen switch mode attribute
	 *
	 * @param array $atts Attributes
	 * @return string Full screen switch mode: 'button-only', 'single-click', or 'double-click'
	 */
	private function parse_fullscreen_switch_mode( $atts ) {
		if ( ! isset( $atts['full-screen-switch'] ) ) {
			// Default to 'double-click'
			return 'double-click';
		}

		$mode = strtolower( trim( $atts['full-screen-switch'] ) );

		// Valid modes: 'button-only', 'single-click', 'double-click'
		$valid_modes = array( 'button-only', 'single-click', 'double-click' );

		if ( in_array( $mode, $valid_modes, true ) ) {
			return $mode;
		}

		// Default fallback
		return 'double-click';
	}

		/**
	 * Parse full screen navigation mode attribute
	 *
	 * @param array $atts Attributes
	 * @return string Full screen navigation mode: 'buttons-only', 'single-click', or 'double-click'
	 */
	private function parse_fullscreen_navigation_mode( $atts ) {
		if ( ! isset( $atts['full-screen-navigation'] ) ) {
			// Default to 'single-click'
			return 'single-click';
		}

		$mode = strtolower( trim( $atts['full-screen-navigation'] ) );

		// Valid modes: 'buttons-only', 'single-click', 'double-click'
		$valid_modes = array( 'buttons-only', 'single-click', 'double-click' );

		if ( in_array( $mode, $valid_modes, true ) ) {
			return $mode;
		}

		// Default fallback
		return 'single-click';
	}

	/**
	 * Parse max-photos-per-album attribute.
	 *
	 * Clamps the value between 1 and self::MAX_PHOTOS.
	 *
	 * @param array $atts Attributes.
	 * @return int
	 */
	private function parse_max_photos( $atts ) {
		if ( ! isset( $atts['max-photos-per-album'] ) ) {
			return self::DEFAULT_MAX_PHOTOS_PER_ALBUM;
		}

		$value = intval( $atts['max-photos-per-album'] );

		if ( $value <= 0 ) {
			return self::DEFAULT_MAX_PHOTOS_PER_ALBUM;
		}

		if ( $value > self::MAX_PHOTOS ) {
			return self::MAX_PHOTOS;
		}

		return $value;
	}

	/**
	 * Prepare photo URLs with dimensions (including preview and full sizes).
	 *
	 * @param array $base_urls      Base photo URLs.
	 * @param int   $full_width     Full image width.
	 * @param int   $full_height    Full image height.
	 * @param int   $preview_width  Preview image width (optional).
	 * @param int   $preview_height Preview image height (optional).
	 * @param int   $max_photos     Maximum number of photos to include from the album.
	 * @return array Photo objects with preview and full URLs.
	 */
	private function prepare_photo_urls( $base_urls, $full_width, $full_height, $preview_width = null, $preview_height = null, $max_photos = self::DEFAULT_MAX_PHOTOS_PER_ALBUM ) {
		// Determine effective limit: requested per-album limit clamped to global MAX_PHOTOS.
		$limit = intval( $max_photos );

		if ( $limit <= 0 ) {
			$limit = self::DEFAULT_MAX_PHOTOS_PER_ALBUM;
		}

		if ( $limit > self::MAX_PHOTOS ) {
			$limit = self::MAX_PHOTOS;
		}

		$base_urls = array_slice( $base_urls, 0, $limit );

		$photos = array();
		foreach ( $base_urls as $base ) {
			$photo = array(
				'full' => sprintf( '%s=w%d-h%d', $base, $full_width, $full_height ),
			);

			// Add preview URL if dimensions provided.
			if ( $preview_width && $preview_height ) {
				$photo['preview'] = sprintf( '%s=w%d-h%d', $base, $preview_width, $preview_height );
			}

			$photos[] = $photo;
		}

		return $photos;
	}

	/**
	 * Render error message for fetch failures
	 *
	 * @param string $error Error message
	 * @return string HTML
	 */
	private function render_fetch_error( $error ) {
		if ( strpos( $error, 'Invalid' ) !== false ) {
			return $this->renderer->render_error(
				__( 'Invalid Google Photos URL', 'janzeman-shared-albums-for-google-photos' ),
				__( 'The URL provided is not a valid Google Photos share link.', 'janzeman-shared-albums-for-google-photos' ),
				__( 'Please use a valid Google Photos URL, ideally in its full form: https://photos.google.com/share/... ', 'janzeman-shared-albums-for-google-photos' ) .
					' <a href="https://support.google.com/photos/answer/6131416" target="_blank" rel="noopener">' .
					esc_html__( 'How to share a Google Photos album', 'janzeman-shared-albums-for-google-photos' ) . '</a>'
			);
		}

		if ( strpos( $error, 'No photos' ) !== false ) {
			return $this->renderer->render_error(
				__( 'No Photos Found', 'janzeman-shared-albums-for-google-photos' ),
				__( 'No photos were found in this album.', 'janzeman-shared-albums-for-google-photos' ),
				__( 'The album might be empty or the sharing link might have expired. Please check the album and try again.', 'janzeman-shared-albums-for-google-photos' )
			);
		}

		return $this->renderer->render_error(
			__( 'Unable to Load Album', 'janzeman-shared-albums-for-google-photos' ),
			__( 'Could not fetch the Google Photos album.', 'janzeman-shared-albums-for-google-photos' ),
			__( 'The album might be private or the link might be incorrect. Make sure the album is publicly shared. ', 'janzeman-shared-albums-for-google-photos' ) .
				' <a href="https://support.google.com/photos/answer/6131416" target="_blank" rel="noopener">' .
					esc_html__( 'Check album sharing settings', 'janzeman-shared-albums-for-google-photos' ) . '</a>'
		);
	}

	/**
	 * Get cache key for album URL
	 * Uses MD5 hash of URL for global caching (independent of post)
	 *
	 * @param string $url Album URL
	 * @return string Cache key
	 */
	private function get_cache_key( $url ) {
		return 'jzsa_album_' . md5( $url );
	}

	/**
	 * Get cache expiration option key
	 *
	 * @param string $url Album URL
	 * @return string Option key
	 */
	private function get_expiration_key( $url ) {
		return 'jzsa_expiry_' . md5( $url );
	}

	/**
	 * Clear all cached galleries for a post
	 * Also clears global album caches for albums used in this post
	 *
	 * @param int $post_id Post ID
	 */
	public function clear_cache( $post_id ) {
		// Get post content to find album URLs
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Find all jzsa-album shortcodes in post content
		if ( preg_match_all( '/\[jzsa-album[^\]]*link=["\']([^"\']+)["\'][^\]]*\]/i', $post->post_content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$cache_key = $this->get_cache_key( $url );
				delete_transient( $cache_key );

				// Also delete expiration tracking
				$expiry_key = $this->get_expiration_key( $url );
				delete_option( $expiry_key );
			}
		}
	}

/**
	 * Handle AJAX request to download image
	 * Proxies the image download to bypass CORS restrictions
	 *
	 */
	public function handle_download_image() {
		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'jzsa_download_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		// Get image URL.
		if ( ! isset( $_POST['image_url'] ) || empty( $_POST['image_url'] ) ) {
			wp_send_json_error( __( 'Missing image URL', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$image_url = esc_url_raw( wp_unslash( $_POST['image_url'] ) );
		$filename  = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : 'photo.jpg';

		// Verify it's a Google Photos image URL.
		$parsed_url = wp_parse_url( $image_url );
		if ( empty( $parsed_url['scheme'] ) || 'https' !== $parsed_url['scheme'] || empty( $parsed_url['host'] ) ) {
			wp_send_json_error( __( 'Invalid image URL', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		$host = strtolower( $parsed_url['host'] );
		if ( 'googleusercontent.com' !== $host && substr( $host, -strlen( '.googleusercontent.com' ) ) !== '.googleusercontent.com' ) {
			wp_send_json_error( __( 'Invalid image URL', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		// Fetch the image
		$response = wp_remote_get(
			$image_url,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s: error message returned from WordPress HTTP API */
					__( 'Failed to fetch image: %s', 'janzeman-shared-albums-for-google-photos' ),
					$response->get_error_message()
				)
			);
			return;
		}

		// Optional: guard against excessively large files.
		$max_size_bytes = (int) apply_filters( 'jzsa_max_download_size', 50 * 1024 * 1024 ); // 50 MB default.
		$content_length = wp_remote_retrieve_header( $response, 'content-length' );

		if ( $max_size_bytes > 0 && $content_length && (int) $content_length > $max_size_bytes ) {
			wp_send_json_error( __( 'Image is too large to download.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		// Get image data
		$image_data = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		if ( $max_size_bytes > 0 && ( ! $content_length ) && strlen( $image_data ) > $max_size_bytes ) {
			wp_send_json_error( __( 'Image is too large to download.', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		if ( empty( $image_data ) ) {
			wp_send_json_error( __( 'Empty image data', 'janzeman-shared-albums-for-google-photos' ) );
			return;
		}

		// Send image to browser with download headers.
		header( 'Content-Type: ' . ( $content_type ? $content_type : 'image/jpeg' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $image_data ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Binary image data must be sent unescaped.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $image_data;
		exit;
	}
}
