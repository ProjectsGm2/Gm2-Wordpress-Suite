<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gm2_Bulk_Taxonomies {

    public function run() {
        add_action( 'restrict_manage_posts', [ $this, 'render_button' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_gm2_fetch_filtered_term_ids', [ $this, 'ajax_fetch_term_ids' ] );
    }

    public function render_button( $post_type ) {
        if ( ! isset( $_GET['taxonomy'] ) ) {
            return;
        }
        echo '<button type="button" id="gm2-tax-select-all" class="button">Select All</button>';
        echo '<input type="hidden" id="gm2-tax-selected-ids" name="gm2_tax_selected_ids" value="" />';
    }

    public function enqueue_scripts( $hook ) {
        if ( 'edit-tags.php' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            'gm2-bulk-taxonomies',
            GM2_PLUGIN_URL . 'admin/js/gm2-bulk-taxonomies.js',
            [],
            GM2_VERSION,
            true
        );
        wp_localize_script( 'gm2-bulk-taxonomies', 'gm2BulkTaxData', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'gm2_bulk_tax' ),
        ] );
    }

    public function ajax_fetch_term_ids() {
        check_ajax_referer( 'gm2_bulk_tax', 'nonce' );
        $query_string = isset( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : '';
        $params       = [];
        if ( ! empty( $query_string ) ) {
            wp_parse_str( $query_string, $params );
        }
        $args = [
            'taxonomy'   => isset( $params['taxonomy'] ) ? sanitize_key( $params['taxonomy'] ) : 'category',
            'search'     => isset( $params['s'] ) ? sanitize_text_field( $params['s'] ) : '',
            'hide_empty' => false,
            'fields'     => 'ids',
            'number'     => 0,
        ];
        $terms = get_terms( $args );
        if ( is_wp_error( $terms ) ) {
            wp_send_json_success( [] );
        } else {
            wp_send_json_success( $terms );
        }
    }
}
