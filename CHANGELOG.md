# Changelog

All notable changes to this project will be documented in this file.

## 2.3

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
