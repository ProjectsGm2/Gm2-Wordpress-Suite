<?php
namespace Gm2\Font_Performance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility methods for working with @font-face blocks.
 */
final class Font_CSS_Util {
    /**
     * Parse @font-face rules and normalise them.
     *
     * @param string $css Raw CSS to process.
     * @return string Processed CSS.
     */
    public static function process(string $css): string {
        $opts           = Font_Performance::get_settings();
        $limit_variants = !empty($opts['limit_variants']);
        $cache_headers  = !empty($opts['cache_headers']);
        $allowed        = [];
        if ($limit_variants && !empty($opts['variant_suggestions']) && is_array($opts['variant_suggestions'])) {
            $allowed = array_flip(array_map('strtolower', $opts['variant_suggestions']));
        }

        return (string) preg_replace_callback(
            '/@font-face\s*{[^}]*}/i',
            static function (array $m) use ($limit_variants, $allowed, $cache_headers): string {
                $block = $m[0];

                $weight = '400';
                if (preg_match('/font-weight\s*:\s*(\d{3}|bold|normal)/i', $block, $w)) {
                    $val = strtolower($w[1]);
                    $weight = match ($val) {
                        'bold' => '700',
                        'normal' => '400',
                        default => $val,
                    };
                }

                $style = 'normal';
                if (preg_match('/font-style\s*:\s*(italic|oblique|normal)/i', $block, $s)) {
                    $style = strtolower($s[1]);
                }

                $variant_key = strtolower($weight . ' ' . $style);
                if ($limit_variants && $allowed && !isset($allowed[$variant_key])) {
                    return '';
                }

                if (stripos($block, 'font-display') === false) {
                    $block = preg_replace('/@font-face\s*{/', '@font-face{font-display:swap;', $block, 1);
                }

                if ($cache_headers) {
                    $block = preg_replace_callback(
                        '/url\(([^)]+)\)/i',
                        static function (array $url_match): string {
                            $url = trim($url_match[1], "'\" ");
                            $url = Font_Performance::rewrite_font_src($url);
                            return 'url(' . $url . ')';
                        },
                        $block
                    );
                }

                return $block;
            },
            $css
        );
    }
}
