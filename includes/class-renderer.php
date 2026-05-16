<?php
/**
 * Gallery Renderer: Generates HTML output for gallery display
 *
 * Output Phase - Responsible for rendering data to display format
 *
 * @package JZSA_Shared_Albums
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Swiper Renderer Class
 */
class JZSA_Renderer {

	/**
	 * Render gallery HTML
	 *
	 * @param array $config Gallery configuration
	 * @return string HTML output
	 */
	public function render( $config ) {
		$gallery_id = $this->generate_gallery_id();

		$html = '';

		// Add deprecation warning for users who can access the plugin admin UI.
		if ( ! empty( $config['show-deprecation-warning'] ) && is_user_logged_in() && current_user_can( jzsa_get_admin_capability() ) ) {
			$html .= $this->render_deprecation_notice();
		}

		// Warn privileged users if mosaic is used with an incompatible mode.
		if ( ! empty( $config['mosaic'] ) && 'gallery' === $config['mode'] && is_user_logged_in() && current_user_can( jzsa_get_admin_capability() ) ) {
			$html .= $this->render_mosaic_mode_notice();
		}

		// Gallery mode: plain thumbnail gallery, no Swiper structure
		if ( 'gallery' === $config['mode'] ) {
			$html .= $this->build_thumbnail_gallery_container( $gallery_id, $config );
			return $html;
		}

		// Build Swiper gallery container
		$html .= $this->build_gallery_container( $gallery_id, $config );

		return $html;
	}

	/**
	 * Render error message
	 *
	 * @param string $title   Error title
	 * @param string $message Error message
	 * @param string $help    Help text or link
	 * @return string HTML
	 */
	public function render_error( $title, $message, $help = '' ) {
		$full_message = $message;
		if ( ! empty( $help ) ) {
			$full_message .= ' ' . $help;
		}

		return sprintf(
			'<div class="jzsa-error" style="border: 2px solid #dc3545; border-radius: 4px; padding: 12px; margin: 8px; background: #f8d7da; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
			'<p style="margin: 0 0 6px 0; color: #721c24; font-size: 13px; font-weight: 600;">%s %s</p>' .
			'<p style="margin: 0; color: #721c24; font-size: 12px;">%s</p>' .
			'</div>',
			esc_html__( 'Error:', 'janzeman-shared-albums-for-google-photos' ),
			esc_html( $title ),
			wp_kses(
				$full_message,
				array(
					'a'      => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'strong' => array(),
				)
			)
		);
	}

	/**
	 * Render deprecation warning
	 *
	 * @return string HTML
	 */
	private function render_deprecation_notice() {
		return sprintf(
			'<div class="jzsa-warning" style="border: 2px solid #f0ad4e; border-radius: 4px; padding: 12px; margin: 8px; background: #fff9e6; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
			'<p style="margin: 0 0 6px 0; color: #856404; font-size: 13px; font-weight: 600;">%s %s</p>' .
			'<p style="margin: 0; color: #856404; font-size: 12px;">%s</p>' .
			'</div>',
			esc_html__( 'Warning (visible to administrators only):', 'janzeman-shared-albums-for-google-photos' ),
			esc_html__( 'Short Link Detected: The short link format might stop working in the future.', 'janzeman-shared-albums-for-google-photos' ),
			esc_html__( 'This warning is only shown to logged-in administrators. Please update the shortcode to use the full link format (https://photos.google.com/share/...) to ensure your gallery continues working for visitors.', 'janzeman-shared-albums-for-google-photos' )
		);
	}

	/**
	 * Render mosaic mode compatibility warning (admins only)
	 *
	 * @return string HTML
	 */
	private function render_mosaic_mode_notice() {
		return sprintf(
			'<div class="jzsa-warning" style="border: 2px solid #f0ad4e; border-radius: 4px; padding: 12px; margin: 8px; background: #fff9e6; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
			'<p style="margin: 0 0 6px 0; color: #856404; font-size: 13px; font-weight: 600;">%s %s</p>' .
			'<p style="margin: 0; color: #856404; font-size: 12px;">%s</p>' .
			'</div>',
			esc_html__( 'Warning (visible to administrators only):', 'janzeman-shared-albums-for-google-photos' ),
			esc_html__( 'Mosaic Requires Slider or Carousel Mode', 'janzeman-shared-albums-for-google-photos' ),
			esc_html__( 'The mosaic="true" parameter only works with mode="slider" or mode="carousel". It is ignored in the default gallery mode. Please add mode="slider" or mode="carousel" to your shortcode.', 'janzeman-shared-albums-for-google-photos' )
		);
	}

