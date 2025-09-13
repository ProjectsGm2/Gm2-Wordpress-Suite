<?php
namespace Gm2\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base class for GM2 CP Elementor dynamic tags.
 */
abstract class Base_GM2_CP_Tag extends Tag {
    /**
     * Group identifier for GM2 custom post fields.
     *
     * @return string
     */
    public function get_group() {
        return 'gm2_cp_fields';
    }

    /**
     * Register controls to choose field key and fallback.
     */
    protected function register_controls() {
        $this->add_control('field_key', [
            'label' => __('Field Key', 'gm2-wordpress-suite'),
            'type'  => Controls_Manager::TEXT,
        ]);

        $this->add_control('fallback', [
            'label' => __('Fallback', 'gm2-wordpress-suite'),
            'type'  => Controls_Manager::TEXT,
        ]);
    }

    /**
     * Resolve current object ID for context-aware field lookup.
     *
     * @return int
     */
    protected function resolve_object_id() {
        $id = get_the_ID();
        if (!$id) {
            $id = get_queried_object_id();
        }
        return $id ?: 0;
    }

    /**
     * Fetch the field value using gm2_field().
     *
     * @return mixed
     */
    protected function fetch_value() {
        $key = $this->get_settings('field_key');
        if (!$key) {
            return '';
        }
        $object_id = $this->resolve_object_id();
        return gm2_field($key, '', $object_id);
    }
}

/**
 * Text field tag.
 */
class GM2_CP_Text_Tag extends Base_GM2_CP_Tag {
    public function get_name() {
        return 'gm2_cp_text';
    }

    public function get_title() {
        return __('GM2 CP Text', 'gm2-wordpress-suite');
    }

    public function get_categories() {
        return [ Module::TEXT_CATEGORY ];
    }

    public function get_value(array $options = []) {
        $value = $this->fetch_value();
        if ($value === '' || $value === null) {
            return $this->get_settings('fallback');
        }
        return $value;
    }
}

/**
 * URL field tag.
 */
class GM2_CP_Url_Tag extends Base_GM2_CP_Tag {
    public function get_name() {
        return 'gm2_cp_url';
    }

    public function get_title() {
        return __('GM2 CP URL', 'gm2-wordpress-suite');
    }

    public function get_categories() {
        return [ Module::URL_CATEGORY ];
    }

    public function get_value(array $options = []) {
        $value = $this->fetch_value();
        if (!is_string($value) || $value === '') {
            $value = $this->get_settings('fallback');
        }
        return ['url' => $value];
    }
}

/**
 * Image field tag.
 */
class GM2_CP_Image_Tag extends Base_GM2_CP_Tag {
    public function get_name() {
        return 'gm2_cp_image';
    }

    public function get_title() {
        return __('GM2 CP Image', 'gm2-wordpress-suite');
    }

    public function get_categories() {
        return [ Module::IMAGE_CATEGORY ];
    }

    public function get_value(array $options = []) {
        $value = $this->fetch_value();
        $id  = 0;
        $url = '';
        if (is_array($value) && isset($value['ID'])) {
            $id = (int) $value['ID'];
        } elseif (is_numeric($value)) {
            $id = (int) $value;
        } elseif (is_string($value)) {
            $url = $value;
        }
        if ($id) {
            $url = wp_get_attachment_url($id) ?: '';
        }
        if (!$url) {
            $url = $this->get_settings('fallback');
        }
        return [
            'id'  => $id,
            'url' => $url,
        ];
    }
}

/**
 * Media field tag (audio, video, file).
 */
class GM2_CP_Media_Tag extends Base_GM2_CP_Tag {
    public function get_name() {
        return 'gm2_cp_media';
    }

    public function get_title() {
        return __('GM2 CP Media', 'gm2-wordpress-suite');
    }

    public function get_categories() {
        return [ Module::MEDIA_CATEGORY ];
    }

