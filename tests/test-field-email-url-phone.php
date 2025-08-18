<?php

class FieldEmailUrlPhoneTest extends WP_UnitTestCase {
    public function test_email_sanitization() {
        $field = new GM2_Field_Email('email');
        $this->assertSame('user@example.com', $field->sanitize('user@example.com'));
        $this->assertSame('', $field->sanitize('invalid-email'));
    }

    public function test_url_sanitization() {
        $field = new GM2_Field_Url('url');
        $this->assertSame('https://example.com', $field->sanitize('https://example.com'));
        $this->assertSame('', $field->sanitize('notaurl'));
    }

    public function test_phone_sanitization() {
        $field = new GM2_Field_Phone('phone');
        $this->assertSame('123-456-7890', $field->sanitize('123-456-7890'));
        $this->assertSame('', $field->sanitize('abc123'));
    }
}
