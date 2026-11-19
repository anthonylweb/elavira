'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const toolbar = require('../../assets/js/toolbar.js')();

test('toolbar validates persisted values and ignores malformed choices', () => {
	const state = toolbar.valid({ textSize: 999, appearance: 'system', grayscale: true, highContrast: 'yes' });
	assert.equal(state.textSize, 100);
	assert.equal(state.appearance, 'default');
	assert.equal(state.grayscale, true);
	assert.equal(state.highContrast, false);
});

test('toolbar defaults represent native site presentation', () => {
	assert.deepEqual(toolbar.valid(null), toolbar.defaults);
	assert.equal(toolbar.defaults.textSize, 100);
	assert.equal(toolbar.defaults.appearance, 'default');
	for (const [name, value] of Object.entries(toolbar.defaults)) {
		if (!['textSize', 'appearance'].includes(name)) assert.equal(value, false);
	}
});
