<?php

declare(strict_types=1);

namespace Gm2\Elementor\Widgets;

use Elementor\Controls_Manager;
use Gm2\Fields\FieldDefinition;
use function esc_html;
use function esc_html__;
use function get_option;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sanitize_text_field;
use function strtotime;
use function trim;
use function wp_date;

if (!class_exists(AbstractFieldWidget::class)) {
    return;
}

final class OpeningHours extends AbstractFieldWidget
{
    public function get_name(): string
    {
        return 'gm2_opening_hours';
    }

    public function get_title(): string
    {
        return esc_html__('GM2 Opening Hours', 'gm2-wordpress-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-time-line';
    }

    public function get_categories(): array
    {
        return [ 'general' ];
    }

    public function get_keywords(): array
    {
        return [ 'gm2', 'hours', 'schedule', 'opening' ];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_hours', [
            'label' => esc_html__('Opening Hours', 'gm2-wordpress-suite'),
        ]);

        $this->add_control('post_type', [
            'label'   => esc_html__('Post Type', 'gm2-wordpress-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->group()->getPostTypeOptions(),
            'default' => '',
        ]);

        $this->add_control('field_key', [
            'label'       => esc_html__('Hours Field', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'label_block' => true,
            'options'     => $this->fieldOptions(null, false, [ 'repeater', 'text', 'textarea' ]),
        ]);

        $this->add_control('closed_label', [
            'label'       => esc_html__('Closed Label', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'default'     => esc_html__('Closed', 'gm2-wordpress-suite'),
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
        $value    = $this->sanitizedValue($field, $fieldKey, $postType);

        if (is_string($value) && trim($value) !== '') {
            echo '<div class="gm2-opening-hours gm2-opening-hours--text">' . esc_html($value) . '</div>';

            return;
        }

        if (!is_array($value) || $value === []) {
            $this->renderFallback($settings);

            return;
        }

        $hours = $this->normalizeHours($value);
        if ($hours === []) {
            $this->renderFallback($settings);

            return;
        }

        $closed = is_string($settings['closed_label'] ?? null) && trim($settings['closed_label']) !== ''
            ? $settings['closed_label']
            : esc_html__('Closed', 'gm2-wordpress-suite');

        $output = '';
        foreach ($hours as $row) {
            $display = $row['closed']
                ? $closed
                : $this->formatTimeRange($row['open'], $row['close']);

            if ($display === '') {
                continue;
            }

            $output .= '<div class="gm2-opening-hours__row">'
                . '<dt class="gm2-opening-hours__day">' . esc_html($row['day']) . '</dt>'
                . '<dd class="gm2-opening-hours__time">' . esc_html($display) . '</dd>'
                . '</div>';
        }

        if ($output === '') {
            $this->renderFallback($settings);

            return;
        }

        echo '<div class="gm2-opening-hours"><dl class="gm2-opening-hours__list">' . $output . '</dl></div>';
    }

    /**
     * @param array<int, mixed> $rows
     *
     * @return array<int, array{day: string, open: string, close: string, closed: bool}>
     */
    private function normalizeHours(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $day = isset($row['day']) && is_string($row['day']) ? sanitize_text_field($row['day']) : '';
            if ($day === '') {
                continue;
            }

            $open  = $this->extractTime($row, ['start', 'opens', 'open']);
            $close = $this->extractTime($row, ['end', 'closes', 'close']);

            $closed = false;
            if (($open === '' && $close === '') || $this->isMarkedClosed($row)) {
                $closed = true;
            }

            $normalized[] = [
                'day'    => $day,
                'open'   => $open,
                'close'  => $close,
                'closed' => $closed,
            ];
        }

        return $normalized;
    }

    private function extractTime(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (!isset($row[$key])) {
                continue;
            }

            if (is_string($row[$key])) {
                return trim($row[$key]);
            }
        }

        return '';
    }

    private function isMarkedClosed(array $row): bool
    {
        foreach (['closed', 'is_closed', 'status'] as $key) {
            if (!isset($row[$key])) {
                continue;
            }

            $value = $row[$key];
            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value)) {
                $value = strtolower(trim($value));
                if (in_array($value, ['closed', 'yes', 'true', '1'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function formatTimeRange(string $open, string $close): string
    {
        if ($open === '' && $close === '') {
            return '';
        }

        $formattedOpen  = $this->formatTime($open);
        $formattedClose = $this->formatTime($close);

        if ($formattedOpen === '' && $formattedClose === '') {
            return '';
        }

        if ($formattedOpen === '') {
            return $formattedClose;
        }

        if ($formattedClose === '') {
            return $formattedOpen;
        }

        return $formattedOpen . ' – ' . $formattedClose;
    }

    private function formatTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return sanitize_text_field($value);
        }

        $format = get_option('time_format');

        return wp_date($format ?: 'g:i a', $timestamp);
    }

    private function renderFallback(array $settings): void
    {
        $fallback = is_string($settings['fallback_text'] ?? null) ? trim($settings['fallback_text']) : '';
        if ($fallback === '') {
            return;
        }

        echo '<div class="gm2-opening-hours gm2-opening-hours--fallback">' . esc_html($fallback) . '</div>';
    }
}
