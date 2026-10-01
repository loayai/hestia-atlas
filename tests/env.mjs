// Loads dev-server test credentials from .secrets/dev-credentials.env (gitignored) or the environment.
import fs from 'node:fs';
import path from 'node:path';

const file = path.resolve(import.meta.dirname, '../.secrets/dev-credentials.env');
const env = { ...process.env };
if (fs.existsSync(file)) {
	for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
		const m = line.match(/^([A-Z_]+)=(.*)$/);
		if (m && !env[m[1]]) env[m[1]] = m[2];
	}
}
for (const k of ['HESTIA_URL', 'HESTIA_ADMIN_USER', 'HESTIA_ADMIN_PASSWORD', 'HESTIA_TEST_USER', 'HESTIA_TEST_PASSWORD']) {
	if (!env[k]) throw new Error(`Missing ${k}: create .secrets/dev-credentials.env (see docs/development.md)`);
}
export const BASE_URL = env.HESTIA_URL.replace(/\/$/, '');
export const ACCOUNTS = {
	admin: { user: env.HESTIA_ADMIN_USER, password: env.HESTIA_ADMIN_PASSWORD },
	user: { user: env.HESTIA_TEST_USER, password: env.HESTIA_TEST_PASSWORD },
};
export const AUTH_DIR = path.resolve(import.meta.dirname, '.auth');
