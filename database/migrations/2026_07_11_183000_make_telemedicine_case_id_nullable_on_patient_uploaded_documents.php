<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telemedicine_patient_uploaded_documents')) {
            return;
        }

        Schema::table('telemedicine_patient_uploaded_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('telemedicine_case_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_patient_uploaded_documents')) {
            return;
        }

        Schema::table('telemedicine_patient_uploaded_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('telemedicine_case_id')->nullable(false)->change();
        });
    }
};
