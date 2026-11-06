<?php
namespace CFF;

if (!defined('ABSPATH')) exit;

function cff_render_page_hero($title, $description, $icon = 'dashicons-cff', $action_html = '') {
    ?>
    <div class="tk-hero">
        <div class="tk-hero-content">
            <div class="tk-hero-main">
                <div class="tk-hero-icon">
                    <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
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
            <span class="dashicons dashicons-cff" aria-hidden="true"></span>
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

/** Render real WordPress list tables, including server-side search and paging. */
function cff_render_management_table($kind, $definitions) {
    require_once dirname(__DIR__) . '/admin/class-management-list-table.php';
    $table = new Management_List_Table($kind, $definitions);
    $table->prepare_items();
    $table->render();
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
    <div class="row-actions cff-definition-actions">
        <span class="edit"><a href="<?php echo esc_url(add_query_arg(['page' => $page, 'edit' => $key], admin_url('admin.php')) . '#cff-definition-editor'); ?>"><?php echo esc_html__('Edit', 'cff'); ?></a> | </span>
            <form method="post" class="cff-definition-action-form">
                <?php
                if ($is_post) wp_nonce_field('cffp_cpt_save', 'cffp_cpt_nonce');
                else wp_nonce_field('cffp_tax_nonce', 'cffp_tax_nonce');
                ?>
                <input type="hidden" name="<?php echo esc_attr($key_name); ?>" value="<?php echo esc_attr($key); ?>">
                <button type="submit" class="button-link" name="<?php echo esc_attr($action); ?>" value="duplicate" title="<?php echo esc_attr__('Duplicate', 'cff'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Duplicate %s', 'cff'), $label)); ?>"><?php echo esc_html__('Duplicate', 'cff'); ?></button>
                <span aria-hidden="true"> | </span><button type="submit" class="button-link button-link-delete cff-requires-confirm" name="<?php echo esc_attr($action); ?>" value="delete" title="<?php echo esc_attr__('Delete', 'cff'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Delete %s', 'cff'), $label)); ?>" data-confirm-submit="1" data-confirm-title="<?php echo esc_attr__('Delete definition', 'cff'); ?>" data-confirm-message="<?php echo esc_attr(sprintf(__('Remove the definition for "%s"? Existing posts and terms will be kept.', 'cff'), $label)); ?>"><?php echo esc_html__('Delete', 'cff'); ?></button>
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
    <section class="postbox cff-definition-box cff-definition-savebox">
        <div class="postbox-header"><h2><?php echo esc_html__('Save definition', 'cff'); ?></h2></div>
        <div class="inside">
            <div class="cff-editor-footer">
                <span class="cff-save-status" role="status" data-clean="<?php echo esc_attr__('No unsaved changes', 'cff'); ?>" data-dirty="<?php echo esc_attr__('Unsaved changes', 'cff'); ?>"><?php echo esc_html__('No unsaved changes', 'cff'); ?></span>
                <div><a class="button" href="<?php echo esc_url($url); ?>"><?php echo esc_html__('Cancel', 'cff'); ?></a><button type="submit" class="button button-primary"><?php echo esc_html($label); ?></button></div>
            </div>
        </div>
    </section>
    <?php
}
