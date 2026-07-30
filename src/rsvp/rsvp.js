/**
 * RSVP Rich Text Format logic
 *
 * Adds <data class="p-rsvp" value="yes|maybe|no|interested">text</data> markup
 * for microformats2 RSVP responses to events.
 */
import { getActiveFormat } from '@wordpress/rich-text';
import { __, sprintf } from '@wordpress/i18n';
import { check, close, help, starEmpty } from '@wordpress/icons';

export const FORMAT_NAME = 'webmention/rsvp';

export const RSVP_VALUES = [
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
 *
 * @param {Object} value Rich text value.
 * @return {string} Current RSVP value, or an empty string.
 */
export function getCurrentRsvpValue( value ) {
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
 * Build the toolbar button title for the current RSVP value.
 *
 * @param {string} currentValue Active RSVP value, or an empty string.
 * @return {string} Localized button title.
 */
export function getRsvpButtonTitle( currentValue ) {
	const currentItem = RSVP_VALUES.find( ( v ) => v.value === currentValue );
	if ( ! currentItem ) {
		return __( 'RSVP', 'webmention' );
	}

	return sprintf(
		/* translators: %s: RSVP response label (e.g. Yes, No, Maybe, Interested). */
		__( 'RSVP: %s', 'webmention' ),
		currentItem.label
	);
}
