<?php
use Gm2\NetworkPayload\Module;

class HandleRulesTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        wp_dequeue_script('gm2-core');
        wp_deregister_script('gm2-core');
        wp_dequeue_script('gm2-child');
        wp_deregister_script('gm2-child');
        wp_dequeue_script('gm2-remove');
        wp_deregister_script('gm2-remove');
        wp_scripts()->done = [];
        delete_option('gm2_netpayload_settings');
        remove_filter('script_loader_tag', [Module::class, 'filter_script_tag'], 10);
        parent::tearDown();
    }

    private function register_scripts(): void {
        wp_register_script('gm2-core', 'https://example.com/core.js', [], null);
        wp_register_script('gm2-child', 'https://example.com/child.js', ['gm2-core'], null);
        wp_register_script('gm2-remove', 'https://example.com/remove.js', [], null);
        wp_enqueue_script('gm2-child');
        wp_enqueue_script('gm2-remove');
    }

    private function get_output(): string {
        ob_start();
        wp_print_scripts();
        return ob_get_clean();
    }

    private function extract_tag(string $html, string $handle): string {
        preg_match("/\<script[^>]*id='" . preg_quote($handle, '/') . "-js'[^>]*>\<\/script>/", $html, $m);
        return $m[0] ?? '';
    }

    public function test_dequeues_and_adds_async_on_front_page(): void {
        update_option('gm2_netpayload_settings', [
            'handle_rules' => [
                'scripts' => [
                    'gm2-remove' => ['dequeue' => ['front_page' => true]],
                    'gm2-child'  => ['attr' => 'async'],
                ],
            ],
        ]);
        add_filter('script_loader_tag', [Module::class, 'filter_script_tag'], 10, 3);

        $this->register_scripts();
        $this->go_to(home_url('/'));
        Module::maybe_apply_handle_rules();

        $html = $this->get_output();

        $this->assertStringNotContainsString('gm2-remove-js', $html);
        $this->assertStringContainsString('gm2-core-js', $html);
        $this->assertStringContainsString('gm2-child-js', $html);

        $childTag = $this->extract_tag($html, 'gm2-child');
        $this->assertStringContainsString('async', $childTag);
    }

    public function test_handle_not_dequeued_off_front_page(): void {
        update_option('gm2_netpayload_settings', [
            'handle_rules' => [
                'scripts' => [
                    'gm2-remove' => ['dequeue' => ['front_page' => true]],
                ],
            ],
        ]);
        add_filter('script_loader_tag', [Module::class, 'filter_script_tag'], 10, 3);

        $this->register_scripts();
        $this->go_to(home_url('/inner'));
        Module::maybe_apply_handle_rules();

        $html = $this->get_output();

        $this->assertStringContainsString('gm2-remove-js', $html);
    }
}
