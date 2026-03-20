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

		// Add deprecation warning if needed (admins only)
		if ( ! empty( $config['show-deprecation-warning'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			$html .= $this->render_deprecation_notice();
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
	 * Build gallery container HTML
	 *
	 * @param string $gallery_id Gallery ID
	 * @param array  $config     Configuration
	 * @return string HTML
	 */
	private function build_gallery_container( $gallery_id, $config ) {
		$styles = $this->build_container_styles( $config );
		$attrs  = $this->build_data_attributes( $config );

		$html = sprintf(
			'<div id="%s" class="jzsa-album swiper jzsa-loader-pending jzsa-content-intro" %s style="%s">',
			esc_attr( $gallery_id ),
			$attrs,
			esc_attr( $styles )
		);

		$html .= '<div class="swiper-wrapper"></div>';
		$html .= '<div class="swiper-button-prev"></div>';
		$html .= '<div class="swiper-button-next"></div>';
		$html .= '<div class="swiper-pagination"></div>';
		$html .= sprintf(
			'<button class="swiper-button-play-pause" title="%s"></button>',
			esc_attr__( 'Play/Pause (Space)', 'janzeman-shared-albums-for-google-photos' )
		);
		$html .= '<div class="swiper-slideshow-progress"><div class="swiper-slideshow-progress-bar"></div></div>';

		// External link button (if enabled)
		if ( ! empty( $config['show-link-button'] ) && ! empty( $config['album-url'] ) ) {
			$html .= sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="swiper-button-external-link" title="%s"></a>',
				esc_url( $config['album-url'] ),
				esc_attr__( 'Open in Google Photos', 'janzeman-shared-albums-for-google-photos' )
			);
		}

		// Download button (if enabled)
		if ( ! empty( $config['show-download-button'] ) ) {
			$html .= sprintf(
				'<button class="swiper-button-download" title="%s"></button>',
				esc_attr__( 'Download current image', 'janzeman-shared-albums-for-google-photos' )
			);
		}

		if ( empty( $config['full-screen-toggle'] ) || 'disabled' !== $config['full-screen-toggle'] ) {
			$html .= '<div class="swiper-button-fullscreen"></div>';
		}
		$html .= '</div>';

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

		if ( isset( $config['width'] ) && $config['width'] !== 'auto' ) {
			$styles[] = 'width: ' . intval( $config['width'] ) . 'px';
		}

		if ( isset( $config['height'] ) && $config['height'] !== 'auto' ) {
			$styles[] = 'height: ' . intval( $config['height'] ) . 'px';
		}

		if ( ! empty( $config['background-color'] ) ) {
			$styles[] = '--gallery-bg-color: ' . esc_attr( $config['background-color'] );
		}
		if ( ! empty( $config['video-controls-color'] ) ) {
			$styles[] = '--jzsa-video-controls-color: ' . esc_attr( $config['video-controls-color'] );
		}

		return implode( '; ', $styles );
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
			$attrs[] = sprintf( 'data-total-count="%d"', count( $config['photos'] ) );
		}

		// Gallery settings
		$boolean_attrs = array(
			'slideshow'             => 'data-slideshow',
			'full-screen-slideshow' => 'data-full-screen-slideshow',
			'show-title'           => 'data-show-title',
			'show-counter'         => 'data-show-counter',
			'show-link-button'     => 'data-show-link-button',
			'show-download-button' => 'data-show-download-button',
			'video-autohide-controls' => 'data-video-autohide-controls',
		);

		foreach ( $boolean_attrs as $key => $attr_name ) {
			if ( isset( $config[ $key ] ) ) {
				$attrs[] = sprintf( '%s="%s"', $attr_name, $config[ $key ] ? 'true' : 'false' );
			}
		}

		// Numeric/string attributes
		if ( isset( $config['slideshow-delay'] ) ) {
			$attrs[] = sprintf( 'data-slideshow-delay="%s"', esc_attr( $config['slideshow-delay'] ) );
		}

		if ( ! empty( $config['image-fit'] ) ) {
			$attrs[] = sprintf( 'data-image-fit="%s"', esc_attr( $config['image-fit'] ) );
		}

		if ( ! empty( $config['full-screen-image-fit'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-image-fit="%s"', esc_attr( $config['full-screen-image-fit'] ) );
		}

		if ( isset( $config['start-at'] ) && '' !== $config['start-at'] ) {
			$attrs[] = sprintf( 'data-start-at="%s"', esc_attr( $config['start-at'] ) );
		}

		if ( isset( $config['full-screen-slideshow-delay'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-slideshow-delay="%s"', esc_attr( $config['full-screen-slideshow-delay'] ) );
		}

		if ( isset( $config['slideshow-inactivity-timeout'] ) ) {
			$attrs[] = sprintf( 'data-slideshow-inactivity-timeout="%s"', esc_attr( $config['slideshow-inactivity-timeout'] ) );
		}

		if ( ! empty( $config['mode'] ) ) {
			$attrs[] = sprintf( 'data-mode="%s"', esc_attr( $config['mode'] ) );
		}

		if ( ! empty( $config['background-color'] ) ) {
			$attrs[] = sprintf( 'data-background-color="%s"', esc_attr( $config['background-color'] ) );
		}

		if ( ! empty( $config['album-title'] ) ) {
			$attrs[] = sprintf( 'data-album-title="%s"', esc_attr( $config['album-title'] ) );
		}

		if ( ! empty( $config['full-screen-toggle'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-toggle="%s"', esc_attr( $config['full-screen-toggle'] ) );
		}

		if ( ! empty( $config['album-url'] ) ) {
			$attrs[] = sprintf( 'data-album-url="%s"', esc_url( $config['album-url'] ) );
		}

		return implode( ' ', $attrs );
	}

	/**
	 * Build gallery-mode container HTML (no Swiper — plain thumbnail gallery).
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

		$gallery_layout       = isset( $config['gallery-layout'] ) ? $config['gallery-layout'] : 'uniform';
		$gallery_sizing_model = isset( $config['gallery-sizing-model'] ) ? $config['gallery-sizing-model'] : 'ratio';
		$gallery_columns      = isset( $config['gallery-columns'] ) ? intval( $config['gallery-columns'] ) : 3;
		$gallery_columns_t    = isset( $config['gallery-columns-tablet'] ) ? intval( $config['gallery-columns-tablet'] ) : 2;
		$gallery_columns_m    = isset( $config['gallery-columns-mobile'] ) ? intval( $config['gallery-columns-mobile'] ) : 1;
		$gallery_row_height   = isset( $config['gallery-row-height'] ) ? intval( $config['gallery-row-height'] ) : 200;
		$gallery_rows         = isset( $config['gallery-rows'] ) ? intval( $config['gallery-rows'] ) : 0;
		$gallery_scroll       = ! empty( $config['gallery-scroll'] );

		$attrs[] = 'data-mode="gallery"';

		// Canonical gallery-* attributes.
		$attrs[] = sprintf( 'data-gallery-layout="%s"', esc_attr( $gallery_layout ) );
		$attrs[] = sprintf( 'data-gallery-sizing-model="%s"', esc_attr( $gallery_sizing_model ) );
		$attrs[] = sprintf( 'data-gallery-columns="%d"', $gallery_columns );
		$attrs[] = sprintf( 'data-gallery-columns-tablet="%d"', $gallery_columns_t );
		$attrs[] = sprintf( 'data-gallery-columns-mobile="%d"', $gallery_columns_m );
		$attrs[] = sprintf( 'data-gallery-row-height="%d"', $gallery_row_height );
		$attrs[] = sprintf( 'data-gallery-rows="%d"', $gallery_rows );
		$attrs[] = sprintf( 'data-gallery-scroll="%s"', $gallery_scroll ? 'true' : 'false' );

		if ( ! empty( $config['width-explicit'] ) && isset( $config['width'] ) && $config['width'] !== 'auto' ) {
			$attrs[] = sprintf( 'data-gallery-explicit-width="%d"', intval( $config['width'] ) );
		}
		if ( ! empty( $config['height-explicit'] ) && isset( $config['height'] ) && $config['height'] !== 'auto' ) {
			$attrs[] = sprintf( 'data-gallery-explicit-height="%d"', intval( $config['height'] ) );
		}

		if ( isset( $config['slideshow'] ) ) {
			$attrs[] = sprintf( 'data-slideshow="%s"', $config['slideshow'] ? 'true' : 'false' );
		}

		if ( isset( $config['slideshow-delay'] ) ) {
			$attrs[] = sprintf( 'data-slideshow-delay="%s"', esc_attr( $config['slideshow-delay'] ) );
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

		// Fullscreen slideshow settings (gallery click opens fullscreen slideshow)
		$slideshow_booleans = array(
			'full-screen-slideshow' => 'data-full-screen-slideshow',
			'show-title'           => 'data-show-title',
			'show-counter'         => 'data-show-counter',
			'show-link-button'     => 'data-show-link-button',
			'show-download-button' => 'data-show-download-button',
			'video-autohide-controls' => 'data-video-autohide-controls',
		);
		foreach ( $slideshow_booleans as $key => $attr_name ) {
			if ( isset( $config[ $key ] ) ) {
				$attrs[] = sprintf( '%s="%s"', $attr_name, $config[ $key ] ? 'true' : 'false' );
			}
		}

		if ( isset( $config['full-screen-slideshow-delay'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-slideshow-delay="%s"', esc_attr( $config['full-screen-slideshow-delay'] ) );
		}

		if ( isset( $config['slideshow-inactivity-timeout'] ) ) {
			$attrs[] = sprintf( 'data-slideshow-inactivity-timeout="%s"', esc_attr( $config['slideshow-inactivity-timeout'] ) );
		}

		if ( ! empty( $config['full-screen-toggle'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-toggle="%s"', esc_attr( $config['full-screen-toggle'] ) );
		}

		if ( ! empty( $config['image-fit'] ) ) {
			$attrs[] = sprintf( 'data-image-fit="%s"', esc_attr( $config['image-fit'] ) );
		}

		if ( ! empty( $config['full-screen-image-fit'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-image-fit="%s"', esc_attr( $config['full-screen-image-fit'] ) );
		}

		if ( ! empty( $config['background-color'] ) ) {
			$attrs[] = sprintf( 'data-background-color="%s"', esc_attr( $config['background-color'] ) );
		}

		// Gallery mode should keep responsive sizing unless width/height
		// were explicitly provided in shortcode.
		$styles = array();
		if ( ! empty( $config['background-color'] ) ) {
			$styles[] = '--gallery-bg-color: ' . esc_attr( $config['background-color'] );
		}
		if ( ! empty( $config['video-controls-color'] ) ) {
			$styles[] = '--jzsa-video-controls-color: ' . esc_attr( $config['video-controls-color'] );
		}
		if ( ! empty( $config['width-explicit'] ) && isset( $config['width'] ) && $config['width'] !== 'auto' ) {
			$styles[] = 'width: ' . intval( $config['width'] ) . 'px';
		}
		if ( ! empty( $config['height-explicit'] ) && isset( $config['height'] ) && $config['height'] !== 'auto' ) {
			$styles[] = 'height: ' . intval( $config['height'] ) . 'px';
		}
		$style_attr = ! empty( $styles ) ? sprintf( ' style="%s"', esc_attr( implode( '; ', $styles ) ) ) : '';

		return sprintf(
			'<div id="%s" class="jzsa-album jzsa-gallery-album jzsa-loader-pending jzsa-gallery-loading jzsa-content-intro" %s%s></div>',
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
		static $counter = 0;
		return 'jzsa-gallery-' . ++$counter;
	}
}
