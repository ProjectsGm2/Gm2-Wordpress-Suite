<?php

use Gm2\Elementor\Forms\Action\CreateOrUpdatePost;

if (!class_exists('Elementor_Test_Form_Record')) {
    class Elementor_Test_Form_Record {
        private array $fields;
        private array $settings;
        private array $errors = [];

        public function __construct(array $fields, array $settings = []) {
            $this->fields   = $fields;
            $this->settings = $settings;
        }

        public function get($key) {
            if ('fields' === $key) {
                return $this->fields;
            }
            if ('files' === $key && isset($this->settings['_files'])) {
                return $this->settings['_files'];
            }
            return null;
        }

        public function get_form_settings($key = null) {
            if (null === $key) {
                return $this->settings;
            }
            return $this->settings[$key] ?? null;
        }

        public function add_error($field_id, $message): void {
            $this->errors[$field_id] = $message;
        }

        public function get_errors(): array {
            return $this->errors;
        }
    }
}

if (!class_exists('Elementor_Test_Ajax_Handler')) {
    class Elementor_Test_Ajax_Handler {
        public array $errors = [];
        public array $success = [];

        public function add_error_message($message): void {
            $this->errors[] = $message;
        }

        public function add_success_message($message): void {
            $this->success[] = $message;
        }
    }
}

/**
 * @covers \Gm2\Elementor\Forms\Action\CreateOrUpdatePost
 */
