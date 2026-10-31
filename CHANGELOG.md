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

## 2.5.41

### Changed
- Post Types and Taxonomies tables: the name links to the definition's settings editor.
- Post Types table: removed the "Manage content" and "Add new" icon links; the Actions column keeps Edit, Duplicate and Delete.

## 2.5.40

### Fixed
- Replace inconsistent inline PHP/shortcode example styles in Global Settings → Guide & Usage with `.cff-guide-code`. Constrain the preformatted block to its panel using border-box sizing and internal horizontal scrolling.
- Reset nested code padding, background and typography so shared code styles do not widen the examples. Preserve the original PHP and shortcode text for copying.

### Validation
- Render the actual guide in browser fixtures with 180px, 240px, 280px and 360px sidebars. Both examples stay within the guide and do not overlap following content; long lines scroll within their blocks.

## 2.5.39

### Changed
- Add a native Actions column to Field Groups. Move the existing WordPress-generated action nodes to it, retaining capabilities, nonce URLs, extension attributes and native row handling. Load the placement script only on the Field Groups list.
- Keep actions available when Screen Options hides their column by returning them to the primary cell. Retain the original actions when JavaScript is unavailable, and realign actions after row refreshes. Show the Actions cell without requiring mobile row expansion.
- Combine Post Type content shortcuts and definition controls in a single Actions column. Use explicit Actions/Frontend columns in the Overview and Taxonomies tables with consistent widths, row spacing, alignment and separators.
- Refine mobile wrapping and tap sizes while preserving definition delete confirmation. Repeater/flexible editor controls are unchanged.

### Validation
- Browser checks at desktop/tablet/mobile widths cover action placement, exact nonce URL preservation, Screen Options hide/show, no duplicate title actions, no horizontal page overflow, and definition delete confirmation/cancellation.

## 2.5.38

### Added
- Toggle collapsible repeater rows by clicking `.cff-rep-row-head` or its title, with a pointer cursor. Use delegated handling for nested and dynamically created rows.
- Keep clone/remove/drag handles, links and other interactive header controls independent. Suppress toggle clicks during sorting and the mouseup click following a drag.
- Add accessible names/expanded state to repeater toggle buttons and synchronize that state after toggles, row creation and title refreshes. Simple/gallery/table layouts retain their existing non-collapsible behavior.

### Validation
- Browser checks with production editor JavaScript and jQuery UI cover header/title collapse, single arrow/keyboard toggles, nested/new rows, unchanged submitted content, clone/remove exclusions and actual drag reordering. Runtime and relational renderer regressions pass.

## 2.5.37

### Changed
- Make repeater row drag/toggle/clone/remove controls and flexible layout removal borderless and transparent in normal/hover states, preserving icon colors, control dimensions and keyboard focus.
- Remove `margin-right: auto` from `.cff-field-actions .cff-copy-field-indicator` so the indicator no longer pushes copy controls apart.

### Validation
- Checked computed border/background/shadow styles for all five row controls at desktop/mobile widths, including hover, copy indicator spacing and keyboard focus.

## 2.5.36

### Changed
- Set `.tk-wrap` and the native `edit.php?post_type=cff_group` list wrapper to a 1440px maximum width with aligned edges. Give the native CFF branding wrapper the shared admin class and set Field Groups as the active navigation item.
- Consolidate duplicate branding rules and define explicit header spacing, surfaces, borders and responsive wrapping for the name, version and help link.
- Align management table cells, bound action column widths and improve native key/location wrapping. Keep WordPress primary-column expansion on mobile while avoiding overlapping column labels and shifted header checkboxes.
- Replace the management More dropdown with direct icon actions; render content/term shortcuts and native group row actions as labelled icon buttons. Preserve existing action attributes, nonce URLs, form payloads and delete confirmation. Scope icon sizing to tables so builder icon controls keep their existing layout.

### Validation
- Reviewed browser fixtures using the actual CFF PHP renderers, CSS/JavaScript and WordPress table styles at 1920px, 1024px and 390px. Verified wrapper/table alignment, no page overflow, square icon buttons, active navigation and confirmed/cancelled delete payloads without site/database writes.

## 2.5.35

### Added
- Add Global Settings → Editor UI Settings → Hide Section Controls. The option defaults off and suppresses the content editor's section hide switches when enabled.
- Preserve `_cff_hidden_sections` and its language-local marker during content saves while the controls are globally hidden, including stale editor submissions. Restoring the controls retains each section's existing state. Field definition visibility settings and frontend visibility behavior remain independent.
- Extend runtime regressions for authenticated settings saves, switch rendering, preserved metadata and normal unhide behavior after restoring the controls.

