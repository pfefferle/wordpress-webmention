import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { CheckboxControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';

import { WEBMENTION_FIELDS, getFieldValue, setFieldValue } from './fields';

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
			{ WEBMENTION_FIELDS.map( ( field ) => (
				<CheckboxControl
					key={ field.metaKey }
					__nextHasNoMarginBottom
					label={ field.label }
					help={ field.help }
					checked={ getFieldValue( meta, field.metaKey ) }
					onChange={ ( value ) =>
						setMeta( setFieldValue( meta, field.metaKey, value ) )
					}
				/>
			) ) }
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'webmention-editor-plugin', { render: EditorPlugin } );
