<?php

class FieldGradientIconBadgeRatingTest extends WP_UnitTestCase {
    public function test_gradient_sanitization() {
        $field = new GM2_Field_Gradient('grad');
        $out = $field->sanitize(array('start' => '#ff0000', 'end' => '#00ff00'));
        $this->assertSame(array('start' => '#ff0000', 'end' => '#00ff00'), $out);
        $out = $field->sanitize(array('start' => 'bad', 'end' => '#00ff00'));
        $this->assertSame(array('start' => '', 'end' => '#00ff00'), $out);
    }

    public function test_icon_sanitization() {
        $field = new GM2_Field_Icon('icon');
        $this->assertSame('dashicons-admin-site', $field->sanitize('dashicons-admin-site'));
        $this->assertSame('script', $field->sanitize('<script>'));
    }

    public function test_badge_sanitization() {
        $field = new GM2_Field_Badge('badge');
        $out = $field->sanitize(array('text' => 'Sale', 'color' => '#123456'));
        $this->assertSame(array('text' => 'Sale', 'color' => '#123456'), $out);
        $out = $field->sanitize(array('text' => '<b>Sale</b>', 'color' => 'bad'));
        $this->assertSame(array('text' => 'Sale', 'color' => ''), $out);
    }

    public function test_rating_sanitization() {
        $field = new GM2_Field_Rating('rating');
        $this->assertSame(3, $field->sanitize(3));
        $this->assertSame(5, $field->sanitize(10));
        $this->assertSame(0, $field->sanitize(-2));
    }
}
