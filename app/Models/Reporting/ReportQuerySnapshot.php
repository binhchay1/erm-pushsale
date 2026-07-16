<?php

namespace App\Models\Reporting;

use Illuminate\Database\Eloquent\Model;

class ReportQuerySnapshot extends Model
{
    protected $fillable = [
        'company_id', 'user_id', 'report_key', 'date_from', 'date_to', 'date_type', 'filter_hash',
        'filter_payload', 'payload', 'encoding', 'data_revision', 'is_final', 'source_watermark_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'filter_payload' => 'array',
            'is_final' => 'boolean',
            'source_watermark_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function decodePayload(): mixed
    {
        if ($this->encoding === 'gzip-base64-json') {
            $compressed = base64_decode($this->payload, true);
            $json = $compressed === false ? false : gzdecode($compressed);

            return $json === false ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }

        return json_decode($this->payload, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function encodePayload(mixed $payload): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $compressed = gzencode($json, 6);

        if ($compressed === false) {
            return ['payload' => $json, 'encoding' => 'json'];
        }

        return ['payload' => base64_encode($compressed), 'encoding' => 'gzip-base64-json'];
    }
}
