<?php

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

        GraphQLRegistry::init();
    }

    protected function tearDown(): void {
        unregister_post_type('book');
        unregister_post_meta('book', 'isbn');
        unregister_post_meta('book', 'profile');
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

    public function test_publicly_queryable_post_types_are_respected(): void {
        global $gm2_graphql_fields;

        unregister_post_type('private_book');
        register_post_type('private_book', [
            'label' => 'Private Book',
            'public' => false,
            'publicly_queryable' => false,
            'show_in_graphql' => true,
            'graphql_single_name' => 'PrivateBook',
        ]);

        register_post_meta('private_book', 'secret', [
            'single' => true,
            'type' => 'string',
            'show_in_rest' => [
                'schema' => [
                    'type' => 'string',
                ],
            ],
        ]);

        do_action('graphql_register_types');

        $this->assertArrayNotHasKey('PrivateBook', $gm2_graphql_fields);

        unregister_post_type('private_book');
        unregister_post_meta('private_book', 'secret');
    }

    public function test_field_name_filter_allows_custom_names(): void {
        global $gm2_graphql_fields;

        $callback = function ($name, $metaKey, $objectType) {
            if ($metaKey === 'isbn' && $objectType === 'post') {
                return 'isbnCode';
            }

            return $name;
        };

        add_filter('gm2/graphql/field_name', $callback, 10, 5);

        do_action('graphql_register_types');

        remove_filter('gm2/graphql/field_name', $callback, 10);

        $this->assertArrayHasKey('Book', $gm2_graphql_fields);
        $this->assertArrayHasKey('isbnCode', $gm2_graphql_fields['Book']);
        $this->assertArrayNotHasKey('isbn', $gm2_graphql_fields['Book']);
    }

    public function test_auth_callback_is_respected(): void {
        global $gm2_graphql_fields;

        register_post_meta('book', 'restricted', [
            'single' => true,
            'type' => 'string',
            'show_in_rest' => [
                'schema' => [
                    'type' => 'string',
                ],
            ],
            'auth_callback' => function ($allowed, $metaKey, $postId, $userId) {
                if (!user_can($userId, 'manage_options')) {
                    return false;
                }

                return $allowed;
            },
        ]);

        do_action('graphql_register_types');

        $this->assertArrayHasKey('Book', $gm2_graphql_fields);
        $this->assertArrayHasKey('restricted', $gm2_graphql_fields['Book']);

        $postId = self::factory()->post->create([ 'post_type' => 'book' ]);
        update_post_meta($postId, 'restricted', 'secret');

        $resolver = $gm2_graphql_fields['Book']['restricted']['resolve'];

        $subscriber = self::factory()->user->create([ 'role' => 'subscriber' ]);
        wp_set_current_user($subscriber);
        $this->assertNull($resolver(get_post($postId), [], null, null));

        $admin = self::factory()->user->create([ 'role' => 'administrator' ]);
        wp_set_current_user($admin);
        $this->assertSame('secret', $resolver(get_post($postId), [], null, null));

        unregister_post_meta('book', 'restricted');
        wp_set_current_user(0);
    }
}
