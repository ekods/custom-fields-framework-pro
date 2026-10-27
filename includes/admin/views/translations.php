<?php
namespace CFF;
if (!defined('ABSPATH')) exit;
if (!$langs) return;
$translation_prefix = isset($supports_all) ? 'cpt_' : '';
?>
<details class="tk-card cff-advanced-section"><summary><?php echo esc_html__('Translations', 'cff'); ?></summary><div class="cff-advanced-body">
    <div class="cff-lang-tabs" data-default="<?php echo esc_attr($langs[0]['slug']); ?>"><div class="cff-lang-tabbar">
        <?php foreach ($langs as $lang): ?><button type="button" class="cff-lang-tab" data-lang="<?php echo esc_attr($lang['slug']); ?>"><?php echo esc_html($lang['name'] ?: $lang['slug']); ?></button><?php endforeach; ?>
    </div>
    <?php foreach ($langs as $lang): $lang_slug = $lang['slug']; ?>
        <div class="cff-lang-panel" data-lang="<?php echo esc_attr($lang_slug); ?>"><div class="cff-form-grid">
            <?php foreach (['singular' => __('Singular name', 'cff'), 'plural' => __('Plural name', 'cff'), 'slug' => __('URL slug', 'cff')] as $property => $label): $field_id = 'cff-translation-' . $property . '-' . $lang_slug; ?>
                <div class="cff-form-field"><label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label); ?></label><input type="text" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($translation_prefix . $property . '_i18n'); ?>[<?php echo esc_attr($lang_slug); ?>]" value="<?php echo esc_attr($edit_def[$property . '_i18n'][$lang_slug] ?? ''); ?>"></div>
            <?php endforeach; ?>
        </div></div>
    <?php endforeach; ?></div>
</div></details>
