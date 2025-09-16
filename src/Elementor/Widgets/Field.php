<?php

declare(strict_types=1);

namespace Gm2\Elementor\Widgets;

use Elementor\Controls_Manager;
use Gm2\Fields\FieldDefinition;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function is_array;
use function is_string;
use function trim;
use function wp_kses_post;

if (!class_exists(AbstractFieldWidget::class)) {
    return;
}

final class Field extends AbstractFieldWidget
{
    public function get_name(): string
    {
        return 'gm2_field';
    }

    public function get_title(): string
    {
        return esc_html__('GM2 Field', 'gm2-wordpress-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-post-info';
    }

    public function get_categories(): array
    {
        return [ 'general' ];
    }

    public function get_keywords(): array
    {
        return [ 'gm2', 'field', 'meta', 'custom' ];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => esc_html__('Content', 'gm2-wordpress-suite'),
        ]);

        $this->add_control('post_type', [
            'label'   => esc_html__('Post Type', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->group()->getPostTypeOptions(),
            'default' => '',
        ]);

        $this->add_control('field_key', [
            'label'       => esc_html__('Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->fieldOptions(null, false),
        ]);

        $this->add_control('fallback', [
            'label'   => esc_html__('Fallback', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => [ 'active' => true ],
        ]);

        $this->add_control('html_tag', [
            'label'   => esc_html__('HTML Tag', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'div',
            'options' => [
                'div'   => 'div',
                'span'  => 'span',
                'p'     => 'p',
                'h2'    => 'h2',
                'h3'    => 'h3',
                'h4'    => 'h4',
                'strong'=> 'strong',
            ],
            'separator' => 'before',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $fieldKey = is_string($settings['field_key'] ?? null) ? trim((string) $settings['field_key']) : '';
        if ($fieldKey === '') {
            $fallback = is_string($settings['fallback'] ?? null) ? $settings['fallback'] : '';
            $this->renderText($fallback, $settings['html_tag'] ?? 'div', [ 'gm2-field' ]);

            return;
        }

        $fieldData = $this->findField($fieldKey);
        if ($fieldData === null) {
            $fallback = is_string($settings['fallback'] ?? null) ? $settings['fallback'] : '';
            $this->renderText($fallback, $settings['html_tag'] ?? 'div', [ 'gm2-field', 'gm2-field--fallback' ]);

            return;
        }

        /** @var FieldDefinition $field */
        $field    = $fieldData['field'];
        $postType = $this->normalizePostType($settings['post_type'] ?? null);
        $value    = $this->formattedValue($field, $fieldKey, $postType);
        $value    = $this->valueOrFallback($field, $value, $settings['fallback'] ?? '');

        $output = $this->formatValueForDisplay($field, $value, $settings);
        if ($output === null) {
            return;
        }

        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function formatValueForDisplay(FieldDefinition $field, mixed $value, array $settings): ?string
    {
        $tag      = is_string($settings['html_tag'] ?? null) ? $settings['html_tag'] : 'div';
        $classes  = [ 'gm2-field' ];
        $type     = $field->getType()->getName();

        switch ($type) {
            case 'image':
                if (is_array($value)) {
                    $html = $this->attachmentHtml($value, [ 'gm2-field__image' ]);
                    if ($html !== null) {
                        return '<div class="gm2-field gm2-field--image">' . $html . '</div>';
                    }
                }

                break;
            case 'media':
            case 'file':
                if (is_array($value)) {
                    $html = $this->attachmentHtml($value, [ 'gm2-field__link' ], 'a');
                    if ($html !== null) {
                        return '<div class="gm2-field gm2-field--link">' . $html . '</div>';
                    }
                }

                break;
            case 'gallery':
                if (is_array($value)) {
                    $gallery = $this->renderGallery($value, [ 'gm2-field__gallery' ]);
                    if ($gallery !== null) {
                        return '<div class="gm2-field gm2-field--gallery">' . $gallery . '</div>';
                    }
                }

                break;
            case 'url':
                if (is_string($value) && $value !== '') {
                    $link = $this->buildLink($value, null, [ 'gm2-field__link' ]);
                    if ($link !== null) {
                        return '<div class="gm2-field gm2-field--link">' . $link . '</div>';
                    }
                }

                break;
            case 'email':
                if (is_string($value) && $value !== '') {
                    $link = $this->mailLink($value, [ 'gm2-field__link' ]);
                    if ($link !== null) {
                        return '<div class="gm2-field gm2-field--email">' . $link . '</div>';
                    }
                }

                break;
            case 'tel':
                if (is_string($value) && $value !== '') {
                    $link = $this->telLink($value, [ 'gm2-field__link' ]);
                    if ($link !== null) {
                        return '<div class="gm2-field gm2-field--tel">' . $link . '</div>';
                    }
                }

                break;
            case 'wysiwyg':
                if (is_string($value)) {
                    $content = wp_kses_post($value);

                    return '<div class="gm2-field gm2-field--wysiwyg">' . $content . '</div>';
                }

                break;
            default:
                $content = $this->stringFromValue($field, $value);
                if ($content !== '') {
                    $classes[] = 'gm2-field--text';
                    $classAttr = ' class="' . esc_attr(implode(' ', $classes)) . '"';

                    return '<' . $this->sanitizeTag($tag) . $classAttr . '>' . esc_html($content) . '</' . $this->sanitizeTag($tag) . '>';
                }
        }

        return null;
    }
}
