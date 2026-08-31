<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LegalAidRequestService extends Pivot
{
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}