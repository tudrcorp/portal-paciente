<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Livewire\Volt\Volt as LivewireVolt;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
    $response->assertSee(__('Clave del portal'), false);
    $response->assertSee(__('¿Olvidaste tu clave?'), false);
    $response->assertSee(__('¡Comunícate con nosotros!'), false);
    $response->assertSee(__('Olvidé mi clave'), false);
});

test('login requires password', function () {
    LivewireVolt::test('auth.login')
        ->set('identityCard', '00112345678')
        ->set('password', '')
        ->call('login')
        ->assertHasErrors(['password']);

    $this->assertGuest();
});

test('users can logout', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));

    $this->assertGuest();
});
