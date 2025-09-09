<?php
use Gm2\Font_Performance\Font_Performance;

class FontCacheHeadersTest extends WP_UnitTestCase {
    public function test_rest_endpoint_sends_cache_headers() {
        // Create a dummy font file within the WordPress installation.
        $relative = 'wp-content/fonts/test-font.woff2';
        $absolute = ABSPATH . $relative;
        if (!file_exists(dirname($absolute))) {
            wp_mkdir_p(dirname($absolute));
        }
        file_put_contents($absolute, 'DUMMY');

        // Prepare temporary files for the helper script and header output.
        $script      = tempnam(sys_get_temp_dir(), 'font-script-');
        $headersFile = tempnam(sys_get_temp_dir(), 'font-headers-');

        $bootstrap = addslashes(dirname(__DIR__) . '/tests/bootstrap.php');
        $code = <<< 'EOS'
<?php
require '$bootstrap';
use Gm2\Font_Performance\Font_Performance;
Font_Performance::register_font_route();
$headersFile = $argv[1];
$fontFile    = $argv[2];
register_shutdown_function(function() use ($headersFile) {
    file_put_contents($headersFile, implode("\n", headers_list()));
});
$req = new WP_REST_Request('GET', '/gm2seo/v1/font');
$req->set_param('file', $fontFile);
rest_get_server()->dispatch($req);
EOS;
        file_put_contents($script, $code);

        // Execute the helper script in a separate PHP process.
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($headersFile) . ' ' . escapeshellarg($relative);
        exec($cmd, $output, $ret);
        $this->assertSame(0, $ret, 'Helper script failed to run');

        $headers = file_get_contents($headersFile);
        $this->assertStringContainsString('Cache-Control: public, max-age=31536000, immutable', $headers);
        $this->assertStringContainsString('Cross-Origin-Resource-Policy: cross-origin', $headers);

        // Cleanup temporary files and dummy font.
        unlink($script);
        unlink($headersFile);
        unlink($absolute);
    }
}
