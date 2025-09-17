<?php
namespace Gm2;

use Gm2\Presets\PresetManager;

use function __;
use function apply_filters;
use function is_wp_error;
use function sanitize_key;
use function sprintf;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Manage bundled content presets.
 */
class Gm2_Presets_CLI extends \WP_CLI_Command {
    /**
     * Retrieve the preset manager instance.
     */
    private function getPresetManager(): PresetManager {
        $manager = apply_filters( 'gm2/presets/manager', null );
        if ( ! $manager instanceof PresetManager ) {
            \WP_CLI::error( __( 'Preset manager is unavailable.', 'gm2-wordpress-suite' ) );
        }

        return $manager;
    }

    /**
     * List available presets bundled with the plugin.
     */
    public function list_( $args, $assoc_args ) {
        $manager = $this->getPresetManager();
        $presets = $manager->getList();

        if ( empty( $presets ) ) {
            \WP_CLI::line( __( 'No presets available.', 'gm2-wordpress-suite' ) );
            return;
        }

        foreach ( $presets as $slug => $meta ) {
            $label       = $meta['label'] ?? '';
            $description = $meta['description'] ?? '';

            if ( $label !== '' && $description !== '' ) {
                \WP_CLI::line( sprintf( '%s: %s - %s', $slug, $label, $description ) );
            } elseif ( $label !== '' ) {
                \WP_CLI::line( sprintf( '%s: %s', $slug, $label ) );
            } elseif ( $description !== '' ) {
                \WP_CLI::line( sprintf( '%s: %s', $slug, $description ) );
            } else {
                \WP_CLI::line( $slug );
            }
        }
    }

    /**
     * Apply a preset blueprint to the current site.
     *
     * ## OPTIONS
     *
     * <preset>
     * : The preset slug to apply.
     *
     * [--force]
     * : Overwrite existing content definitions.
     */
    public function apply( $args, $assoc_args ) {
        $raw  = $args[0] ?? '';
        $slug = $raw !== '' ? sanitize_key( $raw ) : '';

        if ( $slug === '' ) {
            \WP_CLI::error( __( 'Please provide a preset slug.', 'gm2-wordpress-suite' ) );
        }

        $manager = $this->getPresetManager();
        $force   = ! empty( $assoc_args['force'] );

        $result = $manager->apply( $slug, $force );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }

        \WP_CLI::success( sprintf( __( 'Preset "%s" applied successfully.', 'gm2-wordpress-suite' ), $slug ) );
    }
}

\WP_CLI::add_command( 'gm2 presets', __NAMESPACE__ . '\\Gm2_Presets_CLI' );
