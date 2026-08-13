<?php

use App\Models\LegalAidRequest;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LegalAidRequest::where('base_price', 0)
            ->where('status', LegalAidRequest::STATUS_PENDING_PAYMENT)
            ->update(['status' => LegalAidRequest::STATUS_PENDING]);
    }

    public function down(): void
    {
        LegalAidRequest::where('base_price', 0)
            ->where('status', LegalAidRequest::STATUS_PENDING)
            ->update(['status' => LegalAidRequest::STATUS_PENDING_PAYMENT]);
    }
};
