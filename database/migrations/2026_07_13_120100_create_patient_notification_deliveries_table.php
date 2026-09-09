<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_notification_deliveries')) {
            return;
        }

        Schema::create('patient_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_reminder_id');
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->string('channel', 40);
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['patient_reminder_id', 'channel', 'scheduled_for'],
                'patient_notification_deliveries_unique_dispatch'
            );
            $table->index(
                ['telemedicine_patient_id', 'status', 'scheduled_for'],
                'patient_notification_deliveries_patient_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_notification_deliveries');
    }
};
