#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="$(basename "${ROOT_DIR}")"
OUTPUT_PATH="${1:-"${ROOT_DIR}/../${PLUGIN_SLUG}.zip"}"
TEMP_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

mkdir -p "$(dirname "${OUTPUT_PATH}")"
rm -f "${OUTPUT_PATH}"

mkdir -p "$TEMP_DIR/$PLUGIN_SLUG"

rsync -a "$ROOT_DIR/" "$TEMP_DIR/$PLUGIN_SLUG/" \
  --exclude '.git' \
  --exclude '.gitattributes' \
  --exclude '.github' \
  --exclude '.gitignore' \
  --exclude '.DS_Store' \
  --exclude '.phpunit.result.cache' \
  --exclude 'composer.json' \
  --exclude 'phpunit.xml.dist' \
  --exclude 'scripts' \
  --exclude 'tests' \
  --exclude 'vendor' \
  --exclude '*.md'

(cd "$TEMP_DIR" && zip -qr "$OUTPUT_PATH" "$PLUGIN_SLUG")

echo "Created ${OUTPUT_PATH}"
