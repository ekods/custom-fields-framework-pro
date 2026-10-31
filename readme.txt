=== Custom Fields Framework Pro ===
Contributors: ekods
Stable tag: 2.5.41

Custom fields with Field Groups, Repeater, Flexible Content, Location rules, and CFF-compatible frontend helpers.

== Description ==

Build structured WordPress content with custom fields, nested repeaters, groups, flexible layouts, custom post types, taxonomies, global settings, import/export, and frontend helpers.

== Installation ==

1. Upload custom-fields-framework-pro.zip through Plugins > Add New > Upload Plugin.
2. Activate Custom Fields Framework Pro.
3. Open Custom Fields and create a Field Group with location rules.

== Changelog ==

= 2.5.41 =
* Post Types and Taxonomies: click a name in the table to edit its settings.
* Post Types: remove the "Manage content" and "Add new" icons from the table; Edit, Duplicate and Delete remain.

= 2.5.40 =
* Includes the updates from 2.5.33–2.5.39: import one page's JSON content with preview, section mapping, replace/fill-empty modes, media matching and reports.
* Add Global Settings > Hide Section Controls while preserving saved section visibility.
* Align CFF screens to 1440px, tidy branding/tables and provide dedicated Actions columns with direct icon controls.
* Make repeater controls borderless and allow clicking row headers to collapse/expand without interfering with clone/remove/drag actions.
* Keep PHP and shortcode examples inside the Guide & Usage sidebar with shared constrained-width code styling and horizontal scrolling for long lines.
* Reset nested code padding/background so examples no longer overlap adjacent content and remain copyable without changing their text.

= 2.5.39 =
* Add a dedicated Actions column to Field Groups and move WordPress-generated icon actions out of the title cell while preserving links, permissions and nonce parameters.
* Keep Field Group actions visible on mobile, handle refreshed rows, and restore actions below the title when their column is hidden in Screen Options or JavaScript is unavailable.
* Consolidate Post Type content/settings controls into one Actions column and align Actions columns across Overview and Taxonomies.
* Refine column widths, row alignment, cell spacing, separators and mobile action wrapping without changing repeater editor controls.

= 2.5.38 =
* Allow clicking a collapsible repeater row header or title to collapse/expand its body, including nested and newly added rows.
* Keep arrow, clone/remove, interactive controls and drag behavior independent; ignore the click following a sort operation.
* Synchronize the arrow button's accessible expanded state with the row, retaining native keyboard activation.

= 2.5.37 =
* Remove borders, backgrounds and shadows from repeater drag/toggle/clone/remove controls and the flexible layout remove control, including hover states. Keep visible keyboard focus.
* Remove margin-right: auto from the field copy indicator so it stays next to the copy controls.

= 2.5.36 =
* Apply a shared 1440px maximum width to CFF wrappers and the native Field Groups list so branding, navigation and table edges align.
* Consolidate conflicting header branding styles, wrap brand/version/help cleanly, and mark Field Groups active in its navigation.
* Refine table spacing, column sizing, key/location wrapping and mobile primary-column labels/checkbox alignment.
* Replace More menus with direct edit, duplicate and delete icon buttons. Content/term actions and native Field Group row actions also use labelled icon buttons; nonce URLs and delete confirmation are preserved.

= 2.5.35 =
* Add Hide Section Controls under Global Settings > Editor UI Settings to hide the Hide Section switches in CFF content editors.
* Preserve saved section visibility and language-local markers while controls are hidden, including saves from previously opened editors. Turning the option off restores the controls with their saved state.

= 2.5.34 =
* Make Import Content a one-page workflow: open it from the current editor or choose an existing page from the menu. The destination stays fixed throughout preview and import.
* Accept raw section JSON such as hero/questions/contact, matching exact CFF field names or unique suffixes such as hero to why_hero. Preview shows the mappings.
* Add Replace supplied fields and Fill empty fields only. Fill empty merges blank group subfields while preserving existing text, zero/false values and populated repeaters.
* Match image/file paths to existing Media Library files. Unmatched/ambiguous images are reported and prior media at the same field/row position is retained; no downloads or attachment metadata changes.
* Keep title, URL, status, saved template and other translations unchanged. Isolate jobs per user and destination page, and require a new preview if resolved values or media matches change.
* Add regressions using the exact Why EGGHEAD section JSON and field definitions, plus browser checks for editor context, page selection, policies and mapping/media notes.

