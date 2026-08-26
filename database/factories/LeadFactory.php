<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating realistic B2B lead test data.
 *
 * Usage:
 *   Lead::factory()->create()                    // Single lead
 *   Lead::factory()->count(100)->create()         // 100 leads
 *   Lead::factory()->clevel()->create()           // C-Level lead
 *   Lead::factory()->vpLevel()->fromPortugal()->create()
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * Industries matching the real dataset distribution.
     */
    private const INDUSTRIES = [
        'Software', 'Financial Services', 'Real Estate', 'Artificial Intelligence',
        'Healthcare Technology', 'Cybersecurity', 'Education', 'Consulting',
        'Media Production', 'Events', 'Legal Services', 'Human Resources',
        'Staffing & Recruiting', 'Fashion & Apparel', 'Consumer Goods',
        'Venture Capital', 'Publishing', 'Manufacturing', 'Data & Analytics',
        'Public Relations', 'Sustainability', 'Sports Technology', 'Hospitality',
        'Content & Writing', 'Data Science', 'Fitness & Wellness', 'Media',
        'Banking / Financial Services', 'Aerospace', 'E-Commerce',
    ];

    /**
     * Countries matching the real dataset distribution (European-heavy).
     */
    private const COUNTRIES = [
        'Portugal', 'Portugal', 'Portugal', 'Portugal', // weighted toward Portugal
        'Austria', 'Austria',
        'Ireland', 'Ireland',
        'Spain', 'Iceland', 'India', 'Germany', 'France', 'Netherlands',
    ];

    /**
     * Cities grouped by country for realistic HQ locations.
     */
    private const CITIES_BY_COUNTRY = [
        'Portugal' => [
            'Lisbon, Lisbon, Portugal',
            'Porto, Porto, Portugal',
            'Cascais, Lisbon, Portugal',
            'Braga, Braga, Portugal',
            'Coimbra, Coimbra, Portugal',
            'Aveiro, Aveiro, Portugal',
        ],
        'Austria' => [
            'Vienna, Vienna, Austria',
            'Graz, Styria, Austria',
            'Salzburg, Salzburg, Austria',
        ],
        'Ireland' => [
            'Dublin, Dublin, Ireland',
            'Cork, Cork, Ireland',
            'Galway, Connacht, Ireland',
        ],
        'Spain' => [
            'Madrid, Madrid, Spain',
            'Barcelona, Catalonia, Spain',
        ],
        'Iceland' => ['Reykjavik, Capital Region, Iceland'],
        'India' => ['Bangalore, Karnataka, India', 'Mumbai, Maharashtra, India'],
        'Germany' => ['Berlin, Berlin, Germany', 'Munich, Bavaria, Germany'],
        'France' => ['Paris, Île-de-France, France', 'Lyon, Auvergne-Rhône-Alpes, France'],
        'Netherlands' => ['Amsterdam, North Holland, Netherlands'],
    ];

    /**
     * Job titles by tier for realistic generation.
     */
    private const TITLES_BY_TIER = [
        'C-Level' => [
            'CEO', 'CEO & Founder', 'CEO & Co-Founder', 'Co-Founder',
            'Founder', 'Founder & CEO', 'Chief Executive Officer',
            'President', 'Owner', 'Co-Founder & Partner',
            'Founder & Chief Executive', 'Founder & Partner',
            'Founder & Chief Data Scientist',
        ],
        'VP-Level' => [
            'VP of Sales', 'VP of Marketing', 'VP of Engineering',
            'VP of Business Development', 'VP of Operations',
            'Senior Vice President', 'Executive Vice President',
            'VP of Product', 'VP of Strategy',
        ],
        'Director-Level' => [
            'Director of Marketing', 'Director of Sales',
            'Director of Engineering', 'Director of Operations',
            'Founder & Managing Director', 'Managing Director',
            'Director of Business Development', 'Creative Director',
            'Technical Director', 'Director of Product',
        ],
        'Other' => [
            'Senior Manager', 'Head of Growth', 'Head of Sales',
            'Head of Marketing', 'Lead Engineer', 'Principal Consultant',
            'Senior Analyst', 'Program Manager', 'Strategy Lead',
        ],
    ];

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $tier = $this->faker->randomElement(Lead::TITLE_TIERS);
        $country = $this->faker->randomElement(self::COUNTRIES);
        $cities = self::CITIES_BY_COUNTRY[$country] ?? ["{$country} City, {$country}"];
        $companyName = $this->faker->company();
        $domain = $this->generateCleanDomain($companyName);

        return [
            'full_name'               => $this->faker->name(),
            'job_title'               => $this->faker->randomElement(self::TITLES_BY_TIER[$tier]),
            'title_tier'              => $tier,
            'corporate_email'         => $this->faker->unique()->safeEmail(),
            'email_status'            => $this->faker->randomElement(['✔ Valid email', '✅ Valid email', '⚠ Catch-all']),
            'company_name'            => $companyName,
            'clean_root_domain'       => $domain,
            'website_status'          => $this->faker->randomElement(['✔ HTTP 200 OK', 'HTTP 200 OK', '⚠ HTTP 301', '❌ Timeout']),
            'executive_linkedin_url'  => 'https://www.linkedin.com/in/' . $this->faker->slug(2),
            'company_linkedin_page'   => 'https://www.linkedin.com/company/' . $this->faker->slug(1),
            'industry_classification' => $this->faker->randomElement(self::INDUSTRIES),
            'employee_headcount'      => $this->faker->optional(0.8)->numberBetween(5, 500),
            'hq_location'             => $this->faker->randomElement($cities),
            'country'                 => $country,
            'ingestion_channel'       => 'n8n',
            'status'                  => $this->faker->randomElement(Lead::STATUSES),
            'notes'                   => $this->faker->optional(0.2)->sentence(),
        ];
    }

    // ─── State Methods ─────────────────────────────────────────

    /** Set title tier to C-Level with a matching job title. */
    public function clevel(): static
    {
        return $this->state(fn() => [
            'title_tier' => 'C-Level',
            'job_title'  => $this->faker->randomElement(self::TITLES_BY_TIER['C-Level']),
        ]);
    }

    /** Set title tier to VP-Level with a matching job title. */
    public function vpLevel(): static
    {
        return $this->state(fn() => [
            'title_tier' => 'VP-Level',
            'job_title'  => $this->faker->randomElement(self::TITLES_BY_TIER['VP-Level']),
        ]);
    }

    /** Set title tier to Director-Level with a matching job title. */
    public function directorLevel(): static
    {
        return $this->state(fn() => [
            'title_tier' => 'Director-Level',
            'job_title'  => $this->faker->randomElement(self::TITLES_BY_TIER['Director-Level']),
        ]);
    }

    /** Set country to Portugal with a Portuguese city. */
    public function fromPortugal(): static
    {
        return $this->state(fn() => [
            'country'     => 'Portugal',
            'hq_location' => $this->faker->randomElement(self::CITIES_BY_COUNTRY['Portugal']),
        ]);
    }

    /** Set country to Austria with an Austrian city. */
    public function fromAustria(): static
    {
        return $this->state(fn() => [
            'country'     => 'Austria',
            'hq_location' => $this->faker->randomElement(self::CITIES_BY_COUNTRY['Austria']),
        ]);
    }

    /** Set country to Ireland with an Irish city. */
    public function fromIreland(): static
    {
        return $this->state(fn() => [
            'country'     => 'Ireland',
            'hq_location' => $this->faker->randomElement(self::CITIES_BY_COUNTRY['Ireland']),
        ]);
    }

    /** Mark as new lead (default status). */
    public function statusNew(): static
    {
        return $this->state(fn() => ['status' => 'new']);
    }

    /** Mark as reviewed. */
    public function reviewed(): static
    {
        return $this->state(fn() => ['status' => 'reviewed']);
    }

    /** Mark as qualified. */
    public function qualified(): static
    {
        return $this->state(fn() => ['status' => 'qualified']);
    }

    /** Mark as rejected. */
    public function rejected(): static
    {
        return $this->state(fn() => ['status' => 'rejected']);
    }

    /** Set ingestion channel to n8n (simulating webhook). */
    public function viaN8n(): static
    {
        return $this->state(fn() => ['ingestion_channel' => 'n8n']);
    }

    /** Set ingestion channel to CSV import. */
    public function viaCsvImport(): static
    {
        return $this->state(fn() => ['ingestion_channel' => 'csv_import']);
    }

    /**
     * Generate a clean root domain from a company name.
     */
    private function generateCleanDomain(string $companyName): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $companyName));
        $tlds = ['.com', '.io', '.net', '.pt', '.ie', '.ai', '.co', '.consulting'];
        return $slug . $this->faker->randomElement($tlds);
    }
}
