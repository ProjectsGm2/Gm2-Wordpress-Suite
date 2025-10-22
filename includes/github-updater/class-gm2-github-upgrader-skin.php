<?php
namespace Gm2\Updater;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\Automatic_Upgrader_Skin')) {
    $upgrader_path = ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    if (file_exists($upgrader_path)) {
        require_once $upgrader_path;
    }
}

if (!class_exists('\\Automatic_Upgrader_Skin')) {
    return;
}

/**
 * Custom upgrader skin used for AJAX-driven updates.
 */
class Gm2_GitHub_Upgrader_Skin extends \Automatic_Upgrader_Skin {
    /**
     * @var array<int, string>
     */
    protected $log = [];

    /**
     * @var array<int, string>
     */
    protected $error_messages = [];

    /**
     * Record upgrader feedback while keeping the parent behaviour intact.
     *
     * @param string $string Message key or text.
     * @param mixed  ...$args Optional sprintf arguments.
     */
    public function feedback($string, ...$args) { // phpcs:ignore Squiz.Commenting.FunctionComment.ScalarTypeHintMissing
        $formatted = $this->format_string($string, $args);
        if ($formatted !== '') {
            $this->log[] = $formatted;
        }

        // Maintain default behaviour (including printing the message when appropriate).
        parent::feedback(...func_get_args());
    }

    /**
     * Capture upgrader errors in an inspectable form.
     *
     * @param string|WP_Error $errors Error code or WP_Error instance.
     */
    public function error($errors) { // phpcs:ignore Squiz.Commenting.FunctionComment.ScalarTypeHintMissing
        if ($errors instanceof WP_Error) {
            foreach ($errors->get_error_messages() as $message) {
                $message = $this->sanitize_message($message);
                if ($message !== '') {
                    $this->error_messages[] = $message;
                }
            }
        } elseif (is_string($errors)) {
            $message = $this->format_string($errors, []);
            if ($message !== '') {
                $this->error_messages[] = $message;
            }
        }

        parent::error($errors);
    }

    /**
     * Retrieve all captured log messages.
     *
     * @return array<int, string>
     */
    public function get_log() {
        if (empty($this->log)) {
            return [];
        }

        return array_values(array_unique($this->log));
    }

    /**
     * Return a WP_Error containing all captured error messages.
     *
     * @return WP_Error
     */
    public function get_errors() {
        $error = new WP_Error();

        if (empty($this->error_messages)) {
            return $error;
        }

        foreach ($this->error_messages as $message) {
            $error->add('gm2_github_updater_upgrade', $message);
        }

        return $error;
    }

    /**
     * Convert a message key and sprintf args into a plain string.
     *
     * @param string   $string Message key or text.
     * @param string[] $args   Arguments for vsprintf.
     *
     * @return string
     */
    protected function format_string($string, array $args) {
        if (isset($this->upgrader) && isset($this->upgrader->strings[$string])) {
            $string = $this->upgrader->strings[$string];
        }

        if (!empty($args)) {
            $formatted = @vsprintf($string, $args); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if (is_string($formatted)) {
                $string = $formatted;
            }
        }

        return $this->sanitize_message($string);
    }

    /**
     * Sanitize a message prior to storing it.
     *
     * @param string $message Raw message.
     *
     * @return string
     */
    protected function sanitize_message($message) {
        $message = (string) $message;
        $message = \wp_strip_all_tags($message);
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = preg_replace('/\s+/', ' ', $message);

        if (!is_string($message)) {
            return '';
        }

        return trim($message);
    }
}
