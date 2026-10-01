#!/bin/bash
# Install (or upgrade) the Hestia Atlas theme on this HestiaCP server.
#
# Usage: sudo ./install.sh [--dry-run] [--no-activate] [--force] [--landing tools|hestia]
#
#   --dry-run       show what would change without changing anything
#   --no-activate   install files but keep the current system theme
#   --force         install even if the compatibility checks fail (not recommended)
#   --scheme        colour scheme to activate: default (blue) or evergreen (green)
#   --landing       page shown after login: the Atlas "Tools" page (default) or Hestia's list
#
# The installer never patches HestiaCP files. It adds:
#   /usr/local/hestia-atlas/                    theme library, pristine copy, state, CLI
#   $HESTIA/web/css/themes/custom/atlas.css     theme stylesheet (Hestia custom theme)
#   $HESTIA/web/js/custom_scripts/atlas.php     loader (Hestia custom script mechanism)
#   $HESTIA/web/atlas/                          Tools/dashboard page and static assets
#   a marked block in /etc/hestiacp/hooks/post_install.sh (existing content preserved)
set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_SRC="$SRC_DIR/dist"
ACTIVATE=1
FORCE=0
LANDING=""
SCHEME=default
SCHEME_SET=0
export HA_DRY_RUN=0

while [ $# -gt 0 ]; do
	case "$1" in
		--dry-run) HA_DRY_RUN=1 ;;
		--no-activate) ACTIVATE=0 ;;
		--force) FORCE=1 ;;
		--landing) LANDING="${2:-}"; shift ;;
		--scheme) SCHEME="${2:-}"; SCHEME_SET=1; shift ;;
		-h | --help) sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) echo "unknown option: $1" >&2; exit 2 ;;
	esac
	shift
done

[ -f "$DIST_SRC/MANIFEST" ] || { echo "dist/ is missing — run 'npm ci && npm run build' first" >&2; exit 1; }
# shellcheck source=src/sh/common.sh
. "$DIST_SRC/bin/common.sh"

ha_require_root
ha_detect_hestia
VERSION=$(cat "$DIST_SRC/VERSION")
ha_info "Hestia Atlas theme $VERSION → HestiaCP $HESTIA_VERSION at $HESTIA"
[ "$HA_DRY_RUN" = 1 ] && ha_info "dry run: nothing will be changed"

case "$LANDING" in "" | tools | hestia) ;; *) ha_die "--landing must be 'tools' or 'hestia'" ;; esac
THEME=$(ha_scheme_theme "$SCHEME") || ha_die "--scheme must be 'default' or 'evergreen'"

if ! ha_check_compat; then
	[ "$FORCE" = 1 ] || ha_die "compatibility checks failed; nothing was changed (use --force to override)"
	ha_warn "continuing because --force was given"
fi

UPGRADE=0
[ -f "$HA_HOME/dist/VERSION" ] && UPGRADE=1 && ha_info "upgrading from $(cat "$HA_HOME/dist/VERSION")"

# 1. Keep a pristine copy of the release for self-repair after Hestia upgrades.
if [ "$HA_DRY_RUN" != 1 ]; then
	mkdir -p "$HA_HOME" "$HA_STATE"
	chmod 0755 "$HA_HOME"
	chmod 0700 "$HA_STATE"
	rm -rf "$HA_HOME/dist.new"
	cp -a "$DIST_SRC" "$HA_HOME/dist.new"
	chown -R root:root "$HA_HOME/dist.new"
	rm -rf "$HA_HOME/dist"
	mv "$HA_HOME/dist.new" "$HA_HOME/dist"
	ha_change "stored release $VERSION in $HA_HOME/dist"
fi
DIST="$HA_HOME/dist"
[ "$HA_DRY_RUN" = 1 ] && DIST="$DIST_SRC"

# 2. Theme files.
ha_deploy "$DIST"
if [ "$HA_DRY_RUN" != 1 ]; then
	chmod 0755 "$HA_HOME/bin/hestia-atlas"
	ln -sf "$HA_HOME/bin/hestia-atlas" /usr/local/bin/hestia-atlas
fi

# 3. Settings file (kept across upgrades).
if [ ! -f "$HA_HOME/atlas.conf" ] || [ -n "$LANDING" ]; then
	if [ "$HA_DRY_RUN" != 1 ]; then
		cat > "$HA_HOME/atlas.conf" << EOF
# Hestia Atlas theme settings
# LANDING: page after login — 'tools' (Atlas Tools page) or 'hestia' (Hestia default list)
LANDING='${LANDING:-tools}'
EOF
		chmod 0644 "$HA_HOME/atlas.conf"
	fi
	ha_change "wrote $HA_HOME/atlas.conf (LANDING=${LANDING:-tools})"
fi

# 4. Update hook (chained, existing logic preserved).
ha_hook_install

# 5. Activate.
if [ "$ACTIVATE" = 1 ]; then
	current=$(ha_system_theme)
	# Keep the selected Atlas scheme on re-install unless --scheme was given explicitly.
	if ha_is_own_theme "$current" && [ "$SCHEME_SET" = 0 ]; then
		THEME="$current"
	fi
	if [ "$current" != "$THEME" ]; then
		[ "$HA_DRY_RUN" != 1 ] && ! ha_is_own_theme "$current" && echo "$current" > "$HA_STATE/previous-theme"
		ha_set_system_theme "$THEME"
	fi
fi

if [ "$HA_DRY_RUN" != 1 ]; then
	echo "$HESTIA_VERSION" > "$HA_STATE/installed-on-hestia"
	problems=$(ha_verify "$DIST") || ha_die "post-install verification failed:
$problems"
fi

ha_print_changes
ha_info "done. $( [ "$UPGRADE" = 1 ] && echo upgraded || echo installed ) Hestia Atlas $VERSION"
[ "$ACTIVATE" = 1 ] && ha_info "the '$THEME' theme is active for all users who have not chosen their own theme"
ha_info "manage it with: hestia-atlas status | repair | enable | disable"
