<?php

declare(strict_types=1);

namespace Gm2\Elementor\Widgets;

use Elementor\Widget_Base;
use Gm2\Elementor\DynamicTags\GM2_Dynamic_Tag_Group;
use Gm2\Fields\FieldDefinition;
use function __;
use function esc_attr;
use function esc_html;
use function esc_url;
use function esc_url_raw;
use function implode;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_scalar;
use function is_string;
use function sanitize_text_field;
use function trim;
use function wp_kses_post;
use function preg_replace;

if (!class_exists(Widget_Base::class)) {
    return;
}

abstract class AbstractFieldWidget extends Widget_Base
{
    private ?GM2_Dynamic_Tag_Group $group = null;

    protected function group(): GM2_Dynamic_Tag_Group
    {
        if ($this->group instanceof GM2_Dynamic_Tag_Group) {
            return $this->group;
        }

        $this->group = GM2_Dynamic_Tag_Group::instance();

        return $this->group;
    }

    protected function fieldOptions(?string $postType, bool $includeComputed = false, array $allowedTypes = []): array
    {
        return $this->group()->getFieldOptions($postType, $includeComputed, $allowedTypes);
    }

    protected function mapFieldOptions(?string $postType = null): array
    {
        return $this->group()->getMapFieldOptions($postType);
    }

    protected function normalizePostType(mixed $postType): ?string
    {
        if (!is_string($postType)) {
            return null;
        }

        $postType = trim($postType);

        return $postType === '' ? null : $postType;
    }

    protected function findField(string $compoundKey): ?array
    {
        return $this->group()->findField($compoundKey);
    }

    protected function formattedValue(FieldDefinition $field, string $compoundKey, ?string $postType = null): mixed
    {
        return $this->group()->getFormattedValue($compoundKey, $postType);
    }

    protected function sanitizedValue(FieldDefinition $field, string $compoundKey, ?string $postType = null): mixed
    {
        return $this->group()->getSanitizedValue($compoundKey, $postType);
    }

    protected function valueOrFallback(FieldDefinition $field, mixed $value, mixed $fallback): mixed
    {
        if (!$this->group()->isEmptyValue($value)) {
            return $value;
        }

        return $this->group()->formatFallback($field, $fallback);
    }

    protected function renderText(string $content, string $tag = 'div', array $classes = []): void
    {
        $tag     = $this->sanitizeTag($tag);
        $classes = $classes === [] ? '' : ' class="' . esc_attr(implode(' ', $classes)) . '"';

        echo '<' . $tag . $classes . '>' . esc_html($content) . '</' . $tag . '>';
    }

    protected function sanitizeTag(string $tag): string
    {
        $allowed = [ 'div', 'span', 'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong' ];
        if (!in_array($tag, $allowed, true)) {
            return 'div';
        }

        return $tag;
    }

    protected function attachmentHtml(array $value, array $classes = [], string $tag = 'img'): ?string
    {
        $url = isset($value['url']) && is_string($value['url']) ? trim($value['url']) : '';
        if ($url === '') {
            return null;
        }

        $url = esc_url($url);
        if ($tag === 'img') {
            $classAttr = $classes === [] ? '' : ' class="' . esc_attr(implode(' ', $classes)) . '"';

            return '<img src="' . $url . '" alt="" loading="lazy"' . $classAttr . ' />';
        }

        $classAttr = $classes === [] ? '' : ' class="' . esc_attr(implode(' ', $classes)) . '"';

        return '<a' . $classAttr . ' href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
    }

    protected function stringFromValue(FieldDefinition $field, mixed $value): string
    {
        $type = $field->getType()->getName();
        if ($value === null) {
            return '';
        }

        switch ($type) {
            case 'checkbox':
            case 'switch':
                if (is_bool($value)) {
                    return $value ? __('Yes', 'gm2-wordpress-suite') : __('No', 'gm2-wordpress-suite');
                }

                return (string) $value;
            case 'multiselect':
                if (is_array($value)) {
                    return implode(', ', array_map('sanitize_text_field', $value));
                }

                break;
            case 'address':
                if (is_array($value)) {
                    $parts = [];
                    foreach (['line1', 'line2', 'city', 'state', 'postal_code', 'country'] as $key) {
                        if (isset($value[$key]) && is_string($value[$key]) && trim($value[$key]) !== '') {
                            $parts[] = sanitize_text_field($value[$key]);
                        }
                    }

                    return implode(', ', $parts);
                }

                break;
            case 'geopoint':
                if (is_array($value)) {
                    $lat = isset($value['lat']) && is_numeric($value['lat']) ? $this->formatCoordinate((float) $value['lat']) : '';
                    $lng = isset($value['lng']) && is_numeric($value['lng']) ? $this->formatCoordinate((float) $value['lng']) : '';
                    $coords = trim($lat . ', ' . $lng);
                    if ($coords !== ',') {
                        return $coords;
                    }
                }

                break;
        }

        if (is_scalar($value)) {
            return sanitize_text_field((string) $value);
        }

        return '';
    }

    protected function richTextFromValue(FieldDefinition $field, mixed $value): string
    {
        $type = $field->getType()->getName();
        if ($type === 'wysiwyg' && is_string($value)) {
            return wp_kses_post($value);
        }

        return esc_html($this->stringFromValue($field, $value));
    }

    protected function renderGallery(array $items, array $classes = []): ?string
    {
        if ($items === []) {
            return null;
        }

        $output = '<div class="' . esc_attr(implode(' ', $classes)) . '">';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $html = $this->attachmentHtml($item, ['gm2-field-gallery__image']);
            if ($html === null) {
                continue;
            }
            $output .= '<figure class="gm2-field-gallery__item">' . $html . '</figure>';
        }
        $output .= '</div>';

        return $output;
    }

    protected function buildLink(string $url, ?string $text = null, array $classes = []): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $text ??= $url;

        return '<a class="' . esc_attr(implode(' ', $classes)) . '" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($text) . '</a>';
    }

    protected function telLink(string $number, array $classes = []): ?string
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        $display = sanitize_text_field($number);
        $href    = 'tel:' . preg_replace('/[^0-9+]/', '', $display);

        return '<a class="' . esc_attr(implode(' ', $classes)) . '" href="' . esc_url_raw($href) . '">' . esc_html($display) . '</a>';
    }

    protected function mailLink(string $email, array $classes = []): ?string
    {
        $email = sanitize_text_field(trim($email));
        if ($email === '') {
            return null;
        }

        return '<a class="' . esc_attr(implode(' ', $classes)) . '" href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
    }

    protected function formatCoordinate(float $value): string
    {
        $formatted = number_format($value, 6, '.', '');

        return trim(rtrim(rtrim($formatted, '0'), '.'));
    }
}
