<?php

namespace Tests\Feature;

use App\Models\IngestionBatch;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_rejects_unauthenticated_csv_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('leads.csv', "Full Name,Corporate Work Email\nTest,test@example.com");

        $response = $this->postJson('/api/v1/leads/import/csv', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_required_file(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/leads/import/csv', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['file']);
    }

    public function test_successfully_imports_valid_csv_and_logs_batch(): void
    {
        Sanctum::actingAs($this->user);

        $csvContent = implode("\n", [
            'Full Name,Job Title,Corporate Work Email,Contact Number,Company Name,Clean Root Domain,Industry Classification,Employee Headcount,HQ Location',
            'Alice Morgan,CEO,alice@morganenterprises.com,+1 (555) 123-4567,Morgan Enterprises,morganenterprises.com,Technology,25,"San Francisco, California, United States"',
            'Bob Vance,Director of Sales,bob@vancecorp.com,+1 555-987-6543,Vance Corp,vancecorp.com,Manufacturing,120,"Chicago, Illinois, United States"',
        ]);

        $file = UploadedFile::fake()->createWithContent('leads.csv', $csvContent);

        $response = $this->postJson('/api/v1/leads/import/csv', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'summary' => [
                         'total'      => 2,
                         'inserted'   => 2,
                         'duplicates' => 0,
                         'errors'     => 0,
                     ],
                 ]);

        $this->assertDatabaseHas('leads', [
            'corporate_email' => 'alice@morganenterprises.com',
            'contact_number'  => '+15551234567',
        ]);

        $this->assertDatabaseHas('leads', [
            'corporate_email' => 'bob@vancecorp.com',
            'contact_number'  => '+15559876543',
        ]);

        $this->assertDatabaseHas('ingestion_batches', [
            'source'         => 'csv_upload',
            'total_records'  => 2,
            'inserted_count' => 2,
        ]);
    }

    public function test_handles_duplicates_and_format_errors_itemized(): void
    {
        Sanctum::actingAs($this->user);

        // Pre-create existing lead with known email and phone
        Lead::create([
            'full_name'       => 'Existing Lead',
            'corporate_email' => 'existing@test.com',
            'contact_number'  => '+15550001111',
            'title_tier'      => 'Other',
        ]);

        $csvContent = implode("\n", [
            'Full Name,Corporate Work Email,Contact Number,Company Name',
            'Valid New Lead,new@test.com,+15552223333,New Corp',
            'Duplicate Email Lead,existing@test.com,+15554445555,Some Corp',
            'Duplicate Phone Lead,different@test.com,+1 (555) 000-1111,Other Corp',
            'Missing Email Lead,,+15556667777,No Email Corp',
            'Bad Email Lead,not-an-email,+15558889999,Bad Email Corp',
        ]);

        $file = UploadedFile::fake()->createWithContent('mixed_leads.csv', $csvContent);

        $response = $this->postJson('/api/v1/leads/import/csv', [
            'file' => $file,
        ]);

        $response->assertStatus(207) // Multi-status
                 ->assertJson([
                     'summary' => [
                         'total'      => 5,
                         'inserted'   => 1,
                         'duplicates' => 2,
                         'errors'     => 2,
                     ],
                 ]);

        $data = $response->json();
        $this->assertEquals(1, $data['summary']['inserted']);
        $this->assertEquals(2, $data['summary']['duplicates']);
        $this->assertEquals(2, $data['summary']['errors']);

        // Verify specific duplicate fields are flagged
        $duplicateEmailItem = collect($data['details'])->firstWhere('row', 3);
        $this->assertEquals('duplicate', $duplicateEmailItem['status']);
        $this->assertEquals('corporate_email', $duplicateEmailItem['duplicate_field']);

        $duplicatePhoneItem = collect($data['details'])->firstWhere('row', 4);
        $this->assertEquals('duplicate', $duplicatePhoneItem['status']);
        $this->assertEquals('contact_number', $duplicatePhoneItem['duplicate_field']);
    }
}
