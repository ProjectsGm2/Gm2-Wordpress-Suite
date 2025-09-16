<?php

use Gm2\Fields\FieldGroupRegistry;
use Gm2\Fields\FieldTypeRegistry;
use Gm2\Fields\Renderer\AdminMetaBox;
use Gm2\Fields\Sanitizers\SanitizerRegistry;
use Gm2\Fields\Storage\MetaRegistrar;
use Gm2\Fields\Validation\ValidatorRegistry;

class RestRoundtripTest extends WP_UnitTestCase
{
    private FieldGroupRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        register_post_type('library_book', [
            'show_in_rest' => true,
            'supports'     => [ 'custom-fields', 'title' ],
        ]);

        $this->registry = new FieldGroupRegistry(
            new MetaRegistrar(),
            FieldTypeRegistry::withDefaults(),
            ValidatorRegistry::withDefaults(),
            SanitizerRegistry::withDefaults(),
            new AdminMetaBox()
        );

        $this->registry->registerGroup('library_book_details', [
            'contexts' => [ 'post' => [ 'library_book' ] ],
            'fields'   => [
                'published_on' => [ 'type' => 'date' ],
                'contact_email' => [ 'type' => 'email' ],
                'genres' => [
                    'type'    => 'multiselect',
                    'options' => [ 'fiction' => 'Fiction', 'history' => 'History' ],
                ],
            ],
        ]);

        $this->registry->boot();
    }

    protected function tearDown(): void
    {
        unregister_post_type('library_book');
        parent::tearDown();
    }

    public function test_rest_roundtrip_creates_and_reads_meta(): void
    {
        wp_set_current_user(self::factory()->user->create([ 'role' => 'administrator' ]));

        $request = new WP_REST_Request('POST', '/wp/v2/library_book');
        $request->set_body_params([
            'title' => 'REST Book',
            'status' => 'publish',
            'meta' => [
                'published_on' => '2024-05-01',
                'contact_email' => ' author@example.com ',
                'genres' => [ 'fiction', 'unknown' ],
            ],
        ]);

        $response = rest_get_server()->dispatch($request);
        $this->assertSame(201, $response->get_status());

        $data = $response->get_data();
        $postId = $data['id'];

        $this->assertSame('2024-05-01', get_post_meta($postId, 'published_on', true));
        $this->assertSame('author@example.com', get_post_meta($postId, 'contact_email', true));
        $this->assertSame([ 'fiction' ], get_post_meta($postId, 'genres', true));

        $getRequest = new WP_REST_Request('GET', '/wp/v2/library_book/' . $postId);
        $getRequest->set_param('context', 'edit');
        $getResponse = rest_get_server()->dispatch($getRequest);
        $this->assertSame(200, $getResponse->get_status());

        $restData = $getResponse->get_data();
        $this->assertSame('2024-05-01', $restData['meta']['published_on']);
        $this->assertSame('author@example.com', $restData['meta']['contact_email']);
        $this->assertSame([ 'fiction' ], (array) $restData['meta']['genres']);
    }
}
