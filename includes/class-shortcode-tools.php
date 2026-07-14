<?php
/**
 * Shortcode parsing, semantic validation, and viewer migration helpers.
 *
 * @package JZSA_Shared_Albums
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JZSA_Shortcode_Tools {

	const MAX_SHORTCODE_LENGTH = 65536;

	/**
	 * Resolve the initial default without overwriting a valid administrator choice.
	 *
	 * @param string $stored_version Previously stored plugin version.
	 * @param string $current_default Existing option value.
	 * @param string $cutoff_version First version with the safe-upgrade policy.
	 * @return string
	 */
	public static function resolve_initial_default_viewer( $stored_version, $current_default, $cutoff_version ) {
		if ( in_array( $current_default, array( 'lightbox', 'fullscreen' ), true ) ) {
			return $current_default;
		}
		return '' !== $stored_version && version_compare( $stored_version, $cutoff_version, '<' )
			? 'fullscreen'
			: 'lightbox';
	}

	/**
	 * Parse one complete jzsa-album shortcode without executing it.
	 *
	 * @param string $shortcode Shortcode text.
	 * @return array
	 */
	public static function parse( $shortcode ) {
		$shortcode = trim( (string) $shortcode );
		$errors    = array();
		$warnings  = array();

		if ( '' === $shortcode ) {
			$errors[] = self::issue( 'empty_shortcode', 'error', array(), __( 'Paste one complete [jzsa-album] shortcode.', 'janzeman-shared-albums-for-google-photos' ) );
			return compact( 'shortcode', 'errors', 'warnings' ) + array( 'attributes' => array(), 'duplicates' => array() );
		}
		if ( strlen( $shortcode ) > self::MAX_SHORTCODE_LENGTH ) {
			$errors[] = self::issue( 'shortcode_too_long', 'error', array(), __( 'The shortcode is too long to process safely.', 'janzeman-shared-albums-for-google-photos' ) );
		}
		if ( 1 !== substr_count( strtolower( $shortcode ), '[jzsa-album' ) || ! preg_match( '/^\[jzsa-album(?:\s+(.*))?\]$/is', $shortcode, $outer ) ) {
			$errors[] = self::issue( 'invalid_shortcode_shape', 'error', array(), __( 'Enter exactly one complete [jzsa-album] shortcode with no surrounding text.', 'janzeman-shared-albums-for-google-photos' ) );
			return compact( 'shortcode', 'errors', 'warnings' ) + array( 'attributes' => array(), 'duplicates' => array() );
		}

		$attribute_text = isset( $outer[1] ) ? $outer[1] : '';
		$attributes     = array();
		$order          = array();
		$duplicates     = array();
		$pattern        = '/([\w-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/';
		preg_match_all( $pattern, $attribute_text, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$name  = strtolower( $match[1] );
			if ( preg_match( '/=\s*"/', $match[0] ) ) {
				$value = isset( $match[2] ) ? $match[2] : '';
			} elseif ( preg_match( "/=\s*'/", $match[0] ) ) {
				$value = isset( $match[3] ) ? $match[3] : '';
			} else {
				$value = isset( $match[4] ) ? $match[4] : '';
			}
			if ( array_key_exists( $name, $attributes ) ) {
				$duplicates[] = $name;
			}
			$attributes[ $name ] = $value;
			if ( ! in_array( $name, $order, true ) ) {
				$order[] = $name;
			}
		}

		$unparsed = trim( preg_replace( $pattern, ' ', $attribute_text ) );
		if ( '' !== $unparsed && preg_match( '/^https?:\/\/\S+$/i', $unparsed ) ) {
			$attributes['link'] = $unparsed;
			array_unshift( $order, 'link' );
			$unparsed = '';
		}
		if ( '' !== $unparsed ) {
			$errors[] = self::issue( 'invalid_shortcode_syntax', 'error', array(), __( 'The shortcode contains text that is not a valid parameter.', 'janzeman-shared-albums-for-google-photos' ) );
		}

		if ( ! isset( $attributes['link'] ) ) {
			$errors[] = self::issue( 'missing_link', 'error', array( 'link' ), __( 'The shortcode is missing its Google Photos link.', 'janzeman-shared-albums-for-google-photos' ) );
		}
		foreach ( array_unique( $duplicates ) as $name ) {
			$warnings[] = self::issue( 'duplicate_parameter', 'warning', array( $name ), sprintf( __( 'The parameter "%s" appears more than once. Only its final value is effective.', 'janzeman-shared-albums-for-google-photos' ), $name ) );
		}

		return compact( 'shortcode', 'attributes', 'order', 'duplicates', 'errors', 'warnings' );
	}

	/**
	 * Validate relationships between viewer parameters.
	 *
	 * @param array  $attributes Parsed attributes.
	 * @param string $context Validation context.
	 * @return array
	 */
	public static function validate_semantics( $attributes, $context = 'playground' ) {
		$issues = array();
		$viewer = isset( $attributes['viewer'] ) ? strtolower( trim( $attributes['viewer'] ) ) : '';
		if ( 'lightbox, fullscreen' === preg_replace( '/\s*,\s*/', ', ', $viewer ) ) {
			$viewer = 'both';
		}

		if ( 'both' === $viewer && ( isset( $attributes['viewer-trigger'] ) || isset( $attributes['viewer-toggle'] ) ) ) {
			$issues[] = self::issue( 'viewer_trigger_ambiguous', 'error', array( 'viewer', 'viewer-trigger' ), __( 'The shared viewer trigger cannot be used with viewer="both". Assign a gesture to either Lightbox or Fullscreen.', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$lb_trigger = self::trigger_value( $attributes, 'lightbox-trigger' );
		$fs_trigger = self::trigger_value( $attributes, 'fullscreen-trigger' );
		if ( 'both' === $viewer && self::is_gesture( $lb_trigger ) && self::is_gesture( $fs_trigger ) ) {
			$issues[] = self::issue( 'viewer_gesture_conflict', 'error', array( 'lightbox-trigger', 'fullscreen-trigger' ), __( 'Only one viewer may own a click or double-click gesture. Keep the other viewer button-only.', 'janzeman-shared-albums-for-google-photos' ) );
		}
		if ( 'lightbox' === $viewer && isset( $attributes['fullscreen-trigger'] ) ) {
			$issues[] = self::issue( 'inactive_viewer_trigger', 'warning', array( 'fullscreen-trigger' ), __( 'The Fullscreen trigger has no effect because only Lightbox is active.', 'janzeman-shared-albums-for-google-photos' ) );
		}
		if ( 'fullscreen' === $viewer && isset( $attributes['lightbox-trigger'] ) ) {
			$issues[] = self::issue( 'inactive_viewer_trigger', 'warning', array( 'lightbox-trigger' ), __( 'The Lightbox trigger has no effect because only Fullscreen is active.', 'janzeman-shared-albums-for-google-photos' ) );
		}
		if ( 'disabled' === $viewer && ( isset( $attributes['viewer-trigger'] ) || isset( $attributes['lightbox-trigger'] ) || isset( $attributes['fullscreen-trigger'] ) ) ) {
			$issues[] = self::issue( 'disabled_viewer_trigger', 'warning', array( 'viewer' ), __( 'Viewer triggers have no effect when the viewer is disabled.', 'janzeman-shared-albums-for-google-photos' ) );
		}

		$legacy_lb = self::trigger_value( $attributes, 'lightbox-toggle' );
		$legacy_fs = self::trigger_value( $attributes, 'fullscreen-toggle' );
		if ( self::is_gesture( $legacy_lb ) && self::is_gesture( $legacy_fs ) ) {
			$severity = 'migration' === $context ? 'error' : 'warning';
			$issues[] = self::issue( 'legacy_gesture_conflict', $severity, array( 'lightbox-toggle', 'fullscreen-toggle' ), __( 'The legacy Lightbox and Fullscreen gestures compete for the same gallery action. Choose one gesture owner during migration.', 'janzeman-shared-albums-for-google-photos' ) );
		}

		return $issues;
	}

	/**
	 * Migrate viewer selection and trigger attributes to the modern API.
	 *
	 * @param string $shortcode Shortcode text.
	 * @param string $goal Migration goal.
	 * @param string $default_viewer Current site default.
	 * @return array
	 */
	public static function migrate( $shortcode, $goal, $default_viewer ) {
		$parsed = self::parse( $shortcode );
		if ( ! empty( $parsed['errors'] ) ) {
			return array( 'ok' => false, 'issues' => array_merge( $parsed['errors'], $parsed['warnings'] ) );
		}
		$allowed_goals = array( 'preserve', 'lightbox', 'fullscreen', 'both' );
		if ( ! in_array( $goal, $allowed_goals, true ) ) {
			return array( 'ok' => false, 'issues' => array( self::issue( 'invalid_migration_goal', 'error', array(), __( 'Select a valid migration goal.', 'janzeman-shared-albums-for-google-photos' ) ) ) );
		}

		$attributes = $parsed['attributes'];
		$source_model = self::classify_model( $attributes );
		$input_issues = self::validate_semantics( $attributes, 'migration' );
		if ( 'preserve' === $goal && self::has_errors( $input_issues ) ) {
			return array(
				'ok'     => false,
				'issues' => array_merge( $parsed['warnings'], $input_issues ),
			);
		}
		if ( 'preserve' !== $goal ) {
			foreach ( $input_issues as &$issue ) {
				if ( 'legacy_gesture_conflict' === $issue['code'] ) {
					$issue['severity'] = 'warning';
				}
			}
			unset( $issue );
		}

		$current    = self::detect_viewer( $attributes, $default_viewer, $source_model );
		$target     = 'preserve' === $goal ? $current['viewer'] : $goal;
		if ( 'preserve' === $goal ) {
			$trigger = $current['trigger'];
		} elseif ( 'lightbox' === $target && self::is_gesture( $current['lightboxTrigger'] ) ) {
			$trigger = $current['lightboxTrigger'];
		} elseif ( 'fullscreen' === $target && self::is_gesture( $current['fullscreenTrigger'] ) ) {
			$trigger = $current['fullscreenTrigger'];
		} else {
			$trigger = 'button';
		}

		if ( 'legacy' === $source_model ) {
			$attributes = self::materialize_legacy_presentation( $attributes );
		}
		$attributes = self::canonicalize_obsolete_aliases( $attributes );

		foreach ( array_keys( $attributes ) as $name ) {
			if ( in_array( $name, array( 'viewer', 'viewer-toggle', 'viewer-trigger', 'lightbox-toggle', 'fullscreen-toggle', 'lightbox-trigger', 'fullscreen-trigger' ), true ) ) {
				unset( $attributes[ $name ] );
			}
		}
		$attributes['viewer'] = $target;
		if ( 'both' === $target && 'preserve' === $goal ) {
			if ( self::is_gesture( $current['lightboxTrigger'] ) ) {
				$attributes['lightbox-trigger'] = $current['lightboxTrigger'];
			} elseif ( self::is_gesture( $current['fullscreenTrigger'] ) ) {
				$attributes['fullscreen-trigger'] = $current['fullscreenTrigger'];
			}
		} elseif ( 'disabled' !== $target && 'button' !== $trigger ) {
			$attributes['viewer-trigger'] = $trigger;
		}

		$output = self::serialize( $attributes );
		$check  = self::validate_semantics( $attributes, 'migration' );
		return array(
			'ok'                 => ! self::has_errors( $check ),
			'shortcode'          => $output,
			'sourceModel'        => $source_model,
			'currentViewer'      => $current['viewer'],
			'targetViewer'       => $target,
			'behaviorPreserved'  => 'preserve' === $goal,
			'validationStatus'   => self::has_errors( $check ) ? 'error' : 'valid',
			'issues'             => array_merge( $parsed['warnings'], $input_issues, $check ),
		);
	}

	private static function classify_model( $attributes ) {
		foreach ( array_keys( $attributes ) as $name ) {
			if ( 'viewer' === $name || 0 === strpos( $name, 'viewer-' ) ) {
				return 'modern';
			}
		}
		foreach ( array_keys( $attributes ) as $name ) {
			if ( 0 === strpos( $name, 'lightbox-' ) || 0 === strpos( $name, 'fullscreen-' ) || 0 === strpos( $name, 'expanded-' ) ) {
				return 'legacy';
			}
		}
		return 'implicit';
	}

	private static function detect_viewer( $attributes, $default_viewer, $source_model ) {
		if ( 'modern' === $source_model ) {
			$viewer = isset( $attributes['viewer'] ) ? strtolower( trim( $attributes['viewer'] ) ) : $default_viewer;
			$viewer = 'lightbox, fullscreen' === preg_replace( '/\s*,\s*/', ', ', $viewer ) ? 'both' : $viewer;
			$trigger = isset( $attributes['viewer-trigger'] ) ? $attributes['viewer-trigger'] : ( isset( $attributes['viewer-toggle'] ) ? $attributes['viewer-toggle'] : 'button' );
			$lb_trigger = self::trigger_value( $attributes, 'lightbox-trigger' );
			$fs_trigger = self::trigger_value( $attributes, 'fullscreen-trigger' );
			return array( 'viewer' => $viewer, 'trigger' => self::modern_trigger( $trigger ), 'lightboxTrigger' => self::modern_trigger( $lb_trigger ), 'fullscreenTrigger' => self::modern_trigger( $fs_trigger ) );
		}
		if ( 'implicit' === $source_model ) {
			return array( 'viewer' => $default_viewer, 'trigger' => 'button', 'lightboxTrigger' => 'disabled', 'fullscreenTrigger' => 'disabled' );
		}

		$lb = isset( $attributes['lightbox-toggle'] ) ? self::trigger_value( $attributes, 'lightbox-toggle' ) : 'disabled';
		$fs = isset( $attributes['fullscreen-toggle'] )
			? self::trigger_value( $attributes, 'fullscreen-toggle' )
			: ( 'disabled' === $lb ? 'button-only' : 'disabled' );
		if ( 'disabled' !== $lb && 'disabled' !== $fs ) {
			return array( 'viewer' => 'both', 'trigger' => 'button', 'lightboxTrigger' => self::modern_trigger( $lb ), 'fullscreenTrigger' => self::modern_trigger( $fs ) );
		}
		if ( 'disabled' !== $lb ) {
			return array( 'viewer' => 'lightbox', 'trigger' => self::modern_trigger( $lb ), 'lightboxTrigger' => self::modern_trigger( $lb ), 'fullscreenTrigger' => 'disabled' );
		}
		if ( 'disabled' !== $fs ) {
			return array( 'viewer' => 'fullscreen', 'trigger' => self::modern_trigger( $fs ), 'lightboxTrigger' => 'disabled', 'fullscreenTrigger' => self::modern_trigger( $fs ) );
		}
		return array( 'viewer' => 'disabled', 'trigger' => 'button', 'lightboxTrigger' => 'disabled', 'fullscreenTrigger' => 'disabled' );
	}

	/**
	 * Materialize the 2.3.7 sideways inheritance before modern isolation applies.
	 *
	 * TODO(viewer-legacy-removal): Remove with the runtime legacy resolver after
	 * the migration criteria in safe_upgrade.md are met.
	 *
	 * @param array $attributes Parsed shortcode attributes.
	 * @return array
	 */
	private static function materialize_legacy_presentation( $attributes ) {
		$pairs = array(
			array( 'fullscreen-source-width', 'lightbox-source-width' ),
			array( 'fullscreen-source-height', 'lightbox-source-height' ),
			array( 'fullscreen-image-fit', 'lightbox-image-fit' ),
			array( 'fullscreen-controls-color', 'lightbox-controls-color' ),
			array( 'fullscreen-video-controls-color', 'lightbox-video-controls-color' ),
			array( 'fullscreen-video-controls-autohide', 'lightbox-video-controls-autohide' ),
			array( 'fullscreen-show-navigation', 'lightbox-show-navigation' ),
			array( 'fullscreen-show-link-button', 'lightbox-show-link-button' ),
			array( 'fullscreen-show-download-button', 'lightbox-show-download-button' ),
			array( 'fullscreen-slideshow', 'lightbox-slideshow' ),
			array( 'fullscreen-slideshow-delay', 'lightbox-slideshow-delay' ),
			array( 'fullscreen-slideshow-autoresume', 'lightbox-slideshow-autoresume' ),
		);

		foreach ( $pairs as $pair ) {
			$first_set  = isset( $attributes[ $pair[0] ] ) && '' !== trim( (string) $attributes[ $pair[0] ] );
			$second_set = isset( $attributes[ $pair[1] ] ) && '' !== trim( (string) $attributes[ $pair[1] ] );
			if ( $first_set && ! $second_set ) {
				$attributes[ $pair[1] ] = $attributes[ $pair[0] ];
			} elseif ( $second_set && ! $first_set ) {
				$attributes[ $pair[0] ] = $attributes[ $pair[1] ];
			}
		}

		return $attributes;
	}

	private static function canonicalize_obsolete_aliases( $attributes ) {
		$aliases = array(
			'viewer-display-max-width'     => 'viewer-max-width',
			'viewer-display-max-height'    => 'viewer-max-height',
			'fullscreen-display-max-width' => 'fullscreen-max-width',
			'fullscreen-display-max-height' => 'fullscreen-max-height',
		);
		foreach ( $aliases as $obsolete => $canonical ) {
			if ( ! isset( $attributes[ $canonical ] ) && isset( $attributes[ $obsolete ] ) ) {
				$attributes[ $canonical ] = $attributes[ $obsolete ];
			}
			unset( $attributes[ $obsolete ] );
		}
		return $attributes;
	}

	private static function serialize( $attributes ) {
		$tokens = array();
		foreach ( $attributes as $name => $value ) {
			$tokens[] = $name . '="' . str_replace( '"', '&quot;', (string) $value ) . '"';
		}
		return '[jzsa-album ' . implode( ' ', $tokens ) . ']';
	}

	private static function trigger_value( $attributes, $name ) {
		if ( ! isset( $attributes[ $name ] ) ) {
			return 'disabled';
		}
		$value = strtolower( trim( (string) $attributes[ $name ] ) );
		if ( in_array( $value, array( 'true', 'on', 'yes', '1' ), true ) ) {
			return 'click';
		}
		if ( in_array( $value, array( 'false', 'off', 'no', '0' ), true ) ) {
			return 'disabled';
		}
		return $value;
	}

	private static function modern_trigger( $value ) {
		return 'button-only' === $value ? 'button' : $value;
	}

	private static function is_gesture( $value ) {
		return in_array( $value, array( 'click', 'double-click' ), true );
	}

	private static function issue( $code, $severity, $parameters, $message ) {
		return compact( 'code', 'severity', 'parameters', 'message' );
	}

	private static function has_errors( $issues ) {
		foreach ( $issues as $issue ) {
			if ( 'error' === $issue['severity'] ) {
				return true;
			}
		}
		return false;
	}
}
