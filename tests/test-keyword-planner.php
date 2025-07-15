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
        $response = [
            'results' => [
                [
                    'text' => 'alpha',
                    'keyword_idea_metrics' => [
                        'avg_monthly_searches'      => 100,
                        'competition'               => 'LOW',
                        'three_month_avg_searches'  => 110
                    ]
                ],
                [
                    'text' => 'beta',
                    'keyword_idea_metrics' => [
                        'avg_monthly_searches' => 50,
                        'competition'          => null
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
        $this->assertSame('beta', $ideas[1]['text']);
        $this->assertSame(50, $ideas[1]['metrics']['avg_monthly_searches']);
        $this->assertArrayNotHasKey('competition', $ideas[1]['metrics']);
    }
}
