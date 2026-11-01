<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Keeps the Values column of the parameter reference in sync with the value
 * rules used by the shortcode validation in assets/js/admin-settings.js.
 */
class ReferenceParameterValuesTest extends TestCase {

	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Parse the add( [ names ], { rule } ) calls of JZSA_VALUE_RULES.
	 */
	private static function js_rules(): array {
		$js = (string) file_get_contents( self::root() . '/assets/js/admin-settings.js' );
		self::assertSame( 1, preg_match( '/var JZSA_VALUE_RULES = \( function \(\) \{(.*?)return rules;/s', $js, $block ) );
		preg_match_all( '/add\(\s*\[(.*?)\]\s*,\s*(\{.*?\})\s*\);/s', $block[1], $calls, PREG_SET_ORDER );

		$rules = array();
		foreach ( $calls as $call ) {
			preg_match_all( "/'([^']+)'/", $call[1], $names );
			preg_match( "/type:\s*'([^']+)'/", $call[2], $type );
			$rule = array( 'type' => $type[1] );
			if ( preg_match( '/values:\s*\[(.*?)\]/s', $call[2], $values ) ) {
				preg_match_all( "/'([^']+)'/", $values[1], $list );
				$rule['values'] = $list[1];
			}
			foreach ( array( 'min', 'max' ) as $bound ) {
				if ( preg_match( '/' . $bound . ':\s*(\d+)/', $call[2], $number ) ) {
					$rule[ $bound ] = (int) $number[1];
				}
			}
			$rule['auto'] = false !== strpos( $call[2], 'auto: true' );
			foreach ( $names[1] as $name ) {
				$rules[ $name ] = $rule;
			}
		}
		return $rules;
	}

	/**
	 * Map every documented parameter to its Values cell.
	 */
	private static function values_cells(): array {
		$php = (string) file_get_contents( self::root() . '/includes/admin/reference-parameters.php' );
		preg_match_all( '#<tr>((?:(?!</tr>).)*?<td><code>[^<]+</code></td>.*?)</tr>#s', $php, $rows );

		$cells = array();
		foreach ( $rows[1] as $row ) {
			preg_match_all( '#<td>(.*?)</td>(?=\s*(?:<td>|$))#s', trim( $row ), $tds );
			self::assertCount( 4, $tds[1], 'Row needs Parameter, Description, Values and Default: ' . substr( trim( $row ), 0, 80 ) );
			preg_match( '#<code>([^<]+)</code>#', $tds[1][0], $name );
			$cells[ $name[1] ] = $tds[1][2];
		}
		return $cells;
	}

	public function test_every_table_has_a_values_header(): void {
		$php = (string) file_get_contents( self::root() . '/includes/admin/reference-parameters.php' );
		$this->assertSame(
			substr_count( $php, '<th>Default</th>' ) + substr_count( $php, "esc_html_e( 'Default'" ),
			substr_count( $php, '<th>Values</th>' ) + substr_count( $php, "esc_html_e( 'Values'" )
		);
	}

	public function test_values_column_matches_validation_rules(): void {
		$rules = self::js_rules();
		$cells = self::values_cells();
		$this->assertGreaterThan( 90, count( $cells ) );

		foreach ( $cells as $name => $cell ) {
			$this->assertNotSame( '', trim( $cell ), $name . ' has an empty Values cell' );
			if ( ! isset( $rules[ $name ] ) ) {
				continue;
			}
			$rule = $rules[ $name ];
			$expected = array();
			if ( 'bool' === $rule['type'] ) {
				$expected = array( '<code>true</code>', '<code>false</code>' );
			} elseif ( 'enum' === $rule['type'] ) {
				foreach ( $rule['values'] as $value ) {
					$expected[] = '<code>' . $value . '</code>';
				}
				preg_match_all( '#<code>([^<]+)</code>#', $cell, $listed );
				$this->assertSame( $rule['values'], $listed[1], $name . ' lists other values than the validation' );
			} elseif ( 'int' === $rule['type'] ) {
				$expected[] = isset( $rule['max'] ) ? $rule['min'] . '-' . $rule['max'] : '&ge; ' . $rule['min'];
				if ( $rule['auto'] ) {
					$expected[] = '<code>auto</code>';
				}
			} elseif ( 'color' === $rule['type'] ) {
				$expected = array( '#RRGGBB', '<code>transparent</code>' );
			}
			foreach ( $expected as $needle ) {
				$this->assertStringContainsString( (string) $needle, $cell, $name . ' Values cell is missing ' . $needle );
			}
		}
	}
}
