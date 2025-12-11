import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { CheckboxControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';


const EditorPlugin = () => {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	// Return null if meta is not available (e.g., CPT without 'custom-fields' support).
	if ( ! meta ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="webmention"
			title={ __( 'Webmentions', 'webmention' ) }
		>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __( 'Disable incoming', 'webmention' ) }
				help={ __( 'Do not accept incoming Webmentions for this post.', 'webmention' ) }
				checked={ meta.webmentions_disabled || false }
				onChange={ ( value ) => {
					setMeta( { ...meta, webmentions_disabled: value } );
				} }
			/>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __( 'Disable outgoing', 'webmention' ) }
				help={ __( 'Do not send Webmentions for this post.', 'webmention' ) }
				checked={ meta.webmentions_disabled_pings || false }
				onChange={ ( value ) => {
					setMeta( { ...meta, webmentions_disabled_pings: value } );
				} }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'webmention-editor-plugin', { render: EditorPlugin } );
