#!/bin/bash
# Hestia Atlas theme — shared shell functions for install.sh, uninstall.sh and the
# hestia-atlas CLI. Sourced, never executed directly.

HA_HOME="${HA_HOME:-/usr/local/hestia-atlas}"
HA_STATE="$HA_HOME/state"
HA_LOG="$HA_HOME/hestia-atlas.log"
HA_THEME="atlas"
# All theme ids installed by this package (default scheme first).
HA_THEMES="atlas atlas-evergreen"
HA_HOOK="/etc/hestiacp/hooks/post_install.sh"
HA_HOOK_BEGIN="# >>> hestia-atlas >>>"
HA_HOOK_END="# <<< hestia-atlas <<<"
# Hestia releases this theme was developed and tested against.
HA_TESTED_VERSIONS="1.10.5"
HA_SUPPORTED_SERIES="1.10"

HA_DRY_RUN="${HA_DRY_RUN:-0}"
HA_CHANGES=()

ha_color() { [ -t 1 ] && printf '\033[%sm' "$1" || true; }
ha_info() { printf '%s[hestia-atlas]%s %s\n' "$(ha_color '1;34')" "$(ha_color 0)" "$*"; }
ha_warn() { printf '%s[hestia-atlas] warning:%s %s\n' "$(ha_color '1;33')" "$(ha_color 0)" "$*" >&2; }
ha_die() {
	printf '%s[hestia-atlas] error:%s %s\n' "$(ha_color '1;31')" "$(ha_color 0)" "$*" >&2
	exit 1
}

ha_log() {
	[ "$HA_DRY_RUN" = 1 ] && return 0
	mkdir -p "$HA_HOME"
	printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >> "$HA_LOG"
}

# Record (and in dry-run mode, only print) a change.
ha_change() {
	HA_CHANGES+=("$*")
	ha_log "$*"
}

ha_run() {
	if [ "$HA_DRY_RUN" = 1 ]; then
		printf '  (dry-run) %s\n' "$*"
		return 0
	fi
	"$@"
}

ha_require_root() {
	[ "$(id -u)" -eq 0 ] || ha_die "must be run as root"
}

# Locate Hestia and export HESTIA / HESTIA_VERSION.
ha_detect_hestia() {
	if [ -z "${HESTIA:-}" ] && [ -f /etc/hestiacp/hestia.conf ]; then
		HESTIA=$(grep -oP "^export HESTIA='\K[^']+" /etc/hestiacp/hestia.conf 2>/dev/null)
	fi
	HESTIA="${HESTIA:-/usr/local/hestia}"
	[ -x "$HESTIA/bin/v-list-sys-config" ] || ha_die "HestiaCP was not found (looked in $HESTIA)"
	[ -f "$HESTIA/conf/hestia.conf" ] || ha_die "HestiaCP configuration not found ($HESTIA/conf/hestia.conf)"
	HESTIA_VERSION=$(grep -oP "^VERSION='\K[^']+" "$HESTIA/conf/hestia.conf")
	[ -n "$HESTIA_VERSION" ] || ha_die "could not determine the installed HestiaCP version"
	export HESTIA HESTIA_VERSION
}

ha_conf_value() { # key → value from hestia.conf
	grep -oP "^$1='\K[^']*" "$HESTIA/conf/hestia.conf" 2>/dev/null
}

# Structural checks against the upstream files the theme relies on.
# Prints one line per failed check; returns non-zero if any fail.
ha_compat_problems() {
	local w="$HESTIA/web" fail=0
	check() { # file pattern description
		if [ ! -f "$1" ] || ! grep -qF -- "$2" "$1"; then
			echo "  - $3 (${1#"$HESTIA"/}: expected \"$2\")"
			fail=1
		fi
	}
	check "$w/templates/includes/js.php" 'custom_scripts' "custom script loader"
	check "$w/templates/includes/js.php" 'require_once $customScript->getPathname()' "PHP custom script include"
	check "$w/templates/includes/css.php" '/css/themes/custom/' "custom theme stylesheet loader"
	check "$w/templates/header.php" 'includes/js.php' "header includes js.php"
	check "$w/templates/header.php" '<div class="app">' "application wrapper"
	check "$w/templates/includes/panel.php" 'class="app-header"' "panel header element"
	check "$w/templates/includes/panel.php" 'class="top-bar"' "panel top bar element"
	check "$w/templates/includes/panel.php" '<main class="app-content">' "main content element"
	check "$w/templates/includes/panel.php" 'x-data="notifications"' "notifications component"
	check "$w/inc/main.php" 'function render_page(' "render_page() helper"
	check "$w/inc/main.php" '"pages/" . $page . ".php"' "render_page() template include"
	check "$w/inc/main.php" 'function get_user_data(' "get_user_data() helper"
	if [ -f "$w/js/dist/main.min.js" ] && ! grep -q 'notifications' "$w/js/dist/main.min.js"; then
		echo "  - Alpine notifications component missing from js/dist/main.min.js"
		fail=1
	fi
	return $fail
}

