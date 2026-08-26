<?php

namespace Tests\Unit;

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_industry_relationships(): void
    {
        $industry = Industry::create(['name' => 'Fintech']);
        
        $company1 = Company::create(['name' => 'PayCorp', 'industry_id' => $industry->id]);
        $company2 = Company::create(['name' => 'BankFlow', 'industry_id' => $industry->id]);

        $this->assertCount(2, $industry->companies);
        $this->assertTrue($industry->companies->contains($company1));
        $this->assertTrue($industry->companies->contains($company2));
    }

    public function test_country_and_location_relationships(): void
    {
        $country = Country::create(['name' => 'Germany', 'code' => 'DE']);
        $location = Location::create([
            'raw_location' => 'Berlin, Berlin, Germany',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'country_id' => $country->id,
        ]);

        $this->assertEquals($country->id, $location->country->id);
        $this->assertCount(1, $country->locations);
        $this->assertTrue($country->locations->contains($location));
    }

    public function test_company_relationships_and_accessors(): void
    {
        $country = Country::create(['name' => 'France', 'code' => 'FR']);
        $location = Location::create([
            'raw_location' => 'Paris, Ile-de-France, France',
            'country_id' => $country->id,
        ]);
        $industry = Industry::create(['name' => 'Retail']);

        $company = Company::create([
            'name' => 'Luxe Brands',
            'clean_root_domain' => 'luxebrands.fr',
            'website_status' => 'HTTP 200 OK',
            'company_linkedin_page' => 'https://linkedin.com/company/luxebrands',
            'employee_headcount' => 250,
            'industry_id' => $industry->id,
            'location_id' => $location->id,
        ]);

        $this->assertEquals('Retail', $company->industry->name);
        $this->assertEquals('Paris, Ile-de-France, France', $company->location->raw_location);
        $this->assertEquals('France', $company->location->country->name);

        $lead = Lead::create([
            'company_id' => $company->id,
            'full_name' => 'Claire Dupont',
            'job_title' => 'Chief Executive Officer',
            'title_tier' => 'C-Level',
            'corporate_email' => 'claire@luxebrands.fr',
        ]);

        $this->assertCount(1, $company->leads);
        $this->assertEquals($lead->id, $company->leads->first()->id);
    }

    public function test_lead_dynamic_accessors_and_appends(): void
    {
        $country = Country::create(['name' => 'Spain', 'code' => 'ES']);
        $location = Location::create([
            'raw_location' => 'Madrid, Madrid, Spain',
            'country_id' => $country->id,
        ]);
        $industry = Industry::create(['name' => 'Logistics']);

        $company = Company::create([
            'name' => 'Iberia Cargo',
            'clean_root_domain' => 'iberiacargo.es',
            'website_status' => 'HTTP 200 OK',
            'company_linkedin_page' => 'https://linkedin.com/company/iberiacargo',
            'employee_headcount' => 120,
            'industry_id' => $industry->id,
            'location_id' => $location->id,
        ]);

        $lead = Lead::create([
            'company_id' => $company->id,
            'full_name' => 'Carlos Sainz',
            'job_title' => 'VP of Operations',
            'title_tier' => 'VP-Level',
            'corporate_email' => 'carlos@iberiacargo.es',
            'email_status' => 'Valid',
            'executive_linkedin_url' => 'https://linkedin.com/in/carlos-sainz',
            'ingestion_channel' => 'csv_import',
            'status' => 'qualified',
            'notes' => 'Great lead',
        ]);

        // Test dynamic virtual accessors
        $this->assertEquals('Iberia Cargo', $lead->company_name);
        $this->assertEquals('iberiacargo.es', $lead->clean_root_domain);
        $this->assertEquals('HTTP 200 OK', $lead->website_status);
        $this->assertEquals('https://linkedin.com/company/iberiacargo', $lead->company_linkedin_page);
        $this->assertEquals('Logistics', $lead->industry_classification);
        $this->assertEquals('Madrid, Madrid, Spain', $lead->hq_location);
        $this->assertEquals('Spain', $lead->country);
        $this->assertEquals(120, $lead->employee_headcount);

        // Test serialization contains appended attributes for backward compatibility
        $array = $lead->toArray();
        $this->assertArrayHasKey('company_name', $array);
        $this->assertArrayHasKey('clean_root_domain', $array);
        $this->assertArrayHasKey('website_status', $array);
        $this->assertArrayHasKey('company_linkedin_page', $array);
        $this->assertArrayHasKey('industry_classification', $array);
        $this->assertArrayHasKey('hq_location', $array);
        $this->assertArrayHasKey('country', $array);
        $this->assertArrayHasKey('employee_headcount', $array);
    }

    public function test_api_key_hashing_and_verification(): void
    {
        $plainKey = 'jb_live_supersecrettoken12345';
        $hashed = hash('sha256', $plainKey);

        $apiKey = ApiKey::create([
            'name' => 'Production Client',
            'key' => $hashed,
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        $foundKey = ApiKey::where('key', hash('sha256', $plainKey))->first();
        $this->assertNotNull($foundKey);
        $this->assertEquals($apiKey->id, $foundKey->id);
    }
}
