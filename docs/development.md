# Development

## Requirements

* Node.js 20+ and npm (build and tests run on your workstation)
* Python 3 with Pillow (optional, for `scripts/dev/contact_sheet.py`) and Paramiko (only to
  bootstrap SSH key access to a password-only dev server)
* A **disposable** HestiaCP 1.10 server you control. Tests create and delete objects on it.

## Layout

```
src/
  css/          design tokens, colour schemes and component styles
  lite/         CSS-only edition entry points (built to lite/*.css)
  js/           shell behaviour (bundled to one IIFE)
  php/lib/      head hook, navigation registry, dashboard data + view
  php/web/      /atlas/ controller
  sh/           shared shell functions + hestia-atlas CLI
dist/           build output that install.sh deploys (committed so servers need no Node)
lite/           CSS-only edition: built stylesheets, install/uninstall helpers, README
scripts/
  build.mjs     builds dist/ (+ icon sprite, fonts, MANIFEST with SHA-256 sums)
  dev/          dev-server helpers (ssh, deploy, seed data, contact sheets)
tests/          Playwright: screenshots + functional workflows
screenshots/    baseline (stock Hestia), current/evergreen (Atlas), lite/lite-evergreen, readme
docs/           architecture, updates, reference inventory
```

## Build

```bash
npm ci
npm run build
```

The build fails if a referenced Lucide icon does not exist. Icons are discovered from
`ha_icon()`, `ha_tool_icon()`, `"icon" =>` / `"accent" =>` entries and `icon(…, 'name')`
calls; add extra names to `src/icons/extra.txt` if needed.

## Dev server access

Credentials stay out of the repository:

* `.env` (gitignored) — `SERVER_HOST`, `SERVER_PORT`, `SERVER_USER`, `SERVER_PASSWORD`
* `python scripts/dev/devenv.py bootstrap-key` — installs a local ed25519 key on the server
  (`~/.ssh/hestia_atlas_dev_ed25519`) so later commands use key authentication
* `scripts/dev/rsh '<command>'` — run a command on the server
* `scripts/dev/deploy.sh [install options]` — build, upload and run the real `install.sh`
* `scripts/dev/seed.sh` — idempotent test data (users, domains, mail, databases, cron, backup);
  run on the server with `TEST_PASSWORD` in the environment

## Tests

Create `.secrets/dev-credentials.env` (gitignored):

```
HESTIA_URL=https://panel.example.com:8083
HESTIA_ADMIN_USER=admin
HESTIA_ADMIN_PASSWORD=…
HESTIA_TEST_USER=jdemo
HESTIA_TEST_PASSWORD=…
```

```bash
npx playwright install chromium
npm test                                   # all specs
npx playwright test -c tests/playwright.config.mjs tests/workflows.spec.mjs --workers=1
SHOT_SET=current SHOT_VIEWPORTS=desktop,mobile npm run shots
SHOT_VIEWPORTS=wide,laptop,tablet-l,tablet,mobile SHOT_FILTER=user-web npm run shots
HA_TEST_BAD_LOGIN=1 npx playwright test …  # includes a failed-login check (counts toward fail2ban)
```

Screenshots are sanitised before they are written: the panel hostname becomes
`panel.example.com`, the server IP `203.0.113.10` and other public IPs `198.51.100.24`.

Hestia rejects GET requests without a same-origin `Referer` on many pages (CSRF policy);
the tests navigate with a referer, like a real click.

To review many screenshots quickly:

```bash
python scripts/dev/contact_sheet.py /tmp/sheet 1000 2 0.5 screenshots/current/*-desktop.png
```

## Coding notes

* Never change Hestia field names, form targets or endpoints. Move or decorate existing
  elements instead of re-creating them.
* All user-visible strings go through `ha_t()` (Hestia's gettext); strings Hestia already
  translates (e.g. "Web Domains", "Databases") are therefore localised automatically.
* Keep selectors on Hestia's own class names so unreviewed pages inherit the design.
* `.ha` (on `<html>`) scopes theme rules; `.ha-app` is the authenticated shell, `.ha-guest`
  the login pages.
