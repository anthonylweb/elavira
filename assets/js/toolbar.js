(function (root, factory) {
	'use strict';
	if (typeof module === 'object' && module.exports) {
		module.exports = factory;
	} else {
		factory(root.document, root.localStorage);
	}
}(typeof window !== 'undefined' ? window : this, function (document, storage) {
	'use strict';
	var key = 'elaviraToolbarPreferences';
	var defaults = { textSize: 100, textSpacing: false, lineHeight: false, highContrast: false, appearance: 'default', grayscale: false, highlightLinks: false, enhancedFocus: false, readingGuide: false, readableFont: false };
	var classMap = { textSpacing: 'elavira-pref-text-spacing', lineHeight: 'elavira-pref-line-height', highContrast: 'elavira-pref-high-contrast', grayscale: 'elavira-pref-grayscale', highlightLinks: 'elavira-pref-highlight-links', enhancedFocus: 'elavira-pref-enhanced-focus', readingGuide: 'elavira-pref-reading-guide', readableFont: 'elavira-pref-readable-font' };

	function valid(raw) {
		var state = Object.assign({}, defaults);
		if (!raw || typeof raw !== 'object') return state;
		if ([100, 110, 120, 130, 140, 150].indexOf(raw.textSize) !== -1) state.textSize = raw.textSize;
		Object.keys(classMap).forEach(function (name) { if (typeof raw[name] === 'boolean') state[name] = raw[name]; });
		if (['default', 'light', 'dark'].indexOf(raw.appearance) !== -1) state.appearance = raw.appearance;
		return state;
	}

	function load() {
		try { return valid(JSON.parse(storage.getItem(key) || '{}')); } catch (error) { return Object.assign({}, defaults); }
	}

	function init(doc, store) {
		document = doc || document; storage = store || storage;
		var host = document.querySelector('[data-elavira-toolbar]');
		if (!host) return null;
		var html = document.documentElement, trigger = host.querySelector('.elavira-toolbar__trigger'), dialog = host.querySelector('.elavira-toolbar__dialog'), close = host.querySelector('[data-elavira-close]'), state = load();
		var focusable = 'button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

		Object.keys(classMap).forEach(function (name) { if (!host.querySelector('[data-elavira-pref="' + name.replace(/[A-Z]/g, function (m) { return '_' + m.toLowerCase(); }) + '"]')) state[name] = false; });
		if (!host.querySelector('[name="elavira-appearance"]')) state.appearance = 'default';
		if (!host.querySelector('[data-elavira-text-increase]')) state.textSize = 100;

		function apply(persist) {
			html.classList.toggle('elavira-pref-text-size', state.textSize > 100);
			if (state.textSize > 100) html.style.setProperty('--elavira-text-size', state.textSize + '%'); else html.style.removeProperty('--elavira-text-size');
			Object.keys(classMap).forEach(function (name) { html.classList.toggle(classMap[name], state[name]); });
			html.classList.toggle('elavira-pref-appearance-light', state.appearance === 'light');
			html.classList.toggle('elavira-pref-appearance-dark', state.appearance === 'dark');
			var output = host.querySelector('[data-elavira-text-output]'); if (output) output.textContent = state.textSize + '%';
			host.querySelectorAll('[data-elavira-pref]').forEach(function (input) { input.checked = state[input.getAttribute('data-elavira-pref')]; });
			host.querySelectorAll('[name="elavira-appearance"]').forEach(function (input) { input.checked = input.value === state.appearance; });
			var guide = host.querySelector('[data-elavira-reading-guide]'); if (guide) guide.hidden = !state.readingGuide;
			if (persist) { try { storage.setItem(key, JSON.stringify(state)); } catch (error) {} }
		}

		function open() { dialog.hidden = false; trigger.setAttribute('aria-expanded', 'true'); document.body.classList.add('elavira-toolbar-open'); close.focus(); }
		function shut() { dialog.hidden = true; trigger.setAttribute('aria-expanded', 'false'); document.body.classList.remove('elavira-toolbar-open'); trigger.focus(); }
		trigger.addEventListener('click', open); close.addEventListener('click', shut);
		dialog.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { event.preventDefault(); shut(); return; }
			if (event.key !== 'Tab') return;
			var nodes = Array.prototype.slice.call(dialog.querySelectorAll(focusable)); if (!nodes.length) return;
			if (event.shiftKey && document.activeElement === nodes[0]) { event.preventDefault(); nodes[nodes.length - 1].focus(); }
			else if (!event.shiftKey && document.activeElement === nodes[nodes.length - 1]) { event.preventDefault(); nodes[0].focus(); }
		});
		host.querySelectorAll('[data-elavira-pref]').forEach(function (input) { input.addEventListener('change', function () { state[input.getAttribute('data-elavira-pref')] = input.checked; apply(true); }); });
		host.querySelectorAll('[name="elavira-appearance"]').forEach(function (input) { input.addEventListener('change', function () { state.appearance = input.value; apply(true); }); });
		var increase = host.querySelector('[data-elavira-text-increase]'), decrease = host.querySelector('[data-elavira-text-decrease]');
		if (increase) increase.addEventListener('click', function () { state.textSize = Math.min(150, state.textSize + 10); apply(true); });
		if (decrease) decrease.addEventListener('click', function () { state.textSize = Math.max(100, state.textSize - 10); apply(true); });
		host.querySelector('[data-elavira-reset]').addEventListener('click', function () { state = Object.assign({}, defaults); try { storage.removeItem(key); } catch (error) {} apply(false); });
		document.addEventListener('pointermove', function (event) { var guide = host.querySelector('[data-elavira-reading-guide]'); if (state.readingGuide && guide) guide.style.top = Math.max(0, event.clientY - 20) + 'px'; });
		document.addEventListener('keydown', function (event) { var guide = host.querySelector('[data-elavira-reading-guide]'); if (!state.readingGuide || !guide || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return; var top = parseInt(guide.style.top || '100', 10); guide.style.top = Math.max(0, top + (event.key === 'ArrowUp' ? -20 : 20)) + 'px'; });
		apply(false);
		return { open: open, close: shut, state: function () { return state; }, reset: function () { state = Object.assign({}, defaults); apply(false); } };
	}

	if (document) {
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document, storage); }); else init(document, storage);
	}
	return { init: init, valid: valid, defaults: defaults };
}));
