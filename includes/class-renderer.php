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

		// Build gallery container
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
			esc_html__( 'Warning:', 'janzeman-shared-albums-for-google-photos' ),
			esc_html__( 'Short Link Detected: The short link format might stop working in the future.', 'janzeman-shared-albums-for-google-photos' ),
			esc_html__( 'Please update to use the full link format (https://photos.google.com/share/...) to ensure your gallery continues working.', 'janzeman-shared-albums-for-google-photos' )
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
			'<div id="%s" class="jzsa-album swiper" %s style="%s">',
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
		$html .= '<div class="swiper-autoplay-progress"><div class="swiper-autoplay-progress-bar"></div></div>';

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

		$html .= '<div class="swiper-button-fullscreen"></div>';
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

		if ( ! empty( $config['background-color'] ) && $config['background-color'] !== 'transparent' ) {
			$styles[] = '--gallery-bg-color: ' . esc_attr( $config['background-color'] );
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
			'autoplay'               => 'data-autoplay',
			'start-at-random-photo'  => 'data-start-at-random-photo',
			'full-screen-autoplay'   => 'data-full-screen-autoplay',
			'crop-to-fill'           => 'data-crop-to-fill',
			'media-items-stretch'    => 'data-media-items-stretch',
			'show-title'             => 'data-show-title',
			'show-counter'           => 'data-show-counter',
			'show-link-button'       => 'data-show-link-button',
			'show-download-button'   => 'data-show-download-button',
		);

		foreach ( $boolean_attrs as $key => $attr_name ) {
			if ( isset( $config[ $key ] ) ) {
				$attrs[] = sprintf( '%s="%s"', $attr_name, $config[ $key ] ? 'true' : 'false' );
			}
		}

		// Numeric/string attributes
		if ( isset( $config['autoplay-delay'] ) ) {
			$attrs[] = sprintf( 'data-autoplay-delay="%s"', esc_attr( $config['autoplay-delay'] ) );
		}

		if ( isset( $config['full-screen-autoplay-delay'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-autoplay-delay="%s"', esc_attr( $config['full-screen-autoplay-delay'] ) );
		}

		if ( isset( $config['autoplay-inactivity-timeout'] ) ) {
			$attrs[] = sprintf( 'data-autoplay-inactivity-timeout="%s"', esc_attr( $config['autoplay-inactivity-timeout'] ) );
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

		if ( ! empty( $config['full-screen-switch'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-switch="%s"', esc_attr( $config['full-screen-switch'] ) );
		}

		if ( ! empty( $config['full-screen-navigation'] ) ) {
			$attrs[] = sprintf( 'data-full-screen-navigation="%s"', esc_attr( $config['full-screen-navigation'] ) );
		}

		return implode( ' ', $attrs );
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
