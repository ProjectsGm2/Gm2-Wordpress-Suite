<?php
use Gm2\Gm2_Custom_Posts_Admin;

class CustomPostsFieldsTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
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
                            'conditional' => [
                                'field' => 'show_extra',
                                'value' => '1',
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

        $this->assertStringContainsString('data-conditional-field="show_extra"', $html);
        $this->assertStringContainsString('data-conditional-value="1"', $html);
    }
}
