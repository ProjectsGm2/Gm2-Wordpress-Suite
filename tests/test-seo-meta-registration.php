<?php

use Gm2\SEO\Meta_Registration;

class Seo_Meta_Registration_Test extends WP_UnitTestCase {
    /**
     * @var WP_REST_Server
     */
    private $server;

    public function set_up(): void {
        parent::set_up();

        if (!did_action('init')) {
            do_action('init');
        }

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        $this->server   = $wp_rest_server;

        do_action('rest_api_init');
    }

    public function tear_down(): void {
        global $wp_rest_server;
        $wp_rest_server = null;

        parent::tear_down();
    }

    private function get_supported_post_types(): array {
        $args = [
            'public'              => true,
            'show_ui'             => true,
            'exclude_from_search' => false,
        ];
        $types = get_post_types($args, 'names');
        unset($types['attachment']);
        $types = apply_filters('gm2_supported_post_types', array_values($types));
        return array_values(array_unique($types));
    }

    private function get_supported_taxonomies(): array {
        $taxonomies = ['category'];
        if (taxonomy_exists('product_cat')) {
            $taxonomies[] = 'product_cat';
        }
        if (taxonomy_exists('brand')) {
            $taxonomies[] = 'brand';
        }
        if (taxonomy_exists('product_brand')) {
            $taxonomies[] = 'product_brand';
        }
        return array_values(array_unique($taxonomies));
    }

    public function test_post_meta_keys_registered(): void {
        foreach ($this->get_supported_post_types() as $post_type) {
            $registered = get_registered_meta_keys('post', $post_type);
            foreach (Meta_Registration::get_post_meta_keys() as $meta_key) {
                $this->assertArrayHasKey(
                    $meta_key,
                    $registered,
                    sprintf('Meta key %s was not registered for post type %s', $meta_key, $post_type)
                );
                $args = $registered[$meta_key];
                $this->assertTrue($args['single']);
                $this->assertSame($post_type, $args['object_subtype']);
                $this->assertIsArray($args['show_in_rest']);
                $this->assertArrayHasKey('schema', $args['show_in_rest']);
            }
        }
    }

    public function test_term_meta_keys_registered(): void {
        foreach ($this->get_supported_taxonomies() as $taxonomy) {
            $registered = get_registered_meta_keys('term', $taxonomy);
            foreach (Meta_Registration::get_term_meta_keys() as $meta_key) {
                $this->assertArrayHasKey(
                    $meta_key,
                    $registered,
                    sprintf('Meta key %s was not registered for taxonomy %s', $meta_key, $taxonomy)
                );
                $args = $registered[$meta_key];
                $this->assertTrue($args['single']);
                $this->assertSame($taxonomy, $args['object_subtype']);
                $this->assertIsArray($args['show_in_rest']);
                $this->assertArrayHasKey('schema', $args['show_in_rest']);
            }
        }
    }

    public function test_rest_sanitizes_post_meta(): void {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/wp/v2/posts');
        $request->set_param('title', 'SEO Post');
        $request->set_param('status', 'draft');
        $request->set_param('meta', [
            '_gm2_title' => ' <b>Headline</b> ',
            '_gm2_description' => "Line<script>alert('x')</script>",
            '_gm2_noindex' => 'true',
            '_gm2_focus_keyword_limit' => '9words',
            '_gm2_link_rel' => '{"https://example.com/":"nofollow<script>"}',
            '_aeseo_lcp_override' => 'https://example.com/override<script>',
            '_aeseo_lcp_disable' => 'no',
            '_gm2_breadcrumb_title' => '  <b>Trail</b>  ',
        ]);

        $response = $this->server->dispatch($request);
        $this->assertSame(201, $response->get_status());
        $post_id = $response->get_data()['id'];

        $this->assertSame('Headline', get_post_meta($post_id, '_gm2_title', true));
        $this->assertSame("Linealert('x')", get_post_meta($post_id, '_gm2_description', true));
        $this->assertSame('1', get_post_meta($post_id, '_gm2_noindex', true));
        $this->assertSame('9', get_post_meta($post_id, '_gm2_focus_keyword_limit', true));
        $this->assertSame('Trail', get_post_meta($post_id, '_gm2_breadcrumb_title', true));

        $link_rel = get_post_meta($post_id, '_gm2_link_rel', true);
        $this->assertIsString($link_rel);
        $this->assertNotSame('', $link_rel);
        $this->assertSame(
            ['https://example.com/' => 'nofollowscript'],
            json_decode($link_rel, true)
        );

        $expected_override = esc_url_raw('https://example.com/override<script>');
        $this->assertSame($expected_override, get_post_meta($post_id, '_aeseo_lcp_override', true));
        $this->assertSame('0', get_post_meta($post_id, '_aeseo_lcp_disable', true));

        $update = new WP_REST_Request('POST', sprintf('/wp/v2/posts/%d', $post_id));
        $update->set_param('meta', [
            '_gm2_noindex' => false,
            '_gm2_focus_keyword_limit' => 'not-a-number',
            '_aeseo_lcp_override' => 42,
            '_gm2_link_rel' => '{"https://example.org/":["nofollow","noopener"]}',
        ]);

        $update_response = $this->server->dispatch($update);
        $this->assertSame(200, $update_response->get_status());

        $this->assertSame('0', get_post_meta($post_id, '_gm2_noindex', true));
        $this->assertSame('0', get_post_meta($post_id, '_gm2_focus_keyword_limit', true));
        $this->assertSame('42', get_post_meta($post_id, '_aeseo_lcp_override', true));
        $this->assertSame(
            ['https://example.org/' => 'nofollow noopener'],
            json_decode(get_post_meta($post_id, '_gm2_link_rel', true), true)
        );
    }

    public function test_rest_sanitizes_term_meta(): void {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/wp/v2/categories');
        $request->set_param('name', 'SEO Category');
        $request->set_param('slug', 'seo-category');
        $request->set_param('meta', [
            '_gm2_description' => " Term<script>alert('y')</script> ",
            '_gm2_improve_readability' => 'yes',
            '_gm2_number_of_words' => '15 words',
            '_gm2_canonical' => 'https://example.com/category<script>',
            '_gm2_breadcrumb_title' => ' <b>Category Trail</b> ',
        ]);

        $response = $this->server->dispatch($request);
        $this->assertSame(201, $response->get_status());
        $term_id = $response->get_data()['id'];

        $this->assertSame("Termalert('y')", get_term_meta($term_id, '_gm2_description', true));
        $this->assertSame('1', get_term_meta($term_id, '_gm2_improve_readability', true));
        $this->assertSame('15', get_term_meta($term_id, '_gm2_number_of_words', true));
        $this->assertSame(
            esc_url_raw('https://example.com/category<script>'),
            get_term_meta($term_id, '_gm2_canonical', true)
        );
        $this->assertSame('Category Trail', get_term_meta($term_id, '_gm2_breadcrumb_title', true));

        $update = new WP_REST_Request('POST', sprintf('/wp/v2/categories/%d', $term_id));
        $update->set_param('meta', [
            '_gm2_improve_readability' => '',
            '_gm2_number_of_words' => 'words only',
        ]);

        $update_response = $this->server->dispatch($update);
        $this->assertSame(200, $update_response->get_status());

        $this->assertSame('0', get_term_meta($term_id, '_gm2_improve_readability', true));
        $this->assertSame('0', get_term_meta($term_id, '_gm2_number_of_words', true));
    }
}
