<?php
use Gm2\Gm2_SEO_Admin;
use Gm2\Gm2_REST_Fields;
use Gm2\Gm2_REST_Visibility;

class TaxonomyPermissionsTest extends WP_UnitTestCase {
    public function test_save_taxonomy_meta_without_permission_does_nothing() {
        $term_id = self::factory()->term->create(['taxonomy' => 'category']);
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $admin = new Gm2_SEO_Admin();

        $_POST['gm2_seo_nonce'] = wp_create_nonce('gm2_save_seo_meta');
        $_POST['gm2_seo_title'] = 'Title';

        $admin->save_taxonomy_meta($term_id);

        wp_set_current_user(0);
        $_POST = [];

        $this->assertFalse(metadata_exists('term', $term_id, '_gm2_title'));
    }
}

class TaxonomyContentRulesTest extends WP_Ajax_UnitTestCase {
    private function run_check($content) {
        $this->_setRole('administrator');
        $_POST['taxonomy'] = 'category';
        $_POST['content'] = $content;
        $_POST['title'] = 'Test';
        $_POST['description'] = 'Desc';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_check_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_check_rules'); } catch (WPAjaxDieContinueException $e) {}
        return json_decode($this->_last_response, true);
    }

    public function test_description_word_count_rule() {
        update_option('gm2_content_rules', ['tax_category' => ['content' => 'Description has at least 150 words']]);
        $resp = $this->run_check(str_repeat('word ', 160));
        $this->assertTrue($resp['success']);
        $this->assertTrue($resp['data']['description-has-at-least-150-words']);

        $resp = $this->run_check(str_repeat('word ', 10));
        $this->assertFalse($resp['data']['description-has-at-least-150-words']);
    }
}

class FieldCapabilityPermissionsTest extends WP_UnitTestCase {
    public function tearDown(): void {
        parent::tearDown();
        delete_option('gm2_field_caps');
    }

    public function test_role_based_field_capability() {
        update_option('gm2_field_caps', ['foo' => ['edit' => ['administrator']]]);
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        $this->assertTrue(user_can($admin, 'gm2_field_edit_foo'));
        $this->assertFalse(user_can($subscriber, 'gm2_field_edit_foo'));
    }

    public function test_capability_based_field_capability() {
        update_option('gm2_field_caps', ['bar' => ['edit' => ['edit_posts']]]);
        $editor = self::factory()->user->create(['role' => 'editor']);
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        $this->assertTrue(user_can($editor, 'gm2_field_edit_bar'));
        $this->assertFalse(user_can($subscriber, 'gm2_field_edit_bar'));
    }

    public function test_render_field_group_respects_read_capability() {
        update_option('gm2_field_caps', ['secret' => ['read' => ['administrator']]]);
        $post_id = self::factory()->post->create();
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber);
        ob_start();
        gm2_render_field_group(['secret' => ['type' => 'text']], $post_id, 'post');
        $output = trim(ob_get_clean());
        wp_set_current_user(0);
        $this->assertSame('', $output);
    }

    public function test_save_field_group_respects_edit_capability() {
        update_option('gm2_field_caps', ['secret' => ['edit' => ['administrator']]]);
        $post_id = self::factory()->post->create();
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber);
        $_POST['secret'] = 'value';
        gm2_save_field_group(['secret' => ['type' => 'text']], $post_id, 'post');
        wp_set_current_user(0);
        $_POST = [];
        $this->assertFalse(metadata_exists('post', $post_id, 'secret'));
    }

    public function test_rest_and_graphql_respect_field_capabilities() {
        register_post_type('book');
        update_option('gm2_custom_posts_config', [
            'post_types' => [
                'book' => [
                    'label' => 'Book',
                    'fields' => [ 'secret' => [ 'type' => 'text' ] ],
                ],
            ],
            'taxonomies' => [],
        ]);
        update_option(Gm2_REST_Visibility::OPTION, [
            'post_types' => [ 'book' => true ],
            'taxonomies' => [],
            'fields' => [ 'secret' => true ],
        ]);
        update_option('gm2_field_caps', ['secret' => ['read' => ['administrator']]]);
        Gm2_REST_Fields::init();
        do_action('rest_api_init');
        if (class_exists('WPGraphQL')) {
            do_action('graphql_register_types');
        }

        $post_id = self::factory()->post->create(['post_type' => 'book']);
        update_post_meta($post_id, 'secret', 'top');
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber);

        $request = new WP_REST_Request('GET', '/gm2/v1/fields/' . $post_id);
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        $this->assertArrayNotHasKey('secret', $data);

        if (class_exists('WPGraphQL')) {
            $query = 'query ($id:ID!){ nodeById(id:$id){ ... on Book { secret } } }';
            if (function_exists('graphql')) {
                $res = graphql(['query' => $query, 'variables' => ['id' => $post_id]]);
            } elseif (function_exists('do_graphql_request')) {
                $res = do_graphql_request($query, ['id' => $post_id]);
            } else {
                $res = null;
            }
            if ($res) {
                $this->assertNull($res['data']['nodeById']['secret']);
            }
        }

        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);

        $request = new WP_REST_Request('GET', '/gm2/v1/fields/' . $post_id);
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        $this->assertSame('top', $data['secret']);

        if (class_exists('WPGraphQL')) {
            $query = 'query ($id:ID!){ nodeById(id:$id){ ... on Book { secret } } }';
            if (function_exists('graphql')) {
                $res = graphql(['query' => $query, 'variables' => ['id' => $post_id]]);
            } elseif (function_exists('do_graphql_request')) {
                $res = do_graphql_request($query, ['id' => $post_id]);
            } else {
                $res = null;
            }
            if ($res) {
                $this->assertSame('top', $res['data']['nodeById']['secret']);
            }
        }

        unregister_post_type('book');
        delete_option('gm2_custom_posts_config');
        delete_option(Gm2_REST_Visibility::OPTION);
        wp_set_current_user(0);
    }
}

