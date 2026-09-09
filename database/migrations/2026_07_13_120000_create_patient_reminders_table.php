<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_reminders')) {
            return;
        }

        Schema::create('patient_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->string('type', 40);
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('medicine_name')->nullable();
            $table->string('dosage')->nullable();
            $table->timestamp('appointment_at')->nullable();
            $table->string('location')->nullable();
            $table->string('doctor_name')->nullable();
            $table->json('schedule_times')->nullable();
            $table->json('schedule_days')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('lead_minutes')->nullable();
            $table->boolean('channel_whatsapp')->default(true);
            $table->boolean('channel_in_app')->default(true);
            $table->boolean('channel_sms')->default(false);
            $table->boolean('channel_push')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['telemedicine_patient_id', 'type', 'is_active'], 'patient_reminders_patient_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_reminders');
    }
};
