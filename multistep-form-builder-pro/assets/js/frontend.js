/* global wp */
( function () {
	function initForm( wrapper ) {
		const formData = JSON.parse( wrapper.dataset.form );
		const formEl = wrapper.querySelector( '.msfbp-form-el' );
		const pages = [];
		let currentPage = 0;

		formEl.querySelectorAll( '.msfbp-field' ).forEach( ( field ) => {
			const page = parseInt( field.dataset.page, 10 ) || 0;
			pages[ page ] = pages[ page ] || [];
			pages[ page ].push( field );
		} );

		function updateProgress() {
			const progress = wrapper.querySelector( '.msfbp-progress' );
			if ( ! progress ) return;
			const percent = Math.round( ( ( currentPage + 1 ) / pages.length ) * 100 );
			progress.innerHTML = 'Step ' + ( currentPage + 1 ) + ' of ' + pages.length + ' (' + percent + '%)';
		}

		function showPage( index ) {
			currentPage = index;
			pages.forEach( ( fields, idx ) => {
				fields.forEach( ( field ) => {
					field.style.display = idx === currentPage ? 'block' : 'none';
				} );
			} );
			updateProgress();
		}

		function validateCurrentPage() {
			let valid = true;
			pages[ currentPage ].forEach( ( field ) => {
				const input = field.querySelector( 'input, select, textarea' );
				if ( input && ! input.checkValidity() ) {
					valid = false;
					input.reportValidity();
				}
			} );
			return valid;
		}

		function collectValues() {
			const values = {};
			formEl.querySelectorAll( 'input, select, textarea' ).forEach( ( input ) => {
				if ( input.type === 'checkbox' ) {
					const existing = values[ input.name ] || [];
					if ( input.checked ) existing.push( input.value );
					values[ input.name ] = existing;
				} else if ( input.type === 'radio' ) {
					if ( input.checked ) values[ input.name ] = input.value;
				} else {
					values[ input.name ] = input.value;
				}
			} );
			return values;
		}

		function evaluateConditionals() {
			const values = collectValues();

			formEl.querySelectorAll( '.msfbp-field[data-conditional]' ).forEach( ( field ) => {
				const conditional = JSON.parse( field.dataset.conditional );
				const match = window.MSFBP_Conditional.evaluate( conditional, values );
				field.style.display = match ? 'block' : 'none';
			} );

			updateCalculations( values );
		}

		function updateCalculations( values ) {
			formEl.querySelectorAll( '[data-formula]' ).forEach( ( input ) => {
				const formula = input.dataset.formula || '';
				let expression = formula.replace( /{field:([^}]+)}/g, function ( match, slug ) {
					return parseFloat( values[ slug ] || 0 );
				} );
				expression = expression.replace( /[^0-9\\.\\+\\-\\*\\/\\(\\) ]/g, '' );
				try {
					// eslint-disable-next-line no-eval
					const result = expression ? eval( expression ) : 0;
					input.value = result;
				} catch ( e ) {
					input.value = '';
				}
			} );
		}

		formEl.addEventListener( 'change', evaluateConditionals );
		wrapper.querySelector( '.msfbp-next' ).addEventListener( 'click', function () {
			if ( validateCurrentPage() && currentPage < pages.length - 1 ) {
				showPage( currentPage + 1 );
			}
		} );
		wrapper.querySelector( '.msfbp-prev' ).addEventListener( 'click', function () {
			if ( currentPage > 0 ) {
				showPage( currentPage - 1 );
			}
		} );

		formEl.querySelectorAll( '.msfbp-add-row' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				const container = button.closest( '.msfbp-repeater' );
				const row = container.querySelector( '.msfbp-repeat-row' );
				const clone = row.cloneNode( true );
				const input = clone.querySelector( 'input' );
				if ( input ) input.value = '';
				container.insertBefore( clone, button );
			} );
		} );

		updateProgress();
		showPage( 0 );
		evaluateConditionals();
		updateCalculations( collectValues() );
	}

	window.MSFBP_Conditional = {
		evaluate: function ( conditions, values ) {
			if ( ! conditions || ! conditions.rules ) return true;
			const relation = conditions.relation === 'any' ? 'any' : 'all';
			const results = conditions.rules.map( ( rule ) => {
				const fieldVal = values[ rule.field ];
				switch ( rule.operator ) {
					case 'is': return String( fieldVal ) === String( rule.value );
					case 'is_not': return String( fieldVal ) !== String( rule.value );
					case 'contains': return Array.isArray( fieldVal ) ? fieldVal.includes( rule.value ) : String( fieldVal || '' ).indexOf( rule.value ) !== -1;
					default: return false;
				}
			} );
			return relation === 'any' ? results.some( Boolean ) : results.every( Boolean );
		},
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.msfbp-form' ).forEach( initForm );
	} );
}() );
