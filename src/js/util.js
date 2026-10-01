// Shared helpers.

/** Data emitted by the PHP head hook (#ha-data). */
export function readData() {
	const el = document.getElementById('ha-data');
	if (!el) {
		return { search: [], i18n: {}, page: {} };
	}
	try {
		return JSON.parse(el.textContent);
	} catch {
		return { search: [], i18n: {}, page: {} };
	}
}

/** localStorage wrapper that never throws (private mode, blocked storage). */
export const store = {
	get(key, fallback = null) {
		try {
			const v = localStorage.getItem(`ha-${key}`);
			return v === null ? fallback : JSON.parse(v);
		} catch {
			return fallback;
		}
	},
	set(key, value) {
		try {
			localStorage.setItem(`ha-${key}`, JSON.stringify(value));
		} catch {
			/* ignore */
		}
	},
	clear() {
		try {
			Object.keys(localStorage)
				.filter((k) => k.startsWith('ha-'))
				.forEach((k) => localStorage.removeItem(k));
		} catch {
			/* ignore */
		}
	},
};

/** Build an <svg><use> icon from the bundled sprite. */
export function icon(spriteUrl, name, cls = '') {
	const ns = 'http://www.w3.org/2000/svg';
	const svg = document.createElementNS(ns, 'svg');
	svg.setAttribute('class', `ha-i ${cls}`.trim());
	svg.setAttribute('aria-hidden', 'true');
	svg.setAttribute('focusable', 'false');
	const use = document.createElementNS(ns, 'use');
	use.setAttribute('href', `${spriteUrl}#${name}`);
	svg.append(use);
	return svg;
}

export const mqMobileNav = window.matchMedia('(max-width: 991.98px)');
