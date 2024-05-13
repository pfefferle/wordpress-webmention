import { registerBlockType } from '@wordpress/blocks';
import './style.scss';
import Edit from './edit';
import save from './save';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );


const { __ } = wp.i18n;
const { BlockControls } = wp.blockEditor;
const { DropdownMenu, MenuGroup, MenuItem, ToolbarGroup } = wp.components;

const Controls = (props) => {
	const { attributes, setAttributes } = props;
	const { theme } = attributes;

	const themes = [
		{
			key: '',
			label: __('Default', 'custom-domain'),
		},
		{
			key: 'dark',
			label: __('Dark', 'custom-domain'),
		},
		{
			key: 'retro',
			label: __('Retro', 'custom-domain'),
		},
		{
			key: 'vintage',
			label: __('Vintage', 'custom-domain'),
		},
	];

	return (
		<BlockControls group="other">
			<ToolbarGroup>
				<DropdownMenu
					icon={<span>{__('Theme', 'custom-domain')}</span>}
					label={__('Switch Theme', 'custom-domain')}
				>
					{({ onClose }) => (
						<MenuGroup>
							{themes.map((themeData) => {
								return (
									<MenuItem
										icon={theme === themeData.key ? 'yes' : ''}
										onClick={() => {
											setAttributes({ theme: themeData.key });
											onClose();
										}}
									>
										{themeData.label}
									</MenuItem>
								);
							})}
						</MenuGroup>
					)}
				</DropdownMenu>
			</ToolbarGroup>
		</BlockControls>
	);
};

export default Controls;


const { createElement, Fragment } = wp.element
const { registerFormatType, toggleFormat } = wp.richText
const { RichTextToolbarButton, RichTextShortcut } = wp.blockEditor;

[
  {
    name: 'like',
    title: 'Like',
    character: 'heart',
	icon: 'heart'
  },
  {
    name: 'reply',
    title: 'Reply',
    character: ',',
	icon: 'admin-comments'
  }
].forEach(({ name, title, character, icon }) => {
  const type = `advanced/${name}`

  registerFormatType(type, {
    title,
    tagName: name,
    className: null,
    edit ({ isActive, value, onChange }) {
      const onToggle = () => onChange(toggleFormat(value, { type }))

      return (
        createElement(Fragment, null,
          createElement(RichTextShortcut, {
            type: 'primary',
            character,
            onUse: onToggle
          }),
          createElement(RichTextToolbarButton, {
            title,
            onClick: onToggle,
            isActive,
            shortcutType: 'primary',
            shortcutCharacter: character,
            className: `toolbar-button-with-text toolbar-button__advanced-${name}`,
			icon: icon
          })
        )
      )
    }
  })
})
