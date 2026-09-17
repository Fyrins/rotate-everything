<?php
/**
 * Configuration values and the sanitizing that guards them.
 *
 * @package RotateEverything
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for every value the plugin can be configured with.
 *
 * Each getter applies its filter and then sanitizes whatever came back. A
 * filtered value is third-party input: it is never trusted, and it never
 * reaches a CSS declaration without being validated first. The same array is
 * handed to the editor script, so PHP and JavaScript cannot drift apart.
 */
final class Rotate_Everything_Settings {

	/**
	 * Lowest angle offered by default, in degrees.
	 *
	 * @var int
	 */
	const DEFAULT_MIN_ANGLE = -359;

	/**
	 * Highest angle offered by default, in degrees.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_ANGLE = 359;

	/**
	 * Default increment of the angle control, in degrees.
	 *
	 * @var int
	 */
	const DEFAULT_STEP = 1;

	/**
	 * Angle used when a block carries no usable value.
	 *
	 * @var int
	 */
	const DEFAULT_ANGLE = 0;

	/**
	 * Default transition duration, as a CSS time value.
	 *
	 * @var string
	 */
	const DEFAULT_TRANSITION_DURATION = '0.2s';

	/**
	 * Only block types listed here get the Rotation panel.
	 *
	 * @var string[]
	 */
	const DEFAULT_SUPPORTED_BLOCKS = array( 'core/image' );

	/**
	 * Accepts a CSS time value and nothing else: an integer or decimal number
	 * followed by `s` or `ms`. Scientific notation, `calc()` and anything
	 * carrying a semicolon or a brace is rejected.
	 *
	 * @var string
	 */
	const CSS_TIME_PATTERN = '/^[0-9]*\.?[0-9]+m?s$/';

