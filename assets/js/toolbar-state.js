/* Apply explicitly enabled browser-local preferences in the document head. */
( function ( root ) {
	'use strict';
	try {
		var enabled = root.ElaviraToolbarControls || [];
		var state = JSON.parse( root.localStorage.getItem( 'elaviraToolbarPreferences' ) || '{}' );
		var element = root.document.documentElement;
		if ( ! state || typeof state !== 'object' ) {
			return;
		}
		if ( enabled.indexOf( 'text_size' ) > -1 && [ 110, 120, 130, 140, 150 ].indexOf( state.textSize ) > -1 ) {
			element.style.setProperty( '--elavira-text-size', state.textSize + '%' );
			element.classList.add( 'elavira-pref-text-size' );
		}
		var controls = {
			textSpacing: 'text_spacing',
			lineHeight: 'line_height',
			highContrast: 'high_contrast',
			grayscale: 'grayscale',
			highlightLinks: 'highlight_links',
			enhancedFocus: 'enhanced_focus',
			readingGuide: 'reading_guide',
			readableFont: 'readable_font'
		};
		Object.keys( controls ).forEach( function ( key ) {
			if ( enabled.indexOf( controls[ key ] ) > -1 && state[ key ] === true ) {
				element.classList.add( 'elavira-pref-' + key.replace( /[A-Z]/g, function ( letter ) {
					return '-' + letter.toLowerCase();
				} ) );
			}
		} );
		if ( enabled.indexOf( 'appearance' ) > -1 && [ 'light', 'dark' ].indexOf( state.appearance ) > -1 ) {
			element.classList.add( 'elavira-pref-appearance-' + state.appearance );
		}
	} catch ( error ) {
		// Missing, denied, or malformed storage leaves the native presentation intact.
	}
}( window ) );
