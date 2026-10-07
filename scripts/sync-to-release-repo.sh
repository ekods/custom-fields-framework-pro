#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TARGET_DIR="${CFFP_RELEASE_REPO:-$HOME/Sites/localhost/ekodwis/wp-content/plugins/custom-fields-framework-pro}"
DRY_RUN=0
if [[ "${1:-}" == '--dry-run' ]]; then
  DRY_RUN=1
elif [[ $# -ne 0 ]]; then
  echo "Usage: bash scripts/sync-to-release-repo.sh [--dry-run]" >&2
  exit 1
fi

for tool in rsync git; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    echo "$tool is required to sync the release repo." >&2
    exit 1
  fi
done
if [[ ! -d "$TARGET_DIR" ]]; then
  echo "Release repo not found: $TARGET_DIR. Set CFFP_RELEASE_REPO to override it." >&2
  exit 1
fi
TARGET_DIR="$(cd "$TARGET_DIR" && pwd -P)"
if [[ "$ROOT_DIR" == "$TARGET_DIR" ]]; then
  echo "Already running inside the release repo; nothing to sync."
  exit 0
fi
case "$TARGET_DIR/" in "$ROOT_DIR/"*) echo 'Refusing to sync into the source folder.' >&2; exit 1;; esac
case "$ROOT_DIR/" in "$TARGET_DIR/"*) echo 'Refusing to sync into a parent of the source folder.' >&2; exit 1;; esac

if [[ ! -d "$TARGET_DIR/.git" || ! -f "$TARGET_DIR/custom-fields-framework-pro.php" ]]; then
  echo "Refusing to sync: destination must be the Custom Fields Framework Pro Git repo." >&2
  exit 1
fi
REMOTE="$(git -C "$TARGET_DIR" remote get-url origin)"
case "$REMOTE" in
  https://github.com/ekods/custom-fields-framework-pro|https://github.com/ekods/custom-fields-framework-pro.git|git@github.com:ekods/custom-fields-framework-pro.git) ;;
  *) echo "Refusing to sync: unexpected origin $REMOTE" >&2; exit 1;;
esac

RSYNC_ARGS=(-a --delete --exclude .git --exclude .DS_Store --exclude '*.zip' --exclude .env --exclude '.env.*' --exclude CLAUDE.md --exclude AGENTS.md --exclude .agents --exclude .codex --exclude .idea --exclude .vscode --exclude /vendor --exclude /node_modules --exclude .phpunit.cache --exclude .phpunit.result.cache)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_ARGS+=(--dry-run --itemize-changes)
  echo 'Dry run: no files will be written.'
fi
rsync "${RSYNC_ARGS[@]}" "$ROOT_DIR/" "$TARGET_DIR/"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo '(no changes listed means both folders are already identical)'
else
  echo "Synced source to $TARGET_DIR"
  git -C "$TARGET_DIR" status --short
fi
