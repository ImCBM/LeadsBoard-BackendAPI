<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadExportService
{
    /**
     * CSV column headers matching the lead fields.
     */
    private const CSV_HEADERS = [
        'ID',
        'Full Name',
        'Job Title',
        'Title Tier',
        'Corporate Email',
        'Email Status',
        'Company Name',
        'Clean Root Domain',
        'Website Status',
        'Executive LinkedIn URL',
        'Company LinkedIn Page',
        'Industry Classification',
        'Employee Headcount',
        'HQ Location',
        'Country',
        'Ingestion Channel',
        'Status',
        'Notes',
        'Created At',
    ];

    /**
     * Export leads as a streamed CSV response.
     * Uses chunked queries to avoid memory issues with large datasets.
     *
     * @param  Builder $query  Pre-filtered query builder
     * @param  string  $filename
     * @return StreamedResponse
     */
    public function exportCsv(Builder $query, string $filename = 'leads_export.csv'): StreamedResponse
    {
        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Write headers
            fputcsv($handle, self::CSV_HEADERS);

            // Stream data in chunks of 500 to avoid memory exhaustion
            $query->orderBy('created_at', 'desc')->chunk(500, function ($leads) use ($handle) {
                foreach ($leads as $lead) {
                    fputcsv($handle, [
                        $lead->id,
                        $lead->full_name,
                        $lead->job_title,
                        $lead->title_tier,
                        $lead->corporate_email,
                        $lead->email_status,
                        $lead->company_name,
                        $lead->clean_root_domain,
                        $lead->website_status,
                        $lead->executive_linkedin_url,
                        $lead->company_linkedin_page,
                        $lead->industry_classification,
                        $lead->employee_headcount,
                        $lead->hq_location,
                        $lead->country,
                        $lead->ingestion_channel,
                        $lead->status,
                        $lead->notes,
                        $lead->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
