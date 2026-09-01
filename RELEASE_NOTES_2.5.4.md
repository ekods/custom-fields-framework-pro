# Custom Fields Framework Pro 2.5.4

## Highlights
- Added `Parameter` and `Hash` inputs for internal Button/Link fields.
- Fixed internal Button/Link values reverting to custom mode after saving.
- Updated the release package to exclude development-only dependencies.

## New
- Internal Button/Link fields now support separate query parameter and hash values.
- Parameter input accepts values such as `utm_source=site`.
- Hash input accepts values such as `section-id`.
- Internal link URLs are generated as `permalink?parameter#hash` while keeping `parameter` and `hash` stored separately.

## Fixes
- Preserved internal link metadata during save, including `mode`, `internal_id`, and `post_type_filter`.
- Prevented internal Button/Link fields from reloading as custom links after save.
- Kept REST field schema aligned with the expanded Button/Link value shape.

## Packaging
- Bumped plugin version to `2.5.4`.
- Rebuilt `custom-fields-framework-pro.zip`.
- Excluded `vendor/` from release zip because it only contains development dependencies.

## Validation
- PHP syntax checks passed for changed PHP files.
- JavaScript syntax check passed for `assets/post.js`.
- `FieldSanitizerTest` passed: 8 tests, 18 assertions.
