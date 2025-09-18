<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Post;
use WP_Term;

class RealEstateMapper extends AbstractMapper
{
    private const RESIDENCE_TYPE_MAP = [
        'apartment'          => 'Apartment',
        'apartment-complex'  => 'ApartmentComplex',
        'apartmentcomplex'   => 'ApartmentComplex',
        'condo'              => 'Condominium',
        'condominium'        => 'Condominium',
        'house'              => 'SingleFamilyResidence',
        'single-family'      => 'SingleFamilyResidence',
        'singlefamily'       => 'SingleFamilyResidence',
        'townhouse'          => 'Townhouse',
        'townhome'           => 'Townhouse',
        'duplex'             => 'SingleFamilyResidence',
        'multi-family'       => 'MultiFamilyResidence',
        'multifamily'        => 'MultiFamilyResidence',
        'villa'              => 'House',
        'bungalow'           => 'House',
        'commercial'         => 'CommercialBuilding',
        'office'             => 'OfficeBuilding',
        'retail'             => 'Store',
        'land'               => 'Landform',
        'lot'                => 'Landform',
    ];

    private const AVAILABILITY_MAP = [
        'for-sale'       => 'https://schema.org/InStock',
        'for_sale'       => 'https://schema.org/InStock',
        'forsale'        => 'https://schema.org/InStock',
        'forrent'        => 'https://schema.org/InStock',
        'for-rent'       => 'https://schema.org/InStock',
        'rent'           => 'https://schema.org/InStock',
        'lease'          => 'https://schema.org/InStock',
        'leased'         => 'https://schema.org/InStock',
        'sold'           => 'https://schema.org/SoldOut',
        'pending'        => 'https://schema.org/PreOrder',
        'contingent'     => 'https://schema.org/PreOrder',
        'under-contract' => 'https://schema.org/PreOrder',
        'undercontract'  => 'https://schema.org/PreOrder',
        'off-market'     => 'https://schema.org/OutOfStock',
        'offmarket'      => 'https://schema.org/OutOfStock',
    ];

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

        $floorSize    = $this->buildFloorSize($post);
        $lotSize      = $this->buildLotSize($post);
        $images       = $this->collectImages($post);
        $tourUrl      = $this->sanitizeUrl($this->getField($post, 'virtual_tour_url'));
        $propertyType = $this->determinePropertyTypeLabel($post);

