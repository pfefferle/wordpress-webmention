/**
 * RSVP Rich Text Format
 *
 * Adds <data class="p-rsvp" value="yes|maybe|no|interested">text</data> markup
 * for microformats2 RSVP responses to events.
 */
import { registerFormatType, applyFormat, removeFormat, getActiveFormat } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { Popover, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check, close, help, starEmpty, cancelCircleFilled } from '@wordpress/icons';

import './editor.scss';

const FORMAT_NAME = 'webmention/rsvp';

const RSVP_VALUES = [
	{
		value: 'yes',
		label: __( 'Yes', 'webmention' ),
		icon: check,
	},
	{
		value: 'no',
		label: __( 'No', 'webmention' ),
		icon: close,
	},
	{
		value: 'maybe',
		label: __( 'Maybe', 'webmention' ),
		icon: help,
	},
	{
		value: 'interested',
		label: __( 'Interested', 'webmention' ),
		icon: starEmpty,
	},
];

/**
 * Get current RSVP value from format
 */
function getCurrentRsvpValue( value ) {
	const format = getActiveFormat( value, FORMAT_NAME );
	if ( format?.attributes?.value ) {
		return format.attributes.value;
	}
	if ( format?.unregisteredAttributes?.value ) {
		return format.unregisteredAttributes.value;
	}
	return '';
}

/**
 * RSVP Format Edit Component
 */
const RsvpFormatEdit = ( { isActive, value, onChange } ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const currentValue = getCurrentRsvpValue( value );

	const currentItem = RSVP_VALUES.find( ( v ) => v.value === currentValue );
	const buttonTitle = currentItem
		? `RSVP: ${ currentItem.label }`
		: 'RSVP';

	return (
		<>
			<RichTextToolbarButton
				icon="calendar-alt"
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
									onChange( removeFormat( value, FORMAT_NAME ) );
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
	title: 'RSVP',
	tagName: 'data',
	className: 'p-rsvp',
	attributes: {
		value: 'value',
	},
	edit: RsvpFormatEdit,
} );
