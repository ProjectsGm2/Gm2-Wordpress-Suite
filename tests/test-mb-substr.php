<?php
class MbSubstrTest extends WP_UnitTestCase {
    public function test_multibyte_substring() {
        $str = 'こんにちは世界';
        $this->assertSame('こんにちは', gm2_substr($str, 0, 5));
    }
}
