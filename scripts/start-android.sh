#!/data/data/com.termux/files/usr/bin/bash

set -eu

PROJECT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

if [ ! -x "${PREFIX:-}/bin/start-app" ]; then
    printf '%s\n' 'The Android setup has not been completed. Run: bash scripts/setup-android.sh' >&2
    exit 1
fi

exec "$PREFIX/bin/start-app"
