import { defineConfig } from '@playwright/test';
import { BASE_URL } from './env.mjs';

export default defineConfig({
	testDir: '.',
	outputDir: './test-results',
	timeout: 120_000,
	workers: 2,
	retries: 0,
	reporter: [['list']],
	globalSetup: './global-setup.mjs',
	use: {
		baseURL: BASE_URL,
		ignoreHTTPSErrors: true, // dev servers commonly use self-signed certificates
		screenshot: 'off',
		trace: 'off',
	},
});
