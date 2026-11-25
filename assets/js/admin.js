/**
 * Form Steps for Contact Form 7 - admin.
 * Instant-save switches on the forms overview, copy button, and
 * show/hide of dependent settings in the Steps tab.
 */
( function () {
	'use strict';

	var data = window.svfsAdmin || {};
	var i18n = data.i18n || {};

	function flash( element, message, isError ) {
		if ( ! element ) {
			return;
		}
		element.textContent = message;
		element.classList.toggle( 'is-error', !! isError );
		window.clearTimeout( element.svfsTimer );
		if ( ! isError ) {
			element.svfsTimer = window.setTimeout( function () {
				element.textContent = '';
			}, 2000 );
		}
	}

	/* Forms overview: save each switch at once. */
	function setupToggles() {
		var toggles = document.querySelectorAll( '.svfs-toggle' );
		if ( ! toggles.length || ! data.ajaxUrl ) {
			return;
		}
		document.querySelectorAll( '.svfs-nojs-save' ).forEach( function ( button ) {
			button.hidden = true;
		} );

		toggles.forEach( function ( toggle ) {
			toggle.addEventListener( 'change', function () {
				var row = toggle.closest( 'tr' );
				var status = row ? row.querySelector( '.svfs-saved' ) : null;
				var pill = row ? row.querySelector( '.svfs-pill' ) : null;
				var body = new window.FormData();
				body.append( 'action', 'svfs_toggle' );
				body.append( 'nonce', data.nonce );
				body.append( 'form_id', toggle.getAttribute( 'data-form' ) );
				body.append( 'on', toggle.checked ? '1' : '' );
				toggle.disabled = true;

				window.fetch( data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( result ) {
						if ( ! result || ! result.success ) {
							throw new Error( 'save failed' );
						}
						if ( pill ) {
							pill.textContent = result.data.live ? i18n.live : i18n.off;
							pill.classList.toggle( 'is-live', !! result.data.live );
							pill.classList.toggle( 'is-off', ! result.data.live );
						}
						flash( status, i18n.saved, false );
					} )
					.catch( function () {
						toggle.checked = ! toggle.checked;
						flash( status, i18n.failed, true );
					} )
					.then( function () {
						toggle.disabled = false;
					} );
			} );
		} );
	}

	/* Import & export: copy button. */
	function setupCopy() {
		document.querySelectorAll( '.svfs-copy' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var box = button.closest( 'section' );
				var area = box ? box.querySelector( '.svfs-export' ) : null;
				var status = box ? box.querySelector( '.svfs-saved' ) : null;
				if ( ! area ) {
					return;
				}
				var done = function () {
					flash( status, i18n.copied, false );
				};
				if ( window.navigator.clipboard && window.isSecureContext ) {
					window.navigator.clipboard.writeText( area.value ).then( done );
				} else {
					area.select();
					document.execCommand( 'copy' );
					done();
				}
			} );
		} );
	}

	/*
	 * Steps tab: blocks with data-svfs-when="input-name=value" show only for that choice;
	 * blocks with data-svfs-active-when stay visible but look inactive otherwise.
	 */
	function setupConditions() {
		var blocks = document.querySelectorAll( '[data-svfs-when], [data-svfs-active-when]' );
		if ( ! blocks.length ) {
			return;
		}
		function matches( rule ) {
			var parts = rule.split( '=' );
			var checked = document.querySelector( 'input[name="' + parts[ 0 ] + '"]:checked' );
			return !! checked && checked.value === parts[ 1 ];
		}
		function update() {
			blocks.forEach( function ( block ) {
				if ( block.hasAttribute( 'data-svfs-when' ) ) {
					block.hidden = ! matches( block.getAttribute( 'data-svfs-when' ) );
				} else {
					block.classList.toggle( 'is-inactive', ! matches( block.getAttribute( 'data-svfs-active-when' ) ) );
				}
			} );
		}
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.name && 0 === event.target.name.indexOf( 'svfs[' ) ) {
				update();
			}
		} );
		update();
	}

	/* "i" tips: click to pin open, Escape or a click elsewhere to close. */
	function setupTips() {
		var tips = document.querySelectorAll( '.svfs-tip' );
		if ( ! tips.length ) {
			return;
		}
		function close( tip, suppress ) {
			tip.classList.remove( 'is-open' );
			tip.classList.toggle( 'is-closed', !! suppress );
			tip.querySelector( '.svfs-tip-btn' ).setAttribute( 'aria-expanded', 'false' );
		}
		tips.forEach( function ( tip ) {
			var button = tip.querySelector( '.svfs-tip-btn' );
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var open = ! tip.classList.contains( 'is-open' );
				tips.forEach( function ( other ) {
					if ( other !== tip ) {
						close( other, false );
					}
				} );
				if ( open ) {
					tip.classList.add( 'is-open' );
					tip.classList.remove( 'is-closed' );
					button.setAttribute( 'aria-expanded', 'true' );
				} else {
					close( tip, true );
				}
			} );
			// A tip closed on purpose stays closed until the pointer leaves it.
			tip.addEventListener( 'mouseleave', function () {
				if ( ! tip.classList.contains( 'is-open' ) ) {
					tip.classList.remove( 'is-closed' );
				}
			} );
		} );
		document.addEventListener( 'click', function ( event ) {
			tips.forEach( function ( tip ) {
				if ( tip.classList.contains( 'is-open' ) && ! tip.contains( event.target ) ) {
					close( tip, false );
				}
			} );
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' !== event.key ) {
				return;
			}
			tips.forEach( function ( tip ) {
				if ( tip.classList.contains( 'is-open' ) || tip.contains( document.activeElement ) ) {
					close( tip, true );
				}
			} );
		} );
	}

	/*
	 * Rename-labels table: reads the form template in the Form tab and shows the
	 * label the summary table will use for each field. Mirrors the front end's
	 * label search on a rough rendering of the template, and updates while typing.
	 */
	var REQUIRED_MARK = /\s*[([（【]\s*(?:required|必須|必要|pflicht(?:feld)?|erforderlich|obligatoire|requis|obligatorio|requerido|obrigat[oó]rio)\s*[)\]）】]/gi;

	function humanize( name ) {
		var words = name.replace( /^your[-_]/i, '' ).replace( /[-_]+/g, ' ' ).trim();
		return words.charAt( 0 ).toUpperCase() + words.slice( 1 );
	}

	function tidy( label ) {
		return label
			.replace( /\s+/g, ' ' )
			.replace( REQUIRED_MARK, '' )
			.replace( /\s*必須$/, '' )
			.replace( /[\s*:：※]+$/, '' )
			.replace( /^[\s*※]+/, '' )
			.trim();
	}

	function nodeText( node ) {
		var clone = node.cloneNode( true );
		clone.querySelectorAll( '.svfs-x-field, .required, .screen-reader-text' ).forEach( function ( child ) {
			child.remove();
		} );
		return tidy( clone.textContent || '' );
	}

	function renderTemplate( template ) {
		var info = {};
		// Turn every [type name ...] tag into a marker element the label search can find.
		var html = template.replace( /\[\s*([a-zA-Z_][\w-]*\*?)\s+([a-zA-Z][\w:.-]*)([^\]]*)\]/g, function ( all, type, name, rest ) {
			var id = /(?:^|\s)id:([\w-]+)/.exec( rest );
			var placeholder = /placeholder\s+(?:watermark\s+)?"([^"]*)"/.exec( rest ) || /"([^"]*)"\s+(?:placeholder|watermark)/.exec( rest );
			info[ name ] = { placeholder: placeholder ? placeholder[ 1 ] : '' };
			return '<span class="svfs-x-field" data-name="' + name.replace( /"/g, '' ) + '"' + ( id ? ' data-id="' + id[ 1 ] + '"' : '' ) + '></span>';
		} );
		var doc = new window.DOMParser().parseFromString( '<div>' + html + '</div>', 'text/html' );
		return { root: doc.body.firstChild, info: info };
	}

	function findLabel( rendered, name ) {
		var root = rendered.root;
		var marker = null;
		root.querySelectorAll( '.svfs-x-field' ).forEach( function ( element ) {
			if ( ! marker && element.getAttribute( 'data-name' ) === name ) {
				marker = element;
			}
		} );
		if ( ! marker ) {
			return '';
		}
		var found = '';
		var id = marker.getAttribute( 'data-id' );
		if ( id ) {
			root.querySelectorAll( 'label[for]' ).forEach( function ( label ) {
				if ( ! found && label.getAttribute( 'for' ) === id ) {
					found = nodeText( label );
				}
			} );
		}
		if ( ! found ) {
			var around = marker.closest( 'label' );
			found = around ? nodeText( around ) : '';
		}
		if ( ! found ) {
			var fieldset = marker.closest( 'fieldset' );
			var legend = fieldset ? fieldset.querySelector( 'legend' ) : null;
			found = legend ? nodeText( legend ) : '';
		}
		if ( ! found ) {
			var cell = marker.closest( 'td' );
			var header = cell && cell.parentElement ? cell.parentElement.querySelector( 'th' ) : null;
			found = header ? nodeText( header ) : '';
		}
		if ( ! found ) {
			// Text before the field inside the same block, e.g. <p>Name<br>[text name]</p>.
			var before = '';
			for ( var node = marker.previousSibling; node; node = node.previousSibling ) {
				if ( 1 === node.nodeType && ( node.classList.contains( 'svfs-x-field' ) || node.querySelector( '.svfs-x-field' ) ) ) {
					break;
				}
				before = ( node.textContent || '' ) + before;
			}
			// Only the last line: earlier lines belong to other fields or headings.
			var lines = before.split( /\n\s*\n/ );
			before = tidy( lines[ lines.length - 1 ] );
			found = before.length <= 80 ? before : '';
		}
		if ( ! found && rendered.info[ name ] ) {
			found = rendered.info[ name ].placeholder;
		}
		return tidy( found ) || humanize( name );
	}

	function setupLabelTable() {
		var rows = document.querySelectorAll( '.svfs-labels-table tr[data-svfs-field]' );
		var source = document.getElementById( 'wpcf7-form' );
		if ( ! rows.length || ! source ) {
			return;
		}
		var timer = 0;
		function update() {
			var rendered = renderTemplate( source.value || '' );
			rows.forEach( function ( row ) {
				var label = findLabel( rendered, row.getAttribute( 'data-svfs-field' ) );
				var cell = row.querySelector( '.svfs-found' );
				var input = row.querySelector( 'input' );
				if ( cell ) {
					cell.textContent = label || '—';
				}
				if ( input ) {
					input.placeholder = label;
				}
			} );
		}
		source.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( update, 300 );
		} );
		update();
	}

	/*
	 * Button design: keeps the live preview in step with the controls. Uses the
	 * same classes and variables as the front end, so the preview is exact
	 * (except "Theme default", which needs the theme).
	 */
	var HEX = /^#(?:[0-9a-f]{3}){1,2}$/i;

	function setupDesign() {
		document.querySelectorAll( '.svfs-design' ).forEach( function ( box ) {
			var preview = box.querySelector( '[data-svfs-preview]' );
			var site = {};
			try {
				site = JSON.parse( box.getAttribute( 'data-svfs-design-site' ) || '{}' );
			} catch ( error ) {
				site = {};
			}
			var controls = box.querySelectorAll( '[data-svfs-design]' );

			function value( key ) {
				var control = box.querySelector( '[data-svfs-design="' + key + '"]' );
				var own = control ? String( control.value || '' ).trim() : '';
				if ( own && ( ! control.classList.contains( 'svfs-color-text' ) || HEX.test( own ) ) ) {
					return own;
				}
				return site[ key ] || '';
			}

			function update() {
				if ( ! preview ) {
					return;
				}
				var keep = Array.prototype.filter.call( preview.classList, function ( name ) {
					return ! /^svfs-(btn|align|order|no)-/.test( name );
				} );
				preview.className = keep.join( ' ' );
				preview.classList.add( 'svfs-btn-' + ( value( 'style' ) || 'theme' ) );
				preview.classList.add( 'svfs-align-' + ( value( 'align' ) || 'left' ) );
				if ( 'send_first' === value( 'order' ) ) {
					preview.classList.add( 'svfs-order-send-first' );
				}
				if ( 'hide' === value( 'back' ) ) {
					preview.classList.add( 'svfs-no-back' );
				}
				if ( 'hide' === value( 'prev' ) ) {
					preview.classList.add( 'svfs-no-prev' );
				}
				[ [ 'accent', '--svfs-accent' ], [ 'accent_text', '--svfs-accent-text' ], [ 'accent_hover', '--svfs-accent-hover' ] ].forEach( function ( pair ) {
					var color = value( pair[ 0 ] );
					if ( color && HEX.test( color ) ) {
						preview.style.setProperty( pair[ 1 ], color );
					} else {
						preview.style.removeProperty( pair[ 1 ] );
					}
				} );
			}

			controls.forEach( function ( control ) {
				control.addEventListener( 'input', update );
				control.addEventListener( 'change', update );
			} );

			// Color fields: a picker and a text box share one value; the text box is saved.
			box.querySelectorAll( '.svfs-color' ).forEach( function ( wrap ) {
				var pick = wrap.querySelector( '.svfs-color-pick' );
				var text = wrap.querySelector( '.svfs-color-text' );
				var clear = wrap.querySelector( '.svfs-color-clear' );
				pick.addEventListener( 'input', function () {
					text.value = pick.value;
					update();
				} );
				text.addEventListener( 'input', function () {
					if ( HEX.test( text.value.trim() ) && 7 === text.value.trim().length ) {
						pick.value = text.value.trim();
					}
				} );
				clear.addEventListener( 'click', function () {
					text.value = '';
					pick.value = HEX.test( text.placeholder ) && 7 === text.placeholder.length ? text.placeholder : text.getAttribute( 'data-svfs-default' );
					update();
					text.focus();
				} );
			} );

			// Button texts typed in the Steps tab show in the preview at once.
			[ [ 'svfs-label-back', '.svfs-back' ], [ 'svfs-label-send', '.wpcf7-submit' ], [ 'svfs-label-prev', '.svfs-prev' ], [ 'svfs-label-next', '.svfs-next' ] ].forEach( function ( pair ) {
				var input = document.getElementById( pair[ 0 ] );
				var button = preview ? preview.querySelector( pair[ 1 ] ) : null;
				if ( ! input || ! button ) {
					return;
				}
				var original = button.textContent;
				input.addEventListener( 'input', function () {
					button.textContent = input.value.trim() || original;
				} );
			} );

			update();
		} );
	}

	/*
	 * Thank-you screen builder: templates, answer tags, AI prompt and a live
	 * preview in a sandboxed frame (no scripts run there) using the real
	 * front-end styles, this form's colors and its Custom CSS.
	 */
	function escapeHtml( value ) {
		return String( value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function copyText( text, done ) {
		if ( window.navigator.clipboard && window.isSecureContext ) {
			window.navigator.clipboard.writeText( text ).then( done );
			return;
		}
		var helper = document.createElement( 'textarea' );
		helper.value = text;
		helper.setAttribute( 'readonly', '' );
		helper.style.position = 'fixed';
		helper.style.opacity = '0';
		document.body.appendChild( helper );
		helper.select();
		document.execCommand( 'copy' );
		helper.remove();
		done();
	}

	function insertAtCursor( area, text ) {
		var start = typeof area.selectionStart === 'number' ? area.selectionStart : area.value.length;
		var end = typeof area.selectionEnd === 'number' ? area.selectionEnd : start;
		area.value = area.value.slice( 0, start ) + text + area.value.slice( end );
		area.selectionStart = area.selectionEnd = start + text.length;
		area.focus();
		area.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	}

	function setupThanks() {
		var box = document.querySelector( '[data-svfs-thanks-builder]' );
		var area = document.getElementById( 'svfs-thanks' );
		if ( ! box || ! area ) {
			return;
		}
		var data = {};
		try {
			data = JSON.parse( box.getAttribute( 'data-svfs-thanks-builder' ) || '{}' );
		} catch ( error ) {
			return;
		}
		var slot = box.querySelector( '.svfs-thanks-preview-slot' );
		var frame = null;
		if ( slot ) {
			frame = document.createElement( 'iframe' );
			frame.className = 'svfs-thanks-preview';
			frame.title = slot.getAttribute( 'data-svfs-title' ) || '';
			// No allow-scripts: nothing in the preview can run.
			frame.setAttribute( 'sandbox', 'allow-same-origin' );
			slot.appendChild( frame );
		}
		var timer = 0;

		// Fit a template to this form: use its own name and email fields, or drop the tags.
		function adapt( html ) {
			if ( data.nameField ) {
				html = html.split( '[your-name]' ).join( '[' + data.nameField + ']' );
			} else {
				html = html.replace( /,?\s*\[your-name\]/g, '' );
			}
			if ( data.emailField ) {
				html = html.split( '[your-email]' ).join( '[' + data.emailField + ']' );
			} else {
				html = html.replace( /\s+(?:to|at)\s+(?:<strong>)?\[your-email\](?:<\/strong>)?/g, '' ).replace( /\[your-email\]/g, '' ).replace( /<strong>\s*<\/strong>/g, '' );
			}
			return html;
		}

		function previewHtml() {
			var html = area.value.trim() || escapeHtml( area.placeholder );
			if ( ! /<(p|div|h[1-6]|ul|ol|table|section|figure|blockquote)[\s>]/i.test( html ) ) {
				html = html.split( /\n\s*\n/ ).map( function ( block ) {
					return '<p>' + block.replace( /\n/g, '<br>' ) + '</p>';
				} ).join( '' );
			}
			var samples = data.samples || {};
			return html.replace( /\[([A-Za-z][-A-Za-z0-9_:.]*)\]/g, function ( all, name ) {
				return Object.prototype.hasOwnProperty.call( samples, name ) ? escapeHtml( samples[ name ] ) : all;
			} );
		}

		function render() {
			if ( ! frame ) {
				return;
			}
			var designPreview = document.querySelector( '.svfs-panel [data-svfs-preview]' );
			var vars = designPreview ? designPreview.getAttribute( 'style' ) || '' : '';
			var skinBox = document.querySelector( '.svfs-panel [data-svfs-design="skin"]' );
			var skin = ( skinBox && skinBox.value ) || ( data.design && data.design.skin ) || 'none';
			var skinClass = 'none' === skin ? '' : ' svfs-skin svfs-skin-' + skin.replace( /[^a-z]/g, '' );
			frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8">' +
				'<link rel="stylesheet" href="' + escapeHtml( data.cssUrl || '' ) + '">' +
				'<link rel="stylesheet" href="' + escapeHtml( data.skinsUrl || '' ) + '">' +
				'<style>body{margin:0;padding:16px;background:#fff;color:#1d2327;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}h2{font-size:1.6em}a{color:inherit}</style>' +
				'</head><body><div class="wpcf7"><form class="wpcf7-form svfs-form svfs-state-done' + skinClass + '" style="' + escapeHtml( vars ) + '">' +
				'<div class="svfs-thanks">' + previewHtml() + '</div></form></div></body></html>';
		}

		if ( frame ) {
			frame.addEventListener( 'load', function () {
				try {
					frame.style.height = Math.max( 120, frame.contentDocument.documentElement.scrollHeight ) + 'px';
				} catch ( error ) {
					frame.style.height = '320px';
				}
			} );
		}

		function later() {
			window.clearTimeout( timer );
			timer = window.setTimeout( render, 250 );
		}

		area.addEventListener( 'input', later );
		document.querySelectorAll( '.svfs-panel [data-svfs-design]' ).forEach( function ( control ) {
			control.addEventListener( 'input', later );
			control.addEventListener( 'change', later );
		} );
		// The section is hidden until "Thank-you screen" is chosen; draw it when it appears.
		document.querySelectorAll( 'input[name="svfs[after_send]"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', later );
		} );

		box.querySelectorAll( '[data-svfs-template]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var html = ( data.templates || {} )[ button.getAttribute( 'data-svfs-template' ) ];
				if ( ! html ) {
					return;
				}
				if ( area.value.trim() && ! window.confirm( data.confirmReplace ) ) {
					return;
				}
				area.value = adapt( html );
				area.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
				render();
			} );
		} );

		var insert = box.querySelector( '.svfs-insert-tag' );
		if ( insert ) {
			insert.addEventListener( 'change', function () {
				if ( insert.value ) {
					insertAtCursor( area, insert.value );
				}
				insert.value = '';
			} );
		}

		var open = box.querySelector( '.svfs-ai-open' );
		var ai = document.getElementById( 'svfs-ai-box' );
		if ( open && ai ) {
			open.addEventListener( 'click', function () {
				ai.hidden = ! ai.hidden;
				open.setAttribute( 'aria-expanded', ai.hidden ? 'false' : 'true' );
				if ( ! ai.hidden ) {
					ai.querySelector( 'textarea' ).focus();
				}
			} );
			ai.querySelector( '.svfs-ai-copy' ).addEventListener( 'click', function () {
				var wish = ai.querySelector( '.svfs-ai-wish' ).value.trim();
				var prompt = String( data.prompt || '' )
					.replace( '{current}', function () {
						return area.value.trim() || '(empty)';
					} )
					.replace( '{wish}', function () {
						return wish || 'Clean, friendly and modern. Use the ready-made classes.';
					} );
				copyText( prompt, function () {
					flash( ai.querySelector( '.svfs-saved' ), data.copied, false );
				} );
			} );
		}

		render();
	}

	/*
	 * Examples tab: part tabs, preview sizes, copy buttons, the live HTML view
	 * read from the preview frame (same site), and the restyle prompt.
	 */
	var KEPT_ATTRS = [ 'id', 'class', 'name', 'type', 'for', 'href', 'hidden', 'data-name', 'data-tag', 'data-title', 'placeholder', 'value' ];

	function prettyHtml( node, depth ) {
		var pad = new Array( depth + 1 ).join( '  ' );
		if ( 3 === node.nodeType ) {
			var words = node.textContent.replace( /\s+/g, ' ' ).trim();
			return words ? pad + words + '\n' : '';
		}
		if ( 1 !== node.nodeType ) {
			return '';
		}
		var tag = node.tagName.toLowerCase();
		if ( 'script' === tag || 'br' === tag ) {
			return '';
		}
		var attrs = KEPT_ATTRS.filter( function ( name ) {
			if ( 'value' === name ) {
				return 'input' === tag && /^(submit|button)$/.test( node.type );
			}
			return node.hasAttribute( name );
		} ).map( function ( name ) {
			var value = node.getAttribute( name );
			return '' === value && 'hidden' === name ? ' hidden' : ' ' + name + '="' + value + '"';
		} ).join( '' );
		var open = pad + '<' + tag + attrs + '>';
		if ( 'style' === tag ) {
			return open + '…</style>\n';
		}
		if ( node.classList.contains( 'hidden-fields-container' ) ) {
			return open + '…Contact Form 7 hidden fields…</' + tag + '>\n';
		}
		if ( /^(input|img|hr|meta|link)$/.test( tag ) ) {
			return open + '\n';
		}
		var inner = Array.prototype.map.call( node.childNodes, function ( child ) {
			return prettyHtml( child, depth + 1 );
		} ).join( '' );
		if ( ! inner ) {
			return open + '</' + tag + '>\n';
		}
		var flat = inner.trim();
		if ( -1 === flat.indexOf( '\n' ) && flat.length < 60 && -1 === flat.indexOf( '<' ) ) {
			return open + flat + '</' + tag + '>\n';
		}
		return open + '\n' + inner + pad + '</' + tag + '>\n';
	}

	function highlight( html ) {
		return escapeHtml( html ).replace( /class=&quot;([^&]*)&quot;/g, function ( all, classes ) {
			return 'class=&quot;' + classes.replace( /(^|\s)((?:svfs|ex|is)-[\w-]+)/g, '$1<mark>$2</mark>' ) + '&quot;';
		} );
	}

	function setupExamples() {
		var main = document.querySelector( '.svfs-ex-main' );
		if ( ! main ) {
			return;
		}
		var tabs = Array.prototype.slice.call( main.querySelectorAll( '.svfs-ex-tab' ) );
		var frame = main.querySelector( '.svfs-ex-frame' );
		var htmlBox = main.querySelector( '.svfs-ex-html code' );

		function select( tab, focus ) {
			tabs.forEach( function ( other ) {
				var on = other === tab;
				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				other.tabIndex = on ? 0 : -1;
				document.getElementById( other.getAttribute( 'aria-controls' ) ).hidden = ! on;
			} );
			if ( focus ) {
				tab.focus();
			}
			if ( 'svfs-ex-tab-html' === tab.id ) {
				readHtml();
			}
		}
		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				select( tab, false );
			} );
			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;
				if ( 'ArrowRight' === event.key ) {
					next = tabs[ ( index + 1 ) % tabs.length ];
				} else if ( 'ArrowLeft' === event.key ) {
					next = tabs[ ( index - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === event.key ) {
					next = tabs[ 0 ];
				} else if ( 'End' === event.key ) {
					next = tabs[ tabs.length - 1 ];
				}
				if ( next ) {
					event.preventDefault();
					select( next, true );
				}
			} );
		} );

		// The preview grows with its content: steps, confirm screen, errors.
		function fit() {
			try {
				var body = frame.contentDocument.body;
				frame.style.height = Math.max( 360, Math.ceil( body.getBoundingClientRect().height ) ) + 'px';
			} catch ( error ) {
				frame.style.height = '900px';
			}
		}
		if ( frame ) {
			frame.addEventListener( 'load', function () {
				fit();
				try {
					if ( window.ResizeObserver ) {
						new window.ResizeObserver( fit ).observe( frame.contentDocument.body );
					}
				} catch ( error ) {
					// Different origin: keep the fixed height.
				}
			} );
		}

		main.querySelectorAll( '.svfs-ex-size' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				main.querySelectorAll( '.svfs-ex-size' ).forEach( function ( other ) {
					other.classList.toggle( 'is-active', other === button );
				} );
				var width = button.getAttribute( 'data-width' );
				frame.style.width = width ? width + 'px' : '';
				window.setTimeout( fit, 50 );
			} );
		} );

		var reload = main.querySelector( '.svfs-ex-reload' );
		if ( reload ) {
			reload.addEventListener( 'click', function () {
				frame.src = frame.src;
			} );
		}

		function readHtml() {
			if ( ! htmlBox ) {
				return;
			}
			try {
				var form = frame.contentDocument.querySelector( '.wpcf7 form' );
				htmlBox.innerHTML = form ? highlight( prettyHtml( form, 0 ) ) : '';
			} catch ( error ) {
				htmlBox.textContent = '';
			}
		}
		var refresh = main.querySelector( '.svfs-ex-html-refresh' );
		if ( refresh ) {
			refresh.addEventListener( 'click', readHtml );
		}

		main.querySelectorAll( '.svfs-copy-code' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var panel = button.closest( '.svfs-code-box, .svfs-ex-panel' );
				var code = panel ? panel.querySelector( 'pre code' ) : null;
				if ( code ) {
					copyText( code.textContent, function () {
						flash( button.parentNode.querySelector( '.svfs-saved' ), i18n.copied, false );
					} );
				}
			} );
		} );

		var ai = main.querySelector( '.svfs-ex-ai-copy' );
		if ( ai ) {
			ai.addEventListener( 'click', function () {
				var wish = document.getElementById( 'svfs-ex-wish' ).value.trim();
				var prompt = ai.getAttribute( 'data-svfs-prompt' ).replace( '{wish}', function () {
					return wish || 'Clean and modern, matching the current colors.';
				} );
				copyText( prompt, function () {
					flash( ai.parentNode.querySelector( '.svfs-saved' ), i18n.copied, false );
				} );
			} );
		}
	}

	function init() {
		setupToggles();
		setupCopy();
		setupConditions();
		setupTips();
		setupLabelTable();
		setupDesign();
		setupThanks();
		setupExamples();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
