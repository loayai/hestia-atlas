# HestiaCP updates

HestiaCP updates itself through `apt` (automatic updates are on by default). The theme is
designed so that a normal Hestia upgrade never needs manual work.

## Why upgrades do not remove the theme

* No file owned by the `hestia` Debian package is modified, so `dpkg` never overwrites or
  conflicts with the theme.
* All theme files live in locations Hestia reserves for customisation
  (`web/css/themes/custom/`, `web/js/custom_scripts/`) or in paths Hestia does not ship
  (`web/atlas/`, `/usr/local/hestia-atlas/`). `dpkg` leaves unowned files in place.
* The selected theme is stored in `hestia.conf` / `user.conf`, which upgrades preserve.

## The post-install hook

Hestia's package `postinst` runs `/etc/hestiacp/hooks/post_install.sh` after every upgrade.
The installer adds a clearly marked block **at the top** of that file (after the shebang),
so existing hook logic is preserved and still runs, even if it ends with `exit`:

```bash
# >>> hestia-atlas >>>
# Re-validates the Hestia Atlas theme after HestiaCP upgrades …
if [ -x "/usr/local/hestia-atlas/bin/hestia-atlas" ]; then
	"/usr/local/hestia-atlas/bin/hestia-atlas" repair --hook || true
fi
# <<< hestia-atlas <<<
```

If the hook file did not exist, the installer creates it (and the uninstaller deletes it
again if nothing else was added). If it existed, the original is saved to
`/usr/local/hestia-atlas/state/post_install.sh.orig` and only the marked block is removed
on uninstall.

## What `hestia-atlas repair` does

1. **Compatibility check** — verifies the structural anchors the theme relies on in the
   *new* Hestia release (custom script loader and `require_once` in `includes/js.php`,
   custom theme loader in `includes/css.php`, `.app`, `.app-header`, `.top-bar`,
   `<main class="app-content">` and the notifications component in the templates,
   `render_page()` / `get_user_data()` in `inc/main.php`, the notifications component in the
   built JS bundle).
2. **Compatible** → re-installs any missing or modified theme file from the pristine copy in
   `/usr/local/hestia-atlas/dist` (verified by SHA-256 against `MANIFEST`).
3. **Incompatible** → never patches or "fixes" Hestia. It switches the system theme back to
   the theme that was active before installation, clears per-user Atlas selections,
   writes the reason to `/usr/local/hestia-atlas/hestia-atlas.log` and posts a panel
   notification to the administrator. Hestia keeps working with its stock interface until a
   compatible theme release is installed.

Untested Hestia versions that pass every structural check stay enabled; `hestia-atlas
status` and the install output say that the version is untested.

## Manual commands

```bash
hestia-atlas status    # versions, selected themes, compatibility, file integrity, hook
hestia-atlas check     # compatibility only (exit code 0 = compatible)
hestia-atlas repair    # what the hook runs
hestia-atlas disable   # restore the previous theme for everyone
hestia-atlas enable [default|evergreen]   # select an Atlas scheme system-wide
```

## Tested

* HestiaCP 1.10.5 on Debian 12 — install, upgrade-in-place (`apt-get install --reinstall
  hestia`, which runs the package `postinst` and the hook), uninstall, and a simulated
  incompatible upstream template (theme disabled automatically, stock UI restored).
