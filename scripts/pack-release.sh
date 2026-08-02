#!/usr/bin/env bash
# Pack a production-ready zip for new installs and in-app updates.
# Expects: composer install --no-dev and npm run build already done.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
  VERSION="$(grep -E '^DEPOT_VERSION=' .env.example 2>/dev/null | cut -d= -f2- | tr -d '"' || true)"
  VERSION="${VERSION:-0.0.0}"
fi
VERSION="${VERSION#v}"

PKG_NAME="maintenance-depot"
STAGE="dist/${PKG_NAME}"
OUT_DIR="dist"
INSTALL_ZIP="${OUT_DIR}/${PKG_NAME}-${VERSION}-install.zip"
UPDATE_ZIP="${OUT_DIR}/${PKG_NAME}-${VERSION}-update.zip"
# Canonical zip (also used if update/install suffixes are absent).
DEFAULT_ZIP="${OUT_DIR}/${PKG_NAME}-${VERSION}.zip"

rm -rf dist
mkdir -p "$STAGE"

# Prefer rsync when available; fall back to a filtered tar pipe.
copy_tree() {
  local src="$1"
  local dest="$2"
  mkdir -p "$(dirname "$dest")"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a \
      --exclude='.git' \
      --exclude='.github' \
      --exclude='.cursor' \
      --exclude='.codex' \
      --exclude='.idea' \
      --exclude='.vscode' \
      --exclude='.zed' \
      --exclude='.nova' \
      --exclude='.vagrant' \
      --exclude='node_modules' \
      --exclude='tests' \
      --exclude='dist' \
      --exclude='coverage' \
      --exclude='.phpunit.cache' \
      --exclude='.phpunit.result.cache' \
      --exclude='phpunit.xml' \
      --exclude='.env' \
      --exclude='.env.backup' \
      --exclude='.env.local' \
      --exclude='.env.production' \
      --exclude='.env.staging' \
      --exclude='auth.json' \
      --exclude='*.sqlite' \
      --exclude='*.sqlite-journal' \
      --exclude='*.sql' \
      --exclude='*.sql.gz' \
      --exclude='*.pem' \
      --exclude='*.p12' \
      --exclude='*.pfx' \
      --exclude='*.key' \
      --exclude='Homestead.json' \
      --exclude='Homestead.yaml' \
      --exclude='Maintenance-Depot brand' \
      --exclude='favicon.png' \
      --exclude='logo.png' \
      --exclude='logo-horizontal.png' \
      --exclude='public/hot' \
      --exclude='public/storage' \
      --exclude='public/fonts-manifest.dev.json' \
      --exclude='public/icon-sheet.html' \
      --exclude='storage/app/backups' \
      --exclude='storage/app/private/*' \
      --exclude='storage/app/public/*' \
      --exclude='storage/app/update-*' \
      --exclude='storage/app/labels-export-*' \
      --exclude='storage/framework/cache/data/*' \
      --exclude='storage/framework/sessions/*' \
      --exclude='storage/framework/testing/*' \
      --exclude='storage/framework/views/*' \
      --exclude='storage/logs/*' \
      --exclude='storage/*.key' \
      --exclude='*.log' \
      --exclude='.DS_Store' \
      --exclude='Thumbs.db' \
      --exclude='_ide_helper.php' \
      --exclude='agent-transcripts' \
      "$src" "$dest"
  else
    tar -cf - \
      --exclude='.git' \
      --exclude='.github' \
      --exclude='.cursor' \
      --exclude='node_modules' \
      --exclude='tests' \
      --exclude='dist' \
      --exclude='.env' \
      --exclude='vendor' \
      -C "$src" . | tar -xf - -C "$dest"
  fi
}

echo "==> Staging release tree (v${VERSION})"
copy_tree "./" "${STAGE}/"

# Ensure production vendor + built assets are present
if [[ ! -d vendor ]]; then
  echo "ERROR: vendor/ missing. Run: composer install --no-dev --optimize-autoloader" >&2
  exit 1
fi
if [[ ! -d public/build ]]; then
  echo "ERROR: public/build missing. Run: npm ci && npm run build" >&2
  exit 1
fi

# Re-copy vendor and public/build explicitly (rsync above includes them from ROOT)
rsync -a --delete vendor/ "${STAGE}/vendor/"
rsync -a --delete public/build/ "${STAGE}/public/build/"

# Keep .env.example only (never a real .env)
rm -f "${STAGE}/.env" "${STAGE}/.env.backup" "${STAGE}/.env.production" "${STAGE}/.env.local"
if [[ -f .env.example ]]; then
  cp .env.example "${STAGE}/.env.example"
  # Stamp package version for installers / docs
  if grep -q '^DEPOT_VERSION=' "${STAGE}/.env.example"; then
    sed -i.bak "s/^DEPOT_VERSION=.*/DEPOT_VERSION=${VERSION}/" "${STAGE}/.env.example"
    rm -f "${STAGE}/.env.example.bak"
  fi
fi

# Storage scaffolding: empty dirs with .gitignore placeholders
ensure_storage() {
  local dir="$1"
  mkdir -p "${STAGE}/${dir}"
  if [[ -f "${dir}/.gitignore" ]]; then
    cp "${dir}/.gitignore" "${STAGE}/${dir}/.gitignore"
  else
    printf '*\n!.gitignore\n' > "${STAGE}/${dir}/.gitignore"
  fi
}
ensure_storage storage/app/private
ensure_storage storage/app/public
ensure_storage storage/framework/cache/data
ensure_storage storage/framework/sessions
ensure_storage storage/framework/testing
ensure_storage storage/framework/views
ensure_storage storage/logs
# Drop any accidental runtime files under storage
find "${STAGE}/storage" -type f ! -name '.gitignore' -delete 2>/dev/null || true

# Drop local SQLite if copied
find "${STAGE}/database" -name '*.sqlite*' -delete 2>/dev/null || true

# Version markers
printf '%s\n' "$VERSION" > "${STAGE}/VERSION"
printf '%s\n' "$VERSION" > "${STAGE}/public/VERSION"

# Install zip = full package for new hosts
# Update zip = same payload (updater / overlay); exclude installer-only noise if any
echo "==> Creating zip archives"
(
  cd dist
  zip -qr "$(basename "$INSTALL_ZIP")" "$PKG_NAME"
  cp "$(basename "$INSTALL_ZIP")" "$(basename "$UPDATE_ZIP")"
  cp "$(basename "$INSTALL_ZIP")" "$(basename "$DEFAULT_ZIP")"
)

echo "Created:"
ls -lh "$INSTALL_ZIP" "$UPDATE_ZIP" "$DEFAULT_ZIP"
