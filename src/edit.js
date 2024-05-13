import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { RichText } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { className, attributes: attr, setAttributes } ) {
	const onChangeContent = ( newContent ) => {
		setAttributes( { content: newContent } );
	};

	return (
		<div { ...useBlockProps() }>
			<TextControl
				label="This post is a reply to the following URL:"
				value={ className }
				onChange={ ( value ) => onChangeContent( value ) }
			/>

			<RichText
				className={ className }
				tagName="p"
				style={ { textAlign: attr.alignment } }
				value={ attr.content }
			/>
		</div>
	);
}

import Controls from './index.js'; // Make sure that the import path is correct

const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;

const allowedBlocks = ['core/paragraph']; // Enable control to existing Group block

/**
 * Add custom attribute
 */
function addAttributes(settings) {
	// Check if attributes exists and compare the block name
	if (typeof settings.attributes !== 'undefined' && allowedBlocks.includes(settings.name)) {
		settings.attributes = Object.assign(settings.attributes, {
			theme: {
				type: 'string',
				default: '',
			},
		});
	}

	return settings;
}
wp.hooks.addFilter('blocks.registerBlockType', 'example/add-atttibutes', addAttributes);

/**
 * Add Custom Block Controls
 */
const addBlockControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		console.log('BlockEdit', props);
		const { name, isSelected } = props;

		if (!allowedBlocks.includes(name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<Fragment>
				{isSelected && <Controls {...props} />}
				<BlockEdit {...props} />
			</Fragment>
		);
	};
}, 'addBlockControls');

wp.hooks.addFilter('editor.BlockEdit', 'example/add-block-controls', addBlockControls);
