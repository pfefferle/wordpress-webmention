import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import * as React from 'react';

test.each( [ 'editor-plugin/plugin', 'rsvp/index' ] )(
	'%s loads and renders without a core JSX runtime',
	( entry ) => {
		let registration;
		const wp = {
			element: { ...React, useState: () => [ false, jest.fn() ] },
			i18n: { __: ( text ) => text, sprintf: ( text ) => text },
			primitives: { SVG: 'svg', Path: 'path' },
			components: {
				CheckboxControl: 'input',
				Popover: 'div',
				Button: 'button',
			},
			blockEditor: { RichTextToolbarButton: 'button' },
			editor: { PluginDocumentSettingPanel: 'section' },
			data: {
				useSelect: ( callback ) =>
					callback( () => ( { getCurrentPostType: () => 'post' } ) ),
			},
			coreData: { useEntityProp: () => [ {}, jest.fn() ] },
			plugins: {
				registerPlugin: ( name, settings ) => {
					registration = settings.render;
				},
			},
			richText: {
				getActiveFormat: () => undefined,
				registerFormatType: ( name, settings ) => {
					registration = settings.edit;
				},
			},
		};
		const buildPath = path.resolve( __dirname, '../../build', entry );
		const context = { window: { React, wp } };

		expect(
			fs.readFileSync( `${ buildPath }.asset.php`, 'utf8' )
		).not.toContain( 'react-jsx-runtime' );
		vm.runInNewContext(
			fs.readFileSync( `${ buildPath }.js`, 'utf8' ),
			context
		);
		expect( registration ).toEqual( expect.any( Function ) );
		expect(
			React.isValidElement(
				registration( {
					isActive: false,
					value: {},
					onChange: jest.fn(),
				} )
			)
		).toBe( true );
	}
);
