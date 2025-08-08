<?php
use PHPUnit\Framework\TestCase;

// Stubs for required WordPress functions and classes
function check_ajax_referer( $action, $query_arg ) {
    return true;
}
function wp_send_json_success( $data ) {
    echo json_encode( [ 'success' => true, 'data' => $data ] );
    throw new Exception('wp_die');
}
function wp_unslash( $value ) { return $value; }
function wp_parse_str( $string, &$array ) { parse_str( $string, $array ); }
function sanitize_key( $str ) { return $str; }
function sanitize_text_field( $str ) { return $str; }
function admin_url( $path = '' ) { return $path; }
class WP_Query {
    public $posts;
    public function __construct( $args ) {
        $this->posts = [1,2,3];
    }
}

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../admin/class-gm2-bulk-review.php';

class BulkReviewTest extends TestCase {
    public function test_fetch_filtered_ids_returns_all_posts() {
        $_POST['nonce'] = 'dummy';
        $_POST['query'] = '';
        $handler = new Gm2_Bulk_Review();
        ob_start();
        try {
            $handler->ajax_fetch_post_ids();
        } catch ( Exception $e ) {
            // Expected due to wp_die stub
        }
        $output = ob_get_clean();
        $response = json_decode( $output, true );
        $this->assertTrue( $response['success'] );
        $this->assertEquals( [1,2,3], $response['data'] );
    }
}
