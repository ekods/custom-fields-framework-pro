<?php
require __DIR__ . '/fixtures/content-revisions-runtime.php';
$checks = 0;
function check($condition, $message) {
  global $checks;
  if (!$condition) throw new RuntimeException($message);
  $checks++;
}
function ajax_request($post_id, $mode, $args = []) {
  global $revisions;
  $_POST = array_merge(['post_id' => $post_id, 'mode' => $mode, 'nonce' => 'valid-cff_content_revisions_' . $post_id], $args);
  try { $revisions->ajax(); } catch (Revision_Test_Response $response) { return $response; }
  throw new RuntimeException('AJAX did not return JSON');
}
revision_fixture_post(123);
revision_fixture_post(456);
revision_fixture_post(789, 'work');
revision_fixture_post(900, 'cff_group');
revision_fixture_post(901, 'cff_options');
revision_fixture_post(902, 'revision');
$original = [
  '_cff_title' => 'Before "quoted" C:\\files\\draft',
  '_cff_group' => ['heading' => '<script>alert("text")</script>', 'zero' => 0, 'off' => false, 'blank' => '', 'empty' => [], 'null' => null],
  '_cff_rows' => [['__cff_row_id' => 'row-a', 'answer' => 'Original', 'nested' => [['label' => 'Nested']]]],
  '_cff_blocks' => [['layout' => 'hero', '__cff_row_id' => 'block-a', 'fields' => ['image' => 42, 'caption' => 'Hero']]],
  '_cff_group_field_order_900' => ['title', 'rows', 'blocks'],
  '_cff_group_field_order_custom_900' => '1',
  '_cff_hidden_sections' => ['title' => true],
  '_cff_hidden_sections_local' => 'en',
];
foreach ($original as $key => $value) update_post_meta(123, $key, wp_slash($value));
update_post_meta(123, '_wp_page_template', 'page-custom.php');
update_post_meta(123, 'seo_title', 'Keep SEO');
update_post_meta(456, '_cff_title', 'Independent translation');
update_post_meta(789, '_cff_title', 'Independent CPT');
$revisions->register();
$before = $revisions->snapshot(123);
check(is_array(get_post_meta(123)['_cff_rows']) && is_string(get_post_meta(123)['_cff_rows'][0]), 'Core all-meta API really returns serialized nested values');
check($before['_cff_rows'][0] === $original['_cff_rows'], 'Snapshot reads decoded nested metadata');
check($revisions->history(123) === [], 'Existing content is not assigned invented history');

update_post_meta(123, '_cff_title', wp_slash('After C:\\new\\path'));
update_post_meta(123, '_cff_group', wp_slash(['heading' => 'Changed', 'zero' => 0, 'off' => false]));
update_post_meta(123, '_cff_rows', wp_slash([['__cff_row_id' => 'row-a', 'answer' => 'Changed', 'nested' => [['label' => 'Changed nested']]]]));
update_post_meta(123, '_cff_blocks', [['layout' => 'hero', 'fields' => ['image' => 84, 'caption' => 'Changed hero']]]);
update_post_meta(123, '_cff_group_field_order_900', ['blocks', 'rows', 'title']);
delete_post_meta(123, '_cff_hidden_sections');
update_post_meta(123, '_cff_new_field', 'Added later');
check($revisions->history(123) === [], 'A multi-field write is not recorded as intermediate partial versions');
$after = $revisions->snapshot(123);
do_action('shutdown');
$history = $revisions->history(123);
check(count($history) === 2, 'First change records original and completed versions');
check($history[0]['snapshot'] === $before && $history[1]['snapshot'] === $after, 'All nested values, section order and hidden state are versioned');
check($history[0]['post_id'] === 123 && $history[1]['user'] === 7, 'Revision records own their post ID and capture actor');
check(!$revisions->history(456) && !$revisions->history(789), 'Other IDs have no revisions from the first post');
update_post_meta(123, '_cff_title', wp_slash('After C:\\new\\path'));
update_post_meta(123, '_cff_title', 'Temporary failed import state');
update_post_meta(123, '_cff_title', wp_slash('After C:\\new\\path'));
$wpdb->fail_key = '_cff_title';
update_post_meta(123, '_cff_title', 'Database failure');
do_action('shutdown');
check(count($revisions->history(123)) === 2, 'No-op, rolled-back and failed saves create no duplicate revisions');

