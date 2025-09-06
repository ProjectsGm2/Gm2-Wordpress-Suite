<?php
/**
 * Tests for gm2_apply_patch.
 */
class ApplyPatchTest extends WP_UnitTestCase {
    /**
     * Ensure a valid unified diff is applied.
     */
    public function test_apply_patch_success() {
        global $wp_filesystem;
        WP_Filesystem();
        $dir = GM2_PLUGIN_DIR . 'tests/tmp';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir . '/sample.txt';
        file_put_contents($file, "line1\nline2\nline3\n");
        $rel  = str_replace(GM2_PLUGIN_DIR, '', $file);
        $patch = "--- a/sample.txt\n+++ b/sample.txt\n@@ -1,3 +1,3 @@\n line1\n-line2\n+LINE2\n line3\n";
        $result = gm2_apply_patch($rel, $patch);
        $this->assertTrue($result);
        $this->assertStringEqualsFile($file, "line1\nLINE2\nline3\n");
    }

    /**
     * A patch that does not match should return WP_Error.
     */
    public function test_apply_patch_failure() {
        global $wp_filesystem;
        WP_Filesystem();
        $dir = GM2_PLUGIN_DIR . 'tests/tmp';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir . '/sample.txt';
        file_put_contents($file, "line1\nline2\nline3\n");
        $rel  = str_replace(GM2_PLUGIN_DIR, '', $file);
        $patch = "--- a/sample.txt\n+++ b/sample.txt\n@@ -1,3 +1,3 @@\n line1\n-lineX\n+LINE2\n line3\n";
        $result = gm2_apply_patch($rel, $patch);
        $this->assertInstanceOf('WP_Error', $result);
    }
}
