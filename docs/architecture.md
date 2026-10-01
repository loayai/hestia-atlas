# Architecture

Hestia Atlas is an add-on, not a fork. It changes how HestiaCP *looks and is organised*
while every request is still handled by Hestia's own controllers, permission checks,
CSRF protection and `v-*` commands.

## Extension points used (no core patches)

HestiaCP 1.10 ships two supported customisation hooks, and the theme uses only those:

| Hestia mechanism | File installed | Purpose |
| --- | --- | --- |
| Custom themes — `web/css/themes/custom/<name>.css`, selectable as a system or per-user theme | `web/css/themes/custom/atlas.css` | All styling. Loaded after Hestia's base theme, so it only overrides. Selecting another theme turns the whole interface back to stock Hestia. |
| Custom scripts — every `*.php` / `*.js` in `web/js/custom_scripts/` is included in `<head>` of every page | `web/js/custom_scripts/atlas.php` | A 6-line loader that includes `/usr/local/hestia-atlas/lib/head.php` (outside the web root). It does nothing unless an Atlas theme (`atlas` or `atlas-evergreen`) is selected for the session. |

Additional files the theme owns (not owned by the Hestia package, untouched by upgrades):

| Path | Content |
| --- | --- |
| `$HESTIA/web/atlas/index.php` | The **Tools** page (`/atlas/`). It includes Hestia's `inc/main.php` (authentication, session, CSRF token) and renders through Hestia's `render_page()` so header, footer, scripts and alerts are Hestia's own. |
| `$HESTIA/web/atlas/assets/` | `atlas.js`, Lucide icon sprite, Inter font files, licence notices |
| `/usr/local/hestia-atlas/` | PHP library, a pristine copy of the release (for self-repair), the `hestia-atlas` CLI, state and logs |

## Request flow

```
browser ──► Hestia controller (e.g. web/list/web/index.php)
              │  auth, CSRF, v-list-web-domains …          (unchanged)
              ▼
            render_page() → templates/header.php
              │  includes/css.php   → default.min.css + custom/atlas.css
              │  includes/js.php    → Hestia JS, then custom_scripts/atlas.php
              │                         └─ lib/head.php (theme is atlas*?)
              │                              • boot <script>
              │                              • <template id="ha-shell">   sidebar + header
              │                              • <template id="ha-pagehead"> breadcrumb + title
              │                              • #ha-data JSON (tool index, labels)
              ▼
            templates/includes/panel.php (Hestia's header/menu, kept in the DOM)
            templates/pages/list_web.php  (Hestia's page markup, unchanged)
```

The boot script registers a `MutationObserver` while the document is still being parsed:

1. when `<body>` appears, the shell template is inserted as its first child;
2. Hestia's top bar (`.app-header .top-bar`) is detached *before* Alpine.js starts, so its
   notification component is not initialised twice (the Atlas header uses the same
   `x-data="notifications"` Alpine component and endpoints);
3. when `<main class="app-content">` appears, the breadcrumb/page heading is inserted.

Everything happens before first paint, so there is no flash of the stock interface. Hestia's
main menu stays in the DOM (hidden) because its keyboard shortcuts (`1`–`6`, arrows) use it.

`atlas.js` then performs small, reversible enhancements: it moves the form's existing
**Save** button (same element, same `form="main-form"` target) into an action bar under the
form, labels the primary row action, marks destructive actions, wires the header search and
the sidebar. If the script fails, Hestia's markup and behaviour still work.

## Navigation model

`lib/nav.php` maps Hestia features onto task-oriented sections:

| Section | Hestia pages |
| --- | --- |
| Administration (admins) | Users, Add User, Packages, IP Addresses |
| Server (admins) | Services, Server Settings, Updates, Server Information, Resource Graphs, System Log |
| Email | Mail Domains, Email Accounts, Add Mail Domain |
| Files | File Manager, Backups, Backup Exclusions, Incremental Backups |
| Databases | Databases, Add Database, phpMyAdmin / phpPgAdmin |
| Domains | Web Domains, Add Web Domain, DNS Zones, Add DNS Domain |
| Metrics | Statistics, Activity Log, Login History, Resource Graphs |
| Security | SSH Keys, Access Keys, Two-Factor Authentication, Firewall, Banned IPs, IP Lists |
| Advanced | Cron Jobs, Web Terminal, Notifications |
| Preferences | Account Settings, Password & 2FA, Language, Contact Details |

Visibility of every entry reproduces the conditions in Hestia's `templates/includes/panel.php`
(package limits, installed services, impersonation, `POLICY_*` settings). The menu is only
navigation: the target pages enforce permissions themselves, exactly as before.

Route metadata (`ha_routes()`) provides breadcrumbs, titles and descriptions for every
Hestia list/add/edit page; unknown pages simply get no heading and render as Hestia does.

## Design system

`src/css/tokens.css` defines all colours (see [design-system.md](design-system.md)), type sizes, spacing, radii, shadows, layout sizes,
breakpoints and status colours. Components are mapped onto Hestia's existing class names
(`.button`, `.units-table`, `.form-control`, `.alert`, `.inline-alert`, `.modal`, `.tabs`,
`.collapse`, …) so every Hestia page — including ones not individually reviewed — inherits
the Atlas look. `schemes/evergreen.css` only redefines colour tokens; the
`atlas-evergreen` theme is `atlas.css` plus that file.

| File | Scope |
| --- | --- |
| `tokens.css` | design tokens and Hestia variable mapping |
| `base.css` | typography, links, focus states, icons |
| `shell.css` | sidebar, icon rail, drawer, header, search, dropdowns, page heading |
| `components.css` | buttons, badges, alerts, cards, meters, dialogs, tabs, collapses |
| `lists.css` | toolbars and resource tables (desktop table, mobile cards) |
| `forms.css` | inputs, selects, checkboxes, form sections, action bar |
| `dashboard.css` | Tools page, Account Overview / Server Status / Resource Usage |
| `schemes/evergreen.css` | Evergreen colour tokens |
| `pages.css` | statistics, server info, logs |
| `login.css` | login, password reset and 2FA pages |

## Update safety

See [updates.md](updates.md).
