<?php
class UniqueScopeValidationTest extends WP_UnitTestCase {
    public function test_unique_user_meta() {
        $field = [ 'unique' => true, 'unique_scope' => 'user' ];
        $user1 = self::factory()->user->create();
        update_user_meta($user1, 'uniq_key', 'dup');
        $user2 = self::factory()->user->create();
        $this->assertTrue( gm2_validate_field('uniq_key', $field, 'unique', $user2, 'user') );
        $res = gm2_validate_field('uniq_key', $field, 'dup', $user2, 'user');
        $this->assertInstanceOf( WP_Error::class, $res );
    }

    public function test_unique_term_meta() {
        $field = [ 'unique' => true, 'unique_scope' => 'term' ];
        $term1 = self::factory()->term->create([ 'taxonomy' => 'category' ]);
        update_term_meta($term1, 'uniq_key', 'dup');
        $term2 = self::factory()->term->create([ 'taxonomy' => 'category' ]);
        $this->assertTrue( gm2_validate_field('uniq_key', $field, 'unique', $term2, 'term') );
        $res = gm2_validate_field('uniq_key', $field, 'dup', $term2, 'term');
        $this->assertInstanceOf( WP_Error::class, $res );
    }

    public function test_unique_comment_meta() {
        $field = [ 'unique' => true, 'unique_scope' => 'comment' ];
        $post_id = self::factory()->post->create();
        $comment1 = self::factory()->comment->create([ 'comment_post_ID' => $post_id ]);
        update_comment_meta($comment1, 'uniq_key', 'dup');
        $comment2 = self::factory()->comment->create([ 'comment_post_ID' => $post_id ]);
        $this->assertTrue( gm2_validate_field('uniq_key', $field, 'unique', $comment2, 'comment') );
        $res = gm2_validate_field('uniq_key', $field, 'dup', $comment2, 'comment');
        $this->assertInstanceOf( WP_Error::class, $res );
    }

    public function test_unique_option_value() {
        $field = [ 'unique' => true, 'unique_scope' => 'option' ];
        update_option('existing_opt', 'dup');
        $this->assertTrue( gm2_validate_field('new_opt', $field, 'unique', 'new_opt', 'option') );
        $res = gm2_validate_field('new_opt', $field, 'dup', 'new_opt', 'option');
        $this->assertInstanceOf( WP_Error::class, $res );
    }
}
