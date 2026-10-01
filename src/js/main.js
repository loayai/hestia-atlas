// Hestia Atlas theme — client behaviour.
// Runs after Hestia's own scripts (deferred custom script). Everything here is progressive:
// if it fails, Hestia's markup and behaviour remain fully functional.
import { initDropdowns } from './dropdowns.js';
import { enhanceForms } from './forms.js';
import { initNav } from './nav.js';
import { initSearch } from './search.js';
import { enhanceTables } from './tables.js';
import { initTools } from './tools.js';
import { readData, store } from './util.js';

function ready(fn) {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', fn, { once: true });
	} else {
		fn();
	}
}

function safely(name, fn) {
	try {
		fn();
	} catch (err) {
		console.error(`[hestia-atlas] ${name} failed`, err);
	}
}

ready(() => {
	const root = document.documentElement;
	if (!root.classList.contains('ha')) {
		return;
	}
	if (root.classList.contains('ha-app')) {
		const data = readData();
		safely('nav', () => initNav(data));
		safely('dropdowns', initDropdowns);
		safely('search', () => initSearch(data));
		safely('tools', initTools);
		safely('forms', () => enhanceForms(data));
		safely('tables', () => enhanceTables(data));
		document.addEventListener('click', (evt) => {
			const btn = evt.target.closest('[data-ha-action="reset-ui"]');
			if (btn) {
				store.clear();
				window.location.reload();
			}
		});
	}
	root.classList.remove('ha-loading');
});
