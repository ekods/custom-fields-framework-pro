#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"
PLUGIN_SLUG="$(basename "${ROOT_DIR}")"
OUTPUT_PATH="${1:-"${ROOT_DIR}/../${PLUGIN_SLUG}.zip"}"

mkdir -p "$(dirname "${OUTPUT_PATH}")"
rm -f "${OUTPUT_PATH}"

git -C "${ROOT_DIR}" archive \
  --format=zip \
  --worktree-attributes \
  --prefix="${PLUGIN_SLUG}/" \
  --output="${OUTPUT_PATH}" \
  HEAD

echo "Created ${OUTPUT_PATH}"
