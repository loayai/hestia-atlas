import { expect } from '@playwright/test';
import { ACCOUNTS } from './env.mjs';

/** Log in through Hestia's two-step login form. */
export async function login(page, role) {
	const { user, password } = ACCOUNTS[role];
	await page.goto('/login/?logout=true');
	await page.goto('/login/');
	await page.locator('#username').fill(user);
	await page.locator('#login-form button[type=submit]').click();
	await page.locator('#password').fill(password);
	await page.locator('#login-form button[type=submit]').click();
	await page.waitForURL((u) => !u.pathname.startsWith('/login'));
	await expect(page.locator('#token')).toHaveCount(1);
}

export async function token(page) {
	return page.locator('#token').getAttribute('token');
}
