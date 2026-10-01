// Sidebar navigation: grouped accordion, collapsible icon rail and off-canvas drawer.
import { mqMobileNav, store } from './util.js';

export function initNav() {
	const root = document.documentElement;
	const sidebar = document.getElementById('ha-sidebar');
	if (!sidebar) {
		return;
	}
	const backdrop = document.querySelector('.ha-backdrop');
	const opener = document.querySelector('.ha-menu-toggle');
	const remembered = new Set(store.get('nav-open', []));

	// Restore groups the user expanded earlier (the active group is always open).
	sidebar.querySelectorAll('.ha-nav-group').forEach((group) => {
		if (remembered.has(group.dataset.haGroup)) {
			setGroup(group, true);
		}
	});

	function setGroup(group, open) {
		group.classList.toggle('is-open', open);
		group.querySelector('.ha-nav-toggle')?.setAttribute('aria-expanded', String(open));
	}

	function rail() {
		return root.classList.contains('ha-nav-collapsed') && !mqMobileNav.matches;
	}

	sidebar.addEventListener('click', (evt) => {
		const toggle = evt.target.closest('.ha-nav-toggle');
		if (!toggle) {
			return;
		}
		const group = toggle.closest('.ha-nav-group');
		const open = !group.classList.contains('is-open');
		if (rail()) {
			// In the icon rail a group opens as a flyout; close the others.
			sidebar.querySelectorAll('.ha-nav-group.is-flyout').forEach((g) => g !== group && g.classList.remove('is-flyout'));
			group.classList.toggle('is-flyout');
			return;
		}
		setGroup(group, open);
		if (open) {
			remembered.add(group.dataset.haGroup);
		} else {
			remembered.delete(group.dataset.haGroup);
		}
		store.set('nav-open', [...remembered]);
	});

	document.addEventListener('click', (evt) => {
		if (!evt.target.closest('.ha-nav-group')) {
			sidebar.querySelectorAll('.ha-nav-group.is-flyout').forEach((g) => g.classList.remove('is-flyout'));
		}
	});

	// Collapse to icon rail (desktop).
	document.querySelectorAll('[data-ha-action="collapse-nav"]').forEach((btn) => {
		const sync = () => {
			const collapsed = root.classList.contains('ha-nav-collapsed');
			btn.setAttribute('aria-pressed', String(collapsed));
			btn.title = collapsed ? btn.dataset.expandLabel || 'Expand' : '';
		};
		btn.addEventListener('click', () => {
			root.classList.toggle('ha-nav-collapsed');
			try {
				localStorage.setItem('ha-nav-collapsed', root.classList.contains('ha-nav-collapsed') ? '1' : '0');
			} catch {
				/* ignore */
			}
			sidebar.querySelectorAll('.is-flyout').forEach((g) => g.classList.remove('is-flyout'));
			sync();
		});
		sync();
	});

	// Off-canvas drawer (tablet / mobile).
	function openDrawer() {
		root.classList.add('ha-nav-open');
		if (backdrop) backdrop.hidden = false;
		opener?.setAttribute('aria-expanded', 'true');
		sidebar.querySelector('a, button')?.focus({ preventScroll: true });
	}
	function closeDrawer() {
		if (!root.classList.contains('ha-nav-open')) return;
		root.classList.remove('ha-nav-open');
		if (backdrop) backdrop.hidden = true;
		opener?.setAttribute('aria-expanded', 'false');
		opener?.focus({ preventScroll: true });
	}
	document.addEventListener('click', (evt) => {
		const action = evt.target.closest('[data-ha-action]')?.dataset.haAction;
		if (action === 'open-nav') openDrawer();
		if (action === 'close-nav') closeDrawer();
	});
	document.addEventListener('keydown', (evt) => {
		if (evt.key === 'Escape') {
			closeDrawer();
			sidebar.querySelectorAll('.is-flyout').forEach((g) => g.classList.remove('is-flyout'));
		}
	});
	mqMobileNav.addEventListener('change', () => closeDrawer());
	sidebar.addEventListener('keydown', (evt) => {
		// Keep focus inside the drawer while it is open.
		if (evt.key !== 'Tab' || !root.classList.contains('ha-nav-open')) return;
		const items = [...sidebar.querySelectorAll('a[href], button:not([disabled])')].filter((el) => el.offsetParent !== null);
		const first = items[0];
		const last = items[items.length - 1];
		if (evt.shiftKey && document.activeElement === first) {
			last.focus();
			evt.preventDefault();
		} else if (!evt.shiftKey && document.activeElement === last) {
			first.focus();
			evt.preventDefault();
		}
	});

	// Keep the active link visible in a long sidebar.
	sidebar.querySelector('.ha-nav-sublink.is-active, .ha-nav-item.is-active > .ha-nav-link')?.scrollIntoView({ block: 'nearest' });
}
