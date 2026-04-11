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
 * - Invalidates cache if cache-refresh interval changes
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
	 * Default cache refresh interval in hours (7 days).
	 * Can be overridden per shortcode via the cache-refresh attribute.
	 *
	 * @var int
	 */
	const DEFAULT_CACHE_REFRESH = 168;

	/**
	 * Per-photo metadata cache TTL in seconds.
	 *
	 * Individual photo metadata is effectively immutable, so keep it cached much
	 * longer than album HTML.
	 *
	 * @var int
	 */
	const PHOTO_META_CACHE_TTL = 2592000; // 30 days.

	/**
	 * Default gallery dimensions
	 *
	 * @var int
	 */
	const DEFAULT_WIDTH = 400;
	const DEFAULT_HEIGHT = 300;

	/**
	 * Default source dimensions for inline mode (fetched from Google Photos)
	 *
	 * @var int
	 */
	const DEFAULT_SOURCE_WIDTH = 800;
	const DEFAULT_SOURCE_HEIGHT = 600;

	/**
	 * Default source dimensions for fullscreen mode (fetched from Google Photos)
	 *
	 * @var int
	 */
	const DEFAULT_FULLSCREEN_SOURCE_WIDTH = 1920;
	const DEFAULT_FULLSCREEN_SOURCE_HEIGHT = 1440;

	/**
	 * Default thumbnail dimensions for mosaic (retina optimized)
	 *
	 * @var int
	 */
	const DEFAULT_THUMB_WIDTH = 400;
	const DEFAULT_THUMB_HEIGHT = 400;

	/**
	 * Maximum number of media entries to load from album (absolute upper bound).
	 *
	 * @var int
	 */
	const MAX_PHOTOS = 300;

	/**
	 * Default maximum number of media entries per album when not overridden via shortcode.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_PHOTOS_PER_ALBUM = 300;

	/**
	 * Use progressive chunk loading for large slider-only photo albums.
	 *
	 * Keeps initial HTML payload small and lets the browser fetch additional
	 * photos on demand instead of embedding the full album JSON up front.
	 *
	 * PROGRESSIVE_SLIDER_THRESHOLD is intentionally set to ~2.5× PROGRESSIVE_SLIDER_INITIAL_CHUNK.
	 * If the album fits in roughly two initial chunks the user would exhaust it in the first few
	 * swipes anyway, so the AJAX overhead, nonce dependency, and loss of infinite loop aren't
	 * worth it. Adjust THRESHOLD proportionally whenever INITIAL_CHUNK changes.
	 *
	 * @var int
	 */
	const PROGRESSIVE_SLIDER_INITIAL_CHUNK = 24;
	const PROGRESSIVE_SLIDER_CHUNK_SIZE    = 24;
	const PROGRESSIVE_SLIDER_THRESHOLD     = 60; // ~2.5 × PROGRESSIVE_SLIDER_INITIAL_CHUNK

	/**
	 * Hard maximum download size for proxied downloads (MB).
	 * Set to 0 to disable the hard limit (not recommended).
	 *
	 * @var int
	 */
	const MAX_DOWNLOAD_SIZE_MB = 512;

	/**
	 * Default large-download warning threshold for proxied downloads (MB).
	 * Set to 0 to disable the warning/confirmation dialog.
	 *
	 * @var int
	 */
	const DEFAULT_DOWNLOAD_WARNING_SIZE_MB = 128;

	/**
	 * Default slideshow delay range (in seconds) - for normal mode
	 *
	 * @var string
	 */
	const DEFAULT_SLIDESHOW_DELAY_RANGE = '5';

	/**
	 * Default fullscreen slideshow delay (in seconds) - can be single value or range
	 *
	 * @var string
	 */
	const DEFAULT_FULLSCREEN_SLIDESHOW_DELAY = '5';

	/**
	 * Default slideshow inactivity timeout (in seconds) - time after which slideshow resumes after user interaction
	 *
	 * @var string
	 */
	const DEFAULT_SLIDESHOW_INACTIVITY_TIMEOUT = '30';

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
		add_action( 'wp_ajax_jzsa_refresh_urls', array( $this, 'handle_refresh_urls' ) );
		add_action( 'wp_ajax_nopriv_jzsa_refresh_urls', array( $this, 'handle_refresh_urls' ) );
		add_action( 'wp_ajax_jzsa_shortcode_preview', array( $this, 'handle_shortcode_preview' ) );
		add_action( 'wp_ajax_jzsa_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'wp_ajax_jzsa_fetch_photo_meta', array( $this, 'handle_fetch_photo_meta' ) );
		add_action( 'wp_ajax_nopriv_jzsa_fetch_photo_meta', array( $this, 'handle_fetch_photo_meta' ) );
		add_action( 'wp_ajax_jzsa_fetch_album_chunk', array( $this, 'handle_fetch_album_chunk' ) );
		add_action( 'wp_ajax_nopriv_jzsa_fetch_album_chunk', array( $this, 'handle_fetch_album_chunk' ) );

		// Also load front-end gallery assets on our Guide page so the sample
		// shortcode preview works inside the admin.
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Enqueue front-end gallery assets on the plugin's Guide page in admin.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		unset( $hook );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! class_exists( 'JZSA_Admin_Pages' ) || JZSA_Admin_Pages::MENU_SLUG !== $page ) {
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

		// Plyr video player (bundled locally)
		wp_enqueue_style(
			'plyr-css',
			plugins_url( 'assets/vendor/plyr/plyr.css', $this->plugin_file ),
			array(),
			'3.7.8'
		);

		wp_enqueue_script(
			'plyr-js',
			plugins_url( 'assets/vendor/plyr/plyr.min.js', $this->plugin_file ),
			array(),
			'3.7.8',
			true
		);

		// Custom assets
		$style_version = $this->get_asset_version( 'assets/css/swiper-style.css' );
		$script_version = $this->get_asset_version( 'assets/js/swiper-init.js' );

		wp_enqueue_style(
			'jzsa-style',
			plugins_url( 'assets/css/swiper-style.css', $this->plugin_file ),
			array( 'swiper-css', 'plyr-css' ),
			$style_version
		);

		wp_enqueue_script(
			'jzsa-init',
			plugins_url( 'assets/js/swiper-init.js', $this->plugin_file ),
			array( 'jquery', 'swiper-js', 'plyr-js' ),
			$script_version,
			true
		);

		// Localize script for AJAX
		$download_nonce = wp_create_nonce( 'jzsa_download_nonce' );
		$preview_nonce  = wp_create_nonce( 'jzsa_shortcode_preview' );
		$refresh_nonce  = wp_create_nonce( 'jzsa_refresh_urls' );
		$chunk_nonce    = wp_create_nonce( 'jzsa_album_chunk' );

		$photo_meta_nonce = wp_create_nonce( 'jzsa_photo_meta' );

		wp_localize_script(
			'jzsa-init',
			'jzsaAjax',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'downloadNonce'  => $download_nonce,
				'previewNonce'   => $preview_nonce,
				'refreshNonce'   => $refresh_nonce,
				'chunkNonce'     => $chunk_nonce,
				'photoMetaNonce' => $photo_meta_nonce,
				'plyrSvgUrl'     => plugins_url( 'assets/vendor/plyr/plyr.svg', $this->plugin_file ),
			)
		);
	}

	/**
	 * Build an asset version that busts caches when file content changes.
	 *
	 * @param string $relative_path Path relative to plugin root.
	 * @return string Version string.
	 */
	private function get_asset_version( $relative_path ) {
		$relative_path = ltrim( $relative_path, '/\\' );
		$full_path = plugin_dir_path( $this->plugin_file ) . $relative_path;

		if ( file_exists( $full_path ) ) {
			$mtime = filemtime( $full_path );
			if ( false !== $mtime ) {
				return JZSA_VERSION . '.' . intval( $mtime );
			}
		}

		return JZSA_VERSION;
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
		$cache_key        = $this->get_cache_key( $album_url );
		$expiry_key       = $this->get_expiration_key( $album_url );
		$cached_data      = get_transient( $cache_key );
		$stored_expiry    = get_option( $expiry_key, 0 );
		$cache_duration   = $config['cache-refresh'] * 3600; // convert hours to seconds
		$should_refresh   = false;

		// Refresh if no cache OR if cache-refresh interval changed
		if ( false === $cached_data || (int) $stored_expiry !== $cache_duration ) {
			$should_refresh = true;
		}

		if ( ! $should_refresh && false !== $cached_data ) {
			// Use cached data - merge with current config
				$hydrated_cached_items = $this->hydrate_cached_photo_meta(
					$cached_data['photos'],
					$album_url,
					$config['limit'],
					$config['show-videos']
				);
				$config['photos'] = $this->prepare_photo_urls(
					$hydrated_cached_items,
					$config['fullscreen-source-width'],
					$config['fullscreen-source-height'],
					$config['source-width'],
					$config['source-height'],
					$config['limit'],
					$config['show-videos']
				);
			$config['album-title']              = $cached_data['title'] ?? null;
			$config['show-deprecation-warning'] = $cached_data['is_deprecated'];
			$config                            = $this->apply_progressive_slider_loading_config( $config );

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
			$cache_duration
		);

		// Store expiration duration for tracking
		update_option( $expiry_key, $cache_duration, false );

		// Prepare photos with dimensions and max count
			$hydrated_fresh_items = $this->hydrate_cached_photo_meta(
				$result['data']['photos'],
				$album_url,
				$config['limit'],
				$config['show-videos']
			);
			$config['photos'] = $this->prepare_photo_urls(
				$hydrated_fresh_items,
				$config['fullscreen-source-width'],
				$config['fullscreen-source-height'],
				$config['source-width'],
				$config['source-height'],
				$config['limit'],
				$config['show-videos']
			);

		$config['album-title']              = $result['data']['title'] ?? null;
		$config['show-deprecation-warning'] = $result['is_deprecated'];
		$config                            = $this->apply_progressive_slider_loading_config( $config );

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
		$mode                 = $this->parse_mode( $atts );
		$show_navigation      = $this->parse_bool( $atts, 'show-navigation', true );
		$show_link_button     = $this->parse_bool( $atts, 'show-link-button', false );
		$show_download_button = $this->parse_bool( $atts, 'show-download-button', false );
		$controls_color       = $this->parse_color( $atts, 'controls-color', '#ffffff' );
		$video_controls_color = $this->parse_color( $atts, 'video-controls-color', '#00b2ff' );
		$video_controls_autohide = $this->parse_bool( $atts, 'video-controls-autohide', false );
		$info_font_size       = $this->parse_info_font_size( $atts, 'info-font-size', 12 );
		$info_font_family     = $this->parse_info_font_family( $atts, 'info-font-family', '' );
		$info_font_color      = $this->parse_color( $atts, 'info-font-color', '' );
		$info_halo_effect     = $this->parse_bool( $atts, 'info-halo-effect', true );
		$info_top_halo_effect = $this->parse_optional_bool( $atts, 'info-top-halo-effect' );
		$info_top_secondary_halo_effect = $this->parse_optional_bool( $atts, 'info-top-secondary-halo-effect' );
		$info_bottom_halo_effect = $this->parse_optional_bool( $atts, 'info-bottom-halo-effect' );
		$gallery_info_bottom_halo_effect = $this->parse_optional_bool( $atts, 'gallery-info-bottom-halo-effect' );
		$album_title_halo_effect = $this->parse_optional_bool( $atts, 'album-title-halo-effect' );
		$info_wrap            = $this->parse_bool( $atts, 'info-wrap', false );
		$allowed_alignments   = array( 'left', 'center', 'right' );
		$info_text_align      = ( isset( $atts['info-text-align'] ) && in_array( $atts['info-text-align'], $allowed_alignments, true ) )
			? $atts['info-text-align']
			: 'center';
		$info_top_text_align           = ( isset( $atts['info-top-text-align'] ) && in_array( $atts['info-top-text-align'], $allowed_alignments, true ) )
			? $atts['info-top-text-align']
			: null;
		$info_top_secondary_text_align = ( isset( $atts['info-top-secondary-text-align'] ) && in_array( $atts['info-top-secondary-text-align'], $allowed_alignments, true ) )
			? $atts['info-top-secondary-text-align']
			: null;
		$info_bottom_text_align        = ( isset( $atts['info-bottom-text-align'] ) && in_array( $atts['info-bottom-text-align'], $allowed_alignments, true ) )
			? $atts['info-bottom-text-align']
			: null;
		$slideshow_autoresume = $this->parse_slideshow_autoresume(
			$atts,
			array( 'slideshow-autoresume', 'slideshow-autoresume-timeout', 'slideshow-inactivity-timeout' )
		);

		// Fullscreen display controls inherit the inline (non-fullscreen) values
		// when fullscreen-specific attributes are omitted.
		$fullscreen_show_navigation = isset( $atts['fullscreen-show-navigation'] ) ? $this->parse_bool( $atts, 'fullscreen-show-navigation', false ) : $show_navigation;
		$fullscreen_show_link_button = isset( $atts['fullscreen-show-link-button'] ) ? $this->parse_bool( $atts, 'fullscreen-show-link-button', false ) : $show_link_button;
		$fullscreen_show_download_button = isset( $atts['fullscreen-show-download-button'] ) ? $this->parse_bool( $atts, 'fullscreen-show-download-button', false ) : $show_download_button;
		$fullscreen_controls_color = isset( $atts['fullscreen-controls-color'] ) ? $this->parse_color( $atts, 'fullscreen-controls-color', '' ) : $controls_color;
		$fullscreen_video_controls_color = isset( $atts['fullscreen-video-controls-color'] ) ? $this->parse_color( $atts, 'fullscreen-video-controls-color', '' ) : $video_controls_color;
		$fullscreen_video_controls_autohide = isset( $atts['fullscreen-video-controls-autohide'] ) ? $this->parse_bool( $atts, 'fullscreen-video-controls-autohide', false ) : $video_controls_autohide;
		$fullscreen_info_font_size = isset( $atts['fullscreen-info-font-size'] ) ? $this->parse_info_font_size( $atts, 'fullscreen-info-font-size', $info_font_size ) : $info_font_size;
		$fullscreen_info_font_family = isset( $atts['fullscreen-info-font-family'] ) ? $this->parse_info_font_family( $atts, 'fullscreen-info-font-family', $info_font_family ) : $info_font_family;
		$fullscreen_info_font_color = isset( $atts['fullscreen-info-font-color'] ) ? $this->parse_color( $atts, 'fullscreen-info-font-color', '' ) : $info_font_color;
		$fullscreen_slideshow_autoresume = isset( $atts['fullscreen-slideshow-autoresume'] ) ? $this->parse_slideshow_autoresume( $atts, array( 'fullscreen-slideshow-autoresume' ) ) : $slideshow_autoresume;

		$config = array(
			// URL
			'album-url' => $url,

			// Dimensions
			'width'           => $this->parse_dimension( $atts, 'width', self::DEFAULT_WIDTH ),
			'height'          => $this->parse_dimension( $atts, 'height', self::DEFAULT_HEIGHT ),
			// Track whether width/height were explicitly set in shortcode.
			'width-explicit'  => isset( $atts['width'] ),
			'height-explicit' => isset( $atts['height'] ),
			'source-width'              => isset( $atts['source-width'] ) ? intval( $atts['source-width'] ) : self::DEFAULT_SOURCE_WIDTH,
			'source-height'             => isset( $atts['source-height'] ) ? intval( $atts['source-height'] ) : self::DEFAULT_SOURCE_HEIGHT,
			'fullscreen-source-width'   => isset( $atts['fullscreen-source-width'] ) ? intval( $atts['fullscreen-source-width'] ) : self::DEFAULT_FULLSCREEN_SOURCE_WIDTH,
			'fullscreen-source-height'  => isset( $atts['fullscreen-source-height'] ) ? intval( $atts['fullscreen-source-height'] ) : self::DEFAULT_FULLSCREEN_SOURCE_HEIGHT,
			'fullscreen-display-max-width'  => $this->parse_optional_positive_int( $atts, 'fullscreen-display-max-width' ),
			'fullscreen-display-max-height' => $this->parse_optional_positive_int( $atts, 'fullscreen-display-max-height' ),
			// Slideshow (normal mode)
			'slideshow'       => $this->parse_slideshow_mode( $atts, 'slideshow' ),
			'slideshow-delay' => $this->parse_delay_range( isset( $atts['slideshow-delay'] ) ? $atts['slideshow-delay'] : self::DEFAULT_SLIDESHOW_DELAY_RANGE ),
			'start-at'       => $this->parse_start_at( $atts ),

			// Fullscreen slideshow (fullscreen mode only)
			'fullscreen-slideshow'       => $this->parse_slideshow_mode( $atts, 'fullscreen-slideshow' ),
			'fullscreen-slideshow-delay' => $this->parse_delay_range( isset( $atts['fullscreen-slideshow-delay'] ) ? $atts['fullscreen-slideshow-delay'] : self::DEFAULT_FULLSCREEN_SLIDESHOW_DELAY ),

			// Slideshow autoresume (backward compat: slideshow-autoresume-timeout, slideshow-inactivity-timeout)
			'slideshow-autoresume' => $slideshow_autoresume,
			'fullscreen-slideshow-autoresume' => $fullscreen_slideshow_autoresume,

			// Cache refresh interval in minutes (default: 1440 = 24 hours)
			'cache-refresh' => $this->parse_cache_refresh( $atts ),

				// Display
				'mode'                 => $mode,
				'background-color'     => $this->parse_color( $atts, 'background-color', 'transparent' ),
				'fullscreen-background-color' => $this->parse_color( $atts, 'fullscreen-background-color', '' ),
				'controls-color'       => $controls_color,
				'fullscreen-controls-color' => $fullscreen_controls_color,
				'video-controls-color' => $video_controls_color,
				'fullscreen-video-controls-color' => $fullscreen_video_controls_color,
				'image-fit'            => $this->parse_image_fit( $atts ),
				'fullscreen-image-fit' => $this->parse_fullscreen_image_fit( $atts ),
				'fullscreen-toggle'    => $this->parse_fullscreen_toggle_mode( $atts ),
				'interaction-lock'     => $this->parse_bool( $atts, 'interaction-lock', false ),
				'show-navigation'      => $show_navigation,
				'fullscreen-show-navigation' => $fullscreen_show_navigation,
				'show-link-button'     => $show_link_button,
				'show-download-button' => $show_download_button,
				'fullscreen-show-link-button'     => $fullscreen_show_link_button,
				'fullscreen-show-download-button' => $fullscreen_show_download_button,
				'download-size-warning' => $this->parse_download_warning_size_mb( $atts ),

				// Entry count
				'limit'                => $this->parse_limit( $atts ),

			// Video controls
			'video-controls-autohide' => $video_controls_autohide,
			'fullscreen-video-controls-autohide' => $fullscreen_video_controls_autohide,

			// Video support
			'show-videos'            => $this->parse_bool( $atts, 'show-videos', false ),

			// Gallery mode (thumbnail layout)
			'gallery-layout'         => $this->parse_gallery_layout( $atts ),
			'gallery-sizing'         => $this->parse_gallery_sizing( $atts ),
			'gallery-columns'        => $this->parse_gallery_int( $atts, 'gallery-columns', 3 ),
			'gallery-columns-tablet' => $this->parse_gallery_int( $atts, 'gallery-columns-tablet', 2 ),
			'gallery-columns-mobile' => $this->parse_gallery_int( $atts, 'gallery-columns-mobile', 1 ),
			'gallery-row-height'     => $this->parse_gallery_row_height( $atts ),
			'gallery-rows'           => $this->parse_gallery_rows( $atts ),
			'gallery-scrollable'     => $this->parse_bool( $atts, 'gallery-scrollable', false ),
			'gallery-buttons-on-mobile' => $this->parse_gallery_buttons_on_mobile( $atts ),
			'gallery-gap'            => $this->parse_gallery_gap( $atts ),

			// Mosaic (thumbnail strip alongside the main gallery)
			'mosaic'          => $this->parse_bool( $atts, 'mosaic', false ),
			'mosaic-position' => $this->parse_mosaic_position( $atts ),
			'mosaic-count'    => $this->parse_mosaic_count( $atts ),
			'mosaic-gap'      => $this->parse_mosaic_gap( $atts ),
			'mosaic-opacity'  => $this->parse_mosaic_opacity( $atts ),

			// Visual style
			'corner-radius'        => $this->parse_corner_radius( $atts ),
			'mosaic-corner-radius' => $this->parse_mosaic_corner_radius( $atts ),
				'info-font-size'       => $info_font_size,
				'fullscreen-info-font-size' => $fullscreen_info_font_size,
				'info-font-family'     => $info_font_family,
				'fullscreen-info-font-family' => $fullscreen_info_font_family,
				'info-font-color'      => $info_font_color,
				'fullscreen-info-font-color' => $fullscreen_info_font_color,
				'info-halo-effect'     => $info_halo_effect,
				'info-top-halo-effect' => $info_top_halo_effect,
				'info-top-secondary-halo-effect' => $info_top_secondary_halo_effect,
				'info-bottom-halo-effect' => $info_bottom_halo_effect,
				'gallery-info-bottom-halo-effect' => $gallery_info_bottom_halo_effect,
				'album-title-halo-effect' => $album_title_halo_effect,
				'info-wrap'            => $info_wrap,
			'info-text-align'                => $info_text_align,
			'info-top-text-align'            => $info_top_text_align,
			'info-top-secondary-text-align'  => $info_top_secondary_text_align,
			'info-bottom-text-align'         => $info_bottom_text_align,

			// Info boxes - format strings with placeholders like {date} resolved per photo.
			// Backward compat: show-name="true" maps to ="{name}".
		) + $this->build_info_box_config( $atts, $mode );

		return $config;
	}

	/**
	 * Build the info box sub-array for the shortcode config.
	 * Extracted to keep the main config array readable.
	 *
	 * @param array $atts Raw shortcode attributes.
	 * @return array
	 */
	private function build_info_box_config( $atts, $mode ) {
		// Default for info-bottom: derive from legacy show-counter / show-title attributes when not set.
		$show_counter_compat = $this->parse_bool( $atts, 'show-counter', true );
		$show_title_compat   = $this->parse_bool( $atts, 'show-title', false );
		$is_gallery_mode     = 'gallery' === $mode;
		$b1_default          = $is_gallery_mode
			? ''
			: $this->build_legacy_info_bottom_default( $show_title_compat, $show_counter_compat );

		$b1  = $this->parse_info_box( $atts, 'info-bottom', $b1_default );
		$t1  = $this->parse_info_box( $atts, array( 'info-top', 'info-top-1' ), '' );
		$t2  = $this->parse_info_box( $atts, array( 'info-top-secondary', 'info-top-2' ), '' );
		$gpb = $this->parse_info_box( $atts, array( 'gallery-info-bottom', 'gallery-page-bottom' ), $is_gallery_mode ? '{page} / {pages}' : '' );
		$fullscreen_b1_default = $is_gallery_mode ? '{item} / {items}' : $b1;

		// Backward compat: accept fullscreen-show-title / fullscreen-show-counter
		// silently for legacy shortcodes. Modern info-bottom params still win when mixed.
		if (
			! isset( $atts['info-bottom'] ) &&
			! isset( $atts['fullscreen-info-bottom'] ) &&
			( isset( $atts['fullscreen-show-title'] ) || isset( $atts['fullscreen-show-counter'] ) )
		) {
			$fullscreen_show_title_compat = isset( $atts['fullscreen-show-title'] )
				? $this->parse_bool( $atts, 'fullscreen-show-title', false )
				: $show_title_compat;
			$fullscreen_show_counter_compat = isset( $atts['fullscreen-show-counter'] )
				? $this->parse_bool( $atts, 'fullscreen-show-counter', false )
				: $show_counter_compat;
			$fullscreen_b1_default = $this->build_legacy_info_bottom_default(
				$fullscreen_show_title_compat,
				$fullscreen_show_counter_compat
			);
		}

		// Backward compat: in gallery mode, legacy show-title / show-counter control
		// the page pill, not per-tile overlays.
		if (
			$is_gallery_mode &&
			! isset( $atts['gallery-info-bottom'] ) &&
			! isset( $atts['gallery-page-bottom'] ) &&
			( isset( $atts['show-title'] ) || isset( $atts['show-counter'] ) )
		) {
			$gpb = $this->build_legacy_gallery_info_bottom_default( $show_title_compat, $show_counter_compat );
		}

		return array(
			'info-bottom'                    => $b1,
			'fullscreen-info-bottom'         => $this->parse_info_box( $atts, 'fullscreen-info-bottom', $fullscreen_b1_default ),
			'info-top'                       => $t1,
			'fullscreen-info-top'            => $this->parse_info_box( $atts, array( 'fullscreen-info-top', 'fullscreen-info-top-1' ), $t1 ),
			'info-top-secondary'            => $t2,
			'fullscreen-info-top-secondary' => $this->parse_info_box( $atts, array( 'fullscreen-info-top-secondary', 'fullscreen-info-top-2' ), $t2 ),
			'gallery-info-bottom'            => $gpb,
		);
	}

	/**
	 * Derive the legacy bottom-center display string from old title/counter flags.
	 *
	 * @param bool $show_title   Whether to show album title.
	 * @param bool $show_counter Whether to show item counter.
	 * @return string
	 */
	private function build_legacy_info_bottom_default( $show_title, $show_counter ) {
		if ( $show_title && $show_counter ) {
			return '{album-title}: {item} / {items}';
		}

		if ( $show_title ) {
			return '{album-title}';
		}

		if ( $show_counter ) {
			return '{item} / {items}';
		}

		return '';
	}

	/**
	 * Derive the legacy gallery page-bar display string from old title/counter flags.
	 *
	 * @param bool $show_title   Whether to show album title.
	 * @param bool $show_counter Whether to show page counter.
	 * @return string
	 */
	private function build_legacy_gallery_info_bottom_default( $show_title, $show_counter ) {
		if ( $show_title && $show_counter ) {
			return '{album-title}: {page} / {pages}';
		}

		if ( $show_title ) {
			return '{album-title}';
		}

		if ( $show_counter ) {
			return '{page} / {pages}';
		}

		return '';
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
	 * Parse info box placeholder format string attribute.
	 * Returns the sanitized format string, or $default if the attribute is absent.
	 *
	 * @param array        $atts    Attributes.
	 * @param string|array $key     Attribute key or fallback keys in priority order.
	 * @param string       $default Default format string (empty = box hidden).
	 * @return string
	 */
	private function parse_info_box( $atts, $key, $default ) {
		$keys = is_array( $key ) ? $key : array( $key );
		foreach ( $keys as $candidate ) {
			if ( isset( $atts[ $candidate ] ) ) {
				return sanitize_text_field( $atts[ $candidate ] );
			}
		}
		return $default;
	}

	/**
	 * Parse info-font-size attribute in pixels.
	 *
	 * @param array  $atts    Attributes.
	 * @param string $key     Attribute key.
	 * @param int    $default Default value.
	 * @return int
	 */
	private function parse_info_font_size( $atts, $key = 'info-font-size', $default = 12 ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return $default;
		}

		$value = intval( $atts[ $key ] );
		if ( $value < 8 ) {
			return 8;
		}
		if ( $value > 48 ) {
			return 48;
		}

		return $value;
	}

	/**
	 * Parse info-font-family attribute for info boxes.
	 *
	 * Accepts a CSS font-family stack and strips characters that could break
	 * out of the CSS value when echoed into an inline custom property.
	 *
	 * @param array  $atts    Attributes.
	 * @param string $key     Attribute key.
	 * @param string $default Default value.
	 * @return string
	 */
	private function parse_info_font_family( $atts, $key = 'info-font-family', $default = '' ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return $default;
		}

		$value = sanitize_text_field( $atts[ $key ] );
		$value = preg_replace( "/[^A-Za-z0-9\\s,\\-_\"'().+&]/", '', $value );
		$value = preg_replace( '/\s+/', ' ', $value );
		$value = preg_replace( '/\s*,\s*/', ', ', $value );
		$value = trim( $value, " \t\n\r\0\x0B," );

		return '' !== $value ? $value : $default;
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
	 * Parse optional boolean attribute.
	 *
	 * Returns null when the attribute is omitted so callers can inherit
	 * the global/default behavior and only emit an override when explicitly set.
	 *
	 * @param array  $atts Attributes
	 * @param string $key  Attribute key
	 * @return bool|null Boolean value or null when omitted
	 */
	private function parse_optional_bool( $atts, $key ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return null;
		}

		return $this->parse_bool( $atts, $key, false );
	}

	/**
	 * Parse large-download warning threshold in MB for proxied downloads.
	 * 0 disables the warning/confirmation.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int Non-negative size in MB.
	 */
	private function parse_download_warning_size_mb( $atts ) {
		if ( ! isset( $atts['download-size-warning'] ) ) {
			return self::DEFAULT_DOWNLOAD_WARNING_SIZE_MB;
		}

		$value = intval( $atts['download-size-warning'] );
		if ( $value < 0 ) {
			return self::DEFAULT_DOWNLOAD_WARNING_SIZE_MB;
		}

		if ( self::MAX_DOWNLOAD_SIZE_MB > 0 && $value > self::MAX_DOWNLOAD_SIZE_MB ) {
			return self::MAX_DOWNLOAD_SIZE_MB;
		}

		return $value;
	}

	/**
	 * Parse an optional positive integer attribute.
	 *
	 * Returns null when the attribute is omitted or invalid.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $key  Attribute key.
	 * @return int|null Positive integer or null.
	 */
	private function parse_optional_positive_int( $atts, $key ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return null;
		}

		$value = intval( $atts[ $key ] );
		if ( $value <= 0 ) {
			return null;
		}

		return $value;
	}

	/**
	 * Parse gallery-buttons-on-mobile mode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string 'on-interaction' or 'always'.
	 */
	private function parse_gallery_buttons_on_mobile( $atts ) {
		if ( ! isset( $atts['gallery-buttons-on-mobile'] ) ) {
			return 'on-interaction';
		}
		$value = strtolower( trim( $atts['gallery-buttons-on-mobile'] ) );
		if ( in_array( $value, array( 'on-interaction', 'always' ), true ) ) {
			return $value;
		}
		return 'on-interaction';
	}

	private function parse_slideshow_mode( $atts, $key ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return 'disabled';
		}
		$value = strtolower( trim( $atts[ $key ] ) );
		if ( 'true' === $value || 'auto' === $value ) {
			return 'auto';
		}
		if ( 'manual' === $value || 'enabled' === $value ) {
			return 'manual';
		}
		return 'disabled';
	}

	/**
	 * Parse slideshow autoresume: a number of seconds, or 'disabled'.
	 * Checks multiple attribute names for backward compatibility.
	 *
	 * @param array $atts Shortcode attributes.
	 * @param array $keys Attribute keys to check, in priority order.
	 * @return string Number as string, or 'disabled'.
	 */
	private function parse_slideshow_autoresume( $atts, $keys ) {
		$raw = null;
		foreach ( $keys as $key ) {
			if ( isset( $atts[ $key ] ) ) {
				$raw = $atts[ $key ];
				break;
			}
		}
		if ( null === $raw ) {
			return (string) self::DEFAULT_SLIDESHOW_INACTIVITY_TIMEOUT;
		}
		$value = strtolower( trim( $raw ) );
		if ( 'disabled' === $value ) {
			return 'disabled';
		}
		$num = intval( $value );
		return $num > 0 ? (string) $num : (string) self::DEFAULT_SLIDESHOW_INACTIVITY_TIMEOUT;
	}


	/**
	 * Parse starting slide position.
	 *
	 * Parameter: start-at="1" (default) or "random" or a 1-based slide number.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string "random" or a numeric string >= 1
	 */
	private function parse_start_at( $atts ) {
		// New parameter takes precedence.
		if ( isset( $atts['start-at'] ) ) {
			$value = strtolower( trim( (string) $atts['start-at'] ) );

			if ( 'random' === $value ) {
				return 'random';
			}

			if ( is_numeric( $value ) ) {
				$number = intval( $value );
				if ( $number >= 1 ) {
					return (string) $number;
				}
			}

			// Fallback to first slide on invalid input.
			return '1';
		}

		// Default.
		return '1';
	}

	/**
	 * Parse cache-refresh attribute.
	 * Returns the number of hours before the album data is re-fetched from Google Photos.
	 * Defaults to DEFAULT_CACHE_REFRESH (168 hours = 7 days).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int Hours between cache refreshes.
	 */
	private function parse_cache_refresh( $atts ) {
		if ( isset( $atts['cache-refresh'] ) ) {
			$value = intval( $atts['cache-refresh'] );
			if ( $value >= 1 ) {
				return $value;
			}
		}
		return self::DEFAULT_CACHE_REFRESH;
	}

	/**
	 * Parse image fit mode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string One of 'cover' or 'contain'.
	 */
	private function parse_image_fit( $atts ) {
		if ( ! isset( $atts['image-fit'] ) ) {
			// Default: cover (fill container, may crop)
			return 'cover';
		}

		$value = strtolower( trim( (string) $atts['image-fit'] ) );

		if ( in_array( $value, array( 'cover', 'contain' ), true ) ) {
			return $value;
		}

		// Fallback to cover on invalid input.
		return 'cover';
	}

	/**
	 * Parse fullscreen-image-fit attribute.
	 *
	 * Defaults to 'contain' when not explicitly provided.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string One of 'contain' or 'cover'.
	 */
	private function parse_fullscreen_image_fit( $atts ) {
		if ( isset( $atts['fullscreen-image-fit'] ) ) {
			$value = strtolower( trim( (string) $atts['fullscreen-image-fit'] ) );
			if ( in_array( $value, array( 'contain', 'cover' ), true ) ) {
				return $value;
			}
		}

		// Not set or invalid - default to 'contain' (show whole image).
		return 'contain';
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
	private function parse_color( $atts, $key = 'background-color', $default = 'transparent' ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return $default;
		}

		$color = $atts[ $key ];

		if ( 'transparent' === strtolower( $color ) ) {
			return 'transparent';
		}

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $color ) ) {
			return $color;
		}

		return $default;
	}


	/**
	 * Parse mode attribute
	 *
	 * @param array $atts Attributes
	 * @return string Mode: 'gallery', 'slider', or 'carousel'
	 */
	private function parse_mode( $atts ) {
		if ( ! isset( $atts['mode'] ) ) {
			// Default to 'gallery'
			return 'gallery';
		}

		$mode = strtolower( trim( $atts['mode'] ) );

		// Valid modes: 'gallery', 'slider', 'carousel'
		$valid_modes = array( 'gallery', 'slider', 'carousel' );

		if ( in_array( $mode, $valid_modes, true ) ) {
			return $mode;
		}

		// Default fallback
		return 'gallery';
	}

	/**
	 * Parse gallery-layout attribute.
	 *
	 * @param array $atts Attributes.
	 * @return string 'grid' or 'justified'
	 */
	private function parse_gallery_layout( $atts ) {
		if ( ! isset( $atts['gallery-layout'] ) ) {
			return 'grid';
		}

		$value = strtolower( trim( $atts['gallery-layout'] ) );

		if ( in_array( $value, array( 'grid', 'justified' ), true ) ) {
			return $value;
		}

		return 'grid';
	}

	/**
	 * Parse gallery-sizing attribute.
	 *
	 * @param array $atts Attributes.
	 * @return string 'ratio' or 'fill'
	 */
	private function parse_gallery_sizing( $atts ) {
		if ( ! isset( $atts['gallery-sizing'] ) ) {
			return 'ratio';
		}

		$value = strtolower( trim( $atts['gallery-sizing'] ) );

		if ( in_array( $value, array( 'ratio', 'fill' ), true ) ) {
			return $value;
		}

		return 'ratio';
	}

	/**
	 * Parse an integer gallery column/row count attribute.
	 *
	 * @param array  $atts    Attributes.
	 * @param string $key     Canonical key.
	 * @param int    $default Default value.
	 * @return int
	 */
	private function parse_gallery_int( $atts, $key, $default ) {
		if ( ! isset( $atts[ $key ] ) ) {
			return $default;
		}

		$value = intval( $atts[ $key ] );

		return ( $value >= 1 && $value <= 12 ) ? $value : $default;
	}

	/**
	 * Parse gallery-row-height attribute (pixels, 50–800).
	 *
	 * @param array $atts Attributes.
	 * @return int
	 */
	private function parse_gallery_row_height( $atts ) {
		if ( ! isset( $atts['gallery-row-height'] ) ) {
			return 200;
		}

		$value = intval( $atts['gallery-row-height'] );

		return ( $value >= 50 && $value <= 800 ) ? $value : 200;
	}

	/**
	 * Parse gallery-rows attribute.
	 *
	 * Controls how many gallery rows are shown per page.
	 * Use 0 (or omit) to show all rows on one page.
	 *
	 * @param array $atts Attributes.
	 * @return int
	 */
	private function parse_gallery_rows( $atts ) {
		if ( ! isset( $atts['gallery-rows'] ) ) {
			return 0;
		}

		$value = intval( $atts['gallery-rows'] );

		if ( $value <= 0 ) {
			return 0;
		}

		if ( $value > self::MAX_PHOTOS ) {
			return self::MAX_PHOTOS;
		}

		return $value;
	}

	/**
	 * Parse mosaic position attribute.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string 'top', 'bottom', 'left', or 'right'.
	 */
	private function parse_mosaic_position( $atts ) {
		if ( ! isset( $atts['mosaic-position'] ) ) {
			return 'bottom';
		}

		$value = strtolower( trim( (string) $atts['mosaic-position'] ) );

		if ( in_array( $value, array( 'top', 'bottom', 'left', 'right' ), true ) ) {
			return $value;
		}

		return 'right';
	}

	/**
	 * Parse mosaic-count attribute.
	 *
	 * Accepts a positive integer or "auto" (rendered as 0 for JS to calculate).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int Mosaic count (0 means auto).
	 */
	private function parse_mosaic_count( $atts ) {
		if ( ! isset( $atts['mosaic-count'] ) ) {
			return 0; // Auto by default: JS will calculate from available space.
		}

		if ( 'auto' === strtolower( trim( (string) $atts['mosaic-count'] ) ) ) {
			return 0;
		}

		$value = intval( $atts['mosaic-count'] );

		return $value > 0 ? $value : 0;
	}

	/**
	 * Parse mosaic-gap attribute (pixels, 0–100).
	 *
	 * Gap between thumbnails in the mosaic strip.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int Gap in pixels.
	 */
	private function parse_mosaic_gap( $atts ) {
		if ( ! isset( $atts['mosaic-gap'] ) ) {
			return 8;
		}

		$value = intval( $atts['mosaic-gap'] );

		return ( $value >= 0 && $value <= 100 ) ? $value : 8;
	}

	/**
	 * Parse mosaic-opacity attribute (0.0–1.0).
	 *
	 * Opacity of inactive (non-active) mosaic thumbnails.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return float Opacity value.
	 */
	private function parse_mosaic_opacity( $atts ) {
		if ( ! isset( $atts['mosaic-opacity'] ) ) {
			return 0.3;
		}

		$value = floatval( $atts['mosaic-opacity'] );

		return max( 0.0, min( 1.0, $value ) );
	}

	/**
	 * Parse corner-radius attribute.
	 *
	 * Accepts a non-negative integer (pixels). 0 = square corners.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int Corner radius in pixels.
	 */
	private function parse_corner_radius( $atts ) {
		if ( ! isset( $atts['corner-radius'] ) ) {
			return 0;
		}

		$value = intval( $atts['corner-radius'] );

		return max( 0, $value );
	}

	/**
	 * Parse mosaic-corner-radius attribute.
	 *
	 * Falls back to corner-radius when not explicitly set.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int|null Corner radius in pixels, or null to inherit from corner-radius.
	 */
	private function parse_mosaic_corner_radius( $atts ) {
		if ( ! isset( $atts['mosaic-corner-radius'] ) ) {
			return null;
		}

		$value = intval( $atts['mosaic-corner-radius'] );

		return max( 0, $value );
	}

	/**
	 * Parse gallery-gap attribute (pixels, 0–100).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int Gap in pixels.
	 */
	private function parse_gallery_gap( $atts ) {
		if ( ! isset( $atts['gallery-gap'] ) ) {
			return 4;
		}

		$value = intval( $atts['gallery-gap'] );

		return ( $value >= 0 && $value <= 100 ) ? $value : 4;
	}

	/**
	 * Parse fullscreen trigger mode attribute.
	 *
	 * @param array $atts Attributes
	 * @return string Fullscreen trigger mode: 'click', 'double-click', 'button-only', or 'disabled'
	 */
	private function parse_fullscreen_toggle_mode( $atts ) {
		if ( ! isset( $atts['fullscreen-toggle'] ) ) {
			// Default to 'button-only'
			return 'button-only';
		}

		$mode = strtolower( trim( (string) $atts['fullscreen-toggle'] ) );

		// Valid modes: 'button-only' (default), 'click', 'double-click', 'disabled'
		$valid_modes = array( 'button-only', 'click', 'double-click', 'disabled' );

		if ( in_array( $mode, $valid_modes, true ) ) {
			return $mode;
		}

		// Default fallback
		return 'button-only';
	}

	/**
	 * Parse limit attribute.
	 *
	 * Clamps the value between 1 and self::MAX_PHOTOS.
	 *
	 * @param array $atts Attributes.
	 * @return int
	 */
	private function parse_limit( $atts ) {
		if ( ! isset( $atts['limit'] ) ) {
			return self::DEFAULT_MAX_PHOTOS_PER_ALBUM;
		}

		$value = intval( $atts['limit'] );

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
	 * Accepts media items as either plain URL strings (images) or associative
	 * arrays with 'url' and 'type' keys (for videos). This provides backward
	 * compatibility with cached data that stores plain strings.
	 *
	 * @param array $base_items     Base media items (strings or arrays).
	 * @param int   $full_width     Full image width.
	 * @param int   $full_height    Full image height.
	 * @param int   $preview_width  Preview image width (optional).
	 * @param int   $preview_height Preview image height (optional).
	 * @param int   $max_entries    Maximum number of media entries to include from the album.
	 * @return array Photo objects with preview and full URLs.
	 */
	private function prepare_photo_urls( $base_items, $full_width, $full_height, $preview_width = null, $preview_height = null, $max_entries = self::DEFAULT_MAX_PHOTOS_PER_ALBUM, $show_videos = true ) {
		// Determine effective limit: requested per-album limit clamped to global MAX_PHOTOS.
		$limit = intval( $max_entries );

		if ( $limit <= 0 ) {
			$limit = self::DEFAULT_MAX_PHOTOS_PER_ALBUM;
		}

		if ( $limit > self::MAX_PHOTOS ) {
			$limit = self::MAX_PHOTOS;
		}

		$photos = array();
		foreach ( $base_items as $item ) {
			// Support both plain URL strings (backward compat) and object format.
			if ( is_array( $item ) ) {
				$base = $item['url'];
				$type = isset( $item['type'] ) ? $item['type'] : 'image';
			} else {
				$base = $item;
				$type = 'image';
			}

			// Filter out videos when show-videos is disabled.
			if ( ! $show_videos && 'video' === $type ) {
				continue;
			}

			$photo = array(
				'full'  => sprintf( '%s=w%d-h%d', $base, $full_width, $full_height ),
				'thumb' => sprintf( '%s=w%d-h%d-c', $base, self::DEFAULT_THUMB_WIDTH, self::DEFAULT_THUMB_HEIGHT ),
			);

			// Add preview URL if dimensions provided.
			if ( $preview_width && $preview_height ) {
				$photo['preview'] = sprintf( '%s=w%d-h%d', $base, $preview_width, $preview_height );
			}

			// Add video-specific fields.
			if ( 'video' === $type ) {
				$photo['type']  = 'video';
				$photo['video'] = $base . '=dv';
				// Append -no to preview/full URLs for videos to suppress
				// Google's baked-in play button overlay on the thumbnail.
				if ( isset( $photo['preview'] ) ) {
					$photo['preview'] .= '-no';
				}
				$photo['full'] .= '-no';
			}

			// Pass through metadata fields extracted from album HTML.
			if ( is_array( $item ) ) {
				foreach ( array( 'id', 'filename', 'timestamp', 'width', 'height', 'filesize', 'description', 'camera', 'camera_make', 'camera_model', 'exif', 'aperture', 'shutter', 'focal', 'iso' ) as $meta_key ) {
					// Note: 'id' is the AF1Qip… media ID, needed by Wave 2 JS for individual photo page fetching.
						if ( isset( $item[ $meta_key ] ) ) {
							$photo[ $meta_key ] = $item[ $meta_key ];
						}
					}
			}

			$photos[] = $photo;

			// Stop once we have enough visible entries.
			if ( count( $photos ) >= $limit ) {
				break;
			}
		}

		return $photos;
	}

	/**
	 * Switch large plain photo sliders to progressive chunk loading.
	 *
	 * @param array $config Shortcode render config.
	 * @return array
	 */
	private function apply_progressive_slider_loading_config( $config ) {
		if ( ! $this->should_use_progressive_slider_loading( $config ) ) {
			return $config;
		}

		$config['progressive-loading']           = true;
		$config['progressive-total-count']       = count( $config['photos'] );
		$config['progressive-initial-chunk-size'] = self::PROGRESSIVE_SLIDER_INITIAL_CHUNK;
		$config['progressive-chunk-size']        = self::PROGRESSIVE_SLIDER_CHUNK_SIZE;
		$config['photos']                        = array();

		return $config;
	}

	/**
	 * Decide whether a render should use progressive slider chunk loading.
	 *
	 * Scope is intentionally narrow: slider mode only, no mosaic, no videos.
	 *
	 * @param array $config Shortcode render config.
	 * @return bool
	 */
	private function should_use_progressive_slider_loading( $config ) {
		if ( empty( $config['mode'] ) || 'slider' !== $config['mode'] ) {
			return false;
		}

		if ( ! empty( $config['mosaic'] ) || empty( $config['photos'] ) || ! is_array( $config['photos'] ) ) {
			return false;
		}

		if ( count( $config['photos'] ) <= self::PROGRESSIVE_SLIDER_THRESHOLD ) {
			return false;
		}

		foreach ( $config['photos'] as $photo ) {
			if ( is_array( $photo ) && isset( $photo['type'] ) && 'video' === $photo['type'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Fetch cached album base items or prime the cache on demand.
	 *
	 * @param string $album_url Album URL.
	 * @return array Result array with success/data/error keys.
	 */
	private function get_cached_or_fresh_album_data( $album_url ) {
		$cache_key    = $this->get_cache_key( $album_url );
		$expiry_key   = $this->get_expiration_key( $album_url );
		$cached_data  = get_transient( $cache_key );

		if ( is_array( $cached_data ) && ! empty( $cached_data['photos'] ) && is_array( $cached_data['photos'] ) ) {
			return array(
				'success' => true,
				'data'    => $cached_data,
				'error'   => null,
			);
		}

		$result = $this->provider->fetch_album( $album_url );
		if ( ! $result['success'] ) {
			return $result;
		}

		$cache_duration = (int) get_option( $expiry_key, self::DEFAULT_CACHE_REFRESH * 3600 );
		if ( $cache_duration <= 0 ) {
			$cache_duration = self::DEFAULT_CACHE_REFRESH * 3600;
			update_option( $expiry_key, $cache_duration, false );
		}

		$cached_data = array(
			'title'         => $result['data']['title'] ?? null,
			'photos'        => $result['data']['photos'],
			'is_deprecated' => $result['is_deprecated'],
		);

		set_transient( $cache_key, $cached_data, $cache_duration );

		return array(
			'success' => true,
			'data'    => $cached_data,
			'error'   => null,
		);
	}

	/**
	 * Prepare a filtered chunk of visible photos for progressive loading.
	 *
	 * @param array  $base_items      Cached base album items.
	 * @param string $album_url       Album URL.
	 * @param int    $offset          Zero-based visible-item offset.
	 * @param int    $chunk_size      Number of visible items to return.
	 * @param int    $full_width      Full image width.
	 * @param int    $full_height     Full image height.
	 * @param int    $preview_width   Preview image width.
	 * @param int    $preview_height  Preview image height.
	 * @param int    $max_entries     Visible album limit.
	 * @param bool   $show_videos     Whether videos are included.
	 * @return array
	 */
	private function prepare_photo_chunk( $base_items, $album_url, $offset, $chunk_size, $full_width, $full_height, $preview_width, $preview_height, $max_entries, $show_videos ) {
		$limit = intval( $max_entries );
		if ( $limit <= 0 ) {
			$limit = self::DEFAULT_MAX_PHOTOS_PER_ALBUM;
		}
		if ( $limit > self::MAX_PHOTOS ) {
			$limit = self::MAX_PHOTOS;
		}

		$offset = max( 0, intval( $offset ) );
		$chunk_size = max( 1, min( self::MAX_PHOTOS, intval( $chunk_size ) ) );

		$selected_items   = array();
		$selected_indexes = array();
		$visible_total    = 0;

		foreach ( $base_items as $item ) {
			$type = is_array( $item ) && isset( $item['type'] ) ? $item['type'] : 'image';
			if ( ! $show_videos && 'video' === $type ) {
				continue;
			}

			if ( $visible_total >= $limit ) {
				break;
			}

			if ( $visible_total >= $offset && count( $selected_items ) < $chunk_size ) {
				$selected_items[]   = $item;
				$selected_indexes[] = $visible_total;
			}

			$visible_total++;
		}

		$selected_count = count( $selected_items );
		if ( 0 === $selected_count ) {
			return array(
				'photos'      => array(),
				'total_count' => $visible_total,
			);
		}

		$hydrated_items = $this->hydrate_cached_photo_meta(
			$selected_items,
			$album_url,
			$selected_count,
			$show_videos
		);
		$photos = $this->prepare_photo_urls(
			$hydrated_items,
			$full_width,
			$full_height,
			$preview_width,
			$preview_height,
			$selected_count,
			$show_videos
		);

		foreach ( $photos as $index => &$photo ) {
			$photo['globalIndex'] = $selected_indexes[ $index ];
		}
		unset( $photo );

		return array(
			'photos'      => $photos,
			'total_count' => $visible_total,
		);
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
	 * Get cache key for per-photo metadata.
	 *
	 * @param string $photo_url Individual Google Photos page URL.
	 * @return string
	 */
	private function get_photo_meta_cache_key( $photo_url ) {
		if ( preg_match( '#/photo/([^?&/]+)#', $photo_url, $matches ) ) {
			return 'jzsa_photo_meta_' . md5( $matches[1] );
		}

		return 'jzsa_photo_meta_' . md5( $photo_url );
	}

	/**
	 * Collect known media IDs for an album so related per-photo caches can be cleared.
	 *
	 * Uses the cached album payload when present, otherwise falls back to a fresh
	 * provider fetch.
	 *
	 * @param string $album_url Album share URL.
	 * @return string[]
	 */
	private function get_album_media_ids( $album_url ) {
		if ( empty( $album_url ) ) {
			return array();
		}

		$photos = array();
		$cached = get_transient( $this->get_cache_key( $album_url ) );
		if ( is_array( $cached ) && ! empty( $cached['photos'] ) && is_array( $cached['photos'] ) ) {
			$photos = $cached['photos'];
		} else {
			$result = $this->provider->fetch_album( $album_url );
			if ( ! empty( $result['success'] ) && ! empty( $result['data']['photos'] ) && is_array( $result['data']['photos'] ) ) {
				$photos = $result['data']['photos'];
			}
		}

		$ids = array();
		foreach ( $photos as $photo ) {
			if ( is_array( $photo ) && ! empty( $photo['id'] ) ) {
				$ids[ $photo['id'] ] = true;
			}
		}

		return array_keys( $ids );
	}

	/**
	 * Clear all cache entries related to a single album.
	 *
	 * @param string $album_url Album share URL.
	 * @return void
	 */
	private function clear_album_related_caches( $album_url ) {
		if ( empty( $album_url ) ) {
			return;
		}

		$media_ids = $this->get_album_media_ids( $album_url );

		delete_transient( $this->get_cache_key( $album_url ) );
		delete_option( $this->get_expiration_key( $album_url ) );

		foreach ( $media_ids as $media_id ) {
			$photo_url = $this->build_photo_page_url( $album_url, $media_id );
			if ( '' !== $photo_url ) {
				delete_transient( $this->get_photo_meta_cache_key( $photo_url ) );
			}
		}
	}

	/**
	 * Build an individual photo page URL from album URL and media ID.
	 *
	 * @param string $album_url Album share URL.
	 * @param string $media_id  Google Photos media ID.
	 * @return string
	 */
	private function build_photo_page_url( $album_url, $media_id ) {
		if ( empty( $album_url ) || empty( $media_id ) ) {
			return '';
		}

		if ( ! preg_match( '#/share/([^?]+)\?key=([^&]+)#', $album_url, $matches ) ) {
			return '';
		}

		return 'https://photos.google.com/share/' . $matches[1] . '/photo/' . rawurlencode( $media_id ) . '?key=' . rawurlencode( $matches[2] );
	}

	/**
	 * Parse a filename from a Content-Disposition header.
	 *
	 * @param string $content_disposition Header value.
	 * @return string
	 */
	private function parse_content_disposition_filename( $content_disposition ) {
		if ( empty( $content_disposition ) || ! is_string( $content_disposition ) ) {
			return '';
		}

		// sanitize_file_name() is intentionally NOT used here: it converts
		// non-ASCII characters (ø→o, æ→a, etc.) and replaces spaces with hyphens,
		// which corrupts display-only filenames. We just strip control characters.
		if ( preg_match( "/filename\*\s*=\s*(?:UTF-8''|utf-8'')?([^;]+)/i", $content_disposition, $matches ) ) {
			$fn = rawurldecode( trim( $matches[1], " \t\n\r\0\x0B\"'" ) );
			return preg_replace( '/[\x00-\x1F\x7F]/', '', $fn );
		}

		if ( preg_match( '/filename\s*=\s*"([^"]+)"/i', $content_disposition, $matches ) ) {
			return preg_replace( '/[\x00-\x1F\x7F]/', '', $matches[1] );
		}

		if ( preg_match( '/filename\s*=\s*([^;]+)/i', $content_disposition, $matches ) ) {
			$fn = trim( $matches[1], " \t\n\r\0\x0B\"'" );
			return preg_replace( '/[\x00-\x1F\x7F]/', '', $fn );
		}

		return '';
	}

	/**
	 * Normalize a Google media URL so it returns download headers with filename.
	 *
	 * @param string $media_url  Raw media URL.
	 * @param string $media_type Media type: photo or video.
	 * @return string
	 */
	private function normalize_media_url_for_filename( $media_url, $media_type ) {
		$media_url = html_entity_decode( (string) $media_url, ENT_QUOTES );
		$media_url = str_replace(
			array( '\u003d', '\u0026', '\/' ),
			array( '=', '&', '/' ),
			$media_url
		);

		if ( 'video' === $media_type ) {
			return $media_url;
		}

		$base = preg_replace( '/=.+$/', '', $media_url );
		return $base . '=d';
	}

	/**
	 * Fetch original filename from a Google media response header.
	 *
	 * @param string $media_url  Media URL.
	 * @param string $media_type Media type: photo or video.
	 * @return string
	 */
	private function fetch_remote_filename( $media_url, $media_type ) {
		if ( empty( $media_url ) ) {
			return '';
		}

		$request_url = $this->normalize_media_url_for_filename( $media_url, $media_type );
		$parsed_url  = wp_parse_url( $request_url );
		if ( empty( $parsed_url['scheme'] ) || 'https' !== $parsed_url['scheme'] || empty( $parsed_url['host'] ) ) {
			return '';
		}

		$host = strtolower( $parsed_url['host'] );
		if ( 'googleusercontent.com' !== $host && substr( $host, -strlen( '.googleusercontent.com' ) ) !== '.googleusercontent.com' ) {
			return '';
		}

		$context = stream_context_create(
			array(
				'http' => array(
					'method'        => 'GET',
					'timeout'       => 20,
					'ignore_errors' => true,
					'header'        => "Range: bytes=0-0\r\nUser-Agent: WordPress/" . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . "\r\n",
				),
			)
		);

		$headers = @get_headers( $request_url, true, $context );
		if ( false === $headers || ! is_array( $headers ) ) {
			return '';
		}

		$content_disposition = '';
		foreach ( $headers as $key => $value ) {
			if ( 0 === strcasecmp( (string) $key, 'Content-Disposition' ) ) {
				$content_disposition = is_array( $value ) ? end( $value ) : $value;
				break;
			}
		}

		return $this->parse_content_disposition_filename( $content_disposition );
	}

	/**
	 * Strip internal cache bookkeeping keys from a photo meta payload.
	 *
	 * @param array $meta Cached meta array.
	 * @return array
	 */
	private function filter_public_photo_meta( $meta ) {
		if ( ! is_array( $meta ) ) {
			return array();
		}

		unset( $meta['_fetched_exif'], $meta['_fetched_description'], $meta['_fetched_filename'] );

		if (
			( isset( $meta['camera_make'] ) || isset( $meta['camera_model'] ) ) &&
			isset( $this->provider ) &&
			is_object( $this->provider ) &&
			method_exists( $this->provider, 'format_camera_display_name' )
		) {
			$meta['camera'] = $this->provider->format_camera_display_name(
				$meta['camera_make'] ?? '',
				$meta['camera_model'] ?? ''
			);
		}

		return $meta;
	}

	/**
	 * Merge cached per-photo metadata into album items before initial render.
	 *
	 * This lets filename/EXIF placeholders render immediately on page load when we
	 * already have them cached from previous visits, instead of waiting for the
	 * background Wave 2 AJAX path again.
	 *
	 * @param array  $base_items   Album items as returned by the provider/cache.
	 * @param string $album_url    Album share URL.
	 * @param int    $max_entries  Maximum visible entries to hydrate.
	 * @param bool   $show_videos  Whether videos are included in the visible list.
	 * @return array
	 */
	private function hydrate_cached_photo_meta( $base_items, $album_url, $max_entries = self::DEFAULT_MAX_PHOTOS_PER_ALBUM, $show_videos = true ) {
		if ( empty( $album_url ) || empty( $base_items ) || ! is_array( $base_items ) ) {
			return $base_items;
		}

		$limit = intval( $max_entries );
		if ( $limit <= 0 ) {
			$limit = self::DEFAULT_MAX_PHOTOS_PER_ALBUM;
		}
		if ( $limit > self::MAX_PHOTOS ) {
			$limit = self::MAX_PHOTOS;
		}

		$visible_count = 0;
		foreach ( $base_items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				$visible_count++;
				if ( $visible_count >= $limit ) {
					break;
				}
				continue;
			}

			$type = isset( $item['type'] ) ? $item['type'] : 'image';
			if ( ! $show_videos && 'video' === $type ) {
				continue;
			}

			if ( empty( $item['id'] ) ) {
				$visible_count++;
				if ( $visible_count >= $limit ) {
					break;
				}
				continue;
			}

			$photo_url = $this->build_photo_page_url( $album_url, $item['id'] );
			if ( $photo_url ) {
				$cached_meta = get_transient( $this->get_photo_meta_cache_key( $photo_url ) );
				$cached_meta = $this->filter_public_photo_meta( $cached_meta );
				if ( ! empty( $cached_meta ) ) {
					foreach ( array( 'filename', 'description', 'camera', 'camera_make', 'camera_model', 'exif', 'aperture', 'shutter', 'focal', 'iso' ) as $meta_key ) {
						if ( isset( $cached_meta[ $meta_key ] ) && '' !== $cached_meta[ $meta_key ] ) {
							$base_items[ $index ][ $meta_key ] = $cached_meta[ $meta_key ];
						}
					}
				}
			}

			$visible_count++;
			if ( $visible_count >= $limit ) {
				break;
			}
		}

		return $base_items;
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
				$this->clear_album_related_caches( $url );
			}
		}
	}

/**
	 * Handle AJAX shortcode preview for the admin Shortcode Playground.
	 *
	 * This is intentionally simple: it only renders the shortcode and returns
	 * the HTML. The JavaScript side is responsible for deciding whether to
	 * initialize Swiper on the result or keep it as a static preview.
	 */
	public function handle_shortcode_preview() {
		// Only allow logged-in administrators.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'jzsa_shortcode_preview' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$shortcode = isset( $_POST['shortcode'] ) ? wp_kses_post( wp_unslash( $_POST['shortcode'] ) ) : '';

		if ( empty( $shortcode ) ) {
			wp_send_json_error( __( 'Empty shortcode', 'janzeman-shared-albums-for-google-photos' ) );
		}

		// Only allow our own shortcode in this preview endpoint.
		if ( false === strpos( $shortcode, '[jzsa-album' ) ) {
			wp_send_json_error( __( 'Only the [jzsa-album] shortcode is supported in this preview.', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$html = do_shortcode( $shortcode );

		if ( '' === $html ) {
			wp_send_json_error( __( 'Shortcode did not produce any output.', 'janzeman-shared-albums-for-google-photos' ) );
		}

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}

	/**
	 * Handle AJAX request to clear all plugin-managed cache data.
	 *
	 * Deletes all album caches, per-photo metadata caches, and stored album
	 * expiry options so the next page load fetches fresh data from Google Photos.
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'jzsa_clear_cache' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$result = function_exists( 'jzsa_clear_all_plugin_caches' )
			? jzsa_clear_all_plugin_caches()
			: array(
				'album_transient_rows'      => 0,
				'photo_meta_transient_rows' => 0,
				'expiry_rows'               => 0,
			);

		$album_count = (int) floor( max( 0, (int) $result['album_transient_rows'] ) / 2 );
		$photo_count = (int) floor( max( 0, (int) $result['photo_meta_transient_rows'] ) / 2 );

		if ( $album_count > 0 || $photo_count > 0 ) {
			$message = sprintf(
				/* translators: 1: number of cached albums cleared, 2: number of cached photo metadata entries cleared */
				__( 'Cleared %1$d cached albums and %2$d cached photo metadata entries.', 'janzeman-shared-albums-for-google-photos' ),
				$album_count,
				$photo_count
			);
		} else {
			$message = __( 'All plugin caches were already empty.', 'janzeman-shared-albums-for-google-photos' );
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Handle AJAX request to fetch per-photo metadata for an individual photo.
	 *
	 * Wave 2: JS calls this endpoint in the background for each photo that
	 * needs EXIF and/or filename data. The PHP side fetches the individual
	 * photo page from Google Photos (bypassing CORS), extracts camera/EXIF
	 * fields, and resolves the original filename from media response headers.
	 */
	public function handle_fetch_photo_meta() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'jzsa_photo_meta' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$photo_url = isset( $_POST['photo_url'] ) ? esc_url_raw( wp_unslash( $_POST['photo_url'] ) ) : '';
		$media_url = isset( $_POST['media_url'] ) ? esc_url_raw( wp_unslash( $_POST['media_url'] ) ) : '';
		$media_type = isset( $_POST['media_type'] ) ? sanitize_key( wp_unslash( $_POST['media_type'] ) ) : 'photo';
		$need_exif = isset( $_POST['need_exif'] ) ? filter_var( wp_unslash( $_POST['need_exif'] ), FILTER_VALIDATE_BOOLEAN ) : true;
		$need_description = isset( $_POST['need_description'] ) ? filter_var( wp_unslash( $_POST['need_description'] ), FILTER_VALIDATE_BOOLEAN ) : false;
		$need_filename = isset( $_POST['need_filename'] ) ? filter_var( wp_unslash( $_POST['need_filename'] ), FILTER_VALIDATE_BOOLEAN ) : true;
		if ( empty( $photo_url ) ) {
			wp_send_json_error( 'Missing photo URL' );
			return;
		}

		// Validate it's a Google Photos URL.
		if ( false === strpos( $photo_url, 'photos.google.com/share/' ) ) {
			wp_send_json_error( 'Invalid photo URL' );
			return;
		}

		$cache_key   = $this->get_photo_meta_cache_key( $photo_url );
		$cached_meta = get_transient( $cache_key );
		if ( ! is_array( $cached_meta ) ) {
			$cached_meta = array();
		}

		$has_cached_exif_state = array_key_exists( '_fetched_exif', $cached_meta );
		$has_cached_description_state = array_key_exists( '_fetched_description', $cached_meta );
		$has_cached_filename_state = array_key_exists( '_fetched_filename', $cached_meta );
		$need_exif_fetch = $need_exif && ! $has_cached_exif_state;
		$need_description_fetch = $need_description && ! $has_cached_description_state;
		$need_filename_fetch = $need_filename && ! $has_cached_filename_state;

		// Backward compatibility with older cache entries that stored fields but no state flags.
		if ( $need_exif && ! $need_exif_fetch && empty( $cached_meta['_fetched_exif'] ) ) {
			$need_exif_fetch = false;
		} elseif (
			$need_exif &&
			$has_cached_exif_state &&
			! empty( $cached_meta['camera'] ) &&
			! array_key_exists( 'camera_make', $cached_meta ) &&
			! array_key_exists( 'camera_model', $cached_meta )
		) {
			// Compatibility: older EXIF cache entries stored only the combined
			// camera string, so refetch once to populate raw make/model fields.
			$need_exif_fetch = true;
		} elseif (
			$need_exif &&
			$has_cached_exif_state &&
			(
				(
					( ! empty( $cached_meta['camera_make'] ) || ! empty( $cached_meta['camera_model'] ) ) &&
					(
						! array_key_exists( 'aperture', $cached_meta ) ||
						! array_key_exists( 'shutter', $cached_meta ) ||
						! array_key_exists( 'focal', $cached_meta ) ||
						! array_key_exists( 'iso', $cached_meta )
					)
				) ||
				(
					! empty( $cached_meta['camera'] ) &&
					(
						! array_key_exists( 'aperture', $cached_meta ) ||
						! array_key_exists( 'shutter', $cached_meta ) ||
						! array_key_exists( 'focal', $cached_meta ) ||
						! array_key_exists( 'iso', $cached_meta )
					)
				)
			)
		) {
			// Compatibility: refetch partial EXIF cache entries so the full
			// EXIF bundle (camera/aperture/shutter/focal/iso) is restored.
			$need_exif_fetch = true;
		} elseif ( $need_exif && ! $has_cached_exif_state && (
			! empty( $cached_meta['description'] ) ||
			! empty( $cached_meta['camera'] ) ||
			! empty( $cached_meta['camera_make'] ) ||
			! empty( $cached_meta['camera_model'] ) ||
			! empty( $cached_meta['aperture'] ) ||
			! empty( $cached_meta['shutter'] ) ||
			! empty( $cached_meta['focal'] ) ||
			! empty( $cached_meta['iso'] )
		) ) {
			$need_exif_fetch = false;
		}
		if ( $need_description && ! $has_cached_description_state && ! empty( $cached_meta['description'] ) ) {
			$need_description_fetch = false;
		}
		if ( $need_filename && ! $has_cached_filename_state && ! empty( $cached_meta['filename'] ) ) {
			$need_filename_fetch = false;
		}

		if ( ! $need_exif_fetch && ! $need_description_fetch && ! $need_filename_fetch ) {
			wp_send_json_success( $this->filter_public_photo_meta( $cached_meta ) );
			return;
		}

		$meta = $cached_meta;
		$html = '';

		if ( $need_exif_fetch || $need_description_fetch || ( $need_filename_fetch && ( 'video' === $media_type || empty( $media_url ) ) ) ) {
			$response = wp_remote_get(
				$photo_url,
				array(
					'timeout'    => 15,
					'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( 'Fetch failed' );
				return;
			}

			$html = wp_remote_retrieve_body( $response );
			if ( empty( $html ) ) {
				wp_send_json_error( 'Empty response' );
				return;
			}
		}

		if ( $need_exif_fetch || $need_description_fetch ) {
			$meta['_fetched_exif'] = true;
			$meta['_fetched_description'] = true;
			$exif_meta = ! empty( $html ) ? $this->provider->extract_individual_photo_meta( $html ) : array();
			if ( ! empty( $exif_meta ) ) {
				$meta = array_merge( $meta, $exif_meta );
			}
		}

		if ( $need_filename_fetch ) {
			$meta['_fetched_filename'] = true;
			$filename_source_url = $media_url;
			if ( ! empty( $html ) ) {
				$media_urls = $this->provider->extract_individual_photo_media_urls( $html );
				if ( 'video' === $media_type && ! empty( $media_urls['video'] ) ) {
					$filename_source_url = $media_urls['video'];
				} elseif ( empty( $filename_source_url ) && ! empty( $media_urls['image'] ) ) {
					$filename_source_url = $media_urls['image'];
				}
			}

			$filename = $this->fetch_remote_filename( $filename_source_url, $media_type );
			if ( '' !== $filename ) {
				$meta['filename'] = $filename;
			}
		}

		set_transient( $cache_key, $meta, self::PHOTO_META_CACHE_TTL );

		wp_send_json_success( $this->filter_public_photo_meta( $meta ) );
	}

	/**
	 * Handle AJAX request to fetch one visible chunk of a large slider album.
	 *
	 * Uses cached album data whenever possible so large pages can load a small
	 * initial window and append more photos later without embedding all photos
	 * into the page HTML.
	 */
	public function handle_fetch_album_chunk() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'jzsa_album_chunk' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$album_url = isset( $_POST['album_url'] ) ? esc_url_raw( wp_unslash( $_POST['album_url'] ) ) : '';
		if ( empty( $album_url ) ) {
			wp_send_json_error( 'Missing album URL' );
			return;
		}

		$offset                     = isset( $_POST['offset'] ) ? intval( wp_unslash( $_POST['offset'] ) ) : 0;
		$count                      = isset( $_POST['count'] ) ? intval( wp_unslash( $_POST['count'] ) ) : self::PROGRESSIVE_SLIDER_CHUNK_SIZE;
		$limit                      = isset( $_POST['limit'] ) ? intval( wp_unslash( $_POST['limit'] ) ) : self::DEFAULT_MAX_PHOTOS_PER_ALBUM;
		$show_videos                = isset( $_POST['show_videos'] ) ? filter_var( wp_unslash( $_POST['show_videos'] ), FILTER_VALIDATE_BOOLEAN ) : true;
		$preview_width              = isset( $_POST['source_width'] ) ? intval( wp_unslash( $_POST['source_width'] ) ) : self::DEFAULT_SOURCE_WIDTH;
		$preview_height             = isset( $_POST['source_height'] ) ? intval( wp_unslash( $_POST['source_height'] ) ) : self::DEFAULT_SOURCE_HEIGHT;
		$fullscreen_width           = isset( $_POST['fullscreen_source_width'] ) ? intval( wp_unslash( $_POST['fullscreen_source_width'] ) ) : self::DEFAULT_FULLSCREEN_SOURCE_WIDTH;
		$fullscreen_height          = isset( $_POST['fullscreen_source_height'] ) ? intval( wp_unslash( $_POST['fullscreen_source_height'] ) ) : self::DEFAULT_FULLSCREEN_SOURCE_HEIGHT;

		$album_result = $this->get_cached_or_fresh_album_data( $album_url );
		if ( ! $album_result['success'] || empty( $album_result['data']['photos'] ) ) {
			wp_send_json_error( $album_result['error'] ?? 'Unable to load album' );
			return;
		}

		$chunk = $this->prepare_photo_chunk(
			$album_result['data']['photos'],
			$album_url,
			$offset,
			$count,
			$fullscreen_width,
			$fullscreen_height,
			$preview_width,
			$preview_height,
			$limit,
			$show_videos
		);

		wp_send_json_success(
			array(
				'photos'      => $chunk['photos'],
				'offset'      => max( 0, $offset ),
				'total_count' => $chunk['total_count'],
			)
		);
	}

	/**
	 * Handle AJAX request to refresh expired media URLs.
	 *
	 * Re-fetches the album page from Google Photos and returns fresh URLs
	 * so the frontend can update stale video/image sources.
	 */
	public function handle_refresh_urls() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'jzsa_refresh_urls' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$album_url = isset( $_POST['album_url'] ) ? esc_url_raw( wp_unslash( $_POST['album_url'] ) ) : '';
		if ( empty( $album_url ) ) {
			wp_send_json_error( 'Missing album URL' );
			return;
		}

		// Force fresh fetch (bypass cache).
		$result = $this->provider->fetch_album( $album_url );

		if ( ! $result['success'] ) {
			wp_send_json_error( $result['error'] );
			return;
		}

		// Update the transient cache with fresh URLs.
		// Use the stored expiry duration for this album so we respect its cache-refresh setting.
		$cache_key     = $this->get_cache_key( $album_url );
		$expiry_key    = $this->get_expiration_key( $album_url );
		$cache_duration = (int) get_option( $expiry_key, self::DEFAULT_CACHE_REFRESH * 3600 );
		set_transient(
			$cache_key,
			array(
				'title'         => $result['data']['title'] ?? null,
				'photos'        => $result['data']['photos'],
				'is_deprecated' => $result['is_deprecated'],
			),
			$cache_duration
		);

		// Prepare URLs with standard dimensions.
		$hydrated_items = $this->hydrate_cached_photo_meta(
			$result['data']['photos'],
			$album_url,
			self::DEFAULT_MAX_PHOTOS_PER_ALBUM,
			true
		);
		$photos = $this->prepare_photo_urls(
			$hydrated_items,
			self::DEFAULT_FULLSCREEN_SOURCE_WIDTH,
			self::DEFAULT_FULLSCREEN_SOURCE_HEIGHT,
			self::DEFAULT_SOURCE_WIDTH,
			self::DEFAULT_SOURCE_HEIGHT
		);

		wp_send_json_success( array( 'photos' => $photos ) );
	}

	/**
	 * Handle AJAX request to download media.
	 *
	 * Proxies the media download to bypass CORS restrictions.
	 * Backward compatibility: still accepts `image_url`.
	 */
	public function handle_download_image() {
		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'jzsa_download_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid nonce', 'janzeman-shared-albums-for-google-photos' ),
				),
				403
			);
			return;
		}

		// Get media URL (new param) with image_url fallback for backward compatibility.
		$posted_media_url = isset( $_POST['media_url'] ) ? wp_unslash( $_POST['media_url'] ) : '';
		$posted_image_url = isset( $_POST['image_url'] ) ? wp_unslash( $_POST['image_url'] ) : '';
		$raw_media_url    = ! empty( $posted_media_url ) ? $posted_media_url : $posted_image_url;
		if ( empty( $raw_media_url ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Missing media URL', 'janzeman-shared-albums-for-google-photos' ),
				),
				400
			);
			return;
		}

		$media_url = esc_url_raw( $raw_media_url );
		$filename  = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : 'media.bin';

		// Verify it's a Google Photos media URL.
		$parsed_url = wp_parse_url( $media_url );
		if ( empty( $parsed_url['scheme'] ) || 'https' !== $parsed_url['scheme'] || empty( $parsed_url['host'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid media URL', 'janzeman-shared-albums-for-google-photos' ),
				),
				400
			);
			return;
		}

		$host = strtolower( $parsed_url['host'] );
		if ( 'googleusercontent.com' !== $host && substr( $host, -strlen( '.googleusercontent.com' ) ) !== '.googleusercontent.com' ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid media URL', 'janzeman-shared-albums-for-google-photos' ),
				),
				400
			);
			return;
		}

		// Fetch the media file.
		$response = wp_remote_get(
			$media_url,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					/* translators: %s: error message returned from WordPress HTTP API */
					'message' => sprintf(
						__( 'Failed to fetch media: %s', 'janzeman-shared-albums-for-google-photos' ),
						$response->get_error_message()
					),
				),
				502
			);
			return;
		}

		// Hard maximum size guard (server-side).
		// Keeps backward compatibility with the existing filter name and adds a dedicated hard-limit filter.
		$hard_max_size_bytes = (int) apply_filters(
			'jzsa_max_download_hard_limit',
			(int) apply_filters(
				'jzsa_max_download_size',
				self::MAX_DOWNLOAD_SIZE_MB * 1024 * 1024
			)
		);

		// Per-gallery warning threshold (frontend confirmation), 0 = disabled.
		$warning_size_bytes = self::DEFAULT_DOWNLOAD_WARNING_SIZE_MB * 1024 * 1024;
		if ( isset( $_POST['warning_size_bytes'] ) ) {
			$requested_warning_size = intval( wp_unslash( $_POST['warning_size_bytes'] ) );
			if ( $requested_warning_size >= 0 ) {
				$warning_size_bytes = $requested_warning_size;
			}
		}

		// Warning threshold cannot exceed hard maximum when hard maximum is enabled.
		if ( $hard_max_size_bytes > 0 && $warning_size_bytes > $hard_max_size_bytes ) {
			$warning_size_bytes = $hard_max_size_bytes;
		}

		$allow_large_download = false;
		if ( isset( $_POST['allow_large_download'] ) ) {
			$allow_raw            = strtolower( sanitize_text_field( wp_unslash( $_POST['allow_large_download'] ) ) );
			$allow_large_download = in_array( $allow_raw, array( '1', 'true', 'yes' ), true );
		}

		$content_length = wp_remote_retrieve_header( $response, 'content-length' );
		$content_length = $content_length ? intval( $content_length ) : 0;

		if ( $hard_max_size_bytes > 0 && $content_length > $hard_max_size_bytes ) {
			wp_send_json_error(
				array(
					'message' => __( 'This file exceeds the maximum allowed download size.', 'janzeman-shared-albums-for-google-photos' ),
					'actual_size_bytes' => $content_length,
					'hard_limit_bytes'  => $hard_max_size_bytes,
					'exceeds_hard_download_limit' => true,
				),
				413
			);
			return;
		}

		if ( $warning_size_bytes > 0 && $content_length > $warning_size_bytes && ! $allow_large_download ) {
			wp_send_json_error(
				array(
					'message' => __( 'This file is larger than the configured download warning threshold.', 'janzeman-shared-albums-for-google-photos' ),
					'requires_large_download_confirmation' => true,
					'actual_size_bytes' => $content_length,
					'warning_size_bytes' => $warning_size_bytes,
				),
				413
			);
			return;
		}

		// Get media data.
		$media_data   = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		$actual_size_bytes = strlen( $media_data );
		if ( $hard_max_size_bytes > 0 && $actual_size_bytes > $hard_max_size_bytes ) {
			wp_send_json_error(
				array(
					'message' => __( 'This file exceeds the maximum allowed download size.', 'janzeman-shared-albums-for-google-photos' ),
					'actual_size_bytes' => $actual_size_bytes,
					'hard_limit_bytes'  => $hard_max_size_bytes,
					'exceeds_hard_download_limit' => true,
				),
				413
			);
			return;
		}

		if ( $warning_size_bytes > 0 && $actual_size_bytes > $warning_size_bytes && ! $allow_large_download ) {
			wp_send_json_error(
				array(
					'message' => __( 'This file is larger than the configured download warning threshold.', 'janzeman-shared-albums-for-google-photos' ),
					'requires_large_download_confirmation' => true,
					'actual_size_bytes' => $actual_size_bytes,
					'warning_size_bytes' => $warning_size_bytes,
				),
				413
			);
			return;
		}

		if ( empty( $media_data ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Empty media data', 'janzeman-shared-albums-for-google-photos' ),
				),
				502
			);
			return;
		}

		// Send media to browser with download headers.
		header( 'Content-Type: ' . ( $content_type ? $content_type : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $media_data ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Binary media data must be sent unescaped.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $media_data;
		exit;
	}
}
