<?php
/**
 * Asset registration for the editor and the front end.
 *
 * @package RotateEverything
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the editor script and the front-end stylesheet.
 *
 * The stylesheet is enqueued only once a rotated block has actually been
 * rendered, so a page without one carries no reference to it.
 *
 * It is registered lazily, at that same moment, rather than on a hook of its
 * own, because the order of `render_block` and `wp_enqueue_scripts` is not
 * fixed. With a block theme the template is rendered before `wp_head`, so
 * `render_block` runs first and a style registered on `wp_enqueue_scripts` does
 * not exist yet; with a classic theme the content is rendered from inside the
 * template, long after. Registering on demand makes the plugin indifferent to
 * which of the two it is dealing with: early enough and the stylesheet goes out
 * in the head, late enough and WordPress prints it in the footer.
 *
 * Either way nothing depends on the timing, because the rotation itself is an
 * inline declaration written into the markup.
 */
final class Rotate_Everything_Assets {

	/**
	 * Handle of the editor script.
	 *
	 * @var string
	 */
	const EDITOR_SCRIPT_HANDLE = 'rotate-everything-editor';

	/**
	 * Handle of the front-end stylesheet.
	 *
	 * @var string
	 */
	const FRONT_STYLE_HANDLE = 'rotate-everything';

	/**
	 * Custom property the stylesheet reads the transition duration from.
	 *
	 * Going through a custom property rather than overriding `transition`
	 * directly matters: an override printed after the stylesheet would
	 * otherwise outrank the reduced-motion rule and reinstate the animation
	 * for the very people who asked for it to stop.
	 *
	 * @var string
	 */
	const DURATION_CUSTOM_PROPERTY = '--rotate-everything-duration';

	/**
	 * Enqueues the front-end stylesheet, once, on demand.
	 *
	 * Called by the renderer at the moment it rotates something.
	 *
	 * @return void
	 */
	public static function enqueue_front_style() {
		if ( ! wp_style_is( self::FRONT_STYLE_HANDLE, 'registered' ) ) {
			self::register_front_style();
		}

		if ( wp_style_is( self::FRONT_STYLE_HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( self::FRONT_STYLE_HANDLE );
	}

	/**
	 * Registers the front-end stylesheet without enqueuing it.
	 *
	 * @return void
	 */
	private static function register_front_style() {
		wp_register_style(
			self::FRONT_STYLE_HANDLE,
			plugins_url( 'assets/css/rotate-everything.css', ROTATE_EVERYTHING_FILE ),
			array(),
			ROTATE_EVERYTHING_VERSION
		);

		$duration = Rotate_Everything_Settings::get_transition_duration();

		if ( Rotate_Everything_Settings::DEFAULT_TRANSITION_DURATION === $duration ) {
			return;
		}

		/*
		 * $duration has been matched against a CSS time pattern, so it holds
		 * digits, at most one dot and the unit. There is nothing left in it
		 * that could close the declaration and start another one.
		 */
		wp_add_inline_style(
			self::FRONT_STYLE_HANDLE,
			'.rotate-everything-rotated{' . self::DURATION_CUSTOM_PROPERTY . ':' . $duration . ';}'
		);
	}

	/**
	 * Path of the built editor script, relative to the plugin directory.
	 *
	 * Also the path the translation JSON file is named after: WordPress hashes
	 * this string, so moving the script means regenerating the JSON.
	 *
	 * @var string
	 */
	const EDITOR_SCRIPT_PATH = 'build/editor.js';

	/**
	 * Path of the asset file webpack writes next to the script.
	 *
	 * @var string
	 */
	const EDITOR_ASSET_PATH = 'build/editor.asset.php';

	/**
	 * Enqueues the editor script and hands it the PHP configuration.
	 *
	 * The dependency list and the cache-busting version are read from the file
	 * webpack writes next to the bundle, rather than kept by hand: a hand-written
	 * list drifts the first time an import changes, and the symptom is a script
	 * that loads before the package it needs.
	 *
	 * The build directory is absent from a fresh clone, since it is not in the
	 * repository. Bailing out quietly is deliberate; the plugin adds no admin
	 * notice, and CONTRIBUTING.md says to run `npm run build`.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets() {
		$asset_file = ROTATE_EVERYTHING_PATH . self::EDITOR_ASSET_PATH;

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		$dependencies = ( is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) )
			? $asset['dependencies']
			: array();
		$version      = ( is_array( $asset ) && isset( $asset['version'] ) && is_string( $asset['version'] ) )
			? $asset['version']
			: ROTATE_EVERYTHING_VERSION;

		wp_enqueue_script(
			self::EDITOR_SCRIPT_HANDLE,
			plugins_url( self::EDITOR_SCRIPT_PATH, ROTATE_EVERYTHING_FILE ),
			$dependencies,
			$version,
			true
		);

		/*
		 * The supported block types and the angle bounds are read from PHP so
		 * the editor and the renderer cannot disagree about them. Printed
		 * before the script rather than through wp_localize_script(), which
		 * exists for localization and carries l10n baggage this does not need.
		 */
		wp_add_inline_script(
			self::EDITOR_SCRIPT_HANDLE,
			'window.rotateEverythingSettings = ' . wp_json_encode( Rotate_Everything_Settings::get_settings_for_script() ) . ';',
			'before'
		);

		wp_set_script_translations(
			self::EDITOR_SCRIPT_HANDLE,
			'rotate-everything',
			ROTATE_EVERYTHING_PATH . 'languages'
		);
	}
}
