<?php

namespace App\Models\Reporting;

use Illuminate\Database\Eloquent\Model;

class AnalyticsArchiveManifest extends Model
{
    protected $fillable = [
        'company_id', 'source_table', 'archive_table', 'archive_month', 'status', 'source_rows',
        'archive_rows', 'source_checksum', 'archive_checksum', 'verified', 'source_purged',
        'archived_at', 'verified_at', 'purged_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'source_purged' => 'boolean',
            'archived_at' => 'datetime',
            'verified_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }
}
