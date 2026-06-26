<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use JZSA_Shared_Albums;
use JZSA_Renderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Characterizes the complete legacy shortcode configuration surface.
 *
 * The fingerprints were generated from the committed parser immediately before
 * expanded-* support was introduced. Every legacy key must retain its exact
 * value. New keys may be added without weakening this comparison.
 */
class LegacyExpandedCompatibilityTest extends TestCase {

	private const ALBUM_URL = 'https://photos.google.com/share/AF1QipLegacy';

	private JZSA_Shared_Albums $orchestrator;
	private ReflectionClass $reflection;

	protected function setUp(): void {
		$this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );
		$this->reflection   = new ReflectionClass( $this->orchestrator );
	}

	#[DataProvider( 'legacyCases' )]
	public function test_legacy_configuration_matches_pre_expanded_parser(
		string $case_name,
		array $atts,
		string $expected_hash,
		array $legacy_keys,
		string $expected_render_hash
	): void {
		foreach ( array_keys( $atts ) as $key ) {
			$this->assertFalse(
				str_starts_with( $key, 'expanded-' ),
				$case_name . ' must exercise the legacy parsing path'
			);
		}

		$config = $this->reflection
			->getMethod( 'parse_shortcode_config' )
			->invoke( $this->orchestrator, $atts, self::ALBUM_URL );

		$legacy_projection = array();
		foreach ( $legacy_keys as $key ) {
			$this->assertArrayHasKey( $key, $config, $case_name . ' lost legacy key ' . $key );
			$legacy_projection[ $key ] = $config[ $key ];
		}

		$this->assertSame(
			$expected_hash,
			self::fingerprint( $legacy_projection ),
			$case_name . ' changed a legacy configuration value'
		);

		$rendered = ( new JZSA_Renderer() )->render( $config );
		$this->assertSame(
			$expected_render_hash,
			hash( 'sha256', self::legacyRenderedProjection( $rendered ) ),
			$case_name . ' changed legacy rendered markup'
		);
	}

	public function test_every_production_sample_avoids_legacy_toggle_attributes(): void {
		$sample_cases = self::productionSampleCases();

		$this->assertCount( 54, $sample_cases );

		foreach ( $sample_cases as $case_name => $atts ) {
			$this->assertArrayNotHasKey(
				'lightbox-toggle',
				$atts,
				$case_name . ' should use expanded-toggle instead of lightbox-toggle'
			);
			$this->assertArrayNotHasKey(
				'fullscreen-toggle',
				$atts,
				$case_name . ' should use expanded-toggle instead of fullscreen-toggle'
			);
		}
	}

	public static function legacyCases(): iterable {
		$fixture = self::fixture();
		$cases   = array_merge( self::productionSampleCases(), self::namedLegacyCases() );

		foreach ( $fixture['hashes'] as $name => $hash ) {
			if ( ! array_key_exists( $name, $cases ) ) {
				throw new \RuntimeException( 'Missing legacy compatibility case: ' . $name );
			}

			yield $name => array(
				$name,
				$cases[ $name ],
				$hash,
				$fixture['legacy-keys'],
				$fixture['render-hashes'][ $name ],
			);
		}
	}

	private static function fixture(): array {
		return require dirname( __DIR__ ) . '/fixtures/legacy-expanded-compatibility.php';
	}

	private static function productionSampleCases(): array {
		$source = file_get_contents( JZSA_PLUGIN_DIR . 'includes/class-admin-pages.php' );
		if ( false === $source ) {
			throw new \RuntimeException( 'Unable to read production samples.' );
		}

		preg_match_all(
			'/\$(album_sample_link|video_sample_link|info_sample_link)\s*=\s*\'([^\']*)\'/',
			$source,
			$link_matches,
			PREG_SET_ORDER
		);
		$variables = array();
		foreach ( $link_matches as $match ) {
			$variables[ $match[1] ] = $match[2];
		}

		$start = strpos( $source, 'Sample 1:' );
		$end   = strpos( $source, '<!-- Start Tutorial -->', $start );
		if ( false === $start || false === $end ) {
			throw new \RuntimeException( 'Unable to locate production sample section.' );
		}
		$sample_source = substr( $source, $start, $end - $start );

		preg_match_all(
			'/Sample\s+(\d+):([\s\S]*?)(?=Sample\s+\d+:|<!-- Start Tutorial -->|$)/',
			$sample_source,
			$sample_matches,
			PREG_SET_ORDER
		);

		$cases = array();
		foreach ( $sample_matches as $sample_match ) {
			preg_match_all(
				'/\$(?:sample_shortcode|low_res_shortcode|high_res_shortcode)\s*=\s*(.+?);/',
				$sample_match[2],
				$assignment_matches,
				PREG_SET_ORDER
			);

			foreach ( $assignment_matches as $index => $assignment_match ) {
				$name = 'sample-' . $sample_match[1];
				if ( $index > 0 ) {
					$name .= '-' . ( $index + 1 );
				}
				$shortcode      = self::expandShortcodeExpression( $assignment_match[1], $variables );
				$cases[ $name ] = self::parseShortcodeAttributes( $shortcode );
			}
		}

		return $cases;
	}

	private static function expandShortcodeExpression( string $expression, array $variables ): string {
		preg_match_all(
			'/\'((?:\\\\\'|[^\'])*)\'|\$(\w+)/',
			$expression,
			$tokens,
			PREG_SET_ORDER
		);

		$shortcode = '';
		foreach ( $tokens as $token ) {
			if ( isset( $token[1] ) && '' !== $token[1] ) {
				$shortcode .= str_replace( array( '\\\\', '\\\'' ), array( '\\', '\'' ), $token[1] );
				continue;
			}

			$variable = $token[2] ?? '';
			if ( ! array_key_exists( $variable, $variables ) ) {
				throw new \RuntimeException( 'Unknown sample shortcode variable: ' . $variable );
			}
			$shortcode .= $variables[ $variable ];
		}

		return $shortcode;
	}

	private static function parseShortcodeAttributes( string $shortcode ): array {
		preg_match_all(
			'/([a-z][a-z0-9-]*)="([^"]*)"/i',
			$shortcode,
			$matches,
			PREG_SET_ORDER
		);

		$atts = array();
		foreach ( $matches as $match ) {
			if ( 'link' !== $match[1] ) {
				$atts[ $match[1] ] = $match[2];
			}
		}

		return $atts;
	}

	private static function namedLegacyCases(): array {
		$naveen = 'lightbox-toggle="click" lightbox-slideshow="auto" gallery-scrollable="true" gallery-gap="20" gallery-rows="3" gallery-sizing="fill" gallery-layout="grid" gallery-columns="3" gallery-info-bottom="" show-link-button="false" show-download-button="false" info-bottom="{item} / {items}" show-videos="false" album-cache-refresh="24" fullscreen-toggle="click" fullscreen-info-top="{item} / {items}" info-wrap="true" fullscreen-info-top-secondary="{description}" fullscreen-info-bottom="{date} | {aperture} | {shutter} | {iso} | {camera}" fullscreen-slideshow="auto" fullscreen-slideshow-delay="7" fullscreen-display-max-width="800" fullscreen-display-max-height="600" fullscreen-source-width="800" fullscreen-source-height="600"';

		return array(
			'default' => array(),
			'naveen-original' => self::parseShortcodeAttributes( $naveen ),
			'legacy-lightbox-only' => array(
				'mode'                      => 'slider',
				'lightbox-toggle'           => 'click',
				'fullscreen-toggle'         => 'disabled',
				'lightbox-max-width'        => '900',
				'lightbox-max-height'       => '650',
				'lightbox-background-color' => 'rgba(0,0,0,0.8)',
				'lightbox-slideshow'        => 'auto',
				'lightbox-slideshow-delay'  => '4',
			),
			'legacy-fullscreen-only' => array(
				'mode'                          => 'slider',
				'lightbox-toggle'               => 'disabled',
				'fullscreen-toggle'             => 'double-click',
				'fullscreen-display-max-width'  => '1200',
				'fullscreen-display-max-height' => '800',
				'fullscreen-source-width'       => '1920',
				'fullscreen-source-height'      => '1080',
				'fullscreen-image-fit'          => 'cover',
				'fullscreen-slideshow'          => 'manual',
				'fullscreen-slideshow-delay'    => '8',
			),
			'legacy-combined' => array(
				'mode'                         => 'slider',
				'lightbox-toggle'              => 'button-only',
				'fullscreen-toggle'            => 'button-only',
				'lightbox-max-width'           => '1000',
				'fullscreen-display-max-width' => '1400',
				'fullscreen-controls-color'    => '#123456',
				'lightbox-controls-color'      => '#abcdef',
			),
			'legacy-info-aliases' => array(
				'mode'                      => 'slider',
				'show-title'                => 'true',
				'show-counter'              => 'true',
				'info-top-1'                => 'Top',
				'info-top-2'                => 'Secondary',
				'fullscreen-show-title'     => 'false',
				'fullscreen-show-counter'   => 'true',
				'fullscreen-info-top-1'     => 'Fullscreen top',
				'fullscreen-info-top-2'     => 'Fullscreen secondary',
			),
			'legacy-mosaic' => array(
				'mode'                            => 'slider',
				'mosaic'                          => 'true',
				'mosaic-position'                 => 'left',
				'mosaic-count'                    => '9',
				'mosaic-gap'                      => '7',
				'mosaic-opacity'                  => '0.4',
				'mosaic-background'               => '#111111',
				'mosaic-corner-radius'            => '13',
				'fullscreen-mosaic'               => 'true',
				'fullscreen-mosaic-position'      => 'bottom',
				'fullscreen-mosaic-layout'        => 'overlay',
				'fullscreen-mosaic-count'         => '15',
				'fullscreen-mosaic-gap'           => '5',
				'fullscreen-mosaic-opacity'       => '0.6',
				'fullscreen-mosaic-background'    => '#222222',
				'fullscreen-mosaic-corner-radius' => '17',
			),
		);
	}

	private static function fingerprint( array $value ): string {
		$value = self::sortRecursively( $value );

		return hash(
			'sha256',
			json_encode(
				$value,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			)
		);
	}

	private static function legacyRenderedProjection( string $html ): string {
		$html = preg_replace( '/jzsa-(album|gallery)-\d+/', 'jzsa-$1-ID', $html );

		$new_attributes = array(
			'lightbox-info-bottom',
			'lightbox-info-top',
			'lightbox-info-top-secondary',
			'lightbox-info-font-size',
			'lightbox-info-font-family',
			'lightbox-info-font-color',
			'lightbox-mosaic',
			'lightbox-mosaic-position',
			'lightbox-mosaic-layout',
			'lightbox-mosaic-count',
			'lightbox-mosaic-gap',
			'lightbox-mosaic-opacity',
			'lightbox-mosaic-background',
			'lightbox-mosaic-corner-radius',
			'fullscreen-corner-radius',
		);
		foreach ( $new_attributes as $attribute ) {
			$html = preg_replace(
				'/\sdata-' . preg_quote( $attribute, '/' ) . '="[^"]*"/',
				'',
				$html
			);
		}

		return $html;
	}

	private static function sortRecursively( array $value ): array {
		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}

		foreach ( $value as $key => $entry ) {
			if ( is_array( $entry ) ) {
				$value[ $key ] = self::sortRecursively( $entry );
			}
		}

		return $value;
	}
}
