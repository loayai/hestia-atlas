// Build the installable theme into dist/.
//
//   dist/
//     VERSION, MANIFEST            version + sha256 list used by install.sh
//     lib/                         → /usr/local/hestia-atlas/lib   (PHP, outside the web root)
//     web/css/themes/custom/atlas.css   → $HESTIA/web/css/themes/custom/
//     web/js/custom_scripts/atlas.php   → $HESTIA/web/js/custom_scripts/
//     web/atlas/                 → $HESTIA/web/atlas/ (dashboard + static assets)
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import browserslist from 'browserslist';
import esbuild from 'esbuild';
import * as lightningcss from 'lightningcss';

const ROOT = path.resolve(import.meta.dirname, '..');
const SRC = path.join(ROOT, 'src');
const DIST = path.join(ROOT, 'dist');
const NM = path.join(ROOT, 'node_modules');
const pkg = JSON.parse(fs.readFileSync(path.join(ROOT, 'package.json'), 'utf8'));
const VERSION = pkg.version;

function write(rel, data) {
	const file = path.join(DIST, rel);
	fs.mkdirSync(path.dirname(file), { recursive: true });
	fs.writeFileSync(file, data);
}

function copy(from, rel) {
	write(rel, fs.readFileSync(from));
}

function walk(dir) {
	return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
		const p = path.join(dir, e.name);
		return e.isDirectory() ? walk(p) : [p];
	});
}

/** Split a call's argument list starting at `start` (just after "("), honouring nesting and strings. */
function splitArgs(code, start) {
	const args = [];
	let depth = 0;
	let cur = '';
	let quote = null;
	for (let i = start; i < code.length; i++) {
		const ch = code[i];
		if (quote) {
			cur += ch;
			if (ch === quote && code[i - 1] !== '\\') quote = null;
			continue;
		}
		if (ch === '"' || ch === "'") {
			quote = ch;
			cur += ch;
		} else if ('([{'.includes(ch)) {
			depth++;
			cur += ch;
		} else if (')]}'.includes(ch)) {
			if (depth === 0) {
				args.push(cur);
				return args;
			}
			depth--;
			cur += ch;
		} else if (ch === ',' && depth === 0) {
			args.push(cur);
			cur = '';
		} else {
			cur += ch;
		}
	}
	return args;
}

fs.rmSync(DIST, { recursive: true, force: true });

