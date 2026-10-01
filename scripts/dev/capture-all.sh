#!/usr/bin/env bash
# Capture every screenshot set on the dev server (switches the system theme between runs).
#   scripts/dev/capture-all.sh
# Requires both editions to be installed on the server (deploy.sh + lite/install.sh).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
RSH="$ROOT/scripts/dev/rsh"
PW="npx playwright test -c tests/playwright.config.mjs"

theme() { # system theme; clears per-user selections of the test accounts
	"$RSH" "sed -i \"s/^THEME=.*/THEME=''/\" /usr/local/hestia/data/users/admin/user.conf /usr/local/hestia/data/users/jdemo/user.conf; /usr/local/hestia/bin/v-change-sys-config-value THEME $1"
	echo "== theme $1"
}
shots() { # set viewports [filter]
	SHOT_SET="$1" SHOT_VIEWPORTS="$2" SHOT_FILTER="${3:-}" $PW tests/screenshots.spec.mjs ${3:+-g pages} 2>&1 | tail -1
}

rm -rf screenshots/current screenshots/evergreen screenshots/lite screenshots/lite-evergreen screenshots/readme screenshots/baseline

theme atlas
shots current desktop,mobile
for f in dashboard web users; do shots current laptop,tablet-l,tablet "$f"; done
$PW tests/readme-shots.spec.mjs 2>&1 | tail -1

theme atlas-evergreen
shots evergreen desktop,mobile
README_SCHEME=evergreen $PW tests/readme-shots.spec.mjs 2>&1 | tail -1

theme atlas-lite
shots lite desktop,mobile

theme atlas-lite-evergreen
shots lite-evergreen desktop,mobile

theme default
shots baseline desktop,mobile

theme atlas
echo "all screenshot sets captured"
