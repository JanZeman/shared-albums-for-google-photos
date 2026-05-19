<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use JZSA_Shared_Albums;

/**
 * Tests for shortcode attribute normalization in JZSA_Shared_Albums.
 */
class OrchestratorConfigTest extends TestCase {

    private JZSA_Shared_Albums $orchestrator;
    private ReflectionClass $reflection;

    protected function setUp(): void {
        $this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
        $this->reflection   = new ReflectionClass( $this->orchestrator );
    }

    private function config( array $atts ): array {
        return $this->reflection
            ->getMethod( 'parse_shortcode_config' )
            ->invoke( $this->orchestrator, $atts, 'https://photos.google.com/share/AF1QipTest' );
    }

    public function test_defaults_are_stable_for_gallery_mode(): void {
        $config = $this->config( array() );

        $this->assertSame( 'gallery', $config['mode'] );
        $this->assertSame( 400, $config['width'] );
        $this->assertSame( 300, $config['height'] );
        $this->assertFalse( $config['width-explicit'] );
        $this->assertFalse( $config['height-explicit'] );
        $this->assertSame( 800, $config['source-width'] );
        $this->assertSame( 600, $config['source-height'] );
        $this->assertSame( 'button-only', $config['fullscreen-toggle'] );
        $this->assertSame( 'disabled', $config['lightbox-toggle'] );
        $this->assertSame( '', $config['info-bottom'] );
        $this->assertSame( '{page} / {pages}', $config['gallery-info-bottom'] );
        $this->assertSame( 300, $config['limit'] );
        $this->assertSame( 128, $config['download-size-warning'] );
    }

    public function test_slider_mode_keeps_legacy_counter_info_bottom_default(): void {
        $config = $this->config( array( 'mode' => 'slider' ) );

        $this->assertSame( 'slider', $config['mode'] );
        $this->assertSame( '{item} / {items}', $config['info-bottom'] );
        $this->assertSame( '{item} / {items}', $config['fullscreen-info-bottom'] );
    }

    public function test_legacy_show_title_and_counter_build_bottom_info(): void {
        $config = $this->config(
            array(
                'mode'         => 'slider',
                'show-title'   => 'true',
                'show-counter' => 'true',
            )
        );

        $this->assertSame( '{album-title}: {item} / {items}', $config['info-bottom'] );
    }

    public function test_gallery_legacy_title_counter_controls_page_bottom_not_item_bottom(): void {
        $config = $this->config(
            array(
                'mode'         => 'gallery',
                'show-title'   => 'true',
                'show-counter' => 'true',
            )
        );

        $this->assertSame( '', $config['info-bottom'] );
        $this->assertSame( '{album-title}: {page} / {pages}', $config['gallery-info-bottom'] );
    }

    public function test_info_box_aliases_and_fullscreen_inheritance(): void {
        $config = $this->config(
            array(
                'info-top-1'       => 'Top {item}',
                'info-top-2'       => 'Secondary {items}',
                'fullscreen-info-top-2' => 'Fullscreen secondary',
            )
        );

        $this->assertSame( 'Top {item}', $config['info-top'] );
        $this->assertSame( 'Top {item}', $config['fullscreen-info-top'] );
        $this->assertSame( 'Secondary {items}', $config['info-top-secondary'] );
        $this->assertSame( 'Fullscreen secondary', $config['fullscreen-info-top-secondary'] );
    }

    public function test_lightbox_true_alias_disables_default_fullscreen(): void {
        $config = $this->config( array( 'lightbox-toggle' => 'yes' ) );

        $this->assertSame( 'click', $config['lightbox-toggle'] );
        $this->assertSame( 'disabled', $config['fullscreen-toggle'] );
    }

    public function test_lightbox_false_alias_keeps_default_fullscreen(): void {
        $config = $this->config( array( 'lightbox-toggle' => 'no' ) );

        $this->assertSame( 'disabled', $config['lightbox-toggle'] );
        $this->assertSame( 'button-only', $config['fullscreen-toggle'] );
    }

    public function test_paired_fullscreen_lightbox_display_options_inherit_both_directions(): void {
        $from_fullscreen = $this->config(
            array(
                'fullscreen-show-link-button'     => 'true',
                'fullscreen-show-download-button' => 'true',
                'fullscreen-controls-color'       => '#112233',
                'fullscreen-video-controls-color' => '#445566',
                'fullscreen-video-controls-autohide' => 'true',
            )
        );

        $this->assertTrue( $from_fullscreen['lightbox-show-link-button'] );
        $this->assertTrue( $from_fullscreen['lightbox-show-download-button'] );
        $this->assertSame( '#112233', $from_fullscreen['lightbox-controls-color'] );
        $this->assertSame( '#445566', $from_fullscreen['lightbox-video-controls-color'] );
        $this->assertTrue( $from_fullscreen['lightbox-video-controls-autohide'] );

        $from_lightbox = $this->config(
            array(
                'lightbox-show-navigation' => 'false',
                'lightbox-controls-color'  => '#abcdef',
            )
        );

        $this->assertFalse( $from_lightbox['fullscreen-show-navigation'] );
        $this->assertSame( '#abcdef', $from_lightbox['fullscreen-controls-color'] );
    }

