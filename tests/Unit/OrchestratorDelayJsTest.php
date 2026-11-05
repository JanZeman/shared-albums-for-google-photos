<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Shared_Albums;

/**
 * Tests that the album scripts are kept out of WP Rocket's Delay JavaScript execution.
 */
class OrchestratorDelayJsTest extends TestCase {

	private const TAGS = array(
		'jquery'  => '<script id="jquery-core-js" src="https://example.com/wp-includes/js/jquery/jquery.min.js?ver=3.7.1"></script>',
		'migrate' => '<script id="jquery-migrate-js" src="https://example.com/wp-includes/js/jquery/jquery-migrate.min.js?ver=3.4.1"></script>',
		'swiper'  => '<script id="swiper-js-js" src="https://example.com/wp-content/plugins/{plugin_dir}/assets/vendor/swiper/swiper-bundle.min.js?ver=11.0.0"></script>',
		'extra'   => '<script id="jzsa-init-js-extra">var jzsaAjax = {"ajaxUrl":"https://example.com/wp-admin/admin-ajax.php"};</script>',
		'init'    => '<script data-minify="1" id="jzsa-init-js" src="https://example.com/wp-content/cache/min/1/wp-content/plugins/janzeman-shared-albums-for-google-photos/assets/js/swiper-init.js?ver=1"></script>',
		'other'   => '<script id="elementor-frontend-js" src="https://example.com/wp-content/plugins/elementor/assets/js/frontend.min.js?ver=4.3.6"></script>',
	);

	private function excluded_tags( array $patterns ): array {
		$excluded   = array();
		// The plugin folder name is the checkout folder here, not the installed slug.
		$plugin_dir = basename( dirname( JZSA_PLUGIN_FILE ) );
		foreach ( self::TAGS as $name => $tag ) {
			$tag = str_replace( '{plugin_dir}', $plugin_dir, $tag );
			foreach ( $patterns as $pattern ) {
				// Same matching as WP Rocket's DelayJS\HTML::replace_scripts().
				if ( preg_match( "#{$pattern}#i", $tag ) ) {
					$excluded[] = $name;
					break;
				}
			}
		}
		return $excluded;
	}

	public function test_pages_without_an_album_keep_the_full_delay(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );

		$this->assertSame( array( 'existing' ), $orchestrator->exclude_from_wp_rocket_delay_js( array( 'existing' ) ) );
	}

	public function test_album_pages_exclude_the_album_scripts_and_jquery(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$orchestrator->handle_shortcode( array( 'link' => 'https://photos.app.goo.gl/abc123' ) );

		$patterns = $orchestrator->exclude_from_wp_rocket_delay_js( array( 'existing' ) );

		$this->assertSame( 'existing', $patterns[0] );
		$this->assertSame(
			array( 'jquery', 'migrate', 'swiper', 'extra', 'init' ),
			$this->excluded_tags( $patterns )
		);
	}

	public function test_unexpected_filter_values_are_returned_unchanged(): void {
		$orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$orchestrator->handle_shortcode( array( 'link' => 'https://photos.app.goo.gl/abc123' ) );

		$this->assertNull( $orchestrator->exclude_from_wp_rocket_delay_js( null ) );
	}
}