## 2.5.34

### Changed
- Import Content now opens a single existing destination: an editor sidebar action carries its current post ID; the menu provides searchable editable content. Page-scoped jobs are isolated per user and page. The original bulk backend remains compatible at `?page=cff-import-content&bulk=1`.
- Accept raw page section JSON without an `items` envelope. Resolve exact field names before unique suffix matches (`hero` → `why_hero`), validate against the saved template, and display mappings before writing. Unknown, ambiguous and duplicate mappings are rejected.
- Offer Replace supplied fields and Fill empty fields only. Empty filling recurses through group fields, treats missing/null/blank strings as empty and preserves numeric zero, false checkboxes and populated repeaters. Replace changes supplied values only; explicit empty group values now clear correctly.
- Resolve media paths/URLs to unique accessible attachments already in the Media Library without downloads. Report unresolved references and retain prior media at the same row/field position, or omit the missing media value when no previous value exists. Replacement repeater rows align these fallbacks by position; they do not match rows by text.
- Keep page core data, status, template and translations unchanged. Recheck resolved values/media matches against preview; generated repeater row IDs do not cause false conflicts.
- Generate example JSON from the chosen page's actual field definitions.

### Validation
- Exercise the exact supplied Why EGGHEAD JSON and CFF schema in isolated persistence fixtures, covering all seven sections, image paths, repeaters, partial group fill, replacement, context isolation and stale media matches.
- Exercise menu selection, the current-editor link, fixed request context, both policy options, raw JSON upload and safe preview notes in a browser.

## 2.5.33

### Added
- Added Custom Fields → Import Content and a link from Export / Import. JSON content files use a separate format from field group/post type/taxonomy configuration packages.
- Added validation preview, create/update/upsert modes matching destination IDs or slugs, and shared CFF sanitization for nested content, existing media/relationship references and taxonomy assignments. Planned page templates determine applicable field groups without creating preview posts.
- Added five-item batches, pause/resume/cancel, progress recovery, per-item reports and example JSON download. New content defaults to draft; explicit publishing/private statuses require an opt-in and capability check.
- Added per-user expiring jobs, atomic request leases, write-time permission/schema/destination checks and item rollback. An interrupted write pauses for review rather than being repeated automatically. Polylang synchronization is suspended while importing to avoid altering other translations.

### Validation
- Added isolated persistence regressions for preview, writes, partial group merges, escaping, relationship namespaces, capability checks, conflicts, rollback, interruption and progress recovery; included in PHP CI and release checks.
- Added a browser regression for the actual import page and controls, including errors, interrupted responses, pause/resume and safe report rendering.

## 2.5.32

### Changed
- Tidied content editor field rendering: link fields label their URL/Title inputs and lay them out side by side, relational Select2 controls match the height and radius of other inputs, media previews fit a 16:9 frame with an icon empty state, and gallery actions are labelled buttons.
- Aligned repeater and flexible rows (single border, consistent subfield spacing), moved the flexible layout badge beside its title, replaced the flexible "Remove" link with an icon button, and placed Add Row / Add Layout in matching dashed areas.
- Added a container query on `.cff-metabox` so side metaboxes stack link, media and flexible controls regardless of viewport width.
- Table-layout repeaters now size columns by field type (`cff-subfield-type-*`) and pass their column count to the table, scrolling horizontally rather than squeezing image, editor and nested fields.
- Extracted `cff_render_subfield_control()` and used it for repeater rows, table rows, groups and flexible layouts, replacing four diverged copies.
- Removed `post.css` rules that later rules fully override, verified by identical computed styles on the editor at wide, sidebar and narrow widths.
- Made Add Row, Add layout, Add, Select images and Clear translatable.

### Fixed
- Repeaters nested in flexible layouts rendered as a plain text input (showing "Array").
- Table repeaters nested in table rows emitted their row template without `<table><tbody>`, so the browser dropped the template row.
- Images in flexible layouts skipped `cff_media_render_id()` validation, and wysiwyg fields there used a reduced toolbar.
- Gallery fields in flexible layouts passed an undefined `$s` instead of the field settings, raising a warning and ignoring `gallery_preview_fit`.
- The link post type filter select inherited container padding from `.cff-input`.

## 2.5.31

