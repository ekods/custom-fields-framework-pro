<?php
/**
 * Relational Field Helper Functions
 * 
 * Fungsi-fungsi helper untuk bekerja dengan relational field di CFF
 */

if (!function_exists('cff_get_relational_post')) {
  /**
   * Get relational post object
   * 
   * @param int $post_id Post ID yang akan diambil metadata-nya
   * @param string $field_name Nama field relational
   * @return WP_Post|false Post object atau false jika tidak ada
   */
  function cff_get_relational_post($post_id, $field_name) {
    $post_meta_key = '_cff_' . sanitize_key($field_name);
    $related_id = get_post_meta($post_id, $post_meta_key, true);
    
    if (!$related_id) {
      return false;
    }
    
    return get_post(intval($related_id));
  }
}

if (!function_exists('cff_get_relational_posts')) {
  /**
   * Get array of relational posts
   * 
   * @param int $post_id Post ID yang akan diambil metadata-nya
   * @param string $field_name Nama field relational (yang memiliki multiple=true)
   * @return array Array of WP_Post objects
   */
  function cff_get_relational_posts($post_id, $field_name) {
    $post_meta_key = '_cff_' . sanitize_key($field_name);
    $related_ids = get_post_meta($post_id, $post_meta_key, true);
    
    if (!is_array($related_ids)) {
      $related_ids = [];
    }
    
    $posts = [];
    foreach ($related_ids as $id) {
      if ($id === '__cff_rel_empty__') continue; // Skip placeholder
      $post = get_post(intval($id));
      if ($post) {
        $posts[] = $post;
      }
    }
    
    return $posts;
  }
}

if (!function_exists('cff_get_relational_term')) {
  /**
   * Get relational term object
   * 
   * @param int $post_id Post ID yang akan diambil metadata-nya
   * @param string $field_name Nama field relational
   * @return WP_Term|false Term object atau false jika tidak ada
   */
  function cff_get_relational_term($post_id, $field_name) {
    $post_meta_key = '_cff_' . sanitize_key($field_name);
    $related_id = get_post_meta($post_id, $post_meta_key, true);
    
    if (!$related_id) {
      return false;
    }
    
    return get_term(intval($related_id));
  }
}

if (!function_exists('cff_get_relational_terms')) {
  /**
   * Get array of relational terms
   * 
   * @param int $post_id Post ID yang akan diambil metadata-nya
   * @param string $field_name Nama field relational (yang memiliki multiple=true)
   * @return array Array of WP_Term objects
   */
  function cff_get_relational_terms($post_id, $field_name) {
    $post_meta_key = '_cff_' . sanitize_key($field_name);
    $related_ids = get_post_meta($post_id, $post_meta_key, true);
    
    if (!is_array($related_ids)) {
      $related_ids = [];
    }
    
    $terms = [];
    foreach ($related_ids as $id) {
      if ($id === '__cff_rel_empty__') continue; // Skip placeholder
      $term = get_term(intval($id));
      if ($term && !is_wp_error($term)) {
        $terms[] = $term;
      }
    }
    
    return $terms;
  }
}

if (!function_exists('cff_get_relational_user')) {
  /**
   * Get relational user object
   * 
   * @param int $post_id Post ID yang akan diambil metadata-nya
   * @param string $field_name Nama field relational
   * @return WP_User|false User object atau false jika tidak ada
   */
  function cff_get_relational_user($post_id, $field_name) {
    $post_meta_key = '_cff_' . sanitize_key($field_name);
    $related_id = get_post_meta($post_id, $post_meta_key, true);
    
    if (!$related_id) {
      return false;
    }
    
    return get_userdata(intval($related_id));
  }
}

if (!function_exists('cff_get_relational_users')) {
  /**
   * Get array of relational users
   * 
   * @param int $post_id Post ID yang akan diambil metadata-nya
   * @param string $field_name Nama field relational (yang memiliki multiple=true)
   * @return array Array of WP_User objects
   */
  function cff_get_relational_users($post_id, $field_name) {
    $post_meta_key = '_cff_' . sanitize_key($field_name);
    $related_ids = get_post_meta($post_id, $post_meta_key, true);
    
    if (!is_array($related_ids)) {
      $related_ids = [];
    }
    
    $users = [];
    foreach ($related_ids as $id) {
      if ($id === '__cff_rel_empty__') continue; // Skip placeholder
      $user = get_userdata(intval($id));
      if ($user) {
        $users[] = $user;
      }
    }
    
    return $users;
  }
}

/**
 * USAGE EXAMPLES:
 * 
 * === Single Relational Post ===
 * $related = cff_get_relational_post($post_id, 'related_post');
 * if ($related) {
 *   echo $related->post_title;
 *   echo $related->post_excerpt;
 *   echo wp_kses_post($related->post_content);
 * }
 * 
 * === Multiple Relational Posts ===
 * $related_posts = cff_get_relational_posts($post_id, 'related_posts');
 * foreach ($related_posts as $post) {
 *   echo '<h3>' . esc_html($post->post_title) . '</h3>';
 *   echo wp_kses_post(wp_trim_excerpt($post->post_excerpt, $post->ID));
 *   echo '<a href="' . esc_url(get_permalink($post->ID)) . '">Read More</a>';
 * }
 * 
 * === Single Relational Term ===
 * $related_term = cff_get_relational_term($post_id, 'related_category');
 * if ($related_term) {
 *   echo $related_term->name;
 *   echo $related_term->description;
 * }
 * 
 * === Multiple Relational Terms ===
 * $related_terms = cff_get_relational_terms($post_id, 'related_categories');
 * foreach ($related_terms as $term) {
 *   echo '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>';
 * }
 * 
 * === Single Relational User ===
 * $author = cff_get_relational_user($post_id, 'author');
 * if ($author) {
 *   echo 'By ' . esc_html($author->display_name);
 *   echo $author->user_email;
 * }
 * 
 * === Multiple Relational Users ===
 * $contributors = cff_get_relational_users($post_id, 'contributors');
 * foreach ($contributors as $user) {
 *   echo '<strong>' . esc_html($user->display_name) . '</strong>';
 * }
 */