        return [
            '@id'                     => trailingslashit(get_permalink($post)) . '#listing',
            'name'                    => get_the_title($post),
            'description'             => $this->sanitizeText($post->post_excerpt ?: $post->post_content),
            'url'                     => esc_url_raw(get_permalink($post)),
            'image'                   => $images,
            'tourBookingPage'         => $tourUrl,
            'address'                 => $address,
            'geo'                     => $geo,
            'numberOfRooms'           => $this->toInt($this->getField($post, 'bedrooms')),
            'numberOfBathroomsTotal'  => $this->toInt($this->getField($post, 'bathrooms')),
            'floorSize'               => $floorSize,
            'lotSize'                 => $lotSize,
            'propertyType'            => $propertyType,
            'identifier'              => $this->sanitizeText((string) $this->getField($post, 'mls_id')),
            'yearBuilt'               => $this->toInt($this->getField($post, 'year_built')),
            'offers'                  => $this->buildOffers($post, $address, $images, $floorSize, $tourUrl, $propertyType),
            'provider'                => $this->buildProvider($post),
        ];
    }

    private function buildOffers(WP_Post $post, array $address, array $images, array $floorSize, ?string $tourUrl, ?string $propertyType): array
    {
        $price        = $this->toFloat($this->getField($post, 'price'));
        $currency     = $this->getField($post, 'price_currency');
        $availability = $this->determineAvailability($post);

        $itemOffered = $this->filterEmpty([
            '@type'                   => $this->determineResidenceType($post),
            'name'                    => get_the_title($post),
            'numberOfRooms'           => $this->toInt($this->getField($post, 'bedrooms')),
            'numberOfBathroomsTotal'  => $this->toInt($this->getField($post, 'bathrooms')),
            'address'                 => $address,
            'floorSize'               => $floorSize,
            'propertyType'            => $propertyType,
            'tourBookingPage'         => $tourUrl,
            'image'                   => $images,
            'floorPlan'               => $this->buildFloorPlans($post),
            'amenityFeature'          => $this->buildAmenityFeatures($post),
        ]);

        return $this->filterEmpty([
            '@type'         => 'Offer',
            'price'         => $price,
            'priceCurrency' => $currency ?: null,
            'availability'  => $availability,
            'url'           => esc_url_raw(get_permalink($post)),
            'itemOffered'   => $itemOffered,
            'seller'        => $this->buildSeller($post),
        ]);
    }

    private function buildSeller(WP_Post $post): array
    {
        $agentId = $this->extractRelationshipId($this->getField($post, 'agent'));
        if ($agentId) {
            $agent = get_post($agentId);
            if ($agent instanceof WP_Post) {
                $seller = $this->buildContactNode($agent, 'RealEstateAgent');
                if (!empty($seller)) {
                    return $seller;
                }
            }
        }

        $name = $this->getField($post, 'seller_name');
        if ($this->isEmpty($name)) {
            return [];
        }

        return $this->filterEmpty([
            '@type'     => 'RealEstateAgent',
            'name'      => $this->sanitizeText((string) $name),
            'url'       => $this->sanitizeUrl($this->getField($post, 'seller_url')),
            'telephone' => $this->formatTelephone($this->getField($post, 'seller_phone')),
        ]);
    }

    private function buildProvider(WP_Post $post): array
    {
        $agencyId = $this->extractRelationshipId($this->getField($post, 'agency'));
        if (!$agencyId) {
            return [];
        }

        $agency = get_post($agencyId);
        if (!$agency instanceof WP_Post) {
            return [];
        }

        return $this->buildContactNode($agency, 'RealEstateAgent', true);
    }

    private function buildContactNode(WP_Post $entity, string $type, bool $includeLogo = false): array
    {
        $urlField = $this->getEntityField($entity->ID, 'website');
        if ($this->isEmpty($urlField)) {
            $urlField = $this->getEntityField($entity->ID, 'url');
        }
        $url = $this->sanitizeUrl($urlField ?: get_permalink($entity));

        $image = get_the_post_thumbnail_url($entity, 'full');

        $data = [
            '@type'     => $type,
            'name'      => $this->sanitizeText(get_the_title($entity)),
            'url'       => $url,
            'telephone' => $this->formatTelephone($this->getEntityField($entity->ID, 'phone')),
            'email'     => $this->sanitizeEmail($this->getEntityField($entity->ID, 'email')),
            'image'     => $image ?: null,
            'sameAs'    => $this->normalizeSameAs($this->getEntityField($entity->ID, 'same_as')),
        ];

        if ($includeLogo && $image) {
            $data['logo'] = $image;
        }

        return $this->filterEmpty($data);
    }

    private function getEntityField(int $postId, string $key): mixed
    {
        if (!function_exists('gm2_field')) {
            return '';
        }

        return \gm2_field($key, '', $postId);
    }

    private function sanitizeEmail(mixed $value): ?string
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $email = sanitize_email((string) $value);
        return $email !== '' ? $email : null;
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

    private function buildLotSize(WP_Post $post): array
    {
        $value = $this->toFloat($this->getField($post, 'lot_size'));
        $unit  = $this->getField($post, 'lot_size_unit');

        if ($value === null && $this->isEmpty($unit)) {
            return [];
        }

        return $this->filterEmpty([
            '@type'    => 'QuantitativeValue',
            'value'    => $value,
            'unitText' => $unit ?: null,
        ]);
    }

    private function collectImages(WP_Post $post): array
    {
        $images = [];

        $featured = get_the_post_thumbnail_url($post, 'full');
        if ($featured) {
            $images[] = esc_url_raw($featured);
        }

        $gallery = $this->getField($post, 'gallery', []);
        if (is_array($gallery)) {
            foreach ($gallery as $item) {
                $url = null;
                if (is_numeric($item)) {
                    $url = wp_get_attachment_url((int) $item);
                } elseif (is_array($item)) {
                    $id = $item['id'] ?? $item['ID'] ?? null;
                    if (is_numeric($id)) {
                        $url = wp_get_attachment_url((int) $id);
                    } elseif (!empty($item['url']) && is_string($item['url'])) {
                        $url = $item['url'];
                    }
                } elseif (is_string($item)) {
                    $url = $item;
                }

                if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                    $images[] = esc_url_raw($url);
                }
            }
        } elseif (is_string($gallery) && filter_var($gallery, FILTER_VALIDATE_URL)) {
            $images[] = esc_url_raw($gallery);
        }

        $images = array_values(array_unique(array_filter($images)));

        return $images;
    }

    private function buildFloorPlans(WP_Post $post): array
    {
        $plans = $this->getField($post, 'floor_plans', []);
        if (!is_array($plans)) {
            return [];
        }

        $output = [];
        foreach ($plans as $plan) {
            if (!is_array($plan)) {
                continue;
            }

            $file = $plan['file'] ?? null;
            $url  = null;

            if (is_numeric($file)) {
                $url = wp_get_attachment_url((int) $file);
            } elseif (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) {
                $url = $file;
            }

            if (!$url) {
                continue;
            }

            $name = isset($plan['title']) && is_string($plan['title']) ? $this->sanitizeText($plan['title']) : null;

            $output[] = $this->filterEmpty([
                '@type' => 'FloorPlan',
                'name'  => $name,
                'image' => esc_url_raw($url),
                'url'   => esc_url_raw($url),
            ]);
        }

        return $output;
    }

    private function buildAmenityFeatures(WP_Post $post): array
    {
        $features = [];

        $features = array_merge(
            $features,
            $this->createAmenityFeatures('Parking', $this->getField($post, 'parking_options', []), 'parking_options')
        );
        $features = array_merge(
            $features,
            $this->createAmenityFeatures('Heating', $this->getField($post, 'heating_types', []), 'heating_types')
        );
        $features = array_merge(
            $features,
            $this->createAmenityFeatures('Cooling', $this->getField($post, 'cooling_types', []), 'cooling_types')
        );

        return $features;
    }

    private function createAmenityFeatures(string $category, mixed $raw, string $fieldKey): array
    {
        $values = $this->normalizeList($raw);
        if ($values === []) {
            return [];
        }

        $labels = $this->resolveChoiceLabels($fieldKey, $values);

        $features = [];
        foreach ($values as $value) {
            $label = $labels[$value] ?? $this->humanizeValue((string) $value);
            $features[] = $this->filterEmpty([
                '@type'    => 'LocationFeatureSpecification',
                'name'     => $label,
                'category' => $category,
                'value'    => true,
            ]);
        }

        return $features;
    }

    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $item) {
                if (is_string($item) || is_numeric($item)) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $normalized[] = $item;
                    }
                }
            }
            return $normalized;
        }

        if (is_string($value)) {
            $parts = preg_split('/[\r\n,]+/', $value) ?: [];
            return array_values(array_filter(array_map('trim', $parts)));
        }

        return [];
    }

    private function resolveChoiceLabels(string $fieldKey, array $values): array
    {
        if (!function_exists('gm2_find_field_definition')) {
            return [];
        }

        $definition = \gm2_find_field_definition($fieldKey);
        if (!is_array($definition)) {
            return [];
        }

        $choices = $definition['choices'] ?? $definition['options'] ?? [];
        if (!is_array($choices)) {
            return [];
        }

        $labels = [];
        foreach ($values as $value) {
            if (isset($choices[$value])) {
                $labels[$value] = $choices[$value];
            }
        }

        return $labels;
    }

    private function humanizeValue(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return ucwords($value);
    }

    private function determineResidenceType(WP_Post $post): string
    {
        $terms = $this->getTerms($post, 'property_type');
        if ($terms === []) {
            return 'Residence';
        }

        foreach ($terms as $term) {
            $slug = strtolower($term->slug);
            $type = $this->mapResidenceType($slug);
            if ($type) {
                return $type;
            }

            $type = $this->mapResidenceType(sanitize_title($term->name));
            if ($type) {
                return $type;
            }
        }

        return 'Residence';
    }

    private function mapResidenceType(string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }

        $slug = strtolower($slug);
        return self::RESIDENCE_TYPE_MAP[$slug] ?? null;
    }

    private function determineAvailability(WP_Post $post): ?string
    {
        $terms = $this->getTerms($post, 'property_status');
        if ($terms === []) {
            return null;
        }

        foreach ($terms as $term) {
            $slug = strtolower($term->slug);
            if (isset(self::AVAILABILITY_MAP[$slug])) {
                return self::AVAILABILITY_MAP[$slug];
            }
        }

        $slug = strtolower($terms[0]->slug);
        if (strpos($slug, 'sold') !== false) {
            return 'https://schema.org/SoldOut';
        }
        if (strpos($slug, 'pending') !== false || strpos($slug, 'contingent') !== false) {
            return 'https://schema.org/PreOrder';
        }
        if (strpos($slug, 'off') !== false) {
            return 'https://schema.org/OutOfStock';
        }
        if (strpos($slug, 'rent') !== false || strpos($slug, 'lease') !== false) {
            return 'https://schema.org/InStock';
        }

        return null;
    }

    private function determinePropertyTypeLabel(WP_Post $post): ?string
    {
        $terms = $this->getTerms($post, 'property_type');
        if ($terms === []) {
            return null;
        }

        $names = [];
        foreach ($terms as $term) {
            $name = trim($term->name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        if ($names === []) {
            return null;
        }

        if (count($names) === 1) {
            return $names[0];
        }

        return implode(', ', $names);
    }

    /**
     * @return array<int, WP_Term>
     */
    private function getTerms(WP_Post $post, string $taxonomy): array
    {
        $terms = wp_get_post_terms($post->ID, $taxonomy);
        if (is_wp_error($terms) || !is_array($terms) || $terms === []) {
            return [];
        }

        return array_values(array_filter($terms, static fn ($term) => $term instanceof WP_Term));
    }

    private function sanitizeUrl(mixed $value): ?string
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $url = esc_url_raw((string) $value);
        return $url !== '' ? $url : null;
    }

    private function extractRelationshipId(mixed $value): ?int
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    $id = (int) $item;
                    if ($id > 0) {
                        return $id;
                    }
                }
            }

            return null;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function normalizeSameAs(mixed $value): array
    {
        if ($this->isEmpty($value)) {
            return [];
        }

        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
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

    private function formatTelephone(mixed $value): ?string
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        return preg_replace('/[^0-9+\-().\s]/', '', (string) $value);
    }
}