= 2.5.33 =
* Add Custom Fields > Import Content for JSON content files, separate from configuration import.
* Preview validation before importing posts, pages and custom post types; create-only, update-only and create-or-update modes match destination ID or slug.
* Import CFF values including nested groups, repeaters and flexible layouts, existing media/relationship IDs, taxonomy assignments, page templates and optional Polylang language.
* Process up to five items per batch with pause, resume, cancellation and a downloadable report. New content defaults to draft; publishing from JSON requires explicit opt-in and permission.
* Recheck permissions, field definitions and destination changes before writing. Roll back failed items and stop for review after an interrupted write instead of automatically repeating it.
* Limit JSON to 5 MB / 500 items, scope saved progress to the current user and expire temporary payloads/reports after 24 hours.

= 2.5.32 =
* Tidy the content editor field rendering: labelled URL/Title inputs for link fields, Select2 relational controls matching other inputs, contained media previews with a clear empty state, and labelled gallery buttons.
* Align repeater and flexible rows: consistent spacing, an icon remove button and layout badge beside the title for flexible rows, and dashed Add Row / Add Layout areas.
* Stack link, media and flexible controls when a field group sits in the narrow sidebar metabox.
* Size table-layout repeater columns by field type and scroll horizontally instead of squeezing image, editor and nested fields.
* Render sub fields through one shared renderer. Nested repeaters inside flexible layouts now render as repeaters instead of a text input, nested table repeaters keep their row template, and flexible layouts use the same editor toolbar and image validation as other containers.
* Fix an undefined variable warning for gallery fields inside flexible layouts.

= 2.5.31 =
* Preserve top-level relational source type, subtype, taxonomy editor post type, display mode and multiple setting when importing native CFF field groups. Taxonomy relationships no longer silently become Post relationships.
* Add an import-to-render regression with overlapping post/term ID 149 to ensure Service uses the taxonomy label instead of an unrelated post title.
* Existing affected imports need their relationship source corrected once in the Field Group editor, or reimported from a known-good definition. Updating the plugin does not infer missing configuration or alter saved relationship IDs.

= 2.5.30 =
* Keep relational labels readable when themes destroy and recreate CFF Select2 controls after initialization, including the Egghead Service and dependent Work dropdowns.
* Normalize results and render selected labels as text on the current Select2 instance while preserving the theme's AJAX endpoint, service/language filters, pagination and transport.
* Preserve external AJAX providers during CFF refreshes instead of replacing them with the default CFF lookup.
* Add a browser regression using the actual Egghead capabilities integration, covering selected labels, dependent fields, language changes and submitted relationship IDs.

= 2.5.29 =
* Display HTML entities in relational labels as their characters, including F&amp;B Branding as F&B Branding, in AJAX dropdown results and saved selections.
* Apply the same label handling to post, page, custom post type, taxonomy and user relationships, including multiple selects, checkboxes and radio inputs.
* Keep HTML escaping when rendering labels and preserve stored relationship IDs.
* Keep selected relational labels stable with field-local Select2 text renderers and label caches, including legacy entity labels, multiple selections and cloned rows.

= 2.5.28 =
* Toggle metabox builder/reorder visibility through classes so field containers retain their flex layout without inline display overrides.
* Read the view checkbox state consistently in both editor scripts.
* Initialize relational AJAX Select2 fields immediately after Add Row and Add Layout, including scoped refresh on a select element itself.
* Keep cloned Select2 state independent from the source field and upgrade static relational selects to AJAX when configuration is available.
* Render single and multiple relational fields in flexible layouts with the correct AJAX source, saved values, and option labels.
* Ensure the Select2 initializer loads before the content editor script.
* Add regressions for relational rendering in saved and template flexible rows.

= 2.5.27 =
* Preserve taxonomy images and descriptions during quick edit, REST, and partial saves with a dedicated nonce and term-specific permission checks.
* Preserve backslashes and quoted content in classic/REST field saves, field definitions, imports, duplication, and translation copies.
* Format media using field definitions so numbers, numeric text, relational IDs, and gallery IDs retain their types, including nested fields.
* Keep newly created terms without saved reorder metadata visible and preserve counts and pagination.
* Correct REST schemas for single and multiple relational fields.
* Invalidate request caches after metadata, field definition, and post status changes.
* Load repeater loop data once, format requested subfields lazily, and preserve raw reads and nested loop context.
* Limit updater requests to five seconds and cache failed checks for five minutes before retrying.
* Add runtime integrity regressions to CI and release checks.

