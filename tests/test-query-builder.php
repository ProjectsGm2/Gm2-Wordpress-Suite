<?php
use Gm2\Query_Manager;
use WP_REST_Request;

class QueryBuilderTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('gm2_saved_queries');
        wp_set_current_user(0);
    }

    public function test_saved_query_filters_posts() {
        $cat1 = self::factory()->term->create(['taxonomy' => 'category', 'slug' => 'fiction']);
        $cat2 = self::factory()->term->create(['taxonomy' => 'category', 'slug' => 'nonfiction']);

        $p1 = self::factory()->post->create([
            'post_date'   => '2024-05-01 00:00:00',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($p1, $cat1, 'category');
        update_post_meta($p1, 'color', 'red');

        $p2 = self::factory()->post->create([
            'post_date'   => '2024-05-01 00:00:00',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($p2, $cat2, 'category');
        update_post_meta($p2, 'color', 'red');

        $p3 = self::factory()->post->create([
            'post_date'   => '2023-05-01 00:00:00',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($p3, $cat1, 'category');
        update_post_meta($p3, 'color', 'red');

        Query_Manager::save_query('test', [
            'post_type' => 'post',
            'tax_query' => [
                ['taxonomy' => 'category', 'field' => 'slug', 'terms' => ['fiction']],
            ],
            'meta_query' => [
                ['key' => 'color', 'value' => 'red', 'compare' => '='],
            ],
            'date_query' => [
                ['after' => '2024-01-01', 'before' => '2024-12-31', 'inclusive' => true],
            ],
        ]);

        $args  = Query_Manager::get_query('test');
        $query = \Gm2\gm2_run_query($args);
        $ids   = wp_list_pluck($query->posts, 'ID');

        $this->assertSame([$p1], $ids);
    }

    public function test_rest_post_query_whitelists_fields() {
        $request = new WP_REST_Request('GET', '/wp/v2/posts');
        $request->set_param('meta_key', 'color');
        $request->set_param('meta_value', 'red');
        $request->set_param('taxonomy', 'category');
        $request->set_param('term', 'fiction');
        $request->set_param('after', '2024-01-01');
        $request->set_param('before', '2024-12-31');

        $args = apply_filters('rest_post_query', [], $request);

        $this->assertSame('color', $args['meta_query'][0]['key']);
        $this->assertSame('red', $args['meta_query'][0]['value']);
        $this->assertSame('category', $args['tax_query'][0]['taxonomy']);
        $this->assertSame(['fiction'], $args['tax_query'][0]['terms']);
        $this->assertSame('2024-01-01', $args['date_query'][0]['after']);
        $this->assertSame('2024-12-31', $args['date_query'][0]['before']);
    }
}
