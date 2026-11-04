<?php
namespace CFF;

if (!defined('ABSPATH')) exit;
if (!class_exists('\WP_List_Table')) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/** Core table rendering, sorting, pagination and responsive row toggles. */
class Management_List_Table extends \WP_List_Table {
    private $kind;
    private $definitions;

    public function __construct($kind, $definitions) {
        $this->kind = $kind;
        $this->definitions = (array) $definitions;
        parent::__construct(['singular' => 'collection', 'plural' => 'collections', 'ajax' => false]);
    }

    private function query($key) {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }

    public function get_columns() {
        $columns = ['name' => __('Name', 'cff'), 'key' => __('Key', 'cff')];
        if ($this->kind === 'content') $columns['status'] = __('Content status', 'cff');
        if ($this->kind === 'taxonomy') $columns['post_types'] = __('Used by', 'cff');
        $columns['frontend'] = __('Frontend', 'cff');
        return $columns;
    }

    protected function get_sortable_columns() {
        return ['name' => ['name', false], 'key' => ['key', false]];
    }

    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'name'];
        $rows = [];
        $search = $this->query('s');
        $visibility = $this->query('visibility');
        foreach ($this->definitions as $key => $definition) {
            $content = $this->kind === 'content';
            $name = $content ? $definition->labels->name : (($definition['plural'] ?? '') ?: $key);
            $hidden = $content ? empty($definition->public) : (!empty($definition['hide_frontend']) || empty($definition['public']));
            $searchable = $name . ' ' . $key;
            if (!$content) $searchable .= ' ' . ($definition['slug'] ?? '') . ' ' . implode(' ', (array) ($definition['post_types'] ?? []));
            if ($search !== '' && stripos($searchable, $search) === false) continue;
            if (in_array($visibility, ['hidden', 'public'], true) && ($hidden ? 'hidden' : 'public') !== $visibility) continue;
            $rows[] = compact('key', 'name', 'hidden', 'definition');
        }
        $orderby = $this->query('orderby') === 'key' ? 'key' : 'name';
        $direction = strtolower($this->query('order')) === 'desc' ? -1 : 1;
        usort($rows, static function($a, $b) use ($orderby, $direction) {
            return strnatcasecmp($a[$orderby], $b[$orderby]) * $direction;
        });
        $per_page = 20;
        $this->set_pagination_args(['total_items' => count($rows), 'per_page' => $per_page]);
        $this->items = array_slice($rows, ($this->get_pagenum() - 1) * $per_page, $per_page);
    }

    public function column_name($item) {
        $key = $item['key'];
        $def = $item['definition'];
        $page = $this->kind === 'post' ? 'cff-post-types' : 'cff-taxonomies';
        $url = $this->kind === 'content' ? cff_content_admin_url($key) : add_query_arg(['page' => $page, 'edit' => $key], admin_url('admin.php')) . '#cff-definition-editor';
        $html = '<strong><a class="row-title" href="' . esc_url($url) . '">' . esc_html($item['name']) . '</a></strong>';
        if ($this->kind === 'content') {
            return $html . $this->row_actions([
                'edit' => '<a href="' . esc_url($url) . '">' . esc_html__('Manage content', 'cff') . '</a>',
                'new' => '<a href="' . esc_url(cff_content_admin_url($key, true)) . '">' . esc_html__('Add new', 'cff') . '</a>',
            ]);
        }
        ob_start();
        cff_render_definition_actions($this->kind, $key, $item['name']);
        $html .= ob_get_clean();
        if ($this->kind === 'taxonomy') {
            $types = (array) ($def['post_types'] ?? []);
            $args = ['taxonomy' => $key];
            if ($types) $args['post_type'] = reset($types);
            if (!empty($def['hide_frontend']) || !empty($def['public'])) {
                $html .= '<a href="' . esc_url(add_query_arg($args, admin_url('edit-tags.php'))) . '">' . esc_html__('Manage terms', 'cff') . '</a>';
            }
            $html .= '<p class="description">' . esc_html(!empty($def['hierarchical']) ? __('Categories with parent / child groups', 'cff') : __('Tags without a hierarchy', 'cff')) . '</p>';
        } else {
            $html .= '<details class="cff-technical"><summary>' . esc_html__('View details', 'cff') . '</summary><div>';
            $html .= '<span>' . esc_html__('URL slug:', 'cff') . ' <code>' . esc_html($def['slug'] ?? $key) . '</code></span>';
            $html .= '<span>' . esc_html__('REST API:', 'cff') . ' ' . esc_html(!empty($def['show_in_rest']) ? __('Enabled', 'cff') : __('Disabled', 'cff')) . '</span>';
            if (!$item['hidden']) {
                $slug = sanitize_title(($def['slug'] ?? '') ?: $key);
                if (!empty($def['has_archive'])) $html .= '<code>' . esc_html('archive-' . $slug . '.php') . '</code>';
                if (empty($def['block_single'])) $html .= '<code>' . esc_html('single-' . $slug . '.php') . '</code>';
            }
            $html .= '</div></details>';
        }
        return $html;
    }

    protected function handle_row_actions($item, $column_name, $primary) {
        // row_actions() already includes core's mobile toggle on the content overview.
        return $this->kind === 'content' ? '' : parent::handle_row_actions($item, $column_name, $primary);
    }

    public function column_default($item, $column_name) {
        if ($column_name === 'key') return '<code>' . esc_html($item['key']) . '</code>';
        if ($column_name === 'frontend') {
            ob_start();
            cff_render_frontend_badge($item['hidden']);
            if ($this->kind === 'post') {
                $def = $item['definition'];
                $available = !empty($def['hide_frontend']) || (array_key_exists('show_ui', $def) ? !empty($def['show_ui']) : !empty($def['public']));
                if (!$available) echo '<span class="cff-cell-note">' . esc_html__('Admin UI disabled', 'cff') . '</span>';
            }
            return ob_get_clean();
        }
        if ($column_name === 'status') {
            $counts = wp_count_posts($item['key'], 'readable');
            return esc_html(sprintf(__('%d published', 'cff'), $counts->publish ?? 0)) . '<br>' . esc_html(sprintf(__('%d drafts', 'cff'), $counts->draft ?? 0));
        }
        if ($column_name === 'post_types') {
            $labels = [];
            foreach ((array) ($item['definition']['post_types'] ?? []) as $type) {
                $object = get_post_type_object($type);
                $labels[] = $object ? $object->labels->name : $type;
            }
            return esc_html($labels ? implode(', ', $labels) : __('No collections selected', 'cff'));
        }
        return '';
    }

    public function no_items() {
        echo esc_html__('No collections found. Try another search or add a new collection.', 'cff');
    }

    public function render() {
        // Search is its own GET form so nonce-protected row POST forms are never nested.
        $pages = ['post' => 'cff-post-types', 'taxonomy' => 'cff-taxonomies', 'content' => 'cff'];
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" id="cff-list-search" class="cff-list-query">';
        echo '<input type="hidden" name="page" value="' . esc_attr($pages[$this->kind]) . '">';
        echo '<div class="cff-list-filter"><label class="screen-reader-text" for="cff-visibility">' . esc_html__('Filter by frontend visibility', 'cff') . '</label>';
        echo '<select id="cff-visibility" name="visibility">';
        foreach (['' => __('All visibility', 'cff'), 'public' => __('Public', 'cff'), 'hidden' => __('Admin only', 'cff')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($this->query('visibility'), $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> <button class="button" type="submit">' . esc_html__('Filter', 'cff') . '</button></div>';
        $this->search_box(__('Search collections', 'cff'), 'cff-collections');
        echo '</form>';
        ob_start();
        $this->display();
        // Associate core's page-number input with the separate GET form.
        echo str_replace("class='current-page'", "class='current-page' form='cff-list-search'", ob_get_clean());
    }
}
