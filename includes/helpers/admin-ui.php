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

        <div class="tk-header-meta">
            <div class="tk-header-status is-active">
                <div class="tk-status-dot"></div>
                <span>Framework Active</span>
            </div>

            <div class="tk-header-status">
                <div class="tk-status-dot"></div>
                <span>System Operational</span>
            </div>
        </div>
    </div>
    <?php
}
