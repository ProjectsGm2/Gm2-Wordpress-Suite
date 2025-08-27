<?php
use Gm2\AI\LocalGemmaProvider as Gm2_LocalGemma;
use function Gm2\gm2_ai_send_prompt;

class GemmaLocalTest extends WP_UnitTestCase {
    private function createTestBinary(string $output = 'hi'): string {
        $bin = tempnam(sys_get_temp_dir(), 'gemma-bin');
        file_put_contents($bin, "#!/bin/sh\necho $output\n");
        chmod($bin, 0755);
        return $bin;
    }

    private function createModelFile(): string {
        $model = tempnam(sys_get_temp_dir(), 'gemma-model');
        file_put_contents($model, 'model');
        return $model;
    }

    public function test_query_returns_response() {
        $binary = $this->createTestBinary('hi');
        $model  = $this->createModelFile();
        $gemma  = new Gm2_LocalGemma();
        $res    = $gemma->query('hello', ['binary' => $binary, 'model' => $model]);
        unlink($binary);
        unlink($model);
        $this->assertSame('hi', $res);
    }

    public function test_error_when_binary_missing() {
        $model = $this->createModelFile();
        $gemma = new Gm2_LocalGemma();
        $res   = $gemma->query('hello', ['binary' => '/no/bin', 'model' => $model]);
        unlink($model);
        $this->assertInstanceOf('WP_Error', $res);
        $this->assertSame('binary_not_found', $res->get_error_code());
    }

    public function test_error_when_model_missing() {
        $binary = $this->createTestBinary('hi');
        $gemma  = new Gm2_LocalGemma();
        $res    = $gemma->query('hello', ['binary' => $binary, 'model' => '/no/model']);
        unlink($binary);
        $this->assertInstanceOf('WP_Error', $res);
        $this->assertSame('model_not_found', $res->get_error_code());
    }

    public function test_gemma_local_integration_with_ai_send_prompt() {
        $binary = $this->createTestBinary('hi');
        $model  = $this->createModelFile();
        $prev   = get_option('gm2_ai_provider');
        update_option('gm2_ai_provider', 'gemma_local');
        update_option('gm2_gemma_binary_path', $binary);
        update_option('gm2_gemma_model_path', $model);
        $res = gm2_ai_send_prompt('hello');
        update_option('gm2_ai_provider', $prev);
        unlink($binary);
        unlink($model);
        $this->assertSame('hi', $res);
    }
}