### Fixed
- Preserve top-level relational metadata in native CFF JSON imports. `sanitize_fields()` previously dropped the relationship namespace and subtype, so a taxonomy field could fall back to Post and read an unrelated post title for the same numeric ID.
- Keep source type/subtype, taxonomy editor post type, display mode and multiple selection settings through import sanitization.

### Validation
- Regression first reproduced the dropped taxonomy metadata before the fix, then exercises imported Service configuration through rendering with a term and unrelated attachment sharing ID 149. The selected label must come from the term, and AJAX attributes must remain `taxonomy` / `service`.

### Existing installations
- Correct affected fields once in the Field Group editor (Service: Relational → Taxonomy → service), or reimport a known-good definition after reviewing its other changes. Existing field definitions have already lost their source metadata; updating alone cannot infer it safely. Saved relationship values are untouched.

## 2.5.30

### Fixed
- Protect relational result and selection labels on the active Select2 instance when a theme destroys/recreates the control after CFF initialization. This handles the Egghead `admin-capabilities-child.js` integration that replaces the Service and dependent Work AJAX dropdowns.
- Preserve external AJAX providers, request parameters and pagination when decorating labels or refreshing CFF fields. Normalize entity labels from the integration's own results and keep labels as escaped text.

### Validation
- Add a real-browser regression loading the actual Egghead integration after CFF, checking label rendering, relationship IDs, service-filtered Work results, language changes, dependent field clearing and provider preservation.
- Optional integration command: `composer test:relational-theme-select2`. Set `CFFP_THEME_SCRIPT` and `CFFP_JQUERY` to override the local theme/jQuery paths; the test uses mocked AJAX and never loads a site database.

## 2.5.29

### Fixed
- Decode HTML entities once in relational AJAX labels and escape decoded labels when rendering saved options, checkboxes and radio inputs. `F&amp;B Branding` now displays as `F&B Branding`.
- Preserve relationship IDs and default Select2 text escaping; decoded markup is rendered as text.
- Give relational dropdowns their own text-only result/selection templates and per-field label cache so global Select2 templates or mutated option text do not replace the selected label. Decode legacy AJAX entity labels in the browser as well.

### Validation
- Add isolated label regressions for post/page/custom post type/taxonomy/user relationships, single/multiple controls, named/numeric entities, UTF-8 labels, encoded markup and AJAX access checks.
- Add a real Select2 browser fixture covering selection, changed cached labels, multiple values, cloned fields, clearing and form submission. Run `composer test:relational-select2` with Puppeteer and a local jQuery file (`CFFP_JQUERY` can override its path).

## 2.5.28

### Fixed
- Switch metabox builder/reorder visibility through the `is-reorder` class so field containers retain their flex layout without inline display overrides.
- Read view checkbox state consistently in both editor scripts.
- Initialize relational AJAX Select2 fields on Add Row, Add Layout and scoped refresh, including a select passed as the root element.
- Isolate copied Select2 state so clones do not share or destroy the source instance; upgrade static relational selects to AJAX when configuration is available.
- Render relational fields inside flexible layouts with their source, cardinality, saved values and option labels.
- Load the Select2 initializer before the content editor script.

### Validation
- Verify Add Row, Clone, Add Layout with rendered PHP markup, root-select refresh and static-to-AJAX upgrade in a browser using simulated AJAX responses.
- Add eight rendering regressions; all 595 standalone checks/assertions and PHP/JavaScript syntax checks pass.

## 2.5.27

### Fixed
- Require the CFF term form nonce and term-specific edit permission; preserve omitted term metadata during quick edit, REST and partial saves.
- Slash sanitized post/term metadata before passing it to WordPress so nested text, JSON, definitions, imports, duplicates and translation copies retain backslashes.
- Format image/file values by their field definitions, including groups, repeaters and flexible layouts, while preserving numeric text, numbers, relational IDs and gallery IDs.
- Keep terms without reorder metadata in results, including paginated lists and count queries, without duplicating terms with legacy duplicate metadata.
- Declare single relational REST values as integers and multiple relational values as arrays of positive integers.
- Invalidate frontend metadata, aliases, field definitions and location caches through WordPress metadata and post-cache hooks.

### Performance
- Initialize raw repeater rows once per loop and format only requested subfields; retain raw reads, nested parent context and repeated loops.
- Reduce updater request timeout to five seconds, memoize checks within a request, and cache failures for five minutes before retrying.

### Validation
- Add standalone runtime integrity regressions for the fixes, execute generated ordering SQL against SQLite fixtures, and include the suite in CI and release checks.

## 2.5.26

