/**
 * Form Steps for Contact Form 7 - example preview only.
 *
 * Loaded only on the admin's example preview page, never on a real site. It
 * answers Contact Form 7's requests in the browser in the same format as the
 * server, so Review, Next, Back and Send can be tried without saving or
 * sending anything. Required and email fields are checked like Contact Form 7.
 */
( function () {
	'use strict';

	var text = window.svfsDemo || {};
	var realFetch = window.fetch ? window.fetch.bind( window ) : null;

	function reply( body ) {
		return Promise.resolve(
			new window.Response( JSON.stringify( body ), {
				status: 200,
				headers: { 'Content-Type': 'application/json' },
			} )
		);
	}

	function filled( data, name ) {
		return data.getAll( name ).concat( data.getAll( name + '[]' ) ).some( function ( value ) {
			return 'string' === typeof value ? '' !== value.trim() : !! ( value && value.size );
		} );
	}

	function check( form, data, only ) {
		var invalid = [];
		form.querySelectorAll( '.wpcf7-form-control-wrap[data-name]' ).forEach( function ( wrap ) {
			var name = wrap.getAttribute( 'data-name' );
			if ( only && -1 === only.indexOf( name ) ) {
				return;
			}
			var control = wrap.querySelector( '.wpcf7-form-control' );
			if ( ! control ) {
				return;
			}
			var required = control.classList.contains( 'wpcf7-validates-as-required' ) || 'true' === control.getAttribute( 'aria-required' );
			var value = String( data.get( name ) || '' ).trim();
			if ( required && ! filled( data, name ) ) {
				invalid.push( { field: name, message: text.required, idref: null, error_id: '' } );
			} else if ( value && control.classList.contains( 'wpcf7-validates-as-email' ) && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
				invalid.push( { field: name, message: text.email, idref: null, error_id: '' } );
			}
		} );
		return invalid;
	}

	function feedback( body ) {
		var data = body instanceof window.FormData ? body : new window.FormData();
		var unit = String( data.get( '_wpcf7_unit_tag' ) || '' );
		var form = document.querySelector( 'input[name="_wpcf7_unit_tag"][value="' + unit.replace( /"/g, '' ) + '"]' );
		form = form ? form.form : document.querySelector( '.wpcf7 form' );
		var phase = String( data.get( '_svfs_phase' ) || '' );
		var only = 'step' === phase ? String( data.get( '_svfs_fields' ) || '' ).split( ',' ).map( function ( name ) {
			return name.trim();
		} ).filter( Boolean ) : null;

		var invalid = form ? check( form, data, only ) : [];
		if ( invalid.length ) {
			return reply( { status: 'validation_failed', message: text.invalid, invalid_fields: invalid, into: '#' + unit } );
		}
		if ( 'confirm' === phase || 'step' === phase ) {
			return reply( { status: 'svfs_' + phase, message: '', invalid_fields: [], into: '#' + unit } );
		}
		return reply( { status: 'mail_sent', message: text.sent, invalid_fields: [], into: '#' + unit } );
	}

	window.fetch = function ( input, init ) {
		var url = 'string' === typeof input ? input : ( input && input.url ) || '';
		if ( -1 !== url.indexOf( 'contact-form-7/v1/contact-forms/' ) || -1 !== url.indexOf( 'contact-form-7%2Fv1%2Fcontact-forms' ) ) {
			if ( /\/feedback\/schema/.test( url ) || /feedback%2Fschema/.test( url ) ) {
				// Leave Contact Form 7's live field checks off in the demo.
				return new Promise( function () {} );
			}
			if ( /\/refill/.test( url ) || /refill/.test( url ) ) {
				return reply( {} );
			}
			if ( /feedback/.test( url ) ) {
				return feedback( init && init.body );
			}
		}
		return realFetch ? realFetch( input, init ) : Promise.reject( new Error( 'fetch unavailable' ) );
	};
}() );