    public function get_value(array $options = []) {
        $value = $this->fetch_value();
        $id  = 0;
        $url = '';
        if (is_array($value) && isset($value['ID'])) {
            $id = (int) $value['ID'];
        } elseif (is_numeric($value)) {
            $id = (int) $value;
        } elseif (is_string($value)) {
            $url = $value;
        }
        if ($id) {
            $url = wp_get_attachment_url($id) ?: '';
        }
        if (!$url) {
            $url = $this->get_settings('fallback');
        }
        return [
            'id'  => $id,
            'url' => $url,
        ];
    }
}

/**
 * Number field tag.
 */
class GM2_CP_Number_Tag extends Base_GM2_CP_Tag {
    public function get_name() {
        return 'gm2_cp_number';
    }

    public function get_title() {
        return __('GM2 CP Number', 'gm2-wordpress-suite');
    }

    public function get_categories() {
        return [ Module::NUMBER_CATEGORY ];
    }

    public function get_value(array $options = []) {
        $value = $this->fetch_value();
        if ($value === '' || $value === null) {
            return $this->get_settings('fallback');
        }
        return $value;
    }
}

/**
 * Color field tag.
 */
class GM2_CP_Color_Tag extends Base_GM2_CP_Tag {
    public function get_name() {
        return 'gm2_cp_color';
    }

    public function get_title() {
        return __('GM2 CP Color', 'gm2-wordpress-suite');
    }

    public function get_categories() {
        return [ Module::COLOR_CATEGORY ];
    }

    public function get_value(array $options = []) {
        $value = $this->fetch_value();
        if ($value === '' || $value === null) {
            return $this->get_settings('fallback');
        }
        return $value;
    }
}

/**
 * Gallery field tag.
 */
class GM2_CP_Gallery_Tag extends Base_GM2_CP_Tag {
    public function get_name() {
        return 'gm2_cp_gallery';
    }

    public function get_title() {
        return __('GM2 CP Gallery', 'gm2-wordpress-suite');
    }

    public function get_categories() {
        return [ Module::GALLERY_CATEGORY ];
    }

    public function get_value(array $options = []) {
        $value = $this->fetch_value();
        $items = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                $id  = 0;
                $url = '';
                if (is_array($item) && isset($item['ID'])) {
                    $id = (int) $item['ID'];
                } elseif (is_numeric($item)) {
                    $id = (int) $item;
                } elseif (is_string($item)) {
                    $url = $item;
                }
                if ($id) {
                    $url = wp_get_attachment_url($id) ?: '';
                }
                if ($url) {
                    $items[] = [ 'id' => $id, 'url' => $url ];
                }
            }
        }
        if (empty($items) && $this->get_settings('fallback')) {
            $items[] = [ 'id' => 0, 'url' => $this->get_settings('fallback') ];
        }
        return $items;
    }
}

/**
 * Date/Time field tag.
 */
class GM2_CP_Date_Tag extends Base_GM2_CP_Tag {
    public function get_name() {
        return 'gm2_cp_date';
    }

    public function get_title() {
        return __('GM2 CP Date', 'gm2-wordpress-suite');
    }

    public function get_categories() {
        return [ Module::DATETIME_CATEGORY ];
    }

    public function get_value(array $options = []) {
        $value = $this->fetch_value();
        if ($value === '' || $value === null) {
            return $this->get_settings('fallback');
        }
        return $value;
    }
}

// Register group and tags with Elementor.
add_action('elementor/dynamic_tags/register', function($dynamic_tags) {
    $dynamic_tags->register_group('gm2_cp_fields', [
        'title' => __('Gm2 CP Fields', 'gm2-wordpress-suite'),
    ]);

    $dynamic_tags->register_tag(GM2_CP_Text_Tag::class);
    $dynamic_tags->register_tag(GM2_CP_Url_Tag::class);
    $dynamic_tags->register_tag(GM2_CP_Image_Tag::class);
    $dynamic_tags->register_tag(GM2_CP_Media_Tag::class);
    $dynamic_tags->register_tag(GM2_CP_Number_Tag::class);
    $dynamic_tags->register_tag(GM2_CP_Color_Tag::class);
    $dynamic_tags->register_tag(GM2_CP_Gallery_Tag::class);
    $dynamic_tags->register_tag(GM2_CP_Date_Tag::class);
});
