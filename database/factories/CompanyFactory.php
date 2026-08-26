<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $companyName = $this->faker->company();
        $domain = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $companyName)) . '.com';

        return [
            'name'                  => $companyName,
            'clean_root_domain'     => $domain,
            'website_status'        => '✔ HTTP 200 OK',
            'company_linkedin_page' => 'https://www.linkedin.com/company/' . $this->faker->slug(1),
            'industry_id'           => Industry::factory(),
            'location_id'           => Location::factory(),
            'employee_headcount'    => $this->faker->numberBetween(5, 500),
        ];
    }
}
