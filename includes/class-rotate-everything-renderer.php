<?php
/**
 * Server-side rendering of the rotation.
 *
 * @package RotateEverything
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns the `rotation` block attribute into a CSS transform at render time.
 *
 * This is the only place the rotation is ever written. The attribute is
 * declared without a `source`, so it lives in the block comment rather than in
 * the saved markup, and the markup in the database stays exactly what the
 * block's own save function produced. Deactivating the plugin therefore
 * invalidates nothing: the blocks simply stop being tilted.
 */
final class Rotate_Everything_Renderer {

	/**
	 * Class added to the element that carries the transform.
	 *
	 * @var string
	 */
	const ROTATED_CLASS = 'rotate-everything-rotated';

	/**
	 * Elements skipped when looking for the block's outermost rendering element.
	 *
	 * Block supports and third-party filters may prepend a `<style>` or a
	 * `<link>` to a block's markup, which would otherwise be mistaken for the
	 * block itself. The tag processor does not walk into the contents of
	 * `<style>` and `<script>`, so skipping them cannot land the cursor inside
	 * one of them.
	 *
	 * @var string[]
	 */
	const NON_RENDERING_TAGS = array( 'STYLE', 'SCRIPT', 'LINK', 'META' );

	/**
	 * Applies the rotation to a rendered block.
	 *
	 * @param string $block_content Rendered markup of the block.
	 * @param array  $block         Parsed block, including its attributes.
	 * @return string Markup, rotated when it should be and untouched otherwise.
	 */
	public static function filter_render_block( $block_content, $block ) {
		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		if ( ! is_array( $block ) || empty( $block['blockName'] ) || ! is_string( $block['blockName'] ) ) {
			return $block_content;
		}

		if ( ! in_array( $block['blockName'], Rotate_Everything_Settings::get_supported_blocks(), true ) ) {
			return $block_content;
		}

		/*
		 * Guards against the plugin surviving a core downgrade below 6.2.
		 * WordPress refuses to activate it there, but it can stay active
		 * across a rollback.
		 */
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$attributes = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : array();
		$raw_angle  = isset( $attributes['rotation'] ) ? $attributes['rotation'] : null;
		$angle      = Rotate_Everything_Settings::sanitize_angle( $raw_angle );

		if ( 0 === $angle ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( ! self::move_to_target( $processor ) ) {
			return $block_content;
		}

		self::add_class( $processor, self::ROTATED_CLASS );

		/*
		 * $angle is an integer confined to the configured range, and %d emits
		 * nothing but digits and a possible minus sign.
		 */
		self::append_declaration( $processor, 'transform', sprintf( 'rotate(%ddeg)', $angle ) );

		Rotate_Everything_Assets::enqueue_front_style();

		return $processor->get_updated_html();
	}

	/**
	 * Moves the cursor to the element the transform belongs on.
	 *
	 * The target is the block's outermost rendering element, which in document
	 * order is the first tag that is not one of the metadata elements listed in
	 * NON_RENDERING_TAGS. That element is the one whose layout box the browser
	 * reserves for the block, so rotating it rotates the block and nothing
	 * else.
	 *
	 * Looking for the first element carrying a `wp-block-` class was the
	 * alternative, and it is the more fragile one: a dynamic block that renders
	 * its own markup without calling get_block_wrapper_attributes() has no such
	 * class on its outer element, and the search would then descend into an
	 * inner block and tilt a fragment of the content instead of the block.
	 *
	 * @param WP_HTML_Tag_Processor $processor Processor positioned before the first tag.
	 * @return bool True when a usable element was found, false to change nothing.
	 */
	private static function move_to_target( WP_HTML_Tag_Processor $processor ) {
		while ( $processor->next_tag() ) {
			$tag_name = $processor->get_tag();

			if ( ! is_string( $tag_name ) ) {
				return false;
			}

			if ( in_array( $tag_name, self::NON_RENDERING_TAGS, true ) ) {
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * Adds a class to the current element without dropping the ones it has.
	 *
	 * Written with get_attribute() and set_attribute() rather than add_class(),
	 * which only arrived in WordPress 6.4 and would raise the plugin's floor
	 * for no gain.
	 *
	 * @param WP_HTML_Tag_Processor $processor  Processor positioned on an element.
	 * @param string                $class_name Class to add.
	 * @return void
	 */
	private static function add_class( WP_HTML_Tag_Processor $processor, $class_name ) {
		$existing = $processor->get_attribute( 'class' );

		if ( ! is_string( $existing ) || '' === trim( $existing ) ) {
			$processor->set_attribute( 'class', $class_name );

			return;
		}

		$classes = preg_split( '/\s+/', trim( $existing ) );

		if ( is_array( $classes ) && in_array( $class_name, $classes, true ) ) {
			return;
		}

		$processor->set_attribute( 'class', trim( $existing ) . ' ' . $class_name );
	}

	/**
	 * Appends a declaration to the element's inline style.
	 *
	 * The existing value is read, kept whole and rewritten with the new
	 * declaration after it. A missing trailing semicolon is supplied, and the
	 * old value is never parsed: splitting it would corrupt a legitimate
	 * `url(data:image/png;base64,...)` whose own semicolon means nothing to
	 * the cascade.
	 *
	 * Appending also settles what happens when the element already carries the
	 * same property: the later declaration wins, which is plain CSS cascade
	 * rather than a rule of the plugin's own invention.
	 *
	 * @param WP_HTML_Tag_Processor $processor Processor positioned on an element.
	 * @param string                $property  CSS property name.
	 * @param string                $value     CSS value, already validated.
	 * @return void
	 */
	private static function append_declaration( WP_HTML_Tag_Processor $processor, $property, $value ) {
		$declaration = $property . ':' . $value . ';';
		$existing    = $processor->get_attribute( 'style' );

		if ( ! is_string( $existing ) || '' === trim( $existing ) ) {
			$processor->set_attribute( 'style', $declaration );

			return;
		}

		$existing = rtrim( trim( $existing ), "; \t\n\r\0" );

		if ( '' === $existing ) {
			$processor->set_attribute( 'style', $declaration );

			return;
		}

		$processor->set_attribute( 'style', $existing . ';' . $declaration );
	}
}
