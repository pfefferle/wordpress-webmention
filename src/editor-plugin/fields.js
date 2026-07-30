/**
 * Editor Plugin fields
 *
 * Per-post Webmention settings, rendered as checkboxes in the document panel and
 * bound to post meta. Kept in a separate module so the configuration and the
 * meta helpers can be unit tested without rendering the panel (which is a
 * Slot/Fill that only mounts inside the editor).
 */
import { __ } from '@wordpress/i18n';

/**
 * Per-post Webmention settings fields.
 *
 * Each field maps a checkbox to a post meta key.
 */
export const WEBMENTION_FIELDS = [
	{
		metaKey: 'webmentions_disabled',
		label: __( 'Disable incoming', 'webmention' ),
		help: __(
			'Do not accept incoming Webmentions for this post.',
			'webmention'
		),
	},
	{
		metaKey: 'webmentions_disabled_pings',
		label: __( 'Disable outgoing', 'webmention' ),
		help: __( 'Do not send Webmentions for this post.', 'webmention' ),
	},
];

/**
 * Read a boolean field value from post meta.
 *
 * @param {?Object} meta    Post meta object.
 * @param {string}  metaKey Meta key to read.
 * @return {boolean} The field value as a boolean.
 */
export function getFieldValue( meta, metaKey ) {
	return Boolean( meta?.[ metaKey ] );
}

/**
 * Return updated meta with a single field changed, without mutating the input.
 *
 * @param {Object}  meta    Post meta object.
 * @param {string}  metaKey Meta key to change.
 * @param {boolean} value   New value.
 * @return {Object} A new meta object with the field updated.
 */
export function setFieldValue( meta, metaKey, value ) {
	return { ...meta, [ metaKey ]: value };
}
