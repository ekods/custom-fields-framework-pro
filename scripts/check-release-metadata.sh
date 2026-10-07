#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE="$ROOT_DIR/custom-fields-framework-pro.php"
README_FILE="$ROOT_DIR/readme.txt"
EXPECTED_TAG="${1:-}"

for file in "$PLUGIN_FILE" "$README_FILE"; do
  if [[ ! -f "$file" ]]; then
    echo "Release metadata file not found: $file" >&2
    exit 1
  fi
done

HEADER_VERSION="$(sed -n 's/^ \* Version: //p' "$PLUGIN_FILE")"
CONSTANT_VERSION="$(sed -n "s/^define('CFFP_VERSION', '\([^']*\)');$/\1/p" "$PLUGIN_FILE")"
STABLE_VERSION="$(sed -n 's/^Stable tag: //p' "$README_FILE")"

if [[ ! "$HEADER_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Plugin Version must be a stable version such as 2.5.18." >&2
  exit 1
fi

if [[ "$HEADER_VERSION" != "$CONSTANT_VERSION" || "$HEADER_VERSION" != "$STABLE_VERSION" ]]; then
  echo "Version mismatch: header=$HEADER_VERSION, CFFP_VERSION=$CONSTANT_VERSION, Stable tag=$STABLE_VERSION" >&2
  exit 1
fi

if [[ -n "$EXPECTED_TAG" && "$EXPECTED_TAG" != "v$HEADER_VERSION" ]]; then
  echo "Tag mismatch: expected v$HEADER_VERSION, got $EXPECTED_TAG" >&2
  exit 1
fi

if ! grep -Fqx "= $HEADER_VERSION =" "$README_FILE"; then
  echo "Missing readme.txt changelog entry for $HEADER_VERSION." >&2
  exit 1
fi

echo "Release metadata looks consistent: version $HEADER_VERSION${EXPECTED_TAG:+, tag $EXPECTED_TAG}"
