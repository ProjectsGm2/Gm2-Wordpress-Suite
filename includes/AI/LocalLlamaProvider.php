<?php
namespace Gm2\AI;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class LocalLlamaProvider implements ProviderInterface {
    private string $binary;

    public function __construct() {
        $this->binary = get_option('gm2_local_llama_binary', '/usr/local/bin/llama');
    }

    public function query(string $prompt, array $args = []): string|WP_Error {
        $binary = $args['binary'] ?? $this->binary;
        $model  = $args['model'] ?? $args['model_path'] ?? '';
        if ($binary === '' || !file_exists($binary)) {
            return new WP_Error('binary_not_found', 'Llama binary not found');
        }
        if ($model === '' || !file_exists($model)) {
            return new WP_Error('model_not_found', 'Model file not found');
        }
        $temperature = isset($args['temperature']) ? floatval($args['temperature']) : 1.0;
        $max_tokens  = isset($args['max_tokens']) ? intval($args['max_tokens']) : (isset($args['number-of-words']) ? intval($args['number-of-words']) : 0);
        $command = escapeshellcmd($binary)
            . ' -m ' . escapeshellarg($model)
            . ' -p ' . escapeshellarg($prompt)
            . ' --temp ' . escapeshellarg((string)$temperature);
        if ($max_tokens > 0) {
            $command .= ' -n ' . intval($max_tokens);
        }
        $output = shell_exec($command);
        if ($output === null) {
            return new WP_Error('execution_failed', 'Failed to execute Llama binary');
        }
        return trim($output);
    }
}
