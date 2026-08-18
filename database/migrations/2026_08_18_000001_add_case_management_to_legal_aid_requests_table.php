<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->foreignId('advisor_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('case_status', 20)
                ->default('open')
                ->after('advisor_id');
            $table->timestamp('closed_at')->nullable()->after('case_status');
            $table->timestamp('first_contact_at')->nullable()->after('closed_at');
            $table->timestamp('last_touched_at')->nullable()->after('first_contact_at');
        });
    }

    public function down(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('advisor_id');
            $table->dropColumn([
                'case_status',
                'closed_at',
                'first_contact_at',
                'last_touched_at',
            ]);
        });
    }
};