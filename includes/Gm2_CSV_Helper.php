<?php
namespace Gm2;
if (!defined('ABSPATH')) {
    exit;
}
class Gm2_CSV_Helper {
    public static function output(array $rows, $filename = 'export.csv') {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }
}
