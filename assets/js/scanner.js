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
		if ( referenced ) { return referenced; }
		var content = text( node );
		if ( content ) { return content; }
		var images = node.querySelectorAll ? node.querySelectorAll( 'img[alt]' ) : [];
		var alternatives = Array.prototype.map.call( images, function ( image ) { return ( image.getAttribute( 'alt' ) || '' ).trim(); } ).filter( Boolean ).join( ' ' );
		return alternatives || ( node.getAttribute( 'title' ) || '' ).trim();
	}

	function normalizeName( value ) {
		return value.replace( /\s+/g, ' ' ).trim().toLowerCase().slice( 0, 256 );
	}

	function nameExcerpt( value ) {
		return value.replace( /[<>]/g, '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 120 );
	}

	function evidenceText( value, length ) {
		return value.replace( /[<>]/g, '' ).replace( /\s+/g, ' ' ).trim().slice( 0, length );
	}

	function fingerprint( value ) {
		var hash = 2166136261;
		for ( var index = 0; index < value.length; index++ ) { hash ^= value.charCodeAt( index ); hash = Math.imul( hash, 16777619 ); }
		return ( '00000000' + ( hash >>> 0 ).toString( 16 ) ).slice( -8 );
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
		var target = { selector: selector( node ), tag_name: node.tagName.toLowerCase(), identity_version: 2 };
		if ( node.id && node.id.length <= 191 ) { target.element_id = node.id; }
		var classes = node.getAttribute( 'class' ) || '';
		if ( classes ) { target.element_classes = classes.slice( 0, 512 ); }
		return { target: target, element: identification( node ) };
	}

	// Identification only: never read form values, serialize markup, or collect large containers.
	function identifyingText( node ) {
		var parts = [], visited = 0, size = 0;
		function walk( current, depth ) {
			if ( ! current || ++visited > 64 || depth > 6 || size >= 160 ) { return; }
			if ( current.nodeType === 3 ) { var value = String( current.nodeValue || '' ).slice( 0, 160 - size ); parts.push( value ); size += value.length; return; }
			if ( ! visible( current ) || /^(INPUT|TEXTAREA|SELECT|SCRIPT|STYLE|NOSCRIPT)$/.test( current.tagName ) || ( current.hasAttribute && current.hasAttribute( 'contenteditable' ) ) ) { return; }
			Array.prototype.some.call( current.childNodes || [], function ( child ) { walk( child, depth + 1 ); return visited >= 64 || size >= 160; } );
		}
		walk( node, 0 );
		return evidenceText( parts.join( ' ' ), 160 );
	}

	function identifyingUrl( value, node, filename ) {
		if ( ! value ) { return ''; }
		try {
			var doc = node.ownerDocument || root.document, url = new URL( value, doc && ( doc.baseURI || doc.location && doc.location.href ) );
			if ( ! /^https?:$/.test( url.protocol ) ) { return ''; }
			var display = filename ? url.pathname.split( '/' ).pop() : url.origin + url.pathname;
			return display.length <= 512 ? display : '';
		} catch ( error ) { return ''; }
	}

	function identification( node ) {
		var tag = node.tagName.toLowerCase(), item = { tag_name: tag }, link = tag === 'a' && node.hasAttribute( 'href' ) ? node : tag === 'img' && node.closest ? node.closest( 'a[href]' ) : null;
		var image = tag === 'img' ? node : null;
		if ( link ) {
			var content = identifyingText( link ), images = link.querySelectorAll ? link.querySelectorAll( 'img' ) : [];
			if ( ! image && images.length === 1 && visible( images[ 0 ] ) ) { image = images[ 0 ]; }
			item.link_kind = ! text( link ) && images.length === 1 && image ? 'image_only' : content && ! images.length ? 'text' : 'mixed_or_unknown';
			item.link_destination = identifyingUrl( link.getAttribute( 'href' ), link, false );
			if ( content ) { item.text_excerpt = content; }
			// Expose only simple available naming sources; complex referenced names remain technical review.
			if ( ! link.hasAttribute( 'aria-labelledby' ) ) {
				var name = link.getAttribute( 'aria-label' ) || ( item.link_kind === 'text' ? content : item.link_kind === 'image_only' ? image.getAttribute( 'alt' ) : '' );
				if ( name ) { item.link_name_excerpt = evidenceText( name, 160 ); }
			}
			var parent = link.parentElement;
			if ( parent && parent.tagName === 'P' ) { var context = identifyingText( parent ); if ( context && context !== content ) { item.context_excerpt = context; } }
		}
		if ( image ) {
			item.image_filename = identifyingUrl( image.currentSrc || image.getAttribute( 'src' ), image, true );
			item.image_alt_state = ! image.hasAttribute( 'alt' ) ? 'missing' : image.getAttribute( 'alt' ) === '' ? 'empty' : 'present';
			if ( item.image_alt_state === 'present' ) { item.image_alt_excerpt = evidenceText( image.getAttribute( 'alt' ), 160 ); }
		}
		if ( /^h[1-6]$/.test( tag ) || tag === 'button' ) { item.text_excerpt = identifyingText( node ); }
		if ( /^(input|select|textarea)$/.test( tag ) ) {
			item.input_type = ( node.type || tag ).slice( 0, 32 );
			var fieldName = node.getAttribute( 'name' );
			if ( fieldName ) { item.field_name = evidenceText( fieldName, 80 ); }
		}
		Object.keys( item ).forEach( function ( key ) { if ( item[ key ] === '' ) { delete item[ key ]; } } );
		return item;
	}

	function result( id, outcome, node, evidence, grouping, version ) {
		var item = base( node );
		return { rule_id: id, rule_version: version || '1.0.0', outcome: outcome, target: item.target, evidence: evidence, element: item.element, grouping: grouping || {} };
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

	function namingSource( node, doc ) {
		var labelledby = ( node.getAttribute( 'aria-labelledby' ) || '' ).trim();
		if ( labelledby ) {
			var names = labelledby.split( /\s+/ ).map( function ( id ) { var sources = doc.querySelectorAll ? doc.querySelectorAll( '[id="' + id.replace( /(["\\])/g, '\\$1' ) + '"]' ) : []; return sources.length === 1 ? text( sources[ 0 ] ) : ''; } ).filter( Boolean );
			if ( names.length ) { return 'aria_labelledby'; }
		}
		if ( ( node.getAttribute( 'aria-label' ) || '' ).trim() ) { return 'aria_label'; }
		if ( node.labels && Array.prototype.some.call( node.labels, function ( label ) { return Boolean( text( label ) ); } ) ) { return 'native_label'; }
		if ( node.closest ) { var parentLabel = node.closest( 'label' ); if ( parentLabel && text( parentLabel ) ) { return 'native_label'; } }
		var tag = node.tagName.toLowerCase();
		if ( tag === 'button' && text( node ) ) { return 'contents'; }
		if ( tag === 'input' && node.type === 'image' && ( node.getAttribute( 'alt' ) || '' ).trim() ) { return 'alt'; }
		if ( tag === 'input' && /^(?:button|submit|reset)$/.test( node.type ) && ( node.getAttribute( 'value' ) || '' ).trim() ) { return 'value'; }
		if ( ( node.getAttribute( 'title' ) || '' ).trim() ) { return 'title'; }
		if ( /^(?:input|textarea)$/.test( tag ) && ( node.getAttribute( 'placeholder' ) || '' ).trim() ) { return 'placeholder'; }
		return 'none';
	}

	function controlType( node ) {
		var tag = node.tagName.toLowerCase();
		if ( tag !== 'input' ) { return tag; }
		return /^(checkbox|radio|file)$/.test( node.type ) ? node.type : 'input';
	}

	function destinationKind( node, location ) {
		var href = node.getAttribute( 'href' ) || '';
		if ( href.charAt( 0 ) === '#' ) { return 'fragment'; }
		if ( /^(?:mailto|tel):/i.test( href ) ) { return 'other'; }
		try { return new URL( href, location.href ).origin === location.origin ? 'internal' : 'external'; } catch ( error ) { return 'unknown'; }
	}

	function destinationKey( node, location ) {
		try { return new URL( node.getAttribute( 'href' ) || '', location.href ).href; } catch ( error ) { return node.getAttribute( 'href' ) || ''; }
	}

	function languageState( value ) {
		if ( ! /^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/.test( value ) ) { return 'malformed'; }
		var primary = value.split( '-' )[ 0 ].toLowerCase();
		return ( root.ElaviraLanguagePrimarySubtags || [] ).indexOf( primary ) === -1 ? 'unknown_primary' : 'valid';
	}

	function explicitRole( node, vocabulary ) {
		var tokens = ( node.getAttribute( 'role' ) || '' ).trim().toLowerCase().split( /\s+/ ).filter( Boolean );
		for ( var index = 0; index < tokens.length; index++ ) { if ( vocabulary.roles.indexOf( tokens[ index ] ) !== -1 ) { return tokens[ index ]; } }
		return '';
	}

	function nativeRole( node ) {
		var tag = node.tagName.toLowerCase();
		if ( tag === 'a' && node.hasAttribute( 'href' ) ) { return 'link'; }
		if ( tag === 'button' ) { return 'button'; }
		if ( tag === 'select' ) { return 'combobox'; }
		if ( tag === 'textarea' ) { return 'textbox'; }
		if ( tag === 'input' ) { return { checkbox: 'checkbox', radio: 'radio', range: 'slider', search: 'searchbox', button: 'button', submit: 'button', reset: 'button' }[ node.type ] || ( node.type === 'hidden' ? '' : 'textbox' ); }
		return '';
	}

	function focusableKind( node ) {
		var tag = node.tagName.toLowerCase();
		if ( node.hasAttribute( 'disabled' ) || node.hasAttribute( 'hidden' ) || ( node.closest && node.closest( '[inert]' ) ) ) { return '' ; }
		var view = node.ownerDocument && node.ownerDocument.defaultView, style = view && view.getComputedStyle ? view.getComputedStyle( node ) : null;
		if ( style && ( style.display === 'none' || style.visibility === 'hidden' || style.visibility === 'collapse' ) ) { return ''; }
		if ( tag === 'a' && node.hasAttribute( 'href' ) ) { return 'link'; }
		if ( tag === 'button' ) { return 'button'; }
		if ( tag === 'input' && node.type !== 'hidden' ) { return 'input'; }
		if ( tag === 'select' || tag === 'textarea' ) { return tag; }
		if ( /^(audio|video)$/.test( tag ) && node.hasAttribute( 'controls' ) ) { return 'media'; }
		var tabindex = node.getAttribute( 'tabindex' ); return tabindex !== null && parseInt( tabindex, 10 ) >= 0 ? 'tabindex' : '';
	}

	function ariaValueReason( name, value, vocabulary ) {
		if ( vocabulary.booleanAttributes.indexOf( name ) !== -1 && ! /^(?:true|false)$/.test( value ) ) { return 'invalid_boolean'; }
		if ( vocabulary.tristateAttributes.indexOf( name ) !== -1 && ! /^(?:true|false|mixed)$/.test( value ) ) { return 'invalid_tristate'; }
		if ( name === 'aria-level' && ! /^[1-9][0-9]*$/.test( value ) ) { return 'invalid_integer'; }
		if ( /^(?:aria-valuemin|aria-valuemax|aria-valuenow)$/.test( name ) && ( value === '' || ! isFinite( Number( value ) ) ) ) { return 'invalid_number'; }
		if ( vocabulary.enumeratedAttributes && vocabulary.enumeratedAttributes[ name ] && vocabulary.enumeratedAttributes[ name ].indexOf( value ) === -1 ) { return 'invalid_token'; }
		if ( Object.prototype.hasOwnProperty.call( vocabulary.idReferences, name ) && ! value.trim() ) { return 'empty_reference'; }
		return '';
	}

	function roundThree( doc, results ) {
		var vocabulary = root.ElaviraAriaVocabulary || { roles: [], attributes: [], idReferences: {}, booleanAttributes: [], tristateAttributes: [], requiredByRole: {}, nameProhibitedRoles: [] };
		var all = Array.prototype.slice.call( doc.querySelectorAll( '*' ) || [] ), ids = {};
		all.forEach( function ( node ) { var id = node.id || ''; if ( id ) { ids[ id ] = ids[ id ] || []; ids[ id ].push( node ); } } );
		Array.prototype.forEach.call( doc.querySelectorAll( 'label[for]' ) || [], function ( label ) {
			var token = ( label.getAttribute( 'for' ) || '' ).trim(), matches = token ? ( ids[ token ] || [] ) : [], state = ! token ? 'empty' : ! matches.length ? 'missing' : matches.length > 1 ? 'duplicate' : ! /^(?:button|input|meter|output|progress|select|textarea)$/.test( matches[ 0 ].tagName.toLowerCase() ) ? 'not_labelable' : '';
			if ( state ) { var target = matches[ 0 ] || label; results.push( result( 'elavira.forms.broken_label_reference', 'potential_issue', target, { reference_state: state, control_type: target.tagName.toLowerCase() } ) ); }
		} );
		all.forEach( function ( node ) {
			if ( ! visible( node ) ) { return; }
			var roleText = ( node.getAttribute( 'role' ) || '' ).trim().toLowerCase(), role = explicitRole( node, vocabulary ), native = nativeRole( node );
			if ( roleText && ! role ) { results.push( result( 'elavira.semantics.invalid_role', 'potential_issue', node, { role_state: 'no_valid_token', first_invalid_token: evidenceText( roleText.split( /\s+/ )[ 0 ], 64 ), native_role_state: native ? 'available' : 'none' } ) ); }
			if ( role && native && role === native ) { results.push( result( 'elavira.aria.redundant_role', 'potential_issue', node, { native_role: native, explicit_role: role } ) ); }
			var presentation = role === 'presentation' || role === 'none';
			var presentationIgnored = presentation && ( !! focusableKind( node ) || Array.prototype.some.call( node.attributes || [], function ( attribute ) { return /^(aria-atomic|aria-busy|aria-controls|aria-current|aria-describedby|aria-details|aria-disabled|aria-dropeffect|aria-errormessage|aria-flowto|aria-grabbed|aria-haspopup|aria-hidden|aria-invalid|aria-keyshortcuts|aria-label|aria-labelledby|aria-live|aria-owns|aria-relevant|aria-roledescription)$/.test( attribute.name ); } ) );
			if ( role && native && role !== native && ! presentationIgnored && [ 'button', 'checkbox', 'combobox', 'link', 'radio', 'searchbox', 'slider', 'textbox' ].indexOf( native ) !== -1 ) { results.push( result( 'elavira.semantics.native_semantics_overridden', 'potential_issue', node, { native_role: native, explicit_role: role } ) ); }
			var effectiveRole = presentationIgnored ? native : ( role || native ), required = role && ! presentationIgnored && role !== native ? ( vocabulary.requiredByRole[ effectiveRole ] || [] ) : [];
			required.forEach( function ( attribute ) {
				var value = node.getAttribute( attribute ), invalid = value !== null ? ariaValueReason( attribute, value.trim().toLowerCase(), vocabulary ) : '';
				if ( value === null ) { results.push( result( 'elavira.aria.required_attribute_missing', 'potential_issue', node, { role: effectiveRole, attribute_name: attribute } ) ); results.push( result( 'elavira.semantics.required_state_missing', 'automatic_failure', node, { role: effectiveRole, required_attribute: attribute, state_reason: 'missing' } ) ); }
				else if ( invalid ) { results.push( result( 'elavira.semantics.required_state_missing', 'automatic_failure', node, { role: effectiveRole, required_attribute: attribute, state_reason: 'invalid' } ) ); }
			} );
			if ( role && [ 'button', 'checkbox', 'combobox', 'link', 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'option', 'radio', 'searchbox', 'slider', 'spinbutton', 'switch', 'tab', 'textbox', 'treeitem' ].indexOf( role ) !== -1 && namingSource( node, doc ) === 'none' ) { results.push( result( 'elavira.semantics.control_missing_name', 'automatic_failure', node, { role: role, name_state: 'missing' } ) ); }
			var attributes = Array.prototype.slice.call( node.attributes || [] );
			attributes.forEach( function ( attribute ) {
				var name = attribute.name.toLowerCase(), value = attribute.value.trim().toLowerCase();
				if ( name.indexOf( 'aria-' ) !== 0 ) { return; }
				if ( vocabulary.attributes.indexOf( name ) === -1 ) { results.push( result( 'elavira.aria.invalid_attribute', 'potential_issue', node, { attribute_name: evidenceText( name, 64 ), reason: 'unknown' } ) ); return; }
				var reason = ariaValueReason( name, value, vocabulary ); if ( reason ) { results.push( result( 'elavira.aria.invalid_value', 'potential_issue', node, { attribute_name: name, value_reason: reason } ) ); }
				if ( ! presentationIgnored && vocabulary.nameProhibitedRoles.indexOf( role ) !== -1 && /^(?:aria-label|aria-labelledby)$/.test( name ) ) { results.push( result( 'elavira.aria.prohibited_attribute', 'potential_issue', node, { role: role, attribute_name: name } ) ); }
				if ( Object.prototype.hasOwnProperty.call( vocabulary.idReferences, name ) ) {
					var tokens = attribute.value.trim().split( /\s+/ ).filter( Boolean ), broken = ! tokens.length ? 'empty' : '';
					for ( var i = 0; ! broken && i < tokens.length; i++ ) { var matches = ids[ tokens[ i ] ] || []; if ( ! matches.length ) { broken = 'missing'; } else if ( matches.length > 1 ) { broken = 'duplicate'; results.push( result( 'elavira.aria.duplicate_id_reference', 'potential_issue', node, { attribute_name: name, duplicate_count: matches.length } ) ); } else if ( /^(?:aria-labelledby|aria-describedby)$/.test( name ) && ! text( matches[ 0 ] ) ) { broken = 'no_usable_text'; } }
					if ( broken ) { results.push( result( 'elavira.aria.broken_id_reference', 'potential_issue', node, { attribute_name: name, reference_state: broken, token_count: tokens.length } ) ); }
				}
				if ( [ 'aria-checked', 'aria-expanded', 'aria-pressed', 'aria-selected', 'aria-valuenow' ].indexOf( name ) !== -1 && ! reason && role ) { results.push( result( 'elavira.semantics.state_consistency_review', 'human_review', node, { role: role, state_attribute: name } ) ); }
			} );
		} );
		Array.prototype.forEach.call( doc.querySelectorAll( '[aria-hidden="true"]' ) || [], function ( hidden ) { Array.prototype.some.call( hidden.querySelectorAll ? hidden.querySelectorAll( '*' ) : [], function ( child ) { var kind = focusableKind( child ); if ( kind ) { results.push( result( 'elavira.aria.hidden_focusable_content', 'potential_issue', child, { focusable_kind: kind, hidden_source: 'aria_hidden' } ) ); return true; } return false; } ); } );
		Array.prototype.forEach.call( doc.querySelectorAll( 'form' ) || [], function ( form ) { var trigger = form.querySelector && form.querySelector( '[aria-invalid="true"]' ) ? 'aria_invalid' : form.querySelector && form.querySelector( '[role="alert"]' ) ? 'alert' : form.querySelector && form.querySelector( '[aria-live]' ) ? 'live_region' : ''; if ( trigger ) { results.push( result( 'elavira.forms.error_identification_review', 'human_review', form, { trigger: trigger } ) ); } } );
	}

	function tabindexCategory( node ) {
		var raw = node.getAttribute( 'tabindex' );
		if ( raw === null || raw.trim() === '' || ! /^-?\d+$/.test( raw.trim() ) ) { return 'missing'; }
		var value = parseInt( raw, 10 );
		return value > 0 ? 'positive' : value < 0 ? 'negative' : 'zero';
	}

	function authorFocusSuppression( doc ) {
		var matched = [];
		Array.prototype.forEach.call( doc.styleSheets || [], function ( sheet ) {
			var rules;
			try { rules = sheet.cssRules || []; } catch ( error ) { return; }
			Array.prototype.forEach.call( rules, function ( rule ) {
				var css = rule.style, selectorText = rule.selectorText || '';
				if ( ! css || selectorText.indexOf( ':focus' ) === -1 ) { return; }
				var outline = ( css.getPropertyValue( 'outline' ) || '' ).toLowerCase();
				var removed = /^(?:none|0(?:px)?(?:\s|$))/.test( outline ) || css.getPropertyValue( 'outline-style' ) === 'none' || css.getPropertyValue( 'outline-width' ) === '0px';
				var alternative = css.getPropertyValue( 'box-shadow' ) || css.getPropertyValue( 'border-color' ) || css.getPropertyValue( 'border-width' ) || css.getPropertyValue( 'background-color' ) || css.getPropertyValue( 'text-decoration' );
				if ( ! removed || alternative ) { return; }
				var baseSelector = selectorText.replace( /:focus-visible|:focus-within|:focus/g, '' );
				try { Array.prototype.forEach.call( doc.querySelectorAll( baseSelector ), function ( node ) { if ( matched.indexOf( node ) === -1 ) { matched.push( node ); } } ); } catch ( error ) { return; }
			} );
		} );
		return matched;
	}

	function roundFour( doc, results ) {
		var vocabulary = root.ElaviraAriaVocabulary || { roles: [] };
		var interactiveRoles = [ 'button', 'checkbox', 'combobox', 'link', 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'option', 'radio', 'scrollbar', 'searchbox', 'slider', 'spinbutton', 'switch', 'tab', 'textbox', 'treeitem' ];
		var widgetRoles = [ 'combobox', 'grid', 'listbox', 'menu', 'menubar', 'radiogroup', 'scrollbar', 'slider', 'spinbutton', 'tablist', 'tree', 'treegrid' ];
		var nativeTags = [ 'a', 'button', 'input', 'select', 'textarea' ];
		var all = Array.prototype.slice.call( doc.querySelectorAll( '*' ) || [] );
		all.forEach( function ( node ) {
			if ( ! visible( node ) ) { return; }
			var tag = node.tagName.toLowerCase(), role = explicitRole( node, vocabulary ), category = tabindexCategory( node ), kind = focusableKind( node );
			if ( category === 'positive' ) {
				var tabValue = parseInt( node.getAttribute( 'tabindex' ), 10 );
				results.push( result( 'elavira.keyboard.positive_tabindex', 'human_review', node, { tabindex_category: 'positive', tabindex_value: tabValue, focusable_kind: kind || 'tabindex' } ) );
				results.push( result( 'elavira.focus.order_review', 'human_review', node, { order_trigger: 'positive_tabindex' } ) );
			}
			var custom = nativeTags.indexOf( tag ) === -1 && interactiveRoles.indexOf( role ) !== -1;
			if ( custom ) { results.push( result( 'elavira.keyboard.non_native_interactive', 'human_review', node, { role: role, interaction_signal: kind ? 'focusable_role' : 'interactive_role', tabindex_category: category } ) ); }
			if ( custom && ( category === 'missing' || category === 'negative' ) ) { results.push( result( 'elavira.keyboard.possible_unreachable_control', 'human_review', node, { role: role, focusability: category === 'negative' ? 'negative_tabindex' : 'no_tab_stop' } ) ); }
			if ( nativeTags.indexOf( tag ) === -1 && widgetRoles.indexOf( role ) !== -1 ) { results.push( result( 'elavira.keyboard.custom_widget_review', 'human_review', node, { role: role, widget_pattern: [ 'grid', 'listbox', 'menu', 'menubar', 'radiogroup', 'tablist', 'tree', 'treegrid' ].indexOf( role ) !== -1 ? 'composite' : 'specialized' } ) ); }
			if ( role === 'application' && node.getAttribute( 'aria-modal' ) !== 'true' ) {
				var focusable = Array.prototype.filter.call( node.querySelectorAll ? node.querySelectorAll( '*' ) : [], function ( child ) { return Boolean( focusableKind( child ) ); } );
				if ( focusable.length > 1 ) { results.push( result( 'elavira.keyboard.possible_trap', 'human_review', node, { structure: 'application_region', focusable_descendants: Math.min( focusable.length, 1000 ) } ) ); }
			}
			var dialogPattern = tag === 'dialog' && node.hasAttribute( 'open' ) ? 'native_open' : ( role === 'dialog' || role === 'alertdialog' ) && node.getAttribute( 'aria-modal' ) === 'true' ? 'aria_modal' : '';
			if ( dialogPattern ) { results.push( result( 'elavira.focus.modal_management_review', 'human_review', node, { dialog_pattern: dialogPattern, role: role || 'dialog' } ) ); }
			var view = doc.defaultView || root, style = view.getComputedStyle ? view.getComputedStyle( node ) : null, rect = node.getBoundingClientRect ? node.getBoundingClientRect() : null;
			if ( style && rect && ( style.position === 'fixed' || style.position === 'sticky' ) && style.zIndex !== 'auto' ) {
				var width = view.innerWidth || 0, height = view.innerHeight || 0, wide = width && rect.width >= width * 0.5, substantial = rect.height >= 48;
				var boundary = rect.top <= 1 ? 'top' : height && rect.bottom >= height - 1 ? 'bottom' : '';
				if ( wide && substantial && boundary ) { results.push( result( 'elavira.focus.obscured_review', 'human_review', node, { position: style.position, boundary: boundary, coverage: rect.width >= width * 0.8 ? 'most' : 'half' } ) ); }
			}
		} );
		authorFocusSuppression( doc ).forEach( function ( node ) { if ( visible( node ) && focusableKind( node ) ) { results.push( result( 'elavira.focus.possible_hidden_focus', 'human_review', node, { style_pattern: 'outline_removed_no_alternative', style_source: 'author_stylesheet' } ) ); } } );
	}

	function colorValue( value ) {
		var match = String( value || '' ).match( /^rgba?\(\s*(\d+)\D+(\d+)\D+(\d+)(?:\D+([\d.]+))?\s*\)$/i );
		return match ? { r: +match[ 1 ], g: +match[ 2 ], b: +match[ 3 ], a: match[ 4 ] === undefined ? 1 : +match[ 4 ] } : null;
	}
	function composite( front, back ) { var a = front.a + back.a * ( 1 - front.a ); return a ? { r: Math.round( ( front.r * front.a + back.r * back.a * ( 1 - front.a ) ) / a ), g: Math.round( ( front.g * front.a + back.g * back.a * ( 1 - front.a ) ) / a ), b: Math.round( ( front.b * front.a + back.b * back.a * ( 1 - front.a ) ) / a ), a: a } : null; }
	function hexColor( color ) { return '#' + [ color.r, color.g, color.b ].map( function ( value ) { return ( '0' + value.toString( 16 ) ).slice( -2 ); } ).join( '' ); }
	function luminance( color ) { var channels = [ color.r, color.g, color.b ].map( function ( value ) { value /= 255; return value <= 0.04045 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 ); } ); return 0.2126 * channels[ 0 ] + 0.7152 * channels[ 1 ] + 0.0722 * channels[ 2 ]; }
	function directText( node ) { return Array.prototype.some.call( node.childNodes || [], function ( child ) { return child.nodeType === 3 && String( child.nodeValue || '' ).trim(); } ); }
	function backgroundFor( node, view, documentNode ) {
		var layers = [], current = node, reachedRoot = false;
		for ( var depth = 0; current && depth < 12; depth++, current = current.parentElement ) {
			var style = view.getComputedStyle( current ), image = style.backgroundImage || 'none';
			if ( image !== 'none' ) { return { reason: image.indexOf( 'gradient' ) !== -1 ? 'background_gradient' : 'background_image' }; }
			if ( parseFloat( style.opacity || '1' ) !== 1 ) { return { reason: 'ancestor_opacity' }; }
			if ( ( style.filter && style.filter !== 'none' ) || ( style.mixBlendMode && style.mixBlendMode !== 'normal' ) || ( style.backgroundBlendMode && style.backgroundBlendMode !== 'normal' ) ) { return { reason: 'blend_or_filter' }; }
			var pseudoBefore = view.getComputedStyle( current, '::before' ), pseudoAfter = view.getComputedStyle( current, '::after' );
			if ( [ pseudoBefore, pseudoAfter ].some( function ( pseudo ) { return pseudo && pseudo.content && pseudo.content !== 'none' && pseudo.content !== 'normal' && ( pseudo.backgroundImage !== 'none' || ( colorValue( pseudo.backgroundColor ) || { a: 0 } ).a ); } ) ) { return { reason: 'pseudo_element_interference' }; }
			var parsed = colorValue( style.backgroundColor );
			if ( ! parsed ) { return { reason: 'unresolved_transparency' }; }
			layers.push( parsed );
			if ( parsed.a >= 1 ) { reachedRoot = true; break; }
			if ( current === documentNode.documentElement ) { reachedRoot = true; break; }
		}
		if ( ! reachedRoot && current ) { return { reason: 'complex_layering' }; }
		var resolved = { r: 255, g: 255, b: 255, a: 1 };
		for ( var i = layers.length - 1; i >= 0; i-- ) { resolved = composite( layers[ i ], resolved ); }
		return { color: resolved };
	}
	function roundFive( doc, results ) {
		var view = doc.defaultView || root, candidates = Array.prototype.slice.call( doc.querySelectorAll( 'p,span,a,button,label,li,td,th,h1,h2,h3,h4,h5,h6' ) || [] ).slice( 0, 500 );
		candidates.forEach( function ( node ) {
			if ( ! visible( node ) || ! view.getComputedStyle || ! directText( node ) ) { return; }
			var style = view.getComputedStyle( node ), size = parseFloat( style.fontSize ), weight = parseInt( style.fontWeight, 10 ) || ( style.fontWeight === 'bold' ? 700 : 400 ), large = size >= 24 || ( size >= 18.667 && weight >= 700 ), required = large ? 3000 : 4500, resolved = backgroundFor( node, view, doc );
			if ( resolved.reason ) { results.push( result( 'elavira.contrast.cannot_determine', 'human_review', node, { candidate: large ? 'large_text' : 'normal_text', reason: resolved.reason, required_milli: required, rendered_state: 'current' } ) ); return; }
			var background = resolved.color;
			var foreground = colorValue( style.color ); if ( ! foreground ) { results.push( result( 'elavira.contrast.cannot_determine', 'human_review', node, { candidate: large ? 'large_text' : 'normal_text', reason: 'unresolved_transparency', required_milli: required, rendered_state: 'current' } ) ); return; } var effective = composite( foreground, background ), first = luminance( effective ), second = luminance( background ), ratio = Math.round( ( Math.max( first, second ) + 0.05 ) / ( Math.min( first, second ) + 0.05 ) * 1000 );
			if ( ratio < required ) { results.push( result( large ? 'elavira.contrast.large_text_insufficient' : 'elavira.contrast.text_insufficient', 'automatic_failure', node, { foreground: hexColor( effective ), background: hexColor( background ), ratio_milli: ratio, required_milli: required, text_size: large ? 'large' : 'normal', font_size_milli: Math.round( size * 1000 ), font_weight: weight, determination: foreground.a < 1 ? 'composited_alpha' : 'opaque', rendered_state: 'current' } ) ); }
		} );
		Array.prototype.forEach.call( doc.querySelectorAll( 'input[type="checkbox"],input[type="radio"]' ) || [], function ( node ) {
			if ( ! visible( node ) || ! view.getComputedStyle ) { return; }
			var style = view.getComputedStyle( node ), appearance = style.appearance || style.webkitAppearance || '';
			if ( appearance !== 'none' ) { return; }
			var outside = node.parentElement ? backgroundFor( node.parentElement, view, doc ) : { reason: 'complex_layering' };
			var fill = colorValue( style.backgroundColor ), borderColors = [ style.borderTopColor, style.borderRightColor, style.borderBottomColor, style.borderLeftColor ], borderWidths = [ style.borderTopWidth, style.borderRightWidth, style.borderBottomWidth, style.borderLeftWidth ], border = colorValue( borderColors[ 0 ] ), uniformBorder = borderColors.every( function ( color ) { return color === borderColors[ 0 ]; } ) && borderWidths.every( function ( width ) { return parseFloat( width || '0' ) >= 1; } );
			if ( outside.reason || ! fill || ! border || fill.a !== 1 || border.a !== 1 || ! uniformBorder || style.backgroundImage !== 'none' || parseFloat( style.opacity || '1' ) !== 1 ) {
				results.push( result( 'elavira.contrast.cannot_determine', 'human_review', node, { candidate: 'non_text', reason: outside.reason || 'complex_layering', required_milli: 3000, rendered_state: 'current' } ) ); return;
			}
			var fillRatio = Math.round( ( Math.max( luminance( fill ), luminance( outside.color ) ) + 0.05 ) / ( Math.min( luminance( fill ), luminance( outside.color ) ) + 0.05 ) * 1000 );
			var borderRatio = Math.round( ( Math.max( luminance( border ), luminance( outside.color ) ) + 0.05 ) / ( Math.min( luminance( border ), luminance( outside.color ) ) + 0.05 ) * 1000 );
			var visual = borderRatio >= fillRatio ? border : fill, ratio = Math.max( borderRatio, fillRatio );
			if ( ratio < 3000 ) { results.push( result( 'elavira.contrast.non_text_insufficient', 'automatic_failure', node, { foreground: hexColor( visual ), background: hexColor( outside.color ), ratio_milli: ratio, required_milli: 3000, visual_category: 'essential_component', determination: 'opaque', rendered_state: 'current' } ) ); }
		} );
		Array.prototype.forEach.call( doc.querySelectorAll( '[aria-invalid="true"]' ) || [], function ( node ) { if ( visible( node ) && ! node.hasAttribute( 'aria-describedby' ) && ! node.hasAttribute( 'aria-errormessage' ) ) { results.push( result( 'elavira.color.color_only_review', 'human_review', node, { meaning: 'error_status', non_color_cue: 'no_referenced_message', rendered_state: 'current' } ) ); } } );
	}

	function imageContext( image ) {
		var parent = image.closest ? image.closest( 'a[href],button,[role="button"],[role="link"]' ) : null;
		if ( parent ) { return parent.tagName && parent.tagName.toLowerCase() === 'a' || parent.getAttribute( 'role' ) === 'link' ? 'functional_link' : 'functional_control'; }
		var figure = image.closest ? image.closest( 'figure' ) : null;
		return figure && figure.querySelector && text( figure.querySelector( 'figcaption' ) || {} ) ? 'figure_caption' : 'standalone';
	}

	function filenamePattern( value ) {
		if ( /^(?:img|dsc)[_-]?\d{3,}\.(?:avif|gif|jpe?g|png|svg|webp)$/i.test( value ) ) { return 'camera_filename'; }
		return /^[^\s\\/]{1,100}\.(?:avif|gif|jpe?g|png|svg|webp)$/i.test( value ) ? 'image_extension' : '';
	}

	function roundSix( doc, results ) {
		Array.prototype.forEach.call( doc.querySelectorAll( 'img[alt]' ) || [], function ( image ) {
			if ( ! visible( image ) ) { return; }
			var alt = ( image.getAttribute( 'alt' ) || '' ).replace( /\s+/g, ' ' ).trim();
			if ( ! alt ) { return; }
			var context = imageContext( image ), excerpt = evidenceText( alt, 120 ), altFingerprint = fingerprint( alt ), pattern = filenamePattern( alt );
			var words = alt.split( /\s+/ ).filter( Boolean ).length, sentences = ( alt.match( /[.!?](?:\s|$)/g ) || [] ).length;
			var extended = alt.length >= 180 && words >= 30 && sentences >= 2;
			if ( context !== 'standalone' ) { results.push( result( 'elavira.images.alt_quality_review', 'human_review', image, { alt_excerpt: excerpt, alt_fingerprint: altFingerprint, context: context, review_reason: context === 'figure_caption' ? 'caption_context' : 'functional_context' } ) ); }
			if ( pattern ) { results.push( result( 'elavira.images.filename_alt', 'potential_issue', image, { alt_excerpt: excerpt, alt_fingerprint: altFingerprint, context: context, pattern: pattern } ) ); }
			if ( extended ) { results.push( result( 'elavira.images.long_alt_review', 'human_review', image, { alt_excerpt: excerpt, alt_fingerprint: altFingerprint, context: context, alt_length: Math.min( alt.length, 10000 ), word_count: Math.min( words, 10000 ), review_reason: 'extended_multi_sentence' } ) ); }
		} );
	}

	function evaluate( doc ) {
		var results = [];
		Array.prototype.forEach.call( doc.querySelectorAll( 'img:not([alt])' ), function ( node ) {
			if ( visible( node ) ) { results.push( result( 'elavira.image.alt_missing', 'failure', node, { alt_state: 'missing' } ) ); }
		} );
		Array.prototype.forEach.call( doc.querySelectorAll( 'input:not([type="hidden"]), select, textarea, button' ), function ( node ) {
			var source = namingSource( node, doc );
			if ( visible( node ) && source === 'none' ) {
				var type = controlType( node ), item = result( 'elavira.form.accessible_name_missing', 'automatic_failure', node, { control_type: type, name_state: 'missing' }, {}, '2.0.0' );
				item.element.input_type = ( node.type || '' ).slice( 0, 32 ); item.element.has_accessible_name = false; results.push( item );
			}
			if ( visible( node ) && source === 'placeholder' ) { results.push( result( 'elavira.forms.placeholder_only_label', 'potential_issue', node, { control_type: node.tagName.toLowerCase(), placeholder_state: 'only_label' } ) ); }
			if ( visible( node ) && ( node.hasAttribute( 'required' ) || node.getAttribute( 'aria-required' ) === 'true' ) ) { results.push( result( 'elavira.forms.required_instruction_review', 'human_review', node, { required_source: node.hasAttribute( 'required' ) && node.getAttribute( 'aria-required' ) === 'true' ? 'both' : node.hasAttribute( 'required' ) ? 'native' : 'aria', control_type: controlType( node ) } ) ); }
		} );
		roundThree( doc, results );
		roundFour( doc, results );
		roundFive( doc, results );
		roundSix( doc, results );
		var namedLinks = [], linksByName = {};
		Array.prototype.forEach.call( doc.querySelectorAll( 'a[href]' ), function ( node ) {
			if ( ! visible( node ) ) { return; }
			var value = normalizeName( accessibleText( node, doc ) );
			if ( ! value ) {
				results.push( result( 'elavira.links.missing_accessible_name', 'automatic_failure', node, { accessible_name_state: 'missing' } ) );
				return;
			}
			var labels = { 'click here': 'click_here', here: 'here', 'read more': 'read_more', more: 'more', 'learn more': 'learn_more' };
			if ( labels[ value ] ) { results.push( result( 'elavira.link.purpose_review', 'potential_issue', node, { label_kind: labels[ value ], destination_kind: destinationKind( node, doc.location ) }, { label_kind: labels[ value ] }, '2.0.0' ) ); }
			var entry = { node: node, name: value, nameExcerpt: nameExcerpt( accessibleText( node, doc ) ), destination: destinationKey( node, doc.location ) };
			namedLinks.push( entry );
			linksByName[ value ] = linksByName[ value ] || {};
			linksByName[ value ][ entry.destination ] = true;
		} );
		Array.prototype.forEach.call( namedLinks, function ( entry ) {
			var destinations = Object.keys( linksByName[ entry.name ] );
			if ( destinations.length > 1 ) {
				var nameKey = fingerprint( entry.name );
				results.push( result( 'elavira.links.identical_name_different_destination', 'potential_issue', entry.node, { accessible_name_excerpt: entry.nameExcerpt, name_fingerprint: nameKey, destination_fingerprint: fingerprint( entry.destination ), destination_kind: destinationKind( entry.node, doc.location ), destination_state: 'different' }, { name_fingerprint: nameKey }, '2.0.0' ) );
			}
		} );
		var headings = Array.prototype.filter.call( doc.querySelectorAll( 'h1,h2,h3,h4,h5,h6' ), visible );
		var h1Count = headings.filter( function ( node ) { return node.tagName.toLowerCase() === 'h1'; } ).length;
		if ( h1Count === 0 ) { results.push( result( 'elavira.structure.missing_h1', 'human_review', doc.documentElement, { h1_count: 0 } ) ); }
		if ( h1Count > 1 ) { results.push( result( 'elavira.structure.multiple_h1', 'human_review', doc.documentElement, { h1_state: 'multiple' } ) ); }
		if ( headings.length === 0 ) { results.push( result( 'elavira.heading.structure_review', 'human_review', doc.documentElement, { issue: 'no_visible_headings' }, {}, '2.0.0' ) ); }
		else {
			var main = doc.querySelector ? doc.querySelector( 'main' ) : null;
			var mainHeadings = main && main.querySelectorAll ? Array.prototype.filter.call( main.querySelectorAll( 'h1,h2,h3,h4,h5,h6' ), visible ) : [];
			if ( main && mainHeadings.length === 0 ) { results.push( result( 'elavira.heading.structure_review', 'human_review', main, { issue: 'main_without_heading' }, {}, '2.0.0' ) ); }
		}
		var previous = 0;
		Array.prototype.forEach.call( headings, function ( node ) {
			var current = parseInt( node.tagName.slice( 1 ), 10 );
			if ( ! normalizeName( accessibleText( node, doc ) ) ) { results.push( result( 'elavira.structure.empty_heading', 'potential_issue', node, { heading_level: current } ) ); }
			if ( previous && current > previous + 1 ) { results.push( result( 'elavira.structure.heading_level_jump', 'potential_issue', node, { current_level: current, previous_level: previous }, { current_level: current, previous_level: previous } ) ); }
			previous = current;
		} );
		var html = doc.documentElement, language = html.getAttribute( 'lang' );
		if ( language === null || language.trim() === '' ) { results.push( result( 'elavira.document.language_missing', 'automatic_failure', html, { language_state: language === null ? 'missing' : 'empty' }, {}, '2.0.0' ) ); }
		else {
			var declaredLanguage = language.trim(), state = languageState( declaredLanguage );
			if ( state !== 'valid' ) { results.push( result( 'elavira.document.invalid_language', 'automatic_failure', html, { language_state: state, declared_language: evidenceText( declaredLanguage, 64 ), primary_subtag: evidenceText( declaredLanguage.split( '-' )[ 0 ].toLowerCase(), 8 ) }, {}, '2.0.0' ) ); }
		}
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