$selected_id = $history[0]['id'];
$comparison = $revisions->compare(123, $selected_id);
$paths = array_column($comparison['changes'], 'field');
check(in_array('rows[0].nested[0].label', $paths, true), 'Comparison resolves nested field paths');
check(in_array('hidden_sections', $paths, true) && in_array('new_field', $paths, true), 'Comparison includes deleted and added field keys');
check(in_array('group.off', $paths, true) === false, 'Unchanged false values do not appear as changes');
check(!array_filter($paths, function($path) { return strpos($path, '__cff_row_id') !== false; }), 'Comparison hides internal row identity changes');
$cross = $revisions->restore(456, $selected_id, $revisions->fingerprint($revisions->snapshot(456)));
check(is_wp_error($cross) && $cross->get_error_code() === 'cff_revision_missing', 'Cannot restore a revision belonging to another content ID');
check(is_wp_error($revisions->compare(456, $selected_id)), 'Cannot compare another ID’s revision');
$GLOBALS['fixture_denied'] = [123];
check(is_wp_error($revisions->restore(123, $selected_id, $comparison['current_hash'])), 'Restore requires edit permission for the selected ID');
check(ajax_request(123, 'history')->status === 403, 'History AJAX also checks per-ID permission');
$GLOBALS['fixture_denied'] = [];
check(ajax_request(123, 'history', ['nonce' => 'valid-cff_content_revisions_456'])->status === 403, 'Nonce is bound to the exact content ID');
check(ajax_request(123, 'history', ['nonce' => []])->status === 403, 'Malformed nonce is rejected without PHP warnings');
check(ajax_request(123, 'history', ['post_id' => []])->status === 403, 'Malformed content ID is rejected');
check(ajax_request(123, 'unknown')->status === 400, 'Unknown action is rejected');
check(ajax_request(123, 'compare', ['revision_id' => $selected_id])->payload['success'], 'Editor can compare via the actual AJAX handler');
check(ajax_request(456, 'compare', ['revision_id' => $selected_id])->status === 404, 'AJAX rejects cross-ID comparisons');
$GLOBALS['fixture_locks'][123] = 8;
check($revisions->restore(123, $selected_id, $comparison['current_hash'])->get_error_code() === 'cff_revision_locked', 'Respect another editor’s content lock');
unset($GLOBALS['fixture_locks'][123]);
update_post_meta(123, '_cff_title', 'Changed since comparison');
check($revisions->restore(123, $selected_id, $comparison['current_hash'])->get_error_code() === 'cff_revision_stale', 'Stale comparison cannot overwrite a subsequent save');
do_action('shutdown');
$current = $revisions->snapshot(123);
$wpdb->fail_key = CFF\Content_Revisions::META_KEY;
// Ensure backup is needed, rather than already represented by the newest revision.
update_post_meta(123, '_cff_title', 'Unsnapshotted current value');
$current = $revisions->snapshot(123);
$failed_backup = $revisions->restore(123, $selected_id, $revisions->fingerprint($current));
check(is_wp_error($failed_backup) && $revisions->snapshot(123) === $current, 'Backup failure aborts restore before modifying content');
$wpdb->fail_key = '_cff_rows';
$failed_restore = $revisions->restore(123, $selected_id, $revisions->fingerprint($current));
check(is_wp_error($failed_restore) && $revisions->snapshot(123) === $current, 'A failed field write rolls back the full original CFF snapshot');
$result = ajax_request(123, 'restore', ['revision_id' => $selected_id, 'current_hash' => $revisions->fingerprint($current)]);
check($result->payload['success'], 'Restore succeeds through the editor AJAX endpoint');
check($revisions->snapshot(123) === $before, 'Restore returns exact nested/scalar values and removes fields absent from the old version');
check(get_post_meta(123, '_cff_title', true) === $original['_cff_title'], 'Quotes and backslashes survive snapshot storage and restore');
check(get_post_meta(123, '_cff_group', true) === $original['_cff_group'], 'Zero, false, null, empty strings and empty arrays retain their meaning');
check(get_post_meta(123, '_wp_page_template', true) === 'page-custom.php' && get_post_meta(123, 'seo_title', true) === 'Keep SEO', 'Restore preserves template and third-party metadata');
check(get_post_meta(456, '_cff_title', true) === 'Independent translation' && get_post_meta(789, '_cff_title', true) === 'Independent CPT', 'Restore leaves translations and other CPT IDs untouched');
$restored_history = $revisions->history(123);
check(end($restored_history)['kind'] === 'restore', 'Restore records a new history entry');
check((bool) array_filter($restored_history, function($entry) use ($current) { return $entry['snapshot'] === $current; }), 'State before restore remains available for recovery');
$count = count($restored_history);
do_action('shutdown');
check(count($revisions->history(123)) === $count, 'Restore does not generate another duplicate snapshot at shutdown');
$latest_id = end($restored_history)['id'];
check($revisions->compare(123, $latest_id)['can_restore'] === false, 'The current saved version is identified correctly');
check(count($revisions->compare(123, $selected_id, $history[1]['id'])['changes']) > 0, 'Can compare two historical versions');

