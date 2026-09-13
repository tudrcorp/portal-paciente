<?php

use App\Http\Controllers\AppointmentServiceOrderWhatsAppController;
use App\Http\Controllers\ClinicalHistoryController;
use App\Http\Controllers\ClinicalHistoryOnboardingController;
use App\Http\Controllers\CompleteCaseQualitySurveyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyProfileController;
use App\Http\Controllers\Nearby\NearbyAddressController;
use App\Http\Controllers\Nearby\NearbyPlacesController;
use App\Http\Controllers\Nearby\NearbyRouteController;
use App\Http\Controllers\NearbyController;
use App\Http\Controllers\OperationsHelpController;
use App\Http\Controllers\PatientDocumentsController;
use App\Http\Controllers\PatientNotificationController;
use App\Http\Controllers\StorePatientDocumentController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Manifiesto PWA: se sirve por ruta para poder fijar el MIME correcto.
Route::get('manifest.webmanifest', function () {
    return response()
        ->file(resource_path('manifest.webmanifest'), [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
})->name('manifest');

// Recuperación de clave: accesible sin sesión (desde el login).
Route::get('help', OperationsHelpController::class)->name('help');

Route::middleware(['auth', 'patient.history'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::post('appointments/{appointment}/service-order/whatsapp', AppointmentServiceOrderWhatsAppController::class)
        ->whereNumber('appointment')
        ->name('appointments.service-order.whatsapp');

    Route::get('history', ClinicalHistoryController::class)->name('history');

    Route::get('history/download', [ClinicalHistoryController::class, 'download'])
        ->name('history.download');

    Route::get('cases', PatientDocumentsController::class)->name('cases.index');

    Route::post('cases/{case}/quality-survey/complete', CompleteCaseQualitySurveyController::class)
        ->whereNumber('case')
        ->name('cases.quality-survey.complete');

    Route::post('cases/documents', StorePatientDocumentController::class)
        ->name('cases.documents.store');

    Route::get('cases/documents/{source}/{id}/download', [PatientDocumentsController::class, 'download'])
        ->whereNumber('id')
        ->name('cases.documents.download');

    Route::get('my-profile', MyProfileController::class)->name('my-profile.show');

    // Geolocalización de centros de salud. Las consultas pasan por el servidor
    // para cachear, ocultar credenciales del proveedor y respetar la cuota de
    // las APIs públicas; el acelerador evita que un cliente con un bucle mal
    // hecho agote esa cuota para todos.
    Route::get('nearby', NearbyController::class)->name('nearby.index');

    Route::middleware('throttle:60,1')->prefix('nearby')->name('nearby.')->group(function () {
        Route::get('places', NearbyPlacesController::class)->name('places');
        Route::get('route', NearbyRouteController::class)->name('route');
        Route::get('address', NearbyAddressController::class)->name('address');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::get('history/onboarding', [ClinicalHistoryOnboardingController::class, 'create'])
        ->name('history.onboarding');

    Route::post('history/onboarding', [ClinicalHistoryOnboardingController::class, 'store'])
        ->name('history.onboarding.store');
});

Route::middleware(['auth', 'patient.history'])->prefix('notifications')->name('notifications.')->group(function () {
    // {reminder} es el ID numérico (DB o API); no usamos route-model binding Eloquent
    // para poder operar también con DATA_SOURCE=api.
    Route::get('/', [PatientNotificationController::class, 'index'])->name('index');
    Route::post('/', [PatientNotificationController::class, 'store'])->name('store');
    Route::put('{reminder}', [PatientNotificationController::class, 'update'])->whereNumber('reminder')->name('update');
    Route::delete('{reminder}', [PatientNotificationController::class, 'destroy'])->whereNumber('reminder')->name('destroy');
    Route::patch('{reminder}/toggle', [PatientNotificationController::class, 'toggle'])->whereNumber('reminder')->name('toggle');
});

if (app()->environment('local')) {
    Route::get('/__preview-my-profile', function (\Illuminate\Http\Request $request) {
        $patient = new \App\Models\TelemedicinePatient;
        $patient->forceFill([
            'id' => 999999999,
            'full_name' => 'María José Pérez Rodríguez',
            'nro_identificacion' => 'V-12345678',
            'email' => 'maria.perez@example.com',
            'phone' => '+58 412 1234567',
            'phone_contact' => null,
            'address' => 'Av. Principal, Urb. Las Mercedes, Caracas',
            'birth_date' => '1990-05-14',
            'sex' => 'Femenino',
            'age' => 34,
            'afilliation_corporate_id' => 0,
            'afilliation_id' => 0,
        ]);
        $patient->exists = true;
        auth()->setUser($patient);

        return app(MyProfileController::class)($request);
    });
}

Route::middleware(['auth', 'patient.history'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

require __DIR__.'/auth.php';
