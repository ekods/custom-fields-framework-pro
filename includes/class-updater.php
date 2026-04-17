<?php
if (!defined('ABSPATH')) exit;

class CFF_Github_Public_Updater {
  private $plugin_file;
  private $plugin_basename;
  private $plugin_slug;
  private $repo;       // owner/repo
  private $asset_name; // zip asset name
  private $updating_this_plugin = false;

  public function __construct($plugin_file, $repo, $asset_name){
    $this->plugin_file = $plugin_file;
    $this->plugin_basename = plugin_basename($plugin_file);
    $this->plugin_slug = dirname($this->plugin_basename);
    $this->repo = $repo;
    $this->asset_name = $asset_name;

    // ✅ inject saat WP CHECK update (write transient)
    add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);

    // ✅ inject saat WP READ transient (biar muncul di Plugins screen walau cache)
    add_filter('site_transient_update_plugins', [$this, 'inject_update']);

    // (opsional) biar klik “View details” gak kosong
    add_filter('plugins_api', [$this, 'plugins_api'], 20, 3);

    // Normalisasi root folder ZIP supaya update mayor dari GitHub tidak gagal.
    add_filter('upgrader_source_selection', [$this, 'normalize_upgrade_source'], 10, 4);
    add_filter('upgrader_pre_install', [$this, 'handle_pre_install'], 10, 2);
    add_filter('upgrader_post_install', [$this, 'handle_post_install'], 10, 3);
    add_action('upgrader_process_complete', [$this, 'handle_process_complete'], 10, 2);
  }

  private function gh($url){
    $res = wp_remote_get($url, [
      'timeout' => 20,
      'headers' => [
        'User-Agent' => 'WordPress/' . get_bloginfo('version'),
        'Accept'     => 'application/vnd.github+json',
      ],
    ]);
    if (is_wp_error($res)) return null;

    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;

    return json_decode(wp_remote_retrieve_body($res), true);
  }

  public function inject_update($transient){
    if (!is_object($transient)) $transient = (object) [];
    if (empty($transient->checked) || !isset($transient->checked[$this->plugin_basename])) {
      return $transient;
    }

    $current = $transient->checked[$this->plugin_basename];

    $release = $this->gh("https://api.github.com/repos/{$this->repo}/releases/latest");
    if (!$release || empty($release['tag_name'])) return $transient;

    $tag = ltrim((string)$release['tag_name'], 'v');
    if (!version_compare($tag, $current, '>')) return $transient;

    // cari browser_download_url dari asset ZIP
    $package = '';
    if (!empty($release['assets'])) {
      foreach ($release['assets'] as $a) {
        if (!empty($a['name']) && $a['name'] === $this->asset_name && !empty($a['browser_download_url'])) {
          $package = $a['browser_download_url'];
          break;
        }
      }
    }
    if (!$package) return $transient;

    $transient->response[$this->plugin_basename] = (object) [
      'slug'        => $this->plugin_slug,
      'plugin'      => $this->plugin_basename,
      'new_version' => $tag,
      'package'     => $package,
      'url'         => $release['html_url'] ?? '',
    ];

    return $transient;
  }

  // Optional: Plugin info popup (View details)
  public function plugins_api($result, $action, $args){
    if ($action !== 'plugin_information') return $result;
    if (empty($args->slug)) return $result;

    $slug = $this->plugin_slug;
    if ($args->slug !== $slug) return $result;

    $release = $this->gh("https://api.github.com/repos/{$this->repo}/releases/latest");
    if (!$release) return $result;

    $tag = ltrim((string)($release['tag_name'] ?? ''), 'v');

    return (object)[
      'name'          => $release['name'] ?? $slug,
      'slug'          => $slug,
      'version'       => $tag ?: '0.0.0',
      'author'        => 'CFF',
      'homepage'      => $release['html_url'] ?? '',
      'sections'      => [
        'description' => !empty($release['body']) ? wp_kses_post($release['body']) : 'No release notes.',
        'changelog'   => !empty($release['body']) ? wp_kses_post($release['body']) : 'No changelog.',
      ],
      'download_link' => '', // WP pakai `package` dari transient saat update
    ];
  }

  public function normalize_upgrade_source($source, $remote_source, $upgrader, $hook_extra){
    if (is_wp_error($source) || empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
      return $source;
    }

    if (!is_dir($source)) {
      return $source;
    }

    $expected = trailingslashit($remote_source) . $this->plugin_slug;
    $normalized_source = untrailingslashit($source);
    $normalized_expected = untrailingslashit($expected);

    if ($normalized_source === $normalized_expected) {
      return $source;
    }

    global $wp_filesystem;
    if (!$wp_filesystem && function_exists('WP_Filesystem')) {
      WP_Filesystem();
    }
    if (!$wp_filesystem) {
      return $source;
    }

    if ($wp_filesystem->is_dir($expected)) {
      $wp_filesystem->delete($expected, true);
    }

    if (!$wp_filesystem->move($source, $expected, true)) {
      return new WP_Error(
        'cffp_updater_bad_package',
        __('Plugin update package could not be prepared for installation.', 'cff')
      );
    }

    return $expected;
  }

  public function handle_pre_install($response, $hook_extra){
    if (!$this->is_target_plugin_update($hook_extra)) {
      return $response;
    }

    $this->updating_this_plugin = true;
    $this->clear_maintenance_file();

    if (function_exists('register_shutdown_function')) {
      register_shutdown_function([$this, 'cleanup_after_shutdown']);
    }

    return $response;
  }

  public function handle_post_install($response, $hook_extra, $result){
    if ($this->is_target_plugin_update($hook_extra)) {
      $this->clear_maintenance_file();
      $this->updating_this_plugin = false;
    }

    return $response;
  }

  public function handle_process_complete($upgrader, $hook_extra){
    if (!$this->is_target_plugin_update($hook_extra)) {
      return;
    }

    $this->clear_maintenance_file();
    $this->updating_this_plugin = false;
  }

  public function cleanup_after_shutdown(){
    if (!$this->updating_this_plugin) {
      return;
    }

    $this->clear_maintenance_file();
    $this->updating_this_plugin = false;
  }

  private function is_target_plugin_update($hook_extra){
    if (!is_array($hook_extra)) {
      return false;
    }

    if (($hook_extra['type'] ?? '') !== 'plugin' || ($hook_extra['action'] ?? '') !== 'update') {
      return false;
    }

    if (!empty($hook_extra['plugin'])) {
      return $hook_extra['plugin'] === $this->plugin_basename;
    }

    if (!empty($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
      return in_array($this->plugin_basename, $hook_extra['plugins'], true);
    }

    return false;
  }

  private function clear_maintenance_file(){
    $maintenance_file = trailingslashit(ABSPATH) . '.maintenance';

    if (file_exists($maintenance_file) && is_writable($maintenance_file)) {
      @unlink($maintenance_file);
      clearstatcache(false, $maintenance_file);
    }

    if (file_exists($maintenance_file)) {
      global $wp_filesystem;
      if (!$wp_filesystem && function_exists('WP_Filesystem')) {
        WP_Filesystem();
      }
      if ($wp_filesystem && $wp_filesystem->exists($maintenance_file)) {
        $wp_filesystem->delete($maintenance_file, false);
      }
    }
  }
}
