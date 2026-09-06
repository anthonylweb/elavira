'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const scanner = require( '../../assets/js/scanner.js' );

function node( tag, attributes = {}, content = '' ) {
	return {
		nodeType: 1, tagName: tag.toUpperCase(), id: attributes.id || '', type: attributes.type || '', textContent: content, parentElement: null,
		getAttribute: ( name ) => Object.prototype.hasOwnProperty.call( attributes, name ) ? attributes[ name ] : null,
		hasAttribute: ( name ) => Object.prototype.hasOwnProperty.call( attributes, name ), closest: () => null, querySelector: () => null,
		querySelectorAll: ( query ) => query === 'img[alt]' ? ( attributes.images || [] ) : []
	};
}

function documentFor( collections = {}, lang = 'en' ) {
	const html = node( 'html', lang === null ? {} : { lang } );
	return {
		documentElement: html,
		location: { href: 'https://example.com/page', origin: 'https://example.com' },
		getElementById: () => null,
		querySelector: ( query ) => collections[ query ] || null,
		querySelectorAll: ( query ) => collections[ query ] || []
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

test( 'language rules distinguish missing, valid, and objectively invalid tags without inspecting prose', () => {
	const heading = [ node( 'h1', {}, 'Page') ];
	let results = scanner.evaluate( documentFor( { 'h1,h2,h3,h4,h5,h6': heading }, null ) );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.document.language_missing' ).rule_version, '2.0.0' );
	results = scanner.evaluate( documentFor( { 'h1,h2,h3,h4,h5,h6': heading }, 'en-US' ) );
	assert.equal( results.some( ( item ) => item.rule_id.includes( 'language' ) ), false );
	results = scanner.evaluate( documentFor( { 'h1,h2,h3,h4,h5,h6': heading }, 'en_US' ) );
	assert.equal( results.find( ( item ) => item.rule_id === 'elavira.document.invalid_language' ).outcome, 'automatic_failure' );
} );

test( 'readiness observer resolves and cleans up after bounded quiet time', async () => {
	let disconnected = false; let callback;
	class Observer { constructor( supplied ) { callback = supplied; } observe() {} disconnect() { disconnected = true; } }
	const timers = { setTimeout, clearTimeout };
	await scanner.waitForReady( { readyState: 'complete', documentElement: {} }, { quietMs: 1, stabilizeMs: 5, readyMaxMs: 20 }, Observer, timers );
	assert.equal( typeof callback, 'function' ); assert.equal( disconnected, true );
} );
