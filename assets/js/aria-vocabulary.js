/* Derived from WAI-ARIA 1.2. Copyright 2013-2023 W3C (MIT, ERCIM, Keio, Beihang).
 * Modified into bounded JavaScript vocabulary by Elavira.
 * W3C Software and Document License (2015): see licenses/W3C-2015.txt and readme.txt (Third-Party Licenses and Notices).
 */
/* Bounded WAI-ARIA 1.2 vocabulary used by Elavira Round 3. Source: https://www.w3.org/TR/wai-aria-1.2/ (W3C Recommendation, 2023-06-06). */
( function ( root ) {
	'use strict';
	root.ElaviraAriaVocabulary = {
		version: 'WAI-ARIA-1.2-2023-06-06',
		roles: 'alert alertdialog application article banner blockquote button caption cell checkbox code columnheader combobox complementary contentinfo definition deletion dialog directory document emphasis feed figure form generic grid gridcell group heading img insertion link list listbox listitem log main marquee math menu menubar menuitem menuitemcheckbox menuitemradio meter navigation none note option paragraph presentation progressbar radio radiogroup region row rowgroup rowheader scrollbar search searchbox separator slider spinbutton status strong subscript superscript switch tab table tablist tabpanel term textbox time timer toolbar tooltip tree treegrid treeitem'.split( ' ' ),
		attributes: 'aria-activedescendant aria-atomic aria-autocomplete aria-braillelabel aria-brailleroledescription aria-busy aria-checked aria-colcount aria-colindex aria-colindextext aria-colspan aria-controls aria-current aria-describedby aria-description aria-details aria-disabled aria-dropeffect aria-errormessage aria-expanded aria-flowto aria-grabbed aria-haspopup aria-hidden aria-invalid aria-keyshortcuts aria-label aria-labelledby aria-level aria-live aria-modal aria-multiline aria-multiselectable aria-orientation aria-owns aria-placeholder aria-posinset aria-pressed aria-readonly aria-relevant aria-required aria-roledescription aria-rowcount aria-rowindex aria-rowindextext aria-rowspan aria-selected aria-setsize aria-sort aria-valuemax aria-valuemin aria-valuenow aria-valuetext'.split( ' ' ),
		idReferences: { 'aria-activedescendant': false, 'aria-controls': true, 'aria-describedby': true, 'aria-details': false, 'aria-errormessage': false, 'aria-flowto': true, 'aria-labelledby': true, 'aria-owns': true },
		booleanAttributes: 'aria-atomic aria-busy aria-disabled aria-expanded aria-hidden aria-modal aria-multiline aria-multiselectable aria-readonly aria-required aria-selected'.split( ' ' ),
		tristateAttributes: [ 'aria-checked', 'aria-pressed' ],
		enumeratedAttributes: {
			'aria-autocomplete': [ 'inline', 'list', 'both', 'none' ],
			'aria-current': [ 'page', 'step', 'location', 'date', 'time', 'true', 'false' ],
			'aria-haspopup': [ 'false', 'true', 'menu', 'listbox', 'tree', 'grid', 'dialog' ],
			'aria-invalid': [ 'grammar', 'false', 'spelling', 'true' ],
			'aria-live': [ 'assertive', 'off', 'polite' ],
			'aria-orientation': [ 'horizontal', 'undefined', 'vertical' ],
			'aria-sort': [ 'ascending', 'descending', 'none', 'other' ]
		},
		requiredByRole: { checkbox: [ 'aria-checked' ], combobox: [ 'aria-controls', 'aria-expanded' ], heading: [ 'aria-level' ], menuitemcheckbox: [ 'aria-checked' ], menuitemradio: [ 'aria-checked' ], radio: [ 'aria-checked' ], scrollbar: [ 'aria-controls', 'aria-valuenow' ], slider: [ 'aria-valuenow' ], spinbutton: [ 'aria-valuenow' ], switch: [ 'aria-checked' ] },
		nameProhibitedRoles: 'caption code deletion emphasis generic insertion none paragraph presentation strong subscript superscript'.split( ' ' )
	};
	if ( typeof module === 'object' && module.exports ) { module.exports = root.ElaviraAriaVocabulary; }
}( typeof window !== 'undefined' ? window : globalThis ) );
