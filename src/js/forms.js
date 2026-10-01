// Form pages: the primary action sits at the end of the form ("Save" + "Cancel").
// Hestia renders Save in the top toolbar; we move that same button (same form= target,
// same handlers) into an action bar under the form. No field or submit logic changes.

export function enhanceForms(data) {
	const i18n = data.i18n || {};
	const main = document.getElementById('ha-main') || document.querySelector('.app-content');
	if (!main) return;
	const toolbar = main.querySelector(':scope > .toolbar');
	const hasHead = !!main.querySelector(':scope > .ha-page-head');

	if (toolbar) {
		toolbar.classList.add('ha-toolbar');
		// Primary "Add …" action on list pages (forms keep Save as their only primary action).
		const create = !document.getElementById('main-form') && toolbar.querySelector('a.js-button-create[href^="/add/"]');
		if (create) create.classList.add('ha-primary');
		// Back links read as plain text links.
		toolbar.querySelectorAll('.button-back').forEach((b) => b.classList.add('ha-back'));
	}

	if (hasHead) {
		document.documentElement.classList.add('ha-has-head');
		// Hestia repeats the page name as an <h1> inside forms; the page heading already shows it.
		const title = main.querySelector('.ha-page-title')?.firstChild?.textContent.trim().toLowerCase();
		main.querySelectorAll('.form-container h1, .container > h1').forEach((h) => {
			if (title && h.textContent.trim().toLowerCase() === title) h.classList.add('ha-dup-title');
		});
	}

	const form = document.getElementById('main-form');
	if (!form) {
		markEmptyToolbar(toolbar);
		return;
	}
	const save = toolbar?.querySelector('button[type="submit"][form="main-form"]');
	if (!save) {
		markEmptyToolbar(toolbar);
		return;
	}
	const container = form.querySelector('.form-container') || form;
	const bar = document.createElement('div');
	bar.className = 'ha-form-actions';
	save.classList.add('ha-primary');
	bar.append(save);
	const back = toolbar.querySelector('.button-back');
	if (back) {
		const cancel = document.createElement('a');
		cancel.href = back.getAttribute('href');
		cancel.className = 'ha-btn ha-btn-link js-button-back';
		cancel.textContent = i18n.cancel || 'Cancel';
		bar.append(cancel);
	}
	container.append(bar);
	document.documentElement.classList.add('ha-form-page');

	// Field-level helpers: tag the containers of checkbox groups and "Advanced" toggles.
	container.querySelectorAll('.form-check').forEach((el) => el.closest('div:not(.form-check)')?.classList.add('ha-checks'));
	markEmptyToolbar(toolbar);
}

/** Hide the toolbar when only the Back link is left in it (the breadcrumb covers it). */
function markEmptyToolbar(toolbar) {
	if (!toolbar) return;
	const controls = [...toolbar.querySelectorAll('a, button, select, input, form, .inline-alert')].filter(
		(el) => !el.classList.contains('button-back') && !el.closest('.button-back'),
	);
	toolbar.classList.toggle('ha-toolbar-minimal', controls.length === 0);
}
