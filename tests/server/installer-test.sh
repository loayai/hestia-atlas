#!/bin/bash
# Installer / uninstaller / update-hook test. DEV SERVERS ONLY — it uninstalls and reinstalls
# the theme, edits the Hestia post-install hook and temporarily alters a Hestia template.
#
#   ./tests/server/installer-test.sh [--with-reinstall]
#
# Run from an unpacked release (install.sh, uninstall.sh, dist/). --with-reinstall also runs
# `apt-get install --reinstall hestia`, i.e. a real Hestia package upgrade cycle.
set -uo pipefail
cd "$(dirname "$0")/../.."
HESTIA=/usr/local/hestia
HOOK=/etc/hestiacp/hooks/post_install.sh
PASS=0
FAIL=0

ok() { echo "  ✔ $*"; PASS=$((PASS + 1)); }
bad() { echo "  ✘ $*"; FAIL=$((FAIL + 1)); }
check() { if eval "$2"; then ok "$1"; else bad "$1"; fi; }
theme() { grep -oP "^THEME='\K[^']*" $HESTIA/conf/hestia.conf; }
# Hash of Hestia's web tree excluding the theme's own paths.
web_hash() {
	find $HESTIA/web -type f ! -path "$HESTIA/web/atlas/*" ! -path "$HESTIA/web/css/themes/custom/atlas.css" ! -path "$HESTIA/web/css/themes/custom/atlas-evergreen.css" \
		! -path "$HESTIA/web/js/custom_scripts/atlas.php" -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16
}

echo "== 1. clean slate"
[ -d /usr/local/hestia-atlas ] && ./uninstall.sh > /dev/null
check "theme files absent" "[ ! -e $HESTIA/web/atlas ] && [ ! -e $HESTIA/web/js/custom_scripts/atlas.php ]"
BEFORE_THEME=$(theme)
BEFORE_WEB=$(web_hash)
BEFORE_HOOK=$(sha256sum $HOOK 2> /dev/null | cut -c1-16)

echo "== 2. dry run changes nothing"
./install.sh --dry-run > /tmp/ha-dry.log 2>&1
check "dry run exit 0" "[ $? -eq 0 ]"
check "dry run left no files" "[ ! -e $HESTIA/web/atlas ] && [ ! -d /usr/local/hestia-atlas/dist ]"
check "dry run kept theme" "[ \"$(theme)\" = \"$BEFORE_THEME\" ]"

echo "== 3. install with a pre-existing custom hook"
mkdir -p "$(dirname $HOOK)"
cp -a $HOOK /tmp/ha-hook.bak 2> /dev/null || rm -f /tmp/ha-hook.bak
printf '#!/bin/bash\necho custom-hook-ran > /tmp/ha-custom-hook\nexit 0\n' > $HOOK
chmod +x $HOOK
./install.sh > /tmp/ha-install.log 2>&1
check "install exit 0" "[ $? -eq 0 ]"
check "theme active" "[ \"$(theme)\" = atlas ]"
check "hook block added at top" "sed -n 2p $HOOK | grep -q 'hestia-atlas >>>'"
check "custom hook logic preserved" "grep -q custom-hook-ran $HOOK"
check "hestia web tree unchanged" "[ \"$(web_hash)\" = \"$BEFORE_WEB\" ]"
check "status ok" "hestia-atlas status > /tmp/ha-status.txt; grep -q 'Files: *ok' /tmp/ha-status.txt"
check "dashboard served" "curl -sk -o /dev/null -w '%{http_code}' https://127.0.0.1:8083/atlas/assets/atlas.js | grep -q 200"
check "lib not web-accessible" "! curl -sk https://127.0.0.1:8083/atlas/../../hestia-atlas/lib/head.php | grep -q HA_VERSION"

echo "== 4. second install is idempotent"
./install.sh --no-activate > /tmp/ha-install2.log 2>&1
check "re-install reports no file changes" "! grep -q '• installed' /tmp/ha-install2.log"
check "hook block not duplicated" "[ \$(grep -c 'hestia-atlas >>>' $HOOK) -eq 1 ]"

