#!/usr/bin/env bash
# Build locally and install on the dev server (uses the real installer).
#   scripts/dev/deploy.sh [install.sh options]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
node scripts/build.mjs
tar -czf - install.sh uninstall.sh update.sh dist tests/server | "$ROOT/scripts/dev/rsh" 'rm -rf /root/hestia-atlas-dev && mkdir -p /root/hestia-atlas-dev && tar -xzf - -C /root/hestia-atlas-dev'
"$ROOT/scripts/dev/rsh" "cd /root/hestia-atlas-dev && chmod +x install.sh uninstall.sh update.sh && ./install.sh $*"
