<?php
use Gm2\Gm2_CP_Form;

class CPFormTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        register_post_type('book', [
            'public'  => true,
            'supports'=> [ 'title', 'editor', 'excerpt' ],
        ]);

        update_option('gm2_field_groups', [
            'book_details' => [
                'scope'   => 'post_type',
                'objects' => [ 'book' ],
                'fields'  => [
                    'isbn' => [
                        'label'    => 'ISBN',
                        'type'     => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ]);

        Gm2_CP_Form::reset_results();
    }

    public function tearDown(): void {
        unregister_post_type('book');
        delete_option('gm2_field_groups');
        delete_option('gm2_custom_posts_config');
        $_POST  = [];
        $_FILES = [];
        parent::tearDown();
    }

    public function test_submission_creates_post_and_meta(): void {
        $form_id = 'gm2_cp_form_book';
        $_POST   = [
            'gm2_cp_form_id'   => $form_id,
            'gm2_cp_post_type' => 'book',
            'gm2_cp_nonce'     => wp_create_nonce('gm2_cp_form|' . $form_id),
            'gm2_cp_hp'        => '',
            'post_title'       => 'Example Book',
            'post_content'     => 'Description',
            'isbn'             => '9781234567890',
        ];

        Gm2_CP_Form::maybe_handle_submission();
        $result = Gm2_CP_Form::get_last_result($form_id);

        $this->assertNotNull($result, 'Submission result should be stored.');
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['post_id']);

        $post = get_post($result['post_id']);
        $this->assertSame('example-book', $post->post_name);
        $this->assertSame('9781234567890', get_post_meta($post->ID, 'isbn', true));
    }

    public function test_login_required_prevents_submission(): void {
        update_option('gm2_custom_posts_config', [
            'post_types' => [
                'book' => [
                    'submission' => [ 'require_login' => true ],
                ],
            ],
        ]);

        $form_id = 'gm2_cp_form_book';
        $_POST   = [
            'gm2_cp_form_id'   => $form_id,
            'gm2_cp_post_type' => 'book',
            'gm2_cp_nonce'     => wp_create_nonce('gm2_cp_form|' . $form_id),
            'gm2_cp_hp'        => '',
            'post_title'       => 'Should Fail',
            'isbn'             => '12345',
        ];

        wp_set_current_user(0);
        Gm2_CP_Form::maybe_handle_submission();
        $result = Gm2_CP_Form::get_last_result($form_id);

        $this->assertNotNull($result);
        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['post_id']);
    }

    public function test_under_review_status_filter_applied(): void {
        add_filter('gm2_cp_form_under_review_status', static function ($status, $post_type) {
            if ('book' === $post_type) {
                return 'draft';
            }
            return $status;
        }, 10, 2);

        $form_id = 'gm2_cp_form_book';
        $_POST   = [
            'gm2_cp_form_id'   => $form_id,
            'gm2_cp_post_type' => 'book',
            'gm2_cp_nonce'     => wp_create_nonce('gm2_cp_form|' . $form_id),
            'gm2_cp_hp'        => '',
            'post_title'       => 'Filtered Status',
            'isbn'             => '999',
        ];

        Gm2_CP_Form::maybe_handle_submission();
        $result = Gm2_CP_Form::get_last_result($form_id);
        $this->assertTrue($result['success']);

        $post = get_post($result['post_id']);
        $this->assertSame('draft', $post->post_status);

        remove_all_filters('gm2_cp_form_under_review_status');
    }
}
