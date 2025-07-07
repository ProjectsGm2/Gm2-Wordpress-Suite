#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_SLUG="gm2-wordpress-suite"
ZIP_FILE="${PLUGIN_SLUG}.zip"

cd "$ROOT_DIR"

# Install production dependencies
composer install --no-dev --optimize-autoloader

# Remove any existing archive
rm -f "$ZIP_FILE"

STAGING_DIR="$(mktemp -d)"

# Copy plugin files to staging directory
rsync -a \
  --exclude='.git*' \
  --exclude='.github' \
  --exclude='bin/*' \
  --exclude='tests*' \
  --exclude='phpunit.xml' \
  "$ROOT_DIR/" "$STAGING_DIR/$PLUGIN_SLUG/"

# Create zip archive
(cd "$STAGING_DIR" && zip -r "$ROOT_DIR/$ZIP_FILE" "$PLUGIN_SLUG")

# Cleanup
rm -rf "$STAGING_DIR"

echo "Created $ZIP_FILE"