// Copies can share field values, but must never share history.
$clone = new CFF\Language_Clone($plugin);
check(!$clone->copy_meta_key(CFF\Content_Revisions::META_KEY, ['parts' => ['cff', 'meta']]), 'Language Clone excludes source history even when custom meta is selected');
$skip = new ReflectionMethod(CFF\Plugin::class, 'should_skip_duplicate_meta_key');
$skip->setAccessible(true);
check($skip->invoke($plugin, CFF\Content_Revisions::META_KEY), 'Duplicate post excludes source history');
check(apply_filters('pll_copy_post_metas', ['_cff_title', CFF\Content_Revisions::META_KEY, 'seo_title'], true) === ['_cff_title', 'seo_title'], 'Polylang never copies or synchronizes history');
add_post_meta(456, CFF\Content_Revisions::META_KEY, wp_slash($history[0]));
check($revisions->history(456) === [], 'Even foreign history accidentally copied by another plugin is ignored');
check(is_wp_error($revisions->restore(456, $selected_id, $revisions->fingerprint($revisions->snapshot(456)))), 'A copied foreign revision cannot be restored under a different ID');
update_post_meta(456, '_cff_title', 'Translation edited');
update_post_meta(789, '_cff_title', 'Work edited');
foreach ([900, 901, 902] as $id) update_post_meta($id, '_cff_title', 'Internal content');
do_action('shutdown');
check(count($revisions->history(456)) === 2 && count($revisions->history(789)) === 2, 'Translations and CPTs accumulate their own independent history');
foreach ([900, 901, 902] as $id) check($revisions->history($id) === [], 'Internal post types are excluded: ' . $id);
for ($index = 0; $index < 24; $index++) {
  update_post_meta(789, '_cff_title', 'Version ' . $index);
  do_action('shutdown');
}
check(count($revisions->history(789)) === 20, 'Retention keeps only the latest 20 versions per content ID');
$retained = $revisions->history(789);
check(end($retained)['snapshot']['_cff_title'] === ['Version 23'], 'Retention preserves the newest version');
check(count($revisions->history(456)) === 2 && count($revisions->history(123)) === $count, 'Pruning one ID does not prune other IDs');
$items = ajax_request(789, 'history')->payload['data']['items'];
check(count($items) === 20 && !isset($items[0]['snapshot']), 'History list returns only revision summaries, newest first');
check($items[0]['id'] === end($retained)['id'], 'History UI list is ordered newest first');
$revisions->meta_box('work', get_post(789));
check($GLOBALS['fixture_boxes'][0][3] === 'work', 'History metabox is attached to its content editor');
$GLOBALS['fixture_denied'] = [789];
$revisions->meta_box('work', get_post(789));
check(count($GLOBALS['fixture_boxes']) === 1, 'History metabox is not shown without edit permission');
$GLOBALS['fixture_denied'] = [];
ob_start(); $revisions->render_meta_box(get_post(789)); $html = ob_get_clean();
check(strpos($html, 'data-post-id="789"') !== false && strpos($html, 'valid-cff_content_revisions_789') !== false, 'Editor binds panel and nonce to its content ID');

