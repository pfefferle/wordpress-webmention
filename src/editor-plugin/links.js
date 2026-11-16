/**
 * Link inspection helpers for the pre-publish reminder.
 */
import { NOMENTION_CLASS, REACTION_CLASSES } from '../reaction-links/reactions';

/**
 * Check whether a link has been given a meaning or excluded from Webmentions.
 *
 * @param {HTMLAnchorElement} link Link element.
 * @return {boolean} Whether the link has a reaction or nomention marker.
 */
function isAnnotatedLink( link ) {
	const rels = ( link.getAttribute( 'rel' ) || '' )
		.toLowerCase()
		.split( /\s+/ );

	return (
		rels.includes( NOMENTION_CLASS ) ||
		[ NOMENTION_CLASS, ...REACTION_CLASSES ].some( ( cls ) =>
			link.classList.contains( cls )
		)
	);
}

/**
 * Whether the post content has links but none of them is annotated yet.
 *
 * @param {string} content Post content HTML.
 * @return {boolean} Whether the link settings hint is worth showing.
 */
export function hasOnlyPlainLinks( content ) {
	if ( ! content ) {
		return false;
	}

	const doc = new window.DOMParser().parseFromString( content, 'text/html' );
	const links = [ ...doc.querySelectorAll( 'a[href]' ) ];

	return links.length > 0 && ! links.some( isAnnotatedLink );
}
