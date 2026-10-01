#!/bin/bash
# Hestia Atlas Lite — remove the CSS-only theme.
#
# Usage: sudo ./uninstall.sh
#
# Restores the previous system theme if an Atlas Lite scheme is selected, clears per-user
# Atlas Lite selections (so those users follow the system theme again) and deletes the
# stylesheets.
set -euo pipefail

HESTIA="${HESTIA:-/usr/local/hestia}"
CUSTOM="$HESTIA/web/css/themes/custom"
STATE=/var/lib/hestia-atlas-lite
OWN='atlas-lite|atlas-lite-evergreen'

[ "$(id -u)" -eq 0 ] || { echo "error: run as root" >&2; exit 1; }

current=$(grep -oP "^THEME='\K[^']*" "$HESTIA/conf/hestia.conf" || true)
if [[ "$current" =~ ^($OWN)$ ]]; then
	prev=$(cat "$STATE/previous-theme" 2> /dev/null || true)
	if [ -z "$prev" ] || [[ "$prev" =~ ^($OWN)$ ]] ||
		{ [ ! -f "$HESTIA/web/css/themes/$prev.min.css" ] && [ ! -f "$CUSTOM/$prev.css" ]; }; then
		prev=default
	fi
	"$HESTIA/bin/v-change-sys-config-value" THEME "$prev"
	echo "system theme: '$current' → '$prev'"
fi

for conf in "$HESTIA"/data/users/*/user.conf; do
	if grep -qE "^THEME='($OWN)'$" "$conf"; then
		sed -i -E "s/^THEME='($OWN)'$/THEME=''/" "$conf"
		echo "reset theme of user $(basename "$(dirname "$conf")")"
	fi
done

for f in atlas-lite.css atlas-lite-evergreen.css; do
	rm -f "$CUSTOM/$f"
	echo "removed $CUSTOM/$f"
done
# Hestia does not ship the custom directory; remove it again if this left it empty.
[ -d "$CUSTOM" ] && [ -z "$(ls -A "$CUSTOM")" ] && rmdir "$CUSTOM" && echo "removed empty $CUSTOM"
rm -rf "$STATE"
