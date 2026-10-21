<?php

namespace App\Models;

use App\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scan_status' => ScanStatus::class, 'scanned_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isDownloadable(): bool
    {
        return $this->scan_status === ScanStatus::Available;
    }
}
