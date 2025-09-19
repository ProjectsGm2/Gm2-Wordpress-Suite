<?php
if ($this->is_locked()) {
    $this->display_locked_page(esc_html__( 'Relationships', 'gm2-wordpress-suite' ));
    return;
}
if (!$this->can_manage()) {
    wp_die(esc_html__( 'Permission denied', 'gm2-wordpress-suite' ));
}

$config = $this->get_config();
$relationships = $config['relationships'] ?? [];

$objects = [];
$post_types = get_post_types([], 'objects');
foreach ($post_types as $slug => $pt_obj) {
    $label = $pt_obj->labels->singular_name ?? $slug;
    $objects[$slug] = sprintf('%1$s (%2$s)', $label, __( 'Post Type', 'gm2-wordpress-suite' ));
}
$taxonomies = get_taxonomies([], 'objects');
foreach ($taxonomies as $slug => $tax_obj) {
    $label = $tax_obj->labels->singular_name ?? $slug;
    $objects[$slug] = sprintf('%1$s (%2$s)', $label, __( 'Taxonomy', 'gm2-wordpress-suite' ));
}
ksort($objects);

$cardinalities = [
    'one-to-one'   => __( 'One to one', 'gm2-wordpress-suite' ),
    'one-to-many'  => __( 'One to many', 'gm2-wordpress-suite' ),
    'many-to-one'  => __( 'Many to one', 'gm2-wordpress-suite' ),
    'many-to-many' => __( 'Many to many', 'gm2-wordpress-suite' ),
];
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'Relationships', 'gm2-wordpress-suite' ); ?></h1>
    <p><?php echo esc_html__( 'Define how your post types and taxonomies relate to each other. These settings are included when exporting blueprints or applying presets.', 'gm2-wordpress-suite' ); ?></p>

    <div id="gm2-rel-messages" class="notice notice-error" style="display:none;"><p></p></div>

    <table class="widefat fixed" id="gm2-relationships-table">
        <thead>
            <tr>
                <th><?php echo esc_html__( 'Key', 'gm2-wordpress-suite' ); ?></th>
                <th><?php echo esc_html__( 'From', 'gm2-wordpress-suite' ); ?></th>
                <th><?php echo esc_html__( 'To', 'gm2-wordpress-suite' ); ?></th>
                <th><?php echo esc_html__( 'Label', 'gm2-wordpress-suite' ); ?></th>
                <th><?php echo esc_html__( 'Cardinality', 'gm2-wordpress-suite' ); ?></th>
                <th><?php echo esc_html__( 'Actions', 'gm2-wordpress-suite' ); ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <p><button type="button" class="button button-primary" id="gm2-add-relationship"><?php echo esc_html__( 'Add Relationship', 'gm2-wordpress-suite' ); ?></button></p>

    <div id="gm2-relationship-form" style="display:none;">
        <h2><?php echo esc_html__( 'Relationship Details', 'gm2-wordpress-suite' ); ?></h2>
        <input type="hidden" id="gm2-rel-original" />
        <p><label><?php echo esc_html__( 'Relationship Key', 'gm2-wordpress-suite' ); ?><br />
            <input type="text" id="gm2-rel-type" class="regular-text" /></label></p>
        <p><label><?php echo esc_html__( 'From Object', 'gm2-wordpress-suite' ); ?><br />
            <input type="text" id="gm2-rel-from" class="regular-text" list="gm2-rel-objects" /></label></p>
        <p><label><?php echo esc_html__( 'To Object', 'gm2-wordpress-suite' ); ?><br />
            <input type="text" id="gm2-rel-to" class="regular-text" list="gm2-rel-objects" /></label></p>
        <p><label><?php echo esc_html__( 'Label', 'gm2-wordpress-suite' ); ?><br />
            <input type="text" id="gm2-rel-label" class="regular-text" /></label></p>
        <p><label><?php echo esc_html__( 'Reverse Label', 'gm2-wordpress-suite' ); ?><br />
            <input type="text" id="gm2-rel-reverse-label" class="regular-text" /></label></p>
        <p><label><?php echo esc_html__( 'Direction', 'gm2-wordpress-suite' ); ?><br />
            <input type="text" id="gm2-rel-direction" class="regular-text" /></label></p>
        <p><label><?php echo esc_html__( 'Cardinality', 'gm2-wordpress-suite' ); ?><br />
            <select id="gm2-rel-cardinality">
                <option value=""><?php echo esc_html__( 'Select', 'gm2-wordpress-suite' ); ?></option>
                <?php foreach ($cardinalities as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label></p>
        <p><label><?php echo esc_html__( 'Description', 'gm2-wordpress-suite' ); ?><br />
            <textarea id="gm2-rel-description" class="large-text" rows="3"></textarea></label></p>
        <p>
            <button type="button" class="button button-primary" id="gm2-rel-save"><?php echo esc_html__( 'Save Relationship', 'gm2-wordpress-suite' ); ?></button>
            <button type="button" class="button" id="gm2-rel-cancel"><?php echo esc_html__( 'Cancel', 'gm2-wordpress-suite' ); ?></button>
        </p>
    </div>

    <datalist id="gm2-rel-objects">
        <?php foreach ($objects as $slug => $label) : ?>
            <option value="<?php echo esc_attr($slug); ?>" label="<?php echo esc_attr($label); ?>"></option>
        <?php endforeach; ?>
        <?php if (empty($objects)) : ?>
            <option value="post" label="<?php echo esc_attr__( 'Post (Post Type)', 'gm2-wordpress-suite' ); ?>"></option>
        <?php endif; ?>
    </datalist>
</div>
