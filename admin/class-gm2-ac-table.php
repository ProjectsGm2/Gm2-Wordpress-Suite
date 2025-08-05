<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GM2_AC_Table extends \WP_List_Table {
    private $items_per_page = 20;

    public function __construct() {
        parent::__construct([
            'singular' => 'gm2-abandoned-cart',
            'plural'   => 'gm2-abandoned-carts',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'status'      => __('Status', 'gm2-wordpress-suite'),
            'ip_address'  => __('IP Address', 'gm2-wordpress-suite'),
            'email'       => __('Email', 'gm2-wordpress-suite'),
            'location'    => __('Location', 'gm2-wordpress-suite'),
            'device'      => __('Device', 'gm2-wordpress-suite'),
            'browser'     => __('Browser', 'gm2-wordpress-suite'),
            'products'    => __('Products in Cart', 'gm2-wordpress-suite'),
            'cart_value'  => __('Cart Value', 'gm2-wordpress-suite'),
            'entry_url'   => __('Entry URL', 'gm2-wordpress-suite'),
            'exit_url'    => __('Exit URL', 'gm2-wordpress-suite'),
            'abandoned_at'=> __('Abandoned At', 'gm2-wordpress-suite'),
        ];
    }

    private function ensure_value($value) {
        if ($value === null) {
            return 'N/A';
        }
        if (is_string($value)) {
            $value = trim($value);
        }
        return $value === '' ? 'N/A' : $value;
    }

    public function column_default($item, $column_name) {
        $value = $item[$column_name] ?? '';
        return $this->ensure_value($value);
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = [];
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        global $wpdb;
        $table   = $wpdb->prefix . 'wc_ac_carts';
        $per_page = $this->items_per_page;
        $paged    = $this->get_pagenum();
        $search   = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';

        $where  = 'WHERE recovered_order_id IS NULL';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (email LIKE %s OR ip_address LIKE %s)';
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $total_sql  = "SELECT COUNT(*) FROM $table $where";
        $total_items = $wpdb->get_var($wpdb->prepare($total_sql, ...$params));

        $offset   = ($paged - 1) * $per_page;
        $data_sql = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params2  = array_merge($params, [ $per_page, $offset ]);
        $rows     = $wpdb->get_results($wpdb->prepare($data_sql, ...$params2));

        $items = [];
        foreach ($rows as $row) {
            $products   = [];
            $cart_value = 0;
            $contents   = json_decode($row->cart_contents, true);
            if (!is_array($contents)) {
                $old = maybe_unserialize($row->cart_contents);
                if (is_array($old)) {
                    $contents = [];
                    foreach ($old as $item) {
                        $qty     = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                        $prod_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                        $product = isset($item['data']) && is_object($item['data']) ? $item['data'] : wc_get_product($prod_id);
                        $name    = $product ? $product->get_name() : 'Product #' . $prod_id;
                        $price   = $product ? (float) $product->get_price() : 0;
                        $contents[] = [
                            'id'    => $prod_id,
                            'name'  => $name,
                            'qty'   => $qty,
                            'price' => $price,
                        ];
                    }
                    $wpdb->update($table, [ 'cart_contents' => wp_json_encode($contents) ], [ 'id' => $row->id ]);
                } else {
                    $contents = [];
                }
            }
            foreach ($contents as $item) {
                $products[] = $item['name'] . ' x' . $item['qty'];
                if (!empty($item['price'])) {
                    $cart_value += (float) $item['price'] * (int) $item['qty'];
                }
            }
            if ($cart_value <= 0 && $row->cart_total) {
                $cart_value = (float) $row->cart_total;
            }

            $status = $row->abandoned_at ? __('Abandoned', 'gm2-wordpress-suite') : __('Active', 'gm2-wordpress-suite');
            $abandoned_at = '';
            if ($row->abandoned_at) {
                $abandoned_at = mysql2date(get_option('date_format').' '.get_option('time_format'), $row->abandoned_at);
            }

            $items[] = [
                'status'      => esc_html($this->ensure_value($status)),
                'ip_address'  => esc_html($this->ensure_value($row->ip_address)),
                'email'       => esc_html($this->ensure_value($row->email)),
                'location'    => esc_html($this->ensure_value($row->location)),
                'device'      => esc_html($this->ensure_value($row->device)),
                'browser'     => esc_html($this->ensure_value($row->browser)),
                'products'    => esc_html($this->ensure_value(implode(', ', $products))),
                'cart_value'  => $this->ensure_value(wc_price($cart_value)),
                'entry_url'   => $this->ensure_value(esc_url($row->entry_url)),
                'exit_url'    => $this->ensure_value(esc_url($row->exit_url)),
                'abandoned_at'=> esc_html($this->ensure_value($abandoned_at)),
            ];
        }

        $this->items = $items;
        $this->set_pagination_args([
            'total_items' => (int) $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
}
