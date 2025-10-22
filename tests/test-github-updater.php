<?php

declare(strict_types=1);

use Gm2\Updater\Gm2_GitHub_Updater;
use Gm2\Updater\Gm2_GitHub_Updater_Admin;
use Gm2\Updater\Gm2_GitHub_Upgrader_Skin;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionClass;
use ReflectionMethod;

/**
 * @group github-updater
 */
class GithubUpdaterTest extends WP_UnitTestCase {
    private string $pluginFile;

    /**
     * @var array<string, mixed>
     */
    private array $defaultSettings;

    public function set_up(): void {
        parent::set_up();

        $this->pluginFile      = GM2_PLUGIN_DIR . 'gm2-wordpress-suite.php';
        $this->defaultSettings = [
            'owner'        => 'gm2',
            'repo'         => 'wordpress-suite',
            'branch'       => 'develop',
            'token'        => '',
            'channel'      => 'release',
            'cache_ttl'    => 600,
            'check_interval' => 30,
        ];

        wp_cache_flush();
        wp_clear_scheduled_hook('gm2_github_updater_warmup');
    }

    public function tear_down(): void {
        remove_all_filters('pre_http_request');
        remove_all_filters('http_request_args');
        parent::tear_down();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function create_updater(array $overrides = []): Gm2_GitHub_Updater {
        return new Gm2_GitHub_Updater($this->pluginFile, array_merge($this->defaultSettings, $overrides));
    }

    public function test_release_channel_parses_version_and_caches_response(): void {
        $updater = $this->create_updater([
            'channel'   => 'release',
            'cache_ttl' => 123,
            'token'     => 'shhh',
        ]);

        $releaseCalls = 0;
        add_filter(
            'pre_http_request',
            $releaseMock = static function ($pre, $args, $url) use (&$releaseCalls) {
                if (str_contains($url, '/releases/latest')) {
                    ++$releaseCalls;

                    return [
                        'headers'  => [],
                        'body'     => wp_json_encode([
                            'tag_name'    => 'v1.7.0',
                            'zipball_url' => 'https://github.com/gm2/wordpress-suite/zipball/v1.7.0',
                            'body'        => "## Changes\n* Fix",
                            'html_url'    => 'https://github.com/gm2/wordpress-suite/releases/tag/v1.7.0',
                            'published_at'=> '2024-04-01T10:00:00Z',
                        ]),
                        'response' => [
                            'code'    => 200,
                            'message' => 'OK',
                        ],
                    ];
                }

                if (str_contains($url, 'readme.txt')) {
                    return [
                        'headers'  => [],
                        'body'     => "Tested up to: 6.4.3\nRequires at least: 6.2\n== Description ==\nThis is a test description.\n== Changelog ==\n* Added feature",
                        'response' => [
                            'code'    => 200,
                            'message' => 'OK',
                        ],
                    ];
                }

                return false;
            },
            10,
            3
        );

        $meta = $updater->get_remote_version(true);

        $this->assertSame('1.7.0', $meta['version']);
        $this->assertSame('https://github.com/gm2/wordpress-suite/zipball/v1.7.0', $meta['package']);
        $this->assertSame('https://github.com/gm2/wordpress-suite/releases/tag/v1.7.0', $meta['changelog_url']);
        $this->assertSame('6.4.3', $meta['tested']);
        $this->assertSame('6.2', $meta['requires']);
        $this->assertStringContainsString('Added feature', $meta['changelog']);
        $this->assertSame(1, $releaseCalls, 'Initial request should hit the GitHub API exactly once.');

        $refClass   = new ReflectionClass($updater);
        $cacheKey   = $this->get_cache_key($updater, $refClass);
        $cachedMeta = wp_cache_get($cacheKey, 'gm2_github_updater');
        $this->assertSame($meta, $cachedMeta, 'Metadata should be cached under the computed cache key.');

        $remoteProp = $refClass->getProperty('remote_version');
        $remoteProp->setAccessible(true);
        $remoteProp->setValue($updater, null);

        remove_filter('pre_http_request', $releaseMock, 10);

        $cachedMetaAgain = $updater->get_remote_version();
        $this->assertSame($meta, $cachedMetaAgain, 'Subsequent reads should be served from cache without additional HTTP requests.');
    }

    public function test_branch_channel_parses_commit_sha_and_date(): void {
        $updater = $this->create_updater([
            'channel' => 'branch',
            'branch'  => 'feature-branch',
        ]);

        add_filter(
            'pre_http_request',
            $commitMock = static function ($pre, $args, $url) {
                if (str_contains($url, '/commits/feature-branch')) {
                    return [
                        'headers'  => [],
                        'body'     => wp_json_encode([
                            'sha'    => 'abcdef1234567890',
                            'commit' => [
                                'message'   => 'Improve updater logic',
                                'committer' => [
                                    'date' => '2024-03-20T12:34:56Z',
                                ],
                            ],
                        ]),
                        'response' => [
                            'code'    => 200,
                            'message' => 'OK',
                        ],
                    ];
                }

                if (str_contains($url, 'readme.txt')) {
                    return [
                        'headers'  => [],
                        'body'     => '',
                        'response' => [
                            'code'    => 404,
                            'message' => 'Not Found',
                        ],
                    ];
                }

                return false;
            },
            10,
            3
        );

        $meta = $updater->get_remote_version(true);

        $this->assertStringContainsString('2024.03.20', $meta['version']);
        $this->assertStringContainsString('abcdef1', $meta['version']);
        $this->assertSame('Improve updater logic', $meta['description']);
        $this->assertSame('https://api.github.com/repos/gm2/wordpress-suite/zipball/abcdef1234567890', $meta['package']);

        remove_filter('pre_http_request', $commitMock, 10);
    }

    public function test_plugins_api_formats_metadata(): void {
        $updater = $this->create_updater();

        $meta = [
            'version'       => '2.0.0',
            'description'   => 'Plugin description',
            'changelog'     => 'Changelog body',
            'tested'        => '6.4',
            'requires'      => '6.0',
            'package'       => 'https://example.com/download.zip',
            'changelog_url' => 'https://example.com/changelog',
            'last_updated'  => '2024-03-15T00:00:00Z',
        ];

        $refClass   = new ReflectionClass($updater);
        $remoteProp = $refClass->getProperty('remote_version');
        $remoteProp->setAccessible(true);
        $remoteProp->setValue($updater, $meta);

        $slug = dirname(plugin_basename($this->pluginFile));
        $info = $updater->plugins_api(false, 'plugin_information', (object) ['slug' => $slug]);

        $this->assertInstanceOf(stdClass::class, $info);
        $this->assertSame('2.0.0', $info->version);
        $this->assertSame('https://example.com/download.zip', $info->download_link);
        $this->assertSame('Changelog body', $info->sections['changelog']);
        $this->assertSame('Plugin description', $info->sections['description']);
        $this->assertSame('6.4', $info->tested);
        $this->assertSame('6.0', $info->requires);
        $this->assertSame('2024-03-15T00:00:00Z', $info->last_updated);
    }

    public function test_authenticate_http_injects_headers_for_github(): void {
        $updater = $this->create_updater(['token' => 'token-123']);

        $args = $updater->authenticate_http([], 'https://api.github.com/repos/gm2/wordpress-suite/releases/latest');

        $this->assertArrayHasKey('headers', $args);
        $this->assertSame('Gm2WP-Updater', $args['headers']['User-Agent']);
        $this->assertSame('token token-123', $args['headers']['Authorization']);

        $args = $updater->authenticate_http([], 'https://example.com');
        $this->assertArrayNotHasKey('headers', $args);
    }

    public function test_refresh_triggers_manual_check_and_resets_cache(): void {
        $updater = $this->create_updater([
            'channel' => 'release',
        ]);

        $calls = 0;
        add_filter(
            'pre_http_request',
            $refreshMock = static function ($pre, $args, $url) use (&$calls) {
                if (str_contains($url, '/releases/latest')) {
                    ++$calls;

                    return [
                        'headers'  => [],
                        'body'     => wp_json_encode([
                            'tag_name'    => 'v9.9.9',
                            'zipball_url' => 'https://example.com/latest.zip',
                            'body'        => 'Release notes',
                            'html_url'    => 'https://example.com/changelog',
                            'published_at'=> '2024-05-01T00:00:00Z',
                        ]),
                        'response' => [
                            'code'    => 200,
                            'message' => 'OK',
                        ],
                    ];
                }

                if (str_contains($url, 'readme.txt')) {
                    return [
                        'headers'  => [],
                        'body'     => '',
                        'response' => [
                            'code'    => 404,
                            'message' => 'Not Found',
                        ],
                    ];
                }

                return false;
            },
            10,
            3
        );

        $meta = $updater->refresh();

        $this->assertIsArray($meta);
        $this->assertSame('9.9.9', $meta['version']);
        $this->assertSame('https://example.com/latest.zip', $meta['package']);
        $this->assertSame(1, $calls, 'Refresh should make a single forced GitHub request.');

        $refClass   = new ReflectionClass($updater);
        $remoteProp = $refClass->getProperty('remote_version');
        $remoteProp->setAccessible(true);
        $this->assertSame($meta, $remoteProp->getValue($updater));

        remove_filter('pre_http_request', $refreshMock, 10);
    }

    public function test_force_refresh_injects_update_entry_even_when_versions_match(): void {
        $updater         = $this->create_updater(['channel' => 'release']);
        $pluginBasename  = plugin_basename($this->pluginFile);
        $pluginData      = get_plugin_data($this->pluginFile, false, false);
        $localVersion    = isset($pluginData['Version']) ? $pluginData['Version'] : '0.0.0';
        $downloadPackage = 'https://example.com/force-update.zip';

        add_filter(
            'pre_http_request',
            $forceMock = static function ($pre, $args, $url) use ($localVersion, $downloadPackage) {
                if (str_contains($url, '/releases/latest')) {
                    return [
                        'headers'  => [],
                        'body'     => wp_json_encode([
                            'tag_name'    => 'v' . $localVersion,
                            'zipball_url' => $downloadPackage,
                            'body'        => '',
                            'html_url'    => '',
                            'published_at'=> '2024-05-01T00:00:00Z',
                        ]),
                        'response' => [
                            'code'    => 200,
                            'message' => 'OK',
                        ],
                    ];
                }

                if (str_contains($url, 'readme.txt')) {
                    return [
                        'headers'  => [],
                        'body'     => '',
                        'response' => [
                            'code'    => 404,
                            'message' => 'Not Found',
                        ],
                    ];
                }

                return false;
            },
            10,
            3
        );

        $meta = $updater->refresh();
        $this->assertSame($localVersion, $meta['version']);

        $transient = get_site_transient('update_plugins');
        $this->assertIsObject($transient);
        $this->assertArrayHasKey('response', (array) $transient);
        $this->assertArrayNotHasKey($pluginBasename, $transient->response);

        $meta = $updater->refresh(true);
        $this->assertSame($localVersion, $meta['version']);

        $transient = get_site_transient('update_plugins');
        $this->assertIsObject($transient);
        $this->assertArrayHasKey($pluginBasename, $transient->response);
        $this->assertSame($downloadPackage, $transient->response[$pluginBasename]->package);

        remove_filter('pre_http_request', $forceMock, 10);
    }

    public function test_run_registers_cron_schedule_and_event(): void {
        $updater = $this->create_updater([
            'check_interval' => 15,
        ]);

        $schedules = $updater->register_cron_schedule([]);
        $this->assertArrayHasKey('gm2_github_updater_interval', $schedules);
        $this->assertSame(15 * MINUTE_IN_SECONDS, $schedules['gm2_github_updater_interval']['interval']);

        $updater->run();

        $timestamp = wp_next_scheduled('gm2_github_updater_warmup');
        $this->assertNotFalse($timestamp, 'Run should schedule the warmup event.');
    }

    public function test_rate_limit_backoff_prevents_follow_up_requests_on_release_channel(): void {
        $updater = $this->create_updater([
            'channel' => 'release',
        ]);

        $calls = 0;
        add_filter(
            'pre_http_request',
            $rateLimitMock = static function ($pre, $args, $url) use (&$calls) {
                if (str_contains($url, '/releases/latest')) {
                    ++$calls;

                    return [
                        'headers'  => [
                            'Retry-After' => 10,
                        ],
                        'body'     => '',
                        'response' => [
                            'code'    => 403,
                            'message' => 'Forbidden',
                        ],
                    ];
                }

                return false;
            },
            10,
            3
        );

        $error = $updater->get_remote_version(true);
        $this->assertInstanceOf(WP_Error::class, $error);
        $this->assertSame('gm2_updater_rate_limit', $error->get_error_code());
        $this->assertSame(1, $calls, 'First call should hit the HTTP layer.');

        remove_filter('pre_http_request', $rateLimitMock, 10);
        $calls = 0;

        add_filter(
            'pre_http_request',
            $unexpected = static function () use (&$calls) {
                ++$calls;
                return false;
            }
        );

        $backoff = $updater->get_remote_version();
        $this->assertInstanceOf(WP_Error::class, $backoff);
        $this->assertSame('gm2_updater_backoff', $backoff->get_error_code());
        $this->assertSame(0, $calls, 'Backoff should short-circuit before issuing another HTTP request.');

        remove_filter('pre_http_request', $unexpected);
    }

    public function test_rate_limit_backoff_prevents_follow_up_requests_on_branch_channel(): void {
        $updater = $this->create_updater([
            'channel' => 'branch',
        ]);

        $calls = 0;
        add_filter(
            'pre_http_request',
            $rateLimitMock = static function ($pre, $args, $url) use (&$calls) {
                if (str_contains($url, '/commits/develop')) {
                    ++$calls;

                    return [
                        'headers'  => [
                            'Retry-After' => 5,
                        ],
                        'body'     => '',
                        'response' => [
                            'code'    => 403,
                            'message' => 'Forbidden',
                        ],
                    ];
                }

                return false;
            },
            10,
            3
        );

        $error = $updater->get_remote_version(true);
        $this->assertInstanceOf(WP_Error::class, $error);
        $this->assertSame('gm2_updater_rate_limit', $error->get_error_code());
        $this->assertSame(1, $calls);

        remove_filter('pre_http_request', $rateLimitMock, 10);

        $backoff = $updater->get_remote_version();
        $this->assertInstanceOf(WP_Error::class, $backoff);
        $this->assertSame('gm2_updater_backoff', $backoff->get_error_code());
    }

    public function test_check_for_update_populates_transient_with_remote_metadata(): void {
        $updater = $this->create_updater();

        $meta = [
            'version'       => '9.9.9',
            'tested'        => '6.4',
            'requires'      => '6.1',
            'package'       => 'https://example.com/pkg.zip',
            'changelog_url' => 'https://example.com/changelog',
        ];

        $refClass   = new ReflectionClass($updater);
        $remoteProp = $refClass->getProperty('remote_version');
        $remoteProp->setAccessible(true);
        $remoteProp->setValue($updater, $meta);

        $transient = new stdClass();
        $transient->response = [];

        $transient = $updater->check_for_update($transient);

        $this->assertArrayHasKey($updater->get_plugin_basename(), $transient->response);
        $plugin = $transient->response[$updater->get_plugin_basename()];
        $this->assertSame('9.9.9', $plugin->new_version);
        $this->assertSame('https://example.com/pkg.zip', $plugin->package);
        $this->assertSame('https://example.com/changelog', $plugin->url);
    }

    public function test_check_for_update_leaves_transient_untouched_when_repo_missing(): void {
        $updater = $this->create_updater([
            'channel' => 'release',
        ]);

        add_filter(
            'pre_http_request',
            $notFoundMock = static function ($pre, $args, $url) {
                if (str_contains($url, '/releases/latest')) {
                    return [
                        'headers'  => [],
                        'body'     => '',
                        'response' => [
                            'code'    => 404,
                            'message' => 'Not Found',
                        ],
                    ];
                }

                return false;
            },
            10,
            3
        );

        $transient = (object) [
            'checked'  => [
                $updater->get_plugin_basename() => GM2_VERSION,
            ],
            'response' => [],
        ];

        $result = $updater->check_for_update($transient);

        $this->assertSame([], $result->response, 'Missing repositories should not modify the transient response list.');
        $this->assertIsString($updater->get_last_error());

        remove_filter('pre_http_request', $notFoundMock, 10);
    }

    public function test_plugins_api_returns_error_when_slug_does_not_match(): void {
        $updater = $this->create_updater();
        $result  = $updater->plugins_api(false, 'plugin_information', (object) ['slug' => 'other-plugin']);
        $this->assertFalse($result);
    }

    public function test_pre_http_request_mock_supports_post_requests(): void {
        $updater = $this->create_updater();

        add_filter(
            'pre_http_request',
            $mock = static function ($pre, $args, $url) {
                if ('POST' === ($args['method'] ?? 'GET')) {
                    return [
                        'headers'  => [],
                        'body'     => wp_json_encode(['ok' => true]),
                        'response' => [
                            'code'    => 200,
                            'message' => 'OK',
                        ],
                    ];
                }

                return false;
            },
            10,
            3
        );

        $response = wp_remote_post('https://api.github.com/repos/gm2/wordpress-suite/zipball');
        $this->assertSame(200, wp_remote_retrieve_response_code($response));
        $this->assertSame('{"ok":true}', wp_remote_retrieve_body($response));

        remove_filter('pre_http_request', $mock, 10);
    }

    public function test_get_skin_error_handles_various_skins(): void {
        $admin  = new Gm2_GitHub_Updater_Admin();
        $method = new ReflectionMethod($admin, 'get_skin_error');
        $method->setAccessible(true);

        $skinWithResult = new class {
            /** @var WP_Error|null */
            public $result;
        };
        $skinWithResult->result = new WP_Error('gm2_updater_failed', 'Upgrade failed via result.');

        $error = $method->invoke($admin, $skinWithResult);
        $this->assertInstanceOf(WP_Error::class, $error);
        $this->assertSame('Upgrade failed via result.', $error->get_error_message());

        $skinWithMethod = new class {
            public function get_errors() {
                return new WP_Error('gm2_updater_failed', 'Upgrade failed via method.');
            }
        };

        $errorFromMethod = $method->invoke($admin, $skinWithMethod);
        $this->assertInstanceOf(WP_Error::class, $errorFromMethod);
        $this->assertSame('Upgrade failed via method.', $errorFromMethod->get_error_message());

        $customSkin = new Gm2_GitHub_Upgrader_Skin();
        $customSkin->error(new WP_Error('gm2_updater_failed', 'Upgrade failed via custom skin.'));

        $customError = $method->invoke($admin, $customSkin);
        $this->assertInstanceOf(WP_Error::class, $customError);
        $this->assertSame('Upgrade failed via custom skin.', $customError->get_error_message());

        $skinWithoutIssues = new class {};
        $this->assertNull($method->invoke($admin, $skinWithoutIssues));
    }

    public function test_custom_upgrader_skin_collects_logs_and_errors(): void {
        $skin = new Gm2_GitHub_Upgrader_Skin();

        $skin->feedback('Downloading update from %s…', 'https://example.com/plugin.zip');
        $skin->feedback('<strong>Installing</strong> update.');
        $skin->error(new WP_Error('gm2_updater_failed', 'Install failed & needs attention.'));

        $log = $skin->get_log();
        $this->assertNotEmpty($log);
        $this->assertSame('Downloading update from https://example.com/plugin.zip…', $log[0]);
        $this->assertStringNotContainsString('<strong>', $log[1]);

        $errors = $skin->get_errors();
        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertTrue($errors->has_errors());
        $this->assertSame('Install failed & needs attention.', $errors->get_error_message());
    }

    /**
     * @return string
     */
    private function get_cache_key(Gm2_GitHub_Updater $updater, ReflectionClass $refClass): string {
        $method = $refClass->getMethod('get_cache_key');
        $method->setAccessible(true);

        $cacheKey = $method->invoke($updater);
        if (!is_string($cacheKey)) {
            throw new AssertionFailedError('Cache key must be a string.');
        }

        return $cacheKey;
    }
}
