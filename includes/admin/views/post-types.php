<?php
namespace CFF;
if (!defined('ABSPATH')) exit;
$pub = $editing ? !empty($edit_def['public']) : true;
$arc = $editing ? !empty($edit_def['has_archive']) : true;
$rst = $editing ? !empty($edit_def['show_in_rest']) : true;
$hide_frontend = !empty($edit_def['hide_frontend']);
$supports_selected = $editing ? ($edit_def['supports'] ?? ['title', 'editor']) : ['title', 'editor'];
if (!empty($edit_def['list_thumbnail']) && !in_array('thumbnail', $supports_selected, true)) $supports_selected[] = 'thumbnail';
$langs = $this->polylang_languages();
$show_editor = $editing || (isset($_GET['action']) && $_GET['action'] === 'add');
$list_url = admin_url('admin.php?page=cff-post-types');
$add_url = add_query_arg('action', 'add', $list_url);
?>
<div class="wrap tk-wrap cff-admin cff-management<?php echo $show_editor ? ' cff-definition-screen' : ''; ?>">
    <?php
    cff_render_header_branding();
    if ($show_editor) {
        cff_render_page_hero($editing ? __('Edit post type', 'cff') : __('Add post type', 'cff'), $editing ? sprintf(__('Settings for %s.', 'cff'), ($edit_def['plural'] ?? '') ?: $edit_key) : __('Define a collection and choose what editors can manage.', 'cff'), 'dashicons-admin-post', '<a class="button" href="'.esc_url($list_url).'">'.esc_html__('Back to post types', 'cff').'</a>');
    } else {
        cff_render_page_hero(__('Post Types', 'cff'), __('Manage your content collections and choose how each one appears on your website.', 'cff'), 'dashicons-admin-post', '<a class="button button-primary" href="'.esc_url($add_url).'">'.esc_html__('Add post type', 'cff').'</a>');
    }
    ?>
    <?php if (!$show_editor) : ?>
    <section class="tk-card cff-collection" data-cff-collection>
        <div class="cff-card-heading"><div><h2><?php echo esc_html__('Your post types', 'cff'); ?></h2><p><?php echo esc_html__('Click a collection to edit its settings.', 'cff'); ?></p></div><span class="cff-total"><?php echo esc_html(count($defs)); ?></span></div>
        <?php cff_render_management_table('post', $defs); ?>
    </section>

    <?php else : ?>
    <div id="cff-definition-editor" class="cff-definition-editor">
        <form method="post" class="cff-cpt-form cff-definition-form" data-cff-editor>
            <?php wp_nonce_field('cffp_cpt_save', 'cffp_cpt_nonce'); ?>
            <input type="hidden" name="cffp_action" value="<?php echo $editing ? 'update' : 'add'; ?>">
            <input type="hidden" name="cpt_key" value="<?php echo esc_attr($editing ? $edit_key : ''); ?>">
            <div id="poststuff" class="cff-definition-workspace"><div id="post-body" class="metabox-holder columns-2"><div id="postbox-container-2" class="postbox-container cff-editor-main">
                <section class="postbox cff-definition-box"><div class="postbox-header"><h2><?php echo esc_html__('Basic details', 'cff'); ?></h2></div><div class="inside"><p class="description"><?php echo esc_html__('Choose the names your content editors will see.', 'cff'); ?></p>
                    <div class="cff-form-grid">
                        <div class="cff-form-field"><label for="cff-cpt-singular"><?php echo esc_html__('Singular name', 'cff'); ?> <span class="required">*</span></label><input id="cff-cpt-singular" name="cpt_singular" type="text" required placeholder="Project" value="<?php echo esc_attr($edit_def['singular'] ?? ''); ?>"><p class="description"><?php echo esc_html__('For one item, e.g. Project.', 'cff'); ?></p></div>
                        <div class="cff-form-field"><label for="cff-cpt-plural"><?php echo esc_html__('Plural name', 'cff'); ?> <span class="required">*</span></label><input id="cff-cpt-plural" name="cpt_plural" type="text" required placeholder="Projects" value="<?php echo esc_attr($edit_def['plural'] ?? ''); ?>"><p class="description"><?php echo esc_html__('For the collection, e.g. Projects.', 'cff'); ?></p></div>
                    </div>
                    <div class="cff-form-field"><label for="cff-cpt-slug"><?php echo esc_html__('URL slug', 'cff'); ?></label><input id="cff-cpt-slug" type="text" name="cpt_slug" placeholder="projects" value="<?php echo esc_attr($edit_def['slug'] ?? ''); ?>"><p class="description"><?php echo esc_html__('Generated from the plural name. Changing an existing slug changes its public URLs.', 'cff'); ?></p></div>
                    <p class="cff-key-preview"><?php echo esc_html__('Content key:', 'cff'); ?> <code data-cff-key-preview><?php echo esc_html($editing ? $edit_key : __('Generated from singular name', 'cff')); ?></code></p>
                </div></section>
                <section class="postbox cff-definition-box"><div class="postbox-header"><h2><?php echo esc_html__('Content editor', 'cff'); ?></h2></div><div class="inside"><p class="description"><?php echo esc_html__('Choose which fields are available when adding or editing content.', 'cff'); ?></p>
                    <div class="cff-check-grid"><?php foreach ($supports_all as $value => $label): ?><label><input type="checkbox" name="cpt_supports[]" value="<?php echo esc_attr($value); ?>" <?php echo in_array($value, $supports_selected, true) ? 'checked' : ''; ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></div>
                    <?php cff_render_editor_toggle('cpt_list_thumbnail', __('Show thumbnails in content list', 'cff'), __('Also enables Featured Image support.', 'cff'), !empty($edit_def['list_thumbnail'])); ?>
                </div></section>
                <?php include __DIR__ . '/translations.php'; ?>
                <details class="cff-definition-box cff-definition-advanced"><summary><?php echo esc_html__('Menu & advanced settings', 'cff'); ?></summary><div class="cff-advanced-body">
                    <div class="cff-form-grid"><div class="cff-form-field"><label for="cff-menu-icon"><?php echo esc_html__('Menu icon', 'cff'); ?></label><input id="cff-menu-icon" type="text" name="cpt_menu_icon" placeholder="dashicons-admin-post" value="<?php echo esc_attr($edit_def['menu_icon'] ?? ''); ?>"><p class="description"><?php echo esc_html__('Use a Dashicons class or an image URL.', 'cff'); ?></p></div><div class="cff-form-field"><label for="cff-menu-position"><?php echo esc_html__('Menu position', 'cff'); ?></label><input id="cff-menu-position" type="number" min="0" name="cpt_menu_position" value="<?php echo esc_attr($edit_def['menu_position'] ?? 25); ?>"><p class="description"><?php echo esc_html__('Use 0 for the WordPress default.', 'cff'); ?></p></div></div>
                    <div class="cff-form-field"><label for="cff-duplicate-status"><?php echo esc_html__('Visibility when duplicating this post type', 'cff'); ?></label><select id="cff-duplicate-status" name="cpt_duplicate_status"><option value="active" <?php echo ($edit_def['duplicate_status'] ?? 'active') === 'active' ? 'selected' : ''; ?>><?php echo esc_html__('Public enabled', 'cff'); ?></option><option value="inactive" <?php echo ($edit_def['duplicate_status'] ?? '') === 'inactive' ? 'selected' : ''; ?>><?php echo esc_html__('Public disabled', 'cff'); ?></option></select><p class="description"><?php echo esc_html__('Applies to the collection definition. Existing posts keep their status.', 'cff'); ?></p></div>
                </div></details>
            </div><aside id="postbox-container-1" class="postbox-container cff-editor-sidebar"><?php cff_render_editor_footer($editing ? __('Save changes', 'cff') : __('Create post type', 'cff'), admin_url('admin.php?page=cff-post-types')); ?><section class="postbox cff-definition-box"><div class="postbox-header"><h2><?php echo esc_html__('Frontend visibility', 'cff'); ?></h2></div><div class="inside">
                <?php cff_render_editor_toggle('cpt_hide_frontend', __('Hide from frontend', 'cff'), __('Manage content in admin without public pages, search results, or WordPress sitemap entries.', 'cff'), $hide_frontend); ?>
                <div class="cff-visibility-note" role="status" data-cff-visibility-summary data-hidden="<?php echo esc_attr__('Admin only. Public page settings below are ignored.', 'cff'); ?>" data-public="<?php echo esc_attr__('Frontend follows the public page settings below.', 'cff'); ?>" data-private="<?php echo esc_attr__('Public visibility is off. Enable it or choose Hide from frontend to keep the admin available.', 'cff'); ?>"><?php echo esc_html($hide_frontend ? __('Admin only. Public page settings below are ignored.', 'cff') : ($pub ? __('Frontend follows the public page settings below.', 'cff') : __('Public visibility is off. Enable it or choose Hide from frontend to keep the admin available.', 'cff'))); ?></div>
                <div data-cff-public-settings>
                    <?php
                    cff_render_editor_toggle('cpt_public', __('Public visibility', 'cff'), __('Allow this collection on the website.', 'cff'), $pub);
                    cff_render_editor_toggle('cpt_archive', __('Archive page', 'cff'), __('A public listing of this collection.', 'cff'), $arc);
                    cff_render_editor_toggle('cpt_block_single', __('No single view', 'cff'), __('Disable individual content pages.', 'cff'), !empty($edit_def['block_single']));
                    ?>
                </div>
            </div></section><section class="postbox cff-definition-box"><div class="postbox-header"><h2><?php echo esc_html__('Integrations', 'cff'); ?></h2></div><div class="inside"><?php cff_render_editor_toggle('cpt_rest', __('REST API', 'cff'), __('Enable the block editor and API access. Controlled separately from frontend pages.', 'cff'), $rst); ?></div></section></aside></div></div>
        </form>
    </div>
    <?php endif; ?>
</div>
