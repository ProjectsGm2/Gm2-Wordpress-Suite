<?php

declare(strict_types=1);

namespace Gm2\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Gm2\Fields\FieldDefinition;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_the_title;
use function is_array;
use function is_string;
use function trim;
use function wp_kses_post;

if (!class_exists(AbstractFieldWidget::class)) {
    return;
}

final class LoopCard extends AbstractFieldWidget
{
    private const LAYOUTS = [ 'stacked', 'horizontal' ];
    private const CARD_TAGS = [ 'article', 'div', 'section' ];
    private const TITLE_TAGS = [ 'h2', 'h3', 'h4', 'p', 'div' ];

    public function get_name(): string
    {
        return 'gm2_loop_card';
    }

    public function get_title(): string
    {
        return esc_html__('GM2 Loop Card', 'gm2-wordpress-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-post-list';
    }

    public function get_categories(): array
    {
        return [ 'general' ];
    }

    public function get_keywords(): array
    {
        return [ 'gm2', 'loop', 'card', 'meta' ];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => esc_html__('Card', 'gm2-wordpress-suite'),
        ]);

        $this->add_control('post_type', [
            'label'   => esc_html__('Post Type', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->group()->getPostTypeOptions(),
            'default' => '',
        ]);

        $this->add_control('layout', [
            'label'   => esc_html__('Layout', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'stacked'    => esc_html__('Stacked', 'gm2-wordpress-suite'),
                'horizontal' => esc_html__('Horizontal', 'gm2-wordpress-suite'),
            ],
            'default' => 'stacked',
        ]);

        $this->add_control('card_tag', [
            'label'   => esc_html__('Wrapper Tag', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => [ 'article' => 'article', 'div' => 'div', 'section' => 'section' ],
            'default' => 'article',
        ]);

        $this->add_control('image_field', [
            'label'       => esc_html__('Image Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->fieldOptions(null, false, [ 'image' ]),
        ]);

        $this->add_control('image_fallback', [
            'label'       => esc_html__('Image Fallback URL', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'dynamic'     => [ 'active' => true ],
            'placeholder' => 'https://example.com/fallback.jpg',
        ]);

        $this->add_control('title_field', [
            'label'       => esc_html__('Title Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->fieldOptions(null, false, $this->titleFieldTypes()),
        ]);

        $this->add_control('title_tag', [
            'label'   => esc_html__('Title Tag', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => [ 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'p' => 'p', 'div' => 'div' ],
            'default' => 'h3',
        ]);

        $this->add_control('title_fallback', [
            'label'   => esc_html__('Title Fallback', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => [ 'active' => true ],
        ]);

        $this->add_control('subtitle_field', [
            'label'       => esc_html__('Subtitle Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->fieldOptions(null, false, $this->subtitleFieldTypes()),
        ]);

        $this->add_control('subtitle_tag', [
            'label'   => esc_html__('Subtitle Tag', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => [ 'p' => 'p', 'div' => 'div', 'span' => 'span' ],
            'default' => 'p',
        ]);

        $this->add_control('subtitle_fallback', [
            'label'   => esc_html__('Subtitle Fallback', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => [ 'active' => true ],
        ]);

        $this->add_control('body_field', [
            'label'       => esc_html__('Body Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->fieldOptions(null, false, $this->bodyFieldTypes()),
        ]);

        $this->add_control('body_fallback', [
            'label'   => esc_html__('Body Fallback', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::TEXTAREA,
            'dynamic' => [ 'active' => true ],
        ]);

        $repeater = new Repeater();
        $repeater->add_control('label', [
            'label'   => esc_html__('Label', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => [ 'active' => true ],
        ]);
        $repeater->add_control('field_key', [
            'label'       => esc_html__('Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->fieldOptions(null, false, $this->metaFieldTypes()),
        ]);
        $repeater->add_control('fallback', [
            'label'   => esc_html__('Fallback', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => [ 'active' => true ],
        ]);

        $this->add_control('meta_fields', [
            'label'       => esc_html__('Meta Fields', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ label }}}',
        ]);

        $this->add_control('button_text', [
            'label'       => esc_html__('Button Text', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'dynamic'     => [ 'active' => true ],
            'default'     => esc_html__('View Details', 'gm2-wordpress-suite'),
        ]);

        $this->add_control('button_url_field', [
            'label'       => esc_html__('Button URL Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->fieldOptions(null, false, [ 'url' ]),
        ]);

        $this->add_control('button_fallback_url', [
            'label'   => esc_html__('Button Fallback URL', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => [ 'active' => true ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $postType = $this->normalizePostType($settings['post_type'] ?? null);
        $layout   = $this->sanitizeChoice($settings['layout'] ?? 'stacked', self::LAYOUTS, 'stacked');
        $tag      = $this->sanitizeChoice($settings['card_tag'] ?? 'article', self::CARD_TAGS, 'article');

        $classes = [ 'gm2-loop-card', 'gm2-loop-card--' . $layout ];
        echo '<' . $tag . ' class="' . esc_attr(implode(' ', $classes)) . '">';

        $this->renderMedia($settings, $postType);
        echo '<div class="gm2-loop-card__content">';
        $this->renderHeading($settings, $postType);
        $this->renderSubtitle($settings, $postType);
        $this->renderBody($settings, $postType);
        $this->renderMetaList($settings, $postType);
        $this->renderButton($settings, $postType);
        echo '</div>';
        echo '</' . $tag . '>';
    }

    private function renderMedia(array $settings, ?string $postType): void
    {
        $imageData = $this->loadFieldValue($settings['image_field'] ?? null, $postType, $settings['image_fallback'] ?? '');
        if ($imageData === null) {
            return;
        }

        $value = $imageData['value'];
        if (!is_array($value)) {
            return;
        }

        $html = $this->attachmentHtml($value, [ 'gm2-loop-card__image' ]);
        if ($html === null) {
            return;
        }

        echo '<figure class="gm2-loop-card__media">' . $html . '</figure>';
    }

    private function renderHeading(array $settings, ?string $postType): void
    {
        $data = $this->loadFieldValue($settings['title_field'] ?? null, $postType, $settings['title_fallback'] ?? '');
        $title = '';
        if ($data !== null) {
            $title = $this->stringFromValue($data['field'], $data['value']);
        }

        if ($title === '') {
            $title = get_the_title() ?: '';
        }

        if ($title === '') {
            return;
        }

        $tag = $this->sanitizeChoice($settings['title_tag'] ?? 'h3', self::TITLE_TAGS, 'h3');
        echo '<' . $tag . ' class="gm2-loop-card__title">' . esc_html($title) . '</' . $tag . '>';
    }

    private function renderSubtitle(array $settings, ?string $postType): void
    {
        $data = $this->loadFieldValue($settings['subtitle_field'] ?? null, $postType, $settings['subtitle_fallback'] ?? '');
        if ($data === null) {
            return;
        }

        $subtitle = $this->stringFromValue($data['field'], $data['value']);
        if ($subtitle === '') {
            return;
        }

        $tag = $this->sanitizeChoice($settings['subtitle_tag'] ?? 'p', [ 'p', 'div', 'span' ], 'p');
        echo '<' . $tag . ' class="gm2-loop-card__subtitle">' . esc_html($subtitle) . '</' . $tag . '>';
    }

    private function renderBody(array $settings, ?string $postType): void
    {
        $data = $this->loadFieldValue($settings['body_field'] ?? null, $postType, $settings['body_fallback'] ?? '');
        if ($data === null) {
            return;
        }

        $field = $data['field'];
        $value = $data['value'];

        if ($field->getType()->getName() === 'wysiwyg' && is_string($value)) {
            $content = wp_kses_post($value);
            if ($content !== '') {
                echo '<div class="gm2-loop-card__body">' . $content . '</div>';
            }

            return;
        }

        $text = $this->stringFromValue($field, $value);
        if ($text !== '') {
            echo '<div class="gm2-loop-card__body">' . esc_html($text) . '</div>';
        }
    }

    private function renderMetaList(array $settings, ?string $postType): void
    {
        $items = $settings['meta_fields'] ?? [];
        if (!is_array($items) || $items === []) {
            return;
        }

        $output = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $fieldData = $this->loadFieldValue($item['field_key'] ?? null, $postType, $item['fallback'] ?? '');
            if ($fieldData === null) {
                continue;
            }

            $valueHtml = $this->renderMetaValue($fieldData['field'], $fieldData['value']);
            if ($valueHtml === null || trim($valueHtml) === '') {
                continue;
            }

            $label = is_string($item['label'] ?? null) && $item['label'] !== ''
                ? $item['label']
                : $fieldData['field']->getLabel();

            $output .= '<div class="gm2-loop-card__meta-item">'
                . '<dt class="gm2-loop-card__meta-label">' . esc_html($label) . '</dt>'
                . '<dd class="gm2-loop-card__meta-value">' . $valueHtml . '</dd>'
                . '</div>';
        }

        if ($output === '') {
            return;
        }

        echo '<dl class="gm2-loop-card__meta">' . $output . '</dl>';
    }

    private function renderButton(array $settings, ?string $postType): void
    {
        $fieldData = $this->loadFieldValue($settings['button_url_field'] ?? null, $postType, $settings['button_fallback_url'] ?? '');
        if ($fieldData === null) {
            return;
        }

        $urlValue = $fieldData['value'];
        if (!is_string($urlValue) || trim($urlValue) === '') {
            return;
        }

        $text = is_string($settings['button_text'] ?? null) && trim((string) $settings['button_text']) !== ''
            ? $settings['button_text']
            : esc_html__('Read more', 'gm2-wordpress-suite');

        echo '<a class="gm2-loop-card__button" href="' . esc_url($urlValue) . '">' . esc_html($text) . '</a>';
    }

    /**
     * @return array<string>
     */
    private function titleFieldTypes(): array
    {
        return [ 'text', 'textarea', 'number', 'currency', 'select', 'radio', 'multiselect', 'computed', 'relationship_post', 'relationship_term', 'relationship_user', 'date', 'datetime_tz', 'time' ];
    }

    /**
     * @return array<string>
     */
    private function subtitleFieldTypes(): array
    {
        return [ 'text', 'textarea', 'number', 'currency', 'select', 'radio', 'multiselect', 'computed', 'date', 'datetime_tz', 'time', 'relationship_post', 'relationship_term', 'relationship_user' ];
    }

    /**
     * @return array<string>
     */
    private function bodyFieldTypes(): array
    {
        return [ 'text', 'textarea', 'wysiwyg', 'computed' ];
    }

    /**
     * @return array<string>
     */
    private function metaFieldTypes(): array
    {
        return [
            'text', 'textarea', 'number', 'currency', 'select', 'radio', 'multiselect',
            'checkbox', 'switch', 'computed', 'date', 'time', 'datetime_tz', 'relationship_post',
            'relationship_term', 'relationship_user', 'url', 'email', 'tel', 'address', 'geopoint',
        ];
    }

    /**
     * @param array<string, mixed>|null $field
     *
     * @return array{field: FieldDefinition, value: mixed}|null
     */
    private function loadFieldValue(mixed $fieldKey, ?string $postType, mixed $fallback): ?array
    {
        if (!is_string($fieldKey)) {
            return null;
        }

        $fieldKey = trim($fieldKey);
        if ($fieldKey === '') {
            return null;
        }

        $fieldData = $this->findField($fieldKey);
        if ($fieldData === null) {
            return null;
        }

        $field = $fieldData['field'];
        $value = $this->formattedValue($field, $fieldKey, $postType);
        $value = $this->valueOrFallback($field, $value, $fallback);

        return [ 'field' => $field, 'value' => $value ];
    }

    private function renderMetaValue(FieldDefinition $field, mixed $value): ?string
    {
        $type = $field->getType()->getName();
        if ($type === 'url' && is_string($value) && $value !== '') {
            $link = $this->buildLink($value, null, [ 'gm2-loop-card__meta-link' ]);

            return $link;
        }

        if ($type === 'email' && is_string($value) && $value !== '') {
            return $this->mailLink($value, [ 'gm2-loop-card__meta-link' ]);
        }

        if ($type === 'tel' && is_string($value) && $value !== '') {
            return $this->telLink($value, [ 'gm2-loop-card__meta-link' ]);
        }

        $text = $this->stringFromValue($field, $value);
        if ($text === '') {
            return null;
        }

        return esc_html($text);
    }

    private function sanitizeChoice(mixed $value, array $allowed, string $default): string
    {
        if (!is_string($value)) {
            return $default;
        }

        $value = trim($value);
        if ($value === '') {
            return $default;
        }

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
