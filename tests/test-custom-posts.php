<?php
use Gm2\Gm2_Custom_Posts_Admin;

class CustomPostsFieldsTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        $this->user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->user_id);
        update_option('gm2_custom_posts_config', [
            'post_types' => [
                'book' => [
                    'label' => 'Book',
                    'fields' => [
                        'price' => [
                            'label' => 'Price',
                            'type' => 'number',
                        ],
                        'show_extra' => [
                            'label' => 'Show Extra',
                            'type' => 'checkbox',
                        ],
                        'extra' => [
                            'label' => 'Extra',
                            'type' => 'text',
                            'conditions' => [
                                [
                                    'relation' => 'AND',
                                    'conditions' => [
                                        [
                                            'relation' => 'AND',
                                            'target'   => 'show_extra',
                                            'operator' => '=',
                                            'value'    => '1',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'taxonomies' => [],
        ]);
        register_post_type('book');
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('gm2_custom_posts_config');
        foreach (['book','movie'] as $pt) {
            if (post_type_exists($pt)) {
                unregister_post_type($pt);
            }
        }
        if (taxonomy_exists('genre')) {
            unregister_taxonomy('genre');
        }
        $_POST = [];
        $_REQUEST = [];
        $_GET = [];
        wp_set_current_user(0);
    }

    public function test_fields_save_and_load() {
        $admin = new Gm2_Custom_Posts_Admin();
        $post_id = self::factory()->post->create([
            'post_type' => 'book',
            'post_status' => 'publish',
        ]);

        $_POST = [
            'gm2_custom_fields_nonce' => wp_create_nonce('gm2_save_custom_fields'),
            'price' => ' 25 ',
            'show_extra' => '1',
            'extra' => 'info',
        ];
        $admin->save_meta_boxes($post_id);

        $this->assertSame('25', get_post_meta($post_id, 'price', true));
        $this->assertSame('1', get_post_meta($post_id, 'show_extra', true));
        $this->assertSame('info', get_post_meta($post_id, 'extra', true));
    }

    public function test_conditional_attributes_added() {
        $admin = new Gm2_Custom_Posts_Admin();
        $post_id = self::factory()->post->create([
            'post_type' => 'book',
        ]);
        $post = get_post($post_id);
        $config = get_option('gm2_custom_posts_config');
        $fields = $config['post_types']['book']['fields'];

        ob_start();
        $admin->render_meta_box($post, $fields, 'book');
        $html = ob_get_clean();

        $this->assertStringContainsString('data-conditions=', $html);
        preg_match('/data-conditions="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m);
        $decoded = json_decode(htmlspecialchars_decode($m[1]), true);
        $this->assertSame('show_extra', $decoded[0]['conditions'][0]['target']);
        $this->assertSame('=', $decoded[0]['conditions'][0]['operator']);
        $this->assertSame('1', $decoded[0]['conditions'][0]['value']);
    }

    public function test_extra_field_not_saved_when_conditions_fail() {
        $admin = new Gm2_Custom_Posts_Admin();
        $post_id = self::factory()->post->create([
            'post_type' => 'book',
            'post_status' => 'publish',
        ]);

        $_POST = [
            'gm2_custom_fields_nonce' => wp_create_nonce('gm2_save_custom_fields'),
            'price' => '10',
            'extra' => 'should-not-save',
        ];
        $admin->save_meta_boxes($post_id);

        $this->assertSame('', get_post_meta($post_id, 'extra', true));
    }

    public function test_ajax_save_fields_sanitizes_input() {
        $admin = new Gm2_Custom_Posts_Admin();
        $config = get_option('gm2_custom_posts_config');
        $this->assertArrayHasKey('book', $config['post_types']);

        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $_POST = [
            'nonce' => wp_create_nonce('gm2_save_cpt_fields'),
            'slug'  => 'book',
            'fields' => [
                [
                    'label' => '<b>Label</b>',
                    'slug'  => 'bad slug',
                    'type'  => 'text',
                    'default' => '<script>1</script>',
                    'conditions' => [
                        [
                            'relation' => 'AND',
                            'conditions' => [
                                [
                                    'relation' => 'AND',
                                    'target'   => ' show_extra ',
                                    'operator' => 'INVALID',
                                    'value'    => '1',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'args' => [],
        ];

        try {
            $admin->ajax_save_fields();
        } catch (\WPDieException $e) {
            // wp_send_json_success ends execution.
        }

        $config = get_option('gm2_custom_posts_config');
        $saved  = $config['post_types']['book']['fields']['bad_slug'];
        $this->assertSame('Label', $saved['label']);
        $this->assertSame('text', $saved['type']);
        $this->assertSame('1', $saved['default']);
        $this->assertSame('show_extra', $saved['conditions'][0]['conditions'][0]['target']);
        $this->assertSame('=', $saved['conditions'][0]['conditions'][0]['operator']);
    }

    public function test_render_field_group_outputs_disabled_flag() {
        $_REQUEST['trig'] = '1';
        $fields = [
            'trig' => [
                'type' => 'checkbox',
            ],
            'locked' => [
                'type' => 'text',
                'conditions' => [
                    [
                        'relation' => 'AND',
                        'action'   => 'disable',
                        'conditions' => [
                            [
                                'relation' => 'AND',
                                'target'   => 'trig',
                                'operator' => '=',
                                'value'    => '1',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        ob_start();
        gm2_render_field_group($fields, 0, 'post');
        $html = ob_get_clean();
        unset($_REQUEST['trig']);
        $this->assertStringContainsString('data-disabled="1"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="locked"[^>]*disabled/', $html);
    }

    public function test_inline_edit_fields_render_inputs() {
        $admin = new Gm2_Custom_Posts_Admin();
        ob_start();
        $admin->inline_edit_fields('cb', 'book');
        $html = ob_get_clean();
        $this->assertStringContainsString('name="price"', $html);
        $this->assertStringContainsString('name="show_extra"', $html);
        $this->assertStringContainsString('gm2_custom_fields_nonce', $html);
    }

    public function test_save_meta_boxes_uses_request_values() {
        $admin = new Gm2_Custom_Posts_Admin();
        $post_id = self::factory()->post->create([
            'post_type' => 'book',
            'post_status' => 'publish',
        ]);

        $_REQUEST = [
            'gm2_custom_fields_nonce' => wp_create_nonce('gm2_save_custom_fields'),
            'price' => '30',
            'show_extra' => '1',
        ];

        $admin->save_meta_boxes($post_id);

        $this->assertSame('30', get_post_meta($post_id, 'price', true));
        $this->assertSame('1', get_post_meta($post_id, 'show_extra', true));
    }

    public function test_list_table_columns_and_filters() {
        $config = get_option('gm2_custom_posts_config');
        $config['post_types']['book']['fields']['price']['column'] = true;
        $config['post_types']['book']['fields']['price']['sortable'] = true;
        $config['post_types']['book']['fields']['price']['quick_edit'] = true;
        $config['post_types']['book']['fields']['price']['bulk_edit'] = true;
        $config['post_types']['book']['fields']['price']['filter'] = true;
        $config['taxonomies'] = [
            'genre' => [
                'label' => 'Genre',
                'post_types' => [ 'book' ],
                'filter' => true,
                'args' => [],
            ],
        ];
        update_option('gm2_custom_posts_config', $config);
        register_taxonomy('genre', 'book');

        $admin = new Gm2_Custom_Posts_Admin();
        $admin->run();
        $admin->add_list_table_hooks();

        $post_id = self::factory()->post->create([
            'post_type' => 'book',
            'post_status' => 'publish',
        ]);
        update_post_meta($post_id, 'price', '9');

        $cols = apply_filters('manage_book_posts_columns', []);
        $this->assertArrayHasKey('price', $cols);
        $sortable = apply_filters('manage_edit-book_sortable_columns', []);
        $this->assertArrayHasKey('price', $sortable);
        ob_start();
        do_action('manage_book_posts_custom_column', 'price', $post_id);
        $out = trim(ob_get_clean());
        $this->assertSame('9', $out);

        ob_start();
        do_action('quick_edit_custom_box', 'cb', 'book');
        $quick_html = ob_get_clean();
        $this->assertStringContainsString('name="price"', $quick_html);

        ob_start();
        do_action('bulk_edit_custom_box', 'cb', 'book');
        $bulk_html = ob_get_clean();
        $this->assertStringContainsString('name="price"', $bulk_html);

        set_current_screen('edit-book');
        ob_start();
        $admin->restrict_manage_posts();
        $filters_html = ob_get_clean();
        $this->assertStringContainsString('name="price"', $filters_html);
        $this->assertStringContainsString('name="genre"', $filters_html);

        $_GET['price'] = '9';
        $_GET['genre'] = 'fiction';
        $query = new WP_Query(['post_type' => 'book']);
        $query->is_admin = true;
        $query->is_main_query = true;
        set_current_screen('edit-book');
        $admin->pre_get_posts($query);
        $mq = $query->get('meta_query');
        $this->assertSame('price', $mq[0]['key']);
        $tq = $query->get('tax_query');
        $this->assertSame('genre', $tq[0]['taxonomy']);
    }

    public function test_register_post_type_args() {
        update_option('gm2_custom_posts_config', [
            'post_types' => [
                'movie' => [
                    'label' => 'Movie',
                    'fields' => [],
                    'args' => [
                        'labels' => [ 'value' => [ 'name' => 'Movies', 'singular_name' => 'Movie' ] ],
                        'menu_icon' => [ 'value' => 'dashicons-video-alt' ],
                        'menu_position' => [ 'value' => 7 ],
                        'supports' => [ 'value' => [ 'title','editor','excerpt','author','thumbnail','page-attributes','custom-fields','revisions' ] ],
                        'public' => [ 'value' => true ],
                        'publicly_queryable' => [ 'value' => true ],
                        'show_in_nav_menus' => [ 'value' => true ],
                        'show_ui' => [ 'value' => true ],
                        'show_in_menu' => [ 'value' => true ],
                        'show_in_admin_bar' => [ 'value' => true ],
                        'show_in_rest' => [ 'value' => true ],
                        'rest_base' => [ 'value' => 'film' ],
                        'rest_controller_class' => [ 'value' => 'WP_REST_Posts_Controller' ],
                        'rewrite' => [ 'value' => [ 'slug'=>'films','with_front'=>true,'hierarchical'=>true,'feeds'=>true,'pages'=>true ] ],
                        'map_meta_cap' => [ 'value' => true ],
                        'capability_type' => [ 'value' => [ 'movie','movies' ] ],
                        'capabilities' => [ 'value' => [ 'edit_post' => 'edit_movie' ] ],
                        'template' => [ 'value' => [ [ 'core/paragraph', [ 'placeholder' => 'Add summary...' ] ] ] ],
                        'template_lock' => [ 'value' => 'all' ],
                        'description' => [ 'value' => 'Film posts' ],
                        'delete_with_user' => [ 'value' => true ],
                        'rest_namespace' => [ 'value' => 'gm2/v1' ],
                        'taxonomies' => [ 'value' => [ 'genre' ] ],
                        'can_export' => [ 'value' => true ],
                    ],
                ],
            ],
            'taxonomies' => [],
        ]);

        register_taxonomy('genre', []);
        gm2_register_custom_posts();
        $pt = get_post_type_object('movie');

        $this->assertSame('Movies', $pt->labels->name);
        $this->assertSame('dashicons-video-alt', $pt->menu_icon);
        $this->assertSame(7, $pt->menu_position);
        $supports = get_all_post_type_supports('movie');
        foreach(['title','editor','excerpt','author','thumbnail','page-attributes','custom-fields','revisions'] as $s){
            $this->assertArrayHasKey($s, $supports);
        }
        $this->assertTrue($pt->public);
        $this->assertTrue($pt->publicly_queryable);
        $this->assertTrue($pt->show_in_nav_menus);
        $this->assertTrue($pt->show_ui);
        $this->assertTrue($pt->show_in_menu);
        $this->assertTrue($pt->show_in_admin_bar);
        $this->assertTrue($pt->show_in_rest);
        $this->assertSame('film', $pt->rest_base);
        $this->assertSame('WP_REST_Posts_Controller', $pt->rest_controller_class);
        $this->assertSame('gm2/v1', $pt->rest_namespace);
        $this->assertSame('films', $pt->rewrite['slug']);
        $this->assertTrue($pt->rewrite['with_front']);
        $this->assertTrue($pt->rewrite['hierarchical']);
        $this->assertTrue($pt->rewrite['feeds']);
        $this->assertTrue($pt->rewrite['pages']);
        $this->assertTrue($pt->map_meta_cap);
        $this->assertSame('movie', $pt->capability_type);
        $this->assertSame('edit_movie', $pt->cap->edit_post);
        $this->assertSame([ [ 'core/paragraph', [ 'placeholder' => 'Add summary...' ] ] ], $pt->template);
        $this->assertSame('all', $pt->template_lock);
        $this->assertSame('Film posts', $pt->description);
        $this->assertTrue($pt->delete_with_user);
        $this->assertTrue($pt->can_export);
        $this->assertContains('genre', $pt->taxonomies);
    }
}
