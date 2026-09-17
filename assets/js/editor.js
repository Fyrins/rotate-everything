/**
 * Rotate Everything - block editor integration.
 *
 * Plain ES5 against the global `wp` object. There is no build step, so the file
 * that ships is the file that can be read, and the diff of a release is the diff
 * of the source.
 *
 * Nothing here writes to the saved markup. The `rotation` attribute is declared
 * without a `source`, which keeps it in the block comment where block validation
 * does not look, and the transform is applied on the server at render time.
 */
( function ( wp, window ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.compose || ! wp.components || ! wp.blockEditor || ! wp.i18n ) {
		return;
	}

	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var Button = wp.components.Button;
	var addFilter = wp.hooks.addFilter;
	var __ = wp.i18n.__;
	var _x = wp.i18n._x;
	var sprintf = wp.i18n.sprintf;

	/**
	 * Used only if the inline script that carries the PHP configuration failed
	 * to print. It repeats the PHP defaults rather than inventing its own.
	 */
	var FALLBACK = {
		supportedBlocks: [ 'core/image' ],
		minAngle: -359,
		maxAngle: 359,
		step: 1,
		defaultAngle: 0
	};

	var provided = window.rotateEverythingSettings || {};

	/**
	 * The supported block types and the angle bounds are decided in PHP, which
	 * is also what the renderer reads. One list, one set of bounds, no drift.
	 */
	var config = {
		supportedBlocks: Array.isArray( provided.supportedBlocks ) ? provided.supportedBlocks : FALLBACK.supportedBlocks,
		minAngle: toInteger( provided.minAngle, FALLBACK.minAngle ),
		maxAngle: toInteger( provided.maxAngle, FALLBACK.maxAngle ),
		step: toInteger( provided.step, FALLBACK.step ),
		defaultAngle: toInteger( provided.defaultAngle, FALLBACK.defaultAngle )
	};

	/**
	 * Rounds a value to a whole number, or returns the fallback.
	 *
	 * @param {*}      value    Value to convert.
	 * @param {number} fallback Returned when the value is not a finite number.
	 * @return {number} Whole number.
	 */
	function toInteger( value, fallback ) {
		var number = parseFloat( value );

		return isFinite( number ) ? Math.round( number ) : fallback;
	}

	/**
	 * Tells whether a block type is offered the Rotation panel.
	 *
	 * @param {string} blockName Block name.
	 * @return {boolean} True when the block type is supported.
	 */
	function isSupported( blockName ) {
		return -1 !== config.supportedBlocks.indexOf( blockName );
	}

	/**
	 * Confines an angle to the configured range, mirroring the PHP side.
	 *
	 * @param {*} value Raw attribute value.
	 * @return {number} Angle in whole degrees.
	 */
	function normalizeAngle( value ) {
		var angle = toInteger( value, config.defaultAngle );

		if ( angle < config.minAngle ) {
			return config.minAngle;
		}

		if ( angle > config.maxAngle ) {
			return config.maxAngle;
		}

		return angle;
	}

	/**
	 * Builds the inspector panel for one block.
	 *
	 * @param {Object}   attributes    Attributes of the block being edited.
	 * @param {Function} setAttributes Setter for that same block.
	 * @return {Object} Panel element.
	 */
	function renderPanel( attributes, setAttributes ) {
		var angle = normalizeAngle( attributes.rotation );

		return createElement(
			PanelBody,
			{
				/* translators: Title of the block inspector panel that tilts a block. */
				title: _x( 'Rotation', 'block inspector panel title', 'rotate-everything' ),
				initialOpen: 0 !== angle
			},
			createElement( RangeControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Angle', 'rotate-everything' ),
				value: angle,
				min: config.minAngle,
				max: config.maxAngle,
				step: config.step,
				help: sprintf(
					/* translators: 1: Lowest angle offered, in degrees. 2: Highest angle offered, in degrees. */
					__( 'Between %1$d° and %2$d°. A rotated block keeps its original space on the page, so its corners overlap neighbouring content, and a full-width block at a large angle can push the page sideways. Rotating text also makes that text harder to read.', 'rotate-everything' ),
					config.minAngle,
					config.maxAngle
				),
				onChange: function ( value ) {
					setAttributes( { rotation: normalizeAngle( value ) } );
				}
			} ),
			createElement(
				Button,
				{
					variant: 'secondary',
					onClick: function () {
						setAttributes( { rotation: 0 } );
					}
				},
				/* translators: Button that sets the rotation angle back to zero. */
				_x( 'Reset', 'button label', 'rotate-everything' )
			)
		);
	}

	/**
	 * Declares the attribute on supported block types.
	 */
	addFilter(
		'blocks.registerBlockType',
		'rotate-everything/attribute',
		function ( blockSettings, blockName ) {
			if ( ! isSupported( blockName ) ) {
				return blockSettings;
			}

			var attributes = blockSettings.attributes || {};

			if ( attributes.rotation ) {
				return blockSettings;
			}

			return Object.assign( {}, blockSettings, {
				attributes: Object.assign( {}, attributes, {
					rotation: {
						type: 'number',
						default: config.defaultAngle
					}
				} )
			} );
		}
	);

	/**
	 * Adds the Rotation panel to the inspector.
	 *
	 * The panel reads the props of the block being edited. Asking the data store
	 * for the selected block instead would show the angle of the outer block
	 * whenever the edited one is nested, and the panel would quietly lie.
	 */
	addFilter(
		'editor.BlockEdit',
		'rotate-everything/inspector',
		createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( ! isSupported( props.name ) ) {
					return createElement( BlockEdit, props );
				}

				return createElement(
					Fragment,
					null,
					createElement( BlockEdit, props ),
					createElement(
						InspectorControls,
						null,
						renderPanel( props.attributes || {}, props.setAttributes )
					)
				);
			};
		}, 'withRotationControls' )
	);

	/**
	 * Previews the rotation in the editor canvas.
	 *
	 * The transform goes on the wrapper the editor already renders around the
	 * block, which is the counterpart of the element the server rotates. Adding
	 * an element of our own would slide between the block and its resize
	 * handles and its selection outline.
	 */
	addFilter(
		'editor.BlockListBlock',
		'rotate-everything/preview',
		createHigherOrderComponent( function ( BlockListBlock ) {
			return function ( props ) {
				if ( ! isSupported( props.name ) ) {
					return createElement( BlockListBlock, props );
				}

				var attributes = props.attributes || ( props.block && props.block.attributes ) || {};
				var angle = normalizeAngle( attributes.rotation );

				if ( 0 === angle ) {
					return createElement( BlockListBlock, props );
				}

				var wrapperProps = Object.assign( {}, props.wrapperProps );

				wrapperProps.style = Object.assign( {}, wrapperProps.style, {
					transform: 'rotate(' + angle + 'deg)',
					transformOrigin: '50% 50%'
				} );

				return createElement( BlockListBlock, Object.assign( {}, props, { wrapperProps: wrapperProps } ) );
			};
		}, 'withRotationPreview' )
	);
} )( window.wp, window );
