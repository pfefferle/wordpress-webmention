import {
	PluginDocumentSettingPanel,
	PluginPrePublishPanel,
} from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { CheckboxControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createInterpolateElement } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';

import { WEBMENTION_FIELDS, getFieldValue, setFieldValue } from './fields';
import { hasOnlyPlainLinks } from './links';

const EditorPlugin = () => {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
	// Only parse the content while the pre-publish sidebar is open.
	const showLinkHint = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return (
			editor.isPublishSidebarOpened() &&
			hasOnlyPlainLinks( editor.getEditedPostContent() )
		);
	}, [] );

	// Return null if meta is not available (e.g., CPT without 'custom-fields' support).
	if ( ! meta ) {
		return null;
	}

	return (
		<>
			<PluginDocumentSettingPanel
				name="webmention"
				title={ __( 'Webmentions', 'webmention' ) }
			>
				<div
					style={ {
						display: 'flex',
						flexDirection: 'column',
						gap: '16px',
					} }
				>
					{ WEBMENTION_FIELDS.map( ( field ) => (
						<CheckboxControl
							key={ field.metaKey }
							__nextHasNoMarginBottom
							label={ field.label }
							help={ createInterpolateElement( field.help, {
								code: <code />,
							} ) }
							checked={ getFieldValue( meta, field.metaKey ) }
							onChange={ ( value ) =>
								setMeta(
									setFieldValue( meta, field.metaKey, value )
								)
							}
						/>
					) ) }
				</div>
			</PluginDocumentSettingPanel>
			{ showLinkHint &&
				! getFieldValue( meta, 'webmentions_disabled_pings' ) && (
					<PluginPrePublishPanel
						name="webmention-nomention-reminder"
						title={ __( 'Webmentions', 'webmention' ) }
						initialOpen
					>
						<p>
							{ __(
								'Every site linked in this post will be notified via Webmention. In a link\'s settings you can say what the link means, for example a reply, like or bookmark, or check "Don\'t send a Webmention" to skip it.',
								'webmention'
							) }
						</p>
					</PluginPrePublishPanel>
				) }
		</>
	);
};

registerPlugin( 'webmention-editor-plugin', { render: EditorPlugin } );
