'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
require( '../../assets/js/language-primary-subtags.js' );
require( '../../assets/js/aria-vocabulary.js' );
const scanner = require( '../../assets/js/scanner.js' );

function node( tag, attributes = {}, content = '' ) {
	const result = {
		nodeType: 1, tagName: tag.toUpperCase(), id: attributes.id || '', type: attributes.type || '', textContent: content, childNodes: content ? [ { nodeType: 3, nodeValue: content } ] : [], parentElement: null,
		getAttribute: ( name ) => Object.prototype.hasOwnProperty.call( attributes, name ) ? attributes[ name ] : null,
		hasAttribute: ( name ) => Object.prototype.hasOwnProperty.call( attributes, name ), closest: ( query ) => ( attributes.closest || {} )[ query ] || null, querySelector: ( query ) => ( attributes.matches || {} )[ query ] || null,
		querySelectorAll: ( query ) => query === 'img[alt]' ? ( attributes.images || [] ) : query === '*' ? ( attributes.children || [] ) : []
	};
	result.attributes = Object.keys( attributes ).filter( ( name ) => ! [ 'images', 'children', 'matches', 'closest' ].includes( name ) ).map( ( name ) => ( { name, value: String( attributes[ name ] ) } ) );
	return result;
}

function contrastDocument( candidates, styles, extra = {} ) {
	const doc = documentFor( Object.assign( { 'p,span,a,button,label,li,td,th,h1,h2,h3,h4,h5,h6': candidates }, extra ) );
	const html = doc.documentElement;
	candidates.forEach( ( item ) => { item.parentElement = html; } );
	Object.values( extra ).flat().forEach( ( item ) => { if ( item && item.tagName && ! item.parentElement ) { item.parentElement = html; } } );
	styles.set( html, { color: 'rgb(0,0,0)', backgroundColor: 'rgb(255,255,255)', backgroundImage: 'none', opacity: '1', filter: 'none', mixBlendMode: 'normal', backgroundBlendMode: 'normal', fontSize: '16px', fontWeight: '400' } );
	const defaults = { color: 'rgb(0,0,0)', backgroundColor: 'rgba(0,0,0,0)', backgroundImage: 'none', opacity: '1', filter: 'none', mixBlendMode: 'normal', backgroundBlendMode: 'normal', fontSize: '16px', fontWeight: '400', appearance: 'auto', webkitAppearance: 'auto', borderTopColor: 'rgb(0,0,0)', borderRightColor: 'rgb(0,0,0)', borderBottomColor: 'rgb(0,0,0)', borderLeftColor: 'rgb(0,0,0)', borderTopWidth: '0px', borderRightWidth: '0px', borderBottomWidth: '0px', borderLeftWidth: '0px' };
	doc.defaultView = { getComputedStyle: ( item, pseudo ) => pseudo ? Object.assign( {}, defaults, { content: 'none' } ) : Object.assign( {}, defaults, styles.get( item ) || {} ) };
	return doc;
}

function documentFor( collections = {}, lang = 'en' ) {
	const html = node( 'html', lang === null ? {} : { lang } );
	const all = collections[ '*' ] || Object.values( collections ).flat().filter( ( item, index, values ) => item && item.tagName && values.indexOf( item ) === index );
	return {
		documentElement: html,
		location: { href: 'https://example.com/page', origin: 'https://example.com' },
		getElementById: () => null,
		querySelector: ( query ) => collections[ query ] || null,
		querySelectorAll: ( query ) => {
			const id = query.match( /^\[id="(.+)"\]$/ );
			return id ? all.filter( ( item ) => item.id === id[ 1 ] ) : query === '*' ? all : collections[ query ] || [];
		}
	};
}

test( 'representative evaluators emit contract evidence without form values', () => {
	const image = node( 'img' ); const input = node( 'input', { type: 'text', value: 'private value' } ); const html = node( 'html', { lang: '' } );
	const doc = {
		documentElement: html, location: { href: 'https://example.com/', origin: 'https://example.com' },
		querySelector: () => null,
		querySelectorAll: ( query ) => query.startsWith( 'img' ) ? [ image ] : query.startsWith( 'input' ) ? [ input ] : []
	};
	const results = scanner.evaluate( doc );
	assert.deepEqual( results.map( ( item ) => item.rule_id ), [ 'elavira.image.alt_missing', 'elavira.form.accessible_name_missing', 'elavira.structure.missing_h1', 'elavira.heading.structure_review', 'elavira.document.language_missing' ] );
	assert.equal( JSON.stringify( results ).includes( 'private value' ), false );
	assert.equal( results.some( ( item ) => 'priority' in item || 'wcag' in item ), false );
} );

