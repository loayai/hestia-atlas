// Light / dark mode. The boot script in <head> sets html[data-ha-mode] before first paint
// from the remembered choice or the system preference; this wires the header toggle.
const KEY = 'ha-color-mode';

function stored() {
	try {
		const v = localStorage.getItem(KEY);
		return v === 'light' || v === 'dark' ? v : null;
	} catch {
		return null;
	}
}

export function initMode() {
	const root = document.documentElement;
	const buttons = document.querySelectorAll('[data-ha-action="toggle-mode"]');
	const system = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

	function apply(mode) {
		root.setAttribute('data-ha-mode', mode);
		buttons.forEach((btn) => {
			const label = mode === 'dark' ? btn.dataset.labelLight : btn.dataset.labelDark;
			btn.setAttribute('aria-label', label);
			btn.title = label;
			btn.setAttribute('aria-pressed', String(mode === 'dark'));
		});
	}

	apply(root.getAttribute('data-ha-mode') === 'dark' ? 'dark' : 'light');

	buttons.forEach((btn) =>
		btn.addEventListener('click', () => {
			const next = root.getAttribute('data-ha-mode') === 'dark' ? 'light' : 'dark';
			try {
				localStorage.setItem(KEY, next);
			} catch {
				/* ignore */
			}
			apply(next);
		}),
	);

	// Follow the operating system until the user picks a mode explicitly.
	system?.addEventListener('change', (evt) => {
		if (!stored()) apply(evt.matches ? 'dark' : 'light');
	});
}
