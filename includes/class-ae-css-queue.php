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
        if (!\wp_next_scheduled('ae_css_queue_runner')) {
            \wp_schedule_event(time(), 'ae_css_queue_5min', 'ae_css_queue_runner');
        }
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
        if (!isset($schedules['ae_css_queue_5min'])) {
            $schedules['ae_css_queue_5min'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every Five Minutes', 'gm2-wordpress-suite' ),
            ];
        }
        return $schedules;
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

        if (!\wp_next_scheduled('ae_css_queue_runner')) {
            \wp_schedule_event(time(), 'ae_css_queue_5min', 'ae_css_queue_runner');
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
                    $optimizer->process_queue();
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

AE_CSS_Queue::bootstrap();
