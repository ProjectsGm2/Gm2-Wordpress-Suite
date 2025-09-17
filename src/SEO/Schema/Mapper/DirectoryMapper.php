<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Post;

class DirectoryMapper extends AbstractMapper
{
    protected array $requiredFields = [
        'address' => __('Address', 'gm2-wordpress-suite'),
    ];

    public function __construct()
    {
        parent::__construct(
            'listing',
            'LocalBusiness',
            'gm2_schema_directory',
            __('Local Business', 'gm2-wordpress-suite')
        );
    }

    protected function mapData(WP_Post $post): array
    {
        $address = $this->buildAddress([
            'streetAddress'   => $this->getField($post, 'address'),
            'addressLocality' => $this->getField($post, 'city'),
            'addressRegion'   => $this->getField($post, 'region'),
            'postalCode'      => $this->getField($post, 'postal_code'),
            'addressCountry'  => $this->getField($post, 'country'),
        ]);

        $geo = $this->buildGeo(
            $this->toFloat($this->getField($post, 'latitude')),
            $this->toFloat($this->getField($post, 'longitude'))
        );

        $url = $this->getField($post, 'website');
        if (!$url) {
            $url = get_permalink($post);
        }

        $image = get_the_post_thumbnail_url($post, 'full');

        return [
            '@id'                       => trailingslashit(get_permalink($post)) . '#local-business',
            'name'                      => get_the_title($post),
            'description'               => $this->sanitizeText($post->post_excerpt ?: $post->post_content),
            'url'                       => esc_url_raw($url),
            'image'                     => $image ?: null,
            'telephone'                 => $this->formatTelephone($this->getField($post, 'phone')),
            'address'                   => $address,
            'geo'                       => $geo,
            'openingHoursSpecification' => $this->normalizeOpeningHours($this->getField($post, 'opening_hours', [])),
            'sameAs'                    => $this->normalizeSameAs($this->getField($post, 'same_as', [])),
        ];
    }

    private function formatTelephone(mixed $value): ?string
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        return preg_replace('/[^0-9+\-().\s]/', '', (string) $value);
    }

    private function normalizeOpeningHours(mixed $hours): array
    {
        if (!is_array($hours)) {
            return [];
        }

        $normalized = [];
        foreach ($hours as $row) {
            if (!is_array($row)) {
                continue;
            }

            $entry = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $row['dayOfWeek'] ?? $row['day'] ?? null,
                'opens'     => $row['opens'] ?? null,
                'closes'    => $row['closes'] ?? null,
            ];

            $entry = $this->filterEmpty($entry);
            if ($entry) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    private function normalizeSameAs(mixed $value): array
    {
        if ($this->isEmpty($value)) {
            return [];
        }

        if (is_string($value)) {
            $value = preg_split('/[\r\n]+/', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $links = [];
        foreach ($value as $link) {
            if (!is_string($link)) {
                continue;
            }

            $link = trim($link);
            if ($link === '') {
                continue;
            }

            $links[] = esc_url_raw($link);
        }

        return array_values(array_unique(array_filter($links)));
    }
}
