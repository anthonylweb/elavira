'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const scanner = require( '../../assets/js/scanner.js' );

function node( tag, attributes = {}, content = '' ) {
	return {
		nodeType: 1, tagName: tag.toUpperCase(), id: attributes.id || '', type: attributes.type || '', textContent: content, parentElement: null,
		getAttribute: ( name ) => Object.prototype.hasOwnProperty.call( attributes, name ) ? attributes[ name ] : null,
		hasAttribute: ( name ) => Object.prototype.hasOwnProperty.call( attributes, name ), closest: () => null, querySelector: () => null
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
	assert.deepEqual( results.map( ( item ) => item.rule_id ), [ 'elavira.image.alt_missing', 'elavira.form.accessible_name_missing', 'elavira.document.language_missing' ] );
	assert.equal( JSON.stringify( results ).includes( 'private value' ), false );
	assert.equal( results.some( ( item ) => 'priority' in item || 'wcag' in item ), false );
} );

test( 'review evaluators remain review-only and batches are bounded', () => {
	const link = node( 'a', { href: '/next' }, 'Read more' ); const first = node( 'h2', {}, 'Section' ); const second = node( 'h4', {}, 'Nested' ); const html = node( 'html', { lang: 'en' } );
	const doc = { documentElement: html, location: { href: 'https://example.com/', origin: 'https://example.com' }, getElementById: () => null, querySelector: () => null,
		querySelectorAll: ( query ) => query === 'a[href]' ? [ link ] : query.startsWith( 'h1' ) ? [ first, second ] : [] };
	const results = scanner.evaluate( doc );
	assert.deepEqual( results.map( ( item ) => item.outcome ), [ 'review_required', 'review_required' ] );
	assert.deepEqual( scanner.batches( results.concat( results ), 3 ).map( ( batch ) => batch.length ), [ 3, 1 ] );
} );

test( 'readiness observer resolves and cleans up after bounded quiet time', async () => {
	let disconnected = false; let callback;
	class Observer { constructor( supplied ) { callback = supplied; } observe() {} disconnect() { disconnected = true; } }
	const timers = { setTimeout, clearTimeout };
	await scanner.waitForReady( { readyState: 'complete', documentElement: {} }, { quietMs: 1, stabilizeMs: 5, readyMaxMs: 20 }, Observer, timers );
	assert.equal( typeof callback, 'function' ); assert.equal( disconnected, true );
} );
