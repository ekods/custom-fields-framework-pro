<?php
namespace CFF;

if (!defined('ABSPATH')) exit;

function cff_render_page_hero($title, $description, $icon = 'dashicons-admin-tools', $action_html = '') {
    ?>
    <div class="tk-hero">
        <div class="tk-hero-bg-1"></div>
        <div class="tk-hero-bg-2"></div>
        
        <div class="tk-hero-content" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 16px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
                    <span class="dashicons <?php echo esc_attr($icon); ?>" style="color: #fff; font-size: 32px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"></span>
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

        <div style="display:flex; align-items:center; gap:20px;">
            <div class="tk-header-status" style="background:#f0fdf4; border-color:#bbf7d0; color:#166534;">
                <div class="tk-status-dot" style="background:#22c55e;"></div>
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
