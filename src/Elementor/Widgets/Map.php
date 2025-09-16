<?php

declare(strict_types=1);

namespace Gm2\Elementor\Widgets;

use Elementor\Controls_Manager;
use Gm2\Fields\FieldDefinition;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function esc_url_raw;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function sanitize_text_field;
use function trim;

if (!class_exists(AbstractFieldWidget::class)) {
    return;
}

final class Map extends AbstractFieldWidget
{
    public function get_name(): string
    {
        return 'gm2_map';
    }

    public function get_title(): string
    {
        return esc_html__('GM2 Map', 'gm2-wordpress-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-google-maps';
    }

    public function get_categories(): array
    {
        return [ 'general' ];
    }

    public function get_keywords(): array
    {
        return [ 'gm2', 'map', 'address', 'geolocation' ];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_map', [
            'label' => esc_html__('Map', 'gm2-wordpress-suite'),
        ]);

        $this->add_control('post_type', [
            'label'   => esc_html__('Post Type', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->group()->getPostTypeOptions(),
            'default' => '',
        ]);

        $this->add_control('field_key', [
            'label'       => esc_html__('Location Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->mapFieldOptions(null),
        ]);

        $this->add_control('display', [
            'label'   => esc_html__('Display', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'embed' => esc_html__('Embed', 'gm2-wordpress-suite'),
                'link'  => esc_html__('Link', 'gm2-wordpress-suite'),
            ],
            'default' => 'embed',
        ]);

        $this->add_control('provider_url', [
            'label'       => esc_html__('Provider URL', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'dynamic'     => [ 'active' => true ],
            'default'     => 'https://www.google.com/maps/search/?api=1&query={{query}}',
            'description' => esc_html__('Use {{query}}, {{lat}}, and {{lng}} tokens to build the URL.', 'gm2-wordpress-suite'),
        ]);

        $this->add_control('link_text', [
            'label'       => esc_html__('Link Text', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'dynamic'     => [ 'active' => true ],
            'default'     => esc_html__('View on map', 'gm2-wordpress-suite'),
            'condition'   => [ 'display' => 'link' ],
        ]);

        $this->add_control('height', [
            'label'       => esc_html__('Embed Height (px)', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 320,
            'condition'   => [ 'display' => 'embed' ],
        ]);

        $this->add_control('fallback_text', [
            'label'   => esc_html__('Fallback Text', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => [ 'active' => true ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $fieldKey = is_string($settings['field_key'] ?? null) ? trim($settings['field_key']) : '';
        if ($fieldKey === '') {
            $this->renderFallback($settings);

            return;
        }

        $fieldData = $this->findField($fieldKey);
        if ($fieldData === null) {
            $this->renderFallback($settings);

            return;
        }

        /** @var FieldDefinition $field */
        $field    = $fieldData['field'];
        $postType = $this->normalizePostType($settings['post_type'] ?? null);
        $rawValue = $this->sanitizedValue($field, $fieldKey, $postType);

        $url = $this->buildProviderUrl($field, $rawValue, $settings, $fieldKey, $postType);
        if ($url === null) {
            $this->renderFallback($settings);

            return;
        }

        $display = is_string($settings['display'] ?? null) ? $settings['display'] : 'embed';
        if ($display === 'link') {
            $text = is_string($settings['link_text'] ?? null) && trim($settings['link_text']) !== ''
                ? $settings['link_text']
                : esc_html__('View on map', 'gm2-wordpress-suite');
            echo '<a class="gm2-map gm2-map--link" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($text) . '</a>';

            return;
        }

        $height = isset($settings['height']) && is_numeric($settings['height']) ? max(120, (int) $settings['height']) : 320;
        echo '<div class="gm2-map gm2-map--embed">'
            . '<iframe src="' . esc_url($url) . '" loading="lazy" allowfullscreen style="width:100%;border:0;height:' . esc_attr((string) $height) . 'px"></iframe>'
            . '</div>';
    }

    private function renderFallback(array $settings): void
    {
        $fallback = is_string($settings['fallback_text'] ?? null) ? trim($settings['fallback_text']) : '';
        if ($fallback === '') {
            return;
        }

        echo '<div class="gm2-map gm2-map--fallback">' . esc_html($fallback) . '</div>';
    }

    private function buildProviderUrl(FieldDefinition $field, mixed $value, array $settings, string $compoundKey, ?string $postType): ?string
    {
        $template = is_string($settings['provider_url'] ?? null) ? trim($settings['provider_url']) : '';

        if ($template === '') {
            $default = $this->group()->buildMapUrl($compoundKey, $postType);

            return $default === null ? null : $default;
        }

        $lat = null;
        $lng = null;
        $query = '';

        if (is_array($value)) {
            if (isset($value['lat'], $value['lng']) && is_numeric($value['lat']) && is_numeric($value['lng'])) {
                $lat = $this->formatCoordinate((float) $value['lat']);
                $lng = $this->formatCoordinate((float) $value['lng']);
            }

            $parts = [];
            foreach (['line1', 'line2', 'city', 'state', 'postal_code', 'country'] as $key) {
                if (isset($value[$key]) && is_string($value[$key]) && trim($value[$key]) !== '') {
                    $parts[] = sanitize_text_field($value[$key]);
                }
            }

            if ($parts !== []) {
                $query = implode(' ', $parts);
            }
        } elseif (is_string($value)) {
            $query = trim($value);
        }

        if (($query === '' || $query === null) && $lat !== null && $lng !== null) {
            $query = $lat . ',' . $lng;
        }

        $replacements = [
            '{{lat}}'   => $lat ?? '',
            '{{lng}}'   => $lng ?? '',
            '{{query}}' => $query,
        ];

        $url = strtr($template, $replacements);
        $url = esc_url_raw($url);
        if ($url === '' && $query !== '') {
            $url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
        }

        return $url !== '' ? $url : null;
    }
}
