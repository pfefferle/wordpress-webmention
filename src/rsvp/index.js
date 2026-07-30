/**
 * RSVP Rich Text Format
 *
 * Adds <data class="p-rsvp" value="yes|maybe|no|interested">text</data> markup
 * for microformats2 RSVP responses to events.
 */
import {
	registerFormatType,
	applyFormat,
	removeFormat,
} from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { Popover, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { calendar, cancelCircleFilled } from '@wordpress/icons';

import './editor.scss';
import {
	FORMAT_NAME,
	RSVP_VALUES,
	getCurrentRsvpValue,
	getRsvpButtonTitle,
} from './rsvp';

/**
 * RSVP Format Edit Component
 *
 * @param {Object}   root0          Component props.
 * @param {boolean}  root0.isActive Whether the format is active on the selection.
 * @param {Object}   root0.value    Rich text value.
 * @param {Function} root0.onChange Change handler for the rich text value.
 * @return {Element} The RSVP toolbar control.
 */
const RsvpFormatEdit = ( { isActive, value, onChange } ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const currentValue = getCurrentRsvpValue( value );
	const buttonTitle = getRsvpButtonTitle( currentValue );

	return (
		<>
			<RichTextToolbarButton
				icon={ calendar }
				title={ buttonTitle }
				onClick={ () => setIsOpen( ! isOpen ) }
				isActive={ isActive }
			/>
			{ isOpen && (
				<Popover
					className="webmention-rsvp-popover"
					position="bottom center"
					onClose={ () => setIsOpen( false ) }
				>
					<div className="webmention-rsvp-popover__buttons">
						{ RSVP_VALUES.map( ( rsvp ) => (
							<Button
								key={ rsvp.value }
								icon={ rsvp.icon }
								label={ rsvp.label }
								showTooltip
								isPressed={ currentValue === rsvp.value }
								onClick={ () => {
									onChange(
										applyFormat( value, {
											type: FORMAT_NAME,
											attributes: {
												value: rsvp.value,
											},
										} )
									);
									setIsOpen( false );
								} }
							/>
						) ) }
						{ isActive && (
							<Button
								icon={ cancelCircleFilled }
								label={ __( 'Remove', 'webmention' ) }
								showTooltip
								onClick={ () => {
									onChange(
										removeFormat( value, FORMAT_NAME )
									);
									setIsOpen( false );
								} }
							/>
						) }
					</div>
				</Popover>
			) }
		</>
	);
};

/**
 * Register the RSVP format type
 */
registerFormatType( FORMAT_NAME, {
	title: __( 'RSVP', 'webmention' ),
	tagName: 'data',
	className: 'p-rsvp',
	attributes: {
		value: 'value',
	},
	edit: RsvpFormatEdit,
} );
