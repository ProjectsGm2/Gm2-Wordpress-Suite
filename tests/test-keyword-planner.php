<?php
use Gm2\Gm2_Keyword_Planner;

class KeywordPlannerTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        update_option('gm2_gads_developer_token', 'dev');
        update_option('gm2_gads_customer_id', '123-456-7890');
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);
    }

    public function test_metrics_parsed_from_response() {
        $months = [];
        for ($i = 1; $i <= 14; $i++) {
            $months[] = [
                'year' => 2024 + intdiv($i - 1, 12),
                'month' => (($i - 1) % 12) + 1,
                'monthly_searches' => $i * 10,
            ];
        }
        $response = [
            'results' => [
                [
                    'text' => 'alpha',
                    'keyword_idea_metrics' => [
                        'avg_monthly_searches' => 100,
                        'competition'          => 'LOW',
                        'monthly_search_volumes' => $months,
                    ]
                ],
                [
                    'text' => 'beta',
                    'keyword_idea_metrics' => [
                        'avg_monthly_searches' => 50,
                        'competition'          => null,
                        'monthly_search_volumes' => [
                            ['year' => 2025, 'month' => 1, 'monthly_searches' => 200],
                            ['year' => 2025, 'month' => 2, 'monthly_searches' => 210],
                            ['year' => 2025, 'month' => 3, 'monthly_searches' => 220],
                        ]
                    ]
                ]
            ]
        ];

        $filter = function($pre, $args, $url) use ($response) {
            if (false !== strpos($url, 'generateKeywordIdeas')) {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode($response)
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $planner = new Gm2_Keyword_Planner();
        $ideas   = $planner->generate_keyword_ideas('seed');

        remove_filter('pre_http_request', $filter, 10);

        $this->assertIsArray($ideas);
        $this->assertCount(2, $ideas);
        $this->assertSame('alpha', $ideas[0]['text']);
        $this->assertSame(100, $ideas[0]['metrics']['avg_monthly_searches']);
        $this->assertSame('LOW', $ideas[0]['metrics']['competition']);
        $this->assertSame(30, $ideas[0]['metrics']['three_month_change']);
        $this->assertSame(130, $ideas[0]['metrics']['yoy_change']);
        $this->assertSame('beta', $ideas[1]['text']);
        $this->assertSame(50, $ideas[1]['metrics']['avg_monthly_searches']);
        $this->assertArrayNotHasKey('competition', $ideas[1]['metrics']);
        $this->assertSame(20, $ideas[1]['metrics']['three_month_change']);
        $this->assertArrayNotHasKey('yoy_change', $ideas[1]['metrics']);
    }
}
