<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Flexible content field allowing multiple row templates with nested fields.
 *
 * Example configuration:
 *
 * gm2_register_field_type( 'flexible', 'GM2_Field_Flexible' );
 *
 * $field = new GM2_Field_Flexible( 'my_layout', array(
 *     'layouts' => array(
 *         'hero' => array(
 *             'label'  => 'Hero',
 *             'fields' => array(
 *                 'title' => array( 'type' => 'text', 'label' => 'Title' ),
 *                 'image' => array( 'type' => 'media', 'label' => 'Image' ),
 *             ),
 *         ),
 *         'cta'  => array(
 *             'label'  => 'Call to Action',
 *             'fields' => array(
 *                 'content' => array( 'type' => 'textarea', 'label' => 'Content' ),
 *             ),
 *         ),
 *     ),
 * ) );
 *
 * The field stores an array of rows, each row containing a `layout` key and
 * values for the fields defined within that layout.
 */
class GM2_Field_Flexible extends GM2_Field {
    /**
     * Render the flexible content field.
     *
     * @param mixed  $value       Stored value.
     * @param int    $object_id   Current object ID.
     * @param string $context_type Context type.
     */
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $layouts  = $this->args['layouts'] ?? array();
        $value    = is_array( $value ) ? $value : array();
        $disabled = $this->args['disabled'] ?? false ? ' data-disabled="1"' : '';

        echo '<div class="gm2-flexible" data-key="' . esc_attr( $this->key ) . '"' . $disabled . '>';

        foreach ( $value as $index => $row ) {
            $layout_key = $row['layout'] ?? '';
            if ( ! isset( $layouts[ $layout_key ] ) ) {
                continue;
            }

            $layout = $layouts[ $layout_key ];
            echo '<div class="gm2-flexible-row" data-layout="' . esc_attr( $layout_key ) . '">';
            echo '<input type="hidden" name="' . esc_attr( $this->key ) . '[' . esc_attr( $index ) . '][layout]" value="' . esc_attr( $layout_key ) . '" />';
            echo '<div class="gm2-flexible-row-controls"><span class="gm2-flexible-row-handle">&#9776;</span> <button type="button" class="button gm2-flexible-remove">&times;</button></div>';

            foreach ( $layout['fields'] as $field_key => $field_args ) {
                $type       = $field_args['type'] ?? 'text';
                $class_name = gm2_get_field_type_class( $type );
                if ( ! $class_name || ! class_exists( $class_name ) ) {
                    continue;
                }
                $field_obj   = new $class_name( $this->key . '[' . $index . '][' . $field_key . ']', $field_args );
                $field_value = $row[ $field_key ] ?? null;
                $field_obj->render_admin( $field_value, $object_id, $context_type );
            }

            echo '</div>';
        }

        // Add row controls and layout selector.
        if ( ! empty( $layouts ) ) {
            echo '<p class="gm2-flexible-add">';
            echo '<select class="gm2-flexible-layout">';
            foreach ( $layouts as $layout_key => $layout ) {
                echo '<option value="' . esc_attr( $layout_key ) . '">' . esc_html( $layout['label'] ?? $layout_key ) . '</option>';
            }
            echo '</select> <button type="button" class="button gm2-flexible-add-row">' . esc_html__( 'Add Row', 'gm2-wordpress-suite' ) . '</button>';
            echo '</p>';
        }

        echo '</div>';
    }

    /**
     * Sanitize flexible field value.
     *
     * @param mixed $value Submitted value.
     * @return array       Sanitized value.
     */
    public function sanitize( $value ) {
        $layouts = $this->args['layouts'] ?? array();
        $clean   = array();

        if ( is_array( $value ) ) {
            foreach ( $value as $row ) {
                $layout_key = $row['layout'] ?? '';
                if ( ! isset( $layouts[ $layout_key ] ) ) {
                    continue;
                }
                $layout  = $layouts[ $layout_key ];
                $row_out = array( 'layout' => $layout_key );

                foreach ( $layout['fields'] as $field_key => $field_args ) {
                    $type       = $field_args['type'] ?? 'text';
                    $class_name = gm2_get_field_type_class( $type );
                    if ( ! $class_name || ! class_exists( $class_name ) ) {
                        continue;
                    }
                    $field_obj = new $class_name( $field_key, $field_args );
                    $row_out[ $field_key ] = $field_obj->sanitize( $row[ $field_key ] ?? null );
                }

                $clean[] = $row_out;
            }
        }

        return $clean;
    }
}
