<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('telemedicine_patient_uploaded_documents')) {
            return;
        }

        Schema::create('telemedicine_patient_uploaded_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->unsignedBigInteger('telemedicine_case_id');
            $table->string('document_name');
            $table->text('upload_reason');
            $table->string('stored_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->index(['telemedicine_patient_id', 'telemedicine_case_id'], 'tpud_patient_case_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemedicine_patient_uploaded_documents');
    }
};
