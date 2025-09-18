<?php

use Gm2\Gm2_REST_Visibility;
use Gm2\GraphQL\Registry as GraphQLRegistry;

if (!class_exists('WPGraphQL')) {
    class WPGraphQL {}
}

if (!function_exists('register_graphql_field')) {
    function register_graphql_field($type, $name, $config) {
        global $gm2_graphql_fields;
        if (!isset($gm2_graphql_fields[$type])) {
            $gm2_graphql_fields[$type] = [];
        }
        $gm2_graphql_fields[$type][$name] = $config;
    }
}

if (!function_exists('register_graphql_object_type')) {
    function register_graphql_object_type($name, $config) {
        global $gm2_graphql_types;
        $gm2_graphql_types[$name] = $config;
    }
}

$gm2_graphql_fields = [];
$gm2_graphql_types = [];

class GraphQLRegistrationTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        global $gm2_graphql_fields, $gm2_graphql_types;
        $gm2_graphql_fields = [];
        $gm2_graphql_types = [];

        unregister_post_type('book');
        register_post_type('book', [
            'label' => 'Book',
            'public' => true,
            'publicly_queryable' => true,
            'show_in_graphql' => true,
            'graphql_single_name' => 'Book',
        ]);

        register_post_meta('book', 'isbn', [
            'single' => true,
            'type' => 'string',
            'show_in_rest' => [
                'schema' => [
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('book', 'profile', [
            'single' => true,
            'type' => 'object',
            'show_in_rest' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'price' => [
                            'type' => 'number',
                        ],
                        'authors' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        update_option(Gm2_REST_Visibility::OPTION, [
            'post_types' => [ 'book' => true ],
            'taxonomies' => [],
            'fields' => [
                'isbn' => true,
                'profile' => true,
            ],
        ]);

        GraphQLRegistry::init();
    }

    protected function tearDown(): void {
        unregister_post_type('book');
        unregister_post_meta('book', 'isbn');
        unregister_post_meta('book', 'profile');
        delete_option(Gm2_REST_Visibility::OPTION);
        delete_option('gm2_field_caps');
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_registers_meta_fields_with_schema(): void {
        global $gm2_graphql_fields, $gm2_graphql_types;

        do_action('graphql_register_types');

        $this->assertArrayHasKey('Book', $gm2_graphql_fields);
        $this->assertArrayHasKey('isbn', $gm2_graphql_fields['Book']);
        $this->assertSame('String', $gm2_graphql_fields['Book']['isbn']['type']);

        $this->assertArrayHasKey('profile', $gm2_graphql_fields['Book']);
        $profileField = $gm2_graphql_fields['Book']['profile'];
        $this->assertSame('BookProfile', $profileField['type']);

        $this->assertArrayHasKey('BookProfile', $gm2_graphql_types);
        $profileTypeConfig = $gm2_graphql_types['BookProfile']['fields'];
        if (is_callable($profileTypeConfig)) {
            $profileTypeConfig = $profileTypeConfig();
        }
        $this->assertSame('Float', $profileTypeConfig['price']['type']);
        $this->assertSame(['list_of' => 'String'], $profileTypeConfig['authors']['type']);

        $postId = self::factory()->post->create([ 'post_type' => 'book' ]);
        update_post_meta($postId, 'isbn', '9781234567');
        update_post_meta($postId, 'profile', [
            'price' => '19.95',
            'authors' => [ 'Alice', 'Bob' ],
        ]);

        $userId = self::factory()->user->create([ 'role' => 'administrator' ]);
        wp_set_current_user($userId);

        $isbnResolver = $gm2_graphql_fields['Book']['isbn']['resolve'];
        $profileResolver = $gm2_graphql_fields['Book']['profile']['resolve'];

        $this->assertSame('9781234567', $isbnResolver(get_post($postId), [], null, null));
        $this->assertSame(
            [ 'price' => 19.95, 'authors' => [ 'Alice', 'Bob' ] ],
            $profileResolver(get_post($postId), [], null, null)
        );

        update_option('gm2_field_caps', [
            'isbn' => [
                'read' => [ 'editor' ],
            ],
        ]);

        $this->assertNull($isbnResolver(get_post($postId), [], null, null));
    }
}
