<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class Font_Performance_CLI extends \WP_CLI_Command {
    private const OPTION_KEY = 'gm2seo_fonts';

    /** Ensure module exists and is enabled. */
    private function get_opts(): array {
        if ( ! class_exists( '\\Gm2\\Font_Performance\\Font_Performance' ) ) {
            \WP_CLI::error( __( 'Font Performance module not available.', 'gm2-wordpress-suite' ), 1 );
        }
        $opts = \Gm2\Font_Performance\Font_Performance::get_settings();
        if ( empty( $opts['enabled'] ) ) {
            \WP_CLI::error( __( 'Font Performance module is disabled.', 'gm2-wordpress-suite' ), 2 );
        }
        return $opts;
    }

    /** Persist updated options. */
    private function save_opts( array $opts ): void {
        $fn = is_multisite() ? 'update_site_option' : 'update_option';
        $fn( self::OPTION_KEY, $opts, false );
    }

    /**
     * Audit font usage and potential savings.
     */
    public function audit( $args, $assoc_args ) {
        $opts     = $this->get_opts();
        $variants = \Gm2\Font_Performance\Font_Performance::detect_font_variants();
        $sizes    = \Gm2\Font_Performance\Font_Performance::compute_variant_savings( $opts['variant_suggestions'] ?? [] );
        \WP_CLI::line( sprintf( __( 'Variants detected: %d', 'gm2-wordpress-suite' ), count( $variants ) ) );
        \WP_CLI::line( sprintf( __( 'Total size: %0.2f KB', 'gm2-wordpress-suite' ), $sizes['total'] / 1024 ) );
        \WP_CLI::line( sprintf( __( 'Allowed size: %0.2f KB', 'gm2-wordpress-suite' ), $sizes['allowed'] / 1024 ) );
        \WP_CLI::line( sprintf( __( 'Potential reduction: %0.2f KB', 'gm2-wordpress-suite' ), $sizes['reduction'] / 1024 ) );
    }

    /**
     * Enable self-hosted fonts and update variants/families.
     */
    public function self_host( $args, $assoc_args ) {
        $opts = $this->get_opts();

        $variants = [];
        if ( ! empty( $assoc_args['variants'] ) ) {
            $parts = array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['variants'] ) ) );
            foreach ( $parts as $weight ) {
                if ( preg_match( '/^\d{3}$/', $weight ) ) {
                    $variants[] = $weight . ' normal';
                } elseif ( preg_match( '/^\d{3}\s+(normal|italic|oblique)$/', $weight ) ) {
                    $variants[] = $weight;
                }
            }
            if ( $variants ) {
                $opts['variant_suggestions'] = array_values( array_unique( $variants ) );
            }
        }

        if ( ! empty( $assoc_args['families'] ) ) {
            $families = array_filter( array_map( 'trim', explode( '|', (string) $assoc_args['families'] ) ) );
            $families = array_map( 'sanitize_text_field', $families );
            if ( $families ) {
                $opts['families'] = array_values( array_unique( $families ) );
            }
        }

        $opts['self_host'] = true;
        $this->save_opts( $opts );

        $sizes = \Gm2\Font_Performance\Font_Performance::compute_variant_savings( $opts['variant_suggestions'] ?? [] );
        \WP_CLI::success( sprintf( __( 'Self-hosting enabled for %d families. Total: %0.2f KB, Allowed: %0.2f KB, Reduction: %0.2f KB', 'gm2-wordpress-suite' ), count( $opts['families'] ), $sizes['total'] / 1024, $sizes['allowed'] / 1024, $sizes['reduction'] / 1024 ) );
    }

    /**
     * Manage font preloads.
     */
    public function preload( $args, $assoc_args ) {
        $opts = $this->get_opts();
        $sub  = $args[0] ?? '';

        if ( $sub === 'add' ) {
            $url = $args[1] ?? '';
            $url = esc_url_raw( $url );
            if ( $url === '' || ! preg_match( '/\.woff2(\?.*)?$/i', $url ) ) {
                \WP_CLI::error( __( 'Please provide a valid WOFF2 URL.', 'gm2-wordpress-suite' ) );
            }
            $preloads = $opts['preload'] ?? [];
            if ( ! in_array( $url, $preloads, true ) ) {
                $preloads[]    = $url;
                $opts['preload'] = array_values( $preloads );
                $this->save_opts( $opts );
            }
            \WP_CLI::success( sprintf( __( 'Preloads: %d', 'gm2-wordpress-suite' ), count( $preloads ) ) );
            return;
        }

        if ( $sub === 'list' ) {
            $preloads = $opts['preload'] ?? [];
            if ( empty( $preloads ) ) {
                \WP_CLI::line( __( 'No preloads defined.', 'gm2-wordpress-suite' ) );
            } else {
                foreach ( $preloads as $url ) {
                    \WP_CLI::line( $url );
                }
                \WP_CLI::line( sprintf( __( 'Total: %d', 'gm2-wordpress-suite' ), count( $preloads ) ) );
            }
            return;
        }

        \WP_CLI::error( __( 'Usage: wp gm2seo fonts preload <add|list> [url]', 'gm2-wordpress-suite' ) );
    }

    /**
     * Restore remote font loading.
     */
    public function restore( $args, $assoc_args ) {
        $opts             = $this->get_opts();
        $opts['self_host'] = false;
        $this->save_opts( $opts );
        \WP_CLI::success( __( 'Remote font loading restored.', 'gm2-wordpress-suite' ) );
    }
}

\WP_CLI::add_command( 'gm2seo fonts', __NAMESPACE__ . '\\Font_Performance_CLI' );