// A Polylang installation configured to synchronize field metadata must still keep restores local.
$translation_before_restore = $revisions->snapshot(456);
$sync = function($meta_id, $post_id, $key) {
  if ((int) $post_id !== 123 || strpos($key, '_cff_') !== 0) return;
  foreach (apply_filters('pll_copy_post_metas', [$key], true) as $allowed_key) {
    update_post_meta(456, $allowed_key, wp_slash(get_post_meta(123, $allowed_key, true)));
  }
};
add_action('added_post_meta', $sync, 20, 3);
add_action('deleted_post_meta', $sync, 20, 3);
$local_restore = $revisions->restore(123, $history[1]['id'], $revisions->fingerprint($revisions->snapshot(123)));
check($local_restore === true && $revisions->snapshot(456) === $translation_before_restore, 'Restore suppresses Polylang CFF synchronization to translations');
remove_action('added_post_meta', $sync, 20);
remove_action('deleted_post_meta', $sync, 20);
do_action('shutdown');

// Who changed the values and when must belong to that revision, not the current editor.
revision_fixture_post(321);
$GLOBALS['fixture_user_id'] = 11;
update_post_meta(321, '_cff_title', 'A legacy of timeless beauty');
do_action('shutdown');
$first_actor_history = $revisions->history(321);
$GLOBALS['fixture_user_id'] = 12;
update_post_meta(321, '_cff_title', 'A legacy of modern beauty');
do_action('shutdown');
$actor_items = ajax_request(321, 'history')->payload['data']['items'];
check($actor_items[0]['user_id'] === 12 && $actor_items[1]['user_id'] === 11, 'Each saved revision retains its own editing user');
check($actor_items[0]['author_known'] && !$actor_items[2]['author_known'], 'Captured baseline never claims to know the original editor');
check($actor_items[0]['current'] && !$actor_items[1]['current'], 'Only the latest matching snapshot is marked as current');
check(strpos($actor_items[0]['date'], 'WIB') !== false && preg_match('/\d{2}:\d{2}:\d{2}/', $actor_items[0]['date']), 'Exact date/time includes seconds in the site timezone');
check(substr($actor_items[0]['datetime'], -1) === 'Z' && strpos($actor_items[0]['relative'], 'ago') !== false, 'API provides an unambiguous UTC datetime and relative time');
$GLOBALS['fixture_missing_users'] = [11];
$actor_items = ajax_request(321, 'history')->payload['data']['items'];
check($actor_items[1]['user'] === 'Editor 11', 'Saved display name survives deletion of the editing user');
$GLOBALS['fixture_missing_users'] = [];
$word_comparison = $revisions->compare(321, $first_actor_history[1]['id']);
$word_html = $word_comparison['changes'][0]['diff_html'];
check(strpos($word_html, '<del>') !== false && strpos($word_html, '<ins>') !== false, 'Native WordPress diff highlights individual removed and added words');
$unsafe_diff = $revisions->compare(123, $selected_id);
$unsafe_html = implode('', array_column($unsafe_diff['changes'], 'diff_html'));
check(strpos($unsafe_html, '<script>') === false && strpos($unsafe_html, '&lt;script&gt;') !== false, 'Diff escapes saved HTML rather than rendering content markup');
$revisions->register_page();
check($GLOBALS['fixture_pages'][0][0] === '' && $GLOBALS['fixture_pages'][0][3] === 'read', 'Revision destination has no centralized menu and supports content-specific editor roles');
$_GET = ['post_id' => 321];
ob_start(); $revisions->page(); $page_html = ob_get_clean();
check(strpos($page_html, 'data-post-id="321"') !== false && strpos($page_html, 'Compare any two revisions') !== false, 'Dedicated revision page is scoped to the selected ID');
$GLOBALS['fixture_denied'] = [321];
try { $revisions->page(); check(false, 'Unauthorized page access must fail'); }
catch (RuntimeException $error) { check($error->getCode() === 403, 'Direct page access requires edit permission for that exact ID'); }
$GLOBALS['fixture_denied'] = [];
$GLOBALS['fixture_user_id'] = 7;

