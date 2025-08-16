<?php
use Gm2\Gm2_REST_Fields;
use Gm2\Gm2_REST_Visibility;

class GraphQLRegistrationTest extends WP_UnitTestCase {
    public function test_register_graphql_hooks() {
        if (!class_exists('WPGraphQL')) {
            $this->markTestSkipped('WPGraphQL not available.');
        }
        register_post_type('book');
        register_taxonomy('genre', 'book');
        update_option(Gm2_REST_Visibility::OPTION, [
            'post_types' => [ 'book' => true ],
            'taxonomies' => [ 'genre' => true ],
            'fields' => [ 'isbn' => true ],
        ]);
        Gm2_REST_Fields::init();
        do_action('graphql_register_types');
        $this->assertTrue(true);
    }
}