# 0 = compatible (tested or same series with passing checks), 1 = incompatible.
ha_check_compat() {
	local quiet="${1:-}" problems series="${HESTIA_VERSION%.*}"
	problems=$(ha_compat_problems || true)
	if [ -n "$problems" ]; then
		[ -z "$quiet" ] && ha_warn "HestiaCP $HESTIA_VERSION is not compatible with this theme:" && echo "$problems" >&2
		return 1
	fi
	if ! [[ " $HA_TESTED_VERSIONS " == *" $HESTIA_VERSION "* ]]; then
		if [ "$series" != "$HA_SUPPORTED_SERIES" ]; then
			[ -z "$quiet" ] && ha_warn "HestiaCP $HESTIA_VERSION is outside the supported $HA_SUPPORTED_SERIES.x series; structural checks passed"
		else
			[ -z "$quiet" ] && ha_info "HestiaCP $HESTIA_VERSION has not been explicitly tested (tested: $HA_TESTED_VERSIONS); structural checks passed"
		fi
	fi
	return 0
}

# Map a dist-relative path to its installed location.
ha_target() {
	case "$1" in
		lib/*) echo "$HA_HOME/$1" ;;
		bin/*) echo "$HA_HOME/$1" ;;
		web/*) echo "$HESTIA/$1" ;;
		*) return 1 ;;
	esac
}

# Deploy every file listed in <dist>/MANIFEST. Existing files that do not belong to this
# theme are backed up first. Files from a previous install that are no longer shipped are removed.
ha_deploy() {
	local dist="$1" ts rel target sum backup_dir new_list old_list
	ts=$(date +%Y%m%d-%H%M%S)
	backup_dir="$HA_STATE/backups/$ts"
	new_list=$(mktemp)
	old_list="$HA_STATE/installed-files"
	while read -r sum rel; do
		[ -n "$rel" ] || continue
		target=$(ha_target "$rel") || continue
		echo "$target" >> "$new_list"
		if [ -f "$target" ] && echo "$sum  $target" | sha256sum -c --status - 2>/dev/null; then
			continue # already up to date
		fi
		if [ -f "$target" ] && ! grep -qxF "$target" "$old_list" 2>/dev/null; then
			ha_run mkdir -p "$backup_dir$(dirname "$target")"
			ha_run cp -a "$target" "$backup_dir$target"
			ha_change "backed up existing $target → $backup_dir$target"
		fi
		ha_run install -D -m 0644 -o root -g root "$dist/$rel" "$target"
		ha_change "installed $target"
	done < "$dist/MANIFEST"
	# Executables
	if [ -d "$HA_HOME/bin" ] && [ "$HA_DRY_RUN" != 1 ]; then
		chmod 0755 "$HA_HOME/bin/hestia-atlas" 2>/dev/null || true
	fi
	# Stale files from an older version
	if [ -f "$old_list" ]; then
		while read -r target; do
			[ -n "$target" ] || continue
			if ! grep -qxF "$target" "$new_list" && [ -f "$target" ]; then
				ha_run rm -f "$target"
				ha_change "removed obsolete $target"
			fi
		done < "$old_list"
	fi
	if [ "$HA_DRY_RUN" != 1 ]; then
		mkdir -p "$HA_STATE"
		sort -u "$new_list" > "$old_list"
	fi
	rm -f "$new_list"
}

# Verify installed files against the manifest. Prints problems; non-zero if any.
ha_verify() {
	local dist="$1" sum rel target bad=0
	while read -r sum rel; do
		[ -n "$rel" ] || continue
		target=$(ha_target "$rel") || continue
		if [ ! -f "$target" ]; then
			echo "  missing: $target"
			bad=1
		elif ! echo "$sum  $target" | sha256sum -c --status - 2>/dev/null; then
			echo "  modified: $target"
			bad=1
		fi
	done < "$dist/MANIFEST"
	return $bad
}

ha_system_theme() { ha_conf_value THEME; }

ha_set_system_theme() {
	local theme="$1" current
	current=$(ha_system_theme)
	[ "$current" = "$theme" ] && return 0
	ha_run "$HESTIA/bin/v-change-sys-config-value" THEME "$theme"
	ha_change "system theme: '${current}' → '${theme}'"
}

# Add our block at the top of the post-install hook, preserving any existing logic.
ha_hook_install() {
	local tmp
	if [ -f "$HA_HOOK" ] && grep -qF "$HA_HOOK_BEGIN" "$HA_HOOK"; then
		return 0
	fi
	if [ "$HA_DRY_RUN" = 1 ]; then
		ha_change "would add update hook block to $HA_HOOK"
		return 0
	fi
	mkdir -p "$(dirname "$HA_HOOK")" "$HA_STATE"
	tmp=$(mktemp)
	if [ -f "$HA_HOOK" ]; then
		cp -a "$HA_HOOK" "$HA_STATE/post_install.sh.orig"
		# Keep the original shebang first, then our block, then the original body.
		if head -n1 "$HA_HOOK" | grep -q '^#!'; then
			head -n1 "$HA_HOOK" > "$tmp"
			ha_hook_block >> "$tmp"
			tail -n +2 "$HA_HOOK" >> "$tmp"
		else
			echo '#!/bin/bash' > "$tmp"
			ha_hook_block >> "$tmp"
			cat "$HA_HOOK" >> "$tmp"
		fi
		ha_change "added update hook block to existing $HA_HOOK (original saved in $HA_STATE/post_install.sh.orig)"
	else
		{
			echo '#!/bin/bash'
			ha_hook_block
		} > "$tmp"
		touch "$HA_STATE/hook-created"
		ha_change "created $HA_HOOK"
	fi
	cat "$tmp" > "$HA_HOOK"
	rm -f "$tmp"
	chmod 0755 "$HA_HOOK"
}

ha_hook_block() {
	cat << EOF
$HA_HOOK_BEGIN
# Re-validates the Hestia Atlas theme after HestiaCP upgrades. Never patches Hestia files;
# disables the theme (falls back to the previous theme) if upstream became incompatible.
if [ -x "$HA_HOME/bin/hestia-atlas" ]; then
	"$HA_HOME/bin/hestia-atlas" repair --hook || true
fi
$HA_HOOK_END
EOF
}

ha_hook_remove() {
	[ -f "$HA_HOOK" ] || return 0
	grep -qF "$HA_HOOK_BEGIN" "$HA_HOOK" || return 0
	if [ "$HA_DRY_RUN" = 1 ]; then
		ha_change "would remove update hook block from $HA_HOOK"
		return 0
	fi
	sed -i "/^$(printf '%s' "$HA_HOOK_BEGIN" | sed 's/[]\/$*.^[]/\\&/g')\$/,/^$(printf '%s' "$HA_HOOK_END" | sed 's/[]\/$*.^[]/\\&/g')\$/d" "$HA_HOOK"
	if [ -f "$HA_STATE/hook-created" ] && [ -z "$(grep -v '^#!' "$HA_HOOK" | grep -v '^[[:space:]]*$')" ]; then
		rm -f "$HA_HOOK"
		ha_change "removed $HA_HOOK (it was created by this theme)"
	else
		ha_change "removed update hook block from $HA_HOOK"
	fi
}

ha_notify_admin() { # title message
	local admin
	admin=$(ha_conf_value ROOT_USER)
	"$HESTIA/bin/v-add-user-notification" "${admin:-admin}" "$1" "$2" > /dev/null 2>&1 || true
}

# Users who selected the theme individually.
ha_theme_users() {
	grep -lE "^THEME='($(echo "$HA_THEMES" | tr ' ' '|'))'$" "$HESTIA"/data/users/*/user.conf 2>/dev/null | awk -F/ '{print $(NF-1)}'
}

ha_reset_user_theme() { # user — clear the per-user theme so the system theme applies
	local user="$1"
	if [ "$HA_DRY_RUN" = 1 ]; then
		ha_change "would reset theme of user $user"
		return 0
	fi
	sed -i -E "s/^THEME='($(echo "$HA_THEMES" | tr ' ' '|'))'$/THEME=''/" "$HESTIA/data/users/$user/user.conf"
	ha_change "reset theme of user $user to the system default"
}

# 0 if the given theme id belongs to this package.
ha_is_own_theme() {
	[[ " $HA_THEMES " == *" $1 "* ]]
}

# Theme id for a colour scheme name (default | evergreen).
ha_scheme_theme() {
	case "${1:-default}" in
		default) echo "atlas" ;;
		evergreen) echo "atlas-evergreen" ;;
		*) return 1 ;;
	esac
}

ha_print_changes() {
	if [ ${#HA_CHANGES[@]} -eq 0 ]; then
		ha_info "no changes were necessary"
		return
	fi
	ha_info "changes:"
	printf '  • %s\n' "${HA_CHANGES[@]}"
}
