<?php
use Gm2\Font_Performance\Font_Performance;

class FontCacheHeadersTest extends WP_UnitTestCase {
    private string $relative;
    private string $absolute;
    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $ref = new ReflectionClass(Font_Performance::class);
        foreach (['options' => [], 'hooks_added' => false] as $prop => $value) {
            $property = $ref->getProperty($prop);
            $property->setAccessible(true);
            $property->setValue($value);
        }

        $uploads = wp_upload_dir();
        $dir     = trailingslashit($uploads['basedir']) . 'gm2seo-fonts/test/';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $this->relative = 'test/test-font.woff2';
        $this->absolute = $dir . 'test-font.woff2';
        file_put_contents($this->absolute, 'DUMMY');

        update_option('gm2seo_fonts', [
            'enabled'       => true,
            'cache_headers' => true,
        ]);

        Font_Performance::bootstrap();
        Font_Performance::register_font_route();

        $source   = trailingslashit($uploads['baseurl']) . 'gm2seo-fonts/' . $this->relative;
        $endpoint = Font_Performance::rewrite_font_src($source);
        $parts    = wp_parse_url($endpoint);
        $params   = [];
        parse_str($parts['query'] ?? '', $params);
        $requested = (string) ($params['file'] ?? '');
        $this->token = (string) ($params['token'] ?? '');

        if ($requested !== '') {
            $this->relative = $requested;
        }

        if ($this->token === '') {
            $this->fail('Failed to generate font token.');
        }
    }

    protected function tearDown(): void {
        if (file_exists($this->absolute)) {
            unlink($this->absolute);
        }

        $dir = dirname($this->absolute);
        if (is_dir($dir)) {
            rmdir($dir);
        }

        $parent = dirname($dir);
        if (is_dir($parent)) {
            $entries = array_diff(scandir($parent) ?: [], ['.', '..']);
            if (empty($entries)) {
                rmdir($parent);
            }
        }

        parent::tearDown();
    }

    public function test_rest_endpoint_sends_cache_headers(): void {
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
$token       = $argv[3];
register_shutdown_function(function() use ($headersFile) {
    file_put_contents($headersFile, implode("\n", headers_list()));
});
$req = new WP_REST_Request('GET', '/gm2seo/v1/font');
$req->set_param('file', $fontFile);
$req->set_param('token', $token);
rest_get_server()->dispatch($req);
EOS;
        file_put_contents($script, $code);

        $cmd = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($script) . ' '
            . escapeshellarg($headersFile) . ' '
            . escapeshellarg($this->relative) . ' '
            . escapeshellarg($this->token);
        exec($cmd, $output, $ret);
        $this->assertSame(0, $ret, 'Helper script failed to run');

        $headers = file_get_contents($headersFile);
        $this->assertStringContainsString('Cache-Control: public, max-age=31536000, immutable', $headers);
        $this->assertStringContainsString('Cross-Origin-Resource-Policy: cross-origin', $headers);

        unlink($script);
        unlink($headersFile);
    }

    public function test_request_without_token_is_rejected(): void {
        $req = new WP_REST_Request('GET', '/gm2seo/v1/font');
        $req->set_param('file', $this->relative);

        $response = rest_get_server()->dispatch($req);
        $this->assertInstanceOf(\WP_Error::class, $response);
        $data = $response->get_error_data('gm2_font_unauthorized');
        $this->assertIsArray($data);
        $this->assertSame(403, $data['status']);
    }
}
