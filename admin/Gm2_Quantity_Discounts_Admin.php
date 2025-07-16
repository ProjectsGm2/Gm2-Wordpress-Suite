<?php
namespace Gm2;
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Quantity_Discounts_Admin {
    public function register_hooks() {
        add_action('admin_menu', [ $this, 'add_admin_menu' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
        add_action('wp_ajax_gm2_qd_search_products', [ $this, 'ajax_search_products' ]);
        add_action('wp_ajax_gm2_qd_save_groups', [ $this, 'ajax_save_groups' ]);
        add_action('wp_ajax_gm2_qd_get_category_products', [ $this, 'ajax_get_category_products' ]);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'gm2',
            esc_html__( 'Quantity Discounts', 'gm2-wordpress-suite' ),
            esc_html__( 'Quantity Discounts', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-quantity-discounts',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'gm2_page_gm2-quantity-discounts' ) {
            return;
        }
        wp_enqueue_style(
            'gm2-quantity-discounts',
            GM2_PLUGIN_URL . 'admin/css/gm2-quantity-discounts.css',
            [],
            GM2_VERSION
        );
        wp_enqueue_script(
            'gm2-quantity-discounts',
            GM2_PLUGIN_URL . 'admin/js/gm2-quantity-discounts.js',
            [ 'jquery' ],
            GM2_VERSION,
            true
        );
        $groups = get_option( 'gm2_quantity_discount_groups', [] );
        $cats   = [];
        if ( taxonomy_exists( 'product_cat' ) ) {
            $terms = get_terms( [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ] );
            foreach ( $terms as $t ) {
                $cats[] = [ 'id' => $t->term_id, 'name' => $t->name ];
            }
        }
        wp_localize_script(
            'gm2-quantity-discounts',
            'gm2Qd',
            [
                'nonce'      => wp_create_nonce( 'gm2_qd_nonce' ),
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'groups'     => $groups,
                'categories' => $cats,
            ]
        );
    }

    public function render_page() {
        echo '<div class="wrap">';
        echo '<h1 class="gm2-qd-title">' . esc_html__( 'Quantity Discounts', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<div id="gm2-qd-msg" class="notice hidden"></div>';
        echo '<form id="gm2-qd-form"><div id="gm2-qd-groups"></div>';
        echo '<p><button type="button" id="gm2-qd-add-group" class="button">' . esc_html__( 'Add Group', 'gm2-wordpress-suite' ) . '</button></p>';
        submit_button( esc_html__( 'Save Changes', 'gm2-wordpress-suite' ) );
        echo '</form></div>';
    }

    public function ajax_search_products() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_ajax_referer( 'gm2_qd_nonce', 'nonce' );
        $term  = sanitize_text_field( $_GET['term'] ?? '' );
        $cat   = absint( $_GET['category'] ?? 0 );
        $args  = [
            'post_type'      => 'product',
            'posts_per_page' => 20,
            's'              => $term,
        ];
        if ( $cat ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'terms'    => [ $cat ],
                ],
            ];
        }
        $q      = new \WP_Query( $args );
        $result = [];
        foreach ( $q->posts as $p ) {
            $result[] = [
                'id'   => $p->ID,
                'text' => $p->post_title,
            ];
        }
        wp_send_json_success( $result );
    }

    public function ajax_get_category_products() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_ajax_referer( 'gm2_qd_nonce', 'nonce' );
        $cat = absint( $_GET['category'] ?? 0 );
        if ( ! $cat ) {
            wp_send_json_success( [] );
        }
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'terms'    => [ $cat ],
                ],
            ],
            'orderby' => 'title',
            'order'   => 'ASC',
        ];
        $q      = new \WP_Query( $args );
        $result = [];
        foreach ( $q->posts as $p ) {
            $result[] = [ 'id' => $p->ID, 'text' => $p->post_title ];
        }
        wp_send_json_success( $result );
    }

    public function ajax_save_groups() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_ajax_referer( 'gm2_qd_nonce', 'nonce' );
        $groups = isset( $_POST['groups'] ) && is_array( $_POST['groups'] ) ? $_POST['groups'] : [];
        $clean  = [];
        foreach ( $groups as $g ) {
            $name     = sanitize_text_field( $g['name'] ?? '' );
            $products = array_map( 'intval', $g['products'] ?? [] );
            $rules    = [];
            if ( ! empty( $g['rules'] ) && is_array( $g['rules'] ) ) {
                foreach ( $g['rules'] as $r ) {
                    $min    = intval( $r['min'] ?? 1 );
                    $type   = $r['type'] === 'fixed' ? 'fixed' : 'percent';
                    $amount = floatval( $r['amount'] ?? 0 );
                    $rules[] = [
                        'min'    => $min,
                        'type'   => $type,
                        'amount' => $amount,
                    ];
                }
            }
            if ( $name !== '' ) {
                $clean[] = [
                    'name'     => $name,
                    'products' => $products,
                    'rules'    => $rules,
                ];
            }
        }
        update_option( 'gm2_quantity_discount_groups', $clean );
        wp_send_json_success( [ 'saved' => true ] );
    }
}
