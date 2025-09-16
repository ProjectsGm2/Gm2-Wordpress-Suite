<?php

declare(strict_types=1);

namespace Gm2\Elementor\DynamicTags\Tag;

use Elementor\Modules\DynamicTags\Module;

final class ComputedValue extends FieldValue
{
    public function get_name(): string
    {
        return 'gm2_computed_value';
    }

    public function get_title(): string
    {
        return __('GM2 Computed Field', 'gm2-wordpress-suite');
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

        return $this->group()->getComputedCategories($fieldData['field']);
    }

    protected function fieldOptions(?string $postType): array
    {
        return $this->group()->getComputedFieldOptions($postType);
    }
}
