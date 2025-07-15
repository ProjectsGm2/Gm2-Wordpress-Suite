<?php
use Gm2\Gm2_Google_OAuth;

class RedirectRegistrationTest extends WP_UnitTestCase {
    public function tearDown(): void {
        remove_filter('pre_http_request', [ $this, 'intercept' ], 10);
        parent::tearDown();
    }

    public function intercept($pre, $args, $url) {
        if (false !== strpos($url, '/token')) {
            return [ 'response' => ['code' => 200], 'body' => json_encode(['access_token' => 'x']) ];
        }
        if (false !== strpos($url, '/projects/')) {
            $this->calls[] = $args['method'];
            return [ 'response' => ['code' => 200], 'body' => json_encode(['redirectUris' => []]) ];
        }
        return false;
    }

    public function test_auth_url_triggers_registration() {
        $this->calls = [];
        add_filter('pre_http_request', [ $this, 'intercept' ], 10, 3);
        add_filter('gm2_gcloud_project_id', function() { return 'p'; });
        $tmp = tempnam(sys_get_temp_dir(), 'key');
        $key = <<<'KEY'
-----BEGIN PRIVATE KEY-----
MIIBVAIBADANBgkqhkiG9w0BAQEFAASCAT4wggE6AgEAAkEA2555iIYJyFM8/Uz1
3XcS7C1nfVhYTQD0X0WE6kVncN8GPfh44TtXB8mGeJeZaDlhwBnoEA++BYtloXi1
8/xkeQIDAQABAkAUF8+uufSzK0ptMllcRcSpbw4E3cxWXzv8a3PZqzfnj5Sqf4CH
Cnb47kb9eMoopaK4pjnzxIN8Bvrqd+0syfLxAiEA9se3A4qA2COkzkwOp3q/lryA
G36H/IpUSh9SNo+lMYcCIQDj0vt63bApspBQhVmnk6drG8+nmEGYz/zqmnNdNI85
/wIgZN3epRjgbveqrhOSTcwzMQZdCl/eb0+PAjjpHpn5+FMCIQCuRpXTPkRlEVBu
GCQmGcBHIgYuaT08vVX2zOGVGgC6VwIgJC/63eSSnL7QMLP3TAo3KvXI12wFph4+
vmEAqoYdIGk=
-----END PRIVATE KEY-----
KEY;
        file_put_contents($tmp, json_encode(['client_email'=>'test@example.com','private_key'=>$key]));
        add_filter('gm2_service_account_json', function() use ($tmp) { return $tmp; });
        update_option('gm2_gads_client_id', '123.apps.googleusercontent.com');
        $oauth = new Gm2_Google_OAuth();
        $oauth->get_auth_url();
        unlink($tmp);
        $this->assertContains('PATCH', $this->calls);
    }
}
