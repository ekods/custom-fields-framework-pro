<?php
namespace CFF;
if (!defined('ABSPATH')) exit;
?>
<div class="wrap tk-wrap cff-admin cff-tools-page cff-content-import">
  <?php cff_render_header_branding(); ?>
  <?php cff_render_page_hero(__('Import Content', 'cff'), $destination ? __('Import one JSON content object into the selected page. Review field mappings before writing.', 'cff') : __('Import post, page and custom field values from JSON. Review a preview before writing content.', 'cff'), 'dashicons-upload'); ?>
  <?php if ($destination) : ?>
    <div class="tk-card cff-import-destination">
      <h2><?php echo esc_html($destination->post_title); ?> <small>#<?php echo (int) $destination->ID; ?></small></h2>
      <p><?php echo esc_html($destination->post_type . ' · ' . (get_page_template_slug($destination->ID) ?: 'default')); ?><?php if (function_exists('pll_get_post_language')) echo ' · ' . esc_html(strtoupper((string) pll_get_post_language($destination->ID, 'slug'))); ?></p>
      <a class="button" href="<?php echo esc_url(admin_url('post.php?post=' . (int) $destination->ID . '&action=edit')); ?>"><?php esc_html_e('Back to page editor', 'cff'); ?></a>
      <p class="description"><?php esc_html_e('Only this page’s CFF values will change. Its title, URL, template, publication status and translations are kept.', 'cff'); ?></p>
    </div>
  <?php endif; ?>
  <div class="tk-card">
    <form id="cff-content-import-form" enctype="multipart/form-data">
      <div class="cff-import-settings">
        <div class="cff-tools-field"><label for="cff-import-file"><?php esc_html_e('Content JSON', 'cff'); ?></label>
          <input required id="cff-import-file" type="file" name="content_json" accept=".json,application/json">
          <p class="description"><?php esc_html_e($destination ? 'One JSON object, maximum 5 MB. Use section keys such as hero, questions and contact, or full CFF field names.' : 'Maximum 5 MB and 500 items. Field groups must already exist on this site.', 'cff'); ?></p>
        </div>
        <?php if ($destination) : ?>
        <input type="hidden" name="scope" value="page">
        <input type="hidden" name="post_id" value="<?php echo (int) $destination->ID; ?>">
        <div class="cff-tools-field"><label for="cff-import-policy"><?php esc_html_e('Existing content', 'cff'); ?></label>
          <select id="cff-import-policy" name="policy">
            <option value="fill_empty"><?php esc_html_e('Fill empty fields only', 'cff'); ?></option>
            <option value="replace"><?php esc_html_e('Replace supplied fields', 'cff'); ?></option>
          </select>
          <p class="description"><?php esc_html_e('Fill empty keeps existing text, zero/false values and non-empty repeaters. Replace overwrites only fields present in this JSON; supplied repeater arrays replace existing rows.', 'cff'); ?></p>
        </div>
        <?php else : ?>
        <div class="cff-tools-field"><label for="cff-import-type"><?php esc_html_e('Target post type', 'cff'); ?></label>
          <select required id="cff-import-type" name="post_type">
            <?php foreach ($types as $name => $type) : ?>
              <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($type->labels->name ?? $name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="cff-tools-field"><label for="cff-import-mode"><?php esc_html_e('Import mode', 'cff'); ?></label>
          <select id="cff-import-mode" name="mode">
            <option value="create"><?php esc_html_e('Create new only — skip existing content', 'cff'); ?></option>
            <option value="update"><?php esc_html_e('Update existing only — match ID or slug', 'cff'); ?></option>
            <option value="upsert"><?php esc_html_e('Create or update — match ID or slug', 'cff'); ?></option>
          </select>
          <p class="description"><?php esc_html_e('IDs refer to this site. Omit ID to match by post_name. Updates only change values included in the JSON.', 'cff'); ?></p>
        </div>
        <?php endif; ?>
      </div>
      <?php if (!$destination) : ?>
      <p><label><input type="checkbox" name="allow_publish" value="1"> <?php esc_html_e('Allow publishing/private status requested by JSON', 'cff'); ?></label></p>
      <p class="description"><?php esc_html_e('New content defaults to draft. Existing content keeps its status unless post_status is supplied. Preview does not change posts, fields or terms.', 'cff'); ?></p>
      <?php else : ?>
      <p class="description"><?php esc_html_e('Preview shows the section-to-field mapping without changing content. Image paths are matched to existing Media Library files; unmatched images are reported and existing media at the same field/row position is retained. No media is downloaded.', 'cff'); ?></p>
      <?php endif; ?>
      <p class="cff-import-actions"><button type="submit" class="button button-primary" id="cff-import-upload"><?php esc_html_e('Upload and preview', 'cff'); ?></button>
        <a class="button" href="<?php echo esc_url($sample_url); ?>"><?php esc_html_e('Download example JSON', 'cff'); ?></a>
      </p>
    </form>
    <noscript><p><?php esc_html_e('JavaScript is required to preview and process import batches.', 'cff'); ?></p></noscript>
  </div>

  <div class="tk-card cff-import-result" id="cff-import-result" hidden>
    <p id="cff-import-message" role="status" aria-live="polite"></p>
    <progress id="cff-import-progress" max="100" value="0" aria-label="Import progress"></progress>
    <div id="cff-import-counts" class="cff-import-counts"></div>
    <p class="cff-import-actions">
      <button type="button" class="button button-primary" id="cff-import-start" hidden><?php esc_html_e('Import valid items', 'cff'); ?></button>
      <button type="button" class="button" id="cff-import-continue" hidden><?php esc_html_e('Continue saved progress', 'cff'); ?></button>
      <button type="button" class="button" id="cff-import-pause" hidden><?php esc_html_e('Pause after current batch', 'cff'); ?></button>
      <button type="button" class="button" id="cff-import-cancel" hidden><?php esc_html_e('Cancel remaining items', 'cff'); ?></button>
      <button type="button" class="button" id="cff-import-report"><?php esc_html_e('Download report', 'cff'); ?></button>
    </p>
    <p class="description"><?php esc_html_e($destination ? 'This import updates one page. Review the mappings and any media notes before starting. Saved progress expires after 24 hours.' : 'Invalid items are excluded. Processing runs while this page is open, up to five items per batch. Pausing or cancelling keeps completed changes. Saved progress expires after 24 hours.', 'cff'); ?></p>
    <div class="cff-import-table-wrap"><table class="widefat striped"><thead><tr>
      <th>#</th><th><?php esc_html_e('Content', 'cff'); ?></th><th><?php esc_html_e('Action', 'cff'); ?></th><th><?php esc_html_e('Status', 'cff'); ?></th><th><?php esc_html_e('Details', 'cff'); ?></th>
    </tr></thead><tbody id="cff-import-rows"></tbody></table></div>
  </div>

  <details class="tk-card cff-import-help">
    <summary><strong><?php esc_html_e('JSON format and field values', 'cff'); ?></strong></summary>
    <?php if ($destination) : ?>
    <p><?php esc_html_e('Upload your page content JSON directly. Section names match an exact CFF field name first, then a unique suffix: hero can match why_hero. Ambiguous or unknown sections are rejected. Only fields registered for this page and its saved template are available.', 'cff'); ?></p>
    <pre>{"hero": {"title": "Your heading"}, "questions": {"items": [{"question": "Your question", "answer": "Your answer"}]}}</pre>
    <p><?php esc_html_e('The names above are an example. Download example JSON to get the actual field names for this page. Save a changed template in the editor before importing. Unknown sections or subfields are rejected.', 'cff'); ?></p>
    <?php else : ?>
    <p><?php esc_html_e('Use an items array or a plain array of content objects. All items must use the selected target post type. Configuration JSON belongs in Export / Import.', 'cff'); ?></p>
    <pre><?php echo esc_html(wp_json_encode(self::sample(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
    <ul>
      <li><code>cff</code>: <?php esc_html_e('object keyed by your actual CFF field names. Groups use objects, repeaters use arrays of row objects, and flexible rows use layout plus fields.', 'cff'); ?></li>
      <li><code>page_template</code>: <?php esc_html_e('active theme template path, such as templates/page-services.php. Preview uses this template to find the correct field groups.', 'cff'); ?></li>
      <li><code>featured_image</code>, <?php esc_html_e('image, file, gallery and relational fields:', 'cff'); ?> <?php esc_html_e('use existing IDs from this site. Taxonomy relationships must reference terms from the configured taxonomy. Remote media downloads and cross-site ID remapping are not included.', 'cff'); ?></li>
      <li><code>taxonomies</code>: <code>{"service": [149]}</code> — <?php esc_html_e('existing term IDs assigned to the post; only taxonomies enabled for the post type are accepted.', 'cff'); ?></li>
      <li><code>language</code>: <?php esc_html_e('optional existing Polylang language slug. Import does not move existing translations or create translation links.', 'cff'); ?></li>
      <li><?php esc_html_e('Omitted fields are preserved on update. Supplied repeater/gallery/flexible arrays replace those values. Group objects merge with existing group values; null or an empty value clears a supplied field.', 'cff'); ?></li>
    </ul>
    <?php endif; ?>
  </details>
</div>