	/**
	 * Returns the block types the Rotation panel is offered on.
	 *
	 * The default is deliberately a single block type. Rotating running text
	 * hurts legibility, so widening this list is an explicit decision the site
	 * owner makes, not something the plugin does on their behalf.
	 *
	 * @return string[] List of block names, possibly empty.
	 */
	public static function get_supported_blocks() {
		/**
		 * Filters the block types that can be rotated.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $blocks Block names, for example `core/image`.
		 */
		$blocks = apply_filters( 'rotate_everything_supported_blocks', self::DEFAULT_SUPPORTED_BLOCKS );

		if ( ! is_array( $blocks ) ) {
			return self::DEFAULT_SUPPORTED_BLOCKS;
		}

		$sanitized = array();

		foreach ( $blocks as $block_name ) {
			if ( ! is_string( $block_name ) ) {
				continue;
			}

			$block_name = trim( $block_name );

			if ( '' === $block_name ) {
				continue;
			}

			$sanitized[] = $block_name;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Returns the inclusive angle range offered by the control.
	 *
	 * Both bounds are resolved together so an inverted range can be rejected
	 * as a whole rather than silently producing a control with no valid value.
	 *
	 * @return array{min:int,max:int} Angle bounds in degrees.
	 */
	public static function get_angle_range() {
		/**
		 * Filters the lowest angle the control offers.
		 *
		 * @since 1.0.0
		 *
		 * @param int $min_angle Lowest angle, in degrees.
		 */
		$min = apply_filters( 'rotate_everything_min_angle', self::DEFAULT_MIN_ANGLE );

		/**
		 * Filters the highest angle the control offers.
		 *
		 * @since 1.0.0
		 *
		 * @param int $max_angle Highest angle, in degrees.
		 */
		$max = apply_filters( 'rotate_everything_max_angle', self::DEFAULT_MAX_ANGLE );

		$min = self::to_degrees( $min, self::DEFAULT_MIN_ANGLE );
		$max = self::to_degrees( $max, self::DEFAULT_MAX_ANGLE );

		if ( $min >= $max ) {
			return array(
				'min' => self::DEFAULT_MIN_ANGLE,
				'max' => self::DEFAULT_MAX_ANGLE,
			);
		}

		return array(
			'min' => $min,
			'max' => $max,
		);
	}

	/**
	 * Returns the increment of the angle control, in whole degrees.
	 *
	 * Angles are integers throughout the plugin: the rendered declaration is
	 * always a whole number of degrees, which keeps the value trivially safe
	 * to write into CSS. A filter returning a fraction is rounded up to 1.
	 *
	 * @return int Increment, at least 1.
	 */
	public static function get_step() {
		/**
		 * Filters the increment of the angle control.
		 *
		 * @since 1.0.0
		 *
		 * @param int $step Increment, in degrees.
		 */
		$step = apply_filters( 'rotate_everything_step', self::DEFAULT_STEP );

		$step = self::to_degrees( $step, self::DEFAULT_STEP );
		$step = abs( $step );

		return $step < 1 ? 1 : $step;
	}

	/**
	 * Returns the angle applied to a block that carries no usable value.
	 *
	 * @return int Angle in degrees, inside the configured range.
	 */
	public static function get_default_angle() {
		/**
		 * Filters the angle used when a block carries no usable value.
		 *
		 * @since 1.0.0
		 *
		 * @param int $default_angle Angle, in degrees.
		 */
		$angle = apply_filters( 'rotate_everything_default_angle', self::DEFAULT_ANGLE );

		return self::clamp( self::to_degrees( $angle, self::DEFAULT_ANGLE ) );
	}

	/**
	 * Returns the transition duration as a validated CSS time value.
	 *
	 * The value is concatenated into a stylesheet, so it is matched against a
	 * strict pattern rather than escaped. A filter can return an empty string
	 * or zero to switch the transition off; anything unrecognisable falls back
	 * to the default instead of being passed through.
	 *
	 * @return string CSS time value, `0s` when the transition is switched off.
	 */
	public static function get_transition_duration() {
		/**
		 * Filters the duration of the front-end transform transition.
		 *
		 * @since 1.0.0
		 *
		 * @param string $duration CSS time value, for example `0.2s` or `200ms`.
		 */
		$duration = apply_filters( 'rotate_everything_transition_duration', self::DEFAULT_TRANSITION_DURATION );

		if ( ! is_scalar( $duration ) || is_bool( $duration ) ) {
			return self::DEFAULT_TRANSITION_DURATION;
		}

		$duration = trim( (string) $duration );

		if ( '' === $duration || '0' === $duration ) {
			return '0s';
		}

		if ( 1 !== preg_match( self::CSS_TIME_PATTERN, $duration ) ) {
			return self::DEFAULT_TRANSITION_DURATION;
		}

		return $duration;
	}

	/**
	 * Turns a raw block attribute into an angle that is safe to render.
	 *
	 * The value arrives from `$block['attrs']`, which is parsed out of the
	 * block comment and never validated by WordPress. It can therefore be a
	 * string, an array, a boolean, `null`, or a number far outside the range.
	 * Anything that is not a finite number falls back to the default angle.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return int Angle in degrees, inside the configured range.
	 */
	public static function sanitize_angle( $value ) {
		if ( ! is_scalar( $value ) || is_bool( $value ) || ! is_numeric( $value ) ) {
			return self::get_default_angle();
		}

		$angle = (float) $value;

		if ( ! is_finite( $angle ) ) {
			return self::get_default_angle();
		}

		return self::clamp( (int) round( $angle ) );
	}

	/**
	 * Returns the values the editor script needs, ready for wp_json_encode().
	 *
	 * @return array<string,mixed> Settings keyed with the names used in JavaScript.
	 */
	public static function get_settings_for_script() {
		$range = self::get_angle_range();

		return array(
			'supportedBlocks' => self::get_supported_blocks(),
			'minAngle'        => $range['min'],
			'maxAngle'        => $range['max'],
			'step'            => self::get_step(),
			'defaultAngle'    => self::get_default_angle(),
		);
	}

	/**
	 * Converts a filtered value to a whole number of degrees.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $fallback Value returned when $value is not a finite number.
	 * @return int Whole degrees.
	 */
	private static function to_degrees( $value, $fallback ) {
		if ( ! is_scalar( $value ) || is_bool( $value ) || ! is_numeric( $value ) ) {
			return $fallback;
		}

		$number = (float) $value;

		if ( ! is_finite( $number ) ) {
			return $fallback;
		}

		return (int) round( $number );
	}

	/**
	 * Confines an angle to the configured range.
	 *
	 * @param int $angle Angle in degrees.
	 * @return int Angle in degrees, inside the configured range.
	 */
	private static function clamp( $angle ) {
		$range = self::get_angle_range();

		if ( $angle < $range['min'] ) {
			return $range['min'];
		}

		if ( $angle > $range['max'] ) {
			return $range['max'];
		}

		return $angle;
	}
}
