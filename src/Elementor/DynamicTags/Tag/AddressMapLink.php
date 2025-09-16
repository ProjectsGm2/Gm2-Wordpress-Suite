<?php

declare(strict_types=1);

namespace Gm2\Elementor\DynamicTags\Tag;

use Elementor\Modules\DynamicTags\Module;
use Gm2\Fields\FieldDefinition;
use Gm2\Fields\FieldGroupDefinition;
use function esc_url_raw;
use function is_string;

final class AddressMapLink extends FieldValue
{
    public function get_name(): string
    {
        return 'gm2_address_map_link';
    }

    public function get_title(): string
    {
        return __('GM2 Map Link', 'gm2-wordpress-suite');
    }

    public function get_categories(): array
    {
        return [ Module::URL_CATEGORY ];
    }

    protected function fieldOptions(?string $postType): array
    {
        return $this->group()->getMapFieldOptions($postType);
    }

    protected function valueFromGroup(FieldGroupDefinition $group, FieldDefinition $field, string $compoundKey, ?string $postType): mixed
    {
        $url = $this->group()->buildMapUrl($compoundKey, $postType);
        if ($url === null) {
            return [ 'url' => '' ];
        }

        return [ 'url' => $url ];
    }

    protected function fallbackForField(FieldDefinition $field, mixed $fallback): mixed
    {
        $url = is_string($fallback) ? esc_url_raw($fallback) : '';

        return [ 'url' => $url ];
    }
}
