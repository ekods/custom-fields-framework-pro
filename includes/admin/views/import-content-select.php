<?php
namespace CFF;
if (!defined('ABSPATH')) exit;
?>
<div class="wrap tk-wrap cff-admin cff-tools-page cff-content-import">
  <?php cff_render_header_branding(); ?>
  <?php cff_render_page_hero(__('Import Content', 'cff'), __('Import one JSON file into an existing page. Choose a page below or open Import Content from its editor.', 'cff'), 'dashicons-upload'); ?>
  <div class="tk-card">
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
      <input type="hidden" name="page" value="cff-import-content">
      <p><label for="cff-import-search-type"><?php esc_html_e('Post type', 'cff'); ?></label>
      <select id="cff-import-search-type" name="post_type">
        <?php foreach ($types as $name => $object) : ?>
        <option value="<?php echo esc_attr($name); ?>" <?php selected($type, $name); ?>><?php echo esc_html($object->labels->name ?? $name); ?></option>
        <?php endforeach; ?>
      </select></p>
      <p><label for="cff-import-search"><?php esc_html_e('Find a page', 'cff'); ?></label>
      <input type="search" id="cff-import-search" name="content_search" value="<?php echo esc_attr($search); ?>">
      <button type="submit" class="button"><?php esc_html_e('Search', 'cff'); ?></button></p>
    </form>
    <p class="description"><?php esc_html_e('Showing up to 30 recently modified matches. The destination stays fixed throughout preview and import.', 'cff'); ?></p>
    <table class="widefat striped"><thead><tr><th><?php esc_html_e('Content', 'cff'); ?></th><th><?php esc_html_e('Status', 'cff'); ?></th><th><?php esc_html_e('Import', 'cff'); ?></th></tr></thead><tbody>
    <?php foreach ($candidates as $candidate) : ?>
      <tr><td><?php echo esc_html($candidate->post_title ?: '(untitled)'); ?> <small>#<?php echo (int) $candidate->ID; ?></small><?php if (function_exists('pll_get_post_language')) echo ' · ' . esc_html(strtoupper((string) pll_get_post_language($candidate->ID, 'slug'))); ?></td>
      <td><?php echo esc_html($candidate->post_status); ?></td>
      <td><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cff-import-content&post_id=' . (int) $candidate->ID)); ?>"><?php esc_html_e('Import into this page', 'cff'); ?></a></td></tr>
    <?php endforeach; ?>
    <?php if (!$candidates) : ?><tr><td colspan="3"><?php esc_html_e('No editable content found. Save a page first, or try another search.', 'cff'); ?></td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
