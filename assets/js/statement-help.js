/* Statement field guidance: button-triggered, hoverable overlay panels. */
( function () {
	'use strict';
	document.querySelectorAll( '.elavira-field-help' ).forEach( function ( group ) {
		const button = group.querySelector( 'button' );
		const hint = group.querySelector( '.elavira-field-hint' );
		let pinned = false;
		let dismissed = false;
		let hovering = false;
		let closeTimer;
		function show( open ) {
			hint.hidden = ! open;
			button.setAttribute( 'aria-expanded', String( open ) );
		}
		group.classList.add( 'elavira-field-help-enhanced' );
		show( false );
		button.hidden = false;
		function enter( event ) {
			if ( event.pointerType === 'touch' ) { return; }
			clearTimeout( closeTimer );
			hovering = true;
			if ( ! dismissed ) { show( true ); }
		}
		function leave() {
			hovering = false;
			// Allow travel from the button to its panel without closing it en route.
			closeTimer = setTimeout( function () {
				if ( ! hovering && document.activeElement !== button ) {
					dismissed = false;
					if ( ! pinned ) { show( false ); }
				}
			}, 150 );
		}
		button.addEventListener( 'pointerenter', enter );
		button.addEventListener( 'pointerleave', leave );
		hint.addEventListener( 'pointerenter', enter );
		hint.addEventListener( 'pointerleave', leave );
		button.addEventListener( 'focus', function () {
			if ( ! dismissed ) { show( true ); }
		} );
		button.addEventListener( 'blur', function () {
			if ( ! hovering ) {
				dismissed = false;
				if ( ! pinned ) { show( false ); }
			}
		} );
		button.addEventListener( 'click', function () {
			pinned = ! pinned;
			dismissed = ! pinned;
			show( pinned );
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && ! hint.hidden ) {
				pinned = false;
				dismissed = true;
				show( false );
			}
		} );
		document.addEventListener( 'pointerdown', function ( event ) {
			if ( ! group.contains( event.target ) ) {
				pinned = false;
				dismissed = false;
				show( false );
			}
		} );
	} );
}() );
