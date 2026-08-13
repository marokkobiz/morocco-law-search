<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('price_display_en')->nullable()->after('price');
            $table->string('price_display_fr')->nullable()->after('price_display_en');
            $table->string('price_display_ar')->nullable()->after('price_display_fr');
            $table->string('notes_en')->nullable()->after('price_display_ar');
            $table->string('notes_fr')->nullable()->after('notes_en');
            $table->string('notes_ar')->nullable()->after('notes_fr');
            $table->string('additional_notes_en')->nullable()->after('notes_ar');
            $table->string('additional_notes_fr')->nullable()->after('additional_notes_en');
            $table->string('additional_notes_ar')->nullable()->after('additional_notes_fr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'price_display_en',
                'price_display_fr',
                'price_display_ar',
                'notes_en',
                'notes_fr',
                'notes_ar',
                'additional_notes_en',
                'additional_notes_fr',
                'additional_notes_ar',
            ]);
        });
    }
};
