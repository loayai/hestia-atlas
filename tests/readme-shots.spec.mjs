// Viewport-sized screenshots used in README.md (screenshots/readme/).
import path from 'node:path';
import { test } from '@playwright/test';
import { AUTH_DIR } from './env.mjs';
import { sanitize } from './sanitize.mjs';

const OUT = path.resolve(import.meta.dirname, '..', 'screenshots', 'readme');
// README_SCHEME=evergreen captures only the Evergreen example (run with that scheme active).
const EVERGREEN = process.env.README_SCHEME === 'evergreen';
const SHOTS = EVERGREEN ? [['user', 'evergreen-tools', '/atlas/', { width: 1440, height: 900 }]] : [
	['user', 'tools', '/atlas/', { width: 1440, height: 900 }],
	['user', 'web-domains', '/list/web/', { width: 1440, height: 760 }],
	['user', 'edit-web', '/edit/web/?domain=atlas-demo.com&token={token}', { width: 1440, height: 900 }],
	['user', 'mobile-tools', '/atlas/', { width: 390, height: 844 }],
	['admin', 'admin-tools', '/atlas/', { width: 1440, height: 900 }],
	['admin', 'services', '/list/server/', { width: 1440, height: 900 }],
	];

for (const [role, name, url, viewport] of SHOTS) {
	test(`readme ${name}`, async ({ browser, baseURL }) => {
		const ctx = await browser.newContext({ viewport, storageState: path.join(AUTH_DIR, `${role}.json`), ignoreHTTPSErrors: true });
		const page = await ctx.newPage();
		await page.goto('/atlas/', { referer: `${baseURL}/` });
		const token = await page.locator('#token').getAttribute('token');
		await page.goto(url.replace('{token}', token), { referer: `${baseURL}/` });
		await page.waitForLoadState('networkidle').catch(() => {});
		await page.evaluate(() => document.activeElement?.blur());
		await sanitize(page);
		await page.screenshot({ path: path.join(OUT, `${name}.png`) });
		await ctx.close();
	});
}

test('readme login', async ({ browser }) => {
	test.skip(EVERGREEN);
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
	const page = await ctx.newPage();
	await page.goto('/login/');
	await sanitize(page);
	await page.screenshot({ path: path.join(OUT, 'login.png') });
	await ctx.close();
});