// ---------------------------------------------------------------- PHP
for (const file of walk(path.join(SRC, 'php', 'lib'))) {
	let code = fs.readFileSync(file, 'utf8');
	if (file.endsWith('bootstrap.php')) {
		code = code.replace(/define\("HA_VERSION", "[^"]+"\)/, `define("HA_VERSION", "${VERSION}")`);
	}
	write(path.join('lib', path.relative(path.join(SRC, 'php', 'lib'), file)), code);
}
copy(path.join(SRC, 'php', 'web', 'index.php'), 'web/atlas/index.php');
copy(path.join(SRC, 'php', 'custom_scripts_loader.php'), 'web/js/custom_scripts/atlas.php');

// ---------------------------------------------------------------- Shell (CLI + shared functions)
for (const name of ['common.sh', 'hestia-atlas']) {
	copy(path.join(SRC, 'sh', name), `bin/${name}`);
}

// ---------------------------------------------------------------- Icons
const iconNames = new Set();
const sources = [...walk(path.join(SRC, 'php')), ...walk(path.join(SRC, 'js'))];
for (const file of sources) {
	const code = fs.readFileSync(file, 'utf8');
	// ha_icon(<name expr>, "css classes") — only the first argument names an icon;
	// ha_tool_icon(<icon>) — likewise. String literals not used as array keys count.
	for (const m of code.matchAll(/ha_(tool_)?icon\(/g)) {
		const args = splitArgs(code, m.index + m[0].length);
		const iconArgs = args.slice(0, 1);
		for (const a of iconArgs) {
			for (const q of a.matchAll(/"([a-z0-9-]+)"(?!\])/g)) iconNames.add(q[1]);
		}
	}
	for (const m of code.matchAll(/"icon"\s*=>\s*"([a-z0-9-]+)"/g)) iconNames.add(m[1]);
	for (const m of code.matchAll(/icon\([^,]+,\s*'([a-z0-9-]+)'/g)) iconNames.add(m[1]);
}
const extra = path.join(SRC, 'icons', 'extra.txt');
if (fs.existsSync(extra)) {
	fs.readFileSync(extra, 'utf8')
		.split(/\s+/)
		.filter(Boolean)
		.forEach((n) => iconNames.add(n));
}
const iconDir = path.join(NM, 'lucide-static', 'icons');
const missing = [];
const symbols = [...iconNames].sort().map((name) => {
	const file = path.join(iconDir, `${name}.svg`);
	if (!fs.existsSync(file)) {
		missing.push(name);
		return '';
	}
	const svg = fs.readFileSync(file, 'utf8');
	const inner = svg
		.replace(/<!--[\s\S]*?-->/g, '')
		.replace(/^[\s\S]*?<svg[^>]*>/, '')
		.replace(/<\/svg>\s*$/, '')
		.replace(/\s+/g, ' ')
		.trim();
	return `<symbol id="${name}" viewBox="0 0 24 24">${inner}</symbol>`;
});
if (missing.length) {
	console.error(`Missing Lucide icons: ${missing.join(', ')}`);
	process.exit(1);
}
write(
	'web/atlas/assets/icons.svg',
	`<svg xmlns="http://www.w3.org/2000/svg"><!-- Lucide icons (ISC License) https://lucide.dev -->${symbols.join('')}</svg>\n`,
);

// ---------------------------------------------------------------- Fonts + licences
const FONT_PKG = path.join(NM, '@fontsource-variable', 'inter');
const fontDir = path.join(FONT_PKG, 'files');
const FONT_SUBSETS = ['latin', 'latin-ext', 'cyrillic', 'cyrillic-ext', 'greek', 'vietnamese'];
// unicode-range per subset, read from the font package so it never drifts
const fontRanges = Object.fromEntries(
	[...fs.readFileSync(path.join(FONT_PKG, 'wght.css'), 'utf8').matchAll(/inter-([a-z-]+)-wght-normal\.woff2\)[^;]*;\s*unicode-range:\s*([^;]+);/g)].map((m) => [m[1], m[2].trim()]),
);
for (const subset of FONT_SUBSETS) {
	const name = `inter-${subset}-wght-normal.woff2`;
	copy(path.join(fontDir, name), `web/atlas/assets/fonts/${name}`);
}
// src/css/fonts.css is generated (committed so the CSS sources are self-explanatory)
fs.writeFileSync(
	path.join(SRC, 'css', 'fonts.css'),
	`/* Inter (variable, SIL Open Font License 1.1) — served from the panel itself.
   GENERATED by scripts/build.mjs from @fontsource-variable/inter; do not edit. */
${FONT_SUBSETS.map(
	(sub) => `@font-face {
	font-family: "Inter";
	font-style: normal;
	font-display: swap;
	font-weight: 100 900;
	src: url("/atlas/assets/fonts/inter-${sub}-wght-normal.woff2") format("woff2-variations");
	unicode-range: ${fontRanges[sub]};
}
`,
).join('\n')}`,
);
write(
	'web/atlas/assets/LICENSES.txt',
	[
		'Hestia Atlas theme — third-party assets',
		'',
		'Inter (variable) — SIL Open Font License 1.1',
		fs.readFileSync(path.join(FONT_PKG, 'LICENSE'), 'utf8'),
		'',
		'Lucide icons — ISC License',
		fs.readFileSync(path.join(NM, 'lucide-static', 'LICENSE'), 'utf8'),
	].join('\n'),
);

// ---------------------------------------------------------------- CSS
for (const theme of ['atlas', 'atlas-evergreen']) {
	const { code: css } = await lightningcss.bundleAsync({
		filename: path.join(SRC, 'css', `${theme}.css`),
		minify: true,
		targets: lightningcss.browserslistToTargets(browserslist('defaults')),
		drafts: { customMedia: true },
	});
	write(`web/css/themes/custom/${theme}.css`, css);
}

// ---------------------------------------------------------------- JS
const js = await esbuild.build({
	entryPoints: [path.join(SRC, 'js', 'main.js')],
	bundle: true,
	minify: true,
	format: 'iife',
	target: ['es2020'],
	write: false,
	legalComments: 'none',
	banner: { js: `/* Hestia Atlas theme ${VERSION} — GPL-3.0-or-later */` },
});
write('web/atlas/assets/atlas.js', js.outputFiles[0].contents);

// ---------------------------------------------------------------- Manifest
write('VERSION', `${VERSION}\n`);
const files = walk(DIST)
	.map((f) => path.relative(DIST, f).split(path.sep).join('/'))
	.filter((f) => f !== 'MANIFEST' && f !== 'VERSION')
	.sort();
const manifest = files
	.map((f) => `${createHash('sha256').update(fs.readFileSync(path.join(DIST, f))).digest('hex')}  ${f}`)
	.join('\n');
write('MANIFEST', `${manifest}\n`);

const size = (rel) => `${(fs.statSync(path.join(DIST, rel)).size / 1024).toFixed(1)} KB`;
console.log(`Built ${VERSION}: ${files.length} files`);
console.log(`  css ${size('web/css/themes/custom/atlas.css')} (+ atlas-evergreen.css), js ${size('web/atlas/assets/atlas.js')}, icons ${iconNames.size} (${size('web/atlas/assets/icons.svg')})`);

// ---------------------------------------------------------------- Lite (CSS-only) themes → lite/
// Single self-contained stylesheets: same design tokens and components, scoped to `html`
// instead of the `.ha` class the full theme sets from PHP/JS, with Inter (latin) embedded.
{
	const LITE = path.join(ROOT, 'lite');
	fs.mkdirSync(LITE, { recursive: true });
	const latin = fs.readFileSync(path.join(fontDir, 'inter-latin-wght-normal.woff2')).toString('base64');
	const face = `@font-face{font-family:"Inter";font-style:normal;font-display:swap;font-weight:100 900;src:url(data:font/woff2;base64,${latin}) format("woff2-variations");unicode-range:${fontRanges.latin.replace(/\s+/g, '')}}`;
	const sums = [];
	for (const theme of ['atlas-lite', 'atlas-lite-evergreen']) {
		const { code } = await lightningcss.bundleAsync({
			filename: path.join(SRC, 'lite', `${theme}.css`),
			minify: true,
			targets: lightningcss.browserslistToTargets(browserslist('defaults')),
			drafts: { customMedia: true },
		});
		let css = code.toString();
		const banner = css.match(/^\/\*![\s\S]*?\*\//)?.[0] ?? '';
		css = css.slice(banner.length);
		// Scope rewrite: `.ha-guest` / `.ha` (exact class) → `html`; `.ha-*` component classes stay.
		css = css.replace(/\.ha-guest(?![\w-])/g, 'html').replace(/\.ha(?![\w-])/g, 'html');
		css = css.replace(/html\.ha-loading [^{]+\{[^}]*\}/g, '');
		const out = [banner, `${face}${css}`].join('\n');
		fs.writeFileSync(path.join(LITE, `${theme}.css`), out);
		const sum = createHash('sha256').update(out).digest('hex');
		sums.push(`${sum}  ${theme}.css`);
		console.log(`  lite/${theme}.css ${(out.length / 1024).toFixed(1)} KB (sha256 ${sum.slice(0, 12)}…)`);
	}
	fs.writeFileSync(path.join(LITE, 'SHA256SUMS'), [...sums, ''].join('\n'));
}
