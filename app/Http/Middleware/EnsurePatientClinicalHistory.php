<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TelemedicinePatient;
use App\Services\PortalData\ClinicalHistoryGateway;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a completar la historia clínica antes de usar el resto del portal.
 */
final class EnsurePatientClinicalHistory
{
    public function __construct(private readonly ClinicalHistoryGateway $history) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof TelemedicinePatient) {
            return $next($request);
        }

        if ($this->history->hasHistory($user)) {
            return $next($request);
        }

        if ($request->routeIs('history.onboarding', 'history.onboarding.store', 'logout')) {
            return $next($request);
        }

        return redirect()->route('history.onboarding');
    }
}
