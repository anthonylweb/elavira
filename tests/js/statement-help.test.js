const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(process.env.ELAVIRA_HELP_SCRIPT || require('node:path').join(__dirname, '../../assets/js/statement-help.js'), 'utf8');
function setup() {
  function element() {
    return { listeners: {}, hidden: false, attrs: {}, addEventListener(type, cb) { this.listeners[type] = cb; }, setAttribute(k, v) { this.attrs[k] = v; }, fire(type, event = {}) { this.listeners[type]?.(event); } };
  }
  const button = element(), hint = element(), group = element(), document = element();
  group.querySelector = s => s === 'button' ? button : hint;
  group.contains = e => [button, hint, group].includes(e);
  group.classList = { add() {} };
  document.querySelectorAll = () => [group];
  document.activeElement = null;
  let timer;
  vm.runInNewContext(source, { document, setTimeout: cb => { timer = cb; return 1; }, clearTimeout: () => { timer = null; } });
  return {button, hint, group, document, flush() { const cb = timer; timer = null; cb?.(); }};
}
test('only the button opens on hover; moving to the panel keeps it readable', () => {
  const x = setup();
  assert.equal(x.hint.hidden, true);
  x.group.fire('pointerenter', {pointerType: 'mouse'});
  assert.equal(x.hint.hidden, true);
  x.button.fire('pointerenter', {pointerType: 'mouse'});
  assert.equal(x.hint.hidden, false);
  x.button.fire('pointerleave');
  x.hint.fire('pointerenter', {pointerType: 'mouse'});
  x.flush();
  assert.equal(x.hint.hidden, false);
  x.hint.fire('pointerleave'); x.flush();
  assert.equal(x.hint.hidden, true);
});
test('focus opens, Escape dismisses without moving focus, and blur allows reopening', () => {
  const x = setup();
  x.document.activeElement = x.button; x.button.fire('focus');
  assert.equal(x.button.attrs['aria-expanded'], 'true');
  x.document.fire('keydown', {key: 'Escape'});
  assert.equal(x.hint.hidden, true);
  assert.equal(x.document.activeElement, x.button);
  x.button.fire('pointerenter', {pointerType:'mouse'});
  assert.equal(x.hint.hidden, true);
  x.button.fire('pointerleave'); x.document.activeElement = null; x.button.fire('blur'); x.flush();
  x.button.fire('focus'); assert.equal(x.hint.hidden, false);
});
test('touch does not hover; activation pins and toggles; outside press dismisses', () => {
  const x = setup();
  x.button.fire('pointerenter', {pointerType: 'touch'});
  assert.equal(x.hint.hidden, true);
  x.button.fire('focus'); x.button.fire('click');
  assert.equal(x.hint.hidden, false);
  x.button.fire('blur'); assert.equal(x.hint.hidden, false);
  x.button.fire('click'); assert.equal(x.hint.hidden, true);
  x.button.fire('click'); assert.equal(x.hint.hidden, false);
  x.document.fire('pointerdown', {target: {}});
  assert.equal(x.hint.hidden, true);
});
