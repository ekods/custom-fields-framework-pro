#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="custom-fields-framework-pro"
OUTPUT_PATH="${1:-"${ROOT_DIR}/../${PLUGIN_SLUG}.zip"}"
if [[ "$OUTPUT_PATH" != /* ]]; then
  OUTPUT_PATH="$(pwd)/$OUTPUT_PATH"
fi
TEMP_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

for tool in zip unzip rsync; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    echo "$tool is required to build the release package." >&2
    exit 1
  fi
done

bash "$ROOT_DIR/scripts/check-release-metadata.sh"

if [[ "${CFFP_SKIP_SYNC:-0}" != "1" ]]; then
  bash "$ROOT_DIR/scripts/sync-to-release-repo.sh"
fi

mkdir -p "$(dirname "${OUTPUT_PATH}")"

mkdir -p "$TEMP_DIR/$PLUGIN_SLUG"

rsync -a "$ROOT_DIR/" "$TEMP_DIR/$PLUGIN_SLUG/" \
  --exclude '.git' \
  --exclude '.gitattributes' \
  --exclude '.github' \
  --exclude '.gitignore' \
  --exclude '.DS_Store' \
  --exclude '__MACOSX' \
  --exclude '.env*' \
  --exclude '.idea' \
  --exclude '.vscode' \
  --exclude '.agents' \
  --exclude '.codex' \
  --exclude '.phpunit.cache' \
  --exclude '.phpunit.result.cache' \
  --exclude 'composer.json' \
  --exclude 'composer.lock' \
  --exclude 'node_modules' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'phpunit.xml.dist' \
  --exclude 'scripts' \
  --exclude 'tests' \
  --exclude '/vendor' \
  --exclude '*.zip' \
  --exclude '*.md' \
  --exclude '*.log'

(cd "$TEMP_DIR" && zip -qr release.zip "$PLUGIN_SLUG")

bash "$ROOT_DIR/scripts/validate-release-zip.sh" "$TEMP_DIR/release.zip"
mv "$TEMP_DIR/release.zip" "$OUTPUT_PATH"

echo "Created ${OUTPUT_PATH}"
