<?php
namespace CFF;
if (!defined('ABSPATH')) exit;
?>
<div class="wrap cff-revisions-page" data-post-id="<?php echo esc_attr($post_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('cff_content_revisions_' . $post_id)); ?>" data-edit-url="<?php echo esc_url($edit_url); ?>">
  <h1><?php echo esc_html__('Compare CFF Revisions of', 'cff'); ?> “<a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($post->post_title ?: __('Untitled', 'cff')); ?></a>”</h1>
  <p><a class="cff-revisions-back" href="<?php echo esc_url($edit_url); ?>">← <?php echo esc_html__('Go to editor', 'cff'); ?></a></p>
  <p class="description"><?php echo esc_html(sprintf(__('Content ID %d · CFF values, field order and section visibility only.', 'cff'), $post_id)); ?></p>
  <p class="cff-revisions-status" role="status" aria-live="polite"></p>
  <p class="cff-revisions-error" role="alert"></p>
  <form class="cff-revisions-filters" hidden>
    <div class="cff-revisions-filter-controls">
      <label for="cff-revision-user"><?php echo esc_html__('User', 'cff'); ?><select id="cff-revision-user"><option value=""><?php echo esc_html__('All users', 'cff'); ?></option></select></label>
      <label for="cff-revision-date-from"><?php echo esc_html__('From date', 'cff'); ?><input type="date" id="cff-revision-date-from"></label>
      <label for="cff-revision-date-to"><?php echo esc_html__('To date', 'cff'); ?><input type="date" id="cff-revision-date-to"></label>
      <button type="submit" class="button"><?php echo esc_html__('Filter', 'cff'); ?></button>
      <button type="button" class="button cff-revision-reset-filters"><?php echo esc_html__('Reset', 'cff'); ?></button>
    </div>
    <p class="description"><?php echo esc_html__('Dates include the full day in the WordPress site timezone. User includes the person who captured pre-change values.', 'cff'); ?> <span class="cff-revision-filter-count" aria-live="polite"></span></p>
  </form>
  <div class="cff-revisions-workspace" hidden>
    <div class="cff-revisions-toolbar">
      <label><input type="checkbox" id="cff-compare-any"> <?php echo esc_html__('Compare any two revisions', 'cff'); ?></label>
    </div>
    <div class="cff-revisions-navigation">
      <button type="button" class="button cff-revision-previous"><?php echo esc_html__('Previous', 'cff'); ?></button>
      <div class="cff-revisions-timeline">
        <div class="cff-revision-range cff-revision-from-range" hidden>
          <div id="cff-revision-from-tooltip" class="cff-revision-tooltip cff-revision-person" role="tooltip"></div>
          <div class="cff-revision-slider">
            <div class="cff-revision-ticks" aria-hidden="true"></div>
            <label class="screen-reader-text" for="cff-revision-from-range"><?php echo esc_html__('From revision', 'cff'); ?></label>
            <input type="range" id="cff-revision-from-range" aria-describedby="cff-revision-from-tooltip" min="0" step="1" value="0">
          </div>
        </div>
        <div class="cff-revision-range cff-revision-to-range">
          <div id="cff-revision-to-tooltip" class="cff-revision-tooltip cff-revision-person" role="tooltip"></div>
          <div class="cff-revision-slider">
            <div class="cff-revision-ticks" aria-hidden="true"></div>
            <label class="screen-reader-text" for="cff-revision-to-range"><?php echo esc_html__('To revision', 'cff'); ?></label>
            <input type="range" id="cff-revision-to-range" aria-describedby="cff-revision-to-tooltip" min="0" step="1" value="0">
          </div>
        </div>
      </div>
      <button type="button" class="button cff-revision-next"><?php echo esc_html__('Next', 'cff'); ?></button>
    </div>
    <div class="cff-revisions-meta">
      <div class="cff-revisions-versions">
        <div class="cff-revision-version cff-revision-from">
          <label for="cff-revision-from"><?php echo esc_html__('From:', 'cff'); ?></label>
          <div class="cff-revision-person"></div>
          <select id="cff-revision-from" aria-label="<?php echo esc_attr(__('From revision', 'cff')); ?>"></select>
        </div>
        <div class="cff-revision-version cff-revision-to">
          <label for="cff-revision-to"><?php echo esc_html__('To:', 'cff'); ?></label>
          <div class="cff-revision-person"></div>
          <select id="cff-revision-to" aria-label="<?php echo esc_attr(__('To revision to restore', 'cff')); ?>"></select>
        </div>
      </div>
      <div class="cff-revisions-actions">
        <p class="description cff-revisions-restore-note"><?php echo esc_html__('Restore the To revision. Current saved values remain in this content’s history.', 'cff'); ?></p>
        <button type="button" class="button button-primary cff-revision-restore" disabled><?php echo esc_html__('Restore This Revision', 'cff'); ?></button>
      </div>
      <details class="cff-revisions-partial">
        <summary><?php echo esc_html__('Restore selected fields / sections', 'cff'); ?></summary>
        <p class="description"><?php echo esc_html__('Choose fields from the To revision. Groups, repeaters and flexible content are restored as a whole, including their own Hide Section state. Other fields and their order are kept unless explicitly selected.', 'cff'); ?></p>
        <div class="cff-revision-field-options"></div>
        <button type="button" class="button cff-revision-restore-selected" disabled><?php echo esc_html__('Restore selected', 'cff'); ?></button>
      </details>
    </div>
    <div class="cff-revisions-results" aria-busy="false"></div>
  </div>
</div>
