// Tools page: collapsible sections, remembered per browser.
import { store } from './util.js';

export function initTools() {
	const sections = document.querySelectorAll('[data-ha-section]');
	if (!sections.length) return;
	const collapsed = new Set(store.get('tools-collapsed', []));

	sections.forEach((section) => {
		const id = section.dataset.haSection;
		const btn = section.querySelector('.ha-tool-section-toggle');
		const set = (closed) => {
			section.classList.toggle('is-collapsed', closed);
			btn.setAttribute('aria-expanded', String(!closed));
		};
		set(collapsed.has(id));
		btn.addEventListener('click', () => {
			const closed = !section.classList.contains('is-collapsed');
			set(closed);
			if (closed) collapsed.add(id);
			else collapsed.delete(id);
			store.set('tools-collapsed', [...collapsed]);
		});
	});

	// Deep links (#email) from breadcrumbs: make sure the section is open and visible.
	const target = window.location.hash && document.querySelector(`[data-ha-section="${CSS.escape(window.location.hash.slice(1))}"]`);
	if (target) {
		target.classList.remove('is-collapsed');
		target.querySelector('.ha-tool-section-toggle')?.setAttribute('aria-expanded', 'true');
		target.classList.add('is-highlight');
		setTimeout(() => target.classList.remove('is-highlight'), 1600);
	}
}
