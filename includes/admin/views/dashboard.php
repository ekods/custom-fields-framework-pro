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
    cff_render_page_hero(__('Content overview', 'cff'), __('Everything you need to add, organize, and update your website content.', 'cff'), 'dashicons-cff', '<a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=cff-global-settings')).'">'.esc_html__('Edit global content', 'cff').'</a>');
    ?>
    <div class="cff-overview-stats">
        <?php $this->render_stat_card(__('Field Groups', 'cff'), wp_count_posts('cff_group')->publish, 'dashicons-layout', admin_url('edit.php?post_type=cff_group')); ?>
        <?php $this->render_stat_card(__('Post Types', 'cff'), is_array($definitions) ? count($definitions) : 0, 'dashicons-admin-post', admin_url('admin.php?page=cff-post-types')); ?>
        <?php $this->render_stat_card(__('Taxonomies', 'cff'), is_array($tax_definitions) ? count($tax_definitions) : 0, 'dashicons-category', admin_url('admin.php?page=cff-taxonomies')); ?>
    </div>
    <section class="tk-card cff-collection" data-cff-collection>
        <div class="cff-card-heading"><div><h2><?php echo esc_html__('Manage your content', 'cff'); ?></h2><p><?php echo esc_html__('Choose a collection to edit existing items or add something new.', 'cff'); ?></p></div></div>
        <?php cff_render_management_table('content', $types); ?>
    </section>
    <section class="cff-next-steps" aria-label="<?php echo esc_attr__('Content setup shortcuts', 'cff'); ?>">
        <a class="tk-card" href="<?php echo esc_url(admin_url('edit.php?post_type=cff_group')); ?>"><span class="dashicons dashicons-layout" aria-hidden="true"></span><strong><?php echo esc_html__('Customize content fields', 'cff'); ?></strong><span><?php echo esc_html__('Choose the information your editors can manage.', 'cff'); ?></span></a>
        <a class="tk-card" href="<?php echo esc_url(admin_url('admin.php?page=cff-taxonomies')); ?>"><span class="dashicons dashicons-category" aria-hidden="true"></span><strong><?php echo esc_html__('Organize with categories', 'cff'); ?></strong><span><?php echo esc_html__('Group related content with reusable terms.', 'cff'); ?></span></a>
        <a class="tk-card" href="<?php echo esc_url(admin_url('admin.php?page=cff-reorder')); ?>"><span class="dashicons dashicons-sort" aria-hidden="true"></span><strong><?php echo esc_html__('Arrange content order', 'cff'); ?></strong><span><?php echo esc_html__('Set the order of items in your collections.', 'cff'); ?></span></a>
    </section>
</div>
