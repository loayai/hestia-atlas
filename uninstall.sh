#!/bin/bash
# Remove the Hestia Atlas theme and restore the original HestiaCP interface.
#
# Usage: sudo ./uninstall.sh [--dry-run] [--keep-state]
#
#   --dry-run      show what would change
#   --keep-state   keep /usr/local/hestia-atlas/state (logs and backups)
#
# Restores the system theme that was active before installation, clears per-user
# "atlas" selections, removes every file the installer added (and only those),
# restores any pre-existing files it backed up and removes the update-hook block.
set -euo pipefail

export HA_DRY_RUN=0
KEEP_STATE=0
while [ $# -gt 0 ]; do
	case "$1" in
		--dry-run) HA_DRY_RUN=1 ;;
		--keep-state) KEEP_STATE=1 ;;
		-h | --help) sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) echo "unknown option: $1" >&2; exit 2 ;;
	esac
	shift
done

HA_HOME=/usr/local/hestia-atlas
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$HA_HOME/bin/common.sh" ]; then
	. "$HA_HOME/bin/common.sh"
elif [ -f "$SRC_DIR/dist/bin/common.sh" ]; then
	. "$SRC_DIR/dist/bin/common.sh"
else
	echo "Hestia Atlas does not appear to be installed" >&2
	exit 1
fi

ha_require_root
ha_detect_hestia
ha_info "removing Hestia Atlas from HestiaCP $HESTIA_VERSION"

# 1. Themes: restore the previous system theme and clear per-user selections.
if ha_is_own_theme "$(ha_system_theme)"; then
	prev=$(cat "$HA_STATE/previous-theme" 2>/dev/null || true)
	if [ -z "$prev" ] || ha_is_own_theme "$prev" || { [ ! -f "$HESTIA/web/css/themes/$prev.min.css" ] && [ ! -f "$HESTIA/web/css/themes/custom/$prev.css" ]; }; then
		prev=default
	fi
	ha_set_system_theme "$prev"
fi
for u in $(ha_theme_users); do
	ha_reset_user_theme "$u"
done

# 2. Hook block.
ha_hook_remove

# 3. Installed files (only those recorded by the installer).
if [ -f "$HA_STATE/installed-files" ]; then
	while read -r target; do
		[ -n "$target" ] || continue
		case "$target" in "$HESTIA"/web/* | "$HA_HOME"/*) ;; *) continue ;; esac
		if [ -f "$target" ]; then
			ha_run rm -f "$target"
			ha_change "removed $target"
		fi
	done < "$HA_STATE/installed-files"
fi
# Empty directories we created.
for d in "$HESTIA/web/atlas/assets/fonts" "$HESTIA/web/atlas/assets" "$HESTIA/web/atlas" "$HESTIA/web/css/themes/custom"; do
	if [ -d "$d" ] && [ -z "$(ls -A "$d")" ]; then
		ha_run rmdir "$d"
		ha_change "removed empty directory $d"
	fi
done

# 4. Restore files that existed before the first installation.
if [ -d "$HA_STATE/backups" ]; then
	first=$(ls -1 "$HA_STATE/backups" | sort | head -n1)
	if [ -n "$first" ]; then
		while IFS= read -r -d '' f; do
			orig="${f#"$HA_STATE/backups/$first"}"
			if [ ! -e "$orig" ]; then
				ha_run install -D -m 0644 "$f" "$orig"
				ha_change "restored original $orig"
			fi
		done < <(find "$HA_STATE/backups/$first" -type f -print0)
	fi
fi

# 5. Our own directory.
ha_run rm -f /usr/local/bin/hestia-atlas
if [ "$HA_DRY_RUN" != 1 ]; then
	if [ "$KEEP_STATE" = 1 ]; then
		find "$HA_HOME" -mindepth 1 -maxdepth 1 ! -name state ! -name hestia-atlas.log -exec rm -rf {} +
		ha_info "kept $HA_STATE"
	else
		HA_CHANGES+=("removed $HA_HOME")
		rm -rf "$HA_HOME"
	fi
fi

ha_print_changes
ha_info "HestiaCP's original interface is restored"
