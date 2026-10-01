// Generic dropdowns ([data-ha-dropdown]) used by the user menu.

export function initDropdowns() {
	const dropdowns = [...document.querySelectorAll('[data-ha-dropdown]')];

	function close(dd, focus = false) {
		const btn = dd.querySelector('[data-ha-dropdown-toggle]');
		const panel = dd.querySelector('.ha-dropdown-panel');
		if (panel.hidden) return;
		panel.hidden = true;
		btn.setAttribute('aria-expanded', 'false');
		if (focus) btn.focus();
	}

	dropdowns.forEach((dd) => {
		const btn = dd.querySelector('[data-ha-dropdown-toggle]');
		const panel = dd.querySelector('.ha-dropdown-panel');
		btn.addEventListener('click', () => {
			const open = panel.hidden;
			dropdowns.forEach((other) => other !== dd && close(other));
			panel.hidden = !open;
			btn.setAttribute('aria-expanded', String(open));
			if (open) panel.querySelector('a, button')?.focus();
		});
		dd.addEventListener('keydown', (evt) => {
			if (evt.key === 'Escape') {
				close(dd, true);
			}
			if (evt.key === 'ArrowDown' || evt.key === 'ArrowUp') {
				const items = [...panel.querySelectorAll('a, button')];
				const i = items.indexOf(document.activeElement);
				if (i === -1) return;
				evt.preventDefault();
				const next = evt.key === 'ArrowDown' ? items[(i + 1) % items.length] : items[(i - 1 + items.length) % items.length];
				next.focus();
			}
		});
	});

	document.addEventListener('click', (evt) => {
		dropdowns.forEach((dd) => {
			if (!dd.contains(evt.target)) close(dd);
		});
	});
}
