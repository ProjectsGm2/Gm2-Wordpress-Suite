<?php
declare(strict_types=1);

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}

if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static array $commands = [];

        public static function add_command($name, $callable): void
        {
            self::$commands[$name] = $callable;
        }

        public static function line($message): void
        {
            echo $message, "\n";
        }

        public static function success($message): void
        {
            echo $message, "\n";
        }

        public static function warning($message): void
        {
            echo $message, "\n";
        }

        public static function error($message): void
        {
            throw new \RuntimeException($message);
        }

        public static function runcommand(string $command): void
        {
            $parts = preg_split('/\s+/', trim($command));
            if ($parts === false || $parts === []) {
                throw new \RuntimeException('Empty command.');
            }

            $matchedCallable = null;
            $matchedKey      = '';
            $consumed        = 0;
            $current         = '';

            foreach ($parts as $index => $part) {
                $current = $current === '' ? $part : $current . ' ' . $part;
                if (isset(self::$commands[$current])) {
                    $matchedCallable = self::$commands[$current];
                    $matchedKey      = $current;
                    $consumed        = $index + 1;
                }
            }

            if ($matchedCallable === null) {
                throw new \RuntimeException(sprintf('Command %s not registered.', $parts[0] ?? ''));
            }

            $instance  = is_string($matchedCallable) ? new $matchedCallable() : $matchedCallable;
            $remaining = array_slice($parts, $consumed);
            $sub       = '';

            if ($remaining !== []) {
                $sub = array_shift($remaining);
            }

            $method = str_replace('-', '_', $sub);
            if ($method === 'list') {
                $method = 'list_';
            }
            if ($method === '') {
                $method = '__invoke';
            }

            $args  = [];
            $assoc = [];
            foreach ($remaining as $part) {
                if (str_starts_with($part, '--')) {
                    $arg = substr($part, 2);
                    if ($arg === '') {
                        continue;
                    }
                    if (str_contains($arg, '=')) {
                        [$key, $value] = explode('=', $arg, 2);
                        $assoc[$key]   = $value;
                    } else {
                        $assoc[$arg] = true;
                    }
                } else {
                    $args[] = $part;
                }
            }

            if (!method_exists($instance, $method)) {
                throw new \RuntimeException(sprintf('Subcommand %s not found for %s.', $sub, $matchedKey));
            }

            $instance->$method($args, $assoc);
        }
    }

    class WP_CLI_Command {}
}

require_once GM2_PLUGIN_DIR . 'includes/cli/class-gm2-presets-cli.php';

final class PresetsCliTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option('gm2_custom_posts_config');
        delete_option('gm2_field_groups');
        delete_option('gm2_cp_schema_map');
    }

    protected function tearDown(): void
    {
        delete_option('gm2_custom_posts_config');
        delete_option('gm2_field_groups');
        delete_option('gm2_cp_schema_map');
        parent::tearDown();
    }

    public function test_list_outputs_available_presets(): void
    {
        ob_start();
        \WP_CLI::runcommand('gm2 presets list');
        $output = ob_get_clean();

        $this->assertStringContainsString('directory', $output);
        $this->assertStringContainsString('Business Directory', $output);
        $this->assertStringContainsString('Local business listings', $output);
    }

    public function test_apply_imports_blueprint(): void
    {
        ob_start();
        \WP_CLI::runcommand('gm2 presets apply directory');
        $output = ob_get_clean();

        $this->assertStringContainsString('applied', $output);

        $config = get_option('gm2_custom_posts_config');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('post_types', $config);
        $this->assertArrayHasKey('listing', $config['post_types']);

        $fieldGroups = get_option('gm2_field_groups');
        $this->assertNotEmpty($fieldGroups);
    }

    public function test_apply_requires_force_when_definitions_exist(): void
    {
        update_option('gm2_custom_posts_config', [ 'post_types' => [ 'existing' => [] ] ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Existing content definitions');

        \WP_CLI::runcommand('gm2 presets apply directory');
    }

    public function test_apply_with_force_overwrites_existing(): void
    {
        update_option('gm2_custom_posts_config', [ 'post_types' => [ 'existing' => [] ] ]);

        ob_start();
        \WP_CLI::runcommand('gm2 presets apply directory --force');
        ob_end_clean();

        $config = get_option('gm2_custom_posts_config');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('post_types', $config);
        $this->assertArrayHasKey('listing', $config['post_types']);
        $this->assertArrayNotHasKey('existing', $config['post_types']);
    }

    public function test_apply_unknown_preset_throws_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Preset');

        \WP_CLI::runcommand('gm2 presets apply missing');
    }
}
