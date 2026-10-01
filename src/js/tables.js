// Resource lists (Hestia ".units-table"): row actions.
// The edit action becomes a labelled "Manage" style button; other actions stay icon
// buttons with tooltips. All links, confirmation hooks and shortcuts are untouched.

const PRIMARY = ['shortcut-enter'];

export function enhanceTables() {
	document.querySelectorAll('.units-table').forEach((table) => {
		table.classList.add('ha-table');
		table.querySelectorAll('.units-table-row').forEach((row) => {
			const actions = row.querySelector('.units-table-row-actions');
			if (!actions) return;
			const items = [...actions.querySelectorAll(':scope > .units-table-row-action')];
			items.forEach((item) => {
				const link = item.querySelector('.units-table-row-action-link, a, button');
				if (!link) return;
				const label = link.getAttribute('title') || link.textContent.trim();
				if (label && !link.getAttribute('aria-label')) link.setAttribute('aria-label', label);
				if (PRIMARY.some((c) => item.classList.contains(c))) item.classList.add('ha-action-primary');
				if (item.classList.contains('shortcut-delete') || /\/delete\//.test(link.getAttribute('href') || '')) {
					item.classList.add('ha-action-danger');
				}
			});
			actions.classList.toggle('ha-actions-many', items.length > 5);
		});
	});
}
