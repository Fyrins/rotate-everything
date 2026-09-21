/**
 * Rotate Everything - block editor integration.
 *
 * Nothing here writes to the saved markup. The `rotation` attribute is declared
 * without a `source`, which keeps it in the block comment where block validation
 * does not look, and the transform is applied on the server at render time.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, Button } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * Used only if the inline script carrying the PHP configuration failed to
 * print. It repeats the PHP defaults rather than inventing its own.
 *
 * @type {Object}
 */
const FALLBACK = {
	supportedBlocks: [ 'core/image' ],
	minAngle: -359,
	maxAngle: 359,
	step: 1,
	defaultAngle: 0,
};

/**
 * Rounds a value to a whole number, or returns the fallback.
 *
 * @param {*}      value    Value to convert.
 * @param {number} fallback Returned when the value is not a finite number.
 * @return {number} Whole number.
 */
function toInteger( value, fallback ) {
	const number = parseFloat( value );

	return Number.isFinite( number ) ? Math.round( number ) : fallback;
}

const provided = window.rotateEverythingSettings || {};

/**
 * The supported block types and the angle bounds are decided in PHP, which is
 * also what the renderer reads. One list, one set of bounds, no drift.
 *
 * @type {Object}
 */
const config = {
	supportedBlocks: Array.isArray( provided.supportedBlocks )
		? provided.supportedBlocks
		: FALLBACK.supportedBlocks,
	minAngle: toInteger( provided.minAngle, FALLBACK.minAngle ),
	maxAngle: toInteger( provided.maxAngle, FALLBACK.maxAngle ),
	step: toInteger( provided.step, FALLBACK.step ),
	defaultAngle: toInteger( provided.defaultAngle, FALLBACK.defaultAngle ),
};

/**
 * Tells whether a block type is offered the Rotation panel.
 *
 * @param {string} blockName Block name.
 * @return {boolean} True when the block type is supported.
 */
function isSupported( blockName ) {
	return config.supportedBlocks.includes( blockName );
}

/**
 * Confines an angle to the configured range, mirroring the PHP side.
 *
 * @param {*} value Raw attribute value.
 * @return {number} Angle in whole degrees.
 */
function normalizeAngle( value ) {
	const angle = toInteger( value, config.defaultAngle );

	if ( angle < config.minAngle ) {
		return config.minAngle;
	}

	if ( angle > config.maxAngle ) {
		return config.maxAngle;
	}

	return angle;
}

/**
 * The Rotation panel for one block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Attributes of the block being edited.
 * @param {Function} props.setAttributes Setter for that same block.
 * @return {Element} Panel element.
 */
function RotationPanel( { attributes, setAttributes } ) {
	const angle = normalizeAngle( attributes.rotation );

	return (
		<PanelBody
			/* translators: Title of the block inspector panel that tilts a block. */
			title={ _x(
				'Rotation',
				'block inspector panel title',
				'rotate-everything'
			) }
			initialOpen={ 0 !== angle }
		>
			<RangeControl
				__nextHasNoMarginBottom
				label={ __( 'Angle', 'rotate-everything' ) }
				value={ angle }
				min={ config.minAngle }
				max={ config.maxAngle }
				step={ config.step }
				help={ sprintf(
					/* translators: 1: Lowest angle offered, in degrees. 2: Highest angle offered, in degrees. */
					__(
						'Between %1$d° and %2$d°. A rotated block keeps its original space on the page, so its corners overlap neighbouring content, and a full-width block at a large angle can push the page sideways. Rotating text also makes that text harder to read.',
						'rotate-everything'
					),
					config.minAngle,
					config.maxAngle
				) }
				onChange={ ( value ) =>
					setAttributes( { rotation: normalizeAngle( value ) } )
				}
			/>
			<Button
				variant="secondary"
				onClick={ () => setAttributes( { rotation: 0 } ) }
			>
				{
					/* translators: Button that sets the rotation angle back to zero. */
					_x( 'Reset', 'button label', 'rotate-everything' )
				}
			</Button>
		</PanelBody>
	);
}

/**
 * Declares the attribute on supported block types.
 */
addFilter(
	'blocks.registerBlockType',
	'rotate-everything/attribute',
	( blockSettings, blockName ) => {
		if ( ! isSupported( blockName ) ) {
			return blockSettings;
		}

		const attributes = blockSettings.attributes || {};

		if ( attributes.rotation ) {
			return blockSettings;
		}

		return {
			...blockSettings,
			attributes: {
				...attributes,
				rotation: {
					type: 'number',
					default: config.defaultAngle,
				},
			},
		};
	}
);

/**
 * Adds the Rotation panel to the inspector.
 *
 * The panel reads the props of the block being edited. Asking the data store for
 * the selected block instead would show the angle of the outer block whenever
 * the edited one is nested, and the panel would quietly lie.
 *
 * BlockEdit is rendered as-is, with no element wrapped around it: an added
 * wrapper slides between the block and its resize handles and its selection
 * outline.
 */
addFilter(
	'editor.BlockEdit',
	'rotate-everything/inspector',
	createHigherOrderComponent(
		( BlockEdit ) => ( props ) => {
			if ( ! isSupported( props.name ) ) {
				return <BlockEdit { ...props } />;
			}

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<RotationPanel
							attributes={ props.attributes || {} }
							setAttributes={ props.setAttributes }
						/>
					</InspectorControls>
				</>
			);
		},
		'withRotationControls'
	)
);

/**
 * Previews the rotation in the editor canvas.
 *
 * The transform goes on the wrapper the editor already renders around the block,
 * which is the counterpart of the element the server rotates.
 */
addFilter(
	'editor.BlockListBlock',
	'rotate-everything/preview',
	createHigherOrderComponent(
		( BlockListBlock ) => ( props ) => {
			if ( ! isSupported( props.name ) ) {
				return <BlockListBlock { ...props } />;
			}

			const attributes =
				props.attributes ||
				( props.block && props.block.attributes ) ||
				{};
			const angle = normalizeAngle( attributes.rotation );

			if ( 0 === angle ) {
				return <BlockListBlock { ...props } />;
			}

			const wrapperProps = {
				...props.wrapperProps,
				style: {
					...( props.wrapperProps && props.wrapperProps.style ),
					transform: `rotate(${ angle }deg)`,
					transformOrigin: '50% 50%',
				},
			};

			return (
				<BlockListBlock { ...props } wrapperProps={ wrapperProps } />
			);
		},
		'withRotationPreview'
	)
);
