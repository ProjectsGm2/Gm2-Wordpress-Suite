<?php

declare(strict_types=1);

use Gm2\Performance\AutoloadInspector;

class AutoloadInspectorTest extends WP_UnitTestCase
{
    /**
     * @var array<int, string>
     */
    private array $trackedOptions = [];

    protected function tearDown(): void
    {
        foreach ($this->trackedOptions as $option) {
            delete_option($option);
        }
        parent::tearDown();
    }

    public function test_get_totals_reflects_new_rows(): void
    {
        $before = AutoloadInspector::get_totals();

        $autoloadOption = 'gm2_autoload_yes_test';
        $nonAutoloadOption = 'gm2_autoload_no_test';

        $this->trackOption($autoloadOption);
        $this->trackOption($nonAutoloadOption);

        $autoloadPayload    = str_repeat('a', 10);
        $nonAutoloadPayload = str_repeat('b', 5);

        add_option($autoloadOption, $autoloadPayload, '', 'yes');
        add_option($nonAutoloadOption, $nonAutoloadPayload, '', 'no');

        $after = AutoloadInspector::get_totals();

        $this->assertSame($before['yes']['count'] + 1, $after['yes']['count']);
        $this->assertSame($before['yes']['bytes'] + strlen($autoloadPayload), $after['yes']['bytes']);
        $this->assertSame($before['no']['count'] + 1, $after['no']['count']);
        $this->assertSame($before['no']['bytes'] + strlen($nonAutoloadPayload), $after['no']['bytes']);
        $this->assertSame(
            $before['total_bytes'] + strlen($autoloadPayload) + strlen($nonAutoloadPayload),
            $after['total_bytes']
        );
    }

    public function test_get_heavy_options_lists_expected_rows(): void
    {
        $option  = 'gm2_autoload_heavy_option';
        $payload = str_repeat('x', 60000);
        $this->trackOption($option);

        add_option($option, $payload, '', 'yes');

        $rows  = AutoloadInspector::get_heavy_options(50000, 10, 'yes');
        $names = array_column($rows, 'option_name');

        $this->assertContains($option, $names);

        foreach ($rows as $row) {
            if ($row['option_name'] === $option) {
                $this->assertSame(strlen($payload), $row['bytes']);
            }
        }
    }

    public function test_format_bytes_outputs_human_readable_values(): void
    {
        $this->assertSame('0 B', AutoloadInspector::format_bytes(0));
        $this->assertSame('1.50 KB', AutoloadInspector::format_bytes(1536));
        $this->assertSame('1.00 MB', AutoloadInspector::format_bytes(1048576));
    }

    private function trackOption(string $option): void
    {
        $this->trackedOptions[] = $option;
    }
}
