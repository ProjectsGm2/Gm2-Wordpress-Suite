<?php
class LazyMetaValueTest extends WP_UnitTestCase {
    public function test_value_is_lazy_loaded() {
        $post_id = self::factory()->post->create();
        update_post_meta($post_id, '_gm2_lazy', 'foo');

        $calls = 0;
        add_filter('gm2_lazy_load_meta_value', function($defer, $obj, $key) {
            return $key === '_gm2_lazy';
        }, 10, 3);
        add_filter('gm2_lazy_meta_loader', function($resolver) use (&$calls) {
            return function() use ($resolver, &$calls) {
                $calls++;
                return $resolver();
            };
        });

        $value = gm2_get_meta_value($post_id, '_gm2_lazy', 'post', []);
        $this->assertInstanceOf('GM2_Lazy_Meta_Value', $value);
        $this->assertSame(0, $calls);
        $this->assertSame('foo', $value->get());
        $this->assertSame(1, $calls);

        remove_all_filters('gm2_lazy_load_meta_value');
        remove_all_filters('gm2_lazy_meta_loader');
    }
}
