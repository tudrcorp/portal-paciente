<?php

declare(strict_types=1);

use App\Jobs\SendPatientReminderJob;
use App\Models\PatientNotificationDelivery;
use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;
use App\Services\Notifications\NotificationChannelManager;
use App\Services\Notifications\ReminderMessageBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    if (! Schema::hasTable('telemedicine_patients')) {
        Schema::create('telemedicine_patients', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('nro_identificacion')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_contact')->nullable();
            $table->timestamps();
        });
    }
});

function makePatient(array $overrides = []): TelemedicinePatient
{
    $patient = new TelemedicinePatient;
    $patient->forceFill(array_merge([
        'full_name' => 'Ana Paciente',
        'nro_identificacion' => 'V-10000001',
        'email' => 'ana@example.com',
        'phone' => '04121234567',
        'phone_contact' => null,
    ], $overrides));
    $patient->save();

    return $patient->fresh();
}

test('guests cannot open notifications page', function () {
    $this->get(route('notifications.index'))
        ->assertRedirect(route('login'));
});

test('patient can create a chronic reminder for themselves', function () {
    $patient = makePatient();

    $this->actingAs($patient)
        ->post(route('notifications.store'), [
            'type' => PatientReminder::TYPE_CHRONIC,
            'title' => 'Metformina mañana y noche',
            'medicine_name' => 'Metformina',
            'dosage' => '850 mg',
            'schedule_times' => '08:00, 20:00',
            'schedule_days' => [1, 2, 3, 4, 5],
            'starts_on' => now()->toDateString(),
            'channel_whatsapp' => '1',
            'channel_in_app' => '1',
        ])
        ->assertRedirect(route('notifications.index', ['tab' => 'chronic']));

    $reminder = PatientReminder::query()->first();

    expect($reminder)->not->toBeNull()
        ->and((int) $reminder->telemedicine_patient_id)->toBe((int) $patient->id)
        ->and($reminder->schedule_times)->toBe(['08:00', '20:00'])
        ->and($reminder->channel_whatsapp)->toBeTrue();
});

test('patient cannot update another patients reminder', function () {
    $owner = makePatient(['nro_identificacion' => 'V-1']);
    $intruder = makePatient(['nro_identificacion' => 'V-2', 'email' => 'otro@example.com']);

    $reminder = PatientReminder::query()->create([
        'telemedicine_patient_id' => $owner->id,
        'type' => PatientReminder::TYPE_CHRONIC,
        'title' => 'Privado',
        'schedule_times' => ['08:00'],
        'schedule_days' => [1],
        'channel_whatsapp' => false,
        'channel_in_app' => true,
        'is_active' => true,
    ]);

    $this->actingAs($intruder)
        ->put(route('notifications.update', $reminder), [
            'type' => PatientReminder::TYPE_CHRONIC,
            'title' => 'Hackeado',
            'schedule_times' => '09:00',
            'schedule_days' => [1],
            'channel_in_app' => '1',
        ])
        ->assertForbidden();

    expect($reminder->fresh()->title)->toBe('Privado');
});

test('reactivating a chronic reminder restarts the cycle from today', function () {
    $patient = makePatient();

    $reminder = PatientReminder::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'type' => PatientReminder::TYPE_CHRONIC,
        'title' => 'Interdiario',
        'schedule_times' => ['08:00', '20:00'],
        'schedule_days' => [1, 3, 5],
        'starts_on' => now()->subDays(10)->toDateString(),
        'channel_whatsapp' => false,
        'channel_in_app' => true,
        'is_active' => false,
    ]);

    $this->actingAs($patient)
        ->patch(route('notifications.toggle', $reminder))
        ->assertRedirect(route('notifications.index', ['tab' => 'chronic']));

    $reminder->refresh();

    expect($reminder->is_active)->toBeTrue()
        ->and($reminder->starts_on?->toDateString())->toBe(now()->toDateString());
});

test('dispatch command creates idempotent deliveries and send job marks them sent', function () {
    config([
        'services.ultramsg.instance_id' => 'instance123',
        'services.ultramsg.token' => 'secret-token',
        'services.ultramsg.base_url' => 'https://api.ultramsg.com',
    ]);

    Http::fake([
        'https://api.ultramsg.com/*' => Http::response(['sent' => 'true', 'id' => 1], 200),
    ]);

    $patient = makePatient();
    $now = now()->startOfMinute();

    $reminder = PatientReminder::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'type' => PatientReminder::TYPE_CHRONIC,
        'title' => 'Tomar pastilla',
        'medicine_name' => 'Pastilla',
        'schedule_times' => [$now->format('H:i')],
        'schedule_days' => [$now->dayOfWeek],
        'starts_on' => $now->toDateString(),
        'channel_whatsapp' => true,
        'channel_in_app' => true,
        'channel_sms' => false,
        'channel_push' => false,
        'is_active' => true,
    ]);

    $this->artisan('reminders:dispatch', ['--lookback' => 1, '--lookahead' => 0])
        ->assertSuccessful();

    expect(PatientNotificationDelivery::query()->count())->toBe(2);

    $this->artisan('reminders:dispatch', ['--lookback' => 1, '--lookahead' => 0])
        ->assertSuccessful();

    expect(PatientNotificationDelivery::query()->count())->toBe(2);

    PatientNotificationDelivery::query()->each(function (PatientNotificationDelivery $delivery): void {
        (new SendPatientReminderJob($delivery->id))->handle(
            app(NotificationChannelManager::class),
            app(ReminderMessageBuilder::class),
        );
    });

    expect(
        PatientNotificationDelivery::query()
            ->where('status', PatientNotificationDelivery::STATUS_SENT)
            ->count()
    )->toBe(2);

    expect($reminder->id)->toBeInt();
});
