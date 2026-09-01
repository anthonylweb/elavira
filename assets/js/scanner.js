( function ( root, factory ) {
	'use strict';

	var api = factory( root );
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	} else if ( root.ElaviraScanConfig ) {
		api.run( root.document, root.ElaviraScanConfig );
	}
}( typeof window !== 'undefined' ? window : globalThis, function ( root ) {
	'use strict';

	function text( node ) {
		return ( node.textContent || '' ).replace( /\s+/g, ' ' ).trim();
	}

	function accessibleText( node, doc ) {
		var aria = ( node.getAttribute( 'aria-label' ) || '' ).trim();
		if ( aria ) { return aria; }
		var references = ( node.getAttribute( 'aria-labelledby' ) || '' ).trim().split( /\s+/ ).filter( Boolean );
		var referenced = references.map( function ( id ) { var source = doc.getElementById ? doc.getElementById( id ) : null; return source ? text( source ) : ''; } ).filter( Boolean ).join( ' ' );
		return referenced || text( node );
	}

	function visible( node ) {
		if ( node.hidden || ( node.closest && node.closest( '[hidden],[aria-hidden="true"]' ) ) ) { return false; }
		if ( root.getComputedStyle ) { var style = root.getComputedStyle( node ); return style.display !== 'none' && style.visibility !== 'hidden'; }
		return true;
	}

	function selector( node ) {
		var parts = [], current = node;
		while ( current && current.nodeType === 1 && parts.length < 6 ) {
			var part = current.tagName.toLowerCase();
			if ( current.id && /^[A-Za-z][\w:-]{0,180}$/.test( current.id ) ) {
				part += '#' + current.id.replace( /([^\w-])/g, '\\$1' );
				parts.unshift( part );
				break;
			}
			var siblings = current.parentElement ? Array.prototype.filter.call( current.parentElement.children, function ( sibling ) { return sibling.tagName === current.tagName; } ) : [];
			if ( siblings.length > 1 ) { part += ':nth-of-type(' + ( siblings.indexOf( current ) + 1 ) + ')'; }
			parts.unshift( part ); current = current.parentElement;
		}
		return parts.join( ' > ' ).slice( 0, 512 );
	}

	function base( node ) {
		return { target: { selector: selector( node ), tag_name: node.tagName.toLowerCase() }, element: { tag_name: node.tagName.toLowerCase() } };
	}

	function result( id, outcome, node, evidence, grouping ) {
		var item = base( node );
		return { rule_id: id, rule_version: '1.0.0', outcome: outcome, target: item.target, evidence: evidence, element: item.element, grouping: grouping || {} };
	}

	function hasName( node, doc ) {
		var aria = ( node.getAttribute( 'aria-label' ) || '' ).trim();
		if ( aria || node.hasAttribute( 'aria-labelledby' ) || ( node.getAttribute( 'title' ) || '' ).trim() ) { return true; }
		if ( node.labels && node.labels.length ) { return true; }
		if ( node.id && doc.querySelector( 'label[for="' + node.id.replace( /(["\\])/g, '\\$1' ) + '"]' ) ) { return true; }
		if ( node.closest && node.closest( 'label' ) ) { return true; }
		if ( node.tagName.toLowerCase() === 'button' && ( text( node ) || ( node.querySelector && node.querySelector( 'img[alt]:not([alt=""])' ) ) ) ) { return true; }
		if ( node.tagName.toLowerCase() === 'input' && node.type === 'image' && ( node.getAttribute( 'alt' ) || '' ).trim() ) { return true; }
		if ( node.tagName.toLowerCase() === 'input' && ( node.getAttribute( 'placeholder' ) || '' ).trim() ) { return true; }
		if ( node.tagName.toLowerCase() === 'input' && /^(?:button|submit|reset)$/.test( node.type ) && ( node.getAttribute( 'value' ) || '' ).trim() ) { return true; }
		return false;
	}

	function controlType( node ) {
		var tag = node.tagName.toLowerCase();
		if ( tag !== 'input' ) { return tag; }
		return /^(checkbox|radio|file)$/.test( node.type ) ? node.type : 'input';
	}

	function destinationKind( node, location ) {
		var href = node.getAttribute( 'href' ) || '';
		if ( href.charAt( 0 ) === '#' ) { return 'fragment'; }
		try { return new URL( href, location.href ).origin === location.origin ? 'internal' : 'external'; } catch ( error ) { return 'unknown'; }
	}

	function evaluate( doc ) {
		var results = [];
		Array.prototype.forEach.call( doc.querySelectorAll( 'img:not([alt])' ), function ( node ) {
			if ( visible( node ) ) { results.push( result( 'elavira.image.alt_missing', 'failure', node, { alt_state: 'missing' } ) ); }
		} );
		Array.prototype.forEach.call( doc.querySelectorAll( 'input:not([type="hidden"]), select, textarea, button' ), function ( node ) {
			if ( visible( node ) && ! hasName( node, doc ) ) {
				var type = controlType( node ), item = result( 'elavira.form.accessible_name_missing', 'failure', node, { control_type: type, accessible_name_state: 'missing' }, { control_type: type } );
				item.element.input_type = ( node.type || '' ).slice( 0, 32 ); item.element.has_accessible_name = false; results.push( item );
			}
		} );
		Array.prototype.forEach.call( doc.querySelectorAll( 'a[href]' ), function ( node ) {
			if ( ! visible( node ) ) { return; }
			var value = accessibleText( node, doc ).toLowerCase(), state = '';
			if ( ! value ) { state = 'empty'; } else if ( /^(?:click here|here|read more|more|learn more)$/.test( value ) ) { state = 'generic'; }
			if ( state ) { results.push( result( 'elavira.link.purpose_review', 'review_required', node, { text_state: state, destination_kind: destinationKind( node, doc.location ) }, { text_state: state } ) ); }
		} );
		var previous = 0;
		Array.prototype.forEach.call( doc.querySelectorAll( 'h1,h2,h3,h4,h5,h6' ), function ( node ) {
			if ( ! visible( node ) ) { return; }
			var current = parseInt( node.tagName.slice( 1 ), 10 );
			if ( previous && current > previous + 1 ) { results.push( result( 'elavira.heading.structure_review', 'review_required', node, { current_level: current, previous_level: previous, issue: 'skipped_level' }, { issue: 'skipped_level' } ) ); }
			previous = current;
		} );
		var html = doc.documentElement, language = html.getAttribute( 'lang' );
		if ( language === null || language.trim() === '' ) { results.push( result( 'elavira.document.language_missing', 'failure', html, { language_state: language === null ? 'missing' : 'empty' } ) ); }
		return results;
	}

	function waitForReady( doc, limits, Observer, timers ) {
		return new Promise( function ( resolve, reject ) {
			var observer, quietTimer, stabilizationTimer, absoluteTimer, loadHandler, settled = false;
			function cleanup() { if ( observer ) { observer.disconnect(); } if ( loadHandler && root.removeEventListener ) { root.removeEventListener( 'load', loadHandler ); } timers.clearTimeout( quietTimer ); timers.clearTimeout( stabilizationTimer ); timers.clearTimeout( absoluteTimer ); }
			function done() { if ( settled ) { return; } settled = true; cleanup(); resolve(); }
			function stabilize() {
				observer = new Observer( function () { timers.clearTimeout( quietTimer ); quietTimer = timers.setTimeout( done, limits.quietMs ); } );
				observer.observe( doc.documentElement, { childList: true, subtree: true, attributes: true } );
				quietTimer = timers.setTimeout( done, limits.quietMs ); stabilizationTimer = timers.setTimeout( done, limits.stabilizeMs );
			}
			absoluteTimer = timers.setTimeout( function () { if ( settled ) { return; } settled = true; cleanup(); reject( new Error( 'readiness_timeout' ) ); }, limits.readyMaxMs );
			if ( doc.readyState === 'complete' ) { stabilize(); } else { loadHandler = stabilize; root.addEventListener( 'load', loadHandler, { once: true } ); }
		} );
	}

	function statusUi( doc, messages ) {
		var region = doc.createElement( 'aside' ); region.className = 'elavira-scan-status'; region.setAttribute( 'aria-label', messages.ariaLabel ); region.setAttribute( 'aria-live', 'polite' ); region.setAttribute( 'aria-atomic', 'true' );
		var label = doc.createElement( 'strong' ); label.textContent = 'Elavira'; var message = doc.createElement( 'p' ); region.appendChild( label ); region.appendChild( message ); doc.body.appendChild( region );
		return { set: function ( key ) { message.textContent = messages[ key ]; }, complete: function ( url ) { var link = doc.createElement( 'a' ); link.href = url; link.textContent = messages.view; region.appendChild( link ); } };
	}

	function batches( results, size ) {
		var output = [];
		for ( var offset = 0; offset < results.length; offset += size ) { output.push( results.slice( offset, offset + size ) ); }
		return output;
	}

	async function request( config, endpoint, body ) {
		var response = await root.fetch( config.restRoot + endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce }, body: JSON.stringify( body ) } );
		var data = await response.json(); if ( ! response.ok ) { throw new Error( data.message || 'transport_error' ); } return data;
	}

	async function run( doc, config ) {
		var ui = statusUi( doc, config.messages ); ui.set( 'preparing' );
		try {
			await waitForReady( doc, config.limits, root.MutationObserver, root ); ui.set( 'scanning' ); var results = evaluate( doc ); ui.set( 'saving' );
			var resultBatches = batches( results, config.limits.batch );
			for ( var offset = 0; offset < resultBatches.length; offset++ ) { await request( config, 'batches', { session: config.session, results: resultBatches[ offset ] } ); }
			var completed = await request( config, 'complete', { session: config.session } ); ui.set( 'completed' ); ui.complete( completed.destination );
		} catch ( error ) {
			ui.set( 'failed' ); try { await request( config, 'fail', { session: config.session, code: error.message === 'readiness_timeout' ? 'readiness_timeout' : 'scanner_error' } ); } catch ( ignored ) {}
		}
	}

	return { evaluate: evaluate, waitForReady: waitForReady, run: run, selector: selector, batches: batches };
} ) );