= 2.5.26 =
* Add Hide from frontend for post types and taxonomies while retaining admin management and independent REST API settings.
* Disable public single/archive pages, search visibility, navigation choices, and WordPress sitemap entries for hidden content structures.
* Rework the content overview with direct Manage content and Add new actions, plus clearer navigation.
* Add searchable Post Types and Taxonomies lists with Public / Admin only filters and responsive mobile rows.
* Group definition settings into basic details, content editor or relationships, frontend visibility, and integrations.
* Add a sticky save bar, contextual visibility guidance, and unsaved-change feedback.
* Preserve saved URL slugs when labels change and retain taxonomy assignments when saving post type settings.
* Keep hidden content structures available in term fields, relationship selectors, reorder screens, and JSON export/import.
* Refresh rewrite rules after changed definitions are registered and prevent hidden Polylang routes from being recreated.

= 2.5.25 =
* Fix a fatal error on post edit screens with Polylang active, caused by the 2.5.24 translation dropdown running before its helper file was loaded.

= 2.5.24 =
* Add a target-language dropdown to Save + Copy All CFF to Translations when a post has more than one translation.
* Add the same target-language dropdown to per-field Save + Copy to Translations actions, including repeater, group and layout subfields.
* Tidy the CFF metabox layout: spacing between field cards, flush field headers and a single-row Field View toolbar.
* Give CFF inputs, selects and textareas a consistent height, padding and full width; improve color, radio and checkbox layouts.

= 2.5.23 =
* Add Language Clone to copy selected post types and data from a Polylang source language into a target language.
* Choose title, content, excerpt, CFF fields, other meta, featured image, taxonomy terms and hierarchy independently.
* Preserve source publication status and dates for new translations, including scheduled publication.
* Fill empty selected data in existing translations while preserving populated values, page identity and publication status.
* Preview and process copies in batches with progress, error reporting and translated parent/CFF reference resolution.
* Show Language Clone only when Polylang is active and at least two languages are configured.

= 2.5.22 =
* Add an enabled-by-default option to fill empty selected data in existing translations without creating duplicate pages.
* Preserve existing populated values, page identity and publication status; support empty CFF groups and layout/repeater skeletons.
* Preview and report existing translations to fill separately from newly created translations.
* Check target edit permissions and restore touched empty metadata if filling fails, without deleting existing pages.

= 2.5.21 =
* Preserve the source publication status and publication dates when cloning translations, including scheduled publication.
* Require publishing permission for published, private and scheduled copies, and update Language Clone messages accordingly.

= 2.5.20 =
* Add Language Clone for copying all selected post types from a Polylang source language into a target language.
* Choose title, content, excerpt, CFF fields, other meta, featured image, taxonomy terms and hierarchy independently.
* Preview and process copies in batches, preserve existing translations, and create new translations as drafts.
* Resolve translated parent pages and nested CFF references, and match page-specific field groups across translations.

= 2.5.19 =
* Explicitly arrange section metadata with flex-direction: row.
* Set secondary button min-height to 30px, including field view controls.

= 2.5.18 =
* Align section titles and badges on the left, with hide switches and accordion controls on the right.
* Reduce header spacing, add fallback titles for empty labels, and improve mobile layouts and keyboard focus.
* Add source synchronization, version checks, validated ZIP builds, and GitHub release automation.

= 2.5.17 =
* Include bundled Select2 assets in release packages.

== Upgrade Notice ==

= 2.5.28 =
Fixes metabox flex layout when switching views and relational AJAX dropdowns in dynamically added rows and flexible layouts.

= 2.5.27 =
Fixes metadata loss, backslash preservation, field formatting, REST schemas and taxonomy ordering; reduces repeater and updater overhead. Values already lost before this update require recovery from a backup.

= 2.5.26 =
Adds admin-only frontend visibility and a clearer content management interface. Existing frontend settings stay unchanged until Hide from frontend is enabled.

= 2.5.23 =
Language Clone is hidden when bilingual support is inactive or fewer than two languages are configured.

= 2.5.22 =
Language Clone can fill empty data in existing translated pages. Disable the fill-existing checkbox to retain skip-only behavior.

= 2.5.21 =
Language Clone now keeps the source status and publication dates. Published sources create published translations.

= 2.5.20 =
Adds Custom Fields > Language Clone for selective one-time copies between Polylang languages.

= 2.5.19 =
Uses horizontal section metadata and a 30px minimum height for secondary buttons.

= 2.5.18 =
Improves section headers in the content editor and adds validated release packaging.

= 2.5.17 =
Includes the Select2 JavaScript and stylesheet required by the field builder.
