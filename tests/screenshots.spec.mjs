// Captures full-page screenshots of the real Hestia panel for visual review.
//   SHOT_SET=baseline|current (default: current)
//   SHOT_VIEWPORTS=desktop,mobile (default) or any of: wide,desktop,laptop,tablet-l,tablet,mobile
//   SHOT_FILTER=substring to limit pages
import fs from 'node:fs';
import path from 'node:path';
import { test } from '@playwright/test';
import { AUTH_DIR } from './env.mjs';
import { sanitize } from './sanitize.mjs';

const SET = process.env.SHOT_SET || 'current';
const OUT = path.resolve(import.meta.dirname, '..', 'screenshots', SET);
const VIEWPORTS = {
	wide: { width: 1440, height: 900 },
	desktop: { width: 1440, height: 900 },
	laptop: { width: 1280, height: 800 },
	'tablet-l': { width: 1024, height: 768 },
	tablet: { width: 768, height: 1024 },
	mobile: { width: 390, height: 844 },
};
const selected = (process.env.SHOT_VIEWPORTS || 'desktop,mobile').split(',');
const filter = process.env.SHOT_FILTER || '';
// SHOT_MODE=dark|light forces the colour mode (default: light) before every page load.
const MODE = process.env.SHOT_MODE === 'dark' ? 'dark' : 'light';
const forceMode = (ctx) => ctx.addInitScript((m) => { try { localStorage.setItem('ha-color-mode', m); } catch {} }, MODE);

// [name, url] — `{token}` is replaced with the session CSRF token.
const PAGES = {
	admin: [
		['dashboard', '/atlas/'],
		['users', '/list/user/'],
		['add-user', '/add/user/'],
		['edit-user', '/edit/user/?user=jdemo&token={token}'],
		['packages', '/list/package/'],
		['edit-package', '/edit/package/?package=default&token={token}'],
		['ips', '/list/ip/'],
		['firewall', '/list/firewall/'],
		['firewall-banlist', '/list/firewall/banlist/'],
		['services', '/list/server/'],
		['server-settings', '/edit/server/'],
		['server-info', '/list/server/?cpu'],
		['rrd', '/list/rrd/'],
		['updates', '/list/updates/'],
		['logs', '/list/log/'],
		['stats', '/list/stats/'],
		['profile', '/edit/user/?user=admin&token={token}'],
		['web-admin', '/list/web/'],
		['search', '/search/?q=demo&token={token}'],
	],
	user: [
		['dashboard', '/atlas/'],
		['web', '/list/web/'],
		['add-web', '/add/web/'],
		['edit-web', '/edit/web/?domain=atlas-demo.com&token={token}'],
		['dns', '/list/dns/'],
		['dns-records', '/list/dns/?domain=atlas-demo.com&token={token}'],
		['add-dns', '/add/dns/'],
		['mail', '/list/mail/'],
		['mail-accounts', '/list/mail/?domain=atlas-demo.com&token={token}'],
		['add-mail-account', '/add/mail/?domain=atlas-demo.com'],
		['db', '/list/db/'],
		['add-db', '/add/db/'],
		['cron', '/list/cron/'],
		['add-cron', '/add/cron/'],
		['backups', '/list/backup/'],
		['logs', '/list/log/'],
		['stats', '/list/stats/'],
		['profile', '/edit/user/?user=jdemo&token={token}'],
	],
};

fs.mkdirSync(OUT, { recursive: true });

test.describe('login', () => {
	for (const vp of selected) {
		test(`login @${vp}`, async ({ browser }) => {
			const ctx = await browser.newContext({ viewport: VIEWPORTS[vp], ignoreHTTPSErrors: true });
			await forceMode(ctx);
			const page = await ctx.newPage();
			await page.goto('/login/');
			await sanitize(page);
			await page.screenshot({ path: path.join(OUT, `login-${vp}.png`), fullPage: true });
			await page.locator('#username').fill('jdemo');
			await page.locator('#login-form button[type=submit]').click();
			await page.locator('#password').waitFor();
			await sanitize(page);
			await page.screenshot({ path: path.join(OUT, `login-password-${vp}.png`), fullPage: true });
			await ctx.close();
		});
	}
});

for (const [role, pages] of Object.entries(PAGES)) {
	test.describe(role, () => {
		for (const vp of selected) {
			test(`${role} pages @${vp}`, async ({ browser }) => {
				const ctx = await browser.newContext({
					viewport: VIEWPORTS[vp],
					storageState: path.join(AUTH_DIR, `${role}.json`),
					ignoreHTTPSErrors: true,
				});
				await forceMode(ctx);
				const page = await ctx.newPage();
				await page.goto('/');
				const token = await page.locator('#token').getAttribute('token');
				for (const [name, url] of pages) {
					if (filter && !`${role}-${name}`.includes(filter)) continue;
					await page.goto(url.replace('{token}', token), { referer: `${page.url().split('/').slice(0, 3).join('/')}/` });
					await page.waitForLoadState('networkidle').catch(() => {});
					await sanitize(page);
					await page.screenshot({ path: path.join(OUT, `${role}-${name}-${vp}.png`), fullPage: true });
				}
				await ctx.close();
			});
		}
	});
}
