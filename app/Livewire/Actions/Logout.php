<?php

namespace App\Livewire\Actions;

use App\Services\PortalData\PortalAuthGateway;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Cierra sesión Laravel y, si aplica, invalida el JWT del API.
     */
    public function __invoke()
    {
        app(PortalAuthGateway::class)->logout();

        Session::invalidate();
        Session::regenerateToken();

        return redirect()->route('login');
    }
}