    public function test_paired_values_keep_primary_when_both_are_set(): void {
        $config = $this->config(
            array(
                'fullscreen-controls-color' => '#111111',
                'lightbox-controls-color'   => '#222222',
            )
        );

        $this->assertSame( '#111111', $config['fullscreen-controls-color'] );
        $this->assertSame( '#222222', $config['lightbox-controls-color'] );
    }

    public function test_lightbox_fullscreen_slideshow_pairing_uses_dedicated_defaults(): void {
        $config = $this->config(
            array(
                'slideshow'          => 'auto',
                'lightbox-slideshow' => 'manual',
            )
        );

        $this->assertSame( 'auto', $config['slideshow'] );
        $this->assertSame( 'manual', $config['lightbox-slideshow'] );
        $this->assertSame( 'manual', $config['fullscreen-slideshow'] );
    }

    public function test_source_dimensions_reject_invalid_values(): void {
        $config = $this->config(
            array(
                'source-width'              => '-10',
                'source-height'             => '0',
                'fullscreen-source-width'   => '-1',
                'fullscreen-source-height'  => 'abc',
                'lightbox-source-width'     => '1200',
                'lightbox-source-height'    => '900',
            )
        );

        $this->assertSame( 800, $config['source-width'] );
        $this->assertSame( 600, $config['source-height'] );
        $this->assertSame( 1920, $config['fullscreen-source-width'] );
        $this->assertSame( 1440, $config['fullscreen-source-height'] );
        $this->assertSame( 1200, $config['lightbox-source-width'] );
        $this->assertSame( 900, $config['lightbox-source-height'] );
    }

    public function test_limit_and_download_warning_are_clamped(): void {
        $high = $this->config(
            array(
                'limit'                 => '9999',
                'download-size-warning' => '9999',
            )
        );
        $invalid = $this->config(
            array(
                'limit'                 => '-1',
                'download-size-warning' => '-1',
            )
        );

        $this->assertSame( 300, $high['limit'] );
        $this->assertSame( 512, $high['download-size-warning'] );
        $this->assertSame( 300, $invalid['limit'] );
        $this->assertSame( 128, $invalid['download-size-warning'] );
    }

    public function test_gallery_values_fall_back_when_out_of_range_or_invalid(): void {
        $config = $this->config(
            array(
                'gallery-layout'            => 'masonry',
                'gallery-sizing'            => 'stretch',
                'gallery-columns'           => '99',
                'gallery-columns-tablet'    => '0',
                'gallery-columns-mobile'    => '-1',
                'gallery-row-height'        => '20',
                'gallery-rows'              => '9999',
                'gallery-gap'               => '999',
                'gallery-buttons-on-mobile' => 'sometimes',
            )
        );

        $this->assertSame( 'grid', $config['gallery-layout'] );
        $this->assertSame( 'ratio', $config['gallery-sizing'] );
        $this->assertSame( 3, $config['gallery-columns'] );
        $this->assertSame( 2, $config['gallery-columns-tablet'] );
        $this->assertSame( 1, $config['gallery-columns-mobile'] );
        $this->assertSame( 200, $config['gallery-row-height'] );
        $this->assertSame( 300, $config['gallery-rows'] );
        $this->assertSame( 4, $config['gallery-gap'] );
        $this->assertSame( 'on-interaction', $config['gallery-buttons-on-mobile'] );
    }

    public function test_mosaic_values_are_normalized_and_clamped(): void {
        $config = $this->config(
            array(
                'mosaic'                          => 'true',
                'mosaic-position'                 => 'diagonal',
                'mosaic-count'                    => '-5',
                'mosaic-gap'                      => '101',
                'mosaic-opacity'                  => '9',
                'fullscreen-mosaic-layout'        => 'stacked',
                'fullscreen-mosaic-opacity'       => '-2',
                'mosaic-corner-radius'            => '-10',
                'fullscreen-mosaic-corner-radius' => '12',
            )
        );

        $this->assertTrue( $config['mosaic'] );
        $this->assertSame( 'right', $config['mosaic-position'] );
        $this->assertSame( 0, $config['mosaic-count'] );
        $this->assertSame( 8, $config['mosaic-gap'] );
        $this->assertSame( 1.0, $config['mosaic-opacity'] );
        $this->assertSame( 'outer', $config['fullscreen-mosaic-layout'] );
        $this->assertSame( 0.0, $config['fullscreen-mosaic-opacity'] );
        $this->assertSame( 0, $config['mosaic-corner-radius'] );
        $this->assertSame( 12, $config['fullscreen-mosaic-corner-radius'] );
    }

    public function test_info_font_family_is_sanitized_for_css_custom_property(): void {
        $config = $this->config(
            array(
                'info-font-family' => '  "Open Sans" , Arial; color:red; <script> ',
            )
        );

        $this->assertSame( '"Open Sans", Arial colorred', $config['info-font-family'] );
    }

    public function test_optional_positive_display_bounds_ignore_zero_and_negative_values(): void {
        $config = $this->config(
            array(
                'fullscreen-display-max-width' => '0',
                'fullscreen-display-max-height' => '-100',
                'lightbox-max-width' => '900',
                'lightbox-max-height' => '0',
            )
        );

        $this->assertNull( $config['fullscreen-display-max-width'] );
        $this->assertNull( $config['fullscreen-display-max-height'] );
        $this->assertSame( 900, $config['lightbox-max-width'] );
        $this->assertNull( $config['lightbox-max-height'] );
    }
}
