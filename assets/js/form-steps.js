/**
 * Form Steps for Contact Form 7 - front end.
 *
 * Works only through Contact Form 7's public events (wpcf7submit, wpcf7invalid,
 * wpcf7mailsent) and a capture-phase submit listener that sets the hidden
 * _svfs_phase field before Contact Form 7 builds its request.
 *
 * States: "input" (form visible), "review" (confirm screen), "done" (after send).
 */
( function () {
	'use strict';

	var PHASE_FIELD = '_svfs_phase';
	var controllers = new WeakMap();

	// Elements that stay visible on a summary-table confirm screen.
	var KEEP = [
		'.svfs-intro', '.svfs-summary', '.svfs-thanks', '.wpcf7-response-output', '.svfs-progress', '.svfs-live',
		'.svfs-keep', '.svfs-review', '.svfs-back', '.wpcf7-submit', '.wpcf7-spinner',
		'.wpcf7c-elm-step2', '.wpcf7c-elm-step3', '.hidden-fields-container',
		'.cf-turnstile', '.wpcf7-turnstile', '.g-recaptcha', '.h-captcha', '.wpcf7-captchac'
	].join( ',' );

	// Fields never shown on the confirm screen.
	var SKIP = '.svfs-skip, .avoid-confirm';

	// Form children that never belong to a step.
	// .svfs-keep marks content such as a form header that belongs to no step and stays visible.
	var OUTSIDE_STEPS = '.hidden-fields-container, .svfs-intro, .svfs-thanks, .wpcf7-response-output, .svfs-progress, .svfs-live, .svfs-keep, style, script';

	// Captcha widgets. They reset after every response, so they must stay visible
	// on every step; Contact Form 7 places Turnstile at the top of the form.
	var CAPTCHA = '.wpcf7-turnstile, .cf-turnstile, .g-recaptcha, .h-captcha';

	// Turnstile response fields. Turnstile resets after every Contact Form 7 response,
	// including Review and Next, and needs a moment to issue a new token.
	var TOKEN_FIELDS = 'input[name="_wpcf7_turnstile_response"], input[name="cf-turnstile-response"]';
	var TOKEN_WAIT_MS = 8000;

	// Contact Form 7's hidden system fields: a named fieldset since 6.0, a plain
	// hidden <div> in older versions.
	function isSystemFields( element ) {
		return element.matches( '.hidden-fields-container' ) ||
			( !! element.querySelector( 'input[name="_wpcf7"]' ) && ! element.querySelector( '.wpcf7-form-control-wrap' ) );
	}

	function isCaptchaBlock( element ) {
		if ( element.matches( CAPTCHA ) ) {
			return true;
		}
		return !! element.querySelector( CAPTCHA ) && ! element.querySelector( '.wpcf7-form-control-wrap' );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	function reducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function text( node ) {
		return ( node && node.textContent ? node.textContent : '' ).replace( /\s+/g, ' ' ).trim();
	}

	function buttonLabel( button ) {
		return 'INPUT' === button.tagName ? button.value : button.textContent;
	}

	function setButtonLabel( button, label ) {
		if ( 'INPUT' === button.tagName ) {
			button.value = label;
		} else {
			button.textContent = label;
		}
	}

	function format( pattern ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var next = 0;
		return String( pattern ).replace( /%(?:(\d+)\$)?s/g, function ( match, position ) {
			var index = position ? Number( position ) - 1 : next++;
			return undefined === args[ index ] ? '' : String( args[ index ] );
		} );
	}

	var BUTTONS = '.wpcf7-submit, .svfs-review, .svfs-back, .svfs-prev, .svfs-next';

	/**
	 * Marks the element that holds the Review/Send buttons with "svfs-actions",
	 * so button position and order can be set with CSS. Only rows that contain
	 * nothing but buttons are marked; mixed rows keep the theme's layout.
	 */
	function markActionRows( form ) {
		form.querySelectorAll( '.wpcf7-submit, .svfs-review' ).forEach( function ( button ) {
			var row = button.parentElement;
			if ( ! row || row === form || row.classList.contains( 'svfs-step-nav' ) ) {
				return;
			}
			var onlyButtons = Array.prototype.every.call( row.childNodes, function ( node ) {
				if ( 3 === node.nodeType ) {
					return ! node.textContent.trim();
				}
				return 1 !== node.nodeType || node.matches( BUTTONS + ', .wpcf7-spinner, br' );
			} );
			if ( onlyButtons ) {
				row.classList.add( 'svfs-actions' );
			}
		} );
	}

	function makeButton( like, type, className, label ) {
		var button = document.createElement( like && 'BUTTON' === like.tagName ? 'button' : 'input' );
		button.type = type;
		button.className = 'wpcf7-form-control ' + className;
		setButtonLabel( button, label );
		return button;
	}

	function isFirstContent( node ) {
		for ( var previous = node.previousSibling; previous; previous = previous.previousSibling ) {
			if ( 1 === previous.nodeType || previous.textContent.trim() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The element that stands for a [step] marker among its siblings.
	 * A parent that holds nothing but the marker stands for it. A marker that opens
	 * a paragraph shared with other content (older Contact Form 7 formatting, or
	 * hand-written HTML) is lifted out in front of it, with the line break after it.
	 * Only the empty, hidden marker is ever moved.
	 */
	function boundaryOf( marker, form ) {
		var node = marker;
		while ( node.parentElement && node.parentElement !== form ) {
			var parent = node.parentElement;
			if ( 1 === parent.children.length && '' === text( parent ) ) {
				node = parent;
				continue;
			}
			if ( node !== marker || ! isFirstContent( node ) ) {
				break;
			}
			var next = node.nextSibling;
			while ( next && 3 === next.nodeType && ! next.textContent.trim() ) {
				next = next.nextSibling;
			}
			if ( next && 'BR' === next.nodeName ) {
				next.remove();
			}
			parent.parentNode.insertBefore( node, parent );
		}
		return node;
	}

	function humanize( name ) {
		var words = name.replace( /^your[-_]/i, '' ).replace( /[-_:.]+/g, ' ' ).trim();
		return words.charAt( 0 ).toUpperCase() + words.slice( 1 );
	}

	function formatDate( value ) {
		var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value );
		if ( ! parts || ! window.Intl ) {
			return value;
		}
		try {
			var date = new Date( Number( parts[ 1 ] ), Number( parts[ 2 ] ) - 1, Number( parts[ 3 ] ) );
			return new Intl.DateTimeFormat( document.documentElement.lang || undefined, { dateStyle: 'long' } ).format( date );
		} catch ( e ) {
			return value;
		}
	}

	// True when a theme or another plugin (for example Conditional Fields) hides the element.
	function hiddenByOthers( element, form ) {
		var node = element;
		while ( node && node !== form ) {
			if ( node.hidden || ( ! node.classList.contains( 'svfs-step-off' ) && 'none' === window.getComputedStyle( node ).display ) ) {
				return true;
			}
			node = node.parentElement;
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Reading field values                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Describes one Contact Form 7 field wrapper.
	 *
	 * kind "value": readable, shown as text.
	 * kind "keep":  shown as is but not editable (quiz, unknown add-on fields).
	 * kind "consent": acceptance box, kept visible in place, left out of tables.
	 * kind "skip":  not shown.
	 */
	function readField( wrap ) {
		if ( wrap.querySelector( '.wpcf7-quiz, .wpcf7-captchar, .wpcf7-captchac' ) ) {
			return { kind: 'keep' };
		}
		if ( wrap.querySelector( '.wpcf7-acceptance' ) ) {
			return { kind: 'consent' };
		}

		var control = wrap.querySelector( 'select, textarea, input' );
		if ( ! control ) {
			return { kind: 'keep' };
		}

		if ( 'SELECT' === control.tagName ) {
			return {
				kind: 'value',
				text: Array.prototype.filter.call( control.options, function ( option ) {
					return option.selected && '' !== option.value;
				} ).map( function ( option ) {
					return text( option );
				} ).join( ', ' )
			};
		}

		if ( 'TEXTAREA' === control.tagName ) {
			return { kind: 'value', text: control.value.trim() };
		}

		switch ( control.type ) {
			case 'hidden':
				return { kind: 'skip' };
			case 'checkbox':
			case 'radio':
				return { kind: 'value', text: checkedLabels( wrap ) };
			case 'file':
				return {
					kind: 'value',
					text: Array.prototype.map.call( control.files || [], function ( file ) {
						return file.name;
					} ).join( ', ' )
				};
			case 'password':
				return { kind: 'value', text: control.value ? '••••••••' : '' };
			case 'date':
				return { kind: 'value', text: formatDate( control.value ) };
			case 'submit':
			case 'button':
			case 'image':
			case 'reset':
				return { kind: 'skip' };
			default:
				return { kind: 'value', text: control.value.trim() };
		}
	}

	function checkedLabels( wrap ) {
		var labels = [];
		wrap.querySelectorAll( 'input[type="checkbox"]:checked, input[type="radio"]:checked' ).forEach( function ( input ) {
			var item = input.closest( '.wpcf7-list-item' );
			var label = item ? text( item.querySelector( '.wpcf7-list-item-label' ) ) : '';
			label = label || input.value;
			// "Other" option with a free-text box.
			var free = item ? item.querySelector( '.wpcf7-free-text' ) : null;
			if ( free && free.value.trim() ) {
				label += ': ' + free.value.trim();
			}
			labels.push( label );
		} );
		return labels.join( ', ' );
	}

	/* ------------------------------------------------------------------ */
	/* Finding labels for the summary table                                */
	/* ------------------------------------------------------------------ */

	function cleanLabel( node ) {
		var clone = node.cloneNode( true );
		clone.querySelectorAll( '.wpcf7-form-control-wrap, .required, .require, .must, [class*="required"], .screen-reader-text' ).forEach( function ( child ) {
			child.remove();
		} );
		return text( clone ).replace( /[\s*:：※]+$/, '' ).replace( /^[\s*※]+/, '' ).trim();
	}

	// "(required)" markers help while typing but read oddly in a summary.
	var REQUIRED_MARK = /\s*[([（【]\s*(?:required|必須|必要|pflicht(?:feld)?|erforderlich|obligatoire|requis|obligatorio|requerido|obrigat[oó]rio)\s*[)\]）】]/gi;

	function stripRequired( label ) {
		return label.replace( REQUIRED_MARK, '' ).replace( /\s*必須$/, '' ).trim();
	}

	function labelFor( wrap, name, form, overrides ) {
		if ( overrides && overrides[ name ] ) {
			return overrides[ name ];
		}

		var control = wrap.querySelector( 'input, select, textarea' );
		var found = '';

		if ( control && control.id ) {
			var linked = form.querySelector( 'label[for="' + control.id.replace( /"/g, '' ) + '"]' );
			found = linked ? cleanLabel( linked ) : '';
		}

		if ( ! found ) {
			var around = wrap.closest( 'label' );
			found = around ? cleanLabel( around ) : '';
		}

		if ( ! found ) {
			var fieldset = wrap.closest( 'fieldset' );
			var legend = fieldset && ! fieldset.classList.contains( 'hidden-fields-container' ) ? fieldset.querySelector( 'legend' ) : null;
			found = legend ? cleanLabel( legend ) : '';
		}

		if ( ! found ) {
			var cell = wrap.closest( 'td' );
			var row = cell ? cell.parentElement : null;
			var header = row ? row.querySelector( 'th' ) : null;
			found = header ? cleanLabel( header ) : '';
		}

		if ( ! found ) {
			// Text before the field inside the same block, e.g. <p>Name<br>[text name]</p>.
			var parent = wrap.parentElement;
			if ( parent && parent !== form ) {
				var before = '';
				for ( var node = parent.firstChild; node && node !== wrap; node = node.nextSibling ) {
					if ( 1 === node.nodeType && node.classList.contains( 'wpcf7-form-control-wrap' ) ) {
						before = '';
						continue;
					}
					before += node.textContent || '';
				}
				before = before.replace( /\s+/g, ' ' ).replace( /[\s*:：※]+$/, '' ).trim();
				found = before.length <= 80 ? before : '';
			}
		}

		if ( ! found && control && control.placeholder ) {
			found = control.placeholder;
		}

		return stripRequired( found ) || humanize( name );
	}

	/* ------------------------------------------------------------------ */
	/* Controller: one per form                                            */
	/* ------------------------------------------------------------------ */

	function Controller( form, config ) {
		this.form = form;
		this.config = config;
		this.state = 'input';
		this.phase = form.querySelector( 'input[name="' + PHASE_FIELD + '"]' );
		this.intro = form.querySelector( '.svfs-intro' );
		this.thanks = form.querySelector( '.svfs-thanks' );
		this.explicit = !! form.querySelector( '.svfs-review' );
		this.undo = [];
		this.backButtons = [];
		this.submits = Array.prototype.slice.call( form.querySelectorAll( '.wpcf7-submit' ) );
		this.originalLabels = this.submits.map( buttonLabel );

		this.steps = null;
		this.stepIndex = 0;
		this.stepField = form.querySelector( 'input[name="_svfs_fields"]' );

		if ( config.confirm && ! this.explicit ) {
			this.setupAutoButtons();
		}
		if ( config.steps ) {
			this.setupSteps();
		}
		markActionRows( form );

		var self = this;
		form.addEventListener( 'click', function ( event ) {
			var back = event.target.closest( '.svfs-back' );
			if ( back && form.contains( back ) ) {
				event.preventDefault();
				self.leaveReview( true );
				return;
			}
			var prev = event.target.closest( '.svfs-prev' );
			if ( prev && form.contains( prev ) && self.steps ) {
				event.preventDefault();
				self.goTo( self.stepIndex - 1, true );
			}
		} );

		form.classList.add( 'svfs-ready' );
		form.classList.add( 'svfs-state-input' );
	}

	/**
	 * Forms without [confirm] tags: the form's own submit button becomes "Review",
	 * and a Back button is added next to it for the confirm screen.
	 */
	Controller.prototype.setupAutoButtons = function () {
		var labels = this.config.labels || {};
		var self = this;
		this.submits.forEach( function ( submit ) {
			setButtonLabel( submit, labels.review );
			var back = document.createElement( 'INPUT' === submit.tagName ? 'input' : 'button' );
			back.type = 'button';
			back.className = 'wpcf7-form-control svfs-back';
			back.hidden = true;
			setButtonLabel( back, labels.back );
			submit.parentNode.insertBefore( back, submit );
			self.backButtons.push( back );
		} );
	};

	/* ---------------- Steps ---------------- */

	/**
	 * Groups the form's content into steps at each [step] marker. Elements are
	 * only tagged, never moved, so captcha iframes and other plugins' widgets
	 * keep working. All markers must share one parent element; otherwise the
	 * form stays a normal one-page form.
	 */
	Controller.prototype.setupSteps = function () {
		var form = this.form;
		var markers = Array.prototype.slice.call( form.querySelectorAll( '.svfs-step-marker' ) );
		if ( ! markers.length ) {
			return;
		}
		var bounds = markers.map( function ( marker ) {
			return boundaryOf( marker, form );
		} );
		var parent = bounds[ 0 ].parentElement;
		var sameParent = bounds.every( function ( bound ) {
			return bound.parentElement === parent;
		} );
		if ( ! sameParent ) {
			form.classList.add( 'svfs-steps-off' );
			if ( window.console ) {
				window.console.warn( 'Form Steps: all [step] tags must be at the same level of the form, not inside different blocks. The form is shown on one page.' );
			}
			return;
		}

		var i18n = this.config.i18n || {};
		var steps = [];
		var current = { title: '', els: [] };
		Array.prototype.forEach.call( parent.children, function ( child ) {
			var at = bounds.indexOf( child );
			if ( at > -1 ) {
				child.classList.add( 'svfs-step-boundary' );
				if ( current.els.length ) {
					steps.push( current );
				}
				current = { title: markers[ at ].getAttribute( 'data-title' ) || '', els: [] };
				return;
			}
			if ( ! child.matches( OUTSIDE_STEPS ) && ! isCaptchaBlock( child ) && ! isSystemFields( child ) ) {
				current.els.push( child );
			}
		} );
		if ( current.els.length ) {
			steps.push( current );
		}
		if ( steps.length < 2 ) {
			form.classList.add( 'svfs-steps-off' );
			return;
		}
		steps.forEach( function ( step, index ) {
			step.title = step.title || format( i18n.step || 'Step %s', index + 1 );
		} );
		this.steps = steps;

		this.addStepButtons();
		this.addProgress();

		this.live = document.createElement( 'p' );
		this.live.className = 'svfs-live svfs-sr-only';
		this.live.setAttribute( 'aria-live', 'polite' );
		form.appendChild( this.live );

		this.goTo( 0, false );
	};

	Controller.prototype.addStepButtons = function () {
		var labels = this.config.labels || {};
		var steps = this.steps;
		var last = steps.length - 1;
		var like = this.submits[ 0 ] || null;

		steps.forEach( function ( step, index ) {
			if ( index === last ) {
				return;
			}
			var nav = document.createElement( 'p' );
			nav.className = 'svfs-step-nav';
			if ( index > 0 ) {
				nav.appendChild( makeButton( like, 'button', 'svfs-prev', labels.prev ) );
			}
			nav.appendChild( makeButton( like, 'submit', 'svfs-next', labels.next ) );
			var spinner = document.createElement( 'span' );
			spinner.className = 'wpcf7-spinner';
			nav.appendChild( spinner );

			var lastEl = step.els[ step.els.length - 1 ];
			lastEl.parentNode.insertBefore( nav, lastEl.nextSibling );
			step.els.push( nav );
		} );

		// Previous button on the last step, next to the Review or Send button.
		var finalButton = this.form.querySelector( '.svfs-review' ) || like;
		if ( finalButton ) {
			this.lastPrev = makeButton( like, 'button', 'svfs-prev svfs-prev-last', labels.prev );
			finalButton.parentNode.insertBefore( this.lastPrev, finalButton );
		}
	};

	Controller.prototype.addProgress = function () {
		var style = this.config.progress;
		if ( 'none' === style ) {
			return;
		}
		var titles = this.steps.map( function ( step ) {
			return step.title;
		} );
		if ( this.config.confirm ) {
			titles.push( ( this.config.i18n && this.config.i18n.review ) || 'Review' );
		}
		this.progressTitles = titles;

		var progress;
		if ( 'bar' === style ) {
			progress = document.createElement( 'div' );
			progress.className = 'svfs-progress svfs-progress-bar';
			var track = document.createElement( 'div' );
			var fill = document.createElement( 'span' );
			var label = document.createElement( 'p' );
			track.className = 'svfs-progress-track';
			track.setAttribute( 'role', 'progressbar' );
			track.setAttribute( 'aria-valuemin', '1' );
			track.setAttribute( 'aria-valuemax', String( titles.length ) );
			fill.className = 'svfs-progress-fill';
			label.className = 'svfs-progress-label';
			track.appendChild( fill );
			progress.appendChild( track );
			progress.appendChild( label );
		} else {
			progress = document.createElement( 'ol' );
			progress.className = 'svfs-progress svfs-progress-numbers';
			titles.forEach( function ( title, index ) {
				var item = document.createElement( 'li' );
				var number = document.createElement( 'span' );
				var name = document.createElement( 'span' );
				number.className = 'svfs-progress-num';
				number.textContent = String( index + 1 );
				name.className = 'svfs-progress-title';
				name.textContent = title;
				item.appendChild( number );
				item.appendChild( name );
				progress.appendChild( item );
			} );
		}
		var first = this.steps[ 0 ].els[ 0 ];
		first.parentNode.insertBefore( progress, first );
		this.progress = progress;
	};

	Controller.prototype.updateProgress = function () {
		var titles = this.progressTitles || ( this.steps || [] ).map( function ( step ) {
			return step.title;
		} );
		if ( ! titles.length ) {
			return;
		}
		var active = 'review' === this.state && this.progressTitles && this.config.confirm ? titles.length - 1 : this.stepIndex;
		var total = titles.length;
		var i18n = this.config.i18n || {};
		var summary = format( i18n.stepOf || 'Step %1$s of %2$s', active + 1, total ) + ': ' + titles[ active ];

		if ( this.progress && this.progress.classList.contains( 'svfs-progress-bar' ) ) {
			var track = this.progress.querySelector( '.svfs-progress-track' );
			track.setAttribute( 'aria-valuenow', String( active + 1 ) );
			track.setAttribute( 'aria-valuetext', summary );
			this.progress.querySelector( '.svfs-progress-fill' ).style.width = ( ( active + 1 ) / total * 100 ) + '%';
			this.progress.querySelector( '.svfs-progress-label' ).textContent = summary;
		} else if ( this.progress ) {
			Array.prototype.forEach.call( this.progress.children, function ( item, index ) {
				item.classList.toggle( 'is-done', index < active );
				item.classList.toggle( 'is-current', index === active );
				if ( index === active ) {
					item.setAttribute( 'aria-current', 'step' );
				} else {
					item.removeAttribute( 'aria-current' );
				}
			} );
		}
		if ( this.live ) {
			this.live.textContent = summary;
		}
	};

	Controller.prototype.goTo = function ( index, moveFocus ) {
		if ( ! this.steps ) {
			return;
		}
		var last = this.steps.length - 1;
		index = Math.max( 0, Math.min( last, index ) );
		this.stepIndex = index;
		this.showSteps( index );
		this.form.classList.toggle( 'svfs-on-last-step', index === last );
		this.updateProgress();
		if ( moveFocus ) {
			var field = null;
			this.fieldsInStep( index ).some( function ( wrap ) {
				field = wrap.querySelector( 'input:not([type="hidden"]), select, textarea' );
				return !! field;
			} );
			this.reveal( this.progress || this.form, field );
		}
	};

	/** Shows one step, or every step when index is -1 (confirm screen). */
	Controller.prototype.showSteps = function ( index ) {
		this.steps.forEach( function ( step, i ) {
			var off = -1 !== index && i !== index;
			step.els.forEach( function ( element ) {
				element.classList.toggle( 'svfs-step-off', off );
			} );
		} );
	};

	Controller.prototype.fieldsInStep = function ( index ) {
		var form = this.form;
		var found = [];
		this.steps[ index ].els.forEach( function ( element ) {
			var wraps = element.matches( '.wpcf7-form-control-wrap[data-name]' ) ? [ element ] : element.querySelectorAll( '.wpcf7-form-control-wrap[data-name]' );
			Array.prototype.forEach.call( wraps, function ( wrap ) {
				if ( ! hiddenByOthers( wrap, form ) ) {
					found.push( wrap );
				}
			} );
		} );
		return found;
	};

	Controller.prototype.stepOf = function ( name ) {
		if ( ! this.steps ) {
			return -1;
		}
		var wrap = null;
		Array.prototype.some.call( this.form.querySelectorAll( '.wpcf7-form-control-wrap[data-name]' ), function ( candidate ) {
			if ( candidate.dataset.name === name ) {
				wrap = candidate;
				return true;
			}
			return false;
		} );
		if ( ! wrap ) {
			return -1;
		}
		for ( var i = 0; i < this.steps.length; i++ ) {
			if ( this.steps[ i ].els.some( function ( element ) {
				return element === wrap || element.contains( wrap );
			} ) ) {
				return i;
			}
		}
		return -1;
	};

	/* ---------------- Submitting ---------------- */

	/**
	 * On Next, drops files of other steps from the request, so large uploads are
	 * not sent again on every step. The final Review or Send includes all files.
	 */
	Controller.prototype.trimRequest = function ( detail ) {
		if ( ! detail || ! detail.formData || ! this.steps || ! this.phase || 'step' !== this.phase.value ) {
			return;
		}
		var current = this.fieldsInStep( this.stepIndex );
		this.form.querySelectorAll( 'input[type="file"]' ).forEach( function ( input ) {
			var here = current.some( function ( wrap ) {
				return wrap.contains( input );
			} );
			if ( ! here && input.name && detail.formData.delete ) {
				detail.formData.delete( input.name );
			}
		} );
	};

	/** The phase the next submission will carry: "step", "confirm" or "" (real send). */
	Controller.prototype.plannedPhase = function () {
		if ( 'review' === this.state ) {
			return '';
		}
		if ( this.steps && this.stepIndex < this.steps.length - 1 ) {
			return 'step';
		}
		return this.config.confirm ? 'confirm' : '';
	};

	Controller.prototype.tokenPending = function () {
		return Array.prototype.some.call( this.form.querySelectorAll( TOKEN_FIELDS ), function ( input ) {
			return ! input.value;
		} );
	};

	/**
	 * A real send pressed while Turnstile is still issuing its new token would be
	 * rejected as spam. Hold the click, show the spinner, and send as soon as the
	 * token is there (or after a few seconds, letting Contact Form 7 report any error).
	 *
	 * @return {boolean} True when the submission was held.
	 */
	Controller.prototype.holdForToken = function ( event ) {
		if ( this.waiting ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return true;
		}
		if ( this.skipHold ) {
			this.skipHold = false;
			return false;
		}
		if ( '' !== this.plannedPhase() || ! this.tokenPending() ) {
			return false;
		}
		event.preventDefault();
		event.stopImmediatePropagation();

		var self = this;
		var form = this.form;
		var submitter = event.submitter && event.submitter.form === form ? event.submitter : null;
		var started = Date.now();
		this.waiting = true;
		form.classList.add( 'svfs-waiting' );

		( function check() {
			if ( self.tokenPending() && Date.now() - started < TOKEN_WAIT_MS ) {
				window.setTimeout( check, 250 );
				return;
			}
			self.waiting = false;
			self.skipHold = true;
			form.classList.remove( 'svfs-waiting' );
			if ( form.requestSubmit ) {
				form.requestSubmit( submitter || undefined );
			} else {
				form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
			}
		}() );
		return true;
	};

	/** Called in the capture phase, before Contact Form 7 reads the form. */
	Controller.prototype.beforeSubmit = function () {
		var phase = this.plannedPhase();
		var names = '';
		if ( 'step' === phase ) {
			names = this.fieldsInStep( this.stepIndex ).map( function ( wrap ) {
				return wrap.dataset.name;
			} ).join( ',' );
		}
		if ( this.phase ) {
			this.phase.value = phase;
		}
		if ( this.stepField ) {
			this.stepField.value = names;
		}
	};

	Controller.prototype.fields = function () {
		var form = this.form;
		return Array.prototype.filter.call( form.querySelectorAll( '.wpcf7-form-control-wrap[data-name]' ), function ( wrap ) {
			return ! wrap.closest( '.hidden-fields-container' ) && ! hiddenByOthers( wrap, form );
		} );
	};

	Controller.prototype.emptyText = function () {
		return 'hide' === this.config.empty ? '' : '—';
	};

	Controller.prototype.enterReview = function () {
		if ( 'review' === this.state ) {
			return;
		}
		var self = this;
		var table = 'table' === this.config.style;
		var rows = [];
		var keep = [];

		if ( this.steps ) {
			this.showSteps( -1 );
		}

		this.fields().forEach( function ( wrap ) {
			if ( wrap.closest( SKIP ) ) {
				self.hide( wrap );
				return;
			}
			var field = readField( wrap );
			if ( 'skip' === field.kind ) {
				return;
			}
			if ( 'keep' === field.kind || ( 'consent' === field.kind && ! table ) ) {
				self.makeInert( wrap );
				keep.push( wrap );
				return;
			}
			if ( 'consent' === field.kind ) {
				return;
			}
			if ( table ) {
				if ( '' === field.text && 'hide' === self.config.empty ) {
					return;
				}
				rows.push( [ labelFor( wrap, wrap.dataset.name, self.form, self.config.fieldLabels ), field.text || self.emptyText() ] );
			} else {
				self.showValue( wrap, field.text || self.emptyText() );
			}
		} );

		if ( table ) {
			this.showTable( rows );
			this.hideAllExcept( Array.prototype.slice.call( this.form.querySelectorAll( KEEP ) ).concat( keep ) );
		}

		this.setState( 'review' );
		this.toggleButtons( true );
		this.updateProgress();
		if ( this.intro ) {
			this.intro.hidden = false;
		}
		this.reveal( this.intro || this.form );
	};

	Controller.prototype.leaveReview = function ( moveFocus ) {
		if ( 'review' !== this.state ) {
			return;
		}
		while ( this.undo.length ) {
			this.undo.pop()();
		}
		if ( this.intro ) {
			this.intro.hidden = true;
		}
		this.toggleButtons( false );
		this.setState( 'input' );
		if ( this.phase ) {
			this.phase.value = '';
		}
		if ( this.steps ) {
			this.goTo( this.stepIndex, moveFocus );
			return;
		}
		if ( moveFocus ) {
			var first = null;
			this.fields().some( function ( wrap ) {
				first = wrap.querySelector( 'input:not([type="hidden"]), select, textarea' );
				return !! first;
			} );
			this.reveal( this.form, first );
		}
	};

	Controller.prototype.showValue = function ( wrap, value ) {
		var span = document.createElement( 'span' );
		span.className = 'svfs-value wpcf7c-conf';
		span.textContent = value;
		wrap.appendChild( span );
		wrap.classList.add( 'svfs-has-value' );
		this.undo.push( function () {
			span.remove();
			wrap.classList.remove( 'svfs-has-value' );
		} );
	};

	Controller.prototype.makeInert = function ( element ) {
		element.setAttribute( 'inert', '' );
		element.classList.add( 'svfs-locked' );
		this.undo.push( function () {
			element.removeAttribute( 'inert' );
			element.classList.remove( 'svfs-locked' );
		} );
	};

	Controller.prototype.hide = function ( element ) {
		if ( element.classList.contains( 'svfs-hidden' ) ) {
			return;
		}
		element.classList.add( 'svfs-hidden' );
		this.undo.push( function () {
			element.classList.remove( 'svfs-hidden' );
		} );
	};

	Controller.prototype.showTable = function ( rows ) {
		var wrapper = document.createElement( 'div' );
		wrapper.className = 'svfs-summary';
		var table = document.createElement( 'table' );
		var body = document.createElement( 'tbody' );
		rows.forEach( function ( row ) {
			var tr = document.createElement( 'tr' );
			var th = document.createElement( 'th' );
			var td = document.createElement( 'td' );
			th.scope = 'row';
			th.textContent = row[ 0 ];
			td.textContent = row[ 1 ];
			tr.appendChild( th );
			tr.appendChild( td );
			body.appendChild( tr );
		} );
		table.appendChild( body );
		wrapper.appendChild( table );

		// Right after the intro, else after Contact Form 7's hidden fields, else first.
		var after = this.intro || this.form.querySelector( '.hidden-fields-container' );
		this.form.insertBefore( wrapper, after && after.parentNode === this.form ? after.nextSibling : this.form.firstChild );
		this.undo.push( function () {
			wrapper.remove();
		} );
	};

	/**
	 * Hides every element that neither is nor contains one of the kept elements.
	 * Works with any form layout, including forms wrapped in one big container.
	 */
	Controller.prototype.hideAllExcept = function ( keep ) {
		var self = this;

		function walk( parent ) {
			Array.prototype.forEach.call( parent.children, function ( child ) {
				var isKept = keep.some( function ( k ) {
					return k === child;
				} );
				if ( isKept ) {
					return;
				}
				var holdsKept = keep.some( function ( k ) {
					return child.contains( k );
				} );
				if ( holdsKept ) {
					walk( child );
				} else if ( ! child.hidden ) {
					self.hide( child );
				}
			} );
		}
		walk( this.form );
	};

	Controller.prototype.toggleButtons = function ( reviewing ) {
		if ( this.explicit ) {
			return; // CSS shows and hides the [confirm], [back] and step classes.
		}
		var labels = this.config.labels || {};
		var originals = this.originalLabels;
		this.submits.forEach( function ( submit, index ) {
			setButtonLabel( submit, reviewing ? ( labels.send || originals[ index ] ) : labels.review );
		} );
		this.backButtons.forEach( function ( back ) {
			back.hidden = ! reviewing;
		} );
	};

	Controller.prototype.setState = function ( state ) {
		this.form.classList.remove( 'svfs-state-' + this.state );
		this.state = state;
		this.form.classList.add( 'svfs-state-' + state );
	};

	/** Scrolls target into view if needed, then focuses focusTarget (or target). */
	Controller.prototype.reveal = function ( target, focusTarget ) {
		if ( ! target ) {
			return;
		}
		if ( this.config.scroll ) {
			var top = target.getBoundingClientRect().top;
			if ( top < 0 || top > window.innerHeight * 0.6 ) {
				target.scrollIntoView( { behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' } );
			}
		}
		var focus = focusTarget || target;
		if ( focus.focus ) {
			focus.focus( { preventScroll: true } );
		}
	};

	Controller.prototype.onInvalid = function ( detail ) {
		// Errors from the final Send attach to the form fields, so show the form again.
		this.leaveReview( false );
		if ( ! this.steps ) {
			return;
		}
		var invalid = detail && detail.apiResponse && detail.apiResponse.invalid_fields;
		var self = this;
		var target = -1;
		( invalid || [] ).some( function ( field ) {
			target = self.stepOf( field.field );
			return target > -1;
		} );
		if ( target > -1 && target !== this.stepIndex ) {
			this.goTo( target, false );
		}
	};

	/**
	 * Fills [field-name] tags in the thank-you screen with the sent answers, as
	 * plain text. Contact Form 7 passes the sent values with its mailsent event.
	 */
	Controller.prototype.fillThanks = function ( inputs ) {
		var tags = this.thanks ? this.thanks.querySelectorAll( '.svfs-tag[data-tag]' ) : [];
		if ( ! tags.length ) {
			return;
		}
		var values = {};
		( inputs || [] ).forEach( function ( input ) {
			var name = String( input.name || '' ).replace( /\[\]$/, '' );
			var value = String( null === input.value || undefined === input.value ? '' : input.value ).trim();
			if ( ! name || ! value ) {
				return;
			}
			values[ name ] = values[ name ] ? values[ name ] + ', ' + value : value;
		} );
		Array.prototype.forEach.call( tags, function ( tag ) {
			tag.textContent = values[ tag.getAttribute( 'data-tag' ) ] || '';
		} );
	};

	Controller.prototype.onSent = function ( detail ) {
		this.fillThanks( detail && detail.inputs );
		this.leaveReview( false );
		if ( this.steps ) {
			this.goTo( 0, false );
		}
		var config = this.config;

		if ( 'redirect' === config.afterSend && config.redirect ) {
			window.location.assign( config.redirect );
			return;
		}

		if ( 'screen' === config.afterSend && this.thanks ) {
			this.hideAllExcept( [ this.thanks ].concat( Array.prototype.slice.call( this.form.querySelectorAll( '.svfs-keep' ) ) ) );
			this.thanks.hidden = false;
			this.setState( 'done' );
			this.reveal( this.thanks );
			return;
		}

		if ( this.form.querySelector( '.wpcf7c-elm-step3' ) ) {
			this.setState( 'done' );
			return;
		}

		// Multi-step forms go back to step 1; bring Contact Form 7's message into view.
		if ( this.steps ) {
			this.reveal( this.form.querySelector( '.wpcf7-response-output' ) || this.form );
		}
	};

	/* ------------------------------------------------------------------ */
	/* Wiring                                                              */
	/* ------------------------------------------------------------------ */

	function controllerFor( form ) {
		if ( ! form || 'FORM' !== form.tagName || ! form.hasAttribute( 'data-svfs' ) ) {
			return null;
		}
		var controller = controllers.get( form );
		if ( ! controller ) {
			var config;
			try {
				config = JSON.parse( form.getAttribute( 'data-svfs' ) ) || {};
			} catch ( e ) {
				return null;
			}
			controller = new Controller( form, config );
			controllers.set( form, controller );
		}
		return controller;
	}

	function init() {
		document.querySelectorAll( 'form.wpcf7-form[data-svfs]' ).forEach( controllerFor );
	}

	// Capture phase runs before Contact Form 7's own submit listener on the form.
	document.addEventListener( 'submit', function ( event ) {
		var controller = controllerFor( event.target );
		if ( controller && ! controller.holdForToken( event ) ) {
			controller.beforeSubmit( event );
		}
	}, true );

	document.addEventListener( 'wpcf7beforesubmit', function ( event ) {
		var controller = controllerFor( event.target );
		if ( controller ) {
			controller.trimRequest( event.detail );
		}
	} );

	// Forms added after page load (popups, tabs, page builders) set themselves up
	// as soon as the visitor focuses a field.
	document.addEventListener( 'focusin', function ( event ) {
		var form = event.target && event.target.closest ? event.target.closest( 'form.wpcf7-form[data-svfs]' ) : null;
		if ( form ) {
			controllerFor( form );
		}
	}, true );

	document.addEventListener( 'wpcf7submit', function ( event ) {
		var controller = controllerFor( event.target );
		var status = event.detail ? event.detail.status : '';
		if ( ! controller ) {
			return;
		}
		if ( 'svfs_confirm' === status ) {
			controller.enterReview();
		} else if ( 'svfs_step' === status ) {
			controller.goTo( controller.stepIndex + 1, true );
		}
	} );

	document.addEventListener( 'wpcf7invalid', function ( event ) {
		var controller = controllerFor( event.target );
		if ( controller ) {
			controller.onInvalid( event.detail );
		}
	} );

	document.addEventListener( 'wpcf7unaccepted', function ( event ) {
		var controller = controllerFor( event.target );
		if ( controller ) {
			controller.onInvalid();
		}
	} );

	document.addEventListener( 'wpcf7mailsent', function ( event ) {
		var controller = controllerFor( event.target );
		if ( controller ) {
			controller.onSent( event.detail );
		}
	} );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Lets page builders that insert forms later initialise them.
	window.svfsInit = function ( form ) {
		return !! controllerFor( form );
	};
}() );
