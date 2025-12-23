<?php
if (!defined('ABSPATH')) exit;

class CFF_Github_Public_Updater {
  private $plugin_file;
  private $plugin_basename;
  private $repo;       // owner/repo
  private $asset_name; // zip asset name

  public function __construct($plugin_file, $repo, $asset_name){
    $this->plugin_file = $plugin_file;
    $this->plugin_basename = plugin_basename($plugin_file);
    $this->repo = $repo;
    $this->asset_name = $asset_name;

    add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
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
    if (empty($transient->checked) || !isset($transient->checked[$this->plugin_basename])) return $transient;

    $current = $transient->checked[$this->plugin_basename];

    $release = $this->gh("https://api.github.com/repos/{$this->repo}/releases/latest");
    if (!$release || empty($release['tag_name'])) return $transient;

    $tag = ltrim($release['tag_name'], 'v'); // v0.13.4 -> 0.13.4
    if (version_compare($tag, $current, '<=')) return $transient;

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
      'slug'        => dirname($this->plugin_basename),
      'plugin'      => $this->plugin_basename,
      'new_version' => $tag,
      'package'     => $package,                 // ✅ direct public URL
      'url'         => $release['html_url'] ?? '', // release page
    ];

    return $transient;
  }
}
