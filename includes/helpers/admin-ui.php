<?php
namespace CFF;

if (!defined('ABSPATH')) exit;

function cff_render_page_hero($title, $description, $icon = 'dashicons-admin-tools', $action_html = '') {
    ?>
    <div class="tk-hero">
        <div class="tk-hero-content">
            <div class="tk-hero-main">
                <div class="tk-hero-icon">
                    <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                </div>
                <div>
                    <h1 class="tk-hero-title"><?php echo esc_html($title); ?></h1>
                    <p class="tk-hero-subtitle"><?php echo esc_html($description); ?></p>
                </div>
            </div>
            <?php if ($action_html !== '') : ?>
                <div class="tk-hero-action">
                    <?php echo $action_html; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function cff_render_header_branding() {
    ?>
    <div class="tk-header-branding">
        <div class="tk-header-brand">
            <span class="dashicons dashicons-feedback"></span>
            <span>Custom Fields Framework Pro</span>
            <span class="tk-header-version">v<?php echo defined('CFFP_VERSION') ? CFFP_VERSION : '2.3'; ?></span>
        </div>

        <a class="cff-help-link" href="<?php echo esc_url(admin_url('admin.php?page=cff-docs')); ?>"><?php echo esc_html__('Help & documentation', 'cff'); ?></a>
    </div>
    <?php
    $current = sanitize_key($_GET['page'] ?? '');
    $items = [
        'cff' => __('Overview', 'cff'),
        'edit.php?post_type=cff_group' => __('Field Groups', 'cff'),
        'cff-post-types' => __('Post Types', 'cff'),
        'cff-taxonomies' => __('Taxonomies', 'cff'),
        'cff-global-settings' => __('Global Settings', 'cff'),
        'cff-reorder' => __('Reorder', 'cff'),
        'cff-tools' => __('Export / Import', 'cff'),
    ];
    echo '<nav class="cff-section-nav" aria-label="'.esc_attr__('Custom Fields navigation', 'cff').'">';
    foreach ($items as $page => $label) {
        $url = strpos($page, '.php') !== false ? admin_url($page) : admin_url('admin.php?page=' . $page);
        echo '<a href="'.esc_url($url).'"'.($current === $page ? ' aria-current="page"' : '').'>'.esc_html($label).'</a>';
    }
    echo '</nav>';
}

function cff_render_management_toolbar($id, $count) {
    ?>
    <div class="cff-list-tools" hidden>
        <label class="cff-list-search" for="<?php echo esc_attr($id); ?>-search">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <span class="screen-reader-text"><?php echo esc_html__('Search by name or key', 'cff'); ?></span>
            <input type="search" id="<?php echo esc_attr($id); ?>-search" data-cff-search placeholder="<?php echo esc_attr__('Search by name or key…', 'cff'); ?>">
        </label>
        <label class="screen-reader-text" for="<?php echo esc_attr($id); ?>-filter"><?php echo esc_html__('Filter by frontend visibility', 'cff'); ?></label>
        <select id="<?php echo esc_attr($id); ?>-filter" data-cff-filter>
            <option value=""><?php echo esc_html__('All visibility', 'cff'); ?></option>
            <option value="public"><?php echo esc_html__('Public', 'cff'); ?></option>
            <option value="hidden"><?php echo esc_html__('Admin only', 'cff'); ?></option>
        </select>
        <span class="cff-list-count" role="status" aria-live="polite" data-cff-count data-total="<?php echo esc_attr($count); ?>" data-template="<?php echo esc_attr__('%1$d of %2$d shown', 'cff'); ?>"><?php echo esc_html(sprintf(__('%1$d of %2$d shown', 'cff'), $count, $count)); ?></span>
    </div>
    <?php
}

function cff_render_frontend_badge($hidden) {
    echo '<span class="cff-status '.($hidden ? 'cff-status--muted' : 'cff-status--public').'">';
    echo '<span class="dashicons '.($hidden ? 'dashicons-lock' : 'dashicons-admin-site-alt3').'" aria-hidden="true"></span>';
    echo esc_html($hidden ? __('Admin only', 'cff') : __('Public', 'cff')) . '</span>';
}

function cff_content_admin_url($type, $new = false) {
    if ($type === 'attachment') return admin_url($new ? 'media-new.php' : 'upload.php');
    return admin_url(($new ? 'post-new.php' : 'edit.php') . '?post_type=' . sanitize_key($type));
}

function cff_render_definition_actions($kind, $key, $label) {
    $is_post = $kind === 'post';
    $page = $is_post ? 'cff-post-types' : 'cff-taxonomies';
    $action = $is_post ? 'cffp_action' : 'cffp_tax_action';
    $key_name = $is_post ? 'cpt_key' : 'tax_key';
    ?>
    <div class="cff-definition-actions">
        <a class="button" href="<?php echo esc_url(add_query_arg(['page' => $page, 'edit' => $key], admin_url('admin.php')) . '#cff-definition-editor'); ?>"><?php echo esc_html__('Edit settings', 'cff'); ?></a>
        <details class="cff-more-actions">
            <summary><?php echo esc_html__('More', 'cff'); ?><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
            <form method="post">
                <?php
                if ($is_post) wp_nonce_field('cffp_cpt_save', 'cffp_cpt_nonce');
                else wp_nonce_field('cffp_tax_nonce', 'cffp_tax_nonce');
                ?>
                <input type="hidden" name="<?php echo esc_attr($key_name); ?>" value="<?php echo esc_attr($key); ?>">
                <button type="submit" class="button" name="<?php echo esc_attr($action); ?>" value="duplicate"><?php echo esc_html__('Duplicate', 'cff'); ?></button>
                <button type="submit" class="button cff-delete-action cff-requires-confirm" name="<?php echo esc_attr($action); ?>" value="delete" data-confirm-submit="1" data-confirm-title="<?php echo esc_attr__('Delete definition', 'cff'); ?>" data-confirm-message="<?php echo esc_attr(sprintf(__('Remove the definition for "%s"? Existing posts and terms will be kept.', 'cff'), $label)); ?>"><?php echo esc_html__('Delete', 'cff'); ?></button>
            </form>
        </details>
    </div>
    <?php
}

function cff_render_editor_toggle($name, $label, $description, $checked) {
    ?>
    <label class="cff-setting-toggle">
        <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php echo $checked ? 'checked' : ''; ?>>
        <span><strong><?php echo esc_html($label); ?></strong><span class="description"><?php echo esc_html($description); ?></span></span>
    </label>
    <?php
}

function cff_render_editor_footer($label, $url) {
    ?>
    <div class="cff-editor-footer">
        <span class="cff-save-status" role="status" data-clean="<?php echo esc_attr__('No unsaved changes', 'cff'); ?>" data-dirty="<?php echo esc_attr__('Unsaved changes', 'cff'); ?>"><?php echo esc_html__('No unsaved changes', 'cff'); ?></span>
        <div><a class="button" href="<?php echo esc_url($url); ?>"><?php echo esc_html__('Cancel', 'cff'); ?></a><button type="submit" class="button button-primary"><?php echo esc_html($label); ?></button></div>
    </div>
    <?php
}