// Partial restores must preserve every unselected field and complete nested container structures.
revision_fixture_post(777);
$partial_old = [
  '_cff_group' => ['heading' => 'Old section', 'zero' => 0, 'off' => false, 'children' => [['label' => 'Old child']]],
  '_cff_rows' => [['__cff_row_id' => 'row-old', 'answer' => 'Old row', 'nested' => [['text' => 'Old nested']]]],
  '_cff_blocks' => [['layout' => 'hero', '__cff_row_id' => 'block-old', 'fields' => ['caption' => 'Old flexible']]],
  '_cff_title' => 'Old title', '_cff_obsolete' => 'Old deleted field', '_cff_image' => '42', '_cff_image_url' => 'https://example.test/old.jpg',
  '_cff_hidden_sections' => ['group' => true, 'rows' => true], '_cff_hidden_sections_local' => 'en',
  '_cff_group_field_order_900' => ['group', 'rows', 'title'], '_cff_group_field_order_custom_900' => '1',
];
foreach ($partial_old as $key => $value) update_post_meta(777, $key, wp_slash($value));
do_action('shutdown');
$partial_history = $revisions->history(777);
$partial_id = end($partial_history)['id'];
foreach (['group', 'rows', 'blocks'] as $name) update_post_meta(777, '_cff_' . $name, ['new' => 'Current ' . $name]);
update_post_meta(777, '_cff_title', 'Current title');
update_post_meta(777, '_cff_later', 'Added after selected revision');
delete_post_meta(777, '_cff_obsolete');
update_post_meta(777, '_cff_image', '84');
update_post_meta(777, '_cff_image_url', 'https://example.test/new.jpg');
update_post_meta(777, '_cff_hidden_sections', ['unselected' => true]);
update_post_meta(777, '_cff_group_field_order_900', ['title', 'rows', 'group']);
update_post_meta(777, 'seo_title', 'Unrelated SEO');
do_action('shutdown');
$property = new ReflectionProperty(CFF\Plugin::class, 'field_definitions_cache');
$property->setAccessible(true);
$cache = $property->getValue($plugin);
$cache[777] = ['image' => ['type' => 'image', 'label' => 'Photo']];
$property->setValue($plugin, $cache);
$partial_current = $revisions->snapshot(777);
$partial_hash = $revisions->fingerprint($partial_current);
foreach ([[], 'field:group', ['field:not-present'], ['_wp_page_template'], ['field:group', 'field:settings'], [['field:group']], null] as $selection) {
  $response = ajax_request(777, 'restore', ['revision_id' => $partial_id, 'current_hash' => $partial_hash, 'fields' => $selection]);
  check($response->status === 400 && $revisions->snapshot(777) === $partial_current, 'Malformed/unknown partial selection never becomes a full restore');
}
check($revisions->restore(123, $partial_id, $revisions->fingerprint($revisions->snapshot(123)), ['field:group'])->get_error_code() === 'cff_revision_missing', 'Partial restore cannot cross content IDs');
$GLOBALS['fixture_denied'] = [777];
check(is_wp_error($revisions->restore(777, $partial_id, $partial_hash, ['field:group'])), 'Partial restore requires permission for the selected ID');
$GLOBALS['fixture_denied'] = [];
$GLOBALS['fixture_locks'][777] = 99;
check($revisions->restore(777, $partial_id, $partial_hash, ['field:group'])->get_error_code() === 'cff_revision_locked', 'Partial restore respects active editor locks');
unset($GLOBALS['fixture_locks'][777]);
check($revisions->restore(777, $partial_id, str_repeat('0', 64), ['field:group'])->get_error_code() === 'cff_revision_stale', 'Partial restore rejects a stale comparison');
$result = ajax_request(777, 'restore', ['revision_id' => $partial_id, 'current_hash' => $partial_hash, 'fields' => ['field:group']]);
check($result->payload['success'], 'Single section can be restored through AJAX');
check(get_post_meta(777, '_cff_group', true) === $partial_old['_cff_group'], 'Section restore preserves old nested zero/false values and removes new nested members');
$expected = $partial_current;
$expected['_cff_group'] = [$partial_old['_cff_group']];
$expected['_cff_hidden_sections'] = [['unselected' => true, 'group' => true]];
check($revisions->snapshot(777) === $expected, 'Only the selected section and its own visibility change');
$partial_history = $revisions->history(777);
check(end($partial_history)['restored_fields'] === ['field:group'], 'History records which fields were restored');
check((bool) array_filter($partial_history, function($entry) use ($partial_current) { return $entry['snapshot'] === $partial_current; }), 'Partial restore retains the complete pre-restore recovery snapshot');
$hash = $revisions->fingerprint($revisions->snapshot(777));
check($revisions->restore(777, $partial_id, $hash, ['field:rows', 'field:blocks']) === true, 'Multiple selected containers can be restored together');
check(get_post_meta(777, '_cff_rows', true) === $partial_old['_cff_rows'] && get_post_meta(777, '_cff_blocks', true) === $partial_old['_cff_blocks'], 'Repeater row identities and flexible layout structures are restored intact');
check(get_post_meta(777, '_cff_title', true) === 'Current title' && get_post_meta(777, '_cff_group_field_order_900', true) === ['title', 'rows', 'group'], 'Unselected scalar values and field order are preserved');
$hash = $revisions->fingerprint($revisions->snapshot(777));
$revisions->restore(777, $partial_id, $hash, ['field:later', 'field:obsolete']);
check(!metadata_exists('post', 777, '_cff_later') && get_post_meta(777, '_cff_obsolete', true) === 'Old deleted field', 'Selected fields absent from the old version are deleted and old deleted fields can be restored');
$hash = $revisions->fingerprint($revisions->snapshot(777));
$revisions->restore(777, $partial_id, $hash, ['field:image']);
check(get_post_meta(777, '_cff_image', true) === '42' && get_post_meta(777, '_cff_image_url', true) === 'https://example.test/old.jpg', 'Media selection restores its legacy URL companion together');
$before_order = $revisions->snapshot(777);
$hash = $revisions->fingerprint($before_order);
$revisions->restore(777, $partial_id, $hash, ['meta:order']);
$expected = $before_order;
$expected['_cff_group_field_order_900'] = [$partial_old['_cff_group_field_order_900']];
check($revisions->snapshot(777) === $expected, 'Explicit field-order selection changes ordering metadata only');
$hash = $revisions->fingerprint($revisions->snapshot(777));
$revisions->restore(777, $partial_id, $hash, ['meta:visibility']);
check(get_post_meta(777, '_cff_hidden_sections', true) === $partial_old['_cff_hidden_sections'], 'Explicit visibility selection restores the complete saved visibility map');
check(get_post_meta(777, 'seo_title', true) === 'Unrelated SEO', 'Partial restores never modify third-party metadata');
update_post_meta(777, '_cff_group', ['heading' => 'Current after edits']);
update_post_meta(777, '_cff_title', 'Latest title');
do_action('shutdown');
$before_failure = $revisions->snapshot(777);
$wpdb->fail_key = '_cff_group';
$failed = $revisions->restore(777, $partial_id, $revisions->fingerprint($before_failure), ['field:group', 'field:title']);
check(is_wp_error($failed) && $revisions->snapshot(777) === $before_failure, 'A failed multi-field partial restore rolls back every touched value');
$choices = $revisions->compare(777, $partial_id, $partial_id)['restore_fields'];
check(in_array('field:group', array_column($choices, 'id'), true), 'Partial choices compare To against saved values even when From and To are identical');
check(!in_array('field:image_url', array_column($choices, 'id'), true), 'Legacy media companion is not offered as a separate partial field');
$before_noop = count($revisions->history(777));
$hash = $revisions->fingerprint($revisions->snapshot(777));
$revisions->restore(777, $partial_id, $hash, ['field:image']);
check(count($revisions->history(777)) === $before_noop, 'Selecting a field already matching current values does not create redundant history');

// Date filters consume site-local calendar dates, not UTC date prefixes.
revision_fixture_post(778);
update_post_meta(778, '_cff_title', 'Date boundary');
do_action('shutdown');
$dated = $revisions->history(778);
$dated = end($dated);
delete_post_meta(778, CFF\Content_Revisions::META_KEY, wp_slash($dated));
$dated['created_gmt'] = '2026-11-08 18:30:00';
add_post_meta(778, CFF\Content_Revisions::META_KEY, wp_slash($dated));
$dated_summary = ajax_request(778, 'history')->payload['data']['items'][0];
check($dated_summary['local_date'] === '2026-11-09' && substr($dated_summary['datetime'], 0, 10) === '2026-11-08', 'Filter date respects the site day across UTC midnight');
check($dated_summary['date_long'] === 'November 9, 2026 @ 01:30:00', 'Popup date links use the full site-local month, day, year and exact time');

$translation_count = count($revisions->history(456));
define('DOING_AUTOSAVE', true);
update_post_meta(456, '_cff_title', 'Autosave simulation');
do_action('shutdown');
check(count($revisions->history(456)) === $translation_count, 'Autosave does not create a saved revision');

echo "Content revisions: $checks checks passed (real WordPress metadata/hooks, isolated SQLite).\n";
