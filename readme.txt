=== Custom Fields Framework Pro ===
Contributors: ekods
Stable tag: 2.5.25

Custom fields with Field Groups, Repeater, Flexible Content, Location rules, and CFF-compatible frontend helpers.

== Description ==

Build structured WordPress content with custom fields, nested repeaters, groups, flexible layouts, custom post types, taxonomies, global settings, import/export, and frontend helpers.

== Installation ==

1. Upload custom-fields-framework-pro.zip through Plugins > Add New > Upload Plugin.
2. Activate Custom Fields Framework Pro.
3. Open Custom Fields and create a Field Group with location rules.

== Changelog ==

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
