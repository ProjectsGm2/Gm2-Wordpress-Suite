<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Post;

class EventMapper extends AbstractMapper
{
    protected array $requiredFields = [
        'start_date' => __('Start date', 'gm2-wordpress-suite'),
        'end_date'   => __('End date', 'gm2-wordpress-suite'),
        'location'   => __('Location', 'gm2-wordpress-suite'),
    ];

    public function __construct()
    {
        parent::__construct(
            'event',
            'Event',
            'gm2_schema_event',
            __('Event', 'gm2-wordpress-suite')
        );
    }

    protected function mapData(WP_Post $post): array
    {
        $location = [
            '@type' => 'Place',
            'name'  => $this->sanitizeText($this->getField($post, 'location')),
        ];

        $address = $this->buildAddress([
            'streetAddress'   => $this->getField($post, 'location_address'),
            'addressLocality' => $this->getField($post, 'location_city'),
            'addressRegion'   => $this->getField($post, 'location_region'),
            'postalCode'      => $this->getField($post, 'location_postal_code'),
            'addressCountry'  => $this->getField($post, 'location_country'),
        ]);
        if (!empty($address)) {
            $location['address'] = $address;
        }

        return [
            '@id'                 => trailingslashit(get_permalink($post)) . '#event',
            'name'                => get_the_title($post),
            'description'         => $this->sanitizeText($post->post_excerpt ?: $post->post_content),
            'startDate'           => $this->getField($post, 'start_date'),
            'endDate'             => $this->getField($post, 'end_date'),
            'eventAttendanceMode' => $this->getField($post, 'attendance_mode'),
            'eventStatus'         => $this->getField($post, 'event_status'),
            'location'            => $this->filterEmpty($location),
            'organizer'           => $this->buildOrganizer($post),
            'offers'              => $this->buildOffers($post),
        ];
    }

    private function buildOrganizer(WP_Post $post): array
    {
        $name = $this->getField($post, 'organizer_name');
        if ($this->isEmpty($name)) {
            return [];
        }

        return $this->filterEmpty([
            '@type' => 'Organization',
            'name'  => $this->sanitizeText((string) $name),
            'url'   => esc_url_raw((string) $this->getField($post, 'organizer_url')),
        ]);
    }

    private function buildOffers(WP_Post $post): array
    {
        $price    = $this->toFloat($this->getField($post, 'ticket_price'));
        $currency = $this->getField($post, 'ticket_currency');
        $url      = $this->getField($post, 'ticket_url');

        if ($price === null && $this->isEmpty($currency) && $this->isEmpty($url)) {
            return [];
        }

        return $this->filterEmpty([
            '@type'         => 'Offer',
            'price'         => $price,
            'priceCurrency' => $currency ?: null,
            'availability'  => $this->getField($post, 'ticket_availability'),
            'validFrom'     => $this->getField($post, 'ticket_valid_from'),
            'url'           => $url ? esc_url_raw((string) $url) : esc_url_raw(get_permalink($post)),
        ]);
    }
}
