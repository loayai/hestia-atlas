// Replace environment-identifying values (server IP, hostname, visitor IPs) with
// documentation placeholders before screenshots are written.
import dns from 'node:dns/promises';
import { BASE_URL } from './env.mjs';

const host = new URL(BASE_URL).hostname;
let serverIp = null;

export async function sanitizeRules() {
	if (serverIp === null) {
		serverIp = (await dns.lookup(host).catch(() => ({ address: '' }))).address;
	}
	const domain = host.split('.').slice(1).join('.'); // parent domain of the panel host
	return { host, serverIp, domain };
}

/** Run in the page: rewrite text nodes, input values and titles. */
export async function sanitize(page) {
	const rules = await sanitizeRules();
	await page.evaluate(({ host, serverIp, domain }) => {
		const ipv4 = /\b(?:\d{1,3}\.){3}\d{1,3}\b/g;
		const keep = new Set(['127.0.0.1', '0.0.0.0']);
		// Netmasks, private ranges and documentation placeholders are left untouched.
		const harmless = /^(255\.|0\.|10\.|127\.|192\.168\.|203\.0\.113\.|198\.51\.100\.|192\.0\.2\.)/;
		const fix = (s) => {
			if (!s) return s;
			let out = s.split(host).join('panel.example.com');
			if (domain) out = out.split(domain).join('example.net');
			return out.replace(ipv4, (ip) => (keep.has(ip) || harmless.test(ip) ? ip : ip === serverIp ? '203.0.113.10' : '198.51.100.24'));
		};
		const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
		while (walker.nextNode()) {
			const n = walker.currentNode;
			const v = fix(n.nodeValue);
			if (v !== n.nodeValue) n.nodeValue = v;
		}
		document.querySelectorAll('input, textarea').forEach((el) => {
			const v = fix(el.value);
			if (v !== el.value) el.value = v;
		});
		document.querySelectorAll('select option').forEach((o) => {
			const v = fix(o.textContent);
			if (v !== o.textContent) o.textContent = v;
		});
	}, rules);
}
