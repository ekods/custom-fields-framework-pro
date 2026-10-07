#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ZIP_PATH="${1:-$ROOT_DIR/../custom-fields-framework-pro.zip}"
PLUGIN_SLUG="custom-fields-framework-pro"

if ! command -v unzip >/dev/null 2>&1; then
  echo "unzip is required to validate the release archive." >&2
  exit 1
fi
if [[ ! -f "$ZIP_PATH" ]]; then
  echo "Archive not found: $ZIP_PATH" >&2
  exit 1
fi

unzip -tq "$ZIP_PATH" >/dev/null
LISTING="$(unzip -Z1 "$ZIP_PATH")"

if grep -vq "^$PLUGIN_SLUG/" <<<"$LISTING" || grep -Eq '(^|/)\.\.(/|$)|\\' <<<"$LISTING"; then
  echo "Invalid archive paths. Expected every entry inside $PLUGIN_SLUG/." >&2
  exit 1
fi

for file in \
  '' custom-fields-framework-pro.php uninstall.php readme.txt \
  includes/bootstrap.php includes/render.php includes/class-updater.php \
  includes/class-activation.php includes/class-deactivation.php \
  includes/class-plugin.php includes/class-field-sanitizer.php \
  includes/class-rest-fields.php includes/class-tools-page.php \
  includes/class-reorder-manager.php includes/class-ajax-controller.php \
  includes/class-dynamic-content-manager.php includes/class-term-meta-manager.php \
  includes/class-content-field-saver.php \
  includes/helpers/acf-compat.php includes/helpers/frontend-helpers.php \
  includes/helpers/frontend-shortcodes.php includes/helpers/field-group-columns.php \
  includes/helpers/admin-ui.php \
  assets/admin.css assets/admin.js assets/post.css assets/post.js assets/list.css \
  assets/js/select2-init.js assets/vendor/select2/select2.min.css assets/vendor/select2/select2.min.js; do
  if ! grep -Fqx "$PLUGIN_SLUG/$file" <<<"$LISTING"; then
    echo "Missing required release entry: $PLUGIN_SLUG/$file" >&2
    exit 1
  fi
done

if grep -Eq '(^|/)(\.[^/]+|__MACOSX)(/|$)|\.(md|zip|log)$|^custom-fields-framework-pro/(scripts|tests|vendor|node_modules)/|^custom-fields-framework-pro/(composer\.(json|lock)|package(-lock)?\.json|phpunit\.xml[^/]*)$' <<<"$LISTING"; then
  echo "Archive contains development files, nested ZIPs, or hidden metadata." >&2
  exit 1
fi

echo "Release archive looks valid: $ZIP_PATH"
