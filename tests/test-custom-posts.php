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
        if (post_type_exists('book')) {
            unregister_post_type('book');
        }
        $_POST = [];
        $_REQUEST = [];
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
}
