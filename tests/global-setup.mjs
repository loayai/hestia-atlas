import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { AUTH_DIR, BASE_URL } from './env.mjs';
import { login } from './helpers.mjs';

export default async function globalSetup() {
	fs.mkdirSync(AUTH_DIR, { recursive: true });
	const browser = await chromium.launch();
	for (const role of ['admin', 'user']) {
		const ctx = await browser.newContext({ baseURL: BASE_URL, ignoreHTTPSErrors: true });
		const page = await ctx.newPage();
		await login(page, role);
		await ctx.storageState({ path: path.join(AUTH_DIR, `${role}.json`) });
		await ctx.close();
	}
	await browser.close();
}
