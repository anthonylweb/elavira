'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const main = fs.readFileSync(require.resolve('../../assets/js/toolbar.js'), 'utf8');
const early = fs.readFileSync(require.resolve('../../assets/js/toolbar-state.js'), 'utf8');

function fixture() {
 const classes = new Set();
 const properties = {};
 const node = () => ({ listeners: {}, attributes: {}, hidden: true, addEventListener(name, callback) { this.listeners[name] = callback; }, setAttribute(name, value) { this.attributes[name] = value; }, focus() {} });
 const trigger = node(), dialog = node(), close = node(), reset = node(), increase = node();
 const html = { classList: { add: name => classes.add(name), remove: name => classes.delete(name), toggle(name, on) { if (on) classes.add(name); else classes.delete(name); } }, style: { setProperty(name, value) { properties[name] = value; }, removeProperty(name) { delete properties[name]; } } };
 const nodes = { '.elavira-toolbar__trigger': trigger, '.elavira-toolbar__dialog': dialog, '[data-elavira-close]': close, '[data-elavira-reset]': reset, '[data-elavira-text-increase]': increase };
 const host = { querySelector: selector => nodes[selector] || null, querySelectorAll: () => [] };
 const document = { readyState: 'complete', documentElement: html, body: html, addEventListener() {}, querySelector: () => host };
 return { window: { document }, classes, properties, trigger, dialog, reset, increase };
}

for (const denial of ['getter', 'operations']) {
 test(`toolbar opens, changes preferences and resets when storage ${denial} throws`, () => {
  const f = fixture();
  const denied = () => { throw new Error('Storage denied'); };
  if (denial === 'getter') Object.defineProperty(f.window, 'localStorage', { get: denied });
  else f.window.localStorage = { getItem: denied, setItem: denied, removeItem: denied };
  assert.doesNotThrow(() => vm.runInNewContext(main, { window: f.window }));
  f.trigger.listeners.click();
  assert.equal(f.dialog.hidden, false);
  assert.equal(f.trigger.attributes['aria-expanded'], 'true');
  f.increase.listeners.click();
  assert.equal(f.properties['--elavira-text-size'], '110%');
  f.reset.listeners.click();
  assert.equal(f.classes.has('elavira-pref-text-size'), false);
 });
}
test('head initialization applies only enabled, valid saved preferences', () => {
 const f = fixture();
 f.window.ElaviraToolbarControls = ['text_size', 'appearance'];
 f.window.localStorage = { getItem: () => JSON.stringify({ textSize: 120, grayscale: true, appearance: 'dark' }) };
 vm.runInNewContext(early, { window: f.window });
 assert.equal(f.properties['--elavira-text-size'], '120%');
 assert.equal(f.classes.has('elavira-pref-appearance-dark'), true);
 assert.equal(f.classes.has('elavira-pref-grayscale'), false);
});
test('head initialization tolerates denied, malformed and invalid state', () => {
 for (const raw of ['null', '{', '{"textSize":999,"appearance":"invalid"}']) {
  const f = fixture();
  f.window.ElaviraToolbarControls = ['text_size', 'appearance'];
  f.window.localStorage = { getItem: () => raw };
  assert.doesNotThrow(() => vm.runInNewContext(early, { window: f.window }));
  assert.equal(f.classes.size, 0);
 }
 const f = fixture();
 Object.defineProperty(f.window, 'localStorage', { get() { throw new Error('denied'); } });
 assert.doesNotThrow(() => vm.runInNewContext(early, { window: f.window }));
 assert.equal(f.classes.size, 0);
});
