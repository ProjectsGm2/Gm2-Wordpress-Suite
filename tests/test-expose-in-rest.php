<?php
use Gm2\Gm2_REST_Visibility;

class ExposeInRestTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        register_post_type('book');
        update_option('gm2_field_groups', [
            'books' => [
                'title' => 'Books',
                'scope' => 'post_type',
                'objects' => ['book'],
                'fields' => [
                    'isbn' => [
                        'label' => 'ISBN',
                        'type' => 'text',
                        'expose_in_rest' => true,
                    ],
                ],
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
}
