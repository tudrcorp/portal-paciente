<?php

use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Support\ClinicalHistoryPresentation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt as LivewireVolt;

beforeEach(function () {
    config([
        'auth.providers.users.driver' => 'telemedicine_eloquent',
        'auth.providers.users.model' => TelemedicinePatient::class,
        'portal.data_source' => 'database',
        'portal.support_whatsapp_phone' => '+584242132112',
        'portal.support_whatsapp_name' => 'MediChat',
    ]);

    Schema::dropIfExists('telemedicine_history_patients');
    Schema::dropIfExists('telemedicine_patients');

    Schema::create('telemedicine_patients', function (Blueprint $table): void {
        $table->id();
        $table->string('full_name')->nullable();
        $table->string('nro_identificacion')->nullable();
        $table->string('patient_portal_password')->nullable();
        $table->boolean('patient_portal_authorized')->default(true);
        $table->string('email')->nullable();
        $table->string('sex')->nullable();
        $table->timestamps();
    });

    Schema::create('telemedicine_history_patients', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_patient_id');
        $table->date('history_date')->nullable();
        $table->text('allergies')->nullable();
        $table->text('observations_allergies')->nullable();
        $table->text('history_surgical')->nullable();
        $table->text('medications_supplements')->nullable();
        $table->text('observations_medication')->nullable();
        $table->text('observations_pathological')->nullable();
        $table->text('observations_personal')->nullable();
        $table->text('observations_not_pathological')->nullable();
        $table->text('observations_ginecologica')->nullable();
        $table->string('edad_primera_menstruation')->nullable();
        $table->string('fecha_ultima_regla')->nullable();
        $table->unsignedTinyInteger('numero_embarazos')->nullable();
        $table->unsignedTinyInteger('numero_partos')->nullable();
        $table->unsignedTinyInteger('numero_abortos')->nullable();
        $table->unsignedTinyInteger('cesareas')->nullable();

        $flags = array_unique(array_merge(
            array_keys(ClinicalHistoryPresentation::pathologicalFlags()),
            array_keys(ClinicalHistoryPresentation::appDeclaredFlags()),
            array_keys(ClinicalHistoryPresentation::habitFlags()),
        ));

        foreach ($flags as $flag) {
            $table->boolean($flag)->default(false);
        }

        $table->timestamps();
    });
});

function makePortalPatient(array $overrides = []): TelemedicinePatient
{
    return TelemedicinePatient::query()->create(array_merge([
        'full_name' => 'Paciente Prueba',
        'nro_identificacion' => '00112345678',
        'patient_portal_password' => 'clave-segura',
        'patient_portal_authorized' => true,
        'email' => 'paciente@example.com',
        'sex' => 'Femenino',
    ], $overrides));
}

test('patient can authenticate with identity card and portal password', function () {
    makePortalPatient();

    TelemedicineHistoryPatient::query()->create([
        'telemedicine_patient_id' => 1,
        'history_date' => now()->toDateString(),
        'allergies' => 'Ninguna conocida',
    ]);

    LivewireVolt::test('auth.login')
        ->set('identityCard', '00112345678')
        ->set('password', 'clave-segura')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('patient cannot authenticate with invalid password', function () {
    makePortalPatient();

    LivewireVolt::test('auth.login')
        ->set('identityCard', '00112345678')
        ->set('password', 'incorrecta')
        ->call('login')
        ->assertHasErrors(['password']);

    $this->assertGuest();
});

test('patient without portal password is guided to contact operations', function () {
    makePortalPatient(['patient_portal_password' => null]);

    LivewireVolt::test('auth.login')
        ->set('identityCard', '00112345678')
        ->set('password', 'cualquier')
        ->call('login')
        ->assertHasErrors(['password']);

    $this->assertGuest();
});

test('patient without portal authorization cannot login', function () {
    makePortalPatient(['patient_portal_authorized' => false]);

    LivewireVolt::test('auth.login')
        ->set('identityCard', '00112345678')
        ->set('password', 'clave-segura')
        ->call('login')
        ->assertHasErrors([
            'identityCard' => __('Tu acceso al portal del paciente debe ser autorizado por el equipo de operaciones de TuDrGroup.'),
        ]);

    $this->assertGuest();
});

test('patient without clinical history is redirected to onboarding after login', function () {
    makePortalPatient();

    LivewireVolt::test('auth.login')
        ->set('identityCard', '00112345678')
        ->set('password', 'clave-segura')
        ->call('login')
        ->assertRedirect(route('history.onboarding', absolute: false));

    $this->assertAuthenticated();
});

test('patient without clinical history cannot open dashboard', function () {
    $patient = makePortalPatient();

    $this->actingAs($patient)
        ->get(route('dashboard'))
        ->assertRedirect(route('history.onboarding'));
});

test('patient can complete clinical history onboarding and enter portal', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $patient = makePortalPatient();

    $this->actingAs($patient)
        ->post(route('history.onboarding.store'), [
            'no_allergies' => true,
            'history_surgical' => 'Ninguna',
            'tabaco' => false,
            'alcohol' => false,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('portal.history_success');

    expect(
        TelemedicineHistoryPatient::query()
            ->where('telemedicine_patient_id', $patient->id)
            ->exists()
    )->toBeTrue();

    $this->actingAs($patient->fresh())
        ->get(route('dashboard'))
        ->assertOk();
});

test('guest can open help for password recovery', function () {
    $this->get(route('help'))
        ->assertOk()
        ->assertSee(__('¿Olvidaste tu clave?'), false);
});
