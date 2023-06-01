( function ( $, rwmb, i18n ) {
	'use strict';

	class Validation {
		constructor( formSelector ) {
			this.$form = $( formSelector );
			this.validationElements = this.$form.find( '.rwmb-validation' );
			this.showAsterisks();
			this.getSettings();
		}

		init() {
			this.$form
				// Update underlying textarea before submit.
				// Don't use submitHandler() because form can be submitted via Ajax on the front end.
				.on( 'submit', function () {
					if ( typeof tinyMCE !== 'undefined' ) {
						tinyMCE.triggerSave();
					}
				} )
				.validate( this.settings );
		}

		showAsterisks() {
			this.validationElements.each( function () {
				var data = $( this ).data( 'validation' );

				$.each( data.rules, function ( k, v ) {
					if ( !v[ 'required' ] ) {
						return;
					}
					var $el = $( '[name="' + k + '"]' );
					if ( !$el.length ) {
						return;
					}
					$el.closest( '.rwmb-input' ).siblings( '.rwmb-label' ).find( 'label' ).append( '<span class="rwmb-required">*</span>' );
				} );
			} );
		}

		getSettings() {
			this.settings = {
				ignore: ':not(.rwmb-media,.rwmb-image_select,.rwmb-wysiwyg,.rwmb-color,.rwmb-map,.rwmb-osm,.rwmb-switch,[class|="rwmb"])',
				errorPlacement: function ( error, element ) {
					error.appendTo( element.closest( '.rwmb-input' ) );
				},
				errorClass: 'rwmb-error',
				errorElement: 'p',
				invalidHandler: this.invalidHandler.bind( this )
			};

			// Gather all validation rules.
			var that = this;
			this.validationElements.each( function () {
				$.extend( true, that.settings, $( this ).data( 'validation' ) );
			} );
		}

		invalidHandler() {
			this.showMessage();
			// Group field will automatically expand and show an error warning when collapsing
			for ( var i = 0; i < this.$form.data( 'validator' ).errorList.length; i++ ) {
				$( '#' + this.$form.data( 'validator' ).errorList[ i ].element.id ).closest( '.rwmb-group-collapsed' ).removeClass( 'rwmb-group-collapsed' );
			}
			// Custom event for showing error fields inside tabs/hidden divs. Use setTimeout() to run after error class is added to inputs.
			var that = this;
			setTimeout( function () {
				that.$form.trigger( 'after_validate' );
			}, 200 );
		}

		showMessage() {
			// Re-enable the submit ( publish/update ) button and hide the ajax indicator
			$( '#publish' ).removeClass( 'button-primary-disabled' );
			$( '#ajax-loading' ).attr( 'style', '' );
			$( '#rwmb-validation-message' ).remove();
			this.$form.before( '<div id="rwmb-validation-message" class="notice notice-error is-dismissible"><p>' + i18n.message + '</p></div>' );
		}
	};

	class GutenbergValidation extends Validation {
		init() {
			var that = this,
				editor = wp.data.dispatch( 'core/editor' ),
				savePost = editor.savePost; // Reference original method.

			if ( that.settings ) {
				that.$form.validate( that.settings );
			}

			// Change the editor method.
			editor.savePost = function ( object ) {
				// Bypass the validation when previewing in Gutenberg.
				if ( typeof object === 'object' && object.isPreview ) {
					savePost( object );
					return;
				}

				// Must call savePost() here instead of in submitHandler() because the form has inline onsubmit callback.
				if ( that.$form.valid() ) {
					return savePost( object );
				}
			};
		}

		showMessage() {
			wp.data.dispatch( 'core/notices' ).createErrorNotice( i18n.message, {
				id: 'meta-box-validation',
				isDismissible: true
			} );
		}
	};

	// Run on document ready.
	function init() {
		// Overwrite function staticRules
		$.validator.staticRules = function ( element ) {
			var rules = {},
				validator = $.data( element.form, "validator" );

			// Not rules validate
			if ( validator.settings.rules === null || Object.keys( validator.settings.rules ).length === 0 ) {
				return rules;
			}

			// Field hidden not valid
			if ( element.type === 'hidden' ) {
				return rules;
			}
			// Get basename of input name
			const $nameInput = element.name.match( /^(.+?)(?:\[\d+\]|(?:\[\]))?$/ );

			// Validate Input type file and have clone
			if ( element.type === 'file' && $( element ).closest( '.rwmb-clone' ).length > 0 ) {
				const $input = $( element ).closest( '.rwmb-input' );
				const $nameInputClone = $input.find( '*[value="' + $nameInput[ 1 ] + '"]' ).attr( 'name' ).match( /^(.+?)(?:\[\d+\]|(?:\[\]))?$/ );
				if ( validator.settings.rules[ $nameInputClone[ 1 ] ] ) {
					// Set message for element					
					validator.settings.messages[ element.name ] = validator.settings.messages[ $nameInputClone[ 1 ] ];
					// Set Rule for element
					return $.validator.normalizeRule( validator.settings.rules[ $nameInputClone[ 1 ] ] ) || {};
				}

				return rules;
			}

			// Validate other input
			const inputNameList = $( element.form ).find( '*[name^="' + $nameInput[ 1 ] + '"]' );
			if ( inputNameList.length > 0 ) {
				// Set message for element					
				validator.settings.messages[ element.name ] = validator.settings.messages[ $nameInput[ 1 ] ];
				// Set Rule for element
				return $.validator.normalizeRule( validator.settings.rules[ $nameInput[ 1 ] ] ) || {};
			}

			return rules;
		};

		if ( rwmb.isGutenberg ) {
			var advanced = new GutenbergValidation( '.metabox-location-advanced' ),
				normal = new GutenbergValidation( '.metabox-location-normal' ),
				side = new GutenbergValidation( '.metabox-location-side' );

			side.init();
			normal.init();
			advanced.init();
			return;
		}

		// Edit post, edit term, edit user, front-end form.
		var $forms = $( '#post, #edittag, #your-profile, .rwmb-form' );
		$forms.each( function () {
			var form = new Validation( this );
			form.init();
		} );
	};

	rwmb.$document
		.on( 'mb_ready', init );

} )( jQuery, rwmb, rwmbValidation );
