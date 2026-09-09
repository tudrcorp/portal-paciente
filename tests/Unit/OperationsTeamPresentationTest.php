<?php

declare(strict_types=1);

use App\Support\OperationsTeamPresentation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // Con config:cache, Pest puede apuntar a MySQL real; no tocar esa DB.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('OperationsTeamPresentationTest requiere sqlite en testing.');
    }

    Schema::dropIfExists('portal_help_contacts');

    Schema::create('portal_help_contacts', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('phone');
        $table->unsignedInteger('sort_order')->default(0);
        $table->string('status')->default('ACTIVO');
        $table->timestamps();
    });
});

afterEach(function (): void {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('portal_help_contacts');
});

it('lista solo contactos activos ordenados por sort_order', function (): void {
    DB::table('portal_help_contacts')->insert([
        [
            'name' => 'Segundo',
            'phone' => '+584241111111',
            'sort_order' => 2,
            'status' => 'ACTIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'Primero',
            'phone' => '+584242222222',
            'sort_order' => 1,
            'status' => 'ACTIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'Inactivo',
            'phone' => '+584243333333',
            'sort_order' => 0,
            'status' => 'INACTIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $contacts = OperationsTeamPresentation::contactsForPatientPortal('Ana Paciente');

    expect($contacts)->toHaveCount(2)
        ->and($contacts[0]['name'])->toBe('Primero')
        ->and($contacts[1]['name'])->toBe('Segundo')
        ->and($contacts[0]['whatsapp_url'])->toContain('wa.me/')
        ->and($contacts[0]['whatsapp_url'])->toContain(rawurlencode('Ana Paciente'));
});

it('omite teléfonos inválidos y preserva el nombre libre', function (): void {
    DB::table('portal_help_contacts')->insert([
        [
            'name' => 'MediChat',
            'phone' => '+584242132112',
            'sort_order' => 1,
            'status' => 'ACTIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'Sin teléfono',
            'phone' => 'abc',
            'sort_order' => 2,
            'status' => 'ACTIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $contacts = OperationsTeamPresentation::contactsForPatientPortal();

    expect($contacts)->toHaveCount(1)
        ->and($contacts[0]['name'])->toBe('MediChat')
        ->and($contacts[0]['phone_display'])->not->toBe('');
});
