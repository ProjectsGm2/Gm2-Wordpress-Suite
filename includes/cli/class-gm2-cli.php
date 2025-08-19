<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class Gm2_CLI extends \WP_CLI_Command {
    /**
     * Manage the plugin sitemap.
     *
     * ## SUBCOMMANDS
     *
     * generate  Generate the XML sitemap
     */
    public function sitemap( $args, $assoc_args ) {
        $sub = $args[0] ?? '';
        if ( $sub !== 'generate' ) {
            \WP_CLI::error( 'Usage: wp gm2 sitemap generate' );
        }

        $result = \gm2_generate_sitemap();
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( 'Sitemap generated.' );
    }

    /**
     * Clear stored AI data and logs.
     *
     * ## SUBCOMMANDS
     *
     * clear  Remove cached AI research and logs
     */
    public function ai( $args, $assoc_args ) {
        $sub = $args[0] ?? '';
        if ( $sub !== 'clear' ) {
            \WP_CLI::error( 'Usage: wp gm2 ai clear' );
        }

        if ( ! function_exists( '\gm2_ai_clear' ) ) {
            \WP_CLI::error( 'gm2_ai_clear() function not found.' );
        }
        $result = \gm2_ai_clear();
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( 'AI data cleared.' );
    }

    /**
     * Manage abandoned carts data.
     *
     * ## SUBCOMMANDS
     *
     * migrate  Move recovered carts into wc_ac_recovered table
     * process  Run the abandonment processor immediately
     */
    public function ac( $args, $assoc_args ) {
        $sub = $args[0] ?? '';
        if ( $sub === 'migrate' ) {
            $ac    = new Gm2_Abandoned_Carts();
            $count = $ac->migrate_recovered_carts();
            \WP_CLI::success( sprintf( '%d carts migrated.', $count ) );
            return;
        }

        if ( $sub === 'process' ) {
            Gm2_Abandoned_Carts::cron_mark_abandoned();
            \WP_CLI::success( 'Abandoned carts processed.' );
            return;
        }

        \WP_CLI::error( 'Usage: wp gm2 ac <migrate|process>' );
    }

    /**
     * Scaffold theme assets such as Twig/Blade templates or theme.json.
     *
     * ## SUBCOMMANDS
     *
     * twig <slug>       Create a Twig template under templates/.
     * blade <slug>      Create a Blade template under resources/views/.
     * theme-json        Create a basic theme.json if one does not exist.
     */
    public function scaffold( $args, $assoc_args ) {
        $type = $args[0] ?? '';
        $slug = $args[1] ?? 'example';
        $theme_dir = get_stylesheet_directory();

        switch ( $type ) {
            case 'twig':
                $path = trailingslashit( $theme_dir ) . 'templates/' . $slug . '.twig';
                if ( ! file_exists( dirname( $path ) ) ) {
                    wp_mkdir_p( dirname( $path ) );
                }
                file_put_contents( $path, "{{ gm2_field('example') }}\n" );
                \WP_CLI::success( 'Twig template created at ' . $path );
                break;
            case 'blade':
                $path = trailingslashit( $theme_dir ) . 'resources/views/' . $slug . '.blade.php';
                if ( ! file_exists( dirname( $path ) ) ) {
                    wp_mkdir_p( dirname( $path ) );
                }
                file_put_contents( $path, "{{ gm2_field('example') }}\n" );
                \WP_CLI::success( 'Blade template created at ' . $path );
                break;
            case 'theme-json':
            case 'theme':
                $path = trailingslashit( $theme_dir ) . 'theme.json';
                if ( ! file_exists( $path ) ) {
                    $contents = json_encode( [ '$schema' => 'https://schemas.wp.org/wp/6.4/theme.json' ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                    file_put_contents( $path, $contents );
                    \WP_CLI::success( 'theme.json created at ' . $path );
                } else {
                    \WP_CLI::success( 'theme.json already exists at ' . $path );
                }
                $label = ucwords( str_replace( '-', ' ', $slug ) );
                $sample = [
                    'customTemplates' => [
                        [ 'name' => 'single-' . $slug, 'title' => 'Single ' . $label ],
                        [ 'name' => 'archive-' . $slug, 'title' => 'Archive ' . $label ],
                    ],
                    'patterns' => [ 'gm2/' . $slug ],
                ];
                \WP_CLI::line( 'Hint: add the following to theme.json to reference custom templates and patterns:' );
                \WP_CLI::line( json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
                break;
            default:
                \WP_CLI::error( 'Usage: wp gm2 scaffold <twig|blade|theme-json> <slug>' );
        }
    }
}

\WP_CLI::add_command( 'gm2', __NAMESPACE__ . '\\Gm2_CLI' );