	/**
	 * Build gallery container HTML
	 *
	 * @param string $gallery_id Gallery ID
	 * @param array  $config     Configuration
	 * @return string HTML
	 */
	private function build_gallery_container( $gallery_id, $config ) {
		$styles = $this->build_container_styles( $config );
		$attrs  = $this->build_data_attributes( $config );
		$i18n   = jzsa_get_frontend_i18n_strings();

		$mosaic_enabled    = ! empty( $config['mosaic'] );
		$mosaic_pos        = ! empty( $config['mosaic-position'] ) ? $config['mosaic-position'] : 'right';
		$has_responsive_ar = $this->has_explicit_responsive_aspect_ratio( $config );

		$html = '';

		// Mosaic wrapper: wraps both the main gallery and the thumbnail strip.
		if ( $mosaic_enabled ) {
			$html .= sprintf(
				'<div class="jzsa-gallery-wrapper jzsa-mosaic-%s"%s style="%s">',
				esc_attr( $mosaic_pos ),
				$has_responsive_ar ? ' data-responsive-ar="true"' : '',
				esc_attr( $styles )
			);
		}

		$lightbox_on      = $this->lightbox_enabled( $config );
		$fullscreen_enabled = ! empty( $config['fullscreen-toggle'] ) && 'disabled' !== $config['fullscreen-toggle'];
		$show_fullscreen  = $fullscreen_enabled;
		$is_carousel      = isset( $config['mode'] ) && 'carousel' === $config['mode'];
		$album_classes    = 'jzsa-album swiper jzsa-loader-pending';
		// jzsa-has-dual-expand only applies to slider mode: in carousel, the per-tile
		// button handles lightbox so there is never a global lightbox button to offset.
		if ( $lightbox_on && $show_fullscreen && ! $is_carousel ) {
			$album_classes .= ' jzsa-has-dual-expand';
		}

		$html .= sprintf(
			'<div id="%s" class="%s" %s style="%s">',
			esc_attr( $gallery_id ),
			esc_attr( $album_classes ),
			$attrs,
			$mosaic_enabled ? '' : esc_attr( $styles )
		);

		$html .= '<div class="swiper-wrapper"></div>';
		$html .= '<div class="swiper-button-prev"></div>';
		$html .= '<div class="swiper-button-next"></div>';
		$html .= '<div class="swiper-pagination"></div>';
		$html .= sprintf(
			'<button class="swiper-button-play-pause" title="%s"></button>',
			esc_attr( $i18n['playPauseSpace'] )
		);
		$html .= '<div class="swiper-slideshow-progress"><div class="swiper-slideshow-progress-bar"></div></div>';

		$show_inline_link_button         = ! empty( $config['show-link-button'] );
		$show_inline_download_button     = ! empty( $config['show-download-button'] );
		$show_fullscreen_link_button     = ! empty( $config['fullscreen-show-link-button'] );
		$show_fullscreen_download_button = ! empty( $config['fullscreen-show-download-button'] );

		// External link button (if enabled in inline and/or fullscreen modes)
		if ( ( $show_inline_link_button || $show_fullscreen_link_button ) && ! empty( $config['album-url'] ) ) {
			$html .= sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="swiper-button-external-link" title="%s"></a>',
				esc_url( $config['album-url'] ),
				esc_attr( $i18n['openInGooglePhotos'] )
			);
		}

		// Download button (if enabled in inline and/or fullscreen modes)
		if ( $show_inline_download_button || $show_fullscreen_download_button ) {
			$html .= sprintf(
				'<button class="swiper-button-download" title="%s"></button>',
				esc_attr( $i18n['downloadCurrentMedia'] )
			);
		}

		// Dedicated lightbox and fullscreen buttons — each independently rendered.
		// Both appear when the author explicitly sets both (jzsa-has-dual-expand
		// class added above shifts the lightbox button left of the fullscreen one).
		// Carousel omits the global lightbox button: per-tile buttons (jzsa-carousel-tile-fs-enabled)
		// already handle lightbox triggering on each slide.
		if ( $lightbox_on && ! $is_carousel ) {
			$html .= sprintf( '<div class="swiper-button-lightbox" title="%s"></div>', esc_attr( $i18n['openLightbox'] ) );
		}
		if ( $show_fullscreen ) {
			$html .= '<div class="swiper-button-fullscreen"></div>';
		}
		$html .= '</div>'; // Close .jzsa-album

		// Mosaic thumbnail strip (Swiper-powered, synced via thumbs module).
		if ( $mosaic_enabled ) {
			$html .= sprintf(
				'<div class="jzsa-mosaic swiper" id="%s-mosaic">',
				esc_attr( $gallery_id )
			);
			$html .= '<div class="swiper-wrapper"></div>';
			$html .= '</div>';
			$html .= '</div>'; // Close .jzsa-gallery-wrapper
		}

