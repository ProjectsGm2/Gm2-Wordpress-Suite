<?php
use Gm2\Gm2_SEO_Admin;

class BrandMetaTest extends WP_UnitTestCase {
    private $term_id;

    public function setUp(): void {
        parent::setUp();
        register_taxonomy('brand', 'post');
        $this->term_id = self::factory()->term->create([
            'taxonomy' => 'brand',
            'name'     => 'Acme',
        ]);
        add_action('save_post', [$this, 'assign_brand_term'], 20, 1);
    }

    public function tearDown(): void {
        remove_action('save_post', [$this, 'assign_brand_term'], 20);
        $_POST = [];
        parent::tearDown();
    }

    public function assign_brand_term($post_id) {
        wp_set_post_terms($post_id, [$this->term_id], 'brand');
    }

    public function test_schema_brand_populated_after_save_post() {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $_POST['gm2_seo_nonce'] = wp_create_nonce('gm2_save_seo_meta');

        $admin = new Gm2_SEO_Admin();
        $admin->run();

        $post_id = wp_insert_post([
            'post_title'  => 'Brand Post',
            'post_status' => 'publish',
        ]);

        $this->assertSame('Acme', get_post_meta($post_id, '_gm2_schema_brand', true));
    }
}

