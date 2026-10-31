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
            <span class="dashicons dashicons-feedback" aria-hidden="true"></span>
            <span class="tk-header-name">Custom Fields Framework Pro</span>
            <span class="tk-header-version">v<?php echo esc_html(defined('CFFP_VERSION') ? CFFP_VERSION : '2.3'); ?></span>
        </div>

        <a class="cff-help-link" href="<?php echo esc_url(admin_url('admin.php?page=cff-docs')); ?>"><?php echo esc_html__('Help & documentation', 'cff'); ?></a>
    </div>
    <?php
    $current = sanitize_key($_GET['page'] ?? '');
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && in_array($screen->id, ['edit-cff_group', 'cff_group'], true)) $current = 'edit.php?post_type=cff_group';
    $items = [
        'cff' => __('Overview', 'cff'),
        'edit.php?post_type=cff_group' => __('Field Groups', 'cff'),
        'cff-post-types' => __('Post Types', 'cff'),
        'cff-taxonomies' => __('Taxonomies', 'cff'),
        'cff-global-settings' => __('Global Settings', 'cff'),
        'cff-reorder' => __('Reorder', 'cff'),
        'cff-tools' => __('Export / Import', 'cff'),
        'cff-import-content' => __('Import Content', 'cff'),
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

function cff_render_icon_link($url, $icon, $label) {
    echo '<a class="button cff-icon-button" href="' . esc_url($url) . '" title="' . esc_attr($label) . '" aria-label="' . esc_attr($label) . '"><span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html($label) . '</span></a>';
}

/** Keep WordPress action URLs, nonce parameters and inline-edit attributes intact. */
function cff_field_group_icon_actions($actions, $post) {
    if (!$post || $post->post_type !== 'cff_group') return $actions;
    $icons = ['edit' => 'edit', 'inline hide-if-no-js' => 'edit-page', 'trash' => 'trash', 'delete' => 'trash',
        'untrash' => 'undo', 'view' => 'visibility', 'cff_duplicate' => 'admin-page', 'cff_export_group' => 'download'];
    foreach ($actions as $key => $html) {
        if (!is_string($html) || strpos($html, 'cff-icon-button') !== false) continue;
        $icon = $icons[$key] ?? 'admin-generic';
        $actions[$key] = preg_replace_callback('~<(a|button)\b([^>]*)>(.*?)</\1>~is', function($match) use ($icon) {
            $attributes = $match[2];
            $attributes = preg_replace('/\bclass=("|\')(.*?)\1/is', 'class=$1$2 cff-icon-button$1', $attributes, 1, $count);
            if (!$count) $attributes .= ' class="cff-icon-button"';
            if (!preg_match('/\btitle\s*=/i', $attributes)) $attributes .= ' title="' . esc_attr(html_entity_decode(strip_tags($match[3]), ENT_QUOTES, 'UTF-8')) . '"';
            return '<' . $match[1] . $attributes . '><span class="dashicons dashicons-' . esc_attr($icon) . '" aria-hidden="true"></span><span class="screen-reader-text">' . $match[3] . '</span></' . $match[1] . '>';
        }, $actions[$key]);
    }
    return $actions;
}
add_filter('post_row_actions', __NAMESPACE__ . '\\cff_field_group_icon_actions', 110, 2);
add_filter('page_row_actions', __NAMESPACE__ . '\\cff_field_group_icon_actions', 110, 2);

add_filter('manage_edit-cff_group_columns', function($columns) {
    $columns['cff_actions'] = __('Actions', 'cff');
    return $columns;
}, 30);

add_action('manage_cff_group_posts_custom_column', function($column, $post_id) {
    if ($column !== 'cff_actions') return;
    // Move WordPress-generated actions here in the browser, preserving permissions and nonce URLs.
    echo '<div class="cff-field-group-actions" data-cff-group-actions></div>';
    echo '<noscript><span class="description">' . esc_html__('Use the action buttons below the title.', 'cff') . '</span></noscript>';
}, 20, 2);

function cff_render_definition_actions($kind, $key, $label) {
    $is_post = $kind === 'post';
    $page = $is_post ? 'cff-post-types' : 'cff-taxonomies';
    $action = $is_post ? 'cffp_action' : 'cffp_tax_action';
    $key_name = $is_post ? 'cpt_key' : 'tax_key';
    ?>
    <div class="cff-definition-actions">
        <?php cff_render_icon_link(add_query_arg(['page' => $page, 'edit' => $key], admin_url('admin.php')) . '#cff-definition-editor', 'dashicons-edit', sprintf(__('Edit settings for %s', 'cff'), $label)); ?>
            <form method="post" class="cff-definition-action-form">
                <?php
                if ($is_post) wp_nonce_field('cffp_cpt_save', 'cffp_cpt_nonce');
                else wp_nonce_field('cffp_tax_nonce', 'cffp_tax_nonce');
                ?>
                <input type="hidden" name="<?php echo esc_attr($key_name); ?>" value="<?php echo esc_attr($key); ?>">
                <button type="submit" class="button cff-icon-button" name="<?php echo esc_attr($action); ?>" value="duplicate" title="<?php echo esc_attr__('Duplicate', 'cff'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Duplicate %s', 'cff'), $label)); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html__('Duplicate', 'cff'); ?></span></button>
                <button type="submit" class="button cff-icon-button cff-delete-action cff-requires-confirm" name="<?php echo esc_attr($action); ?>" value="delete" title="<?php echo esc_attr__('Delete', 'cff'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Delete %s', 'cff'), $label)); ?>" data-confirm-submit="1" data-confirm-title="<?php echo esc_attr__('Delete definition', 'cff'); ?>" data-confirm-message="<?php echo esc_attr(sprintf(__('Remove the definition for "%s"? Existing posts and terms will be kept.', 'cff'), $label)); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html__('Delete', 'cff'); ?></span></button>
            </form>
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
