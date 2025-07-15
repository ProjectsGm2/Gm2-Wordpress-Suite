<?php
use Gm2\Gm2_SEO_Admin;

class TaxonomyHooksTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        register_post_type('product');
        register_taxonomy('product_cat', 'product');
    }

    public function test_hooks_registered_and_term_meta_saved() {
        $admin = new Gm2_SEO_Admin();
        $admin->run();

        // Hooks are added on init, so before that nothing should be registered.
        $this->assertFalse( has_action('product_cat_add_form_fields', [$admin, 'render_taxonomy_meta_box']) );

        do_action('init');

        $this->assertNotFalse( has_action('product_cat_add_form_fields', [$admin, 'render_taxonomy_meta_box']) );
        $this->assertNotFalse( has_action('create_product_cat', [$admin, 'save_taxonomy_meta']) );

        $_POST['gm2_seo_nonce'] = wp_create_nonce('gm2_save_seo_meta');
        $_POST['gm2_seo_title'] = 'Cat Title';
        $term  = wp_insert_term('New Cat', 'product_cat');
        $term_id = $term['term_id'];

        $this->assertSame('Cat Title', get_term_meta($term_id, '_gm2_title', true));
    }
}

