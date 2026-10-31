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
?>
<div class="wrap tk-wrap cff-admin cff-management">
    <?php
    cff_render_header_branding();
    cff_render_page_hero(__('Post Types', 'cff'), __('Manage your content collections and choose how each one appears on your website.', 'cff'), 'dashicons-admin-post', '<a class="button button-primary" data-cff-open-editor href="'.esc_url(admin_url('admin.php?page=cff-post-types') . '#cff-definition-editor').'">'.esc_html__('Add post type', 'cff').'</a>');
    ?>
    <section class="tk-card cff-collection" data-cff-collection>
        <div class="cff-card-heading"><div><h2><?php echo esc_html__('Your post types', 'cff'); ?></h2><p><?php echo esc_html__('Open a collection to manage its content, or edit its settings below.', 'cff'); ?></p></div><span class="cff-total"><?php echo esc_html(count($defs)); ?></span></div>
        <?php if (!$defs): ?>
            <div class="cff-empty-state"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span><h3><?php echo esc_html__('Create your first content collection', 'cff'); ?></h3><p><?php echo esc_html__('Use a post type for content such as Projects, Services, or Team Members.', 'cff'); ?></p><a class="button button-primary" href="#cff-definition-editor" data-cff-open-editor><?php echo esc_html__('Add post type', 'cff'); ?></a></div>
        <?php else: cff_render_management_toolbar('cff-types', count($defs)); ?>
            <div class="tk-table-scroll"><table class="tk-table cff-management-table"><thead><tr><th scope="col"><?php echo esc_html__('Content collection', 'cff'); ?></th><th scope="col" class="cff-column-frontend"><?php echo esc_html__('Frontend', 'cff'); ?></th><th scope="col" class="cff-column-actions"><?php echo esc_html__('Actions', 'cff'); ?></th></tr></thead><tbody>
            <?php foreach ($defs as $key => $def):
                $label = ($def['plural'] ?? '') ?: $key;
                $hidden = !empty($def['hide_frontend']) || empty($def['public']);
                $available = !empty($def['hide_frontend']) || (array_key_exists('show_ui', $def) ? !empty($def['show_ui']) : !empty($def['public']));
            ?>
                <tr data-cff-row data-visibility="<?php echo $hidden ? 'hidden' : 'public'; ?>" data-search="<?php echo esc_attr($label . ' ' . $key . ' ' . ($def['slug'] ?? '')); ?>">
                    <td data-label="<?php echo esc_attr__('Collection', 'cff'); ?>"><div class="cff-collection-name"><strong><?php echo esc_html($label); ?></strong><code><?php echo esc_html($key); ?></code></div>
                        <details class="cff-technical"><summary><?php echo esc_html__('View details', 'cff'); ?></summary><div><span><?php echo esc_html__('URL slug:', 'cff'); ?> <code><?php echo esc_html($def['slug'] ?? $key); ?></code></span><span><?php echo esc_html__('REST API:', 'cff'); ?> <?php echo esc_html(!empty($def['show_in_rest']) ? __('Enabled', 'cff') : __('Disabled', 'cff')); ?></span>
                        <?php if (!$hidden): $template_slug = sanitize_title(($def['slug'] ?? '') ?: $key); ?>
                            <?php if (!empty($def['has_archive'])): ?><code><?php echo esc_html('archive-' . $template_slug . '.php'); ?></code><?php endif; ?>
                            <?php if (empty($def['block_single'])): ?><code><?php echo esc_html('single-' . $template_slug . '.php'); ?></code><?php endif; ?>
                        <?php endif; ?></div></details>
                    </td>
                    <td class="cff-column-frontend" data-label="<?php echo esc_attr__('Frontend', 'cff'); ?>"><?php cff_render_frontend_badge($hidden); ?><?php if (!$available): ?><span class="cff-cell-note"><?php echo esc_html__('Admin UI disabled', 'cff'); ?></span><?php endif; ?></td>
                    <td class="cff-column-actions" data-label="<?php echo esc_attr__('Actions', 'cff'); ?>"><div class="cff-table-actions"><?php if ($available): cff_render_icon_link(cff_content_admin_url($key), 'dashicons-list-view', sprintf(__('Manage %s content', 'cff'), $label)); cff_render_icon_link(cff_content_admin_url($key, true), 'dashicons-plus-alt2', sprintf(__('Add new %s', 'cff'), $label)); endif; cff_render_definition_actions('post', $key, $label); ?></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <p class="cff-no-results" data-cff-no-results hidden><?php echo esc_html__('No collections match your search. Try another name or clear the filter.', 'cff'); ?></p>
        <?php endif; ?>
    </section>

    <details class="cff-editor-disclosure" id="cff-definition-editor" <?php echo $editing || !$defs ? 'open' : ''; ?>>
        <summary><span><strong><?php echo esc_html($editing ? sprintf(__('Edit %s', 'cff'), $edit_def['plural'] ?: $edit_key) : __('Add a post type', 'cff')); ?></strong><span class="description"><?php echo esc_html__('Set up labels, editor fields, and frontend visibility.', 'cff'); ?></span></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
        <form method="post" class="cff-cpt-form cff-definition-form" data-cff-editor>
            <?php wp_nonce_field('cffp_cpt_save', 'cffp_cpt_nonce'); ?>
            <input type="hidden" name="cffp_action" value="<?php echo $editing ? 'update' : 'add'; ?>">
            <input type="hidden" name="cpt_key" value="<?php echo esc_attr($editing ? $edit_key : ''); ?>">
            <?php cff_render_editor_footer($editing ? __('Save changes', 'cff') : __('Create post type', 'cff'), admin_url('admin.php?page=cff-post-types')); ?>
            <div class="cff-editor-grid"><div class="cff-editor-main">
                <section class="tk-card cff-form-section"><h3><?php echo esc_html__('Basic details', 'cff'); ?></h3><p class="description"><?php echo esc_html__('Choose the names your content editors will see.', 'cff'); ?></p>
                    <div class="cff-form-grid">
                        <div class="cff-form-field"><label for="cff-cpt-singular"><?php echo esc_html__('Singular name', 'cff'); ?> <span class="required">*</span></label><input id="cff-cpt-singular" name="cpt_singular" type="text" required placeholder="Project" value="<?php echo esc_attr($edit_def['singular'] ?? ''); ?>"><p class="description"><?php echo esc_html__('For one item, e.g. Project.', 'cff'); ?></p></div>
                        <div class="cff-form-field"><label for="cff-cpt-plural"><?php echo esc_html__('Plural name', 'cff'); ?> <span class="required">*</span></label><input id="cff-cpt-plural" name="cpt_plural" type="text" required placeholder="Projects" value="<?php echo esc_attr($edit_def['plural'] ?? ''); ?>"><p class="description"><?php echo esc_html__('For the collection, e.g. Projects.', 'cff'); ?></p></div>
                    </div>
                    <div class="cff-form-field"><label for="cff-cpt-slug"><?php echo esc_html__('URL slug', 'cff'); ?></label><input id="cff-cpt-slug" type="text" name="cpt_slug" placeholder="projects" value="<?php echo esc_attr($edit_def['slug'] ?? ''); ?>"><p class="description"><?php echo esc_html__('Generated from the plural name. Changing an existing slug changes its public URLs.', 'cff'); ?></p></div>
                    <p class="cff-key-preview"><?php echo esc_html__('Content key:', 'cff'); ?> <code data-cff-key-preview><?php echo esc_html($editing ? $edit_key : __('Generated from singular name', 'cff')); ?></code></p>
                </section>
                <section class="tk-card cff-form-section"><h3><?php echo esc_html__('Content editor', 'cff'); ?></h3><p class="description"><?php echo esc_html__('Choose which fields are available when adding or editing content.', 'cff'); ?></p>
                    <div class="cff-check-grid"><?php foreach ($supports_all as $value => $label): ?><label><input type="checkbox" name="cpt_supports[]" value="<?php echo esc_attr($value); ?>" <?php echo in_array($value, $supports_selected, true) ? 'checked' : ''; ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></div>
                    <?php cff_render_editor_toggle('cpt_list_thumbnail', __('Show thumbnails in content list', 'cff'), __('Also enables Featured Image support.', 'cff'), !empty($edit_def['list_thumbnail'])); ?>
                </section>
                <?php include __DIR__ . '/translations.php'; ?>
                <details class="tk-card cff-advanced-section"><summary><?php echo esc_html__('Menu & advanced settings', 'cff'); ?></summary><div class="cff-advanced-body">
                    <div class="cff-form-grid"><div class="cff-form-field"><label for="cff-menu-icon"><?php echo esc_html__('Menu icon', 'cff'); ?></label><input id="cff-menu-icon" type="text" name="cpt_menu_icon" placeholder="dashicons-admin-post" value="<?php echo esc_attr($edit_def['menu_icon'] ?? ''); ?>"><p class="description"><?php echo esc_html__('Use a Dashicons class or an image URL.', 'cff'); ?></p></div><div class="cff-form-field"><label for="cff-menu-position"><?php echo esc_html__('Menu position', 'cff'); ?></label><input id="cff-menu-position" type="number" min="0" name="cpt_menu_position" value="<?php echo esc_attr($edit_def['menu_position'] ?? 25); ?>"><p class="description"><?php echo esc_html__('Use 0 for the WordPress default.', 'cff'); ?></p></div></div>
                    <div class="cff-form-field"><label for="cff-duplicate-status"><?php echo esc_html__('Visibility when duplicating this post type', 'cff'); ?></label><select id="cff-duplicate-status" name="cpt_duplicate_status"><option value="active" <?php echo ($edit_def['duplicate_status'] ?? 'active') === 'active' ? 'selected' : ''; ?>><?php echo esc_html__('Public enabled', 'cff'); ?></option><option value="inactive" <?php echo ($edit_def['duplicate_status'] ?? '') === 'inactive' ? 'selected' : ''; ?>><?php echo esc_html__('Public disabled', 'cff'); ?></option></select><p class="description"><?php echo esc_html__('Applies to the collection definition. Existing posts keep their status.', 'cff'); ?></p></div>
                </div></details>
            </div><aside class="cff-editor-sidebar"><section class="tk-card cff-form-section"><h3><?php echo esc_html__('Frontend visibility', 'cff'); ?></h3>
                <?php cff_render_editor_toggle('cpt_hide_frontend', __('Hide from frontend', 'cff'), __('Manage content in admin without public pages, search results, or WordPress sitemap entries.', 'cff'), $hide_frontend); ?>
                <div class="cff-visibility-note" role="status" data-cff-visibility-summary data-hidden="<?php echo esc_attr__('Admin only. Public page settings below are ignored.', 'cff'); ?>" data-public="<?php echo esc_attr__('Frontend follows the public page settings below.', 'cff'); ?>" data-private="<?php echo esc_attr__('Public visibility is off. Enable it or choose Hide from frontend to keep the admin available.', 'cff'); ?>"><?php echo esc_html($hide_frontend ? __('Admin only. Public page settings below are ignored.', 'cff') : ($pub ? __('Frontend follows the public page settings below.', 'cff') : __('Public visibility is off. Enable it or choose Hide from frontend to keep the admin available.', 'cff'))); ?></div>
                <div data-cff-public-settings>
                    <?php
                    cff_render_editor_toggle('cpt_public', __('Public visibility', 'cff'), __('Allow this collection on the website.', 'cff'), $pub);
                    cff_render_editor_toggle('cpt_archive', __('Archive page', 'cff'), __('A public listing of this collection.', 'cff'), $arc);
                    cff_render_editor_toggle('cpt_block_single', __('No single view', 'cff'), __('Disable individual content pages.', 'cff'), !empty($edit_def['block_single']));
                    ?>
                </div>
            </section><section class="tk-card cff-form-section"><h3><?php echo esc_html__('Integrations', 'cff'); ?></h3><?php cff_render_editor_toggle('cpt_rest', __('REST API', 'cff'), __('Enable the block editor and API access. Controlled separately from frontend pages.', 'cff'), $rst); ?></section></aside></div>
        </form>
    </details>
</div>