class ElementorGm2FormActionTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        register_post_type('book', [
            'public'   => true,
            'supports' => ['title', 'editor', 'excerpt'],
        ]);
        register_taxonomy('genre', 'book', [
            'public'      => true,
            'hierarchical'=> true,
        ]);
        register_taxonomy('audience', 'book', [
            'public' => true,
        ]);
    }

    public function tearDown(): void {
        unregister_post_type('book');
        unregister_taxonomy('genre');
        unregister_taxonomy('audience');
        $_FILES = [];
        parent::tearDown();
    }

    public function test_action_creates_post_with_meta(): void {
        $form_id = 'gm2_elementor_form';
        $nonce   = wp_create_nonce('gm2_cp_form|' . $form_id);

        $fields = [
            'gm2_cp_nonce' => [ 'id' => 'gm2_cp_nonce', 'value' => $nonce, 'raw_value' => $nonce, 'type' => 'hidden' ],
            'gm2_cp_hp'    => [ 'id' => 'gm2_cp_hp', 'value' => '', 'raw_value' => '', 'type' => 'hidden' ],
            'title'        => [ 'id' => 'title', 'value' => 'Library Book', 'raw_value' => 'Library Book', 'type' => 'text' ],
            'summary'      => [ 'id' => 'summary', 'value' => 'Summary content', 'raw_value' => 'Summary content', 'type' => 'textarea' ],
            'isbn'         => [ 'id' => 'isbn', 'value' => '9781234567897', 'raw_value' => '9781234567897', 'type' => 'text' ],
        ];

        $settings = [
            'gm2_cp_form_id'       => $form_id,
            'gm2_cp_post_type'     => 'book',
            'gm2_cp_title_field'   => 'title',
            'gm2_cp_content_field' => 'summary',
            'gm2_cp_nonce_field'   => 'gm2_cp_nonce',
            'gm2_cp_honeypot_field'=> 'gm2_cp_hp',
            'gm2_cp_post_status'   => 'pending',
            'gm2_cp_meta_map'      => [
                [ 'form_field' => 'isbn', 'meta_key' => 'isbn' ],
            ],
        ];

        $record = new Elementor_Test_Form_Record($fields, $settings);
        $ajax   = new Elementor_Test_Ajax_Handler();

        $action = new CreateOrUpdatePost();
        $action->run($record, $ajax);

        $posts = get_posts([
            'post_type'   => 'book',
            'post_status' => 'pending',
            'numberposts' => 1,
            'orderby'     => 'ID',
            'order'       => 'DESC',
        ]);
        $this->assertNotEmpty($posts, 'Post should be created.');
        $post = $posts[0];

        $this->assertSame('Library Book', $post->post_title);
        $this->assertSame('Summary content', $post->post_content);
        $this->assertSame('9781234567897', get_post_meta($post->ID, 'isbn', true));
    }

    public function test_action_assigns_taxonomy_terms_from_fields(): void {
        $form_id = 'gm2_elementor_tax_form';
        $nonce   = wp_create_nonce('gm2_cp_form|' . $form_id);

        $fields = [
            'gm2_cp_nonce'   => [ 'id' => 'gm2_cp_nonce', 'value' => $nonce, 'raw_value' => $nonce, 'type' => 'hidden' ],
            'gm2_cp_hp'      => [ 'id' => 'gm2_cp_hp', 'value' => '', 'raw_value' => '', 'type' => 'hidden' ],
            'title'          => [ 'id' => 'title', 'value' => 'Filed Story', 'raw_value' => 'Filed Story', 'type' => 'text' ],
            'genre_field'    => [ 'id' => 'genre_field', 'value' => 'Mystery', 'raw_value' => ' Mystery ', 'type' => 'text' ],
            'audience_field' => [ 'id' => 'audience_field', 'value' => ['Teachers', 'Students '], 'raw_value' => ['Teachers', 'Students '], 'type' => 'select' ],
        ];

        $settings = [
            'gm2_cp_form_id'        => $form_id,
            'gm2_cp_post_type'      => 'book',
            'gm2_cp_title_field'    => 'title',
            'gm2_cp_nonce_field'    => 'gm2_cp_nonce',
            'gm2_cp_honeypot_field' => 'gm2_cp_hp',
            'gm2_cp_post_status'    => 'publish',
            'gm2_cp_taxonomy_map'   => [
                [ 'form_field' => 'genre_field', 'taxonomy' => 'genre' ],
                [ 'form_field' => 'audience_field', 'taxonomy' => 'audience', 'allow_multiple' => 'yes' ],
            ],
        ];

        $record = new Elementor_Test_Form_Record($fields, $settings);
        $ajax   = new Elementor_Test_Ajax_Handler();

        $action = new CreateOrUpdatePost();
        $action->run($record, $ajax);

        $posts = get_posts([
            'post_type'   => 'book',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby'     => 'ID',
            'order'       => 'DESC',
        ]);
        $this->assertNotEmpty($posts, 'Post should be created with taxonomy terms.');
        $post = $posts[0];

        $genre_terms = wp_get_object_terms($post->ID, 'genre', [ 'fields' => 'slugs' ]);
        $this->assertSame(['mystery'], $genre_terms, 'Genre term should be assigned.');

        $audience_terms = wp_get_object_terms($post->ID, 'audience', [ 'fields' => 'slugs' ]);
        sort($audience_terms);
        $this->assertSame(['students', 'teachers'], $audience_terms, 'Audience terms should be assigned.');
        $this->assertEmpty($ajax->errors, 'No taxonomy errors expected.');
    }

    public function test_action_reports_taxonomy_validation_errors(): void {
        $form_id = 'gm2_elementor_tax_error_form';
        $nonce   = wp_create_nonce('gm2_cp_form|' . $form_id);

        $fields = [
            'gm2_cp_nonce'    => [ 'id' => 'gm2_cp_nonce', 'value' => $nonce, 'raw_value' => $nonce, 'type' => 'hidden' ],
            'gm2_cp_hp'       => [ 'id' => 'gm2_cp_hp', 'value' => '', 'raw_value' => '', 'type' => 'hidden' ],
            'title'           => [ 'id' => 'title', 'value' => 'Mismatched Story', 'raw_value' => 'Mismatched Story', 'type' => 'text' ],
            'category_field'  => [ 'id' => 'category_field', 'value' => 'news', 'raw_value' => 'news', 'type' => 'text' ],
        ];

        $settings = [
            'gm2_cp_form_id'        => $form_id,
            'gm2_cp_post_type'      => 'book',
            'gm2_cp_title_field'    => 'title',
            'gm2_cp_nonce_field'    => 'gm2_cp_nonce',
            'gm2_cp_honeypot_field' => 'gm2_cp_hp',
            'gm2_cp_taxonomy_map'   => [
                [ 'form_field' => 'category_field', 'taxonomy' => 'category' ],
            ],
        ];

        $record = new Elementor_Test_Form_Record($fields, $settings);
        $ajax   = new Elementor_Test_Ajax_Handler();

        $action = new CreateOrUpdatePost();
        $action->run($record, $ajax);

        $this->assertNotEmpty($ajax->errors, 'Taxonomy validation error should be reported.');
        $this->assertStringContainsString('Taxonomy "category" cannot be assigned to book posts.', $ajax->errors[0]);
        $this->assertArrayHasKey('gm2_cp_form_action', $record->get_errors());
        $this->assertEmpty($ajax->success, 'Submission should not report success.');
    }

    public function test_action_updates_post_on_target_site_with_upload(): void {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite not enabled.');
        }

        $site_id = self::factory()->blog->create();

        switch_to_blog($site_id);
        register_post_type('book', [
            'public'   => true,
            'supports' => ['title', 'editor', 'excerpt'],
        ]);
        $existing_id = wp_insert_post([
            'post_type'   => 'book',
            'post_status' => 'draft',
            'post_title'  => 'Original Title',
            'post_content'=> 'Original content',
        ]);
        restore_current_blog();

        $form_id = 'gm2_elementor_ms_form';
        $nonce   = wp_create_nonce('gm2_cp_form|' . $form_id);

        $image_path = wp_tempnam('gm2-elementor-upload.png');
        file_put_contents($image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII='));

        $_FILES = [
            'form_fields' => [
                'name'     => [ 'cover' => 'cover.png' ],
                'type'     => [ 'cover' => 'image/png' ],
                'tmp_name' => [ 'cover' => $image_path ],
                'error'    => [ 'cover' => UPLOAD_ERR_OK ],
                'size'     => [ 'cover' => filesize($image_path) ],
            ],
        ];

        $fields = [
            'gm2_cp_nonce' => [ 'id' => 'gm2_cp_nonce', 'value' => $nonce, 'raw_value' => $nonce, 'type' => 'hidden' ],
            'gm2_cp_hp'    => [ 'id' => 'gm2_cp_hp', 'value' => '', 'raw_value' => '', 'type' => 'hidden' ],
            'title'        => [ 'id' => 'title', 'value' => 'Updated Title', 'raw_value' => 'Updated Title', 'type' => 'text' ],
            'summary'      => [ 'id' => 'summary', 'value' => 'Updated body', 'raw_value' => 'Updated body', 'type' => 'textarea' ],
            'existing'     => [ 'id' => 'existing', 'value' => (string) $existing_id, 'raw_value' => (string) $existing_id, 'type' => 'hidden' ],
            'cover'        => [ 'id' => 'cover', 'value' => '', 'raw_value' => '', 'type' => 'upload' ],
        ];

        $settings = [
            'gm2_cp_form_id'       => $form_id,
            'gm2_cp_post_type'     => 'book',
            'gm2_cp_site_id'       => $site_id,
            'gm2_cp_post_id_field' => 'existing',
            'gm2_cp_title_field'   => 'title',
            'gm2_cp_content_field' => 'summary',
            'gm2_cp_nonce_field'   => 'gm2_cp_nonce',
            'gm2_cp_honeypot_field'=> 'gm2_cp_hp',
            'gm2_cp_post_status'   => 'draft',
            'gm2_cp_meta_map'      => [
                [ 'form_field' => 'cover', 'meta_key' => 'cover_image' ],
            ],
        ];

        $record = new Elementor_Test_Form_Record($fields, $settings);
        $ajax   = new Elementor_Test_Ajax_Handler();

        $action = new CreateOrUpdatePost();
        $action->run($record, $ajax);

        try {
            switch_to_blog($site_id);
            $post = get_post($existing_id);
            $this->assertSame('Updated Title', $post->post_title);
            $this->assertSame('Updated body', $post->post_content);

            $attachment_id = (int) get_post_meta($existing_id, 'cover_image', true);
            $this->assertGreaterThan(0, $attachment_id);
            $this->assertSame($existing_id, (int) get_post_field('post_parent', $attachment_id));
            $this->assertFileExists(get_attached_file($attachment_id));
        } finally {
            unregister_post_type('book');
            restore_current_blog();
            $_FILES = [];
            @unlink($image_path);
        }
    }
}
