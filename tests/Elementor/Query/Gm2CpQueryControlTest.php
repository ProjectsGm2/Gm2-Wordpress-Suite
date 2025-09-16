<?php

declare(strict_types=1);

use Gm2\Integrations\Elementor\GM2_CP_Elementor_Query;

class Gm2CpQueryControlTest extends WP_UnitTestCase
{
    public function test_apply_query_sanitizes_new_control_values(): void
    {
        $settings = [
            'gm2_cp_post_type'   => [' job ', 'event<script>'],
            'gm2_cp_taxonomy'    => 'genre<script>',
            'gm2_cp_terms'       => ['5', '8<script>'],
            'gm2_cp_meta_key'    => ' total_sales ',
            'gm2_cp_meta_value'  => ' 100 ',
            'gm2_cp_meta_compare'=> '>=',
            'gm2_cp_meta_type'   => 'NUMERIC',
            'gm2_cp_price'       => [
                'key' => ' price-key ',
                'min' => '10.5',
                'max' => '25.5',
            ],
            'gm2_cp_geo_lat'     => '45.00',
            'gm2_cp_geo_lng'     => '-93.00',
            'gm2_cp_geo_radius'  => [
                'value' => '5',
                'unit'  => 'mi',
            ],
            'gm2_cp_geo_lat_key' => ' latitude_meta ',
            'gm2_cp_geo_lng_key' => ' longitude_meta ',
        ];

        $widget = new class($settings) {
            private $settings;

            public function __construct(array $settings)
            {
                $this->settings = $settings;
            }

            public function get_settings(): array
            {
                return $this->settings;
            }
        };

        $query = new WP_Query();
        GM2_CP_Elementor_Query::apply_query($query, $widget);

        $this->assertSame(['job', 'eventscript'], $query->get('post_type'));

        $tax_query = $query->get('tax_query');
        $this->assertCount(1, $tax_query);
        $this->assertSame('genrescript', $tax_query[0]['taxonomy']);
        $this->assertSame([5, 8], $tax_query[0]['terms']);

        $meta_query = $query->get('meta_query');
        $this->assertNotEmpty($meta_query);

        $meta_keys = wp_list_pluck($meta_query, 'key');
        $this->assertContains('total_sales', $meta_keys);
        $this->assertContains('price-key', $meta_keys);
        $this->assertContains('latitude_meta', $meta_keys);
        $this->assertContains('longitude_meta', $meta_keys);

        $price_clause = $meta_query[array_search('price-key', $meta_keys, true)];
        $this->assertSame('NUMERIC', $price_clause['type']);
        $this->assertSame('BETWEEN', $price_clause['compare']);
        $this->assertEquals([10.5, 25.5], array_values($price_clause['value']));

        $lat_clause = $meta_query[array_search('latitude_meta', $meta_keys, true)];
        $lng_clause = $meta_query[array_search('longitude_meta', $meta_keys, true)];
        $this->assertSame('BETWEEN', $lat_clause['compare']);
        $this->assertCount(2, $lat_clause['value']);
        $this->assertSame('BETWEEN', $lng_clause['compare']);
        $this->assertCount(2, $lng_clause['value']);

        // 5 miles is approximately 8.04672 kilometres.
        $radius_km = 5 * 1.609344;
        $expected_lat_delta = $radius_km / 111.045;
        $this->assertEqualsWithDelta($expected_lat_delta, $lat_clause['value'][1] - 45.0, 0.0001);
        $this->assertEqualsWithDelta($expected_lat_delta, 45.0 - $lat_clause['value'][0], 0.0001);
    }
}
