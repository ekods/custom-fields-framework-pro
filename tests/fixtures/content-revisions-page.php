<?php
/** Disposable browser fixture. Run only with an isolated CFF_REVISION_TEST_DSN SQLite file. */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$plugin_root = dirname(__DIR__, 2);
if (strpos($path, '/cff/') === 0 || strpos($path, '/core/') === 0) {
  $base = strpos($path, '/cff/') === 0 ? $plugin_root : getenv('WP_ADMIN_TEST_ROOT');
  $relative = substr($path, strpos($path, '/cff/') === 0 ? 5 : 6);
  $file = realpath($base . '/' . $relative);
  if (!$file || strpos($file, realpath($base) . '/') !== 0 || !preg_match('/\.(css|js|woff2)$/', $file)) { http_response_code(404); exit; }
  header('Content-Type: ' . (substr($file, -3) === '.js' ? 'application/javascript' : 'text/css'));
  readfile($file);
  exit;
}
if (!getenv('CFF_REVISION_TEST_DSN')) throw new RuntimeException('A disposable SQLite database is required.');
require __DIR__ . '/content-revisions-runtime.php';
revision_fixture_post(123);
revision_fixture_post(456);
if (!$revisions->history(123)) {
  update_post_meta(123, '_cff_title', wp_slash('Original C:\\draft\\quoted "title"'));
  update_post_meta(123, '_cff_rows', [['answer' => '<img src=x onerror="window.injected=true">', 'nested' => [['label' => 'Original nested']]]]);
  update_post_meta(123, '_cff_hidden_sections', ['title' => true]);
  update_post_meta(123, '_cff_marker_fixture', ['title' => 'Browse Works By Service', 'slide_text' => 'Build with', 'typed' => 0,
    'wrapped' => 'Build with a carefully considered branding system that stays readable across several wrapped lines.']);
  update_post_meta(456, '_cff_title', 'Other ID untouched');
  $revisions->register();
  $GLOBALS['fixture_user_id'] = 8;
  update_post_meta(123, '_cff_title', 'Current title');
  update_post_meta(123, '_cff_rows', [['answer' => 'New answer', 'nested' => [['label' => 'Changed nested']]]]);
  delete_post_meta(123, '_cff_hidden_sections');
  update_post_meta(123, '_cff_marker_fixture', ['title' => 'Browse Works By Service 1', 'slide_text' => 'Build with 22', 'typed' => '0',
    'wrapped' => 'Build with a carefully considered visual branding system that stays readable across several wrapped lines.']);
  $revisions->flush();
} else {
  $revisions->register();
}
if (isset($_GET['extra_history'])) {
  while (count($revisions->history(123)) < 5) {
    update_post_meta(123, '_cff_title', 'Timeline version ' . count($revisions->history(123)));
    $revisions->flush();
  }
}
if ($path === '/ajax') {
  try { $revisions->ajax(); } catch (Revision_Test_Response $response) {
    http_response_code($response->status);
    header('Content-Type: application/json');
    echo json_encode($response->payload);
  }
  exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/core/wp-admin/css/common.min.css"><link rel="stylesheet" href="/core/wp-includes/css/buttons.min.css">
<link rel="stylesheet" href="/core/wp-admin/css/forms.min.css"><link rel="stylesheet" href="/cff/assets/content-revisions.css">
<link rel="stylesheet" href="/core/wp-includes/css/dashicons.min.css">
<link rel="stylesheet" href="/core/wp-admin/css/revisions.min.css">
<style>body{padding:20px;background:#f0f0f1}#post{max-width:800px}textarea{width:100%}.cff-revisions{margin-top:20px}</style>
</head><body class="wp-admin wp-core-ui">
<?php if (isset($_GET['page']) && $_GET['page'] === 'cff-content-revisions') : ?>
<?php $revisions->page(); ?>
<?php else : ?>
<?php $editor_id = isset($_GET['post_id']) && in_array((int) $_GET['post_id'], [123, 456], true) ? (int) $_GET['post_id'] : 123; ?>
<form id="<?php echo isset($_GET['block']) ? 'metaboxes' : 'post'; ?>"><h1>Edit content ID <?php echo esc_html($editor_id); ?></h1><label for="title">Title field</label>
<textarea id="title" name="cff_values[title]"><?php echo esc_html(get_post_meta($editor_id, '_cff_title', true)); ?></textarea>
<label for="row-answer">Repeater answer</label><textarea id="row-answer" name="cff_values[rows][0][answer]"><?php echo esc_html(get_post_meta($editor_id, '_cff_rows', true)[0]['answer'] ?? ''); ?></textarea>
<?php $revisions->render_meta_box(get_post($editor_id)); ?>
</form>
<?php endif; ?>
<script src="/core/wp-includes/js/jquery/jquery.min.js"></script><script src="/core/wp-includes/js/dist/hooks.min.js"></script><script src="/core/wp-includes/js/dist/i18n.min.js"></script>
<script>window.cffContentRevisions={ajaxUrl:'/ajax'};</script><script src="/cff/assets/js/content-revisions.js"></script>
</body></html>
