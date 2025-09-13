<?php
namespace Plugin\CLS\Fonts;

if (!defined('ABSPATH')) {
    exit;
}

$critical_fonts    = [];
$needs_googleapis   = false;
$needs_gstatic      = false;

function register(): void {
    if (is_admin() || false === apply_filters('plugin_cls_fonts_enabled', true)) {
        return;
    }
    add_action('init', __NAMESPACE__ . '\\init');
    add_action('wp_head', __NAMESPACE__ . '\\print_links', 20);
    add_action('wp_head', __NAMESPACE__ . '\\inline_script', 1);
}

function init(): void {
    global $critical_fonts;
    $stored = get_option('plugin_cls_critical_fonts', []);
    if (is_array($stored)) {
        $critical_fonts = $stored;
    } else {
        $critical_fonts = [];
    }
    get_discovered_fonts();
}

function get_discovered_fonts(): array {
    $fonts = get_transient('plugin_cls_font_faces');
    if ($fonts !== false && is_array($fonts)) {
        return $fonts;
    }
    $fonts = parse_stylesheets();
    set_transient('plugin_cls_font_faces', $fonts, DAY_IN_SECONDS);
    return $fonts;
}

function parse_stylesheets(): array {
    global $wp_styles;
    $fonts = [];
    if (!isset($wp_styles)) {
        $wp_styles = wp_styles();
    }
    $home = wp_parse_url(home_url());
    $handles = array_unique(array_merge($wp_styles->queue ?? [], array_keys($wp_styles->registered ?? [])));
    foreach ($handles as $handle) {
        $style = $wp_styles->registered[$handle] ?? null;
        if (!$style || empty($style->src)) {
            continue;
        }
        $stylesheet_url = resolve_url($style->src, $wp_styles->base_url ?? '');
        $parsed = wp_parse_url($stylesheet_url);
        if (!empty($parsed['host']) && strtolower($parsed['host']) !== strtolower($home['host'] ?? '')) {
            detect_google_fonts($stylesheet_url);
            continue;
        }
        $path = ABSPATH . ltrim($parsed['path'] ?? '', '/');
        if (!file_exists($path) || !is_readable($path)) {
            continue;
        }
        $css = file_get_contents($path);
        if ($css === false) {
            continue;
        }
        if (preg_match_all('/@font-face\s*{[^}]*}/i', $css, $matches)) {
            foreach ($matches[0] as $block) {
                if (!preg_match('/src:[^;]*url\(([^)]+\.woff2)[^\)]*\)/i', $block, $um)) {
                    continue;
                }
                $font_url = trim($um[1], "'\" ");
                $font_url = resolve_url($font_url, dirname($stylesheet_url) . '/');
                $weight = '400';
                if (preg_match('/font-weight:\s*([^;]+);/i', $block, $wm)) {
                    $weight = trim($wm[1]);
                }
                $style_val = 'normal';
                if (preg_match('/font-style:\s*([^;]+);/i', $block, $sm)) {
                    $style_val = trim($sm[1]);
                }
                $family = '';
                if (preg_match('/font-family:\s*([^;]+);/i', $block, $fm)) {
                    $family = trim($fm[1], "'\" ");
                }
                detect_google_fonts($font_url);
                $fonts[$font_url] = [
                    'url'    => $font_url,
                    'weight' => $weight,
                    'style'  => $style_val,
                    'family' => $family,
                ];
            }
        }
    }
    return array_values($fonts);
}

function resolve_url(string $url, string $base): string {
    if (strpos($url, '//') === 0) {
        return (is_ssl() ? 'https:' : 'http:') . $url;
    }
    if (preg_match('#^https?://#', $url)) {
        return $url;
    }
    if (strpos($url, '/') === 0) {
        return home_url($url);
    }
    return trailingslashit($base) . ltrim($url, './');
}

function detect_google_fonts(string $url): void {
    global $needs_googleapis, $needs_gstatic;
    if (strpos($url, 'fonts.googleapis.com') !== false) {
        $needs_googleapis = true;
        $needs_gstatic    = true;
    }
    if (strpos($url, 'fonts.gstatic.com') !== false) {
        $needs_gstatic = true;
    }
}

function inline_script(): void {
    if (false === apply_filters('plugin_cls_fonts_report_enabled', true)) {
        return;
    }
    $nonce        = wp_create_nonce('wp_rest');
    $theme        = wp_get_theme();
    $cache_key    = 'cls-fonts-' . md5($theme->get('Version'));
    $script = '(function(){if(!("fonts" in document))return;const key="' . esc_js($cache_key) . '";if(localStorage.getItem(key))return;const used=new Map;const start=performance.now();function scan(){const sels=["h1","h2","h3","nav","header",".hero"];for(const sel of sels){document.querySelectorAll(sel).forEach(el=>{const cs=getComputedStyle(el);let fam=(cs.fontFamily||"").split(",")[0].replace(/[\"\']/g,"").trim();if(!fam)return;const weight=cs.fontWeight||"400";const style=cs.fontStyle||"normal";const id=fam+"|"+weight+"|"+style;if(used.has(id))return;if(document.fonts.check(style+" "+weight+" 1em "+fam)){used.set(id,{family:fam,weight:weight,style:style});}});}if(performance.now()-start<1200){requestAnimationFrame(scan);}else{report();}}async function report(){await document.fonts.ready;if(!used.size)return;fetch("/wp-json/gm2/v1/above-fold-fonts",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":"' . esc_js($nonce) . '"},body:JSON.stringify({fonts:Array.from(used.values())})}).then(()=>{localStorage.setItem(key,"1");}).catch(()=>{});}scan();})();';
    wp_print_inline_script_tag($script);
}

function print_links(): void {
    global $critical_fonts, $needs_googleapis, $needs_gstatic;
    $preloaded = [];
    global $wp_styles;
    if (isset($wp_styles)) {
        foreach ((array) $wp_styles->queue as $handle) {
            $style = $wp_styles->registered[$handle] ?? null;
            if ($style && !empty($style->src) && !empty($style->extra['as']) && 'font' === $style->extra['as']) {
                $preloaded[] = resolve_url($style->src, $wp_styles->base_url ?? '');
            }
        }
    }
    foreach (apply_filters('wp_preload_resources', []) as $res) {
        if (!empty($res['as']) && 'font' === $res['as'] && !empty($res['href'])) {
            $preloaded[] = $res['href'];
        }
    }
    $home_host     = wp_parse_url(home_url(), PHP_URL_HOST);
    $allowed_hosts = [$home_host, 'fonts.gstatic.com'];
    $printed       = 0;
    foreach ($critical_fonts as $font) {
        if ($printed >= 3) {
            break;
        }
        if (is_array($font)) {
            $url = $font['url'] ?? $font['href'] ?? '';
        } else {
            $url = (string) $font;
        }
        if ($url === '' || strpos($url, '.woff2') === false) {
            continue;
        }
        $url  = resolve_url($url, home_url('/'));
        if (in_array($url, $preloaded, true)) {
            continue;
        }
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!in_array($host, $allowed_hosts, true)) {
            continue;
        }
        echo '<link rel="preload" href="' . esc_url($url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        $printed++;
    }
    if ($needs_googleapis) {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    }
    if ($needs_gstatic) {
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }
}
