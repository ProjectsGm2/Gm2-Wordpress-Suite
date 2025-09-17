<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Post;

class RealEstateMapper extends AbstractMapper
{
    protected array $requiredFields = [
        'price'   => __('Price', 'gm2-wordpress-suite'),
        'address' => __('Address', 'gm2-wordpress-suite'),
    ];

    public function __construct()
    {
        parent::__construct(
            'property',
            'RealEstateListing',
            'gm2_schema_real_estate',
            __('Real Estate Listing', 'gm2-wordpress-suite')
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

        return [
            '@id'          => trailingslashit(get_permalink($post)) . '#listing',
            'name'         => get_the_title($post),
            'description'  => $this->sanitizeText($post->post_excerpt ?: $post->post_content),
            'url'          => esc_url_raw(get_permalink($post)),
            'address'      => $address,
            'geo'          => $geo,
            'numberOfRooms'=> $this->toInt($this->getField($post, 'bedrooms')),
            'numberOfBathroomsTotal' => $this->toInt($this->getField($post, 'bathrooms')),
            'floorSize'    => $this->buildFloorSize($post),
            'propertyType' => $this->getField($post, 'property_type'),
            'offers'       => $this->buildOffers($post, $address),
        ];
    }

    private function buildOffers(WP_Post $post, array $address): array
    {
        $price    = $this->toFloat($this->getField($post, 'price'));
        $currency = $this->getField($post, 'price_currency');
        $status   = $this->getField($post, 'availability');

        $itemOffered = $this->filterEmpty([
            '@type'             => $this->getField($post, 'residence_type') ?: 'Residence',
            'name'              => get_the_title($post),
            'numberOfRooms'     => $this->toInt($this->getField($post, 'bedrooms')),
            'numberOfBathroomsTotal' => $this->toInt($this->getField($post, 'bathrooms')),
            'address'           => $address,
            'floorSize'         => $this->buildFloorSize($post),
        ]);

        return $this->filterEmpty([
            '@type'         => 'Offer',
            'price'         => $price,
            'priceCurrency' => $currency ?: null,
            'availability'  => $status ?: null,
            'url'           => esc_url_raw(get_permalink($post)),
            'itemOffered'   => $itemOffered,
            'seller'        => $this->buildSeller($post),
        ]);
    }

    private function buildSeller(WP_Post $post): array
    {
        $name = $this->getField($post, 'seller_name');
        if ($this->isEmpty($name)) {
            return [];
        }

        return $this->filterEmpty([
            '@type' => 'RealEstateAgent',
            'name'  => $this->sanitizeText((string) $name),
            'url'   => esc_url_raw((string) $this->getField($post, 'seller_url')),
            'telephone' => $this->formatTelephone($this->getField($post, 'seller_phone')),
        ]);
    }

    private function buildFloorSize(WP_Post $post): array
    {
        $value = $this->toFloat($this->getField($post, 'floor_size'));
        $unit  = $this->getField($post, 'floor_size_unit');

        if ($value === null && $this->isEmpty($unit)) {
            return [];
        }

        return $this->filterEmpty([
            '@type'    => 'QuantitativeValue',
            'value'    => $value,
            'unitText' => $unit ?: null,
        ]);
    }

    private function formatTelephone(mixed $value): ?string
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        return preg_replace('/[^0-9+\-().\s]/', '', (string) $value);
    }
}
