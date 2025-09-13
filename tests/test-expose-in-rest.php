<?php
use Gm2\Gm2_REST_Visibility;

class ExposeInRestTest extends WP_UnitTestCase {
    private $fields;

    public function setUp(): void {
        parent::setUp();
        register_post_type('book');
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
        gm2_register_field_groups();
        Gm2_REST_Visibility::apply_visibility();
    }

    public function tearDown(): void {
        unregister_post_type('book');
        delete_option('gm2_field_groups');
        delete_option(Gm2_REST_Visibility::OPTION);
        parent::tearDown();
    }

    public function test_field_exposed_in_rest() {
        $vis = Gm2_REST_Visibility::get_visibility();
        $this->assertTrue($vis['fields']['isbn']);
        $registered = get_registered_meta_keys('post', 'book');
        $this->assertArrayHasKey('isbn', $registered);
        $this->assertTrue($registered['isbn']['show_in_rest']);
    }

    public function test_saved_meta_visible_via_rest() {
        $post_id = self::factory()->post->create(['post_type' => 'book']);
        $_POST['isbn'] = '9781234567';
        gm2_save_field_group($this->fields, $post_id, 'post');
        $this->assertSame('9781234567', get_post_meta($post_id, 'isbn', true));

        $request = new WP_REST_Request('GET', '/wp/v2/book/' . $post_id);
        $request->set_param('context', 'edit');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        $this->assertSame('9781234567', $data['meta']['isbn']);
    }
}
