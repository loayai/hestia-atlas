// Header tool search — filters the tool registry; Enter on free text falls back
// to Hestia's own object search (/search/?q=…), so nothing Hestia offers is lost.
import { icon } from './util.js';

function normalise(s) {
	return s
		.toLowerCase()
		.normalize('NFD')
		.replace(/[̀-ͯ]/g, '');
}

export function initSearch(data) {
	const form = document.querySelector('[data-ha-search]');
	if (!form) return;
	const input = form.querySelector('.ha-search-input');
	const results = form.querySelector('.ha-search-results');
	const tools = (data.search || []).map((t) => ({ ...t, key: normalise(`${t.l} ${t.g}`) }));
	const i18n = data.i18n || {};
	let active = -1;
	let items = [];

	function close() {
		results.hidden = true;
		input.setAttribute('aria-expanded', 'false');
		input.removeAttribute('aria-activedescendant');
		active = -1;
	}

	function highlight(i) {
		items.forEach((el, n) => el.classList.toggle('is-active', n === i));
		active = i;
		if (items[i]) {
			input.setAttribute('aria-activedescendant', items[i].id);
			items[i].scrollIntoView({ block: 'nearest' });
		}
	}

	function render() {
		const q = normalise(input.value.trim());
		results.replaceChildren();
		items = [];
		if (!q) {
			close();
			return;
		}
		const words = q.split(/\s+/);
		const matches = tools
			.filter((t) => words.every((w) => t.key.includes(w)))
			.sort((a, b) => normalise(a.l).indexOf(words[0]) - normalise(b.l).indexOf(words[0]))
			.slice(0, 8);
		const seen = new Set();
		matches.forEach((t) => {
			if (seen.has(t.h + t.l)) return;
			seen.add(t.h + t.l);
			const a = document.createElement('a');
			a.href = t.h;
			a.className = 'ha-search-item';
			a.id = `ha-sr-${items.length}`;
			a.setAttribute('role', 'option');
			if (t.x) {
				a.target = '_blank';
				a.rel = 'noopener';
			}
			a.append(icon(data.icons, t.i, 'ha-search-item-icon'));
			const label = document.createElement('span');
			label.className = 'ha-search-item-label';
			label.textContent = t.l;
			const group = document.createElement('span');
			group.className = 'ha-search-item-group';
			group.textContent = t.g;
			a.append(label, group);
			results.append(a);
			items.push(a);
		});
		if (!matches.length) {
			const empty = document.createElement('div');
			empty.className = 'ha-search-empty';
			empty.textContent = i18n.noTools || 'No matching tools';
			results.append(empty);
		}
		// Fallback to Hestia's object search.
		const all = document.createElement('button');
		all.type = 'submit';
		all.className = 'ha-search-item ha-search-all';
		all.id = `ha-sr-${items.length}`;
		all.setAttribute('role', 'option');
		all.append(icon(data.icons, 'search', 'ha-search-item-icon'));
		const text = document.createElement('span');
		text.className = 'ha-search-item-label';
		text.textContent = `${i18n.searchFor || 'Search for'} “${input.value.trim()}”`;
		all.append(text);
		results.append(all);
		items.push(all);
		results.hidden = false;
		input.setAttribute('aria-expanded', 'true');
		highlight(matches.length ? 0 : items.length - 1);
	}

	input.addEventListener('input', render);
	input.addEventListener('focus', () => input.value.trim() && render());
	input.addEventListener('keydown', (evt) => {
		if (results.hidden) return;
		if (evt.key === 'ArrowDown') {
			evt.preventDefault();
			highlight((active + 1) % items.length);
		} else if (evt.key === 'ArrowUp') {
			evt.preventDefault();
			highlight((active - 1 + items.length) % items.length);
		} else if (evt.key === 'Enter' && items[active] && items[active].tagName === 'A') {
			evt.preventDefault();
			items[active].click();
		} else if (evt.key === 'Escape') {
			close();
			input.blur();
		}
	});
	form.addEventListener('submit', (evt) => {
		if (!input.value.trim()) {
			evt.preventDefault();
			input.focus();
		}
	});
	document.addEventListener('click', (evt) => {
		if (!form.contains(evt.target) && !evt.target.closest('[data-ha-action="open-search"]')) {
			close();
			document.documentElement.classList.remove('ha-search-open');
		}
	});

	// "/" focuses the search; ignore while typing in a field.
	document.addEventListener('keydown', (evt) => {
		if (evt.key !== '/' || evt.ctrlKey || evt.metaKey || evt.altKey) return;
		const t = evt.target;
		if (t.closest('input, textarea, select, [contenteditable="true"]')) return;
		evt.preventDefault();
		evt.stopPropagation();
		document.documentElement.classList.add('ha-search-open');
		input.focus();
	}, true);

	// Compact header: search opens as an overlay row.
	document.querySelector('[data-ha-action="open-search"]')?.addEventListener('click', () => {
		document.documentElement.classList.add('ha-search-open');
		input.focus();
	});
	input.addEventListener('blur', () => {
		setTimeout(() => {
			if (!form.contains(document.activeElement)) {
				document.documentElement.classList.remove('ha-search-open');
			}
		}, 150);
	});
}
