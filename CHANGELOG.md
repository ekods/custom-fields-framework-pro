# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Added
- Added a Global Settings toggle for disabling CFF REST API writes while keeping REST reads enabled.

### Changed
- Refined admin and content editor styling with cleaner surfaces, tighter radii, restrained shadows, improved headers, tabs, forms, tables, field rows, and metabox panels.
- Restricted internal CFF post types and settings saves to the configurable CFF admin capability.
- Reused request-level field group settings cache across editor matching and REST schema generation.
- Hardened JSON import handling with upload, size, extension, JSON, and payload-shape validation.
- Moved reorder AJAX handling into a dedicated `Reorder_Manager` while preserving existing plugin wrapper methods.
- Moved lookup AJAX handling into a dedicated `Ajax_Controller` while preserving existing plugin wrapper methods.
- Moved dynamic post type and taxonomy registration into a dedicated `Dynamic_Content_Manager` while preserving existing plugin wrapper methods.
- Moved public taxonomy term meta UI and save handling into a dedicated `Term_Meta_Manager` while preserving existing plugin wrapper methods.
- Moved content field save handling out of `render.php` into a dedicated `Content_Field_Saver`.

### Tests
- Added unit coverage for REST write toggle behavior and JSON import payload validation.
- Added unit coverage for content field ordering, hidden-section saves, sanitized value saves, and empty-value deletes.

## 2.5.17

### Fixed
- Included bundled Select2 assets in the release ZIP so admin field builder pages no longer load missing JS/CSS files.

## 2.5.16

### Fixed
- Prevented the Select2 initializer from throwing when the Select2 plugin is unavailable during field builder refresh.

## 2.5.15

### Fixed
- Made field builder rendering tolerate missing jQuery UI sortable/droppable helpers.
- Normalized legacy choice, field, subfield, layout, and relational data before rendering.
- Added the underlying render exception message to field builder error notices.

## 2.5.14

### Fixed
- Reused the shared field renderer for initial builder rows so saved fields appear consistently in Builder and Reorder views.
- Added a render-failure guard so broken admin rendering does not overwrite saved field definitions with an empty array.
- Preserved existing field definitions when a save request submits an empty field payload unexpectedly.

## 2.5.13

### Fixed
- Restored Gallery Preview Fit values when reopening saved field groups.
- Added a legacy field JSON fallback so field rows can still render if `_cff_settings` fields are empty.
- Mirrored sanitized field definitions back to the legacy `cff_fields_json` meta for compatibility.

## 2.5.12

### Fixed
- Forced Gallery Preview Fit changes to sync into saved field JSON before save and publish actions.
- Ensured Contain gallery previews override later cover rules.
- Preserved the Field Reorder View hide option through presentation sanitization.

## 2.5.11

### Added
- Added Gallery Preview Fit persistence for gallery fields with Cover and Contain preview modes.
- Added hide support for the `.cff-field-reorder-view` field builder reorder panel.

### Fixed
- Synced gallery preview fit changes into saved field JSON immediately when the select value changes.

## 2.5.10

### Fixed
- Synced per-post field reorder UI with rendered field order and frontend ordered field helpers.
- Kept normal content saves aligned with the Field Group setting order unless a post-specific reorder is explicitly edited.

## 2.5.9

### Fixed
- Saved nested media companion URLs for group, repeater, and flexible fields so selected image/video URLs are not dropped before formatting.
- Resolved media IDs from submitted companion URLs when possible, preventing stale attachment IDs from rendering an older asset.
- Added a defensive media-frame fallback so the picker can still open if a library filter fails in WordPress admin.

## 2.5.8

### Fixed
- Restored image field support for both image and video attachments while still rejecting unrelated attachment types.
- Kept cleared nested media values as explicit empty values so old image/video IDs are not merged back after save.

## 2.5.7

### Fixed
- Limited image fields to image attachments in the media picker instead of allowing videos.
- Rejected non-image attachment IDs during image field sanitization so stale video IDs cannot be saved back into image fields.
- Rendered invalid existing video IDs as empty for image fields in the editor.

## 2.5.6

### Fixed
- Scoped media picker updates to the active media field so nested group/repeater media inputs do not keep stale attachment IDs or URLs.
- Preserved submitted empty nested image/file values during save so cleared group, repeater, and flexible media fields override older attachment IDs instead of merging stale media back in.

## 2.5.5

### Fixed
- Cleared stale companion media URL metadata when image/file fields are removed.
- Made the field builder initialize against the current builder root markup and tolerate saved field JSON wrapped in a `fields` object.

## 2.5.4

### Added
- Added parameter and hash inputs for internal Button/Link fields.

