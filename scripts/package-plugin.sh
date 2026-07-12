#!/usr/bin/env bash
# Build dashboard assets and create a deploy zip (excludes client/node_modules).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CLIENT="$ROOT/client"
OUT_DIR="$ROOT/../dist"
ZIP_NAME="webinocrm-$(grep "WEBINOCRM_VERSION" "$ROOT/webinocrm.php" | head -1 | sed -E "s/.*'([^']+)'.*/\1/").zip"

echo "[package] build:check in client/"
cd "$CLIENT"
npm run build:check

echo "[package] verify build-entry.json"
test -f "$ROOT/assets/dashboard-build/build-entry.json"
test -f "$ROOT/assets/dashboard-build/assets/index.js"
JS=$(python3 -c "import json; print(json.load(open('$ROOT/assets/dashboard-build/build-entry.json'))['js'])")
test -f "$ROOT/assets/dashboard-build/$JS"

echo "[package] creating zip (excluding node_modules)"
mkdir -p "$OUT_DIR"
rm -f "$OUT_DIR/$ZIP_NAME"
(
  cd "$(dirname "$ROOT")"
  zip -r "$OUT_DIR/$ZIP_NAME" "$(basename "$ROOT")" \
    -x "$(basename "$ROOT")/client/node_modules/*" \
    -x "$(basename "$ROOT")/client/node_modules/**" \
    -x "$(basename "$ROOT")/.git/*" \
    -x "$(basename "$ROOT")/.git/**"
)

echo "[package] done: $OUT_DIR/$ZIP_NAME"
ls -lh "$OUT_DIR/$ZIP_NAME"
