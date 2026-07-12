#!/usr/bin/env bash
# Fail CI if legacy PuzzlingCRM identifiers appear outside the one-shot DB migration in class-installer.php.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Match historical product strings (table prefix, CPT keys, option keys). Exempt only includes/class-installer.php.
PATTERN='puzzling|puzzlingcrm|pzl_'

FILTER_EXEMPT='^\./includes/class-installer\.php$|^\./scripts/check-no-legacy-branding\.sh$'

if grep -RIl -E "$PATTERN" \
  --exclude-dir=.git --exclude-dir=node_modules \
  --include='*.php' --include='*.js' --include='*.ts' --include='*.tsx' \
  --include='*.css' --include='*.scss' --include='*.html' --include='*.md' \
  --include='*.pot' --include='*.po' --include='*.txt' \
  . 2>/dev/null | grep -Ev "$FILTER_EXEMPT" | grep -q .; then
  echo "ERROR: Legacy branding strings found outside includes/class-installer.php:"
  grep -RIn -E "$PATTERN" \
    --exclude-dir=.git --exclude-dir=node_modules \
    --include='*.php' --include='*.js' --include='*.ts' --include='*.tsx' \
    --include='*.css' --include='*.scss' --include='*.html' --include='*.md' \
    --include='*.pot' --include='*.po' --include='*.txt' \
    . 2>/dev/null | grep -Ev "$FILTER_EXEMPT" || true
  exit 1
fi

echo "OK: no legacy puzzling/pzl strings outside migration (includes/class-installer.php)."
