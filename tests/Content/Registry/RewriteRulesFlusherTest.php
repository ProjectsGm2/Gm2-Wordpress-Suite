<?php

use Gm2\Content\Registry\PostTypeRegistry;
use Gm2\Content\Registry\RewriteRulesFlusher;
use ReflectionClass;

class RewriteRulesFlusherTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetFlusherState();
    }

    protected function tearDown(): void
    {
        $this->resetFlusherState();
        parent::tearDown();
    }

    public function test_activation_and_deactivation_hooks_registered(): void
    {
        if (!function_exists('register_activation_hook') || !function_exists('register_deactivation_hook') || !function_exists('plugin_basename')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginFile = GM2_PLUGIN_DIR . 'gm2-wordpress-suite.php';
        $pluginBase = plugin_basename($pluginFile);

        remove_action('activate_' . $pluginBase, 'flush_rewrite_rules');
        remove_action('deactivate_' . $pluginBase, 'flush_rewrite_rules');

        $this->setFlusherProperty('hooksRegistered', false);

        new PostTypeRegistry();

        $this->assertSame(10, has_action('activate_' . $pluginBase, 'flush_rewrite_rules'));
        $this->assertSame(10, has_action('deactivate_' . $pluginBase, 'flush_rewrite_rules'));
    }

    private function resetFlusherState(): void
    {
        if (defined('GM2_PLUGIN_DIR') && function_exists('plugin_basename')) {
            $pluginFile = GM2_PLUGIN_DIR . 'gm2-wordpress-suite.php';
            if (file_exists($pluginFile)) {
                $pluginBase = plugin_basename($pluginFile);
                remove_action('activate_' . $pluginBase, 'flush_rewrite_rules');
                remove_action('deactivate_' . $pluginBase, 'flush_rewrite_rules');
            }
        }

        $this->setFlusherProperty('hooksRegistered', false);
        $this->setFlusherProperty('rewriteScheduled', false);
    }

    private function setFlusherProperty(string $property, bool $value): void
    {
        $reflection = new ReflectionClass(RewriteRulesFlusher::class);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
    }
}
