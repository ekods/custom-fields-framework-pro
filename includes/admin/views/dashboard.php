<?php
namespace CFF;
if (!defined('ABSPATH')) exit;
$definitions = get_option('cffp_post_types', []);
$tax_definitions = get_option('cffp_taxonomies', []);
$types = get_post_types(['show_ui' => true], 'objects');
unset($types['cff_group'], $types['cff_options']);
?>
<div class="wrap tk-wrap cff-admin cff-management">
    <?php
    cff_render_header_branding();
    cff_render_page_hero(__('Content overview', 'cff'), __('Everything you need to add, organize, and update your website content.', 'cff'), 'dashicons-feedback', '<a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=cff-global-settings')).'">'.esc_html__('Edit global content', 'cff').'</a>');
    ?>
    <div class="cff-overview-stats">
        <?php $this->render_stat_card(__('Field Groups', 'cff'), wp_count_posts('cff_group')->publish, 'dashicons-layout', admin_url('edit.php?post_type=cff_group')); ?>
        <?php $this->render_stat_card(__('Post Types', 'cff'), is_array($definitions) ? count($definitions) : 0, 'dashicons-admin-post', admin_url('admin.php?page=cff-post-types')); ?>
        <?php $this->render_stat_card(__('Taxonomies', 'cff'), is_array($tax_definitions) ? count($tax_definitions) : 0, 'dashicons-category', admin_url('admin.php?page=cff-taxonomies')); ?>
    </div>
    <section class="tk-card cff-collection" data-cff-collection>
        <div class="cff-card-heading"><div><h2><?php echo esc_html__('Manage your content', 'cff'); ?></h2><p><?php echo esc_html__('Choose a collection to edit existing items or add something new.', 'cff'); ?></p></div></div>
        <?php cff_render_management_toolbar('cff-overview', count($types)); ?>
        <div class="tk-table-scroll"><table class="tk-table cff-management-table"><thead><tr><th scope="col"><?php echo esc_html__('Collection', 'cff'); ?></th><th scope="col"><?php echo esc_html__('Content status', 'cff'); ?></th><th scope="col" class="cff-column-frontend"><?php echo esc_html__('Frontend', 'cff'); ?></th><th scope="col" class="cff-column-actions"><?php echo esc_html__('Actions', 'cff'); ?></th></tr></thead><tbody>
        <?php foreach ($types as $key => $type): $counts = wp_count_posts($key, 'readable'); $hidden = empty($type->public); ?>
            <tr data-cff-row data-visibility="<?php echo $hidden ? 'hidden' : 'public'; ?>" data-search="<?php echo esc_attr($type->labels->name . ' ' . $key); ?>">
                <td data-label="<?php echo esc_attr__('Collection', 'cff'); ?>"><div class="cff-collection-name"><a href="<?php echo esc_url(cff_content_admin_url($key)); ?>"><strong><?php echo esc_html($type->labels->name); ?></strong></a><code><?php echo esc_html($key); ?></code></div></td>
                <td data-label="<?php echo esc_attr__('Status', 'cff'); ?>"><div class="cff-content-counts"><span><?php echo esc_html(sprintf(__('%d published', 'cff'), $counts->publish ?? 0)); ?></span><span><?php echo esc_html(sprintf(__('%d drafts', 'cff'), $counts->draft ?? 0)); ?></span></div></td>
                <td class="cff-column-frontend" data-label="<?php echo esc_attr__('Frontend', 'cff'); ?>"><?php cff_render_frontend_badge($hidden); ?></td>
                <td class="cff-column-actions" data-label="<?php echo esc_attr__('Actions', 'cff'); ?>"><div class="cff-table-actions"><?php cff_render_icon_link(cff_content_admin_url($key), 'dashicons-list-view', sprintf(__('Manage %s content', 'cff'), $type->labels->name)); cff_render_icon_link(cff_content_admin_url($key, true), 'dashicons-plus-alt2', sprintf(__('Add new %s', 'cff'), $type->labels->name)); ?></div></td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
        <p class="cff-no-results" data-cff-no-results hidden><?php echo esc_html__('No collections match your search. Try another name or clear the filter.', 'cff'); ?></p>
    </section>
    <section class="cff-next-steps" aria-label="<?php echo esc_attr__('Content setup shortcuts', 'cff'); ?>">
        <a class="tk-card" href="<?php echo esc_url(admin_url('edit.php?post_type=cff_group')); ?>"><span class="dashicons dashicons-layout" aria-hidden="true"></span><strong><?php echo esc_html__('Customize content fields', 'cff'); ?></strong><span><?php echo esc_html__('Choose the information your editors can manage.', 'cff'); ?></span></a>
        <a class="tk-card" href="<?php echo esc_url(admin_url('admin.php?page=cff-taxonomies')); ?>"><span class="dashicons dashicons-category" aria-hidden="true"></span><strong><?php echo esc_html__('Organize with categories', 'cff'); ?></strong><span><?php echo esc_html__('Group related content with reusable terms.', 'cff'); ?></span></a>
        <a class="tk-card" href="<?php echo esc_url(admin_url('admin.php?page=cff-reorder')); ?>"><span class="dashicons dashicons-sort" aria-hidden="true"></span><strong><?php echo esc_html__('Arrange content order', 'cff'); ?></strong><span><?php echo esc_html__('Set the order of items in your collections.', 'cff'); ?></span></a>
    </section>
</div>
