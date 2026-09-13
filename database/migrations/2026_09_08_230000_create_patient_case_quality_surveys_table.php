<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_case_quality_surveys')) {
            return;
        }

        Schema::create('patient_case_quality_surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->unsignedBigInteger('telemedicine_case_id');
            $table->string('status', 20)->default('completed');
            $table->string('form_url')->nullable();
            $table->string('source', 40)->default('portal_declaration');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['telemedicine_patient_id', 'telemedicine_case_id'],
                'patient_case_quality_surveys_patient_case_unique'
            );
            $table->index(
                ['telemedicine_case_id', 'status'],
                'patient_case_quality_surveys_case_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_case_quality_surveys');
    }
};
