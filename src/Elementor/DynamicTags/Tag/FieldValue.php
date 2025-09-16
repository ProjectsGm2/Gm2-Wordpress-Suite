<?php

declare(strict_types=1);

namespace Gm2\Elementor\DynamicTags\Tag;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use Gm2\Elementor\DynamicTags\GM2_Dynamic_Tag_Group;
use Gm2\Fields\FieldDefinition;
use Gm2\Fields\FieldGroupDefinition;
use function is_string;
use function sanitize_text_field;
use function trim;

class FieldValue extends Tag
{
    private static ?GM2_Dynamic_Tag_Group $group = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $registeredControls = [];

    /**
     * @var array<string, mixed>
     */
    private array $settings = [];

    public static function setGroup(GM2_Dynamic_Tag_Group $group): void
    {
        self::$group = $group;
    }

    protected function group(): GM2_Dynamic_Tag_Group
    {
        return self::$group ?? GM2_Dynamic_Tag_Group::instance();
    }

    public function get_name(): string
    {
        return 'gm2_field_value';
    }

    public function get_title(): string
    {
        return __('GM2 Field Value', 'gm2-wordpress-suite');
    }

    public function get_group(): string
    {
        return $this->group()->getGroupName();
    }

    public function get_categories(): array
    {
        $fieldKey = (string) ($this->get_settings('field_key') ?? '');
        if ($fieldKey === '') {
            return [ Module::TEXT_CATEGORY ];
        }

        $fieldData = $this->group()->findField($fieldKey);
        if ($fieldData === null) {
            return [ Module::TEXT_CATEGORY ];
        }

        return $this->group()->getCategories($fieldData['field']);
    }

    protected function register_controls(): void
    {
        $this->add_control('post_type', [
            'label'   => __('Post Type', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->group()->getPostTypeOptions(),
            'default' => '',
        ]);

        $this->add_control('field_key', [
            'label'   => __('Field', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT2,
            'options' => $this->fieldOptions(null),
        ]);

        $this->add_control('fallback', [
            'label' => __('Fallback', 'gm2-wordpress-suite'),
            'type'  => Controls_Manager::TEXT,
        ]);
    }

    protected function add_control($id, $args = []): void
    {
        $this->registeredControls[$id] = $args;
        parent::add_control($id, $args);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function get_registered_controls(): array
    {
        return $this->registeredControls;
    }

    public function set_settings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function get_settings($key = null): mixed
    {
        if ($key === null) {
            return $this->settings;
        }

        return $this->settings[$key] ?? null;
    }

    public function get_value(array $options = []): mixed
    {
        $fieldKey = (string) ($this->get_settings('field_key') ?? '');
        if ($fieldKey === '') {
            return $this->get_settings('fallback');
        }

        $fieldData = $this->group()->findField($fieldKey);
        if ($fieldData === null) {
            return $this->get_settings('fallback');
        }

        $postType = $this->normalizePostType($this->get_settings('post_type'));
        $value    = $this->valueFromGroup($fieldData['group'], $fieldData['field'], $fieldKey, $postType);

        if ($this->group()->isEmptyValue($value)) {
            return $this->fallbackForField($fieldData['field'], $this->get_settings('fallback'));
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    protected function fieldOptions(?string $postType): array
    {
        return $this->group()->getFieldOptions($postType, false);
    }

    protected function valueFromGroup(FieldGroupDefinition $group, FieldDefinition $field, string $compoundKey, ?string $postType): mixed
    {
        return $this->group()->getFormattedValue($compoundKey, $postType);
    }

    protected function fallbackForField(FieldDefinition $field, mixed $fallback): mixed
    {
        $value = is_string($fallback) ? sanitize_text_field($fallback) : $fallback;

        return $this->group()->formatFallback($field, $value);
    }

    private function normalizePostType(mixed $postType): ?string
    {
        if (!is_string($postType)) {
            return null;
        }

        $postType = trim($postType);

        return $postType === '' ? null : $postType;
    }
}
