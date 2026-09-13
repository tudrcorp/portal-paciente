<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patient_case_quality_surveys')) {
            return;
        }

        Schema::table('patient_case_quality_surveys', function (Blueprint $table): void {
            if (! Schema::hasColumn('patient_case_quality_surveys', 'answers')) {
                $table->json('answers')->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('patient_case_quality_surveys')) {
            return;
        }

        Schema::table('patient_case_quality_surveys', function (Blueprint $table): void {
            if (Schema::hasColumn('patient_case_quality_surveys', 'answers')) {
                $table->dropColumn('answers');
            }
        });
    }
};
