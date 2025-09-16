<?php

class FieldComputedTest extends WP_UnitTestCase {
    public function test_arithmetic_formula() {
        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, 'a', 5 );
        update_post_meta( $post_id, 'b', 2 );

        $field = new GM2_Field_Computed( 'total', [ 'formula' => '({a} * {b}) + 1' ] );
        $field->save( $post_id, null );

        $this->assertSame( 11, get_post_meta( $post_id, 'total', true ) );

        ob_start();
        $field->render_admin( '', $post_id, 'post' );
        $out = ob_get_clean();
        $this->assertStringContainsString( '11', $out );
    }

    public function test_invalid_formula_is_rejected() {
        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, 'a', 5 );

        $field = new GM2_Field_Computed( 'total', [ 'formula' => '{a} + system("ls")' ] );
        $field->save( $post_id, null );

        $this->assertSame( '', get_post_meta( $post_id, 'total', true ) );
    }

    public function test_formula_cannot_execute_php_code() {
        self::$executed = false;

        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, 'a', 'FieldComputedTest::trigger()' );

        $field = new GM2_Field_Computed( 'total', [ 'formula' => '{a} + 1' ] );
        $field->save( $post_id, null );

        $this->assertSame( '', get_post_meta( $post_id, 'total', true ) );
        $this->assertFalse( self::$executed );
    }

    public static $executed = false;

    public static function trigger() {
        self::$executed = true;

        return 99;
    }
}

