<?php
namespace CFF;
if (!defined('ABSPATH')) exit;
$langs = $this->polylang_languages();
$public_checked = $editing ? !empty($edit_def['public']) : true;
$rest_checked = $editing ? !empty($edit_def['show_in_rest']) : true;
$hidden_checked = !empty($edit_def['hide_frontend']);
?>
<div class="wrap tk-wrap cff-admin cff-management">
    <?php
    cff_render_header_branding();
    cff_render_page_hero(__('Taxonomies', 'cff'), __('Organize your content with categories, tags, and other reusable groups.', 'cff'), 'dashicons-category', '<a class="button button-primary" data-cff-open-editor href="'.esc_url(admin_url('admin.php?page=cff-taxonomies') . '#cff-definition-editor').'">'.esc_html__('Add taxonomy', 'cff').'</a>');
    ?>
    <section class="tk-card cff-collection" data-cff-collection>
        <div class="cff-card-heading"><div><h2><?php echo esc_html__('Your taxonomies', 'cff'); ?></h2><p><?php echo esc_html__('Manage terms to organize content, or edit how each taxonomy works.', 'cff'); ?></p></div><span class="cff-total"><?php echo esc_html(count($defs)); ?></span></div>
        <?php cff_render_management_table('taxonomy', $defs); ?>
    </section>

    <details class="cff-editor-disclosure" id="cff-definition-editor" <?php echo $editing || !$defs ? 'open' : ''; ?>>
        <summary><span><strong><?php echo esc_html($editing ? sprintf(__('Edit %s', 'cff'), ($edit_def['plural'] ?? '') ?: $edit_key) : __('Add a taxonomy', 'cff')); ?></strong><span class="description"><?php echo esc_html__('Choose labels, connected collections, and visibility.', 'cff'); ?></span></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
        <form method="post" class="cff-tax-form cff-definition-form" data-cff-editor>
            <?php wp_nonce_field('cffp_tax_nonce', 'cffp_tax_nonce'); ?>
            <input type="hidden" name="cffp_tax_action" value="<?php echo $editing ? 'update' : 'add'; ?>">
            <?php cff_render_editor_footer($editing ? __('Save changes', 'cff') : __('Create taxonomy', 'cff'), admin_url('admin.php?page=cff-taxonomies')); ?>
            <div class="cff-editor-grid"><div class="cff-editor-main">
                <section class="tk-card cff-form-section"><h3><?php echo esc_html__('Basic details', 'cff'); ?></h3><p class="description"><?php echo esc_html__('Use familiar names so editors can easily organize content.', 'cff'); ?></p>
                    <div class="cff-form-grid"><div class="cff-form-field"><label for="cff-tax-singular"><?php echo esc_html__('Singular name', 'cff'); ?> <span class="required">*</span></label><input id="cff-tax-singular" type="text" name="singular" required placeholder="Project Category" value="<?php echo esc_attr($edit_def['singular'] ?? ''); ?>"></div><div class="cff-form-field"><label for="cff-tax-plural"><?php echo esc_html__('Plural name', 'cff'); ?> <span class="required">*</span></label><input id="cff-tax-plural" type="text" name="plural" required placeholder="Project Categories" value="<?php echo esc_attr($edit_def['plural'] ?? ''); ?>"></div></div>
                    <div class="cff-form-grid"><div class="cff-form-field"><label for="cff-tax-key"><?php echo esc_html__('Taxonomy key', 'cff'); ?> <span class="required">*</span></label><input id="cff-tax-key" type="text" name="tax_key" required maxlength="32" pattern="[a-z0-9_\-]+" placeholder="project_category" value="<?php echo esc_attr($editing ? $edit_key : ''); ?>" <?php echo $editing ? 'readonly' : ''; ?>><p class="description"><?php echo esc_html__('Lowercase letters, numbers, underscores, or dashes. Cannot change after creation.', 'cff'); ?></p></div><div class="cff-form-field"><label for="cff-tax-slug"><?php echo esc_html__('URL slug', 'cff'); ?></label><input id="cff-tax-slug" type="text" name="slug" placeholder="project-categories" value="<?php echo esc_attr($edit_def['slug'] ?? ''); ?>"><p class="description"><?php echo esc_html__('Used for public term archive URLs.', 'cff'); ?></p></div></div>
                </section>
                <section class="tk-card cff-form-section"><h3><?php echo esc_html__('Connected content', 'cff'); ?></h3><p class="description"><?php echo esc_html__('Choose which collections can use these terms.', 'cff'); ?></p>
                    <div class="cff-form-field"><label for="cff-tax-types"><?php echo esc_html__('Post types', 'cff'); ?></label><select id="cff-tax-types" name="post_types[]" multiple class="cff-select2" data-placeholder="<?php echo esc_attr__('Choose content collections', 'cff'); ?>"><?php foreach ($post_types as $pt): ?><option value="<?php echo esc_attr($pt->name); ?>" <?php echo in_array($pt->name, $sel_pt, true) ? 'selected' : ''; ?>><?php echo esc_html($pt->labels->name); ?></option><?php endforeach; ?></select><p class="description"><?php echo esc_html__('You can select more than one collection, including collections hidden from the frontend.', 'cff'); ?></p></div>
                    <?php cff_render_editor_toggle('hierarchical', __('Use parent / child groups', 'cff'), __('Enable for categories and subcategories. Leave off for tags.', 'cff'), !empty($edit_def['hierarchical'])); ?>
                </section>
                <?php include __DIR__ . '/translations.php'; ?>
            </div><aside class="cff-editor-sidebar"><section class="tk-card cff-form-section"><h3><?php echo esc_html__('Frontend visibility', 'cff'); ?></h3>
                <?php cff_render_editor_toggle('hide_frontend', __('Hide from frontend', 'cff'), __('Manage terms in admin without public archives or WordPress sitemap entries.', 'cff'), $hidden_checked); ?>
                <div class="cff-visibility-note" role="status" data-cff-visibility-summary data-hidden="<?php echo esc_attr__('Admin only. Public page settings below are ignored.', 'cff'); ?>" data-public="<?php echo esc_attr__('Frontend follows the public page settings below.', 'cff'); ?>" data-private="<?php echo esc_attr__('Public visibility is off. Enable it or choose Hide from frontend to keep the admin available.', 'cff'); ?>"><?php echo esc_html($hidden_checked ? __('Admin only. Public page settings below are ignored.', 'cff') : ($public_checked ? __('Frontend follows the public page settings below.', 'cff') : __('Public visibility is off. Enable it or choose Hide from frontend to keep the admin available.', 'cff'))); ?></div>
                <div data-cff-public-settings><?php cff_render_editor_toggle('public', __('Public visibility', 'cff'), __('Allow term archives on the website.', 'cff'), $public_checked); ?></div>
            </section><section class="tk-card cff-form-section"><h3><?php echo esc_html__('Integrations', 'cff'); ?></h3><?php cff_render_editor_toggle('show_in_rest', __('REST API', 'cff'), __('Make terms available to the block editor and API. Controlled separately from frontend pages.', 'cff'), $rest_checked); ?></section></aside></div>
        </form>
    </details>
</div>