### Added
- Added Hide from frontend options for dynamic post types and taxonomies while retaining admin management and independent REST API settings.
- Added searchable content lists, Public / Admin only filters, direct content actions, and unsaved-change feedback.

### Changed
- Reworked the content overview and Post Types / Taxonomies screens with clearer navigation, responsive rows, grouped settings, contextual visibility guidance, and a sticky save bar.
- Keep hidden structures available in taxonomy fields, relationships, reorder screens, and JSON export/import.

### Fixed
- Preserve saved URL slugs when editing labels, generate valid content keys from names, and retain taxonomy assignments when saving post type settings.
- Refresh rewrite rules after updated definitions are registered and prevent hidden frontend routes, including translated routes, from returning public content.

### Validation
- Added 54 standalone frontend visibility checks and verified management interactions at desktop, tablet, and mobile widths.

## 2.5.25

### Fixed
- Fix a fatal error ("Call to undefined function") on post edit screens with Polylang active: the 2.5.24 Save + Copy All dropdown called a `render.php` helper before that file was lazily loaded.

## 2.5.24

### Added
- Add a target-language dropdown next to Save + Copy All CFF to Translations when the post has more than one translation; "All translations" keeps the previous behavior.
- Add the same dropdown to per-field Save + Copy to Translations actions, including repeater, group and flexible-layout subfields.

### Changed
- Share the translation-target lookup between the metabox and per-field actions, and hide the dropdowns with the existing copy-to-translations hide option.
- Tidy the CFF metabox layout with spacing between field cards, flush field headers and a single-row Field View toolbar.
- Give CFF inputs, selects and textareas a consistent 36px height, padding and full width; improve color, radio and checkbox layouts and the mobile Field View toolbar.

## 2.5.23

### Changed
- Show the Language Clone submenu only when Polylang APIs are available and at least two languages are configured, including languages with no content yet.

## 2.5.22

### Added
- Add an enabled-by-default checkbox to fill empty selected data in existing Polylang translations. Uncheck it to retain skip-only behavior.
- Fill missing body/excerpt/title, selected metadata and CFF values, featured images, empty taxonomy assignments and missing hierarchy without duplicating target pages.
- Detect empty CFF group, repeater and flexible-layout skeletons while preserving populated field containers and intentional numeric/checkbox values.
- Preview and count existing translations to fill separately from newly created translations, and include them in reference finalization and result links.

### Fixed
- Require edit permission on existing targets and preserve their IDs, slugs, language links, status, dates and populated values.
- Suspend Polylang core-field synchronization during existing content updates and limit CFF reference remapping to fields copied by the current job.
- Roll back touched empty meta on failure and never delete an existing translation during failure cleanup.

## 2.5.21

### Changed
- Language Clone preserves each source post's status (publish, draft, pending, private or future) and local/GMT publication dates, including its scheduled publication time.
- Require the post type's publishing capability before copying publish, private or future content. Existing translations continue to be skipped.
- Update clone summaries and documentation to describe the preserved status instead of draft-only output.

### Tests
- Verify status and publication dates across batches and finalization for every supported status, plus publishing permission checks.

## 2.5.20

### Added
- Added Custom Fields > Language Clone for one-time copies of all content in selected Polylang post types from a source language to a target language.
- Added independent checkboxes for titles, main content, excerpts, CFF values and section settings, other custom meta, featured images, attached taxonomy terms and term fields, and page hierarchy.
- Added a read-only preview, user-scoped jobs, serialized batch processing, progress, item errors, and retry support. New translations are drafts and existing translations are skipped.
- Added standalone regression coverage for language validation, checkbox isolation, nested field values, taxonomy copies, parent mapping, translation links, permissions, job locking and failure cleanup.

### Fixed
- Match page/post-specific field group rules against Polylang translations so cloned values remain editable without duplicating field definitions.
- Preserve quotes, backslashes, repeated meta rows and row IDs while copying; resolve nested CFF relations and internal links after all drafts have been created.

## 2.5.19

### Changed
- Explicitly set section metadata to `flex-direction: row` in admin and content editor styles.
- Set WordPress secondary button minimum height to 30px, including field view controls.

## 2.5.18

### Added
- Added Tool Kits-style release tooling: source synchronization, metadata checks, ZIP validation, packaged readme.txt, CI packaging checks, and GitHub releases on version tags.

### Changed
- Aligned section titles, badges, hide switches, and accordion controls in the content editor, with fallback titles for empty labels and responsive layouts.
- Reduced header spacing, normalized field-card gaps, and added keyboard focus indicators for section controls.

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
