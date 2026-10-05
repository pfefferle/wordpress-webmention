/*
 * @jsxRuntime classic
 * @jsx createElement
 */

/**
 * Webmention Form block.
 *
 * Rendered on the server by the same template as the classic comment form,
 * the editor only shows a static preview.
 */
import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

const Edit = () => {
	const blockProps = useBlockProps( { className: 'webmention-form' } );

	return (
		<div { ...blockProps }>
			<p className="webmention-form__source">
				<label htmlFor="webmention-source-preview">
					{ __(
						'Posted a response on your own website? Share the link here:',
						'webmention'
					) }
				</label>
				<input
					id="webmention-source-preview"
					className="webmention-source"
					type="url"
					placeholder="https://"
					disabled
				/>
				<small>
					{ __(
						'Your post must include a link to this post.',
						'webmention'
					) }
				</small>
			</p>
			<div className="webmention-form__submit">
				<button
					className="webmention-submit wp-element-button wp-block-button__link"
					type="button"
					disabled
				>
					{ __( 'Send Webmention', 'webmention' ) }
				</button>
				<details className="webmention-form__help">
					<summary>
						{ __( 'How does this work?', 'webmention' ) }
					</summary>
				</details>
			</div>
		</div>
	);
};

registerBlockType( metadata.name, {
	edit: Edit,
} );
