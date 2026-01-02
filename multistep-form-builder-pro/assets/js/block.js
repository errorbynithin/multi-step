( function ( blocks, element, components ) {
	const { registerBlockType } = blocks;
	const { createElement: el } = element;
	const { TextControl } = components;

	registerBlockType( 'msfbp/form', {
		title: 'MultiStep Form',
		icon: 'feedback',
		category: 'widgets',
		attributes: {
			id: { type: 'number' },
		},
		edit: ( props ) => el(
			'div',
			{ className: 'msfbp-block' },
			el( TextControl, {
				label: 'Form ID',
				value: props.attributes.id || '',
				onChange: ( val ) => props.setAttributes( { id: parseInt( val, 10 ) || 0 } ),
			} )
		),
		save: () => null,
	} );
}( window.wp.blocks, window.wp.element, window.wp.components ));
