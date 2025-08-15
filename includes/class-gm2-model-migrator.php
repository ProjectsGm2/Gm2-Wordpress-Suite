<?php
namespace Gm2;

/**
 * Helper for running model field migrations with rollback support.
 */
class Gm2_Model_Migrator {
    /**
     * Post type being migrated.
     *
     * @var string
     */
    protected $post_type;

    /**
     * Stack of rollback operations.
     *
     * @var array<callable>
     */
    protected $rollbacks = [];

    /**
     * Constructor.
     *
     * @param string $post_type Post type slug.
     */
    public function __construct( string $post_type ) {
        $this->post_type = $post_type;
    }

    /**
     * Add a meta field to all posts.
     *
     * @param string      $field   Meta key to add.
     * @param mixed       $default Default value.
     * @return void
     */
    public function add_field( string $field, $default = '' ) : void {
        $posts = get_posts( [
            'post_type'      => $this->post_type,
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ] );
        foreach ( $posts as $id ) {
            $prev = get_post_meta( $id, $field, true );
            if ( '' === $prev ) {
                update_post_meta( $id, $field, $default );
                $this->rollbacks[] = function() use ( $id, $field ) {
                    delete_post_meta( $id, $field );
                };
            }
        }
    }

    /**
     * Rename a meta field, copying data.
     *
     * @param string $old Old meta key.
     * @param string $new New meta key.
     * @return void
     */
    public function rename_field( string $old, string $new ) : void {
        $posts = get_posts( [
            'post_type'      => $this->post_type,
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ] );
        foreach ( $posts as $id ) {
            $value = get_post_meta( $id, $old, true );
            update_post_meta( $id, $new, $value );
            delete_post_meta( $id, $old );
            $this->rollbacks[] = function() use ( $id, $old, $new, $value ) {
                update_post_meta( $id, $old, $value );
                delete_post_meta( $id, $new );
            };
        }
    }

    /**
     * Deprecate a meta field by deleting it.
     *
     * @param string $field Meta key to remove.
     * @return void
     */
    public function deprecate_field( string $field ) : void {
        $posts = get_posts( [
            'post_type'      => $this->post_type,
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ] );
        foreach ( $posts as $id ) {
            $value = get_post_meta( $id, $field, true );
            delete_post_meta( $id, $field );
            $this->rollbacks[] = function() use ( $id, $field, $value ) {
                update_post_meta( $id, $field, $value );
            };
        }
    }

    /**
     * Execute rollback callbacks in reverse order.
     *
     * @return void
     */
    public function rollback() : void {
        foreach ( array_reverse( $this->rollbacks ) as $fn ) {
            try {
                $fn();
            } catch ( \Throwable $e ) {
                // Ignore rollback failures.
            }
        }
    }
}

/**
 * Registered model migrations.
 *
 * @var array
 */
$GLOBALS['gm2_model_migrations'] = $GLOBALS['gm2_model_migrations'] ?? [];

/**
 * Register a migration callback for a model and version.
 *
 * @param string   $model    Model slug.
 * @param int      $version  Target version.
 * @param callable $callback Migration callback.
 * @return void
 */
function gm2_register_model_migration( string $model, int $version, callable $callback ) : void {
    if ( ! isset( $GLOBALS['gm2_model_migrations'][ $model ] ) ) {
        $GLOBALS['gm2_model_migrations'][ $model ] = [];
    }
    $GLOBALS['gm2_model_migrations'][ $model ][ $version ] = $callback;
}

/**
 * Run pending migrations for a model.
 *
 * @param string $model Model slug.
 * @param int    $from  Current version.
 * @param int    $to    Target version.
 * @return void
 */
function gm2_run_model_migrations( string $model, int $from, int $to ) : void {
    for ( $v = $from + 1; $v <= $to; $v++ ) {
        if ( isset( $GLOBALS['gm2_model_migrations'][ $model ][ $v ] ) ) {
            $migrator = new Gm2_Model_Migrator( $model );
            try {
                call_user_func( $GLOBALS['gm2_model_migrations'][ $model ][ $v ], $migrator );
            } catch ( \Throwable $e ) {
                $migrator->rollback();
                throw $e;
            }
        }
    }
}
