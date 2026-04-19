# Custom Fields Framework Pro 2.3

## New
- Added `Repeater Layout: Table (fill values inline)`.
- Added true table-based repeater rendering with `table`, `thead`, `tbody`, and `tr`.
- Added delete confirmation for repeater and flexible rows.
- Added `File Library` filtering for `file` fields with granular file type choices.
- Added file type info to the media limit label.
- Added shortcode documentation for use in frontend PHP via `do_shortcode()`.
- Added uninstall data cleanup flow and `Keep data on uninstall` setting.
- Added PHP lint workflow for automated validation.

## Improvements
- Refined admin UI and frontend field rendering.
- Updated repeater `row` layout to wrap into two columns.
- Moved media action buttons into the media preview area.
- Preserved media action buttons after media selection.
- Refactored internal plugin structure for tools page and REST field handling.
- Added GitHub updater response caching.
- Expanded documentation in `CFF Documentation`.

## Fixes
- Fixed text domain and translation loading setup.
- Fixed updater boot timing.
- Fixed AJAX capability validation.
- Removed debug logs from admin scripts.
- Fixed repeater table layout fallback from `table` to `default`.
- Fixed `Add Row` in repeater table layout.
- Fixed `tbody` table layout rendering issue.
- Fixed action column ordering in repeater table mode.
- Fixed missing delete confirmation on repeater remove actions.
