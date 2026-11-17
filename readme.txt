=== Custom Fields Framework Pro ===
Contributors: ekods
Stable tag: 2.5.55

Custom fields with Field Groups, Repeater, Flexible Content, Location rules, and CFF-compatible frontend helpers.

== Description ==

Build structured WordPress content with custom fields, nested repeaters, groups, flexible layouts, custom post types, taxonomies, global settings, import/export, and frontend helpers.

== Installation ==

1. Upload custom-fields-framework-pro.zip through Plugins > Add New > Upload Plugin.
2. Activate Custom Fields Framework Pro.
3. Open Custom Fields and create a Field Group with location rules.

== Changelog ==

= 2.5.55 =
* Rework the field builder into a table of fields (# / Label / Name / Type) that open into General, Validation, Presentation and Conditional Logic tabs, with Close Field and Close and Add Field actions; sub fields use the same list.
* Add Instructions, Default Value and Wrapper Width to every field; Label Placement Left and Instruction Placement now apply in the editor.
* Validate fields on the server before publishing (required, email, URL, number, range bounds, repeater min/max), with messages on each field; fields hidden by conditional logic are skipped.
* Add Local JSON: field groups are saved to the theme's cff-json folder when it exists, with Sync / Sync all on the Field Groups screen and a status card on Export / Import.
* Add Email, Range, Time Picker, Message, Tab and Accordion field types; ACF migration carries these types and common field settings.
* Flexible Content: an Add Row menu lists the layouts; layout names are generated from the label so layouts are no longer dropped on save.
* Field Groups list shows Description, Key, Location and Fields like ACF; field groups get a Description input that is exported and imported.
* Rework the Overview dashboard with Build and Tools links, remove the section navigation, use Tool Kits page headings, and turn Reorder into accordions with one panel per post type and coloured status badges.
* Imports and syncs keep required, placeholder, choices and media settings; number sub fields render as number inputs; round media select/clear buttons.

= 2.5.54 =
* Replace the Revisions popup's Close text button with a compact X icon and accessible close label.
* Constrain the revision list height and support wheel, touch and keyboard scrolling while keeping the popup header, X and footer visible.

= 2.5.53 =
* Open a Revisions popup from CFF Revision History with newest-first rows showing avatar, user, relative time and linked full local date/time, scoped to the editor's content ID.
* Date links open the comparison page with that exact To revision selected. Keep Compare revisions available for the general comparison view; invalid/expired revision links do not silently select another version.
* Support keyboard focus, Escape/Close, scrollable mobile lists, loading/empty/error states and retry. Preserve unsaved-editor guards and per-ID permissions.

= 2.5.52 =
* Reserve a dedicated gutter for diff minus/plus markers so they cannot overlap the first character or wrapped text when WordPress revisions.css is loaded.
* Align native and fallback markers with the first text line using consistent text symbols, including mobile layouts.

= 2.5.51 =
* Restore one or several selected root fields/sections from the To revision, using the field/section action or the checkbox selector. Keep unselected values and field order unchanged.
* Restore groups, repeaters and flexible layouts intact with their own Hide Section state; keep legacy media URL companions together. Field order and complete section visibility are separately selectable.
* Preserve full recovery snapshots, permission/lock/stale checks, rollback and per-ID isolation for partial restores. Record the restored field scope in revision history and reject empty or invalid selections.
* Filter the current content ID's history by user and inclusive site-local date range; update both version selectors and timeline marks to match, with no-results feedback and Reset.

= 2.5.50 =
* Match the WordPress revision timeline with a white segmented track, circular two-arrow handles and an author/time speech bubble above the selected revision.
* Keep tooltip arrows aligned to their handles at the start, middle and end of the timeline, and constrain bubbles within desktop/mobile layouts.
* Retain native slider semantics, keyboard navigation, pointer dragging, independent From/To controls and per-content revision behavior.

= 2.5.49 =
* Tidy the revision comparison header into equal From/To columns with aligned full-width version selectors, readable author/date details and shorter dropdown labels.
* Put the restore action and explanation in a separate footer, stack version details on small screens and constrain the reading width on large screens.
* Tighten field headings, diff row padding and line spacing; remove row gutters while preserving all whitespace and content differences.

= 2.5.48 =
* Add CFF revision history scoped to each post, page or CPT ID, including nested values, field order and Hide Section; keep the latest 20 versions independently per ID.
* Open a dedicated comparison page from the content editor with a revision timeline, Previous/Next, compare-any-two controls, From/To author details, exact local timestamps and relative time.
* Use WordPress's native split diff renderer with red/green line and word highlights. Display saved content markup as escaped text.
* Record the editing user and capture their display name for each saved/restored version. Identify initial recovery snapshots as captures with unknown original author/change time.
* Restore the To revision only to its original content ID, preserve a recovery point, enforce edit permissions and stale-value/editor-lock checks, and roll back failed field writes.
* Keep history out of duplication and Polylang copies; prevent restores from synchronizing CFF values to other translations. Protect unsaved classic and Gutenberg metabox changes before opening history.

= 2.5.47 =
- Repeater rows show a title taken from the first filled text field when no Row Label Field is set, and the title follows typing.
- Repeaters gain a row count with Collapse all / Expand all, and say why Add, Duplicate or Remove is disabled at a minimum or maximum.
- Update opens a collapsed repeater row (or closed box) and focuses the empty required field instead of failing silently; rows needing attention are marked.
- Import Content is reached from Custom Fields → Import Content with the destination page fixed in the URL; the side box in the page editor is removed.
- All buttons use WordPress's standard button styles; only layout rules remain.

= 2.5.46 =
- Page headings use a new header: a blue-to-mint background, an accent edge and a filled icon tile, matching Tool Kits.
- Buttons in the page heading are WordPress's standard buttons; their colors follow the accent color.

= 2.5.45 =
- Add a Custom Fields icon to the admin menu, page headings and the content overview, replacing the generic feedback icon.
- In the mobile section navigation, scroll the current section into view without moving the page.
- Avoid redundant text updates in the Post Types and Taxonomies editors, and refine admin, management, content import and post editor styling.

= 2.5.44 =
- Give Post Types and Taxonomies dedicated add/edit views using the WordPress post.php layout while retaining their CFF page URLs.
- Put Save definition controls in the right sidebar and use consistent postbox headers, panel spacing and full-width columns; keep list tables out of the editor workspace.
- Refresh CFF navigation, content inventory, field hierarchy and editor controls with blue accents and quieter surfaces.
- Preserve form fields, nonces, translation settings, connected post types, frontend visibility and independent REST settings. Legacy add links remain supported.
- Stack editor columns cleanly on smaller screens and retain native WordPress list-table controls and visible keyboard focus.

Validation: 119 real WordPress add/edit, create/update/cancel, layout and compatibility checks at five viewport widths; 388 runtime-integrity checks; native-table and isolated WordPress builder/save checks.

= 2.5.43 =
- Use native WordPress list tables for content overview, post types and taxonomies, with server-side search, visibility filters, sorting and pagination; restore native Field Groups row actions.
- Align field, repeater, subfield, editor and content-field accents with the teal admin palette.
- Group Conditional Logic and Hide Section inside Advanced options; keep Required visible and saved advanced settings expanded.
- Fix narrow-screen repeater input widths; preserve nested fields and saved field values.
- Distinguish destructive confirmations and import success, warning and failure states.


= 2.5.42 =
- Refresh admin navigation, headers, cards and editor accents with teal and mint surfaces.
- Use WP_List_Table for content overview, post types and taxonomies, with server-side search, visibility filters, sorting and pagination.
- Restore native Field Groups row actions and table styling; preserve nonce-protected definition actions and responsive row details.


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
