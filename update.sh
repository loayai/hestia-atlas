#!/bin/bash
# Update the Hestia Atlas theme from this checkout.
#
# Usage: sudo ./update.sh [--no-pull] [install.sh options]
#
# Pulls the latest release (when this is a git checkout), then re-runs install.sh without
# changing which theme is selected. Run `hestia-atlas status` afterwards to verify.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

PULL=1
ARGS=()
for a in "$@"; do
	if [ "$a" = "--no-pull" ]; then PULL=0; else ARGS+=("$a"); fi
done

if [ "$PULL" = 1 ] && [ -d .git ] && command -v git > /dev/null; then
	echo "[hestia-atlas] pulling latest changes"
	git pull --ff-only
fi

exec ./install.sh --no-activate "${ARGS[@]}"
