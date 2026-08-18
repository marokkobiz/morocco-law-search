<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalAidCaseNote extends Model
{
    protected $fillable = [
        'legal_aid_request_id',
        'user_id',
        'note',
    ];

    public function legalAidRequest(): BelongsTo
    {
        return $this->belongsTo(LegalAidRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
