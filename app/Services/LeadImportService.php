<?php

namespace App\Services;

use App\Models\IngestionBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class LeadImportService
{
    public function __construct(
        private LeadIngestionService $ingestionService
    ) {}

    /**
     * Parse and import leads from an uploaded CSV file.
     *
     * @param UploadedFile|string $file Uploaded file or path to CSV
     * @param string|null $originalFilename
     * @return array
     */
    public function importCsv(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        $path = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $handle = fopen($path, 'r');
        if (!$handle) {
            return [
                'success' => false,
                'message' => 'Unable to read the uploaded CSV file.',
                'summary' => ['total' => 0, 'inserted' => 0, 'duplicates' => 0, 'errors' => 1],
                'details' => [],
            ];
        }

        // Read and clean headers
        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return [
                'success' => false,
                'message' => 'The uploaded CSV file is empty.',
                'summary' => ['total' => 0, 'inserted' => 0, 'duplicates' => 0, 'errors' => 1],
                'details' => [],
            ];
        }

        // Strip UTF-8 BOM
        $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeaders[0]);
        $headers = array_map('trim', $rawHeaders);

        $insertedCount  = 0;
        $duplicateCount = 0;
        $errorCount     = 0;
        $results        = [];
        $totalRows      = 0;
        $rowNumber      = 1; // Header is row 1

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip blank lines
            if (empty(array_filter($data, fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            $totalRows++;

            // Map data to headers
            if (count($data) !== count($headers)) {
                $errorCount++;
                $results[] = [
                    'row'       => $rowNumber,
                    'status'    => 'error',
                    'name'      => $data[0] ?? 'Unknown',
                    'email'     => null,
                    'phone'     => null,
                    'message'   => "Row {$rowNumber} column count mismatch: expected " . count($headers) . " columns, got " . count($data) . ".",
                    'errors'    => ['format' => 'Column count does not match CSV headers.'],
                ];
                continue;
            }

            $rowPayload = array_combine($headers, $data);

            // Extract basic identifiers for clear reporting
            $name  = trim($rowPayload['Full Name'] ?? ($rowPayload['full_name'] ?? ($rowPayload['Name'] ?? '')));
            $email = trim($rowPayload['Corporate Work Email'] ?? ($rowPayload['corporate_email'] ?? ($rowPayload['Email'] ?? '')));
            $phone = trim($rowPayload['Contact Number'] ?? ($rowPayload['contact_number'] ?? ($rowPayload['Phone'] ?? ($rowPayload['Phone Number'] ?? ''))));

            // Validate minimum required fields before ingestion
            if (empty($email)) {
                $errorCount++;
                $results[] = [
                    'row'       => $rowNumber,
                    'status'    => 'error',
                    'name'      => $name ?: 'Unknown',
                    'email'     => null,
                    'phone'     => $phone ?: null,
                    'message'   => "Row {$rowNumber}: Missing corporate email.",
                    'errors'    => ['corporate_email' => 'Corporate work email is required.'],
                ];
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorCount++;
                $results[] = [
                    'row'       => $rowNumber,
                    'status'    => 'error',
                    'name'      => $name ?: 'Unknown',
                    'email'     => $email,
                    'phone'     => $phone ?: null,
                    'message'   => "Row {$rowNumber}: Invalid email format '{$email}'.",
                    'errors'    => ['corporate_email' => "The email '{$email}' is not a valid email address."],
                ];
                continue;
            }

            // Ingest using standard LeadIngestionService
            $result = $this->ingestionService->ingest($rowPayload, 'csv_upload');

            if ($result['success']) {
                $insertedCount++;
                $results[] = [
                    'row'       => $rowNumber,
                    'status'    => 'inserted',
                    'name'      => $result['lead']->full_name,
                    'email'     => $result['lead']->corporate_email,
                    'phone'     => $result['lead']->contact_number,
                    'lead_id'   => $result['lead']->id,
                    'message'   => 'Successfully inserted.',
                ];
            } elseif ($result['duplicate']) {
                $duplicateCount++;
                $results[] = [
                    'row'              => $rowNumber,
                    'status'           => 'duplicate',
                    'name'             => $name ?: 'Unknown',
                    'email'            => $email,
                    'phone'            => $phone ?: null,
                    'duplicate_fields' => $result['duplicate_fields'] ?? [],
                    'duplicate_field'  => $result['duplicate_field'] ?? 'unknown',
                    'message'          => "Row {$rowNumber}: " . ($result['message'] ?? 'Duplicate lead entry.'),
                    'errors'           => $result['errors'],
                ];
            } else {
                $errorCount++;
                $results[] = [
                    'row'       => $rowNumber,
                    'status'    => 'error',
                    'name'      => $name ?: 'Unknown',
                    'email'     => $email,
                    'phone'     => $phone ?: null,
                    'message'   => "Row {$rowNumber}: " . implode('; ', array_values($result['errors'])),
                    'errors'    => $result['errors'],
                ];
            }
        }

        fclose($handle);

        // Record audit batch
        $batch = IngestionBatch::create([
            'source'           => 'csv_upload',
            'filename'         => $filename,
            'batch_id'         => 'csv_' . now()->format('YmdHis') . '_' . Str::random(6),
            'total_records'    => $totalRows,
            'inserted_count'   => $insertedCount,
            'duplicates_count' => $duplicateCount,
            'errors_count'     => $errorCount,
            'error_details'    => array_values(array_filter($results, fn($r) => $r['status'] !== 'inserted')),
        ]);

        return [
            'success'     => $insertedCount > 0 || ($totalRows > 0 && $duplicateCount > 0),
            'message'     => "CSV import completed: {$insertedCount} inserted, {$duplicateCount} duplicates skipped, {$errorCount} errors.",
            'batch_id'    => $batch->id,
            'summary'     => [
                'total'       => $totalRows,
                'inserted'    => $insertedCount,
                'duplicates'  => $duplicateCount,
                'errors'      => $errorCount,
            ],
            'details'     => $results,
        ];
    }
}
