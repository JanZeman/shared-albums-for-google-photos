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
	 * @param string $cutoff_version   First version with the safe-upgrade policy.
	 * @param bool   $fresh_activation Whether this is the activation hook for a fresh install.
	 * @return string
	 */
	public static function resolve_initial_default_viewer( $stored_version, $current_default, $cutoff_version, $fresh_activation = false ) {
		if ( in_array( $current_default, array( 'lightbox', 'fullscreen' ), true ) ) {
			return $current_default;
		}
		return self::is_legacy_upgrade( $stored_version, $cutoff_version, $fresh_activation )
			? 'fullscreen'
			: 'lightbox';
	}

	/**
	 * Distinguish a legacy upgrade from the lifecycle point where no version means fresh.
	 *
	 * Versions before 2.1.0 did not store a plugin version. If an active plugin reaches
	 * normal startup without that option, it must be handled as a legacy upgrade.
	 *
	 * @param string $stored_version   Previously stored plugin version.
	 * @param string $cutoff_version   First version with the safe-upgrade policy.
	 * @param bool   $fresh_activation Whether this is the activation hook for a fresh install.
	 * @return bool
	 */
	public static function is_legacy_upgrade( $stored_version, $cutoff_version, $fresh_activation = false ) {
		if ( '' === $stored_version ) {
			return ! $fresh_activation;
		}

		return version_compare( $stored_version, $cutoff_version, '<' );
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

		$attribute_text          = isset( $outer[1] ) ? $outer[1] : '';
		$attributes              = array();
		$order                   = array();
		$duplicates              = array();
		$link_whitespace_removed = false;
		$pattern                 = '/([\w-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/';
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
			if ( 'link' === $name && preg_match( '/\s/', $value ) ) {
				$value = preg_replace( '/\s+/', '', $value );
				$link_whitespace_removed = true;
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
		if ( $link_whitespace_removed ) {
			$warnings[] = self::issue( 'link_whitespace_removed', 'warning', array( 'link' ), __( 'The Google Photos link contains invalid whitespace. The generated shortcode removes it because URLs cannot contain spaces or line breaks.', 'janzeman-shared-albums-for-google-photos' ) );
		}
		foreach ( array_unique( $duplicates ) as $name ) {
			$warnings[] = self::issue( 'duplicate_parameter', 'warning', array( $name ), sprintf( __( 'The parameter "%s" appears more than once. Only its final value is effective.', 'janzeman-shared-albums-for-google-photos' ), $name ) );
		}

		return compact( 'shortcode', 'attributes', 'order', 'duplicates', 'errors', 'warnings' );
	}

	/**
	 * Normalize shortcode presentation without changing its parameters.
	 *
	 * @param string $shortcode Shortcode text.
	 * @return array
	 */
	public static function format( $shortcode ) {
		$parsed = self::parse( $shortcode );
		$issues = array_merge( $parsed['errors'], $parsed['warnings'] );
		if ( ! empty( $parsed['errors'] ) || ! empty( $parsed['duplicates'] ) ) {
			return array( 'ok' => false, 'issues' => $issues );
		}

		$semantic_issues = self::validate_semantics( $parsed['attributes'] );
		$issues = array_merge( $issues, $semantic_issues );
		if ( self::has_errors( $semantic_issues ) ) {
			return array( 'ok' => false, 'issues' => $issues );
		}

		$formatted = self::serialize( $parsed['attributes'] );
		return array(
			'ok'        => true,
			'shortcode' => $formatted,
			'changed'   => trim( (string) $shortcode ) !== $formatted,
			'issues'    => $issues,
		);
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
			$issues[] = self::issue( 'viewer_trigger_ambiguous', 'error', array( 'viewer', 'viewer-trigger' ), __( 'The shared viewer trigger cannot be used with viewer="both". Visitors need a clear way to understand which viewer will open. Keep both viewers button-only, or assign a gesture to only one mode-specific trigger.', 'janzeman-shared-albums-for-google-photos' ) );
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

		$attributes        = $parsed['attributes'];
		$source_attributes = $attributes;
		$source_model = self::classify_model( $attributes );
		$input_issues = self::validate_semantics( $attributes, 'migration' );
		$input_has_errors = self::has_errors( $input_issues );
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
		$same_viewer_target = $target === $current['viewer'];
		if ( 'preserve' === $goal || ( $same_viewer_target && 'both' !== $target ) ) {
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
		$attributes = self::collapse_shared_viewer_attributes( $attributes );

		foreach ( array_keys( $attributes ) as $name ) {
			if ( in_array( $name, array( 'viewer', 'viewer-toggle', 'viewer-trigger', 'lightbox-toggle', 'fullscreen-toggle', 'lightbox-trigger', 'fullscreen-trigger' ), true ) ) {
				unset( $attributes[ $name ] );
			}
		}
		$attributes['viewer'] = $target;
		if ( 'both' === $target && ( 'preserve' === $goal || $same_viewer_target ) ) {
			if ( self::is_gesture( $current['lightboxTrigger'] ) ) {
				$attributes['lightbox-trigger'] = $current['lightboxTrigger'];
			} elseif ( self::is_gesture( $current['fullscreenTrigger'] ) ) {
				$attributes['fullscreen-trigger'] = $current['fullscreenTrigger'];
			}
		} elseif ( 'disabled' !== $target && 'button' !== $trigger ) {
			$attributes['viewer-trigger'] = $trigger;
		}

		$output       = self::serialize( $attributes );
		$check        = self::validate_semantics( $attributes, 'migration' );
		$input_issues = array_merge( $parsed['warnings'], $input_issues );
		$replacements = self::describe_migration_replacements( $source_attributes, $attributes );
		return array(
			'ok'                 => ! self::has_errors( $check ),
			'shortcode'          => $output,
			'sourceModel'        => $source_model,
			'currentViewer'      => $current['viewer'],
			'targetViewer'       => $target,
			'behaviorPreserved'  => 'preserve' === $goal || ( $same_viewer_target && ! $input_has_errors ),
			'validationStatus'   => self::has_errors( $check ) ? 'error' : 'valid',
			'inputIssues'        => $input_issues,
			'outputIssues'       => $check,
			'replacements'       => $replacements,
			'changes'            => self::describe_migration_changes( $source_attributes, $attributes, $parsed['order'], $replacements ),
			'issues'             => array_merge( $input_issues, $check ),
		);
	}

	/**
	 * Build the two shortcode forms stored by the Community API.
	 *
	 * The 2.3.7 form remains compatible with that exact plugin generation. The 2.4.0 form
	 * uses explicit viewer syntax and is returned only to compatible clients.
	 *
	 * @param string $shortcode      Submitted shortcode.
	 * @param string $default_viewer Site default used for implicit shortcodes.
	 * @return array
	 */
	public static function community_variants( $shortcode, $default_viewer ) {
		$current = self::migrate( $shortcode, 'preserve', $default_viewer );
		if ( empty( $current['ok'] ) ) {
			return array(
				'ok'     => false,
				'issues' => isset( $current['issues'] ) ? $current['issues'] : array(),
			);
		}

		$parsed = self::parse( $current['shortcode'] );
		$viewer = isset( $parsed['attributes']['viewer'] ) ? strtolower( trim( (string) $parsed['attributes']['viewer'] ) ) : '';
		if ( ! in_array( $viewer, array( 'lightbox', 'fullscreen', 'both', 'disabled' ), true ) ) {
			return array(
				'ok'     => false,
				'issues' => array( self::issue( 'invalid_viewer', 'error', array( 'viewer' ), __( 'The viewer parameter must use lightbox, fullscreen, both, or disabled before this shortcode can be shared.', 'janzeman-shared-albums-for-google-photos' ) ) ),
			);
		}

		return array(
			'ok'      => true,
			'v2_3_7'  => self::serialize( self::v2_3_7_community_attributes( $parsed['attributes'] ) ),
			'v2_4_0'  => $current['shortcode'],
			'issues'  => isset( $current['issues'] ) ? $current['issues'] : array(),
		);
	}

	/**
	 * Convert canonical 2.4.0 attributes to the syntax understood by 2.3.7.
	 *
	 * @param array $attributes Canonical current attributes.
	 * @return array
	 */
	private static function v2_3_7_community_attributes( $attributes ) {
		$viewer             = strtolower( trim( (string) $attributes['viewer'] ) );
		$shared_trigger     = isset( $attributes['viewer-trigger'] ) ? self::v2_3_7_trigger( $attributes['viewer-trigger'] ) : 'button-only';
		$lightbox_trigger   = isset( $attributes['lightbox-trigger'] ) ? self::v2_3_7_trigger( $attributes['lightbox-trigger'] ) : 'button-only';
		$fullscreen_trigger = isset( $attributes['fullscreen-trigger'] ) ? self::v2_3_7_trigger( $attributes['fullscreen-trigger'] ) : 'button-only';

		$lightbox_toggle   = 'disabled';
		$fullscreen_toggle = 'disabled';
		if ( 'lightbox' === $viewer ) {
			$lightbox_toggle = $shared_trigger;
		} elseif ( 'fullscreen' === $viewer ) {
			$fullscreen_toggle = $shared_trigger;
		} elseif ( 'both' === $viewer ) {
			$lightbox_toggle   = $lightbox_trigger;
			$fullscreen_toggle = $fullscreen_trigger;
		}

		foreach ( self::viewer_attribute_pairs() as $viewer_key => $concrete_keys ) {
			if ( ! array_key_exists( $viewer_key, $attributes ) ) {
				continue;
			}
			foreach ( $concrete_keys as $concrete_key ) {
				if ( ! array_key_exists( $concrete_key, $attributes ) ) {
					$attributes[ $concrete_key ] = $attributes[ $viewer_key ];
				}
			}
		}
		if ( 'both' === $viewer ) {
			$attributes = self::isolate_v2_3_7_sideways_pairs( $attributes );
		}

		if ( array_key_exists( 'fullscreen-max-width', $attributes ) ) {
			$attributes['fullscreen-display-max-width'] = $attributes['fullscreen-max-width'];
		}
		if ( array_key_exists( 'fullscreen-max-height', $attributes ) ) {
			$attributes['fullscreen-display-max-height'] = $attributes['fullscreen-max-height'];
		}

		foreach ( array_keys( $attributes ) as $name ) {
			$is_unsupported_lightbox_group = 0 === strpos( $name, 'lightbox-info-' ) || 0 === strpos( $name, 'lightbox-mosaic' );
			$is_modern_viewer_parameter = 'viewer' === $name || 0 === strpos( $name, 'viewer-' );
			$is_renamed_parameter       = in_array( $name, array( 'lightbox-trigger', 'fullscreen-trigger', 'fullscreen-max-width', 'fullscreen-max-height', 'fullscreen-corner-radius' ), true );
			if ( $is_modern_viewer_parameter || $is_unsupported_lightbox_group || $is_renamed_parameter ) {
				unset( $attributes[ $name ] );
			}
		}

		$attributes['lightbox-toggle']   = $lightbox_toggle;
		$attributes['fullscreen-toggle'] = $fullscreen_toggle;
		return $attributes;
	}

	/**
	 * Prevent 2.3.7 sideways inheritance from changing a 2.4.0 mode override.
	 *
	 * TODO(viewer-legacy-removal): Remove this exporter support only when 2.3.7
	 * Community responses are retired under the criteria in safe_upgrade.md.
	 *
	 * @param array $attributes 2.4.0 attributes materialized for 2.3.7.
	 * @return array
	 */
	private static function isolate_v2_3_7_sideways_pairs( $attributes ) {
		$inline_autoresume = self::first_attribute_value(
			$attributes,
			array( 'slideshow-autoresume', 'slideshow-autoresume-timeout', 'slideshow-inactivity-timeout' ),
			'30'
		);
		$pairs = array(
			array( 'fullscreen-source-width', 'lightbox-source-width', '1920' ),
			array( 'fullscreen-source-height', 'lightbox-source-height', '1440' ),
			array( 'fullscreen-image-fit', 'lightbox-image-fit', 'contain' ),
			array( 'fullscreen-controls-color', 'lightbox-controls-color', self::first_attribute_value( $attributes, array( 'controls-color' ), '#ffffff' ) ),
			array( 'fullscreen-video-controls-color', 'lightbox-video-controls-color', self::first_attribute_value( $attributes, array( 'video-controls-color' ), '#00b2ff' ) ),
			array( 'fullscreen-video-controls-autohide', 'lightbox-video-controls-autohide', self::first_attribute_value( $attributes, array( 'video-controls-autohide' ), 'false' ) ),
			array( 'fullscreen-show-navigation', 'lightbox-show-navigation', self::first_attribute_value( $attributes, array( 'show-navigation' ), 'true' ) ),
			array( 'fullscreen-show-link-button', 'lightbox-show-link-button', self::first_attribute_value( $attributes, array( 'show-link-button' ), 'false' ) ),
			array( 'fullscreen-show-download-button', 'lightbox-show-download-button', self::first_attribute_value( $attributes, array( 'show-download-button' ), 'false' ) ),
			array( 'fullscreen-slideshow', 'lightbox-slideshow', 'disabled' ),
			array( 'fullscreen-slideshow-delay', 'lightbox-slideshow-delay', '5' ),
			array( 'fullscreen-slideshow-autoresume', 'lightbox-slideshow-autoresume', $inline_autoresume ),
		);

		foreach ( $pairs as $pair ) {
			$fullscreen_set = self::has_non_empty_attribute( $attributes, $pair[0] );
			$lightbox_set   = self::has_non_empty_attribute( $attributes, $pair[1] );
			if ( $fullscreen_set && ! $lightbox_set ) {
				$attributes[ $pair[1] ] = $pair[2];
			} elseif ( $lightbox_set && ! $fullscreen_set ) {
				$attributes[ $pair[0] ] = $pair[2];
			}
		}

		return $attributes;
	}

	private static function has_non_empty_attribute( $attributes, $name ) {
		return array_key_exists( $name, $attributes ) && '' !== trim( (string) $attributes[ $name ] );
	}

	private static function first_attribute_value( $attributes, $names, $default ) {
		foreach ( $names as $name ) {
			if ( array_key_exists( $name, $attributes ) ) {
				return $attributes[ $name ];
			}
		}
		return $default;
	}

	/**
	 * Shared viewer parameters and their 2.3.7-compatible concrete forms.
	 *
	 * @return array
	 */
	private static function viewer_attribute_pairs() {
		return array(
			'viewer-max-width'            => array( 'fullscreen-display-max-width', 'lightbox-max-width' ),
			'viewer-max-height'           => array( 'fullscreen-display-max-height', 'lightbox-max-height' ),
			'viewer-source-width'         => array( 'fullscreen-source-width', 'lightbox-source-width' ),
			'viewer-source-height'        => array( 'fullscreen-source-height', 'lightbox-source-height' ),
			'viewer-image-fit'            => array( 'fullscreen-image-fit', 'lightbox-image-fit' ),
			'viewer-background-color'     => array( 'fullscreen-background-color', 'lightbox-background-color' ),
			'viewer-corner-radius'        => array( 'lightbox-corner-radius' ),
			'viewer-controls-color'       => array( 'fullscreen-controls-color', 'lightbox-controls-color' ),
			'viewer-video-controls-color' => array( 'fullscreen-video-controls-color', 'lightbox-video-controls-color' ),
			'viewer-video-controls-autohide' => array( 'fullscreen-video-controls-autohide', 'lightbox-video-controls-autohide' ),
			'viewer-show-navigation'      => array( 'fullscreen-show-navigation', 'lightbox-show-navigation' ),
			'viewer-show-link-button'     => array( 'fullscreen-show-link-button', 'lightbox-show-link-button' ),
			'viewer-show-download-button' => array( 'fullscreen-show-download-button', 'lightbox-show-download-button' ),
			'viewer-slideshow'            => array( 'fullscreen-slideshow', 'lightbox-slideshow' ),
			'viewer-slideshow-delay'      => array( 'fullscreen-slideshow-delay', 'lightbox-slideshow-delay' ),
			'viewer-slideshow-autoresume' => array( 'fullscreen-slideshow-autoresume', 'lightbox-slideshow-autoresume' ),
			'viewer-info-bottom'          => array( 'fullscreen-info-bottom' ),
			'viewer-info-top'             => array( 'fullscreen-info-top' ),
			'viewer-info-top-secondary'   => array( 'fullscreen-info-top-secondary' ),
			'viewer-info-font-size'       => array( 'fullscreen-info-font-size' ),
			'viewer-info-font-family'     => array( 'fullscreen-info-font-family' ),
			'viewer-info-font-color'      => array( 'fullscreen-info-font-color' ),
			'viewer-mosaic'               => array( 'fullscreen-mosaic' ),
			'viewer-mosaic-position'      => array( 'fullscreen-mosaic-position' ),
			'viewer-mosaic-layout'        => array( 'fullscreen-mosaic-layout' ),
			'viewer-mosaic-count'         => array( 'fullscreen-mosaic-count' ),
			'viewer-mosaic-gap'           => array( 'fullscreen-mosaic-gap' ),
			'viewer-mosaic-opacity'       => array( 'fullscreen-mosaic-opacity' ),
			'viewer-mosaic-background'    => array( 'fullscreen-mosaic-background' ),
			'viewer-mosaic-corner-radius' => array( 'fullscreen-mosaic-corner-radius' ),
		);
	}

	private static function v2_3_7_trigger( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( 'click', 'double-click' ), true ) ? $value : 'button-only';
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
		foreach ( self::legacy_sideways_attribute_pairs() as $pair ) {
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

	/**
	 * Parameters that inherited sideways between viewer modes in 2.3.7.
	 *
	 * @return array
	 */
	private static function legacy_sideways_attribute_pairs() {
		return array(
			'viewer-source-width'            => array( 'fullscreen-source-width', 'lightbox-source-width' ),
			'viewer-source-height'           => array( 'fullscreen-source-height', 'lightbox-source-height' ),
			'viewer-image-fit'               => array( 'fullscreen-image-fit', 'lightbox-image-fit' ),
			'viewer-controls-color'          => array( 'fullscreen-controls-color', 'lightbox-controls-color' ),
			'viewer-video-controls-color'    => array( 'fullscreen-video-controls-color', 'lightbox-video-controls-color' ),
			'viewer-video-controls-autohide' => array( 'fullscreen-video-controls-autohide', 'lightbox-video-controls-autohide' ),
			'viewer-show-navigation'         => array( 'fullscreen-show-navigation', 'lightbox-show-navigation' ),
			'viewer-show-link-button'        => array( 'fullscreen-show-link-button', 'lightbox-show-link-button' ),
			'viewer-show-download-button'    => array( 'fullscreen-show-download-button', 'lightbox-show-download-button' ),
			'viewer-slideshow'               => array( 'fullscreen-slideshow', 'lightbox-slideshow' ),
			'viewer-slideshow-delay'         => array( 'fullscreen-slideshow-delay', 'lightbox-slideshow-delay' ),
			'viewer-slideshow-autoresume'    => array( 'fullscreen-slideshow-autoresume', 'lightbox-slideshow-autoresume' ),
		);
	}

	/**
	 * Shared modern viewer parameters and their concrete mode overrides.
	 *
	 * @return array
	 */
	private static function current_viewer_attribute_pairs() {
		return array(
			'viewer-max-width'                 => array( 'fullscreen-max-width', 'lightbox-max-width' ),
			'viewer-max-height'                => array( 'fullscreen-max-height', 'lightbox-max-height' ),
			'viewer-source-width'              => array( 'fullscreen-source-width', 'lightbox-source-width' ),
			'viewer-source-height'             => array( 'fullscreen-source-height', 'lightbox-source-height' ),
			'viewer-image-fit'                 => array( 'fullscreen-image-fit', 'lightbox-image-fit' ),
			'viewer-background-color'          => array( 'fullscreen-background-color', 'lightbox-background-color' ),
			'viewer-corner-radius'             => array( 'fullscreen-corner-radius', 'lightbox-corner-radius' ),
			'viewer-controls-color'            => array( 'fullscreen-controls-color', 'lightbox-controls-color' ),
			'viewer-video-controls-color'      => array( 'fullscreen-video-controls-color', 'lightbox-video-controls-color' ),
			'viewer-video-controls-autohide'   => array( 'fullscreen-video-controls-autohide', 'lightbox-video-controls-autohide' ),
			'viewer-show-navigation'           => array( 'fullscreen-show-navigation', 'lightbox-show-navigation' ),
			'viewer-show-link-button'          => array( 'fullscreen-show-link-button', 'lightbox-show-link-button' ),
			'viewer-show-download-button'      => array( 'fullscreen-show-download-button', 'lightbox-show-download-button' ),
			'viewer-slideshow'                 => array( 'fullscreen-slideshow', 'lightbox-slideshow' ),
			'viewer-slideshow-delay'           => array( 'fullscreen-slideshow-delay', 'lightbox-slideshow-delay' ),
			'viewer-slideshow-autoresume'      => array( 'fullscreen-slideshow-autoresume', 'lightbox-slideshow-autoresume' ),
			'viewer-info-bottom'               => array( 'fullscreen-info-bottom', 'lightbox-info-bottom' ),
			'viewer-info-top'                  => array( 'fullscreen-info-top', 'lightbox-info-top' ),
			'viewer-info-top-secondary'        => array( 'fullscreen-info-top-secondary', 'lightbox-info-top-secondary' ),
			'viewer-info-font-size'            => array( 'fullscreen-info-font-size', 'lightbox-info-font-size' ),
			'viewer-info-font-family'          => array( 'fullscreen-info-font-family', 'lightbox-info-font-family' ),
			'viewer-info-font-color'           => array( 'fullscreen-info-font-color', 'lightbox-info-font-color' ),
			'viewer-mosaic'                    => array( 'fullscreen-mosaic', 'lightbox-mosaic' ),
			'viewer-mosaic-position'           => array( 'fullscreen-mosaic-position', 'lightbox-mosaic-position' ),
			'viewer-mosaic-layout'             => array( 'fullscreen-mosaic-layout', 'lightbox-mosaic-layout' ),
			'viewer-mosaic-count'              => array( 'fullscreen-mosaic-count', 'lightbox-mosaic-count' ),
			'viewer-mosaic-gap'                => array( 'fullscreen-mosaic-gap', 'lightbox-mosaic-gap' ),
			'viewer-mosaic-opacity'            => array( 'fullscreen-mosaic-opacity', 'lightbox-mosaic-opacity' ),
			'viewer-mosaic-background'         => array( 'fullscreen-mosaic-background', 'lightbox-mosaic-background' ),
			'viewer-mosaic-corner-radius'      => array( 'fullscreen-mosaic-corner-radius', 'lightbox-mosaic-corner-radius' ),
		);
	}

	/**
	 * Replace identical concrete viewer values with their shared modern form.
	 *
	 * Different values remain isolated as explicit Lightbox and Fullscreen
	 * overrides.
	 *
	 * @param array $attributes Shortcode attributes.
	 * @return array
	 */
	private static function collapse_shared_viewer_attributes( $attributes ) {
		$allows_empty = array( 'viewer-info-bottom', 'viewer-info-top', 'viewer-info-top-secondary' );
		foreach ( self::current_viewer_attribute_pairs() as $viewer_key => $pair ) {
			if ( ! array_key_exists( $pair[0], $attributes ) || ! array_key_exists( $pair[1], $attributes ) ) {
				continue;
			}

			$first  = (string) $attributes[ $pair[0] ];
			$second = (string) $attributes[ $pair[1] ];
			if ( ( '' === trim( $first ) && ! in_array( $viewer_key, $allows_empty, true ) ) || $first !== $second ) {
				continue;
			}

			if ( array_key_exists( $viewer_key, $attributes ) && (string) $attributes[ $viewer_key ] !== $first ) {
				continue;
			}

			$attributes[ $viewer_key ] = $attributes[ $pair[0] ];
			unset( $attributes[ $pair[0] ], $attributes[ $pair[1] ] );
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

	/**
	 * Describe obsolete viewer syntax replaced in the generated shortcode.
	 *
	 * @param array $source Source shortcode attributes.
	 * @param array $output Generated shortcode attributes.
	 * @return array
	 */
	private static function describe_migration_replacements( $source, $output ) {
		$rows = array();
		$aliases = array(
			'viewer-display-max-width'      => 'viewer-max-width',
			'viewer-display-max-height'     => 'viewer-max-height',
			'fullscreen-display-max-width'  => 'fullscreen-max-width',
			'fullscreen-display-max-height' => 'fullscreen-max-height',
		);

		foreach ( $aliases as $obsolete => $canonical ) {
			if ( isset( $source[ $obsolete ], $output[ $canonical ] ) ) {
				$rows[] = array(
					'obsolete'     => $obsolete,
					'replacements' => array( self::attribute_token( $canonical, $output[ $canonical ] ) ),
				);
			}
		}

		foreach ( array( 'viewer-toggle', 'lightbox-toggle', 'fullscreen-toggle' ) as $obsolete ) {
			if ( ! isset( $source[ $obsolete ] ) ) {
				continue;
			}
			$modern = array();
			foreach ( array( 'viewer', 'viewer-trigger' ) as $name ) {
				if ( isset( $output[ $name ] ) ) {
					$modern[] = self::attribute_token( $name, $output[ $name ] );
				}
			}
			$mode_trigger = 'lightbox-toggle' === $obsolete ? 'lightbox-trigger' : ( 'fullscreen-toggle' === $obsolete ? 'fullscreen-trigger' : '' );
			if ( $mode_trigger && isset( $output[ $mode_trigger ] ) ) {
				$modern[] = self::attribute_token( $mode_trigger, $output[ $mode_trigger ] );
			}
			$rows[] = array(
				'obsolete'     => $obsolete,
				'replacements' => array_values( array_unique( $modern ) ),
			);
		}

		return $rows;
	}

	private static function attribute_token( $name, $value ) {
		return $name . '="' . (string) $value . '"';
	}

	/**
	 * Report every effective transformation applied by the migrator.
	 *
	 * @param array $source       Source shortcode attributes.
	 * @param array $output       Generated shortcode attributes.
	 * @param array $source_order Source parameter order.
	 * @param array $replacements Obsolete-to-current replacement rows.
	 * @return array
	 */
	private static function describe_migration_changes( $source, $output, $source_order, $replacements ) {
		$replaced_source = array();
		$replacement_targets = array();
		foreach ( $replacements as $replacement ) {
			$replaced_source[] = $replacement['obsolete'];
			foreach ( $replacement['replacements'] as $token ) {
				$replacement_targets[] = strstr( $token, '=', true );
			}
		}

		$added = array();
		foreach ( $output as $name => $value ) {
			if ( ! array_key_exists( $name, $source ) && ! in_array( $name, $replacement_targets, true ) ) {
				$added[] = self::attribute_token( $name, $value );
			}
		}

		$removed = array();
		foreach ( $source as $name => $value ) {
			if ( ! array_key_exists( $name, $output ) && ! in_array( $name, $replaced_source, true ) ) {
				$removed[] = self::attribute_token( $name, $value );
			}
		}

		$changed = array();
		foreach ( $source as $name => $value ) {
			if ( array_key_exists( $name, $output ) && (string) $value !== (string) $output[ $name ] ) {
				$changed[] = array(
					'from' => self::attribute_token( $name, $value ),
					'to'   => self::attribute_token( $name, $output[ $name ] ),
				);
			}
		}

		$common_source_order = array_values(
			array_filter(
				$source_order,
				function ( $name ) use ( $output ) {
					return array_key_exists( $name, $output );
				}
			)
		);
		$common_output_order = array_values(
			array_filter(
				self::serialized_attribute_order( $output ),
				function ( $name ) use ( $source ) {
					return array_key_exists( $name, $source );
				}
			)
		);

		return array(
			'added'           => $added,
			'removed'         => $removed,
			'changed'         => $changed,
			'orderNormalized' => $common_source_order !== $common_output_order,
		);
	}

	private static function serialized_attribute_order( $attributes ) {
		$order    = array();
		$priority = self::canonical_attribute_order();
		foreach ( $priority as $name ) {
			if ( array_key_exists( $name, $attributes ) ) {
				$order[] = $name;
			}
		}
		foreach ( array_keys( $attributes ) as $name ) {
			if ( ! in_array( $name, $priority, true ) ) {
				$order[] = $name;
			}
		}
		return $order;
	}

	private static function serialize( $attributes ) {
		$tokens   = array();
		$priority = self::canonical_attribute_order();

		foreach ( $priority as $name ) {
			if ( array_key_exists( $name, $attributes ) ) {
				$tokens[] = $name . '="' . str_replace( '"', '&quot;', (string) $attributes[ $name ] ) . '"';
			}
		}
		foreach ( $attributes as $name => $value ) {
			if ( in_array( $name, $priority, true ) ) {
				continue;
			}
			$tokens[] = $name . '="' . str_replace( '"', '&quot;', (string) $value ) . '"';
		}
		return '[jzsa-album ' . implode( ' ', $tokens ) . ']';
	}

	/**
	 * Return the standard display order for every accepted shortcode parameter.
	 *
	 * Unknown extension parameters are serialized after this list in their original order.
	 *
	 * @return string[]
	 */
	public static function canonical_attribute_order() {
		return array(
			// Source and primary visitor experience.
			'link',
			'mode',
			'viewer',
			'viewer-trigger',
			'viewer-toggle',
			'lightbox-trigger',
			'lightbox-toggle',
			'fullscreen-trigger',
			'fullscreen-toggle',

			// Content selection and initial state.
			'limit',
			'start-at',
			'show-videos',

			// Inline frame and source quality.
			'width',
			'height',
			'source-width',
			'source-height',
			'image-fit',

			// Gallery layout.
			'gallery-layout',
			'gallery-sizing',
			'gallery-columns',
			'gallery-columns-tablet',
			'gallery-columns-mobile',
			'gallery-row-height',
			'gallery-rows',
			'gallery-scrollable',
			'gallery-gap',
			'gallery-buttons-on-mobile',

			// Inline mosaic.
			'mosaic',
			'mosaic-position',
			'mosaic-count',
			'mosaic-gap',
			'mosaic-opacity',
			'mosaic-background',
			'mosaic-corner-radius',

			// Inline playback, controls, and appearance.
			'slideshow',
			'slideshow-delay',
			'slideshow-autoresume',
			'show-navigation',
			'show-link-button',
			'show-download-button',
			'interaction-lock',
			'background-color',
			'controls-color',
			'corner-radius',
			'video-controls-color',
			'video-controls-autohide',

			// Inline information.
			'info-top',
			'info-top-secondary',
			'info-bottom',
			'gallery-info-bottom',
			'info-font-size',
			'info-font-family',
			'info-font-color',
			'info-wrap',
			'info-text-align',
			'info-top-text-align',
			'info-top-secondary-text-align',
			'info-bottom-text-align',
			'info-halo-effect',
			'info-top-halo-effect',
			'info-top-secondary-halo-effect',
			'info-bottom-halo-effect',
			'gallery-info-bottom-halo-effect',
			'album-title-halo-effect',

			// Shared expanded-view settings.
			'viewer-max-width',
			'viewer-max-height',
			'viewer-source-width',
			'viewer-source-height',
			'viewer-image-fit',
			'viewer-background-color',
			'viewer-corner-radius',
			'viewer-controls-color',
			'viewer-video-controls-color',
			'viewer-video-controls-autohide',
			'viewer-show-navigation',
			'viewer-show-link-button',
			'viewer-show-download-button',
			'viewer-slideshow',
			'viewer-slideshow-delay',
			'viewer-slideshow-autoresume',
			'viewer-info-top',
			'viewer-info-top-secondary',
			'viewer-info-bottom',
			'viewer-info-font-size',
			'viewer-info-font-family',
			'viewer-info-font-color',
			'viewer-mosaic',
			'viewer-mosaic-position',
			'viewer-mosaic-layout',
			'viewer-mosaic-count',
			'viewer-mosaic-gap',
			'viewer-mosaic-opacity',
			'viewer-mosaic-background',
			'viewer-mosaic-corner-radius',

			// Lightbox-specific overrides.
			'lightbox-fullscreen',
			'lightbox-max-width',
			'lightbox-max-height',
			'lightbox-source-width',
			'lightbox-source-height',
			'lightbox-image-fit',
			'lightbox-background-color',
			'lightbox-backdrop-color',
			'lightbox-corner-radius',
			'lightbox-controls-color',
			'lightbox-video-controls-color',
			'lightbox-video-controls-autohide',
			'lightbox-show-navigation',
			'lightbox-show-link-button',
			'lightbox-show-download-button',
			'lightbox-slideshow',
			'lightbox-slideshow-delay',
			'lightbox-slideshow-autoresume',
			'lightbox-info-top',
			'lightbox-info-top-secondary',
			'lightbox-info-bottom',
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

			// Fullscreen-specific overrides.
			'fullscreen-max-width',
			'fullscreen-max-height',
			'fullscreen-source-width',
			'fullscreen-source-height',
			'fullscreen-image-fit',
			'fullscreen-background-color',
			'fullscreen-corner-radius',
			'fullscreen-controls-color',
			'fullscreen-video-controls-color',
			'fullscreen-video-controls-autohide',
			'fullscreen-show-navigation',
			'fullscreen-show-link-button',
			'fullscreen-show-download-button',
			'fullscreen-slideshow',
			'fullscreen-slideshow-delay',
			'fullscreen-slideshow-autoresume',
			'fullscreen-info-top',
			'fullscreen-info-top-secondary',
			'fullscreen-info-bottom',
			'fullscreen-info-font-size',
			'fullscreen-info-font-family',
			'fullscreen-info-font-color',
			'fullscreen-mosaic',
			'fullscreen-mosaic-position',
			'fullscreen-mosaic-layout',
			'fullscreen-mosaic-count',
			'fullscreen-mosaic-gap',
			'fullscreen-mosaic-opacity',
			'fullscreen-mosaic-background',
			'fullscreen-mosaic-corner-radius',

			// Technical settings.
			'album-cache-refresh',
			'download-size-warning',

			// Accepted obsolete names, kept deterministic for legacy shortcodes.
			'cache-refresh',
			'viewer-display-max-width',
			'viewer-display-max-height',
			'fullscreen-display-max-width',
			'fullscreen-display-max-height',
			'gallery-page-bottom',
			'show-title',
			'show-counter',
			'info-top-1',
			'info-top-2',
			'fullscreen-show-title',
			'fullscreen-show-counter',
			'fullscreen-info-top-1',
			'fullscreen-info-top-2',
			'expanded-toggle',
			'expanded-max-width',
			'expanded-max-height',
			'expanded-source-width',
			'expanded-source-height',
			'expanded-image-fit',
			'expanded-background-color',
			'expanded-corner-radius',
			'expanded-controls-color',
			'expanded-video-controls-color',
			'expanded-video-controls-autohide',
			'expanded-show-navigation',
			'expanded-show-link-button',
			'expanded-show-download-button',
			'expanded-slideshow',
			'expanded-slideshow-delay',
			'expanded-slideshow-autoresume',
			'expanded-info-top',
			'expanded-info-top-secondary',
			'expanded-info-bottom',
			'expanded-info-font-size',
			'expanded-info-font-family',
			'expanded-info-font-color',
			'expanded-mosaic',
			'expanded-mosaic-position',
			'expanded-mosaic-layout',
			'expanded-mosaic-count',
			'expanded-mosaic-gap',
			'expanded-mosaic-opacity',
			'expanded-mosaic-background',
			'expanded-mosaic-corner-radius',
		);
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
