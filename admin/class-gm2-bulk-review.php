<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gm2_Bulk_Review {

    public function run() {
        add_action( 'restrict_manage_posts', [ $this, 'render_button' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_gm2_fetch_filtered_post_ids', [ $this, 'ajax_fetch_post_ids' ] );
    }

    public function render_button( $post_type ) {
        if ( 'post' !== $post_type ) {
            return;
        }
        echo '<button type="button" id="gm2-select-all" class="button">Select All</button>';
        echo '<input type="hidden" id="gm2-selected-ids" name="gm2_selected_ids" value="" />';
    }

    public function enqueue_scripts( $hook ) {
        if ( 'edit.php' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            'gm2-bulk-review',
            GM2_PLUGIN_URL . 'admin/js/gm2-bulk-review.js',
            [],
            GM2_VERSION,
            true
        );
        wp_localize_script( 'gm2-bulk-review', 'gm2BulkReviewData', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'gm2_bulk_review' ),
        ] );
    }

    public function ajax_fetch_post_ids() {
        check_ajax_referer( 'gm2_bulk_review', 'nonce' );
        $query_string = isset( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : '';
        $params       = [];
        if ( ! empty( $query_string ) ) {
            wp_parse_str( $query_string, $params );
        }
        $args = [
            'post_type'      => isset( $params['post_type'] ) ? sanitize_key( $params['post_type'] ) : 'post',
            'post_status'    => isset( $params['post_status'] ) ? sanitize_key( $params['post_status'] ) : 'any',
            's'              => isset( $params['s'] ) ? sanitize_text_field( $params['s'] ) : '',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];
        $query = new WP_Query( $args );
        wp_send_json_success( $query->posts );
    }
}