test( 'review evaluators remain review-only and batches are bounded', () => {
	const link = node( 'a', { href: '/next' }, 'Read more' ); const first = node( 'h2', {}, 'Section' ); const second = node( 'h4', {}, 'Nested' ); const html = node( 'html', { lang: 'en' } );
	const doc = { documentElement: html, location: { href: 'https://example.com/', origin: 'https://example.com' }, getElementById: () => null, querySelector: () => null,
		querySelectorAll: ( query ) => query === 'a[href]' ? [ link ] : query.startsWith( 'h1' ) ? [ first, second ] : [] };
	const results = scanner.evaluate( doc );
	assert.deepEqual( results.map( ( item ) => item.outcome ), [ 'potential_issue', 'human_review', 'potential_issue' ] );
	assert.deepEqual( scanner.batches( results.concat( results ), 3 ).map( ( batch ) => batch.length ), [ 3, 3 ] );
} );

test( 'link rules distinguish missing, generic, descriptive, and descendant image names', () => {
	const unnamed = node( 'a', { href: '/empty' } );
	const generic = node( 'a', { href: '/generic' }, 'Read more' );
	const descriptive = node( 'a', { href: '/about' }, 'About our team' );
	const image = node( 'img', { alt: 'Annual report' } );
	const imageLink = node( 'a', { href: '/report', images: [ image ] } );
	const results = scanner.evaluate( documentFor( { 'a[href]': [ unnamed, generic, descriptive, imageLink ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page') ] } ) );
	assert.equal( results.filter( ( item ) => item.rule_id === 'elavira.links.missing_accessible_name' ).length, 1 );
	const ambiguous = results.filter( ( item ) => item.rule_id === 'elavira.link.purpose_review' );
	assert.equal( ambiguous.length, 1 );
	assert.equal( ambiguous[ 0 ].outcome, 'potential_issue' );
	assert.equal( ambiguous[ 0 ].rule_version, '2.0.0' );
} );

test( 'identical link names trigger only for materially different destinations', () => {
	const sameOne = node( 'a', { href: '/same?view=one#top' }, 'Details' );
	const sameTwo = node( 'a', { href: '/same?view=one#top' }, ' details ' );
	let results = scanner.evaluate( documentFor( { 'a[href]': [ sameOne, sameTwo ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page') ] } ) );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.links.identical_name_different_destination' ), false );
	const different = node( 'a', { href: '/same?view=two#top' }, 'Details' );
	results = scanner.evaluate( documentFor( { 'a[href]': [ sameOne, different ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page') ] } ) );
	const duplicates = results.filter( ( item ) => item.rule_id === 'elavira.links.identical_name_different_destination' );
	assert.equal( duplicates.length, 2 );
	assert.equal( duplicates.every( ( item ) => item.outcome === 'potential_issue' ), true );
	assert.equal( duplicates.every( ( item ) => item.rule_version === '2.0.0' ), true );
	assert.equal( duplicates.every( ( item ) => item.evidence.accessible_name_excerpt === 'Details' ), true );
	assert.equal( JSON.stringify( duplicates ).includes( '?view=' ), false );
	assert.notEqual( duplicates[ 0 ].evidence.destination_fingerprint, duplicates[ 1 ].evidence.destination_fingerprint );
} );

test( 'heading rules distinguish structural warnings, jumps, empty headings, and sensible progression', () => {
	let results = scanner.evaluate( documentFor() );
	assert.deepEqual( results.filter( ( item ) => item.rule_id.includes( 'missing_h1' ) || item.rule_id === 'elavira.heading.structure_review' ).map( ( item ) => item.outcome ), [ 'human_review', 'human_review' ] );
	const sensible = [ node( 'h1', {}, 'Page' ), node( 'h2', {}, 'Section' ), node( 'h3', {}, 'Subsection' ) ];
	results = scanner.evaluate( documentFor( { 'h1,h2,h3,h4,h5,h6': sensible } ) );
	assert.equal( results.some( ( item ) => item.rule_id.startsWith( 'elavira.structure.' ) ), false );
	const problematic = [ node( 'h1', {}, 'First' ), node( 'h1', {}, 'Second' ), node( 'h3', {}, '' ) ];
	results = scanner.evaluate( documentFor( { 'h1,h2,h3,h4,h5,h6': problematic } ) );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.structure.multiple_h1' ).outcome, 'human_review' );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.structure.heading_level_jump' ).outcome, 'potential_issue' );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.structure.empty_heading' ).outcome, 'potential_issue' );
	const main = node( 'main' );
	results = scanner.evaluate( documentFor( { main, 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Site title') ] } ) );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.heading.structure_review' ).evidence.issue, 'main_without_heading' );
} );

test( 'language rules accept registered primary subtags without inspecting prose', () => {
	const heading = [ node( 'h1', {}, 'Page') ];
	let results = scanner.evaluate( documentFor( { 'h1,h2,h3,h4,h5,h6': heading }, null ) );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.document.language_missing' ).rule_version, '2.0.0' );
	[ 'en', 'en-US', 'haw', 'de-hello' ].forEach( ( language ) => {
		results = scanner.evaluate( documentFor( { 'h1,h2,h3,h4,h5,h6': heading }, language ) );
		assert.equal( results.some( ( item ) => item.rule_id.includes( 'language' ) ), false, language );
	} );
} );

test( 'language rules reject malformed and plausible but unregistered primary subtags', () => {
	const heading = [ node( 'h1', {}, 'Page') ];
	[ [ 'en_US', 'malformed' ], [ '#1', 'malformed' ], [ 'em-US', 'unknown_primary' ], [ 'eng', 'unknown_primary' ] ].forEach( ( sample ) => {
		const results = scanner.evaluate( documentFor( { 'h1,h2,h3,h4,h5,h6': heading }, sample[ 0 ] ) );
		const finding = results.find( ( item ) => item.rule_id === 'elavira.document.invalid_language' );
		assert.equal( finding.rule_version, '2.0.0', sample[ 0 ] );
		assert.equal( finding.outcome, 'automatic_failure', sample[ 0 ] );
		assert.equal( finding.evidence.language_state, sample[ 1 ], sample[ 0 ] );
	} );
} );

test( 'Round 3 separates native names, placeholder review, and required instructions', () => {
	const label = node( 'label', { for: 'email' }, 'Email' );
	const named = node( 'input', { id: 'email', type: 'email', required: '' } ); named.labels = [ label ];
	const placeholder = node( 'textarea', { placeholder: 'Message' } );
	const unnamed = node( 'select' ); unnamed.labels = [];
	const results = scanner.evaluate( documentFor( { '*': [ label, named, placeholder, unnamed ], 'label[for]': [ label ], 'input:not([type="hidden"]), select, textarea, button': [ named, placeholder, unnamed ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.forms.placeholder_only_label' ), true );
	assert.equal( results.filter( ( item ) => item.rule_id === 'elavira.form.accessible_name_missing' ).length, 1 );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.forms.required_instruction_review' ), true );
	assert.equal( JSON.stringify( results ).includes( 'value' ), false );
} );

test( 'Round 3 preserves valid naming fallback while reporting broken ARIA references', () => {
	const control = node( 'div', { role: 'button', 'aria-labelledby': 'missing', 'aria-label': 'Save' } );
	const results = scanner.evaluate( documentFor( { '*': [ control ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.aria.broken_id_reference' ), true );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.semantics.control_missing_name' ), false );
} );

test( 'Round 3 emits consequence and cause observations for missing required state', () => {
	const widget = node( 'div', { role: 'checkbox', 'aria-label': 'Subscribe' } );
	const results = scanner.evaluate( documentFor( { '*': [ widget ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.semantics.required_state_missing' ).outcome, 'automatic_failure' );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.aria.required_attribute_missing' ).outcome, 'potential_issue' );
} );

test( 'Round 3 validates bounded ARIA boolean, tristate, numeric, and enumerated values', () => {
	const invalid = node( 'div', { role: 'button', 'aria-label': 'Toggle', 'aria-pressed': 'sometimes', 'aria-live': 'urgent', 'aria-level': '0', 'aria-valuenow': 'many' } );
	const valid = node( 'div', { role: 'button', 'aria-label': 'Toggle', 'aria-pressed': 'mixed', 'aria-live': 'polite', 'aria-level': '2', 'aria-valuenow': '3.5' } );
	let results = scanner.evaluate( documentFor( { '*': [ invalid ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.deepEqual( results.filter( ( item ) => item.rule_id === 'elavira.aria.invalid_value' ).map( ( item ) => item.evidence.value_reason ).sort(), [ 'invalid_integer', 'invalid_number', 'invalid_token', 'invalid_tristate' ] );
	results = scanner.evaluate( documentFor( { '*': [ valid ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.aria.invalid_value' ), false );
} );

test( 'Round 3 reports only relationship-relevant duplicate IDs and hidden focusable content', () => {
	const first = node( 'span', { id: 'name' }, 'First' ); const second = node( 'span', { id: 'name' }, 'Second' );
	const widget = node( 'div', { role: 'button', 'aria-labelledby': 'name' } );
	const button = node( 'button', {}, 'Hidden action' ); const hidden = node( 'div', { 'aria-hidden': 'true', children: [ button ] } );
	const results = scanner.evaluate( documentFor( { '*': [ first, second, widget, hidden, button ], '[aria-hidden="true"]': [ hidden ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.aria.duplicate_id_reference' ), true );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.aria.hidden_focusable_content' ), true );
} );

test( 'Round 4 positive tabindex is review-only while zero and negative do not trigger it', () => {
	const positive = node( 'button', { tabindex: '2' }, 'First' ); const zero = node( 'button', { tabindex: '0' }, 'Second' ); const negative = node( 'button', { tabindex: '-1' }, 'Third' );
	const results = scanner.evaluate( documentFor( { '*': [ positive, zero, negative ], 'input:not([type="hidden"]), select, textarea, button': [ positive, zero, negative ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	const findings = results.filter( ( item ) => item.rule_id === 'elavira.keyboard.positive_tabindex' );
	assert.equal( findings.length, 1 ); assert.equal( findings[ 0 ].outcome, 'human_review' ); assert.equal( findings[ 0 ].evidence.tabindex_value, 2 );
} );

test( 'Round 4 custom roles produce bounded review observations but native controls do not', () => {
	const custom = node( 'div', { role: 'tab', tabindex: '-1', 'aria-label': 'Panel' } ); const native = node( 'button', {}, 'Save' );
	const results = scanner.evaluate( documentFor( { '*': [ custom, native ], 'input:not([type="hidden"]), select, textarea, button': [ native ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.keyboard.non_native_interactive' ), true );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.keyboard.possible_unreachable_control' ), true );
	assert.equal( results.filter( ( item ) => item.rule_id.startsWith( 'elavira.keyboard.' ) ).every( ( item ) => item.outcome === 'human_review' ), true );
} );

test( 'Round 4 limits trap review to application regions and treats modals separately', () => {
	const one = node( 'button', {}, 'One' ); const two = node( 'button', {}, 'Two' ); const application = node( 'div', { role: 'application', children: [ one, two ] } );
	const modal = node( 'div', { role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Settings' } ); const ordinary = node( 'div' );
	const results = scanner.evaluate( documentFor( { '*': [ application, one, two, modal, ordinary ], 'input:not([type="hidden"]), select, textarea, button': [ one, two ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.keyboard.possible_trap' ).outcome, 'human_review' );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.focus.modal_management_review' ).outcome, 'human_review' );
	assert.equal( results.filter( ( item ) => item.target.tag_name === 'div' && item.rule_id === 'elavira.keyboard.possible_trap' ).length, 1 );
} );

test( 'Round 4 detects only substantial elevated boundary overlays', () => {
	const overlay = node( 'div' ); overlay.getBoundingClientRect = () => ( { top: 0, bottom: 80, width: 800, height: 80 } ); overlay.ownerDocument = {};
	const doc = documentFor( { '*': [ overlay ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } );
	doc.defaultView = { innerWidth: 1000, innerHeight: 800, getComputedStyle: () => ( { position: 'fixed', zIndex: '10' } ) };
	const finding = scanner.evaluate( doc ).find( ( item ) => item.rule_id === 'elavira.focus.obscured_review' );
	assert.equal( finding.outcome, 'human_review' ); assert.equal( finding.evidence.boundary, 'top' );
} );

test( 'Round 4 focus suppression requires outline removal without a declared alternative', () => {
	const suspicious = node( 'button', {}, 'Risk' ); const alternate = node( 'button', {}, 'Safe' );
	const property = ( values ) => ( { getPropertyValue: ( name ) => values[ name ] || '' } );
	const doc = documentFor( { '*': [ suspicious, alternate ], '.risk': [ suspicious ], '.safe': [ alternate ], 'input:not([type="hidden"]), select, textarea, button': [ suspicious, alternate ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } );
	doc.styleSheets = [ { cssRules: [ { selectorText: '.risk:focus', style: property( { outline: 'none' } ) }, { selectorText: '.safe:focus-visible', style: property( { outline: 'none', 'box-shadow': '0 0 0 2px blue' } ) } ] } ];
	const findings = scanner.evaluate( doc ).filter( ( item ) => item.rule_id === 'elavira.focus.possible_hidden_focus' );
	assert.equal( findings.length, 1 ); assert.equal( findings[ 0 ].outcome, 'human_review' );
} );

test( 'Round 5 distinguishes normal, large, and alpha-composited determinate text', () => {
	const normal = node( 'p', { id: 'normal' }, 'Normal copy' );
	const large = node( 'p', { id: 'large' }, 'Large copy' );
	const alpha = node( 'p', { id: 'alpha' }, 'Alpha copy' );
	const styles = new Map( [
		[ normal, { color: 'rgb(119,119,119)', fontSize: '16px', fontWeight: '400' } ],
		[ large, { color: 'rgb(149,149,149)', fontSize: '24px', fontWeight: '400' } ],
		[ alpha, { color: 'rgba(0,0,0,0.2)', fontSize: '16px', fontWeight: '400' } ],
	] );
	const results = scanner.evaluate( contrastDocument( [ normal, large, alpha ], styles ) );
	assert.equal( results.filter( ( item ) => item.rule_id === 'elavira.contrast.text_insufficient' ).length, 2 );
	assert.equal( results.filter( ( item ) => item.rule_id === 'elavira.contrast.large_text_insufficient' ).length, 1 );
	assert.equal( results.filter( ( item ) => item.rule_id === 'elavira.contrast.text_insufficient' ).some( ( item ) => item.evidence.determination === 'composited_alpha' ), true );
} );

test( 'Round 5 sends gradients to human review and does not claim a contrast failure', () => {
	const copy = node( 'p', { id: 'gradient' }, 'Layered copy' );
	const styles = new Map( [ [ copy, { color: 'rgb(119,119,119)', backgroundImage: 'linear-gradient(red, blue)' } ] ] );
	const results = scanner.evaluate( contrastDocument( [ copy ], styles ) ).filter( ( item ) => item.rule_id.indexOf( 'elavira.contrast.' ) === 0 );
	assert.deepEqual( results.map( ( item ) => item.rule_id ), [ 'elavira.contrast.cannot_determine' ] );
	assert.equal( results[ 0 ].outcome, 'human_review' );
} );

test( 'Round 5 color-only review requires an explicit invalid state without referenced messaging', () => {
	const unresolved = node( 'input', { id: 'bad', 'aria-invalid': 'true' } );
	const described = node( 'input', { id: 'described', 'aria-invalid': 'true', 'aria-describedby': 'message' } );
	const styles = new Map();
	const doc = contrastDocument( [], styles, { '[aria-invalid="true"]': [ unresolved, described ] } );
	const findings = scanner.evaluate( doc ).filter( ( item ) => item.rule_id === 'elavira.color.color_only_review' );
	assert.equal( findings.length, 1 );
	assert.equal( findings[ 0 ].evidence.non_color_cue, 'no_referenced_message' );
} );

test( 'Round 5 non-text failure is limited to determinate custom-rendered controls', () => {
	const custom = node( 'input', { id: 'custom', type: 'checkbox' } );
	const native = node( 'input', { id: 'native', type: 'checkbox' } );
	const styles = new Map( [
		[ custom, { appearance: 'none', backgroundColor: 'rgb(180,180,180)', borderTopColor: 'rgb(170,170,170)', borderRightColor: 'rgb(170,170,170)', borderBottomColor: 'rgb(170,170,170)', borderLeftColor: 'rgb(170,170,170)', borderTopWidth: '1px', borderRightWidth: '1px', borderBottomWidth: '1px', borderLeftWidth: '1px' } ],
		[ native, { appearance: 'auto' } ],
	] );
	const doc = contrastDocument( [], styles, { 'input[type="checkbox"],input[type="radio"]': [ custom, native ] } );
	const findings = scanner.evaluate( doc ).filter( ( item ) => item.rule_id === 'elavira.contrast.non_text_insufficient' );
	assert.equal( findings.length, 1 );
	assert.equal( findings[ 0 ].evidence.required_milli, 3000 );
} );

test( 'Round 6 preserves missing-alt semantics and ignores intentional empty alt', () => {
	const missing = node( 'img' );
	const empty = node( 'img', { alt: '' } );
	const ordinary = node( 'img', { alt: 'A team meeting around a table' } );
	const results = scanner.evaluate( documentFor( { 'img:not([alt])': [ missing ], 'img[alt]': [ empty, ordinary ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.filter( ( item ) => item.rule_id === 'elavira.image.alt_missing' ).length, 1 );
	assert.equal( results.some( ( item ) => item.rule_id.indexOf( 'elavira.images.' ) === 0 ), false );
} );

test( 'Round 6 filename heuristic is conservative and remains a Potential Issue', () => {
	const positives = [ 'photo.jpg', 'IMG_1234.jpeg', 'hero-banner.png' ].map( ( alt ) => node( 'img', { alt } ) );
	const negatives = [ 'Photo of our support team', 'Release 2.0 overview', 'Product image for blue chair' ].map( ( alt ) => node( 'img', { alt } ) );
	const results = scanner.evaluate( documentFor( { 'img[alt]': positives.concat( negatives ), 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	const findings = results.filter( ( item ) => item.rule_id === 'elavira.images.filename_alt' );
	assert.equal( findings.length, 3 );
	assert.equal( findings.every( ( item ) => item.outcome === 'potential_issue' ), true );
	assert.equal( findings.every( ( item ) => item.evidence.alt_excerpt.length <= 120 ), true );
} );

test( 'Round 6 contextual and long alternatives remain bounded Human Review observations', () => {
	const link = node( 'a', { href: '/details' } );
	const functional = node( 'img', { alt: 'View service details', closest: { 'a[href],button,[role="button"],[role="link"]': link } } );
	const longAlt = 'This chart compares quarterly service usage across all four regions and explains the major changes for each reporting period. It also identifies the highest and lowest values, describes the overall trend, and notes the two quarters where demand changed most significantly for customers.';
	const extended = node( 'img', { alt: longAlt } );
	const results = scanner.evaluate( documentFor( { 'img[alt]': [ functional, extended ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	const quality = results.find( ( item ) => item.rule_id === 'elavira.images.alt_quality_review' );
	const long = results.find( ( item ) => item.rule_id === 'elavira.images.long_alt_review' );
	assert.equal( quality.outcome, 'human_review' );
	assert.equal( long.outcome, 'human_review' );
	assert.equal( long.evidence.alt_excerpt.length, 120 );
	assert.equal( JSON.stringify( long ).includes( longAlt ), false );
} );

test( 'Round 6 long-alt heuristic requires every conservative threshold', () => {
	const tooShort = 'A'.repeat( 179 ) + '. This second sentence contains enough punctuation but remains below the character threshold.';
	const tooFewWords = 'This description has two sentences and is deliberately padded to exceed the character threshold. ' + 'Details '.repeat( 12 ) + 'finish here.';
	const onlyOneSentence = 'This single sentence contains more than thirty separate words and is deliberately expanded with additional contextual language about a chart, its labels, its series, its values, its trends, its comparisons, its annotations, and its overall purpose for a reader who cannot see the image directly';
	const results = scanner.evaluate( documentFor( { 'img[alt]': [ node( 'img', { alt: tooShort } ), node( 'img', { alt: tooFewWords } ), node( 'img', { alt: onlyOneSentence } ) ], 'h1,h2,h3,h4,h5,h6': [ node( 'h1', {}, 'Page' ) ] } ) );
	assert.equal( results.some( ( item ) => item.rule_id === 'elavira.images.long_alt_review' ), false );
} );

test( 'readiness observer resolves and cleans up after bounded quiet time', async () => {
	let disconnected = false; let callback;
	class Observer { constructor( supplied ) { callback = supplied; } observe() {} disconnect() { disconnected = true; } }
	const timers = { setTimeout, clearTimeout };
	await scanner.waitForReady( { readyState: 'complete', documentElement: {} }, { quietMs: 1, stabilizeMs: 5, readyMaxMs: 20 }, Observer, timers );
	assert.equal( typeof callback, 'function' ); assert.equal( disconnected, true );
} );
