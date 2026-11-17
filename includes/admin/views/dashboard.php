<?php
namespace CFF;
if (!defined('ABSPATH')) exit;
$definitions = get_option('cffp_post_types', []);
$tax_definitions = get_option('cffp_taxonomies', []);
$types = get_post_types(['show_ui' => true], 'objects');
unset($types['cff_group'], $types['cff_options']);
$group_count = (int) (wp_count_posts('cff_group')->publish ?? 0);
$pt_count = is_array($definitions) ? count($definitions) : 0;
$tax_count = is_array($tax_definitions) ? count($tax_definitions) : 0;
$build_links = [
    ['edit.php?post_type=cff_group', 'dashicons-layout', __('Field Groups', 'cff'), __('Choose the fields editors fill in.', 'cff')],
    ['admin.php?page=cff-post-types', 'dashicons-admin-post', __('Post Types', 'cff'), __('Content collections and their settings.', 'cff')],
    ['admin.php?page=cff-taxonomies', 'dashicons-category', __('Taxonomies', 'cff'), __('Categories and tags for your content.', 'cff')],
    ['admin.php?page=cff-global-settings', 'dashicons-admin-generic', __('Global Settings', 'cff'), __('Site-wide content and options.', 'cff')],
];
$tool_links = [
    ['admin.php?page=cff-reorder', 'dashicons-sort', __('Reorder', 'cff'), __('Set the order of items and groups.', 'cff')],
    ['admin.php?page=cff-tools', 'dashicons-migrate', __('Export / Import', 'cff'), __('Move field groups between sites.', 'cff')],
    ['admin.php?page=cff-import-content', 'dashicons-upload', __('Import Content', 'cff'), __('Bring in content from JSON.', 'cff')],
    ['admin.php?page=cff-docs', 'dashicons-editor-help', __('Help & documentation', 'cff'), __('Guides and field reference.', 'cff')],
];
$hero_actions = '<a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type=cff_group')).'">'.esc_html__('Add field group', 'cff').'</a> '
    . '<a class="button" href="'.esc_url(admin_url('admin.php?page=cff-global-settings')).'">'.esc_html__('Edit global content', 'cff').'</a>';
?>
<div class="wrap tk-wrap cff-admin cff-management cff-dashboard">
    <?php
    cff_render_header_branding();
    cff_render_page_hero(__('Content overview', 'cff'), __('Everything you need to add, organize, and update your website content.', 'cff'), 'dashicons-cff', $hero_actions);
    ?>
    <div class="cff-dashboard-grid">
        <div class="cff-dashboard-main">
            <div class="cff-overview-stats">
                <?php $this->render_stat_card(__('Field Groups', 'cff'), $group_count, 'dashicons-layout', admin_url('edit.php?post_type=cff_group')); ?>
                <?php $this->render_stat_card(__('Post Types', 'cff'), $pt_count, 'dashicons-admin-post', admin_url('admin.php?page=cff-post-types')); ?>
                <?php $this->render_stat_card(__('Taxonomies', 'cff'), $tax_count, 'dashicons-category', admin_url('admin.php?page=cff-taxonomies')); ?>
            </div>
            <section class="tk-card cff-collection" data-cff-collection>
                <div class="cff-card-heading"><div><h2><?php echo esc_html__('Manage your content', 'cff'); ?></h2><p><?php echo esc_html__('Choose a collection to edit existing items or add something new.', 'cff'); ?></p></div></div>
                <?php cff_render_management_table('content', $types); ?>
            </section>
        </div>
        <aside class="cff-dashboard-side">
            <?php foreach ([[__('Build', 'cff'), $build_links], [__('Tools', 'cff'), $tool_links]] as $panel) : ?>
                <section class="tk-card cff-dashboard-links">
                    <h2><?php echo esc_html($panel[0]); ?></h2>
                    <ul>
                        <?php foreach ($panel[1] as $link) : ?>
                            <li>
                                <a href="<?php echo esc_url(admin_url($link[0])); ?>">
                                    <span class="dashicons <?php echo esc_attr($link[1]); ?>" aria-hidden="true"></span>
                                    <span class="cff-dashboard-link-text"><strong><?php echo esc_html($link[2]); ?></strong><span><?php echo esc_html($link[3]); ?></span></span>
                                    <span class="dashicons dashicons-arrow-right-alt2 cff-dashboard-chevron" aria-hidden="true"></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </aside>
    </div>
</div>
