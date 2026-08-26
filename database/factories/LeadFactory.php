<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating realistic B2B lead test data.
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

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

    private const COUNTRIES = [
        'Portugal', 'Portugal', 'Portugal', 'Portugal',
        'Austria', 'Austria',
        'Ireland', 'Ireland',
        'Spain', 'Iceland', 'India', 'Germany', 'France', 'Netherlands',
    ];

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
        $countryName = $this->faker->randomElement(self::COUNTRIES);
        $cities = self::CITIES_BY_COUNTRY[$countryName] ?? ["{$countryName} City, {$countryName}"];
        $rawLocation = $this->faker->randomElement($cities);
        $industryName = $this->faker->randomElement(self::INDUSTRIES);

        $industry = Industry::firstOrCreate(['name' => $industryName]);
        $country = Country::firstOrCreate(['name' => $countryName]);
        $location = Location::firstOrCreate(
            ['raw_location' => $rawLocation],
            ['country_id' => $country->id]
        );

        $companyName = $this->faker->company();
        $domain = $this->generateCleanDomain($companyName);

        $company = Company::firstOrCreate(
            ['clean_root_domain' => $domain],
            [
                'name'                  => $companyName,
                'website_status'        => $this->faker->randomElement(['✔ HTTP 200 OK', 'HTTP 200 OK', '⚠ HTTP 301']),
                'company_linkedin_page' => 'https://www.linkedin.com/company/' . $this->faker->slug(1),
                'industry_id'           => $industry->id,
                'location_id'           => $location->id,
                'employee_headcount'    => $this->faker->optional(0.8)->numberBetween(5, 500),
            ]
        );

        return [
            'company_id'             => $company->id,
            'full_name'              => $this->faker->name(),
            'job_title'              => $this->faker->randomElement(self::TITLES_BY_TIER[$tier]),
            'title_tier'             => $tier,
            'corporate_email'        => $this->faker->unique()->safeEmail(),
            'email_status'           => $this->faker->randomElement(['✔ Valid email', '✅ Valid email', '⚠ Catch-all']),
            'executive_linkedin_url' => 'https://www.linkedin.com/in/' . $this->faker->slug(2),
            'ingestion_channel'      => 'n8n',
            'status'                 => $this->faker->randomElement(Lead::STATUSES),
            'notes'                  => $this->faker->optional(0.2)->sentence(),
        ];
    }

    public function clevel(): static
    {
        return $this->state(fn() => [
            'title_tier' => 'C-Level',
            'job_title'  => $this->faker->randomElement(self::TITLES_BY_TIER['C-Level']),
        ]);
    }

    public function vpLevel(): static
    {
        return $this->state(fn() => [
            'title_tier' => 'VP-Level',
            'job_title'  => $this->faker->randomElement(self::TITLES_BY_TIER['VP-Level']),
        ]);
    }

    public function directorLevel(): static
    {
        return $this->state(fn() => [
            'title_tier' => 'Director-Level',
            'job_title'  => $this->faker->randomElement(self::TITLES_BY_TIER['Director-Level']),
        ]);
    }

    public function fromCountry(string $countryName): static
    {
        return $this->afterCreating(function (Lead $lead) use ($countryName) {
            $cities = self::CITIES_BY_COUNTRY[$countryName] ?? ["{$countryName} City, {$countryName}"];
            $rawLocation = $this->faker->randomElement($cities);

            $country = Country::firstOrCreate(['name' => $countryName]);
            $location = Location::firstOrCreate(
                ['raw_location' => $rawLocation],
                ['country_id' => $country->id]
            );

            if ($lead->company) {
                $lead->company->update(['location_id' => $location->id]);
            }
        });
    }

    public function fromPortugal(): static
    {
        return $this->fromCountry('Portugal');
    }

    public function fromAustria(): static
    {
        return $this->fromCountry('Austria');
    }

    public function fromIreland(): static
    {
        return $this->fromCountry('Ireland');
    }

    public function statusNew(): static
    {
        return $this->state(fn() => ['status' => 'new']);
    }

    public function reviewed(): static
    {
        return $this->state(fn() => ['status' => 'reviewed']);
    }

    public function qualified(): static
    {
        return $this->state(fn() => ['status' => 'qualified']);
    }

    public function rejected(): static
    {
        return $this->state(fn() => ['status' => 'rejected']);
    }

    public function viaN8n(): static
    {
        return $this->state(fn() => ['ingestion_channel' => 'n8n']);
    }

    public function viaCsvImport(): static
    {
        return $this->state(fn() => ['ingestion_channel' => 'csv_import']);
    }

    private function generateCleanDomain(string $companyName): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $companyName));
        $tlds = ['.com', '.io', '.net', '.pt', '.ie', '.ai', '.co', '.consulting'];
        return $slug . $this->faker->randomElement($tlds);
    }
}
