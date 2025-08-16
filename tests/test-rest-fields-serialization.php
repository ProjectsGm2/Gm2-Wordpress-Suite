<?php
use Gm2\Gm2_REST_Fields;
use Gm2\Gm2_REST_Visibility;

class RestFieldsSerializationTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        register_post_type('book');
        update_option('gm2_custom_posts_config', [
            'post_types' => [
                'book' => [
                    'label' => 'Book',
                    'fields' => [
                        'raw_field' => [
                            'label' => 'Raw',
                            'type' => 'text',
                            'serialize' => 'raw',
                        ],
                        'render_field' => [
                            'label' => 'Render',
                            'type' => 'text',
                            'serialize' => 'rendered',
                        ],
                        'media_field' => [
                            'label' => 'Media',
                            'type' => 'media',
                            'serialize' => 'media',
                        ],
                    ],
                ],
            ],
            'taxonomies' => [],
        ]);
        update_option(Gm2_REST_Visibility::OPTION, [
            'post_types' => [ 'book' => true ],
            'taxonomies' => [],
            'fields' => [
                'raw_field' => true,
                'render_field' => true,
                'media_field' => true,
            ],
        ]);
        Gm2_REST_Fields::init();
        do_action('rest_api_init');
        remove_all_filters('the_content');
        add_filter('the_content', function($content) { return 'FILTER:' . $content; });
    }

    public function tearDown(): void {
        remove_all_filters('the_content');
        unregister_post_type('book');
        delete_option('gm2_custom_posts_config');
        delete_option(Gm2_REST_Visibility::OPTION);
        parent::tearDown();
    }

    public function test_field_serialization_modes() {
        $post_id = self::factory()->post->create(['post_type' => 'book']);
        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg', $post_id);
        update_post_meta($post_id, 'raw_field', '<p>raw</p>');
        update_post_meta($post_id, 'render_field', 'render');
        update_post_meta($post_id, 'media_field', $attachment_id);

        $request = new WP_REST_Request('GET', '/gm2/v1/fields/' . $post_id);
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertSame('<p>raw</p>', $data['raw_field']);
        $this->assertSame('FILTER:render', $data['render_field']);
        $this->assertIsArray($data['media_field']);
        $this->assertSame($attachment_id, $data['media_field']['id']);
    }
}
