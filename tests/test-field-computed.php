<?php

class FieldComputedTest extends WP_UnitTestCase {
    public function test_arithmetic_formula() {
        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, 'a', 5 );
        update_post_meta( $post_id, 'b', 2 );

        $field = new GM2_Field_Computed( 'total', [ 'formula' => '{a} * {b} + 1' ] );
        $field->save( $post_id, null );

        $this->assertSame( 11, get_post_meta( $post_id, 'total', true ) );

        ob_start();
        $field->render_admin( '', $post_id, 'post' );
        $out = ob_get_clean();
        $this->assertStringContainsString( '11', $out );
    }

    public function test_string_formula() {
        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, 'first', 'John' );
        update_post_meta( $post_id, 'last', 'Doe' );

        $field = new GM2_Field_Computed( 'full', [ 'formula' => "{first} . ' ' . {last}" ] );
        $field->save( $post_id, null );

        $this->assertSame( 'John Doe', get_post_meta( $post_id, 'full', true ) );
    }
}

