<?php
use Gm2\Gm2_PageSpeed;

class PageSpeedTest extends WP_UnitTestCase {
    public function test_parse_scores() {
        $body = json_encode([
            'lighthouseResult' => [
                'categories' => [
                    'performance' => [ 'score' => 0.82 ]
                ]
            ]
        ]);

        $filter = function($pre, $args, $url) use ($body) {
            return [ 'response' => ['code' => 200], 'body' => $body ];
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $ps = new Gm2_PageSpeed('key');
        $scores = $ps->get_scores('https://example.com');

        remove_filter('pre_http_request', $filter, 10);

        $this->assertIsArray($scores);
        $this->assertSame(82.0, $scores['mobile']);
        $this->assertSame(82.0, $scores['desktop']);
    }
}
