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

    public function test_job_mapper_includes_salary(): void
    {
        $this->registerPostType('job');
        $post_id = self::factory()->post->create([
            'post_type' => 'job',
            'post_title' => 'Developer',
            'post_content' => 'Build systems.',
        ]);

        update_post_meta($post_id, 'date_posted', '2024-03-01');
        update_post_meta($post_id, 'employment_type', 'Full-time');
        update_post_meta($post_id, 'company', 'Example Inc');
        update_post_meta($post_id, 'salary_currency', 'USD');
        update_post_meta($post_id, 'salary_value', '90000');
        update_post_meta($post_id, 'job_city', 'Gotham');

        $mapper = new JobMapper();
        $result = $mapper->map(get_post($post_id));

        $this->assertIsArray($result);
        $this->assertSame('JobPosting', $result['@type']);
        $this->assertSame('USD', $result['baseSalary']['currency']);
        $this->assertSame(90000.0, $result['baseSalary']['value']['value']);
        $this->assertSame('Example Inc', $result['hiringOrganization']['name']);
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
        $post_id = self::factory()->post->create([
            'post_type' => 'property',
            'post_title' => 'Luxury Condo',
        ]);

        update_post_meta($post_id, 'address', '200 Market Street');
        update_post_meta($post_id, 'city', 'Metropolis');
        update_post_meta($post_id, 'price', '750000');
        update_post_meta($post_id, 'price_currency', 'USD');
        update_post_meta($post_id, 'bedrooms', '3');

        $mapper = new RealEstateMapper();
        $result = $mapper->map(get_post($post_id));

        $this->assertIsArray($result);
        $this->assertSame('RealEstateListing', $result['@type']);
        $this->assertSame(3, $result['numberOfRooms']);
        $this->assertSame(750000.0, $result['offers']['price']);
        $this->assertSame('USD', $result['offers']['priceCurrency']);
        $this->assertSame('Residence', $result['offers']['itemOffered']['@type']);
    }
}
