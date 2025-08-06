<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Gm2_Bulk_Ai_Tax_List_Table extends \WP_List_Table {
    private $admin;
    private $page_size;
    private $status;
    private $taxonomy;
    private $search;
    private $missing_title;
    private $missing_desc;
    private $seo_status;

    public function __construct($admin, $args) {
        $this->admin         = $admin;
        $this->page_size     = max(1, (int) ($args['page_size'] ?? 10));
        $this->status        = $args['status'] ?? 'publish';
        $this->taxonomy      = $args['taxonomy'] ?? 'all';
        $this->search        = $args['search'] ?? '';
        $this->seo_status    = $args['seo_status'] ?? 'all';
        $this->missing_title = $args['missing_title'] ?? '0';
        $this->missing_desc  = $args['missing_description'] ?? '0';

        parent::__construct([
            'plural'   => 'gm2-bulk-ai-tax',
            'singular' => 'gm2-bulk-ai-tax',
            'ajax'     => false,
            'screen'   => 'gm2-bulk-ai-tax',
        ]);
    }

    public function get_columns() {
        return [
            'cb'              => '<input type="checkbox" id="gm2-bulk-term-select-all" />',
            'name'            => esc_html__( 'Name', 'gm2-wordpress-suite' ),
            'seo_title'       => esc_html__( 'SEO Title', 'gm2-wordpress-suite' ),
            'description'     => esc_html__( 'Description', 'gm2-wordpress-suite' ),
            'tax_description' => esc_html__( 'Tax Description', 'gm2-wordpress-suite' ),
            'ai'              => esc_html__( 'AI Suggestions', 'gm2-wordpress-suite' ),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'name' => [ 'name', false ],
        ];
    }

    protected function column_cb($item) {
        $key = $item->taxonomy . ':' . $item->term_id;
        return '<input type="checkbox" class="gm2-select" value="' . esc_attr($key) . '" />';
    }

    protected function column_name($item) {
        $edit_link = get_edit_term_link($item->term_id, $item->taxonomy);
        $name      = $edit_link ? '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($item->name) . '</a>' : esc_html($item->name);
        return $name;
    }

    protected function column_seo_title($item) {
        return esc_html( get_term_meta($item->term_id, '_gm2_title', true) );
    }

    protected function column_description($item) {
        return esc_html( get_term_meta($item->term_id, '_gm2_description', true) );
    }

    protected function column_tax_description($item) {
        return esc_html( wp_strip_all_tags( term_description($item->term_id, $item->taxonomy) ) );
    }

    protected function column_ai($item) {
        $stored = get_term_meta($item->term_id, '_gm2_ai_research', true);
        $result_html = '';
        if ($stored) {
            $data = json_decode($stored, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $result_html = $this->render_result($data, $item);
            }
        }
        return $result_html;
    }

    private function render_result($data, $item) {
        $html        = '';
        $suggestions = '';

        if (!empty($data['seo_title'])) {
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="seo_title" data-value="' . esc_attr($data['seo_title']) . '"> ' . esc_html($data['seo_title']) . '</label></p>';
        }
        if (!empty($data['description'])) {
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="seo_description" data-value="' . esc_attr($data['description']) . '"> ' . esc_html($data['description']) . '</label></p>';
        }

        if ($suggestions !== '') {
            $key  = $item->taxonomy . ':' . $item->term_id;
            $html .= '<p><label><input type="checkbox" class="gm2-row-select-all"> ' . esc_html__( 'Select all', 'gm2-wordpress-suite' ) . '</label></p>';
            $html .= $suggestions;
            $html .= '<p><button class="button gm2-apply-btn" data-key="' . esc_attr($key) . '" aria-label="' . esc_attr__( 'Apply', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Apply', 'gm2-wordpress-suite' ) . '</button></p>';
        }

        return $html;
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $tax_list = $this->admin->get_supported_taxonomies();
        $tax_arg  = ($this->taxonomy === 'all') ? $tax_list : $this->taxonomy;

        $args = [
            'taxonomy'   => $tax_arg,
            'hide_empty' => false,
            'status'     => $this->status,
            'number'     => $this->page_size,
            'offset'     => $this->page_size * ($this->get_pagenum() - 1),
        ];

        if ($this->search !== '') {
            $args['search'] = $this->search;
        }

        $meta_query = [];
        if ($this->seo_status === 'complete') {
            $meta_query[] = [ 'key' => '_gm2_title', 'value' => '', 'compare' => '!=' ];
            $meta_query[] = [ 'key' => '_gm2_description', 'value' => '', 'compare' => '!=' ];
        } elseif ($this->seo_status === 'incomplete') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($this->missing_title === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($this->missing_desc === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($meta_query) {
            $args['meta_query'] = array_merge([ 'relation' => 'AND' ], $meta_query);
        }

        $orderby = $_REQUEST['orderby'] ?? '';
        $order   = $_REQUEST['order'] ?? 'asc';
        if ($orderby === 'name') {
            $args['orderby'] = 'name';
            $args['order']   = $order;
        }

        $query = new \WP_Term_Query($args);
        $this->items = $query->terms;

        $count_args = $args;
        unset($count_args['number'], $count_args['offset'], $count_args['orderby'], $count_args['order']);
        $count_args['fields'] = 'count';
        $total_items = (int) get_terms($count_args);
        $total_pages = (int) max(1, ceil($total_items / $this->page_size));

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $this->page_size,
            'total_pages' => $total_pages,
        ]);
    }

    public function single_row($item) {
        $key = $item->taxonomy . ':' . $item->term_id;
        echo '<tr id="gm2-term-' . esc_attr($item->taxonomy) . '-' . intval($item->term_id) . '" data-key="' . esc_attr($key) . '">';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    public function display() {
        $this->display_tablenav('top');
        $classes = implode(' ', $this->get_table_classes());
        echo '<table id="gm2-bulk-term-list" class="wp-list-table ' . esc_attr($classes) . '">';

        echo '<thead>';
        $this->print_column_headers();
        echo '</thead>';

        echo '<tbody id="the-list">';
        $this->display_rows_or_placeholder();
        echo '</tbody>';

        echo '<tfoot>';
        $this->print_column_headers(false);
        echo '</tfoot>';

        echo '</table>';
        $this->display_tablenav('bottom');
    }

    public function no_items() {
        esc_html_e( 'No terms found.', 'gm2-wordpress-suite' );
    }
}

