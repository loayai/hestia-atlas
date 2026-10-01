#!/bin/bash
# Hestia Atlas Lite — install the CSS-only theme.
#
# Usage: sudo ./install.sh [--activate] [--scheme default|evergreen]
#
# Copies two stylesheets — atlas-lite.css (blue) and atlas-lite-evergreen.css (green) —
# into Hestia's custom theme directory ($HESTIA/web/css/themes/custom/). No PHP, no
# JavaScript, no hooks, no other changes. With --activate it also makes the chosen scheme
# the system theme (the previous theme is remembered for uninstall.sh). Without it, pick
# the theme in Server Settings or per user in Account Settings.
set -euo pipefail

HESTIA="${HESTIA:-/usr/local/hestia}"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CUSTOM="$HESTIA/web/css/themes/custom"
STATE=/var/lib/hestia-atlas-lite
ACTIVATE=0
SCHEME=default

while [ $# -gt 0 ]; do
	case "$1" in
		--activate) ACTIVATE=1 ;;
		--scheme) SCHEME="${2:-}"; shift ;;
		*) echo "unknown option: $1" >&2; exit 2 ;;
	esac
	shift
done
case "$SCHEME" in
	default) THEME=atlas-lite ;;
	evergreen) THEME=atlas-lite-evergreen ;;
	*) echo "error: --scheme must be default or evergreen" >&2; exit 2 ;;
esac

[ "$(id -u)" -eq 0 ] || { echo "error: run as root" >&2; exit 1; }
[ -x "$HESTIA/bin/v-change-sys-config-value" ] || { echo "error: HestiaCP not found in $HESTIA" >&2; exit 1; }
grep -q "themes/custom/" "$HESTIA/web/templates/includes/css.php" ||
	{ echo "error: this HestiaCP version does not load custom themes" >&2; exit 1; }
(cd "$DIR" && sha256sum --check --quiet SHA256SUMS) || { echo "error: checksum mismatch" >&2; exit 1; }

for f in atlas-lite.css atlas-lite-evergreen.css; do
	install -D -m 0644 -o root -g root "$DIR/$f" "$CUSTOM/$f"
	echo "installed $CUSTOM/$f"
done

if [ "$ACTIVATE" = 1 ]; then
	current=$(grep -oP "^THEME='\K[^']*" "$HESTIA/conf/hestia.conf" || true)
	if [ "$current" != "$THEME" ]; then
		mkdir -p "$STATE"
		case "$current" in atlas-lite | atlas-lite-evergreen) ;; *) echo "$current" > "$STATE/previous-theme" ;; esac
		"$HESTIA/bin/v-change-sys-config-value" THEME "$THEME"
		echo "system theme: '$current' → '$THEME'"
	fi
else
	echo "select 'atlas-lite' or 'atlas-lite-evergreen' in Server Settings → Theme, or run: $0 --activate"
fi
