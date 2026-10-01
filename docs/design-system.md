# Hestia Atlas design system

All visual values live in [`src/css/tokens.css`](../src/css/tokens.css) (default scheme)
and [`src/css/schemes/evergreen.css`](../src/css/schemes/evergreen.css) (optional green
scheme). Components only reference tokens; no colour is hard-coded outside these files
(white `#fff` excepted).

## Provenance

Hestia Atlas's *information architecture* — a left navigation column, a home page of
grouped tools, an account/usage column, list pages with a toolbar above a table — is
inspired by the general layout of the cPanel® Jupiter interface. Its *visual values* are
original:

* **Colour** — HSL scales generated from HestiaCP's own logo colours (blue `#2271b8`,
  magenta `#e72273`) and a cool slate neutral (hue 215°). The Evergreen scheme is generated
  from a single navigation green, `#60a96d` (HSL 131° 30% 52%).
* **Typography** — [Inter](https://rsms.me/inter/) (SIL OFL 1.1), 14px base, medium-weight
  headings.
* **Spacing and sizes** — a 4px grid; layout sizes chosen for this project.
* **Icons** — [Lucide](https://lucide.dev) (ISC), drawn on tinted tiles.
* **Components** — written from scratch against Hestia's own markup.

No CSS, JavaScript, markup, font, icon or image from cPanel was used. During early
prototyping some colours and sizes were measured from public cPanel documentation
screenshots to study proportions; every one of those values was replaced by the original
system below (see the audit table).

## Colour scales (default scheme)

| Scale | Source | 50 | 100 | 300 | 500 | 600 | 700 | 800 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| brand | Hestia logo blue, hsl(208 69% 43%) | `#edf5fc` | `#d8e9f9` | `#8bbbe4` | `#2885d7` | `#2273b9` | `#1c609b` | `#174d7d` |
| accent | Hestia logo magenta, hsl(336 81% 52%) | `#fdf1f6` | `#fbdbe7` | — | `#e82171` | — | `#ad1f58` | — |
| ink | hue 216 (navigation) | | | | | `#3e506a` | `#2a3b55` | `#1b2a41` (900 `#121e30`) |
| slate | hue 215 neutrals | `#f4f6f9` | `#e9ecf1` | `#b2bbc7` | `#647082` | `#4e5c6e` | | `#202c3c` (900 `#121d2b`) |

Status hues: green `#20794d`, amber `#f59f0a` / text `#914c08`, red `#c62a2a`, each with
50/300 tints for backgrounds and borders.

Contrast (WCAG 2.x): body text `#202c3c` on `#f4f6f9` 13:1; links `#1c609b` on white 6.6:1;
primary button text on `#2273b9` 5.0:1; sidebar labels `#dde3ec` on `#1b2a41` 11:1.

## Evergreen scheme

| Token | Value | Notes |
| --- | --- | --- |
| `--ha-sidebar` | `#60a96d` | requested navigation green |
| sidebar labels | `#fff`, semibold, faint shadow | forum-header style; white on `#60a96d` is 2.8:1, so labels are bold and states darken the green |
| `--ha-sidebar-deep` | `#4f925b` | rail flyouts/tooltips (white 3.8:1) |
| brand 50 / 100 | `#eafaec` / `#cff2d2` | mint tints for soft surfaces |
| brand 600 | `#40774a` | buttons, 5.3:1 with white text |
| brand 700 | `#36633e` | links |
| accent 500 | `#60a96d` | the navigation green |
| neutrals | `#f5f7f6` … `#162218` | light greys with a faint green cast |
| success | `#1c6e5a` | teal-green, distinct from the brand |

Choose it per server (`./install.sh --scheme evergreen`, `hestia-atlas enable evergreen`)
or per user (*Account Settings → Theme → atlas-evergreen*). The Lite edition ships the same
scheme as `atlas-lite-evergreen.css`.

## Typography and layout

| Token | Value |
| --- | --- |
| Font | Inter variable (latin, latin-ext, cyrillic, cyrillic-ext, greek, vietnamese) |
| Sizes | 12 / 13 / 14 (base) / 15 / 16 / 19 / 26 (page title) / 32 (Tools title) |
| Weights | 400 body, 500 headings, 600 labels |
| Sidebar | 256px, icon rail 72px |
| Header | 60px (56px on phones) |
| Content | max 1360px, gutter 28px (16px on phones) |
| Side column | 344px |
| Controls | 38px high (32px compact) |
| Radius | 4 / 6 / 10px, pill search |

## Audit

Every value that was originally measured from cPanel documentation screenshots, and what
replaced it:

| Element | Earlier prototype (measured) | Hestia Atlas |
| --- | --- | --- |
| Navigation background | `#202654` | `#1b2a41` (ink-800, hue 216) |
| Accent (active marker, badges) | `#ff6c2c` orange | `#e82171` Hestia magenta |
| Page background | `#fafafa` | `#f4f6f9` (slate-50) |
| Link colour | `#365aa2` | `#1c609b` (brand-700) |
| Primary button | `#388ace` | `#2273b9` (brand-600) |
| Heading text | `#0a2540` | `#121d2b` (slate-900) |
| Label text | `#586376` | `#4e5c6e` (slate-600) |
| Dividers | `#e4e9f2`, `#dddddd` | `#dde2e9`, `#e9ecf1` |
| Input fill / soft button | `#f7f9fc` / `#e7ecfc` | `#fafbfc` / `#edf5fc` |
| Row hover | `#f1f1f1` | `#f4f6f9` |
| Table header | 2px `#323232` rule | tinted header band, uppercase 12px labels, 1px border |
| Header search | navy 1px box, 4px radius | pill field on `#fafbfc` with `/` key hint |
| Header buttons | navy-outlined circles | soft filled circles |
| Tool icons | black line icon + orange badge | brand glyph on a tinted 10px-radius tile, no badge |
| Typeface | Open Sans | Inter |
| Sidebar / header / side column | 240 / 58 / 330px | 256 / 60 / 344px |
| Radius | 4px | 6px controls, 10px tiles |
| Page title | 28–38px light (300) | 26 / 32px medium (500) |
| Labels copied verbatim ("Search Tools (/)", "General Information", "Statistics", "SSL/TLS Status", "Last Login IP Address", "Password & Security", "Change Language", "Contact Information", "Reset Page Settings") | | "Find a tool or page", "Account Overview", "Resource Usage", "Manage SSL", "Last Sign-in", "Password & 2FA", "Language", "Contact Details", "Reset layout preferences" |

Generic section names describing hosting features (Email, Files, Databases, Domains,
Metrics, Security, Advanced, Preferences, Tools) are kept because they are plain
descriptions of Hestia's functionality.
