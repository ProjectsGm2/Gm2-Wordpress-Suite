<?php
use Gm2\Gm2_Cache_Audit;

class CacheFixHandlesTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        delete_option('gm2_cache_audit_results');
        delete_option('gm2_script_attributes');
        parent::tearDown();
    }

    public function test_apply_fix_preserves_hyphenated_handle() {
        $asset = [
            'url'    => 'https://cdn.example.com/foo.js',
            'type'   => 'script',
            'handle' => 'foo-bar',
            'issues' => [],
        ];
        update_option('gm2_cache_audit_results', [ 'assets' => [ $asset ] ]);

        $result = Gm2_Cache_Audit::apply_fix($asset);
        $this->assertNotInstanceOf(\WP_Error::class, $result);

        $attrs = get_option('gm2_script_attributes', []);
        $this->assertSame('defer', $attrs['foo-bar'] ?? null);
    }
}
