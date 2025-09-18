<?php

use Gm2\SEO\Schema\Mapper\CourseMapper;
use Gm2\SEO\Schema\Mapper\DirectoryMapper;
use Gm2\SEO\Schema\Mapper\EventMapper;
use Gm2\SEO\Schema\Mapper\JobMapper;
use Gm2\SEO\Schema\Mapper\RealEstateMapper;
use WP_Error;

class SchemaMapperTest extends WP_UnitTestCase
{
    private array $registered = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->registered = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->registered as $slug) {
            if (post_type_exists($slug)) {
                unregister_post_type($slug);
            }
        }

        parent::tearDown();
    }

    private function registerPostType(string $slug): void
    {
        if (!post_type_exists($slug)) {
            register_post_type($slug, ['public' => true]);
            $this->registered[] = $slug;
        }
    }

    public function test_directory_mapper_requires_address(): void
    {
        $this->registerPostType('listing');
        $post_id = self::factory()->post->create([
            'post_type' => 'listing',
            'post_title' => 'Sample Listing',
        ]);

        $mapper = new DirectoryMapper();
        $result = $mapper->map(get_post($post_id));
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_directory_mapper_builds_schema(): void
    {
        $this->registerPostType('listing');
        $post_id = self::factory()->post->create([
            'post_type' => 'listing',
            'post_title' => 'Cafe 123',
            'post_content' => 'Great coffee and pastries.',
        ]);

        update_post_meta($post_id, 'address', '123 Main St');
        update_post_meta($post_id, 'city', 'Metropolis');
        update_post_meta($post_id, 'region', 'CA');
        update_post_meta($post_id, 'postal_code', '90001');
        update_post_meta($post_id, 'country', 'US');
        update_post_meta($post_id, 'phone', '(555) 555-0100');
        update_post_meta($post_id, 'website', 'https://example.com/cafe');
        update_post_meta($post_id, 'latitude', '34.05');
        update_post_meta($post_id, 'longitude', '-118.25');
        update_post_meta($post_id, 'opening_hours', [
            ['dayOfWeek' => 'Monday', 'opens' => '09:00', 'closes' => '17:00'],
        ]);

        $mapper = new DirectoryMapper();
        $result = $mapper->map(get_post($post_id));

        $this->assertIsArray($result);
        $this->assertSame('LocalBusiness', $result['@type']);
        $this->assertSame('123 Main St', $result['address']['streetAddress']);
        $this->assertSame('Metropolis', $result['address']['addressLocality']);
        $this->assertSame('CA', $result['address']['addressRegion']);
        $this->assertSame('(555) 555-0100', $result['telephone']);
        $this->assertSame('https://example.com/cafe', $result['url']);
        $this->assertSame(34.05, $result['geo']['latitude']);
        $this->assertSame(-118.25, $result['geo']['longitude']);
        $this->assertSame('OpeningHoursSpecification', $result['openingHoursSpecification'][0]['@type']);
    }

    public function test_event_mapper_requires_fields(): void
    {
        $this->registerPostType('event');
        $post_id = self::factory()->post->create([
            'post_type' => 'event',
            'post_title' => 'Conf',
        ]);

        $mapper = new EventMapper();
        $result = $mapper->map(get_post($post_id));
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_event_mapper_builds_schema(): void
    {
        $this->registerPostType('event');
        $post_id = self::factory()->post->create([
            'post_type' => 'event',
            'post_title' => 'Product Launch',
        ]);

        update_post_meta($post_id, 'start_date', '2024-07-01T09:00:00-05:00');
        update_post_meta($post_id, 'end_date', '2024-07-01T11:00:00-05:00');
        update_post_meta($post_id, 'location', 'Downtown HQ');
        update_post_meta($post_id, 'location_city', 'Springfield');
        update_post_meta($post_id, 'ticket_price', '25');
        update_post_meta($post_id, 'ticket_currency', 'USD');

        $mapper = new EventMapper();
        $result = $mapper->map(get_post($post_id));

        $this->assertIsArray($result);
        $this->assertSame('Event', $result['@type']);
        $this->assertSame('2024-07-01T09:00:00-05:00', $result['startDate']);
        $this->assertSame('Downtown HQ', $result['location']['name']);
        $this->assertSame('USD', $result['offers']['priceCurrency']);
    }

    public function test_job_mapper_outputs_comprehensive_fields(): void
    {
        $this->registerPostType('job');
        $this->registerPostType('company');

        $company_id = self::factory()->post->create([
            'post_type' => 'company',
            'post_title' => 'Example Inc',
            'post_content' => 'Global technology company.',
        ]);

        $post_id = self::factory()->post->create([
            'post_type' => 'job',
            'post_title' => 'Developer',
            'post_content' => 'Build systems that scale.',
        ]);

        update_post_meta($post_id, 'date_posted', '2024-03-01');
        update_post_meta($post_id, 'valid_through', '2024-05-01');
        update_post_meta($post_id, 'employment_type', ['FULL_TIME']);
        update_post_meta($post_id, 'job_location_type', 'Hybrid');
        update_post_meta($post_id, 'job_location_name', 'Innovation Hub');
        update_post_meta($post_id, 'job_street', '123 Market Street');
        update_post_meta($post_id, 'job_city', 'Metropolis');
        update_post_meta($post_id, 'job_region', 'CA');
        update_post_meta($post_id, 'job_postal_code', '90001');
        update_post_meta($post_id, 'job_country', 'US');
        update_post_meta($post_id, 'job_latitude', '34.05');
        update_post_meta($post_id, 'job_longitude', '-118.25');
        update_post_meta($post_id, 'salary_currency', 'USD');
        update_post_meta($post_id, 'salary_min', '90000');
        update_post_meta($post_id, 'salary_max', '120000');
        update_post_meta($post_id, 'salary_unit_text', 'YEAR');
        update_post_meta($post_id, 'job_benefits', "Health insurance\nRemote stipend");
        update_post_meta($post_id, 'education_level', 'BachelorsDegree');
        update_post_meta($post_id, 'experience_requirements', '5+ years experience leading engineering teams.');
        update_post_meta($post_id, 'apply_email', 'apply@example.com');
        update_post_meta($post_id, 'apply_url', 'https://example.com/jobs/apply');
        update_post_meta($post_id, 'company', [$company_id]);

        $mapper = new JobMapper();
        $result = $mapper->map(get_post($post_id));

        $this->assertIsArray($result);
        $this->assertSame('JobPosting', $result['@type']);
        $this->assertSame('Hybrid', $result['jobLocationType']);
        $this->assertSame('FULL_TIME', $result['employmentType']);

        $company_url = esc_url_raw(get_permalink($company_id));
        $this->assertSame('Organization', $result['hiringOrganization']['@type']);
        $this->assertSame('Example Inc', $result['hiringOrganization']['name']);
        $this->assertSame($company_url, $result['hiringOrganization']['url']);

        $this->assertSame('Place', $result['jobLocation']['@type']);
        $this->assertSame('PostalAddress', $result['jobLocation']['address']['@type']);
        $this->assertSame('123 Market Street', $result['jobLocation']['address']['streetAddress']);
        $this->assertSame('Metropolis', $result['jobLocation']['address']['addressLocality']);
        $this->assertSame('CA', $result['jobLocation']['address']['addressRegion']);
        $this->assertSame('US', $result['jobLocation']['address']['addressCountry']);
        $this->assertSame('GeoCoordinates', $result['jobLocation']['geo']['@type']);
        $this->assertSame(34.05, $result['jobLocation']['geo']['latitude']);
        $this->assertSame(-118.25, $result['jobLocation']['geo']['longitude']);

        $this->assertSame('MonetaryAmount', $result['baseSalary']['@type']);
        $this->assertSame('USD', $result['baseSalary']['currency']);
        $this->assertSame('QuantitativeValue', $result['baseSalary']['value']['@type']);
        $this->assertSame(90000.0, $result['baseSalary']['value']['minValue']);
        $this->assertSame(120000.0, $result['baseSalary']['value']['maxValue']);
        $this->assertSame('YEAR', $result['baseSalary']['value']['unitText']);

        $this->assertIsArray($result['offers']);
        $this->assertCount(1, $result['offers']);
        $this->assertSame('Offer', $result['offers'][0]['@type']);
        $this->assertSame('USD', $result['offers'][0]['priceCurrency']);
        $this->assertSame(90000.0, $result['offers'][0]['price']);
        $this->assertSame('PriceSpecification', $result['offers'][0]['priceSpecification']['@type']);
        $this->assertSame(90000.0, $result['offers'][0]['priceSpecification']['minPrice']);
        $this->assertSame(120000.0, $result['offers'][0]['priceSpecification']['maxPrice']);
        $this->assertSame('YEAR', $result['offers'][0]['priceSpecification']['unitText']);

        $this->assertSame('ContactPoint', $result['applicationContact']['@type']);
        $this->assertSame('apply@example.com', $result['applicationContact']['email']);
        $this->assertSame('https://example.com/jobs/apply', $result['applicationContact']['url']);
        $this->assertSame('https://example.com/jobs/apply', $result['url']);
        $this->assertSame('2024-05-01', $result['validThrough']);
        $this->assertStringContainsString('Health insurance', $result['jobBenefits']);
        $this->assertSame('BachelorsDegree', $result['educationRequirements']);
        $this->assertStringContainsString('5+ years', $result['experienceRequirements']);
    }

    public function test_job_mapper_supports_remote_jobs(): void
    {
        $this->registerPostType('job');
        $this->registerPostType('company');

        $company_id = self::factory()->post->create([
            'post_type' => 'company',
            'post_title' => 'Remote Co',
        ]);

        $post_id = self::factory()->post->create([
            'post_type' => 'job',
            'post_title' => 'Remote Support Specialist',
        ]);

        update_post_meta($post_id, 'date_posted', '2024-04-10');
        update_post_meta($post_id, 'employment_type', ['PART_TIME']);
        update_post_meta($post_id, 'job_location_type', 'Remote');
        update_post_meta($post_id, 'salary_currency', 'USD');
        update_post_meta($post_id, 'salary_min', '40000');
        update_post_meta($post_id, 'company', [$company_id]);
        update_post_meta($post_id, 'apply_url', 'https://example.com/jobs/remote-support');

        $mapper = new JobMapper();
        $result = $mapper->map(get_post($post_id));

        $this->assertSame('Remote', $result['jobLocationType']);
        $this->assertArrayNotHasKey('jobLocation', $result);
        $this->assertSame('PART_TIME', $result['employmentType']);
        $this->assertSame('https://example.com/jobs/remote-support', $result['url']);
        $this->assertSame('Offer', $result['offers'][0]['@type']);
        $this->assertSame('USD', $result['offers'][0]['priceCurrency']);
        $this->assertSame(40000.0, $result['offers'][0]['price']);
        $this->assertSame(40000.0, $result['baseSalary']['value']['minValue']);
    }

    public function test_course_mapper_builds_instance(): void
    {
        $this->registerPostType('course');
        $post_id = self::factory()->post->create([
            'post_type' => 'course',
            'post_title' => 'Robotics 101',
        ]);

        update_post_meta($post_id, 'provider', 'Robotics Academy');
        update_post_meta($post_id, 'course_code', 'ROB101');
        update_post_meta($post_id, 'course_instance_name', 'Spring Session');
        update_post_meta($post_id, 'course_instance_start', '2024-04-01');
        update_post_meta($post_id, 'course_instance_end', '2024-06-01');
        update_post_meta($post_id, 'course_location_region', 'CA');

        $mapper = new CourseMapper();
        $result = $mapper->map(get_post($post_id));

        $this->assertIsArray($result);
        $this->assertSame('Course', $result['@type']);
        $this->assertSame('ROB101', $result['courseCode']);
        $this->assertSame('CourseInstance', $result['courseInstance']['@type']);
        $this->assertSame('Spring Session', $result['courseInstance']['name']);
        $this->assertSame('CA', $result['courseInstance']['location']['address']['addressRegion']);
    }

    public function test_real_estate_mapper_builds_offer(): void
    {
        $this->registerPostType('property');
        $this->registerPostType('agent');
        $this->registerPostType('agency');

        register_taxonomy('property_type', 'property', ['public' => true]);
        register_taxonomy('property_status', 'property', ['public' => true]);

        wp_insert_term('House', 'property_type', ['slug' => 'house']);
        wp_insert_term('For Sale', 'property_status', ['slug' => 'for-sale']);

        $post_id = self::factory()->post->create([
            'post_type' => 'property',
            'post_title' => 'Luxury Condo',
        ]);

        update_post_meta($post_id, 'address', '200 Market Street');
        update_post_meta($post_id, 'city', 'Metropolis');
        update_post_meta($post_id, 'price', '750000');
        update_post_meta($post_id, 'price_currency', 'USD');
        update_post_meta($post_id, 'bedrooms', '3');
        update_post_meta($post_id, 'bathrooms', '2.5');
        update_post_meta($post_id, 'virtual_tour_url', 'https://example.com/tour');
        update_post_meta($post_id, 'parking_options', ['attached_garage']);
        update_post_meta($post_id, 'heating_types', ['forced_air']);
        update_post_meta($post_id, 'cooling_types', ['central_air']);

        $agent_id = self::factory()->post->create([
            'post_type' => 'agent',
            'post_title' => 'Agent Jane',
        ]);
        update_post_meta($agent_id, 'phone', '(555) 555-0101');
        update_post_meta($agent_id, 'email', 'jane@example.com');
        update_post_meta($agent_id, 'website', 'https://example.com/agents/jane');

        $agency_id = self::factory()->post->create([
            'post_type' => 'agency',
            'post_title' => 'Prime Realty',
        ]);
        update_post_meta($agency_id, 'phone', '(555) 555-0200');
        update_post_meta($agency_id, 'email', 'office@example.com');
        update_post_meta($agency_id, 'website', 'https://example.com');

        update_post_meta($post_id, 'agent', [$agent_id]);
        update_post_meta($post_id, 'agency', [$agency_id]);

        wp_set_object_terms($post_id, 'house', 'property_type');
        wp_set_object_terms($post_id, 'for-sale', 'property_status');

        $gallery_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg', $post_id);
        $floorplan_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/codeispoetry.png', $post_id);

        update_post_meta($post_id, 'gallery', [$gallery_id]);
        update_post_meta($post_id, 'floor_plans', [
            [
                'title' => 'Main Level',
                'file'  => $floorplan_id,
            ],
        ]);

        $mapper = new RealEstateMapper();
        $result = $mapper->map(get_post($post_id));

        unregister_taxonomy('property_type');
        unregister_taxonomy('property_status');

        $this->assertIsArray($result);
        $this->assertSame('RealEstateListing', $result['@type']);
        $this->assertSame(3, $result['numberOfRooms']);
        $this->assertSame(750000.0, $result['offers']['price']);
        $this->assertSame('USD', $result['offers']['priceCurrency']);
        $this->assertSame('SingleFamilyResidence', $result['offers']['itemOffered']['@type']);
        $this->assertSame('House', $result['offers']['itemOffered']['propertyType']);
        $this->assertSame('https://schema.org/InStock', $result['offers']['availability']);
        $this->assertSame('https://example.com/tour', $result['tourBookingPage']);
        $this->assertSame('https://example.com/tour', $result['offers']['itemOffered']['tourBookingPage']);
        $this->assertNotEmpty($result['image']);
        $this->assertSame($result['image'], $result['offers']['itemOffered']['image']);
        $this->assertSame('FloorPlan', $result['offers']['itemOffered']['floorPlan'][0]['@type']);
        $this->assertSame('Main Level', $result['offers']['itemOffered']['floorPlan'][0]['name']);
        $categories = wp_list_pluck($result['offers']['itemOffered']['amenityFeature'], 'category');
        $this->assertContains('Parking', $categories);
        $this->assertContains('Heating', $categories);
        $this->assertContains('Cooling', $categories);
        $this->assertSame('RealEstateAgent', $result['offers']['seller']['@type']);
        $this->assertSame('Agent Jane', $result['offers']['seller']['name']);
        $this->assertSame('RealEstateAgent', $result['provider']['@type']);
        $this->assertSame('Prime Realty', $result['provider']['name']);
    }
}
