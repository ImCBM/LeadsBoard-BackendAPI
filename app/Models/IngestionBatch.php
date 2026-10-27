<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IngestionBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'filename',
        'batch_id',
        'total_records',
        'inserted_count',
        'duplicates_count',
        'errors_count',
        'error_details',
    ];

    protected function casts(): array
    {
        return [
            'total_records'    => 'integer',
            'inserted_count'   => 'integer',
            'duplicates_count' => 'integer',
            'errors_count'     => 'integer',
            'error_details'    => 'array',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
        ];
    }
}