		return $html;
	}

	/**
	 * Build container inline styles
	 *
	 * @param array $config Configuration
	 * @return string CSS styles
	 */
	private function build_container_styles( $config ) {
		$styles = array();

		$has_width       = isset( $config['width'] ) && $config['width'] !== 'auto';
		$has_height      = isset( $config['height'] ) && $config['height'] !== 'auto';
		$has_responsive_ar = $this->has_explicit_responsive_aspect_ratio( $config );

		if ( $has_width ) {
			$styles[] = 'width: ' . intval( $config['width'] ) . 'px';
			$styles[] = 'max-width: 100%';
		}

		if ( $has_height ) {
			// Always emit the fixed height so old browsers (no aspect-ratio support) keep a defined box.
			$styles[] = 'height: ' . intval( $config['height'] ) . 'px';
			// When the user explicitly set both dimensions, also store the ratio as a CSS custom
			// property. The @supports rule in swiper-style.css then uses it to override height with
			// auto + aspect-ratio on modern browsers, giving proportional scaling on narrow screens.
			if ( $has_responsive_ar ) {
				$styles[] = '--jzsa-ar: ' . intval( $config['width'] ) . ' / ' . intval( $config['height'] );
			}
		}

		if ( ! empty( $config['background-color'] ) ) {
			$styles[] = '--gallery-bg-color: ' . esc_attr( $config['background-color'] );
		}
		if ( ! empty( $config['controls-color'] ) ) {
			$styles[] = '--jzsa-controls-color: ' . esc_attr( $config['controls-color'] );
		}
		if ( ! empty( $config['video-controls-color'] ) ) {
			$styles[] = '--jzsa-video-controls-color: ' . esc_attr( $config['video-controls-color'] );
		}
		if ( isset( $config['corner-radius'] ) ) {
			$styles[] = '--jzsa-corner-radius: ' . intval( $config['corner-radius'] ) . 'px';
		}
		if ( isset( $config['mosaic-corner-radius'] ) ) {
			$styles[] = '--jzsa-mosaic-corner-radius: ' . intval( $config['mosaic-corner-radius'] ) . 'px';
		}
		if ( isset( $config['fullscreen-mosaic-corner-radius'] ) ) {
			$styles[] = '--jzsa-fullscreen-mosaic-corner-radius: ' . intval( $config['fullscreen-mosaic-corner-radius'] ) . 'px';
		}
		if ( isset( $config['info-font-size'] ) ) {
			$styles[] = '--jzsa-info-font-size: ' . intval( $config['info-font-size'] ) . 'px';
		}
		if ( ! empty( $config['info-font-family'] ) ) {
			$styles[] = '--jzsa-info-font-family: ' . $config['info-font-family'];
		}
		if ( ! empty( $config['info-font-color'] ) ) {
			$styles[] = '--jzsa-info-font-color: ' . esc_attr( $config['info-font-color'] );
		}

		return implode( '; ', $styles );
	}

	/**
	 * Build the data-lightbox-* attribute strings.
	 *
	 * The lightbox is an alternative to native fullscreen: when enabled (any
	 * value other than 'disabled') the album opens in a dimmed overlay on top of
	 * the page at a bounded size, and native fullscreen is suppressed for it.
	 * The overlay markup is built entirely in JavaScript, which reads these
	 * attributes from the source album container.
	 *
	 * @param array $config Configuration.
	 * @return string[] Array of `name="value"` attribute strings.
	 */
	private function build_lightbox_data_attributes( $config ) {
		$attrs = array();

		$mode = isset( $config['lightbox-toggle'] ) ? $config['lightbox-toggle'] : 'disabled';
		// interaction-lock is a hard override for all interactions (same as fullscreen).
		if ( ! empty( $config['interaction-lock'] ) ) {
			$mode = 'disabled';
		}
		// Always emit data-lightbox-toggle so JS can branch deterministically.
		$attrs[] = sprintf( 'data-lightbox-toggle="%s"', esc_attr( $mode ) );

		if ( 'disabled' === $mode ) {
			return $attrs;
		}

		if ( ! empty( $config['lightbox-image-fit'] ) ) {
			$attrs[] = sprintf( 'data-lightbox-image-fit="%s"', esc_attr( $config['lightbox-image-fit'] ) );
		}
		if ( isset( $config['lightbox-max-width'] ) && null !== $config['lightbox-max-width'] ) {
			$attrs[] = sprintf( 'data-lightbox-max-width="%d"', intval( $config['lightbox-max-width'] ) );
		}
		if ( isset( $config['lightbox-max-height'] ) && null !== $config['lightbox-max-height'] ) {
			$attrs[] = sprintf( 'data-lightbox-max-height="%d"', intval( $config['lightbox-max-height'] ) );
		}
		if ( ! empty( $config['lightbox-background-color'] ) ) {
			$attrs[] = sprintf( 'data-lightbox-background-color="%s"', esc_attr( $config['lightbox-background-color'] ) );
		}
		if ( ! empty( $config['lightbox-corner-radius'] ) ) {
			$attrs[] = sprintf( 'data-lightbox-corner-radius="%d"', intval( $config['lightbox-corner-radius'] ) );
		}

		return $attrs;
	}

	/**
	 * Whether the album expands into the JS lightbox overlay (vs native fullscreen / nothing).
	 *
	 * @param array $config Configuration.
	 * @return bool
	 */
	private function lightbox_enabled( $config ) {
		return ! empty( $config['lightbox-toggle'] ) && 'disabled' !== $config['lightbox-toggle'] && empty( $config['interaction-lock'] );
	}

	/**
	 * Build data attributes for gallery
	 *
	 * @param array $config Configuration
	 * @return string HTML attributes
	 */
	private function build_data_attributes( $config ) {
		$attrs = array();

		// Photo URLs as JSON
		if ( ! empty( $config['photos'] ) ) {
			$attrs[] = sprintf( 'data-all-photos=\'%s\'', esc_attr( wp_json_encode( $config['photos'] ) ) );
		}
		if ( isset( $config['progressive-total-count'] ) ) {
			$attrs[] = sprintf( 'data-total-count="%d"', intval( $config['progressive-total-count'] ) );
		} elseif ( ! empty( $config['photos'] ) ) {
			$attrs[] = sprintf( 'data-total-count="%d"', count( $config['photos'] ) );
		}

		// Slideshow mode (string: auto / manual / disabled)
		if ( isset( $config['slideshow'] ) ) {
			$attrs[] = sprintf( 'data-slideshow="%s"', esc_attr( $config['slideshow'] ) );
		}
		if ( isset( $config['fullscreen-slideshow'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-slideshow="%s"', esc_attr( $config['fullscreen-slideshow'] ) );
		}

		// Gallery settings
		$boolean_attrs = array(
			'interaction-lock'        => 'data-interaction-lock',
			'show-navigation'         => 'data-show-navigation',
			'fullscreen-show-navigation' => 'data-fullscreen-show-navigation',
			'show-link-button'        => 'data-show-link-button',
			'show-download-button'    => 'data-show-download-button',
			'fullscreen-show-link-button'     => 'data-fullscreen-show-link-button',
			'fullscreen-show-download-button' => 'data-fullscreen-show-download-button',
			'video-controls-autohide' => 'data-video-controls-autohide',
			'fullscreen-video-controls-autohide' => 'data-fullscreen-video-controls-autohide',
			'info-halo-effect'       => 'data-info-halo-effect',
			'info-top-halo-effect'   => 'data-info-top-halo-effect',
			'info-top-secondary-halo-effect' => 'data-info-top-secondary-halo-effect',
			'info-bottom-halo-effect' => 'data-info-bottom-halo-effect',
			'gallery-info-bottom-halo-effect' => 'data-gallery-info-bottom-halo-effect',
			'album-title-halo-effect' => 'data-album-title-halo-effect',
			'mosaic'                  => 'data-mosaic',
			'fullscreen-mosaic'       => 'data-fullscreen-mosaic',
		);

		foreach ( $boolean_attrs as $key => $attr_name ) {
			if ( isset( $config[ $key ] ) ) {
				$attrs[] = sprintf( '%s="%s"', $attr_name, $config[ $key ] ? 'true' : 'false' );
			}
		}

		// PHP-emits data-has-active-bottom-center so CSS can position the play button
		// correctly before JavaScript runs (JS will update it on fullscreen toggle).
		$attrs[] = sprintf( 'data-has-active-bottom-center="%s"', ! empty( $config['info-bottom'] ) ? 'true' : 'false' );

		// Info box format strings (only emit non-empty).
		$info_boxes = array(
			'info-bottom',  'fullscreen-info-bottom',
			'info-top',     'fullscreen-info-top',
			'info-top-secondary',     'fullscreen-info-top-secondary',
		);
		foreach ( $info_boxes as $box_key ) {
			if ( ! empty( $config[ $box_key ] ) ) {
				$attrs[] = sprintf( 'data-%s="%s"', $box_key, esc_attr( $config[ $box_key ] ) );
			}
		}

		// Mosaic attributes
		if ( ! empty( $config['mosaic-position'] ) ) {
			$attrs[] = sprintf( 'data-mosaic-position="%s"', esc_attr( $config['mosaic-position'] ) );
		}

		if ( isset( $config['mosaic-count'] ) ) {
			$attrs[] = sprintf( 'data-mosaic-count="%d"', intval( $config['mosaic-count'] ) );
		}

		if ( isset( $config['mosaic-gap'] ) ) {
			$attrs[] = sprintf( 'data-mosaic-gap="%d"', intval( $config['mosaic-gap'] ) );
		}

		if ( isset( $config['mosaic-opacity'] ) ) {
			$attrs[] = sprintf( 'data-mosaic-opacity="%s"', esc_attr( $config['mosaic-opacity'] ) );
		}

		if ( ! empty( $config['mosaic-background'] ) ) {
			$attrs[] = sprintf( 'data-mosaic-background="%s"', esc_attr( $config['mosaic-background'] ) );
		}

		if ( ! empty( $config['fullscreen-mosaic-position'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-position="%s"', esc_attr( $config['fullscreen-mosaic-position'] ) );
		}

		if ( ! empty( $config['fullscreen-mosaic-layout'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-layout="%s"', esc_attr( $config['fullscreen-mosaic-layout'] ) );
		}

		if ( isset( $config['fullscreen-mosaic-count'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-count="%d"', intval( $config['fullscreen-mosaic-count'] ) );
		}

		if ( isset( $config['fullscreen-mosaic-gap'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-gap="%d"', intval( $config['fullscreen-mosaic-gap'] ) );
		}

		if ( isset( $config['fullscreen-mosaic-opacity'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-opacity="%s"', esc_attr( $config['fullscreen-mosaic-opacity'] ) );
		}

		if ( ! empty( $config['fullscreen-mosaic-background'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-background="%s"', esc_attr( $config['fullscreen-mosaic-background'] ) );
		}

		if ( isset( $config['fullscreen-mosaic-corner-radius'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-corner-radius="%d"', intval( $config['fullscreen-mosaic-corner-radius'] ) );
		}

		// Numeric/string attributes
		if ( isset( $config['slideshow-delay'] ) ) {
			$attrs[] = sprintf( 'data-slideshow-delay="%s"', esc_attr( $config['slideshow-delay'] ) );
		}

		if ( isset( $config['download-size-warning'] ) ) {
			$warning_size = intval( $config['download-size-warning'] );
			$attrs[]      = sprintf( 'data-download-size-warning="%d"', $warning_size );
		}

		if ( ! empty( $config['image-fit'] ) ) {
			$attrs[] = sprintf( 'data-image-fit="%s"', esc_attr( $config['image-fit'] ) );
		}

		if ( ! empty( $config['fullscreen-image-fit'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-image-fit="%s"', esc_attr( $config['fullscreen-image-fit'] ) );
		}
		if ( isset( $config['fullscreen-display-max-width'] ) && null !== $config['fullscreen-display-max-width'] ) {
			$attrs[] = sprintf( 'data-fullscreen-display-max-width="%d"', intval( $config['fullscreen-display-max-width'] ) );
		}
		if ( isset( $config['fullscreen-display-max-height'] ) && null !== $config['fullscreen-display-max-height'] ) {
			$attrs[] = sprintf( 'data-fullscreen-display-max-height="%d"', intval( $config['fullscreen-display-max-height'] ) );
		}

		if ( isset( $config['start-at'] ) && '' !== $config['start-at'] ) {
			$attrs[] = sprintf( 'data-start-at="%s"', esc_attr( $config['start-at'] ) );
		}

		if ( isset( $config['fullscreen-slideshow-delay'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-slideshow-delay="%s"', esc_attr( $config['fullscreen-slideshow-delay'] ) );
		}

		if ( isset( $config['slideshow-autoresume'] ) ) {
			$attrs[] = sprintf( 'data-slideshow-autoresume="%s"', esc_attr( $config['slideshow-autoresume'] ) );
		}
		if ( isset( $config['fullscreen-slideshow-autoresume'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-slideshow-autoresume="%s"', esc_attr( $config['fullscreen-slideshow-autoresume'] ) );
		}

		if ( ! empty( $config['mode'] ) ) {
			$attrs[] = sprintf( 'data-mode="%s"', esc_attr( $config['mode'] ) );
		}

		if ( ! empty( $config['background-color'] ) ) {
			$attrs[] = sprintf( 'data-background-color="%s"', esc_attr( $config['background-color'] ) );
		}
		if ( ! empty( $config['fullscreen-background-color'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-background-color="%s"', esc_attr( $config['fullscreen-background-color'] ) );
		}
		if ( ! empty( $config['controls-color'] ) ) {
			$attrs[] = sprintf( 'data-controls-color="%s"', esc_attr( $config['controls-color'] ) );
		}
		if ( ! empty( $config['fullscreen-controls-color'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-controls-color="%s"', esc_attr( $config['fullscreen-controls-color'] ) );
		}
		if ( ! empty( $config['video-controls-color'] ) ) {
			$attrs[] = sprintf( 'data-video-controls-color="%s"', esc_attr( $config['video-controls-color'] ) );
		}
		if ( ! empty( $config['fullscreen-video-controls-color'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-video-controls-color="%s"', esc_attr( $config['fullscreen-video-controls-color'] ) );
		}

		if ( ! empty( $config['album-title'] ) ) {
			$attrs[] = sprintf( 'data-album-title="%s"', esc_attr( $config['album-title'] ) );
		}

		if ( ! empty( $config['fullscreen-toggle'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-toggle="%s"', esc_attr( $config['fullscreen-toggle'] ) );
		}


		if ( ! empty( $config['album-url'] ) ) {
			$attrs[] = sprintf( 'data-album-url="%s"', esc_url( $config['album-url'] ) );
		}
		if ( ! empty( $config['progressive-loading'] ) ) {
			$attrs[] = 'data-progressive-loading="true"';
			$attrs[] = sprintf( 'data-progressive-initial-chunk-size="%d"', intval( $config['progressive-initial-chunk-size'] ) );
			$attrs[] = sprintf( 'data-progressive-chunk-size="%d"', intval( $config['progressive-chunk-size'] ) );
			$attrs[] = sprintf( 'data-progressive-limit="%d"', intval( $config['limit'] ) );
			$attrs[] = sprintf( 'data-progressive-show-videos="%s"', ! empty( $config['show-videos'] ) ? 'true' : 'false' );
			$attrs[] = sprintf( 'data-progressive-source-width="%d"', intval( $config['source-width'] ) );
			$attrs[] = sprintf( 'data-progressive-source-height="%d"', intval( $config['source-height'] ) );
			$attrs[] = sprintf( 'data-progressive-fullscreen-source-width="%d"', intval( $config['fullscreen-source-width'] ) );
			$attrs[] = sprintf( 'data-progressive-fullscreen-source-height="%d"', intval( $config['fullscreen-source-height'] ) );
		}
		if ( isset( $config['info-font-size'] ) ) {
			$attrs[] = sprintf( 'data-info-font-size="%d"', intval( $config['info-font-size'] ) );
		}
		if ( ! empty( $config['info-wrap'] ) ) {
			$attrs[] = 'data-info-wrap="true"';
		}
		if ( ! empty( $config['info-text-align'] ) && 'center' !== $config['info-text-align'] ) {
			$attrs[] = sprintf( 'data-info-text-align="%s"', esc_attr( $config['info-text-align'] ) );
		}
		foreach ( array( 'info-top-text-align', 'info-top-secondary-text-align', 'info-bottom-text-align' ) as $align_key ) {
			if ( ! empty( $config[ $align_key ] ) ) {
				$attrs[] = sprintf( 'data-%s="%s"', $align_key, esc_attr( $config[ $align_key ] ) );
			}
		}
		if ( isset( $config['fullscreen-info-font-size'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-info-font-size="%d"', intval( $config['fullscreen-info-font-size'] ) );
		}
		if ( ! empty( $config['info-font-family'] ) ) {
			$attrs[] = sprintf( 'data-info-font-family="%s"', esc_attr( $config['info-font-family'] ) );
		}
		if ( ! empty( $config['fullscreen-info-font-family'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-info-font-family="%s"', esc_attr( $config['fullscreen-info-font-family'] ) );
		}
		if ( ! empty( $config['info-font-color'] ) ) {
			$attrs[] = sprintf( 'data-info-font-color="%s"', esc_attr( $config['info-font-color'] ) );
		}
		if ( ! empty( $config['fullscreen-info-font-color'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-info-font-color="%s"', esc_attr( $config['fullscreen-info-font-color'] ) );
		}

		// Flag for CSS @supports aspect-ratio rule: both dimensions were explicitly set in the shortcode.
		if ( $this->has_explicit_responsive_aspect_ratio( $config ) ) {
			$attrs[] = 'data-responsive-ar="true"';
		}

		// Lightbox (alternative to native fullscreen).
		$attrs = array_merge( $attrs, $this->build_lightbox_data_attributes( $config ) );

		return implode( ' ', $attrs );
	}

	/**
	 * Check whether width and height were both explicitly set in the shortcode.
	 *
	 * This enables responsive aspect-ratio scaling on narrow screens without
	 * changing gallery mode or non-explicit default sizing semantics.
	 *
	 * @param array $config Gallery configuration.
	 * @return bool
	 */
	private function has_explicit_responsive_aspect_ratio( $config ) {
		return ! empty( $config['width-explicit'] )
			&& ! empty( $config['height-explicit'] )
			&& isset( $config['width'] ) && $config['width'] !== 'auto'
			&& isset( $config['height'] ) && $config['height'] !== 'auto';
	}

	/**
	 * Build gallery-mode container HTML (no Swiper - plain thumbnail gallery).
	 * Thumbnails and layout are rendered by JS after page load.
	 *
	 * @param string $gallery_id Gallery ID.
	 * @param array  $config     Configuration.
	 * @return string HTML.
	 */
	private function build_thumbnail_gallery_container( $gallery_id, $config ) {
		$attrs = array();

		if ( ! empty( $config['photos'] ) ) {
			$attrs[] = sprintf( 'data-all-photos=\'%s\'', esc_attr( wp_json_encode( $config['photos'] ) ) );
			$attrs[] = sprintf( 'data-total-count="%d"', count( $config['photos'] ) );
		}

		$gallery_layout       = isset( $config['gallery-layout'] ) ? $config['gallery-layout'] : 'grid';
		$gallery_sizing       = isset( $config['gallery-sizing'] ) ? $config['gallery-sizing'] : 'ratio';
		$gallery_columns      = isset( $config['gallery-columns'] ) ? intval( $config['gallery-columns'] ) : 3;
		$gallery_columns_t    = isset( $config['gallery-columns-tablet'] ) ? intval( $config['gallery-columns-tablet'] ) : 2;
		$gallery_columns_m    = isset( $config['gallery-columns-mobile'] ) ? intval( $config['gallery-columns-mobile'] ) : 1;
		$gallery_row_height   = isset( $config['gallery-row-height'] ) ? intval( $config['gallery-row-height'] ) : 200;
		$gallery_rows         = isset( $config['gallery-rows'] ) ? intval( $config['gallery-rows'] ) : 0;
		$gallery_scrollable   = ! empty( $config['gallery-scrollable'] );
		$buttons_on_mobile    = isset( $config['gallery-buttons-on-mobile'] ) ? $config['gallery-buttons-on-mobile'] : 'on-interaction';

		$attrs[] = 'data-mode="gallery"';

		// Canonical gallery-* attributes.
		$attrs[] = sprintf( 'data-gallery-layout="%s"', esc_attr( $gallery_layout ) );
		$attrs[] = sprintf( 'data-gallery-sizing="%s"', esc_attr( $gallery_sizing ) );
		$attrs[] = sprintf( 'data-gallery-columns="%d"', $gallery_columns );
		$attrs[] = sprintf( 'data-gallery-columns-tablet="%d"', $gallery_columns_t );
		$attrs[] = sprintf( 'data-gallery-columns-mobile="%d"', $gallery_columns_m );
		$attrs[] = sprintf( 'data-gallery-row-height="%d"', $gallery_row_height );
		$attrs[] = sprintf( 'data-gallery-rows="%d"', $gallery_rows );
		$attrs[] = sprintf( 'data-gallery-scrollable="%s"', $gallery_scrollable ? 'true' : 'false' );
		$attrs[] = sprintf( 'data-gallery-buttons-on-mobile="%s"', esc_attr( $buttons_on_mobile ) );
		if ( isset( $config['gallery-gap'] ) ) {
			$attrs[] = sprintf( 'data-gallery-gap="%d"', intval( $config['gallery-gap'] ) );
		}

		if ( ! empty( $config['width-explicit'] ) && isset( $config['width'] ) && $config['width'] !== 'auto' ) {
			$attrs[] = sprintf( 'data-gallery-explicit-width="%d"', intval( $config['width'] ) );
		}
		if ( ! empty( $config['height-explicit'] ) && isset( $config['height'] ) && $config['height'] !== 'auto' ) {
			$attrs[] = sprintf( 'data-gallery-explicit-height="%d"', intval( $config['height'] ) );
		}

		if ( isset( $config['slideshow'] ) ) {
			$attrs[] = sprintf( 'data-slideshow="%s"', esc_attr( $config['slideshow'] ) );
		}

		if ( isset( $config['slideshow-delay'] ) ) {
			$attrs[] = sprintf( 'data-slideshow-delay="%s"', esc_attr( $config['slideshow-delay'] ) );
		}

		if ( isset( $config['download-size-warning'] ) ) {
			$warning_size = intval( $config['download-size-warning'] );
			$attrs[]      = sprintf( 'data-download-size-warning="%d"', $warning_size );
		}

		if ( isset( $config['start-at'] ) && '' !== $config['start-at'] ) {
			$attrs[] = sprintf( 'data-start-at="%s"', esc_attr( $config['start-at'] ) );
		}

		if ( ! empty( $config['album-url'] ) ) {
			$attrs[] = sprintf( 'data-album-url="%s"', esc_url( $config['album-url'] ) );
		}

		if ( ! empty( $config['album-title'] ) ) {
			$attrs[] = sprintf( 'data-album-title="%s"', esc_attr( $config['album-title'] ) );
		}

		// Fullscreen slideshow mode (string: auto / manual / disabled)
		if ( isset( $config['fullscreen-slideshow'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-slideshow="%s"', esc_attr( $config['fullscreen-slideshow'] ) );
		}

		// Fullscreen slideshow settings (gallery click opens fullscreen slideshow)
		$slideshow_booleans = array(
			'interaction-lock'        => 'data-interaction-lock',
			'show-navigation'         => 'data-show-navigation',
			'fullscreen-show-navigation' => 'data-fullscreen-show-navigation',
			'show-link-button'        => 'data-show-link-button',
			'show-download-button'    => 'data-show-download-button',
			'fullscreen-show-link-button'     => 'data-fullscreen-show-link-button',
			'fullscreen-show-download-button' => 'data-fullscreen-show-download-button',
			'video-controls-autohide' => 'data-video-controls-autohide',
			'fullscreen-video-controls-autohide' => 'data-fullscreen-video-controls-autohide',
			'info-halo-effect'       => 'data-info-halo-effect',
			'info-top-halo-effect'   => 'data-info-top-halo-effect',
			'info-top-secondary-halo-effect' => 'data-info-top-secondary-halo-effect',
			'info-bottom-halo-effect' => 'data-info-bottom-halo-effect',
			'gallery-info-bottom-halo-effect' => 'data-gallery-info-bottom-halo-effect',
			'album-title-halo-effect' => 'data-album-title-halo-effect',
			'fullscreen-mosaic'       => 'data-fullscreen-mosaic',
		);
		foreach ( $slideshow_booleans as $key => $attr_name ) {
			if ( isset( $config[ $key ] ) ) {
				$attrs[] = sprintf( '%s="%s"', $attr_name, $config[ $key ] ? 'true' : 'false' );
			}
		}

		// PHP-emits data-has-active-bottom-center so CSS can position the play button
		// correctly before JavaScript runs (JS will update it on fullscreen toggle).
		$attrs[] = sprintf( 'data-has-active-bottom-center="%s"', ! empty( $config['info-bottom'] ) ? 'true' : 'false' );

			// Info box format strings (only emit non-empty).
			$gallery_info_boxes = array(
				'info-bottom',  'fullscreen-info-bottom',
				'info-top',     'fullscreen-info-top',
				'info-top-secondary',     'fullscreen-info-top-secondary',
			);
			foreach ( $gallery_info_boxes as $box_key ) {
				if ( ! empty( $config[ $box_key ] ) ) {
					$attrs[] = sprintf( 'data-%s="%s"', $box_key, esc_attr( $config[ $box_key ] ) );
			}
			}
			if ( array_key_exists( 'gallery-info-bottom', $config ) ) {
					$attrs[] = sprintf( 'data-gallery-info-bottom="%s"', esc_attr( $config['gallery-info-bottom'] ) );
				}

		if ( isset( $config['fullscreen-slideshow-delay'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-slideshow-delay="%s"', esc_attr( $config['fullscreen-slideshow-delay'] ) );
		}

		if ( isset( $config['slideshow-autoresume'] ) ) {
			$attrs[] = sprintf( 'data-slideshow-autoresume="%s"', esc_attr( $config['slideshow-autoresume'] ) );
		}
		if ( isset( $config['fullscreen-slideshow-autoresume'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-slideshow-autoresume="%s"', esc_attr( $config['fullscreen-slideshow-autoresume'] ) );
		}

		if ( ! empty( $config['fullscreen-toggle'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-toggle="%s"', esc_attr( $config['fullscreen-toggle'] ) );
		}


		if ( ! empty( $config['image-fit'] ) ) {
			$attrs[] = sprintf( 'data-image-fit="%s"', esc_attr( $config['image-fit'] ) );
		}

		if ( ! empty( $config['fullscreen-image-fit'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-image-fit="%s"', esc_attr( $config['fullscreen-image-fit'] ) );
		}
		if ( ! empty( $config['mosaic-background'] ) ) {
			$attrs[] = sprintf( 'data-mosaic-background="%s"', esc_attr( $config['mosaic-background'] ) );
		}

		if ( ! empty( $config['fullscreen-mosaic-position'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-position="%s"', esc_attr( $config['fullscreen-mosaic-position'] ) );
		}
		if ( ! empty( $config['fullscreen-mosaic-layout'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-layout="%s"', esc_attr( $config['fullscreen-mosaic-layout'] ) );
		}
		if ( isset( $config['fullscreen-mosaic-count'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-count="%d"', intval( $config['fullscreen-mosaic-count'] ) );
		}
		if ( isset( $config['fullscreen-mosaic-gap'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-gap="%d"', intval( $config['fullscreen-mosaic-gap'] ) );
		}
		if ( isset( $config['fullscreen-mosaic-opacity'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-opacity="%s"', esc_attr( $config['fullscreen-mosaic-opacity'] ) );
		}
		if ( ! empty( $config['fullscreen-mosaic-background'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-background="%s"', esc_attr( $config['fullscreen-mosaic-background'] ) );
		}

		if ( isset( $config['fullscreen-mosaic-corner-radius'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-mosaic-corner-radius="%d"', intval( $config['fullscreen-mosaic-corner-radius'] ) );
		}
		if ( isset( $config['fullscreen-display-max-width'] ) && null !== $config['fullscreen-display-max-width'] ) {
			$attrs[] = sprintf( 'data-fullscreen-display-max-width="%d"', intval( $config['fullscreen-display-max-width'] ) );
		}
		if ( isset( $config['fullscreen-display-max-height'] ) && null !== $config['fullscreen-display-max-height'] ) {
			$attrs[] = sprintf( 'data-fullscreen-display-max-height="%d"', intval( $config['fullscreen-display-max-height'] ) );
		}

		if ( ! empty( $config['background-color'] ) ) {
			$attrs[] = sprintf( 'data-background-color="%s"', esc_attr( $config['background-color'] ) );
		}
		if ( ! empty( $config['fullscreen-background-color'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-background-color="%s"', esc_attr( $config['fullscreen-background-color'] ) );
		}
		if ( ! empty( $config['controls-color'] ) ) {
			$attrs[] = sprintf( 'data-controls-color="%s"', esc_attr( $config['controls-color'] ) );
		}
		if ( ! empty( $config['fullscreen-controls-color'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-controls-color="%s"', esc_attr( $config['fullscreen-controls-color'] ) );
		}
		if ( ! empty( $config['video-controls-color'] ) ) {
			$attrs[] = sprintf( 'data-video-controls-color="%s"', esc_attr( $config['video-controls-color'] ) );
		}
		if ( ! empty( $config['fullscreen-video-controls-color'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-video-controls-color="%s"', esc_attr( $config['fullscreen-video-controls-color'] ) );
		}
		if ( isset( $config['info-font-size'] ) ) {
			$attrs[] = sprintf( 'data-info-font-size="%d"', intval( $config['info-font-size'] ) );
		}
		if ( ! empty( $config['info-wrap'] ) ) {
			$attrs[] = 'data-info-wrap="true"';
		}
		if ( ! empty( $config['info-text-align'] ) && 'center' !== $config['info-text-align'] ) {
			$attrs[] = sprintf( 'data-info-text-align="%s"', esc_attr( $config['info-text-align'] ) );
		}
		foreach ( array( 'info-top-text-align', 'info-top-secondary-text-align', 'info-bottom-text-align' ) as $align_key ) {
			if ( ! empty( $config[ $align_key ] ) ) {
				$attrs[] = sprintf( 'data-%s="%s"', $align_key, esc_attr( $config[ $align_key ] ) );
			}
		}
		if ( isset( $config['fullscreen-info-font-size'] ) ) {
			$attrs[] = sprintf( 'data-fullscreen-info-font-size="%d"', intval( $config['fullscreen-info-font-size'] ) );
		}
			if ( ! empty( $config['info-font-family'] ) ) {
				$attrs[] = sprintf( 'data-info-font-family="%s"', esc_attr( $config['info-font-family'] ) );
			}
			if ( ! empty( $config['fullscreen-info-font-family'] ) ) {
				$attrs[] = sprintf( 'data-fullscreen-info-font-family="%s"', esc_attr( $config['fullscreen-info-font-family'] ) );
			}
			if ( ! empty( $config['info-font-color'] ) ) {
				$attrs[] = sprintf( 'data-info-font-color="%s"', esc_attr( $config['info-font-color'] ) );
			}
			if ( ! empty( $config['fullscreen-info-font-color'] ) ) {
				$attrs[] = sprintf( 'data-fullscreen-info-font-color="%s"', esc_attr( $config['fullscreen-info-font-color'] ) );
			}

			// Lightbox (alternative to native fullscreen).
			$attrs = array_merge( $attrs, $this->build_lightbox_data_attributes( $config ) );

			// Gallery mode should keep responsive sizing unless width/height
		// were explicitly provided in shortcode.
		$styles = array();
		if ( ! empty( $config['background-color'] ) ) {
			$styles[] = '--gallery-bg-color: ' . esc_attr( $config['background-color'] );
		}
		if ( ! empty( $config['controls-color'] ) ) {
			$styles[] = '--jzsa-controls-color: ' . esc_attr( $config['controls-color'] );
		}
		if ( ! empty( $config['video-controls-color'] ) ) {
			$styles[] = '--jzsa-video-controls-color: ' . esc_attr( $config['video-controls-color'] );
		}
		if ( isset( $config['corner-radius'] ) ) {
			$styles[] = '--jzsa-corner-radius: ' . intval( $config['corner-radius'] ) . 'px';
		}
		if ( isset( $config['mosaic-corner-radius'] ) ) {
			$styles[] = '--jzsa-mosaic-corner-radius: ' . intval( $config['mosaic-corner-radius'] ) . 'px';
		}
		if ( isset( $config['fullscreen-mosaic-corner-radius'] ) ) {
			$styles[] = '--jzsa-fullscreen-mosaic-corner-radius: ' . intval( $config['fullscreen-mosaic-corner-radius'] ) . 'px';
		}
		if ( isset( $config['info-font-size'] ) ) {
			$styles[] = '--jzsa-info-font-size: ' . intval( $config['info-font-size'] ) . 'px';
		}
		if ( ! empty( $config['info-font-family'] ) ) {
			$styles[] = '--jzsa-info-font-family: ' . $config['info-font-family'];
		}
		if ( ! empty( $config['info-font-color'] ) ) {
			$styles[] = '--jzsa-info-font-color: ' . esc_attr( $config['info-font-color'] );
		}
		if ( ! empty( $config['width-explicit'] ) && isset( $config['width'] ) && $config['width'] !== 'auto' ) {
			$styles[] = 'width: ' . intval( $config['width'] ) . 'px';
			$styles[] = 'max-width: 100%';
		}
		if ( ! empty( $config['height-explicit'] ) && isset( $config['height'] ) && $config['height'] !== 'auto' ) {
			$styles[] = 'height: ' . intval( $config['height'] ) . 'px';
		}
		$style_attr = ! empty( $styles ) ? sprintf( ' style="%s"', esc_attr( implode( '; ', $styles ) ) ) : '';

		return sprintf(
			'<div id="%s" class="jzsa-album jzsa-gallery-album jzsa-loader-pending jzsa-gallery-loading" %s%s></div>',
			esc_attr( $gallery_id ),
			implode( ' ', $attrs ),
			$style_attr
		);
	}

	/**
	 * Generate unique gallery ID
	 *
	 * @return string Gallery ID
	 */
	private function generate_gallery_id() {
		if ( function_exists( 'wp_unique_id' ) ) {
			return wp_unique_id( 'jzsa-gallery-' );
		}

		return 'jzsa-gallery-' . uniqid();
	}
}
