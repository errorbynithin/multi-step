/* global wp, MSFBP_Admin */
( function ( wp, apiFetch ) {
	const { render, useState } = wp.element;
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

		if ( ! isOpen ) return null;
		return (
			<Modal title="Bulk Add Choices" onRequestClose={ onClose }>
				<TextareaControl
					label="Enter one per line (Label|Value or Label)"
					value={ text }
					onChange={ setText }
					rows={ 8 }
				/>
				<div className="msfbp-modal-actions">
					<Button isSecondary onClick={ onClose }>Cancel</Button>
					<Button isPrimary onClick={ parse }>Apply</Button>
				</div>
			</Modal>
		);
	};

	const ConditionalBuilder = ( { value, onChange } ) => {
		const relation = value?.relation || 'all';
		const rules = value?.rules || [];
		const updateRule = ( index, key, val ) => {
			const next = [ ...rules ];
			next[ index ] = { ...next[ index ], [ key ]: val };
			onChange( { relation, rules: next } );
		};

		return (
			<PanelBody title="Conditional Logic" initialOpen={ false }>
				<SelectControl
					label="Show field if"
					value={ relation }
					options={ [
						{ label: 'All conditions match', value: 'all' },
						{ label: 'Any condition matches', value: 'any' },
					] }
					onChange={ ( val ) => onChange( { relation: val, rules } ) }
				/>
				{ rules.map( ( rule, idx ) => (
					<div key={ idx } className="msfbp-rule">
						<TextControl label="Field Slug" value={ rule.field } onChange={ ( v ) => updateRule( idx, 'field', v ) } />
						<SelectControl
							label="Operator"
							value={ rule.operator }
							options={ [
								{ label: 'Is', value: 'is' },
								{ label: 'Is Not', value: 'is_not' },
								{ label: 'Contains', value: 'contains' },
								{ label: 'Greater Than', value: 'greater' },
								{ label: 'Less Than', value: 'less' },
							] }
							onChange={ ( v ) => updateRule( idx, 'operator', v ) }
						/>
						<TextControl label="Value" value={ rule.value || '' } onChange={ ( v ) => updateRule( idx, 'value', v ) } />
						<Button isLink isDestructive onClick={ () => onChange( { relation, rules: rules.filter( ( r, i ) => i !== idx ) } ) }>Remove</Button>
					</div>
				) ) }
				<Button isSecondary onClick={ () => onChange( { relation, rules: [ ...rules, { field: '', operator: 'is', value: '' } ] } ) }>Add Condition</Button>
			</PanelBody>
		);
	};

	const FieldEditor = ( { field, onChange, onRemove, onMoveUp, onMoveDown } ) => {
		const [ bulkOpen, setBulkOpen ] = useState( false );
		const update = ( key, val ) => onChange( { ...field, [ key ]: val } );
		const updateSettings = ( key, val ) => update( 'settings', { ...field.settings, [ key ]: val } );
		return (
			<div className="msfbp-field-card">
				<div className="msfbp-field-header">
					<strong>{ field.label }</strong>
					<Button isLink isDestructive onClick={ onRemove }>Delete</Button>
					<div className="msfbp-move">
						<Button isSecondary onClick={ onMoveUp } icon="arrow-up-alt2" />
						<Button isSecondary onClick={ onMoveDown } icon="arrow-down-alt2" />
					</div>
				</div>
				<TextControl label="Label" value={ field.label } onChange={ ( v ) => update( 'label', v ) } />
				<TextControl label="Slug" value={ field.slug } onChange={ ( v ) => update( 'slug', v ) } />
				<SelectControl label="Type" value={ field.type } options={ fieldTypes } onChange={ ( v ) => update( 'type', v ) } />
				<CheckboxControl label="Required" checked={ field.required } onChange={ ( v ) => update( 'required', v ) } />

				{ [ 'select', 'multi-select', 'radio', 'checkbox' ].includes( field.type ) && (
					<PanelBody title="Choices" initialOpen={ true }>
						<Button isSecondary onClick={ () => setBulkOpen( true ) }>Bulk Add</Button>
						{ field.settings.choices.map( ( choice, idx ) => (
							<div key={ idx } className="msfbp-choice-row">
								<TextControl
									label="Label"
									value={ choice.label }
									onChange={ ( v ) => {
										const choices = [ ...field.settings.choices ];
										choices[ idx ] = { ...choices[ idx ], label: v };
										updateSettings( 'choices', choices );
									} }
								/>
								<TextControl
									label="Value"
									value={ choice.value }
									onChange={ ( v ) => {
										const choices = [ ...field.settings.choices ];
										choices[ idx ] = { ...choices[ idx ], value: v };
										updateSettings( 'choices', choices );
									} }
								/>
								<Button isLink isDestructive onClick={ () => updateSettings( 'choices', field.settings.choices.filter( ( c, i ) => i !== idx ) ) }>Remove</Button>
							</div>
						) ) }
						<Button isSecondary onClick={ () => updateSettings( 'choices', [ ...field.settings.choices, { label: 'Option', value: 'option' } ] ) }>Add Choice</Button>
					</PanelBody>
				) }

				{ field.type === 'calculation' && (
					<TextareaControl
						label="Formula (use {field:slug} tokens)"
						value={ field.settings.formula || '' }
						onChange={ ( v ) => updateSettings( 'formula', v ) }
					/>
				) }

				<ConditionalBuilder value={ field.settings.conditional } onChange={ ( val ) => updateSettings( 'conditional', val ) } />
				<ChoiceBulkModal
					isOpen={ bulkOpen }
					onClose={ () => setBulkOpen( false ) }
					onApply={ ( choices ) => {
						updateSettings( 'choices', choices );
						setBulkOpen( false );
					} }
				/>
			</div>
		);
	};

	const BuilderApp = () => {
		const container = document.getElementById( 'msfbp-builder-root' );
		const template = container?.dataset?.formTemplate ? JSON.parse( container.dataset.formTemplate ) : {};
		const baseForm = {
			id: template.id || 0,
			name: template.name || 'Untitled Form',
			status: template.status || 'draft',
			settings: template.settings || {},
			fields: template.fields || [],
		};

		const [ form, setForm ] = useState( baseForm );
		const [ notice, setNotice ] = useState( '' );

		const addField = () => setForm( { ...form, fields: [ ...form.fields, defaultField() ] } );
		const save = () => {
			apiFetch( {
				path: MSFBP_Admin.restUrl + '/forms',
				method: 'POST',
				headers: { 'X-WP-Nonce': MSFBP_Admin.restNonce },
				body: JSON.stringify( form ),
			} ).then( ( res ) => {
				setForm( { ...form, id: res.id } );
				setNotice( 'Form saved.' );
			} ).catch( () => setNotice( 'Error saving form.' ) );
		};

		return (
			<div className="msfbp-builder">
				{ notice && <Notice status="success" onRemove={ () => setNotice( '' ) }>{ notice }</Notice> }
				<TextControl label="Form Name" value={ form.name } onChange={ ( v ) => setForm( { ...form, name: v } ) } />
				<div className="msfbp-fields">
					{ form.fields.map( ( field, idx ) => (
						<FieldEditor
							key={ field.id }
							field={ field }
							onChange={ ( updated ) => {
								const next = [ ...form.fields ];
								next[ idx ] = updated;
								setForm( { ...form, fields: next } );
							} }
							onRemove={ () => setForm( { ...form, fields: form.fields.filter( ( f ) => f.id !== field.id ) } ) }
							onMoveUp={ () => {
								if ( idx === 0 ) return;
								const next = [ ...form.fields ];
								[ next[ idx - 1 ], next[ idx ] ] = [ next[ idx ], next[ idx - 1 ] ];
								setForm( { ...form, fields: next } );
							} }
							onMoveDown={ () => {
								if ( idx === form.fields.length - 1 ) return;
								const next = [ ...form.fields ];
								[ next[ idx + 1 ], next[ idx ] ] = [ next[ idx ], next[ idx + 1 ] ];
								setForm( { ...form, fields: next } );
							} }
						/>
					) ) }
				</div>
				<div className="msfbp-actions">
					<Button isSecondary onClick={ addField }>Add Field</Button>
					<Button isPrimary onClick={ save }>Save Form</Button>
				</div>
			</div>
		);
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = document.getElementById( 'msfbp-builder-root' );
		if ( root ) {
			render( <BuilderApp />, root );
		}
	} );
} )( window.wp, wp.apiFetch );
