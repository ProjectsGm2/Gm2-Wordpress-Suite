<?php
if (!defined('ABSPATH')) {
    exit;
}

$log_file = WP_CONTENT_DIR . '/ae-seo/logs/js-optimizer.log';
if (!file_exists($log_file)) {
    $alt = GM2_PLUGIN_DIR . 'js-optimizer.log';
    if (file_exists($alt)) {
        $log_file = $alt;
    }
}

$rows = [];
$large = [];
if (is_readable($log_file)) {
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines);
    $seen = [];
    foreach ($lines as $line) {
        if (preg_match('/\[(.*?)\]\s+(https?:\/\/\S+)\s+registered=(\d+)\s+enqueued=(\d+)\s+dequeued=(\d+)\s+lazy=(\d+)\s+jquery=([YN])\s+polyfills=(\d+)/', $line, $m)) {
            $url = $m[2];
            if (isset($seen[$url])) {
                continue;
            }
            $rows[$url] = [
                'registered' => (int) $m[3],
                'enqueued'   => (int) $m[4],
                'dequeued'   => (int) $m[5],
                'lazy'       => (int) $m[6],
                'jquery'     => $m[7],
                'polyfills'  => (int) $m[8],
            ];
            $seen[$url] = true;
            continue;
        }
        if (preg_match('/large\s+(\S+)\s+size=(\d+)/', $line, $m)) {
            $handle = $m[1];
            if (!isset($large[$handle])) {
                $large[$handle] = (int) $m[2];
            }
        }
    }
    $rows = array_reverse($rows);
}

$hints = [];
$lazy_enabled = get_option('ae_js_lazy_analytics', '0') === '1';
foreach ($rows as $url => $m) {
    if ($m['lazy'] === 0 && ! $lazy_enabled) {
        $hints['lazy'] = __('Consider enabling lazy-load for Analytics.', 'gm2-wordpress-suite');
    }
    if ($m['jquery'] === 'Y' && $m['enqueued'] <= 1) {
        $hints['jquery'] = __('jQuery loaded but no dependents found.', 'gm2-wordpress-suite');
    }
    if ($m['polyfills'] > 0) {
        $hints['polyfills'] = __('Polyfills detected. Review need for legacy browser support.', 'gm2-wordpress-suite');
    }
}

if ($large) {
    foreach ($large as $handle => $bytes) {
        $kb = $bytes / 1024;
        $hints['size-' . $handle] = sprintf(
            /* translators: 1: script handle, 2: size in KB */
            __('%1$s is %2$.1f KB. Consider dequeuing or lazy loading.', 'gm2-wordpress-suite'),
            $handle,
            $kb
        );
    }
}

echo '<div class="wrap"><h1>' . esc_html__( 'JS Performance Report', 'gm2-wordpress-suite' ) . '</h1>';
if ($hints) {
    echo '<div class="notice notice-warning"><ul>';
    foreach ($hints as $hint) {
        echo '<li>' . esc_html($hint) . '</li>';
    }
    echo '</ul></div>';
}

if ($rows) {
    echo '<table class="widefat fixed"><thead><tr>';
    echo '<th>' . esc_html__( 'URL', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Registered', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Enqueued', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Dequeued', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Lazy', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'jQuery', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Polyfills', 'gm2-wordpress-suite' ) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $url => $m) {
        $lazy = $m['lazy'];
        $jquery = $m['jquery'];
        $poly = $m['polyfills'];
        if ($m['lazy'] === 0 && ! $lazy_enabled) {
            $lazy = '<span style="color:#b32d2e;font-weight:bold">' . $lazy . '</span>';
        }
        if ($m['jquery'] === 'Y' && $m['enqueued'] <= 1) {
            $jquery = '<span style="color:#b32d2e;font-weight:bold">' . $jquery . '</span>';
        }
        if ($m['polyfills'] > 0) {
            $poly = '<span style="color:#b32d2e;font-weight:bold">' . $poly . '</span>';
        }
        echo '<tr>';
        echo '<td>' . esc_url($url) . '</td>';
        echo '<td>' . (int) $m['registered'] . '</td>';
        echo '<td>' . (int) $m['enqueued'] . '</td>';
        echo '<td>' . (int) $m['dequeued'] . '</td>';
        echo '<td>' . $lazy . '</td>';
        echo '<td>' . esc_html($jquery) . '</td>';
        echo '<td>' . $poly . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p>' . esc_html__( 'No smoke test results found.', 'gm2-wordpress-suite' ) . '</p>';
}

echo '</div>';