echo "== 4b. colour schemes"
./install.sh --scheme evergreen > /dev/null 2>&1
check "--scheme evergreen activates atlas-evergreen" "[ \"$(theme)\" = atlas-evergreen ]"
./install.sh > /dev/null 2>&1
check "re-install keeps the selected scheme" "[ \"$(theme)\" = atlas-evergreen ]"
hestia-atlas enable default > /dev/null
check "hestia-atlas enable default" "[ \"$(theme)\" = atlas ]"
$HESTIA/bin/v-change-user-theme jdemo atlas-evergreen > /dev/null
check "per-user scheme listed in status" "hestia-atlas status > /tmp/ha-status2.txt; grep -q 'Per-user theme:   jdemo' /tmp/ha-status2.txt"

echo "== 5. self-repair restores a deleted file"
rm -f $HESTIA/web/atlas/assets/atlas.js
hestia-atlas repair > /dev/null
check "file restored" "[ -f $HESTIA/web/atlas/assets/atlas.js ]"

echo "== 6. hook runs during a Hestia post-install cycle"
rm -f /tmp/ha-custom-hook
bash $HOOK
check "custom hook still runs after ours" "[ -f /tmp/ha-custom-hook ]"
check "repair logged by hook" "tail -n 3 /usr/local/hestia-atlas/hestia-atlas.log | grep -q 'post-install hook'"

echo "== 7. incompatible upstream → theme disabled, nothing patched"
cp -a $HESTIA/web/templates/includes/panel.php /tmp/ha-panel.php
sed -i 's/class="top-bar"/class="top-bar-renamed"/' $HESTIA/web/templates/includes/panel.php
hestia-atlas repair > /tmp/ha-incompat.log 2>&1
check "repair exits non-zero" "[ $? -ne 0 ]"
check "theme switched back" "[ \"$(theme)\" != atlas ]"
check "panel.php left as found (not patched)" "grep -q top-bar-renamed $HESTIA/web/templates/includes/panel.php"
cp -a /tmp/ha-panel.php $HESTIA/web/templates/includes/panel.php
hestia-atlas enable > /dev/null
check "re-enabled after upstream restored" "[ \"$(theme)\" = atlas ]"

if [ "${1:-}" = "--with-reinstall" ]; then
	echo "== 8. real Hestia package reinstall (runs postinst + hooks)"
	DEBIAN_FRONTEND=noninteractive apt-get install -y --reinstall -o Dpkg::Options::=--force-confold hestia > /tmp/ha-apt.log 2>&1
	check "apt reinstall exit 0" "[ $? -eq 0 ]"
	check "theme still active" "[ \"$(theme)\" = atlas ]"
	check "files intact" "hestia-atlas status > /tmp/ha-status.txt; grep -q 'Files: *ok' /tmp/ha-status.txt"
	check "hook ran during postinst" "grep -q 'post-install hook' /usr/local/hestia-atlas/hestia-atlas.log"
fi

echo "== 9. uninstall restores the original state"
./uninstall.sh > /tmp/ha-uninstall.log 2>&1
check "uninstall exit 0" "[ $? -eq 0 ]"
check "per-user scheme reset" "grep -q \"^THEME=''\" $HESTIA/data/users/jdemo/user.conf"
check "theme restored to '$BEFORE_THEME'" "[ \"$(theme)\" = \"$BEFORE_THEME\" ]"
check "theme files removed" "[ ! -e $HESTIA/web/atlas ] && [ ! -e $HESTIA/web/css/themes/custom/atlas.css ] && [ ! -e $HESTIA/web/css/themes/custom/atlas-evergreen.css ] && [ ! -e $HESTIA/web/js/custom_scripts/atlas.php ] && [ ! -d /usr/local/hestia-atlas ]"
check "hook block removed, custom hook kept" "! grep -q hestia-atlas $HOOK && grep -q custom-hook-ran $HOOK"
check "hestia web tree identical to before" "[ \"$(web_hash)\" = \"$BEFORE_WEB\" ]"
check "stock UI served" "curl -sk https://127.0.0.1:8083/login/ | grep -q 'default.min.css' && ! curl -sk https://127.0.0.1:8083/login/ | grep -qE 'custom/atlas(-evergreen)?[.]'"

# restore the hook as it was before the test
if [ -f /tmp/ha-hook.bak ]; then cp -a /tmp/ha-hook.bak $HOOK; else rm -f $HOOK; fi
[ "$(sha256sum $HOOK 2> /dev/null | cut -c1-16)" = "$BEFORE_HOOK" ] && ok "hook file restored to pre-test state" || bad "hook file differs from pre-test state"

echo
echo "passed: $PASS  failed: $FAIL"
[ $FAIL -eq 0 ]
