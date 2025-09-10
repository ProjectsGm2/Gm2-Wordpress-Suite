<?php
use Gm2\NetworkPayload\Compression;
use org\bovigo\vfs\vfsStream;

if (!function_exists('apache_get_modules')) {
    function apache_get_modules() {
        return ['mod_deflate', 'mod_brotli'];
    }
}

class HtaccessWriterTest extends WP_UnitTestCase {
    private string $file;

    public function setUp(): void {
        parent::setUp();
        vfsStream::setup('root', 0777, ['.htaccess' => "Original\n"]);
        $this->file = vfsStream::url('root/.htaccess');
        add_filter('gm2_compression_htaccess_path', [$this, 'path']);
    }

    public function tearDown(): void {
        remove_filter('gm2_compression_htaccess_path', [$this, 'path']);
        parent::tearDown();
    }

    public function path($path) {
        return $this->file;
    }

    public function test_enable_twice_and_revert(): void {
        $original = file_get_contents($this->file);
        Compression::enable_apache_compression();
        $first = file_get_contents($this->file);
        Compression::enable_apache_compression();
        $second = file_get_contents($this->file);
        $this->assertSame($first, $second);
        $this->assertEquals(1, substr_count($second, '# BEGIN GM2_COMPRESSION'));
        $this->assertEquals(1, substr_count($second, '# END GM2_COMPRESSION'));
        $this->assertStringContainsString('mod_deflate.c', $second);
        $this->assertStringContainsString('mod_brotli.c', $second);
        Compression::revert_apache_compression();
        $final = file_get_contents($this->file);
        $this->assertSame($original, $final);
    }
}
