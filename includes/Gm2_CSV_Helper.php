<?php
namespace Gm2;
if (!defined('ABSPATH')) {
    exit;
}
class Gm2_CSV_Helper {
    /**
     * Output an array of rows as a CSV file.
     *
     * @param array  $rows     Array of rows to output.
     * @param string $filename Optional. Name of the file to download. Default 'export.csv'.
     */
    public static function output(array $rows, $filename = 'export.csv') {
        $filename = sanitize_file_name($filename);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');

        if (false === $out) {
            // Bail out if the output stream cannot be opened. wp_die() will display an error
            // message in a WordPress context; otherwise we return quietly.
            if (function_exists('wp_die')) {
                wp_die(esc_html__('Unable to open output stream', 'gm2-wordpress-suite'));
            }

            return;
        }

        foreach ($rows as $row) {
            fputcsv($out, $row);
        }

        fclose($out);
    }
}
