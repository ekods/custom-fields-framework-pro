# JSON content import

Open a saved page's editor, then **Import Content → Import JSON into this page**. Save editor changes before opening the importer. Alternatively, choose an existing page through **Custom Fields → Import Content**. The destination is fixed in the URL (`admin.php?page=cff-import-content&post_id=123`) and checked on every request. This workflow updates only that page's CFF values; it does not change its title, URL, template, publication status, language or other translations.

Choose **Fill empty fields only** (default) or **Replace supplied fields**, upload one JSON object and review the preview before starting. Download example JSON to get the actual registered field names for that page. Configuration must already exist: **Export / Import** handles field groups/post types/taxonomies.

Raw section files such as `why-egghead-en.json` are accepted directly:

```json
{
  "hero": {"title": "Your heading", "intro": "Introductory copy"},
  "questions": {"items": [{"question": "Why?", "answer": "Because..."}]},
  "contact": {"contact_form": "[contact-form-7 id=\"5\"]"}
}
```

Section names match exact CFF names first, then unique suffixes: `hero` can map to `why_hero`, and `questions` to `why_questions`. Preview lists each mapping. Unknown sections, ambiguous suffixes, duplicate mappings and unknown subfields are rejected. Full CFF names and an optional single `{"cff": {...}}` wrapper are supported. Arrays of posts, core post properties and configuration packages are not page content files. Fields are resolved using the destination's **saved** template; save a changed template in the editor first.

**Fill empty** fills missing, null, empty or whitespace-only values inside groups. Numeric zero and explicit false/unchecked checkboxes count as existing content. Empty media IDs (`0`) can be filled. A populated repeater/gallery/flexible/multiple-value list is preserved as a whole; rows are not guessed or appended. An empty list can be filled from the file.

**Replace** overwrites only supplied fields/subfields. Omitted values remain. Supplied repeaters/gallery/flexible lists replace their existing lists. Null/empty optional values clear according to CFF sanitization; an explicitly empty group clears that group. No unrelated field group is erased.

Image/file paths such as `images/example.jpg` or media URLs are matched to a unique accessible attachment in the existing Media Library by URL or exact filename. The importer does not download media or change attachment metadata. Unresolved/ambiguous references appear as preview notes; existing media at the same field/row position is retained, or the absent media value is omitted if none exists. For replacement repeater/flexible rows this fallback uses **row position**, not text or a cross-site ID map. Upload media first or use an existing numeric media ID if the filename is ambiguous. Gallery and relationship fields use existing numeric IDs.

Preview does not modify content. Starting writes one existing page; the same permission, schema, destination and resolved-value checks run again. A changed media match requires another preview. Reports, cancellation and interrupted-response handling remain available. Jobs are isolated by site, user and destination page, and expire after 24 hours. Page import never edits the JSON source file or theme configuration.

## Existing bulk format (advanced compatibility)

The original native content envelope remains available at `admin.php?page=cff-import-content&bulk=1`. The following applies only to that advanced endpoint, not the default one-page workflow.

1. Download the example JSON or prepare the structure below.
2. Choose the target post type and mode. Create-only skips existing content; update-only requires a match; create-or-update permits both. Explicit IDs must exist in the chosen type. Otherwise `post_name` matches the slug (derived from `post_title` when omitted). Ambiguous matches require an ID.
3. Upload and review the validation preview. Preview only saves temporary progress; it does not change posts, metadata, terms or languages.
4. Start valid items. Invalid items are excluded and remain in the report. Keep the page open for batches; pause or cancel after the current batch. Completed changes are retained.
5. Download the report and review failed items. After a dropped response, check saved progress before continuing. An item interrupted during writing is marked for review and never replayed automatically.

```json
{
  "format": "cff-content",
  "version": 1,
  "items": [
    {
      "post_type": "page",
      "post_title": "Services",
      "post_name": "services",
      "post_content": "<p>What we do.</p>",
      "post_status": "draft",
      "cff": {
        "heading": "F&B Branding",
        "intro": {"title": "Hello", "description": "Our services"},
        "services": [{"title": "Strategy"}],
        "sections": [{"layout": "hero", "fields": {"title": "Hello"}}]
      }
    }
  ]
}
```

Replace every CFF key, subfield and layout with names from your actual field groups. A plain array of content objects, or a single content object, is also accepted. CSV and WordPress WXR/XML are not supported. Files must be UTF-8 JSON, at most 5 MB / 500 items.

Supported content keys: `ID`, `post_type`, `post_title`, `post_name`, `post_content`, `post_excerpt`, `post_status`, `post_parent`, `menu_order`, `page_template`, `language`, `featured_image`, `cff`, `taxonomies`. Unknown keys and unknown CFF fields are rejected rather than silently discarded.

New posts require a title and default to draft. Existing posts retain their status when it is omitted. Explicit `publish` or `private` requires **Allow publishing/private status requested by JSON** and publishing permission. Draft and pending statuses are accepted; scheduled publication dates are not imported.

Updates preserve omitted core fields, metadata and taxonomy assignments. Supplied group objects merge with existing values, while repeater, gallery, flexible and multiple-choice/relationship arrays replace the supplied field. Null or empty inputs clear optional values according to the normal CFF sanitizer. Zero and false checkbox values are retained. New required fields and required subfields in supplied replacement rows must be present. Core HTML and rich text use WordPress content sanitization.

Media/image/file/featured image values reference **existing media IDs on this site**, gallery values are arrays of IDs. Relationships reference existing IDs in their configured taxonomy, post type or user source. Taxonomy assignments use `"taxonomies": {"service": [149]}` with existing term IDs from a taxonomy enabled for the destination post type. No remote media downloads, new term creation or cross-site ID remapping are performed.

`page_template` must be a template path registered by the current theme for that post type; it is used during preview to match field groups. `language` is an optional existing Polylang language slug. Existing content must already have that language. Imports do not create translation links, move translations or copy changes to other languages.

The importer rechecks permissions, CFF definitions and destination snapshots before writing. Known per-item failures restore mapped core fields, metadata and terms, or remove a newly created draft. Rollback cannot undo third-party hook side effects such as external integrations. Incomplete rollback and interrupted writes are flagged for manual review. This is not an all-file database transaction.

Jobs belong to the current user and site. Payloads are removed after completion/cancellation, and saved reports expire 24 hours after upload. WordPress cron cleans expired jobs; accessing an expired job also removes it. Uninstall removes temporary import data even when persistent CFF content is retained.

Development checks: `composer test:content-import`, plus `composer test:content-import-ui` with Puppeteer installed in a parent `node_modules`. All fixtures are isolated; neither test writes to a WordPress database.
