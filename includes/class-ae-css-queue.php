<?php
namespace AE\CSS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple queue for CSS related jobs.
 */
final class AE_CSS_Queue {
    private const OPTION = 'ae_css_queue';

    private static ?AE_CSS_Queue $instance = null;

    /**
     * Bootstrap hooks.
     */
    public static function bootstrap(): void {
        $instance = self::get_instance();
        add_action('ae_css_queue_runner', [ $instance, 'run_next' ]);
        add_filter('cron_schedules', [ __CLASS__, 'add_schedule' ]);
        add_action('save_post', [ __CLASS__, 'handle_save_post' ], 10, 1);
        add_action('switch_theme', [ __CLASS__, 'handle_switch_theme' ], 10, 0);
        if (!\wp_next_scheduled('ae_css_queue_runner')) {
            \wp_schedule_event(time(), 'ae_css_five_minutes', 'ae_css_queue_runner');
        }
    }

    /**
     * Initialize the queue.
     */
    public static function init(): void {
        self::bootstrap();
    }

    /**
     * Retrieve singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a 5 minute cron schedule.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public static function add_schedule(array $schedules): array {
        if (!isset($schedules['ae_css_five_minutes'])) {
            $schedules['ae_css_five_minutes'] = [
                'interval' => 300,
                'display'  => __( 'Every Five Minutes', 'gm2-wordpress-suite' ),
            ];
        }
        return $schedules;
    }

    /**
     * Handle a post save event.
     *
     * @param int $post_id Post identifier.
     * @return void
     */
    public static function handle_save_post(int $post_id): void {
        if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
            return;
        }
        $urls = self::determine_urls($post_id);
        self::enqueue_urls($urls);
    }

    /**
     * Handle a theme switch.
     *
     * @return void
     */
    public static function handle_switch_theme(): void {
        $urls = self::determine_urls();
        self::enqueue_urls($urls);
    }

    /**
     * Determine URLs affected by a change.
     *
     * @param int $post_id Optional post identifier.
     * @return array<string>
     */
    private static function determine_urls(int $post_id = 0): array {
        $urls = [];
        if ($post_id > 0) {
            $permalink = \get_permalink($post_id);
            if (is_string($permalink) && $permalink !== '') {
                $urls[] = $permalink;
            }
        }
        $home = \home_url('/');
        if (is_string($home) && $home !== '') {
            $urls[] = $home;
        }
        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Enqueue snapshot and critical jobs for a list of URLs.
     *
     * @param array $urls URLs to enqueue.
     * @return void
     */
    private static function enqueue_urls(array $urls): void {
        $queue = self::get_instance();
        foreach ($urls as $url) {
            $queue->enqueue('snapshot', $url);
            $queue->enqueue('critical', $url);
        }
    }

    /**
     * Enqueue a job.
     *
     * @param string $type    Job type: snapshot|purge|critical.
     * @param mixed  $payload Associated payload.
     * @return void
     */
    public function enqueue(string $type, $payload): void {
        if (!in_array($type, [ 'snapshot', 'purge', 'critical' ], true)) {
            return;
        }
        $queue = \get_option(self::OPTION, []);
        if (!is_array($queue)) {
            $queue = [];
        }
        $queue[] = [ 'type' => $type, 'payload' => $payload ];
        \update_option(self::OPTION, $queue, false);

        $status           = \get_option('ae_css_job_status', []);
        $status[$type]    = [ 'status' => 'queued', 'message' => '' ];
        \update_option('ae_css_job_status', $status, false);

        if (!\wp_next_scheduled('ae_css_queue_runner')) {
            \wp_schedule_event(time(), 'ae_css_five_minutes', 'ae_css_queue_runner');
        }
    }

    /**
     * Run the next job in the queue.
     *
     * @return void
     */
    public function run_next(): void {
        $queue = \get_option(self::OPTION, []);
        if (!is_array($queue) || empty($queue)) {
            return;
        }
        $job = array_shift($queue);
        \update_option(self::OPTION, $queue, false);

        $optimizer = AE_CSS_Optimizer::get_instance();
        $type      = $job['type'] ?? '';
        $payload   = $job['payload'] ?? null;

        try {
            switch ($type) {
                case 'snapshot':
                    if (is_array($payload)) {
                        $css      = $payload['css'] ?? [];
                        $html     = $payload['html'] ?? [];
                        $safelist = $payload['safelist'] ?? [];
                        AE_CSS_Optimizer::purgecss_analyze($css, $html, $safelist);
                    }
                    break;
                case 'purge':
                    if (is_string($payload)) {
                        $optimizer->cron_run_purgecss($payload);
                    }
                    break;
                case 'critical':
                    if (is_array($payload)) {
                        $optimizer->process_critical_job($payload);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            // Silently ignore.
        }

        if (!empty($queue)) {
            \wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'ae_css_queue_runner');
        }
    }
}
