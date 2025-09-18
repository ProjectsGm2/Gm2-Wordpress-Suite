<?php

class EditorialCommentsAjaxTest extends WP_UnitTestCase {
    private $post_id;

    protected function setUp(): void {
        parent::setUp();
        $this->post_id = self::factory()->post->create([
            'post_status' => 'draft',
        ]);
    }

    protected function tearDown(): void {
        $_POST    = [];
        $_GET     = [];
        $_REQUEST = [];
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_add_comment_requires_edit_capability() {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $nonce = wp_create_nonce('gm2_editorial_comment');
        $_POST = [
            'post_id'     => $this->post_id,
            'message'     => 'Unauthorized note',
            'context'     => 'meta-box',
            '_ajax_nonce' => $nonce,
        ];
        $_REQUEST = $_POST;

        try {
            gm2_ajax_add_editorial_comment();
            $this->fail('Expected WPDieException not thrown');
        } catch (\WPDieException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertIsArray($response);
            $this->assertFalse($response['success']);
            $this->assertSame(
                'You are not allowed to edit this post.',
                $response['data']['message']
            );
        }

        $comments = get_comments([
            'post_id' => $this->post_id,
            'type'    => 'gm2_editorial',
        ]);
        $this->assertCount(0, $comments);
    }

    public function test_add_comment_succeeds_for_authorized_user() {
        $user_id = self::factory()->user->create(['role' => 'editor']);
        wp_set_current_user($user_id);

        $nonce = wp_create_nonce('gm2_editorial_comment');
        $_POST = [
            'post_id'     => $this->post_id,
            'message'     => 'Authorized note',
            'context'     => 'meta-box',
            '_ajax_nonce' => $nonce,
        ];
        $_REQUEST = $_POST;

        try {
            gm2_ajax_add_editorial_comment();
            $this->fail('Expected WPDieException not thrown');
        } catch (\WPDieException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['success']);
            $this->assertCount(1, $response['data']);
            $this->assertSame('Authorized note', $response['data'][0]['content']);
        }

        $comments = get_comments([
            'post_id' => $this->post_id,
            'type'    => 'gm2_editorial',
        ]);
        $this->assertCount(1, $comments);
        $this->assertSame('Authorized note', $comments[0]->comment_content);
    }

    public function test_get_comments_requires_edit_capability() {
        $editor_id = self::factory()->user->create(['role' => 'editor']);
        wp_set_current_user($editor_id);
        $comment_id = wp_insert_comment([
            'comment_post_ID'      => $this->post_id,
            'comment_content'      => 'Hidden note',
            'user_id'              => $editor_id,
            'comment_type'         => 'gm2_editorial',
            'comment_approved'     => 1,
            'comment_author'       => 'Editor',
            'comment_author_email' => 'editor@example.com',
        ]);
        add_comment_meta($comment_id, 'gm2_context', 'meta-box');
        wp_set_current_user(0);

        $subscriber_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        $nonce = wp_create_nonce('gm2_editorial_comment');
        $_GET = [
            'post_id'     => $this->post_id,
            'context'     => 'meta-box',
            '_ajax_nonce' => $nonce,
        ];
        $_REQUEST = $_GET;

        try {
            gm2_ajax_get_editorial_comments();
            $this->fail('Expected WPDieException not thrown');
        } catch (\WPDieException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertFalse($response['success']);
            $this->assertSame(
                'You are not allowed to edit this post.',
                $response['data']['message']
            );
        }
    }

    public function test_get_comments_succeeds_for_authorized_user() {
        $editor_id = self::factory()->user->create(['role' => 'editor']);
        wp_set_current_user($editor_id);
        $comment_id = wp_insert_comment([
            'comment_post_ID'      => $this->post_id,
            'comment_content'      => 'Visible note',
            'user_id'              => $editor_id,
            'comment_type'         => 'gm2_editorial',
            'comment_approved'     => 1,
            'comment_author'       => 'Editor',
            'comment_author_email' => 'editor@example.com',
        ]);
        add_comment_meta($comment_id, 'gm2_context', 'meta-box');

        $nonce = wp_create_nonce('gm2_editorial_comment');
        $_GET = [
            'post_id'     => $this->post_id,
            'context'     => 'meta-box',
            '_ajax_nonce' => $nonce,
        ];
        $_REQUEST = $_GET;

        try {
            gm2_ajax_get_editorial_comments();
            $this->fail('Expected WPDieException not thrown');
        } catch (\WPDieException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['success']);
            $this->assertCount(1, $response['data']);
            $this->assertSame('Visible note', $response['data'][0]['content']);
        }
    }
}

