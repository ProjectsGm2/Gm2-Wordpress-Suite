<?php

declare(strict_types=1);

use Gm2\Perf\Settings;
use Gm2\Performance\QueryCacheManager;

class PerformanceSettingsDataTest extends WP_UnitTestCase
{
    /**
     * @var array<int, string>
     */
    private array $trackedOptions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->trackedOptions = array_merge(
            array_keys(Settings::get_flag_options()),
            array_keys(Settings::get_cache_options())
        );
        foreach ($this->trackedOptions as $option) {
            delete_option($option);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->trackedOptions as $option) {
            delete_option($option);
        }
        parent::tearDown();
    }

    public function test_register_adds_cache_options_with_defaults(): void
    {
        global $wpdb;

        Settings::register();

        $this->assertSame('1', get_option(QueryCacheManager::OPTION_ENABLED));
        $this->assertSame('0', get_option(QueryCacheManager::OPTION_FORCE_TRANSIENTS));

        $autoload = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
                QueryCacheManager::OPTION_ENABLED
            )
        );
        $this->assertSame('yes', $autoload);
    }

    public function test_get_flag_options_returns_expected_keys(): void
    {
        $flags = Settings::get_flag_options();

        $this->assertArrayHasKey('ae_perf_webworker', $flags);
        $this->assertArrayHasKey('ae_perf_passive_patch', $flags);
        $this->assertArrayHasKey('ae_perf_domaudit', $flags);
    }

    public function test_get_cache_options_defines_defaults_and_labels(): void
    {
        $options = Settings::get_cache_options();

        $this->assertArrayHasKey(QueryCacheManager::OPTION_ENABLED, $options);
        $this->assertArrayHasKey(QueryCacheManager::OPTION_FORCE_TRANSIENTS, $options);
        $this->assertSame('1', $options[QueryCacheManager::OPTION_ENABLED]['default']);
        $this->assertSame('0', $options[QueryCacheManager::OPTION_FORCE_TRANSIENTS]['default']);
        $this->assertNotEmpty($options[QueryCacheManager::OPTION_ENABLED]['description']);
    }
}
