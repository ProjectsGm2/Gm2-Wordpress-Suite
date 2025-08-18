<?php
class ConfigHistoryTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        delete_option('gm2_custom_posts_config');
        delete_option('gm2_custom_posts_config_history');
        delete_option('gm2_field_groups');
        delete_option('gm2_field_groups_history');
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('gm2_custom_posts_config');
        delete_option('gm2_custom_posts_config_history');
        delete_option('gm2_field_groups');
        delete_option('gm2_field_groups_history');
    }

    public function test_history_and_restore_custom_posts() {
        update_option('gm2_custom_posts_config', ['first' => 1]);
        update_option('gm2_custom_posts_config', ['second' => 2]);
        $history = gm2_get_option_history('gm2_custom_posts_config');
        $this->assertCount(2, $history);
        $this->assertSame(2, end($history)['version']);
        gm2_restore_option_version('gm2_custom_posts_config', 1);
        $this->assertSame(['first' => 1], get_option('gm2_custom_posts_config'));
    }

    public function test_history_and_restore_field_groups() {
        update_option('gm2_field_groups', ['a' => 1]);
        update_option('gm2_field_groups', ['b' => 2]);
        $history = gm2_get_option_history('gm2_field_groups');
        $this->assertCount(2, $history);
        gm2_restore_option_version('gm2_field_groups', 1);
        $this->assertSame(['a' => 1], get_option('gm2_field_groups'));
    }
}
