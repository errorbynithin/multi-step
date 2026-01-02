/* global wp, MSFBP_Admin */
( function ( wp, apiFetch ) {
	const { createElement: el, render, useState } = wp.element;
	const { Button, TextControl, PanelBody, SelectControl, CheckboxControl, Modal, TextareaControl, Notice } = wp.components;

	const fieldTypes = [
		{ label: 'Single Line Text', value: 'text' },
		{ label: 'Paragraph', value: 'textarea' },
		{ label: 'Number', value: 'number' },
		{ label: 'Email', value: 'email' },
		{ label: 'Phone', value: 'phone' },
		{ label: 'URL', value: 'url' },
		{ label: 'Select', value: 'select' },
		{ label: 'Multi-select', value: 'multi-select' },
		{ label: 'Radio', value: 'radio' },
		{ label: 'Checkbox', value: 'checkbox' },
		{ label: 'Date', value: 'date' },
		{ label: 'Time', value: 'time' },
		{ label: 'File Upload', value: 'file' },
		{ label: 'Hidden', value: 'hidden' },
		{ label: 'HTML/Section', value: 'html' },
		{ label: 'Page Break', value: 'page_break' },
		{ label: 'Name', value: 'name' },
		{ label: 'Address', value: 'address' },
		{ label: 'Consent', value: 'consent' },
		{ label: 'Calculation', value: 'calculation' },
		{ label: 'Repeater', value: 'repeater' },
	];

	const defaultField = ( type = 'text' ) => ( {
		id: Date.now(),
		type,
		label: 'New Field',
		slug: 'field_' + Math.random().toString( 36 ).substring( 2, 9 ),
		required: false,
		page_index: 0,
		settings: {
			choices: [],
			conditional: null,
		},
	} );

	const ChoiceBulkModal = ( { isOpen, onClose, onApply } ) => {
		const [ text, setText ] = useState( '' );
		const parse = () => {
			const lines = text.split( '\n' ).filter( ( l ) => l.trim() );
			const choices = lines.map( ( line ) => {
				const parts = line.split( '|' );
				const label = parts[ 0 ].trim();
				const value = parts[ 1 ] ? parts[ 1 ].trim() : label.toLowerCase().replace( /\s+/g, '_' );
				return { label, value };
			} );
			onApply( choices );
		};

		if ( ! isOpen ) {
			return null;
		}

		return el(
			Modal,
			{ title: 'Bulk Add Choices', onRequestClose: onClose },
			el( TextareaControl, {
				label: 'Enter one per line (Label|Value or Label)',
				value: text,
				onChange: setText,
				rows: 8,
			} ),
			el(
				'div',
				{ className: 'msfbp-modal-actions' },
				el( Button, { isSecondary: true, onClick: onClose }, 'Cancel' ),
				el( Button, { isPrimary: true, onClick: parse }, 'Apply' )
			)
		);
	};

	const ConditionalBuilder = ( { value, onChange } ) => {
		const relation = ( value && value.relation ) || 'all';
		const rules = ( value && value.rules ) || [];

		const updateRule = ( index, key, val ) => {
			const next = rules.slice();
			next[ index ] = Object.assign( {}, next[ index ], { [ key ]: val } );
			onChange( { relation: relation, rules: next } );
		};

		return el(
			PanelBody,
			{ title: 'Conditional Logic', initialOpen: false },
			el( SelectControl, {
				label: 'Show field if',
				value: relation,
				options: [
					{ label: 'All conditions match', value: 'all' },
					{ label: 'Any condition matches', value: 'any' },
				],
				onChange: function ( val ) {
					onChange( { relation: val, rules: rules } );
				},
			} ),
			rules.map( function ( rule, idx ) {
				return el(
					'div',
					{ key: idx, className: 'msfbp-rule' },
					el( TextControl, {
						label: 'Field Slug',
						value: rule.field,
						onChange: function ( v ) {
							updateRule( idx, 'field', v );
						},
					} ),
					el( SelectControl, {
						label: 'Operator',
						value: rule.operator,
						options: [
							{ label: 'Is', value: 'is' },
							{ label: 'Is Not', value: 'is_not' },
							{ label: 'Contains', value: 'contains' },
							{ label: 'Greater Than', value: 'greater' },
							{ label: 'Less Than', value: 'less' },
						],
						onChange: function ( v ) {
							updateRule( idx, 'operator', v );
						},
					} ),
					el( TextControl, {
						label: 'Value',
						value: rule.value || '',
						onChange: function ( v ) {
							updateRule( idx, 'value', v );
						},
					} ),
					el(
						Button,
						{
							isLink: true,
							isDestructive: true,
							onClick: function () {
								onChange( {
									relation: relation,
									rules: rules.filter( function ( r, i ) {
										return i !== idx;
									} ),
								} );
							},
						},
						'Remove'
					)
				);
			} ),
			el(
				Button,
				{
					isSecondary: true,
					onClick: function () {
						onChange( {
							relation: relation,
							rules: rules.concat( [ { field: '', operator: 'is', value: '' } ] ),
						} );
					},
				},
				'Add Condition'
			)
		);
	};

	const FieldEditor = ( { field, onChange, onRemove, onMoveUp, onMoveDown } ) => {
		const [ bulkOpen, setBulkOpen ] = useState( false );
		const update = ( key, val ) => onChange( Object.assign( {}, field, { [ key ]: val } ) );
		const updateSettings = ( key, val ) => update( 'settings', Object.assign( {}, field.settings, { [ key ]: val } ) );

		return el(
			'div',
			{ className: 'msfbp-field-card' },
			el(
				'div',
				{ className: 'msfbp-field-header' },
				el( 'strong', null, field.label ),
				el(
					Button,
					{ isLink: true, isDestructive: true, onClick: onRemove },
					'Delete'
				),
				el(
					'div',
					{ className: 'msfbp-move' },
					el( Button, { isSecondary: true, onClick: onMoveUp, icon: 'arrow-up-alt2' } ),
					el( Button, { isSecondary: true, onClick: onMoveDown, icon: 'arrow-down-alt2' } )
				)
			),
			el( TextControl, {
				label: 'Label',
				value: field.label,
				onChange: function ( v ) {
					update( 'label', v );
				},
			} ),
			el( TextControl, {
				label: 'Slug',
				value: field.slug,
				onChange: function ( v ) {
					update( 'slug', v );
				},
			} ),
			el( SelectControl, {
				label: 'Type',
				value: field.type,
				options: fieldTypes,
				onChange: function ( v ) {
					update( 'type', v );
				},
			} ),
			el( CheckboxControl, {
				label: 'Required',
				checked: field.required,
				onChange: function ( v ) {
					update( 'required', v );
				},
			} ),
			[ 'select', 'multi-select', 'radio', 'checkbox' ].indexOf( field.type ) > -1 &&
				el(
					PanelBody,
					{ title: 'Choices', initialOpen: true },
					el(
						Button,
						{ isSecondary: true, onClick: function () { setBulkOpen( true ); } },
						'Bulk Add'
					),
					field.settings.choices.map( function ( choice, idx ) {
						return el(
							'div',
							{ key: idx, className: 'msfbp-choice-row' },
							el( TextControl, {
								label: 'Label',
								value: choice.label,
								onChange: function ( v ) {
									const choices = field.settings.choices.slice();
									choices[ idx ] = Object.assign( {}, choices[ idx ], { label: v } );
									updateSettings( 'choices', choices );
								},
							} ),
							el( TextControl, {
								label: 'Value',
								value: choice.value,
								onChange: function ( v ) {
									const choices = field.settings.choices.slice();
									choices[ idx ] = Object.assign( {}, choices[ idx ], { value: v } );
									updateSettings( 'choices', choices );
								},
							} ),
							el(
								Button,
								{
									isLink: true,
									isDestructive: true,
									onClick: function () {
										updateSettings(
											'choices',
											field.settings.choices.filter( function ( c, i ) {
												return i !== idx;
											} )
										);
									},
								},
								'Remove'
							)
						);
					} ),
					el(
						Button,
						{
							isSecondary: true,
							onClick: function () {
								updateSettings(
									'choices',
									field.settings.choices.concat( [ { label: 'Option', value: 'option' } ] )
								);
							},
						},
						'Add Choice'
					)
				),
			field.type === 'calculation' &&
				el( TextareaControl, {
					label: 'Formula (use {field:slug} tokens)',
					value: field.settings.formula || '',
					onChange: function ( v ) {
						updateSettings( 'formula', v );
					},
				} ),
			el( ConditionalBuilder, {
				value: field.settings.conditional,
				onChange: function ( val ) {
					updateSettings( 'conditional', val );
				},
			} ),
			el( ChoiceBulkModal, {
				isOpen: bulkOpen,
				onClose: function () { setBulkOpen( false ); },
				onApply: function ( choices ) {
					updateSettings( 'choices', choices );
					setBulkOpen( false );
				},
			} )
		);
	};

	const BuilderApp = () => {
		const sample = MSFBP_Admin.sample || {};
		const baseForm = {
			id: sample.id || 0,
			name: sample.name || 'Untitled Form',
			status: sample.status || 'draft',
			settings: sample.settings || {},
			fields: sample.fields || [],
		};

		const [ form, setForm ] = useState( baseForm );
		const [ notice, setNotice ] = useState( '' );

		const addField = () => setForm( Object.assign( {}, form, { fields: form.fields.concat( [ defaultField() ] ) } ) );
		const save = () => {
			apiFetch( {
				url: MSFBP_Admin.restUrl + '/forms',
				method: 'POST',
				headers: { 'X-WP-Nonce': MSFBP_Admin.restNonce },
				body: JSON.stringify( form ),
			} ).then( function ( res ) {
				setForm( Object.assign( {}, form, { id: res.id } ) );
				setNotice( 'Form saved.' );
			} ).catch( function () {
				setNotice( 'Error saving form.' );
			} );
		};

		return el(
			'div',
			{ className: 'msfbp-builder' },
			notice &&
				el( Notice, { status: 'success', onRemove: function () { setNotice( '' ); } }, notice ),
			el( TextControl, {
				label: 'Form Name',
				value: form.name,
				onChange: function ( v ) {
					setForm( Object.assign( {}, form, { name: v } ) );
				},
			} ),
			el(
				'div',
				{ className: 'msfbp-fields' },
				form.fields.map( function ( field, idx ) {
					return el( FieldEditor, {
						key: field.id,
						field: field,
						onChange: function ( updated ) {
							const next = form.fields.slice();
							next[ idx ] = updated;
							setForm( Object.assign( {}, form, { fields: next } ) );
						},
						onRemove: function () {
							setForm( Object.assign( {}, form, { fields: form.fields.filter( function ( f ) { return f.id !== field.id; } ) } ) );
						},
						onMoveUp: function () {
							if ( idx === 0 ) {
								return;
							}
							const next = form.fields.slice();
							const tmp = next[ idx - 1 ];
							next[ idx - 1 ] = next[ idx ];
							next[ idx ] = tmp;
							setForm( Object.assign( {}, form, { fields: next } ) );
						},
						onMoveDown: function () {
							if ( idx === form.fields.length - 1 ) {
								return;
							}
							const next = form.fields.slice();
							const tmp = next[ idx + 1 ];
							next[ idx + 1 ] = next[ idx ];
							next[ idx ] = tmp;
							setForm( Object.assign( {}, form, { fields: next } ) );
						},
					} );
				} )
			),
			el(
				'div',
				{ className: 'msfbp-actions' },
				el( Button, { isSecondary: true, onClick: addField }, 'Add Field' ),
				el( Button, { isPrimary: true, onClick: save }, 'Save Form' )
			)
		);
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		const root = document.getElementById( 'msfbp-builder-root' );
		if ( root ) {
			render( el( BuilderApp, null ), root );
		}
	} );
}( window.wp, wp.apiFetch ));
