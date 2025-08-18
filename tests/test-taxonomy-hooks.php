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

    public function test_default_terms_created_with_meta_and_hooks() {
        update_option('gm2_custom_posts_config', [
            'post_types' => [],
            'taxonomies' => [
                'genre' => [
                    'label' => 'Genre',
                    'post_types' => ['post'],
                    'args' => [
                        'public'      => [ 'value' => true ],
                        'show_ui'     => [ 'value' => true ],
                        'show_in_rest'=> [ 'value' => true ],
                    ],
                    'default_terms' => [
                        [
                            'slug' => 'horror',
                            'name' => 'Horror',
                            'description' => 'Scary stuff',
                            'color' => '#ff0000',
                            'icon'  => 'dashicons-media-text',
                            'order' => 1,
                            'meta'  => [ 'rating' => '5' ],
                        ],
                    ],
                    'term_fields' => [
                        'rating' => [ 'type' => 'number', 'description' => 'Rating' ],
                    ],
                ],
            ],
        ]);

        gm2_register_custom_posts();

        $this->assertTrue( taxonomy_exists('genre') );
        $this->assertNotFalse( has_action('genre_add_form_fields') );
        $this->assertNotFalse( has_action('created_genre') );

        ob_start();
        do_action('genre_add_form_fields');
        $html = ob_get_clean();
        $this->assertStringContainsString('name="color"', $html);
        $this->assertStringContainsString('name="icon"', $html);
        $this->assertStringContainsString('name="_gm2_order"', $html);

        $term = get_term_by('slug', 'horror', 'genre');
        $this->assertNotEmpty($term);
        $this->assertSame('Scary stuff', $term->description);
        $this->assertSame('#ff0000', get_term_meta($term->term_id, 'color', true));
        $this->assertSame('dashicons-media-text', get_term_meta($term->term_id, 'icon', true));
        $this->assertSame('1', get_term_meta($term->term_id, '_gm2_order', true));
        $this->assertSame('5', get_term_meta($term->term_id, 'rating', true));

        $meta_keys = get_registered_meta_keys('term', 'genre');
        $this->assertArrayHasKey('color', $meta_keys);
        $this->assertArrayHasKey('icon', $meta_keys);
        $this->assertArrayHasKey('_gm2_order', $meta_keys);
        $this->assertArrayHasKey('rating', $meta_keys);

        unregister_taxonomy('genre');
        delete_option('gm2_custom_posts_config');
    }

    public function test_terms_ordered_when_enabled() {
        update_option('gm2_custom_posts_config', [
            'post_types' => [],
            'taxonomies' => [
                'ordered' => [
                    'label'      => 'Ordered',
                    'post_types' => ['post'],
                    'ordering'   => true,
                    'default_terms' => [
                        [ 'slug' => 'b-term', 'name' => 'B', 'order' => 1 ],
                        [ 'slug' => 'a-term', 'name' => 'A', 'order' => 2 ],
                    ],
                ],
            ],
        ]);

        gm2_register_custom_posts();

        $terms = get_terms([
            'taxonomy'   => 'ordered',
            'hide_empty' => false,
        ]);

        $this->assertSame(['b-term', 'a-term'], wp_list_pluck($terms, 'slug'));

        unregister_taxonomy('ordered');
        delete_option('gm2_custom_posts_config');
        remove_all_filters('pre_get_terms');
    }
}

