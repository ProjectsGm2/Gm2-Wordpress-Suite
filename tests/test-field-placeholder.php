<?php

class FieldPlaceholderTest extends WP_UnitTestCase {
    public function test_text_field_placeholder() {
        $field = new GM2_Field_Text( 'text_field', [ 'placeholder' => 'Enter text' ] );
        ob_start();
        $field->render_admin( '', 0, 'post' );
        $out = ob_get_clean();
        $this->assertStringContainsString( 'placeholder="Enter text"', $out );
    }

    public function test_number_field_placeholder() {
        $field = new GM2_Field_Number( 'number_field', [ 'placeholder' => 'Enter number' ] );
        ob_start();
        $field->render_admin( '', 0, 'post' );
        $out = ob_get_clean();
        $this->assertStringContainsString( 'placeholder="Enter number"', $out );
    }

    public function test_textarea_field_placeholder() {
        $field = new GM2_Field_Textarea( 'textarea_field', [ 'placeholder' => 'Enter more text' ] );
        ob_start();
        $field->render_admin( '', 0, 'post' );
        $out = ob_get_clean();
        $this->assertStringContainsString( 'placeholder="Enter more text"', $out );
    }
}