### Fixed
- Preserved internal Button/Link mode metadata after saving so internal links do not reload as custom links.

## 2.5.3

### Fixed
- Enforced per-post `Hide Section` state in direct frontend helpers, including `cff_get_value()`, `cff_get_text()`, repeater helpers, shortcode field checks, and CFF-compatible `get_field()`.
- Kept hidden-section checks consistent with Polylang-local metadata so translated posts do not inherit hidden sections unless saved locally.

## 2.5.2

### Added
- Added a per-post `Hide Section` switch directly inside rendered `.cff-field` editors.
- Hidden rendered sections are saved in `_cff_hidden_sections` and excluded from frontend helpers and shortcode output.

### Changed
- Rendered field bodies now hide immediately with `display:none!important` when the section switch is enabled, while keeping the switch visible for re-enabling.

## 2.5.1

### Added
- Renamed the field hide control to `Hide Section` for clearer dynamic section workflows.
- Added frontend hide enforcement for ordered field helpers and `[cff_items]` loops.

### Changed
- Hidden fields are excluded from ordered frontend section output, including saved per-post reorder sequences and candidate-based shortcode loops.
- Direct hidden field shortcode calls now return a hidden placeholder with `display:none!important`.

## 2.5.0

### Added
- Added the native WordPress Custom Fields panel to Field Group “Hide on screen” controls.
- Added paginated AJAX search for relational select fields.
- Added PHPUnit configuration and a PHP 7.4–8.3 CI matrix.

### Changed
- Centralized classic-editor and REST value validation in a type-aware field sanitizer.
- Load heavy editor assets only when the current screen has matching field groups.
- Cache field group settings per request and batch large post reorder writes.
- Made reorder handles keyboard-accessible with Arrow Up and Arrow Down.

### Fixed
- Hidden editor fields remain available to frontend helpers and REST responses.
- REST reads and writes no longer fall back to field groups that do not match the current post.
- Native WordPress meta boxes are removed through `remove_meta_box()` in addition to the CSS fallback.

## 2.4.1

### Fixed
- Fixed missing `tk-header-branding` styles on native WordPress admin pages.
- Suppressed `Tool Kits` license notification inside CFF admin screens to maintain a clean UI.

## 2.4.0

### Added
- Synchronized design system with the Tool Kits plugin for a premium, SaaS-like administrative UI.
- New standalone `tk-` CSS utility classes included directly within the plugin for independent styling.
- Added `cff_render_header_branding()` and `cff_render_page_hero()` UI helpers to unify plugin headers.

### Changed
- Modernized the UI for Dashboard, Post Types, Taxonomies, Reorder, Tools, and Documentation pages to use `tk-card` and `tk-grid` layouts.
- Updated Field Group edit screens and Global Settings views to render consistent plugin branding above standard WordPress list tables.

## 2.3.0

### Added
- Added `Repeater Layout: Table (fill values inline)`.
- Added real table rendering for repeater fields using `table`, `thead`, `tbody`, and `tr`.
- Added delete confirmation for repeater rows and flexible rows.
- Added `File Library` option for `file` fields:
  - `All files`
  - `PDF only`
  - `Excel only`
  - `Word only`
  - `Images only`
  - `Video only`
  - `Document bundle`
- Added allowed file type info to `cff-media-limit-label`.
- Added shortcode usage examples for frontend PHP via `do_shortcode()`.
- Added `Keep data on uninstall` setting.
- Added uninstall cleanup handler in `uninstall.php`.
- Added GitHub Actions PHP lint workflow.

### Changed
- Improved admin UI and frontend field render UI for more consistent layout.
- Updated repeater `row` layout to support two columns with wrapping.
- Moved `cff-media-actions` inside media preview.
- Improved media preview handling so action buttons stay visible after selecting media.
- Refactored plugin internals by separating tools page and REST field logic into dedicated classes.
- Added caching for GitHub release updater requests using site transients.
- Expanded `CFF Documentation` with shortcode and PHP usage guidance.

### Fixed
- Fixed plugin `Text Domain` and `Domain Path`.
- Fixed `load_plugin_textdomain()` initialization.
- Fixed updater initialization timing.
- Added capability checks to several AJAX endpoints.
- Removed leftover debug `console.log` calls from admin scripts.
- Fixed top-level repeater table layout incorrectly falling back to `data-layout="default"`.
- Fixed `Add Row` behavior for repeater table layout.
- Fixed `tbody` display bug in repeater table layout caused by conflicting flex styles.
- Fixed repeater table action column placement and moved it to the first column.
- Fixed missing delete confirmation on `.cff-rep-remove`.
