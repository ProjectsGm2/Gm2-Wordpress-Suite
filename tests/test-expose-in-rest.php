<?php
use Gm2\Gm2_REST_Visibility;

class ExposeInRestTest extends WP_UnitTestCase {
    private $fields;

    public function setUp(): void {
        parent::setUp();
        register_post_type('book', [
            'show_in_rest' => true,
            'supports'     => [ 'custom-fields' ],
        ]);
        $this->fields = [
            'isbn' => [
                'label' => 'ISBN',
                'type'  => 'text',
                'expose_in_rest' => true,
            ],
        ];
        update_option('gm2_field_groups', [
            'books' => [
                'title'   => 'Books',
                'scope'   => 'post_type',
                'objects' => ['book'],
                'fields'  => $this->fields,
            ],
        ]);
        delete_option(Gm2_REST_Visibility::OPTION);
    }

    public function tearDown(): void {
        unregister_post_type('book');
        delete_option('gm2_field_groups');
        delete_option(Gm2_REST_Visibility::OPTION);
        parent::tearDown();
    }

    private function register_field_groups(): void {
        gm2_register_field_groups();
        Gm2_REST_Visibility::apply_visibility();
    }

    public function test_field_exposed_in_rest() {
        $this->register_field_groups();
        $vis = Gm2_REST_Visibility::get_visibility();
        $this->assertTrue($vis['fields']['isbn']);
        $registered = get_registered_meta_keys('post', 'book');
        $this->assertArrayHasKey('isbn', $registered);
        $this->assertTrue($registered['isbn']['show_in_rest']);
    }

    public function test_saved_meta_visible_via_rest() {
        $this->register_field_groups();
        $post_id = self::factory()->post->create(['post_type' => 'book']);
        gm2_save_field_group($this->fields, $post_id, 'post', [ 'isbn' => '9781234567' ]);
        $this->assertSame('9781234567', get_post_meta($post_id, 'isbn', true));

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $request = new WP_REST_Request('GET', '/wp/v2/book/' . $post_id);
        $request->set_param('context', 'edit');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        $this->assertContains('9781234567', (array) $data['meta']['isbn']);
    }

    public function test_expose_in_rest_registers_meta_and_rest_response() {
        $calls = [];
        $callback = function ($args, $defaults, $object_type, $meta_key) use (&$calls) {
            if ($meta_key === 'isbn') {
                $calls[] = [
                    'args'        => $args,
                    'defaults'    => $defaults,
                    'object_type' => $object_type,
                    'meta_key'    => $meta_key,
                ];
            }
            return $args;
        };
        add_filter('register_meta_args', $callback, 10, 4);

        $this->register_field_groups();

        $this->assertNotEmpty($calls, 'register_meta should be invoked for expose_in_rest fields.');
        $this->assertSame('post', $calls[0]['object_type']);
        $this->assertTrue($calls[0]['args']['show_in_rest']);
        $this->assertSame('book', $calls[0]['args']['object_subtype']);

        $post_id = self::factory()->post->create(['post_type' => 'book']);
        gm2_save_field_group($this->fields, $post_id, 'post', [ 'isbn' => '9785555555' ]);

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $request = new WP_REST_Request('GET', '/wp/v2/book/' . $post_id);
        $request->set_param('context', 'edit');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        $this->assertContains('9785555555', (array) $data['meta']['isbn']);

        remove_filter('register_meta_args', $callback, 10);
    }
}
