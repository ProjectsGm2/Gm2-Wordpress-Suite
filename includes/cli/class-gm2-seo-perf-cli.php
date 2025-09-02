<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class Gm2_SEO_Perf_CLI extends \WP_CLI_Command {
    /**
     * Run an SEO and performance audit using AI.
     */
    public function audit( $args, $assoc_args ) {
        $prompt = 'Prompt A: Audit this WordPress site for SEO and performance issues. Return JSON recommendations.';
        if ( ! function_exists( '\\gm2_ai_send_prompt' ) ) {
            \WP_CLI::error( __( 'AI utilities not available.', 'gm2-wordpress-suite' ), 1 );
        }
        $response = \gm2_ai_send_prompt( $prompt );
        if ( is_wp_error( $response ) ) {
            \WP_CLI::error( $response->get_error_message(), $response->get_error_code() ?: 1 );
        }
        \WP_CLI::line( wp_json_encode( $response ) );
    }

    /**
     * Apply caching headers to the .htaccess file.
     */
    public function apply_htaccess( $args, $assoc_args ) {
        $result = Gm2_Cache_Headers_Apache::maybe_apply();
        switch ( $result['status'] ?? '' ) {
            case 'written':
                \WP_CLI::success( __( 'Cache headers written to .htaccess.', 'gm2-wordpress-suite' ) );
                break;
            case 'unsupported':
                \WP_CLI::error( __( 'Server does not appear to be Apache or LiteSpeed.', 'gm2-wordpress-suite' ), 1 );
                break;
            case 'already_handled':
                \WP_CLI::error( __( 'CDN already sets cache headers.', 'gm2-wordpress-suite' ), 2 );
                break;
            case 'not_writable':
                \WP_CLI::error( __( 'The .htaccess file or directory is not writable.', 'gm2-wordpress-suite' ), 3 );
                break;
            default:
                \WP_CLI::error( __( 'Unknown result applying .htaccess rules.', 'gm2-wordpress-suite' ), 4 );
        }
    }

    /**
     * Generate Nginx caching headers configuration.
     */
    public function generate_nginx( $args, $assoc_args ) {
        $result = Gm2_Cache_Headers_Nginx::maybe_apply();
        switch ( $result['status'] ?? '' ) {
            case 'written':
                $file = $result['file'] ?? Gm2_Cache_Headers_Nginx::get_file_path();
                \WP_CLI::success( $file );
                break;
            case 'unsupported':
                \WP_CLI::error( __( 'Server does not appear to be Nginx.', 'gm2-wordpress-suite' ), 1 );
                break;
            case 'already_handled':
                \WP_CLI::error( __( 'CDN already sets cache headers.', 'gm2-wordpress-suite' ), 2 );
                break;
            case 'not_writable':
                $file = $result['file'] ?? Gm2_Cache_Headers_Nginx::get_file_path();
                \WP_CLI::error( sprintf( __( 'Directory not writable: %s', 'gm2-wordpress-suite' ), dirname( $file ) ), 3 );
                break;
            default:
                \WP_CLI::error( __( 'Unknown result generating Nginx config.', 'gm2-wordpress-suite' ), 4 );
        }
    }

    /**
     * Remove caching header markers and generated Nginx file.
     */
    public function clear_markers( $args, $assoc_args ) {
        Gm2_Cache_Headers_Apache::remove_rules();
        $file = Gm2_Cache_Headers_Nginx::get_file_path();
        if ( file_exists( $file ) && ! @unlink( $file ) ) {
            \WP_CLI::error( __( 'Could not remove Nginx config file.', 'gm2-wordpress-suite' ), 1 );
        }
        \WP_CLI::success( __( 'Cache header markers cleared.', 'gm2-wordpress-suite' ) );
    }
}

\WP_CLI::add_command( 'seo-perf', __NAMESPACE__ . '\\Gm2_SEO_Perf_CLI' );
