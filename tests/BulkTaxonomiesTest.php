<?php
use PHPUnit\Framework\TestCase;

// Stubs for required WordPress functions and classes
if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action, $query_arg ) {
        return true;
    }
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data ) {
        echo json_encode( [ 'success' => true, 'data' => $data ] );
        throw new Exception('wp_die');
    }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) { return $value; }
}
if ( ! function_exists( 'wp_parse_str' ) ) {
    function wp_parse_str( $string, &$array ) { parse_str( $string, $array ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $str ) { return $str; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return $str; }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) { return $path; }
}
if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $args ) { return [4,5,6]; }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) { return false; }
}

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../admin/class-gm2-bulk-taxonomies.php';

class BulkTaxonomiesTest extends TestCase {
    public function test_fetch_filtered_ids_returns_all_terms() {
        $_POST['nonce'] = 'dummy';
        $_POST['query'] = 'taxonomy=category';
        $handler = new Gm2_Bulk_Taxonomies();
        ob_start();
        try {
            $handler->ajax_fetch_term_ids();
        } catch ( Exception $e ) {
            // Expected due to wp_die stub
        }
        $output = ob_get_clean();
        $response = json_decode( $output, true );
        $this->assertTrue( $response['success'] );
        $this->assertEquals( [4,5,6], $response['data'] );
    }
}
