// Functional workflows on the real Hestia dev server with the Hestia Atlas theme installed.
// Destructive steps only touch objects created by the tests themselves (prefix "hatest").
import path from 'node:path';
import { expect, test } from '@playwright/test';
import { ACCOUNTS, AUTH_DIR } from './env.mjs';
import { login } from './helpers.mjs';

const RUN = Date.now().toString(36).slice(-5);
const userState = path.join(AUTH_DIR, 'user.json');
const adminState = path.join(AUTH_DIR, 'admin.json');

/** Navigate like a click would (Hestia checks the Referer for CSRF on GET pages). */
async function go(page, url) {
	const origin = new URL(page.url().startsWith('http') ? page.url() : test.info().project.use.baseURL).origin;
	await page.goto(url, { referer: `${origin}/` });
}

async function expectAlert(page, kind) {
	await expect(page.locator(`.inline-alert-${kind}, .alert-${kind}`).first()).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test.describe('authentication', () => {
	test('login lands on the Tools page, logout returns to login', async ({ page }) => {
		await login(page, 'user');
		await expect(page).toHaveURL(/\/atlas\/$/);
		await expect(page.locator('h1.ha-page-title')).toHaveText(/Tools/);
		await expect(page.locator('.ha-sidebar')).toBeVisible();
		await page.locator('.ha-user-btn').click();
		await expect(page.locator('.ha-usermenu-panel')).toBeVisible();
		await page.locator('.ha-usermenu-logout').click();
		await expect(page).toHaveURL(/\/login\//);
		await expect(page.locator('#username')).toBeVisible();
	});

	// Each run records a failed login; Hestia's fail2ban jail bans after 5 failures, so this
	// check is opt-in: HA_TEST_BAD_LOGIN=1 npm test
	test('wrong password shows a styled error', async ({ page }) => {
		test.skip(!process.env.HA_TEST_BAD_LOGIN, 'set HA_TEST_BAD_LOGIN=1 to run (counts towards fail2ban)');
		await page.goto('/login/');
		await page.locator('#username').fill(ACCOUNTS.user.user);
		await page.locator('#login-form button[type=submit]').click();
		await page.locator('#password').fill('definitely-wrong-password');
		await page.locator('#login-form button[type=submit]').click();
		await expect(page.locator('.login .error')).toBeVisible();
		await expect(page.locator('html')).toHaveClass(/ha-guest/);
	});
});

test.describe('navigation and search', () => {
	test.use({ storageState: userState });

	test('sidebar groups expand and links mark the active page', async ({ page }) => {
		await go(page, '/atlas/');
		const group = page.locator('.ha-nav-group[data-ha-group="databases"]');
		// "Databases" has a single sidebar entry → rendered as a direct link
		const dbLink = page.locator('.ha-nav-item a[href="/list/db/"]');
		if (await group.count()) {
			await group.locator('.ha-nav-toggle').click();
			await expect(group).toHaveClass(/is-open/);
			await group.locator('a[href="/list/db/"]').click();
		} else {
			await dbLink.click();
		}
		await expect(page).toHaveURL(/\/list\/db\//);
		await expect(page.locator('.ha-breadcrumb')).toContainText('Databases');
		await expect(page.locator('.ha-nav [aria-current="page"]')).toHaveCount(1);
	});

	test('tool search filters tools and falls back to object search', async ({ page }) => {
		await go(page, '/atlas/');
		await page.keyboard.press('/');
		await expect(page.locator('#ha-search-input')).toBeFocused();
		await page.keyboard.type('cron');
		await expect(page.locator('.ha-search-item').first()).toContainText('Cron Jobs');
		await page.keyboard.press('Enter');
		await expect(page).toHaveURL(/\/list\/cron\//);

		await page.locator('#ha-search-input').fill('atlas-demo');
		await page.locator('.ha-search-all').click();
		await expect(page).toHaveURL(/\/search\/\?/);
		await expect(page.locator('.units-table-row').first()).toContainText('atlas-demo');
	});

	test('tool sections collapse and stay collapsed after reload', async ({ page }) => {
		await go(page, '/atlas/');
		const section = page.locator('[data-ha-section="files"]');
		await section.locator('.ha-tool-section-toggle').click();
		await expect(section).toHaveClass(/is-collapsed/);
		await page.reload();
		await expect(page.locator('[data-ha-section="files"]')).toHaveClass(/is-collapsed/);
		await page.locator('[data-ha-section="files"] .ha-tool-section-toggle').click();
		await expect(page.locator('[data-ha-section="files"]')).not.toHaveClass(/is-collapsed/);
	});

	test('sidebar collapses to an icon rail and back', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 });
		await go(page, '/list/web/');
		await page.locator('.ha-sidebar-collapse').click();
		await expect(page.locator('html')).toHaveClass(/ha-nav-collapsed/);
		await expect.poll(async () => (await page.locator('.ha-sidebar').boundingBox()).width).toBeLessThan(80);
		await page.reload();
		await expect(page.locator('html')).toHaveClass(/ha-nav-collapsed/);
		await page.locator('.ha-sidebar-collapse').click();
		await expect(page.locator('html')).not.toHaveClass(/ha-nav-collapsed/);
	});
});

test.describe('web domain lifecycle (user)', () => {
	test.use({ storageState: userState });
	const domain = `hatest-${RUN}.example.org`;

	test('validation error is shown for an invalid domain', async ({ page }) => {
		await go(page, '/add/web/');
		await page.locator('#v_domain').fill('not a domain!');
		await page.locator('.ha-form-actions button[type=submit]').click();
		await expectAlert(page, 'danger');
		// The form is re-rendered with the action bar and page heading intact
		await expect(page.locator('.ha-form-actions')).toBeVisible();
		await expect(page.locator('h1.ha-page-title')).toHaveText(/Add Web Domain/);
	});

	test('create, edit and delete a web domain', async ({ page }) => {
		await go(page, '/add/web/');
		await page.locator('#v_domain').fill(domain);
		await page.locator('.ha-form-actions button[type=submit]').click();
		await expectAlert(page, 'success');

		await go(page, '/list/web/');
		const row = page.locator('.units-table-row', { hasText: domain });
		await expect(row).toHaveCount(1);

		// Edit: the primary "Edit Domain" action is labelled
		await row.locator('.ha-action-primary a').click();
		await expect(page.locator('h1.ha-page-title')).toContainText('Edit Web Domain');
		await page.locator('#v_aliases').fill(`www.${domain}\nalias.${domain}`);
		await page.locator('.ha-form-actions button[type=submit]').click();
		await expectAlert(page, 'success');

		// Bulk suspend / unsuspend from the toolbar
		await go(page, '/list/web/');
		const bulk = page.locator('form[action="/bulk/web/"]');
		await page.locator('.units-table-row', { hasText: domain }).locator('.js-unit-checkbox').check();
		await bulk.locator('select[name=action]').selectOption('suspend');
		await bulk.locator('button[type=submit]').click();
		await expect(page.locator('.units-table-row.disabled', { hasText: domain })).toHaveCount(1);
		await page.locator('.units-table-row', { hasText: domain }).locator('.js-unit-checkbox').check();
		await page.locator('form[action="/bulk/web/"] select[name=action]').selectOption('unsuspend');
		await page.locator('form[action="/bulk/web/"] button[type=submit]').click();
		await expect(page.locator('.units-table-row.disabled', { hasText: domain })).toHaveCount(0);

		// Delete: Hestia's confirmation dialog, styled by the theme
		await go(page, '/list/web/');
		await page.locator('.units-table-row', { hasText: domain }).locator('.ha-action-danger a').click();
		const dialog = page.locator('dialog.modal');
		await expect(dialog).toBeVisible();
		await expect(dialog.locator('.modal-message')).toContainText(domain);
		await dialog.locator('.modal-options .button:not(.button-secondary)').click();
		await expect(page.locator('.units-table-row', { hasText: domain })).toHaveCount(0);
	});

	test('cancel in a confirmation dialog keeps the object', async ({ page }) => {
		await go(page, '/list/web/');
		const row = page.locator('.units-table-row', { hasText: 'shop-demo.net' });
		await row.locator('.ha-action-danger a').click();
		await page.locator('dialog.modal .button-secondary').click();
		await expect(page.locator('dialog.modal')).toHaveCount(0);
		await expect(row).toHaveCount(1);
	});
});

test.describe('cron and bulk actions (user)', () => {
	test.use({ storageState: userState });
	const cmd = `/bin/true hatest-${RUN}`;

	test('create two cron jobs and bulk delete them', async ({ page }) => {
		for (const n of [1, 2]) {
			await go(page, '/add/cron/');
			await page.locator('#v_cmd').fill(`${cmd}-${n}`);
			for (const f of ['v_min', 'v_hour', 'v_day', 'v_month', 'v_wday']) await page.locator(`#${f}`).fill('*');
			await page.locator('.ha-form-actions button[type=submit]').click();
			await expectAlert(page, 'success');
		}
		await go(page, '/list/cron/');
		const rows = page.locator('.units-table-row', { hasText: `hatest-${RUN}` });
		await expect(rows).toHaveCount(2);
		// Regular users may only bulk-delete cron jobs (Hestia permission); suspend is admin-only.
		const again = rows;
		for (let i = 0; i < 2; i++) await again.nth(i).locator('.js-unit-checkbox').check();
		await expect(page.locator('.units-table-row.selected', { hasText: `hatest-${RUN}` })).toHaveCount(2);
		await page.locator('form[action="/bulk/cron/"] select[name=action]').selectOption('delete');
		await page.locator('form[action="/bulk/cron/"] button[type=submit]').click();
		await expect(page.locator('.units-table-row', { hasText: `hatest-${RUN}` })).toHaveCount(0);
	});
});

test.describe('database lifecycle (user)', () => {
	test.use({ storageState: userState });

	test('create and delete a database', async ({ page }) => {
		const name = `hat${RUN}`;
		await go(page, '/add/db/');
		await page.locator('#v_database').fill(name);
		await page.locator('#v_dbuser').fill(name);
		await page.locator('#v_password').fill(`Aa1-${RUN}-${RUN}-x`);
		await page.locator('.ha-form-actions button[type=submit]').click();
		await expectAlert(page, 'success');
		await go(page, '/list/db/');
		const row = page.locator('.units-table-row', { hasText: name });
		await expect(row).toHaveCount(1);
		await row.locator('.ha-action-danger a').click();
		await page.locator('dialog.modal .modal-options .button:not(.button-secondary)').click();
		await expect(page.locator('.units-table-row', { hasText: name })).toHaveCount(0);
	});
});

test.describe('mobile', () => {
	test.use({ storageState: userState, viewport: { width: 390, height: 844 } });

	test('drawer navigation and compact search', async ({ page }) => {
		await go(page, '/atlas/');
		await expect(page.locator('.ha-sidebar')).not.toBeInViewport();
		await page.locator('.ha-menu-toggle').click();
		await expect(page.locator('html')).toHaveClass(/ha-nav-open/);
		await expect(page.locator('.ha-sidebar')).toBeInViewport();
		await page.keyboard.press('Escape');
		await expect(page.locator('html')).not.toHaveClass(/ha-nav-open/);

		await page.locator('.ha-menu-toggle').click();
		const email = page.locator('.ha-nav-group[data-ha-group="email"]');
		await email.locator('.ha-nav-toggle').click();
		await email.locator('a[href="/list/mail/"]').click();
		await expect(page).toHaveURL(/\/list\/mail\//);
		await expect(page.locator('html')).not.toHaveClass(/ha-nav-open/);

		await page.locator('.ha-search-toggle').click();
		await expect(page.locator('#ha-search-input')).toBeVisible();
		await page.locator('#ha-search-input').fill('backup');
		await expect(page.locator('.ha-search-item').first()).toContainText('Backup');
	});

	test('tables become cards with visible actions; forms keep a Save bar', async ({ page }) => {
		await go(page, '/list/web/');
		const first = page.locator('.units-table-row').first();
		await expect(first.locator('.units-table-row-action-link').first()).toBeVisible();
		const box = await first.boundingBox();
		expect(box.width).toBeLessThanOrEqual(390);
		await go(page, '/add/web/');
		await expect(page.locator('.ha-form-actions button[type=submit]')).toBeInViewport();
		// no horizontal overflow
		expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
	});
});

test.describe('administrator', () => {
	test.use({ storageState: adminState });

	test('admin sees Administration and Server sections and server status', async ({ page }) => {
		await go(page, '/atlas/');
		await expect(page.locator('[data-ha-section="admin"]')).toBeVisible();
		await expect(page.locator('[data-ha-section="server"]')).toBeVisible();
		await expect(page.locator('.ha-service-list li').first()).toBeVisible();
		await go(page, '/list/user/');
		await expect(page.locator('.units-table-row', { hasText: 'jdemo' })).toHaveCount(1);
	});

	test('notifications panel opens', async ({ page }) => {
		await go(page, '/atlas/');
		await page.locator('.ha-notifications button').first().click();
		await expect(page.locator('.ha-notifications-panel')).toBeVisible();
		await expect(page.locator('.ha-notifications-panel')).not.toContainText('Loading');
	});

	test('impersonate a user and exit back to the administrator', async ({ page }) => {
		await go(page, '/list/user/');
		await page.locator('.units-table-row', { hasText: 'jdemo' }).locator('a[href*="loginas=jdemo"]').first().click();
		await expect(page.locator('.ha-impersonation')).toContainText('jdemo');
		await expect(page.locator('[data-ha-section="admin"]')).toHaveCount(0);
		await go(page, '/atlas/');
		await expect(page.locator('[data-ha-section="admin"]')).toHaveCount(0);
		await page.locator('.ha-impersonation a').click();
		await go(page, '/atlas/');
		await expect(page.locator('.ha-impersonation')).toHaveCount(0);
		await expect(page.locator('[data-ha-section="admin"]')).toBeVisible();
	});

	test('a regular user cannot reach admin pages', async ({ browser }) => {
		const ctx = await browser.newContext({ storageState: userState, ignoreHTTPSErrors: true });
		const page = await ctx.newPage();
		await page.goto('/atlas/');
		await expect(page.locator('a[href="/list/user/"]')).toHaveCount(0);
		await go(page, '/list/package/');
		await expect(page.locator('.units-table-row', { hasText: 'default' })).toHaveCount(0);
		await ctx.close();
	});
});

test.describe('theme selection', () => {
	test.use({ storageState: userState });

	test('switching the user theme to default removes the shell, and back', async ({ page }) => {
		const tokenUrl = async () => `/edit/user/?user=${ACCOUNTS.user.user}&token=${await page.locator('#token').getAttribute('token')}`;
		await go(page, '/atlas/');
		await go(page, await tokenUrl());
		await page.locator('#v_user_theme').selectOption('default');
		await page.locator('.ha-form-actions button[type=submit]').click();
		await expectAlert(page, 'success');
		await go(page, '/list/web/');
		await expect(page.locator('.ha-sidebar')).toHaveCount(0);
		await expect(page.locator('.app-header .top-bar')).toBeVisible();

		await go(page, await tokenUrl());
		await page.locator('#v_user_theme').selectOption('atlas');
		await page.locator('#main-form button[type=submit], .toolbar button[type=submit][form="main-form"]').first().click();
		await expectAlert(page, 'success');
		await go(page, '/list/web/');
		await expect(page.locator('.ha-sidebar')).toBeVisible();
	});
});
