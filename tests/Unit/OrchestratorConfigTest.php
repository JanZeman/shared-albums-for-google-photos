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

    private function invoke( string $method, mixed ...$args ): mixed {
        return $this->reflection->getMethod( $method )->invoke( $this->orchestrator, ...$args );
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
		$this->assertSame( 'disabled', $config['fullscreen-toggle'] );
		$this->assertSame( 'button-only', $config['lightbox-toggle'] );
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

    public function test_lightbox_false_alias_does_not_affect_fullscreen(): void {
        $config = $this->config( array( 'lightbox-toggle' => 'no' ) );

        $this->assertSame( 'disabled', $config['lightbox-toggle'] );
        $this->assertSame( 'disabled', $config['fullscreen-toggle'] );
    }

    public function test_viewer_settings_configure_both_modes(): void {
        $config = $this->config(
            [
                'viewer'                         => 'lightbox, fullscreen',
                'viewer-max-width'               => '900',
                'viewer-max-height'              => '700',
                'viewer-source-width'            => '1600',
                'viewer-source-height'           => '1200',
                'viewer-image-fit'               => 'cover',
                'viewer-background-color'        => '#112233',
                'viewer-corner-radius'           => '14',
                'viewer-controls-color'          => '#445566',
                'viewer-slideshow'               => 'auto',
                'viewer-slideshow-delay'         => '7',
                'viewer-info-top'                => '{description}',
                'viewer-info-font-size'          => '18',
                'viewer-mosaic'                  => 'true',
                'viewer-mosaic-position'         => 'left',
                'viewer-mosaic-layout'           => 'overlay',
                'viewer-mosaic-count'            => '6',
                'viewer-mosaic-gap'              => '5',
                'viewer-mosaic-opacity'          => '0.5',
                'viewer-mosaic-background'       => '#778899',
                'viewer-mosaic-corner-radius'    => '9',
            ]
        );

        $this->assertSame( 'button-only', $config['lightbox-toggle'] );
        $this->assertSame( 'button-only', $config['fullscreen-toggle'] );
        $this->assertSame( 900, $config['lightbox-max-width'] );
        $this->assertSame( 900, $config['fullscreen-display-max-width'] );
        $this->assertSame( 700, $config['lightbox-max-height'] );
        $this->assertSame( 700, $config['fullscreen-display-max-height'] );
        $this->assertSame( 1600, $config['lightbox-source-width'] );
        $this->assertSame( 1600, $config['fullscreen-source-width'] );
        $this->assertSame( 'cover', $config['lightbox-image-fit'] );
        $this->assertSame( 'cover', $config['fullscreen-image-fit'] );
        $this->assertSame( '#112233', $config['lightbox-background-color'] );
        $this->assertSame( '#112233', $config['fullscreen-background-color'] );
        $this->assertSame( 14, $config['lightbox-corner-radius'] );
        $this->assertSame( 14, $config['fullscreen-corner-radius'] );
        $this->assertSame( '#445566', $config['lightbox-controls-color'] );
        $this->assertSame( '#445566', $config['fullscreen-controls-color'] );
        $this->assertSame( 'auto', $config['lightbox-slideshow'] );
        $this->assertSame( 'auto', $config['fullscreen-slideshow'] );
        $this->assertSame( '{description}', $config['lightbox-info-top'] );
        $this->assertSame( '{description}', $config['fullscreen-info-top'] );
        $this->assertSame( 18, $config['lightbox-info-font-size'] );
        $this->assertSame( 18, $config['fullscreen-info-font-size'] );
        $this->assertTrue( $config['lightbox-mosaic'] );
        $this->assertTrue( $config['fullscreen-mosaic'] );
        $this->assertSame( 'left', $config['lightbox-mosaic-position'] );
        $this->assertSame( 'left', $config['fullscreen-mosaic-position'] );
        $this->assertSame( 'overlay', $config['lightbox-mosaic-layout'] );
        $this->assertSame( 'overlay', $config['fullscreen-mosaic-layout'] );
        $this->assertSame( 6, $config['lightbox-mosaic-count'] );
        $this->assertSame( 6, $config['fullscreen-mosaic-count'] );
        $this->assertSame( 5, $config['lightbox-mosaic-gap'] );
        $this->assertSame( 5, $config['fullscreen-mosaic-gap'] );
        $this->assertSame( 0.5, $config['lightbox-mosaic-opacity'] );
        $this->assertSame( 0.5, $config['fullscreen-mosaic-opacity'] );
        $this->assertSame( '#778899', $config['lightbox-mosaic-background'] );
        $this->assertSame( '#778899', $config['fullscreen-mosaic-background'] );
        $this->assertSame( 9, $config['lightbox-mosaic-corner-radius'] );
        $this->assertSame( 9, $config['fullscreen-mosaic-corner-radius'] );
    }

    public function test_specific_settings_override_viewer_settings_per_mode(): void {
        $config = $this->config(
            [
                'viewer'                   => 'lightbox, fullscreen',
                'viewer-max-width'         => '900',
                'viewer-info-top'          => 'Shared',
                'viewer-mosaic-position'   => 'bottom',
                'lightbox-max-width'       => '700',
                'fullscreen-info-top'      => 'Fullscreen only',
                'lightbox-mosaic-position' => 'right',
            ]
        );

        $this->assertSame( 700, $config['lightbox-max-width'] );
        $this->assertSame( 900, $config['fullscreen-display-max-width'] );
        $this->assertSame( 'Shared', $config['lightbox-info-top'] );
        $this->assertSame( 'Fullscreen only', $config['fullscreen-info-top'] );
        $this->assertSame( 'right', $config['lightbox-mosaic-position'] );
        $this->assertSame( 'bottom', $config['fullscreen-mosaic-position'] );
    }

    public function test_viewer_and_specific_info_boxes_can_be_explicitly_hidden(): void {
        $shared_hidden = $this->config(
            [
                'mode'                 => 'slider',
                'viewer-info-bottom' => '',
            ]
        );
        $specific_hidden = $this->config(
            [
                'viewer-info-top'   => 'Shared',
                'lightbox-info-top' => '',
            ]
        );

        $this->assertSame( '', $shared_hidden['lightbox-info-bottom'] );
        $this->assertSame( '', $shared_hidden['fullscreen-info-bottom'] );
        $this->assertSame( '', $specific_hidden['lightbox-info-top'] );
        $this->assertSame( 'Shared', $specific_hidden['fullscreen-info-top'] );
    }

    public function test_lightbox_and_fullscreen_display_options_do_not_inherit_sideways(): void {
        $from_fullscreen = $this->config(
            array(
                'fullscreen-show-link-button'     => 'true',
                'fullscreen-show-download-button' => 'true',
                'fullscreen-controls-color'       => '#112233',
                'fullscreen-video-controls-color' => '#445566',
                'fullscreen-video-controls-autohide' => 'true',
            )
        );

        $this->assertTrue( $from_fullscreen['fullscreen-show-link-button'] );
        $this->assertTrue( $from_fullscreen['fullscreen-show-download-button'] );
        $this->assertSame( '#112233', $from_fullscreen['fullscreen-controls-color'] );
        $this->assertSame( '#445566', $from_fullscreen['fullscreen-video-controls-color'] );
        $this->assertTrue( $from_fullscreen['fullscreen-video-controls-autohide'] );
        $this->assertFalse( $from_fullscreen['lightbox-show-link-button'] );
        $this->assertFalse( $from_fullscreen['lightbox-show-download-button'] );
        $this->assertSame( '#ffffff', $from_fullscreen['lightbox-controls-color'] );
        $this->assertSame( '#00b2ff', $from_fullscreen['lightbox-video-controls-color'] );
        $this->assertFalse( $from_fullscreen['lightbox-video-controls-autohide'] );

        $from_lightbox = $this->config(
            array(
                'lightbox-show-navigation' => 'false',
                'lightbox-controls-color'  => '#abcdef',
            )
        );

        $this->assertFalse( $from_lightbox['lightbox-show-navigation'] );
        $this->assertSame( '#abcdef', $from_lightbox['lightbox-controls-color'] );
        $this->assertTrue( $from_lightbox['fullscreen-show-navigation'] );
        $this->assertSame( '#ffffff', $from_lightbox['fullscreen-controls-color'] );
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

    public function test_lightbox_and_fullscreen_slideshow_use_dedicated_defaults(): void {
        $config = $this->config(
            array(
                'slideshow'          => 'auto',
                'lightbox-slideshow' => 'manual',
            )
        );

        $this->assertSame( 'auto', $config['slideshow'] );
        $this->assertSame( 'manual', $config['lightbox-slideshow'] );
        $this->assertSame( 'disabled', $config['fullscreen-slideshow'] );
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

    public function test_parse_color_accepts_rgba_and_hsl_formats_and_rejects_invalid(): void {
        $rgba   = $this->invoke( 'parse_color', array( 'background-color' => 'rgba(0,0,0,0.5)' ), 'background-color', 'transparent' );
        $hsl    = $this->invoke( 'parse_color', array( 'background-color' => 'hsl(120,50%,50%)' ), 'background-color', 'transparent' );
        $hsla   = $this->invoke( 'parse_color', array( 'background-color' => 'hsla(0,0%,0%,0.7)' ), 'background-color', 'transparent' );
        $rgb    = $this->invoke( 'parse_color', array( 'background-color' => 'rgb(255,128,0)' ), 'background-color', 'transparent' );
        $invalid = $this->invoke( 'parse_color', array( 'background-color' => 'notacolor' ), 'background-color', 'transparent' );
        $missing = $this->invoke( 'parse_color', array(), 'background-color', 'transparent' );

        $this->assertSame( 'rgba(0,0,0,0.5)', $rgba );
        $this->assertSame( 'hsl(120,50%,50%)', $hsl );
        $this->assertSame( 'hsla(0,0%,0%,0.7)', $hsla );
        $this->assertSame( 'rgb(255,128,0)', $rgb );
        $this->assertSame( 'transparent', $invalid );
        $this->assertSame( 'transparent', $missing );
    }

    public function test_parse_delay_range_returns_fixed_value_or_random_in_range(): void {
        $fixed = $this->invoke( 'parse_delay_range', '3000' );
        $range = $this->invoke( 'parse_delay_range', '1000-5000' );

        $this->assertSame( 3000, $fixed );
        $this->assertGreaterThanOrEqual( 1000, $range );
        $this->assertLessThanOrEqual( 5000, $range );
    }

    public function test_mosaic_position_defaults_to_bottom_when_absent_and_valid_values_pass_through(): void {
        $default = $this->invoke( 'parse_mosaic_position', array() );
        $left    = $this->invoke( 'parse_mosaic_position', array( 'mosaic-position' => 'left' ) );
        $top     = $this->invoke( 'parse_mosaic_position', array( 'mosaic-position' => 'top' ) );

        $this->assertSame( 'bottom', $default );
        $this->assertSame( 'left', $left );
        $this->assertSame( 'top', $top );
    }

    public function test_mode_carousel_is_accepted_and_invalid_mode_falls_back_to_gallery(): void {
        $carousel = $this->config( array( 'mode' => 'carousel' ) );
        $invalid  = $this->config( array( 'mode' => 'fullscreen' ) );

        $this->assertSame( 'carousel', $carousel['mode'] );
        $this->assertSame( 'gallery', $invalid['mode'] );
    }

    public function test_slideshow_mode_enabled_alias_maps_to_manual(): void {
        $config = $this->config( array( 'slideshow' => 'enabled' ) );
        $this->assertSame( 'manual', $config['slideshow'] );
    }

    public function test_slideshow_mode_invalid_value_falls_back_to_disabled(): void {
        $config = $this->config( array( 'slideshow' => 'something' ) );
        $this->assertSame( 'disabled', $config['slideshow'] );
    }

    public function test_slideshow_autoresume_disabled_string_is_preserved(): void {
        $config = $this->config( array( 'slideshow-autoresume' => 'disabled' ) );
        $this->assertSame( 'disabled', $config['slideshow-autoresume'] );
    }

    public function test_slideshow_autoresume_numeric_string_is_preserved(): void {
        $config = $this->config( array( 'slideshow-autoresume' => '30' ) );
        $this->assertSame( '30', $config['slideshow-autoresume'] );
    }

    public function test_parse_start_at_accepts_random_and_numeric_and_falls_back(): void {
        $random  = $this->invoke( 'parse_start_at', array( 'start-at' => 'random' ) );
        $numeric = $this->invoke( 'parse_start_at', array( 'start-at' => '5' ) );
        $zero    = $this->invoke( 'parse_start_at', array( 'start-at' => '0' ) );
        $invalid = $this->invoke( 'parse_start_at', array( 'start-at' => 'abc' ) );
        $absent  = $this->invoke( 'parse_start_at', array() );

        $this->assertSame( 'random', $random );
        $this->assertSame( '5', $numeric );
        $this->assertSame( '1', $zero );
        $this->assertSame( '1', $invalid );
        $this->assertSame( '1', $absent );
    }

    public function test_parse_image_fit_accepts_cover_and_contain_and_falls_back(): void {
        $cover   = $this->invoke( 'parse_image_fit', array( 'image-fit' => 'cover' ) );
        $contain = $this->invoke( 'parse_image_fit', array( 'image-fit' => 'contain' ) );
        $invalid = $this->invoke( 'parse_image_fit', array( 'image-fit' => 'stretch' ) );
        $absent  = $this->invoke( 'parse_image_fit', array() );

        $this->assertSame( 'cover', $cover );
        $this->assertSame( 'contain', $contain );
        $this->assertSame( 'cover', $invalid );
        $this->assertSame( 'cover', $absent );
    }

    public function test_parse_mosaic_count_auto_keyword_and_positive_value(): void {
        $auto    = $this->invoke( 'parse_mosaic_count', array( 'mosaic-count' => 'auto' ) );
        $upper   = $this->invoke( 'parse_mosaic_count', array( 'mosaic-count' => 'AUTO' ) );
        $valid   = $this->invoke( 'parse_mosaic_count', array( 'mosaic-count' => '5' ) );
        $absent  = $this->invoke( 'parse_mosaic_count', array() );

        $this->assertSame( 0, $auto );
        $this->assertSame( 0, $upper );
        $this->assertSame( 5, $valid );
        $this->assertSame( 0, $absent );
    }

    public function test_parse_mosaic_gap_valid_in_range_and_zero_boundary(): void {
        $absent = $this->invoke( 'parse_mosaic_gap', array() );
        $zero   = $this->invoke( 'parse_mosaic_gap', array( 'mosaic-gap' => '0' ) );
        $mid    = $this->invoke( 'parse_mosaic_gap', array( 'mosaic-gap' => '20' ) );
        $max    = $this->invoke( 'parse_mosaic_gap', array( 'mosaic-gap' => '100' ) );

        $this->assertSame( 8, $absent );
        $this->assertSame( 0, $zero );
        $this->assertSame( 20, $mid );
        $this->assertSame( 100, $max );
    }

    public function test_parse_mosaic_opacity_valid_in_range_and_absent(): void {
        $absent = $this->invoke( 'parse_mosaic_opacity', array() );
        $mid    = $this->invoke( 'parse_mosaic_opacity', array( 'mosaic-opacity' => '0.5' ) );
        $zero   = $this->invoke( 'parse_mosaic_opacity', array( 'mosaic-opacity' => '0' ) );
        $one    = $this->invoke( 'parse_mosaic_opacity', array( 'mosaic-opacity' => '1' ) );

        $this->assertSame( 0.3, $absent );
        $this->assertSame( 0.5, $mid );
        $this->assertSame( 0.0, $zero );
        $this->assertSame( 1.0, $one );
    }

    public function test_parse_fullscreen_mosaic_layout_accepts_overlay_and_outer(): void {
        $overlay = $this->invoke( 'parse_fullscreen_mosaic_layout', array( 'fullscreen-mosaic-layout' => 'overlay' ) );
        $outer   = $this->invoke( 'parse_fullscreen_mosaic_layout', array( 'fullscreen-mosaic-layout' => 'outer' ) );
        $invalid = $this->invoke( 'parse_fullscreen_mosaic_layout', array( 'fullscreen-mosaic-layout' => 'stacked' ) );
        $absent  = $this->invoke( 'parse_fullscreen_mosaic_layout', array() );

        $this->assertSame( 'overlay', $overlay );
        $this->assertSame( 'outer', $outer );
        $this->assertSame( 'outer', $invalid );
        $this->assertSame( 'outer', $absent );
    }

    public function test_parse_gallery_gap_valid_in_range_and_zero_boundary(): void {
        $absent = $this->invoke( 'parse_gallery_gap', array() );
        $zero   = $this->invoke( 'parse_gallery_gap', array( 'gallery-gap' => '0' ) );
        $mid    = $this->invoke( 'parse_gallery_gap', array( 'gallery-gap' => '20' ) );
        $max    = $this->invoke( 'parse_gallery_gap', array( 'gallery-gap' => '100' ) );

        $this->assertSame( 4, $absent );
        $this->assertSame( 0, $zero );
        $this->assertSame( 20, $mid );
        $this->assertSame( 100, $max );
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

    public function test_fullscreen_max_alias_sets_display_bounds(): void {
        $config = $this->config(
            array(
                'fullscreen-max-width' => '1200',
                'fullscreen-max-height' => '800',
            )
        );

        $this->assertSame( 1200, $config['fullscreen-display-max-width'] );
        $this->assertSame( 800, $config['fullscreen-display-max-height'] );
    }

    public function test_fullscreen_max_alias_overrides_legacy_display_bounds(): void {
        $config = $this->config(
            array(
                'fullscreen-max-width' => '1200',
                'fullscreen-display-max-width' => '900',
            )
        );

        $this->assertSame( 1200, $config['fullscreen-display-max-width'] );
    }

    public function test_parse_dimension_accepts_auto_keyword(): void {
        $auto   = $this->invoke( 'parse_dimension', array( 'width' => 'auto' ), 'width', 400 );
        $upper  = $this->invoke( 'parse_dimension', array( 'width' => 'AUTO' ), 'width', 400 );
        $valid  = $this->invoke( 'parse_dimension', array( 'width' => '500' ), 'width', 400 );
        $absent = $this->invoke( 'parse_dimension', array(), 'width', 400 );

        $this->assertSame( 'auto', $auto );
        $this->assertSame( 'auto', $upper );
        $this->assertSame( 500, $valid );
        $this->assertSame( 400, $absent );
    }

    public function test_parse_cache_refresh_legacy_key_and_invalid_value_fall_back(): void {
        $legacy  = $this->invoke( 'parse_cache_refresh', array( 'cache-refresh' => '72' ) );
        $zero    = $this->invoke( 'parse_cache_refresh', array( 'album-cache-refresh' => '0' ) );
        $neg     = $this->invoke( 'parse_cache_refresh', array( 'cache-refresh' => '-5' ) );
        $absent  = $this->invoke( 'parse_cache_refresh', array() );
        $primary = $this->invoke( 'parse_cache_refresh', array( 'album-cache-refresh' => '48', 'cache-refresh' => '1' ) );

        $this->assertSame( 72, $legacy );
        $this->assertSame( 168, $zero );
        $this->assertSame( 168, $neg );
        $this->assertSame( 168, $absent );
        $this->assertSame( 48, $primary );
    }

    public function test_parse_lightbox_toggle_mode_aliases_and_valid_modes(): void {
        $true_alias   = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => 'true' ) );
        $on_alias     = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => 'on' ) );
        $one_alias    = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => '1' ) );
        $false_alias  = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => 'false' ) );
        $off_alias    = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => 'off' ) );
        $zero_alias   = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => '0' ) );
        $btn_only     = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => 'button-only' ) );
        $dbl_click    = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => 'double-click' ) );
        $invalid      = $this->invoke( 'parse_lightbox_toggle_mode', array( 'lightbox-toggle' => 'hover' ) );

        $this->assertSame( 'click', $true_alias );
        $this->assertSame( 'click', $on_alias );
        $this->assertSame( 'click', $one_alias );
        $this->assertSame( 'disabled', $false_alias );
        $this->assertSame( 'disabled', $off_alias );
        $this->assertSame( 'disabled', $zero_alias );
        $this->assertSame( 'button-only', $btn_only );
        $this->assertSame( 'double-click', $dbl_click );
        $this->assertSame( 'disabled', $invalid );
    }

    public function test_parse_fullscreen_toggle_mode_explicit_values_and_invalid_fallback(): void {
        $click      = $this->invoke( 'parse_fullscreen_toggle_mode', array( 'fullscreen-toggle' => 'click' ) );
        $dbl        = $this->invoke( 'parse_fullscreen_toggle_mode', array( 'fullscreen-toggle' => 'double-click' ) );
        $disabled   = $this->invoke( 'parse_fullscreen_toggle_mode', array( 'fullscreen-toggle' => 'disabled' ) );
        $invalid    = $this->invoke( 'parse_fullscreen_toggle_mode', array( 'fullscreen-toggle' => 'hover' ) );

        $this->assertSame( 'click', $click );
        $this->assertSame( 'double-click', $dbl );
        $this->assertSame( 'disabled', $disabled );
        $this->assertSame( 'button-only', $invalid );
    }

    public function test_parse_gallery_buttons_on_mobile_accepts_always(): void {
        $always  = $this->invoke( 'parse_gallery_buttons_on_mobile', array( 'gallery-buttons-on-mobile' => 'always' ) );
        $default = $this->invoke( 'parse_gallery_buttons_on_mobile', array() );
        $invalid = $this->invoke( 'parse_gallery_buttons_on_mobile', array( 'gallery-buttons-on-mobile' => 'sometimes' ) );

        $this->assertSame( 'always', $always );
        $this->assertSame( 'on-interaction', $default );
        $this->assertSame( 'on-interaction', $invalid );
    }

    public function test_parse_slideshow_autoresume_absent_and_invalid_fall_back_to_default(): void {
        $absent  = $this->invoke( 'parse_slideshow_autoresume', array(), array( 'slideshow-autoresume' ) );
        $invalid = $this->invoke( 'parse_slideshow_autoresume', array( 'slideshow-autoresume' => '0' ), array( 'slideshow-autoresume' ) );
        $neg     = $this->invoke( 'parse_slideshow_autoresume', array( 'slideshow-autoresume' => '-5' ), array( 'slideshow-autoresume' ) );
        $valid   = $this->invoke( 'parse_slideshow_autoresume', array( 'slideshow-autoresume' => '60' ), array( 'slideshow-autoresume' ) );

        $this->assertSame( '30', $absent );
        $this->assertSame( '30', $invalid );
        $this->assertSame( '30', $neg );
        $this->assertSame( '60', $valid );
    }

    public function test_image_fit_parsers_only_read_their_own_key(): void {
        // Each parser reads only its own key. No sideways inheritance between modes.
        $fs_cover   = $this->invoke( 'parse_fullscreen_image_fit', array( 'fullscreen-image-fit' => 'cover' ) );
        $fs_no_side = $this->invoke( 'parse_fullscreen_image_fit', array( 'lightbox-image-fit' => 'cover' ) );
        $fs_invalid = $this->invoke( 'parse_fullscreen_image_fit', array( 'fullscreen-image-fit' => 'stretch' ) );
        $fs_absent  = $this->invoke( 'parse_fullscreen_image_fit', array() );

        $lb_cover   = $this->invoke( 'parse_lightbox_image_fit', array( 'lightbox-image-fit' => 'cover' ) );
        $lb_no_side = $this->invoke( 'parse_lightbox_image_fit', array( 'fullscreen-image-fit' => 'cover' ) );
        $lb_invalid = $this->invoke( 'parse_lightbox_image_fit', array( 'lightbox-image-fit' => 'stretch' ) );
        $lb_absent  = $this->invoke( 'parse_lightbox_image_fit', array() );

        $this->assertSame( 'cover', $fs_cover );
        $this->assertSame( 'contain', $fs_no_side, 'lightbox-image-fit must not bleed into fullscreen' );
        $this->assertSame( 'contain', $fs_invalid );
        $this->assertSame( 'contain', $fs_absent );

        $this->assertSame( 'cover', $lb_cover );
        $this->assertSame( 'contain', $lb_no_side, 'fullscreen-image-fit must not bleed into lightbox' );
        $this->assertSame( 'contain', $lb_invalid );
        $this->assertSame( 'contain', $lb_absent );
    }

    public function test_fullscreen_image_fit_cover_does_not_affect_lightbox(): void {
        $config = $this->config( array( 'fullscreen-image-fit' => 'cover' ) );

        $this->assertSame( 'cover', $config['fullscreen-image-fit'] );
        $this->assertSame( 'contain', $config['lightbox-image-fit'], 'lightbox must not inherit fullscreen-image-fit' );
    }

    public function test_lightbox_image_fit_cover_does_not_affect_fullscreen(): void {
        $config = $this->config( array( 'lightbox-image-fit' => 'cover' ) );

        $this->assertSame( 'cover', $config['lightbox-image-fit'] );
        $this->assertSame( 'contain', $config['fullscreen-image-fit'], 'fullscreen must not inherit lightbox-image-fit' );
    }

    public function test_viewer_image_fit_propagates_and_mode_override_stays_isolated(): void {
        // viewer-image-fit sets the shared baseline; a concrete mode key overrides only that mode.
        $config = $this->config( array(
            'viewer-image-fit'    => 'cover',
            'fullscreen-image-fit' => 'contain',
        ) );

        $this->assertSame( 'contain', $config['fullscreen-image-fit'], 'fullscreen-image-fit overrides viewer baseline' );
        $this->assertSame( 'cover', $config['lightbox-image-fit'], 'lightbox must not see the fullscreen override' );
    }

    public function test_parse_optional_bool_returns_null_when_absent_and_correct_bool_when_present(): void {
        $absent = $this->invoke( 'parse_optional_bool', array(), 'show-navigation' );
        $true   = $this->invoke( 'parse_optional_bool', array( 'show-navigation' => 'true' ), 'show-navigation' );
        $false  = $this->invoke( 'parse_optional_bool', array( 'show-navigation' => 'false' ), 'show-navigation' );

        $this->assertNull( $absent );
        $this->assertTrue( $true );
        $this->assertFalse( $false );
    }
}
