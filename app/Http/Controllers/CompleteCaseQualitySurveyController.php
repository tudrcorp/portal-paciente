<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseQualitySurveyRequest;
use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiException;
use App\Services\PortalData\QualitySurveyGateway;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CompleteCaseQualitySurveyController extends Controller
{
    public function __invoke(
        StoreCaseQualitySurveyRequest $request,
        int $case,
        QualitySurveyGateway $surveys,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TelemedicinePatient, 403);

        try {
            $result = $surveys->markCompleted($user, $case, $request->answersPayload());
        } catch (PortalApiException $exception) {
            return response()->json([
                'message' => $exception->getMessage() ?: __('No se pudo registrar el cuestionario.'),
            ], $exception->status >= 400 ? $exception->status : 502);
        } catch (HttpException $exception) {
            return response()->json([
                'message' => $exception->getMessage() ?: __('No se pudo registrar el cuestionario.'),
            ], $exception->getStatusCode());
        }

        return response()->json([
            'message' => __('Cuestionario guardado. Ya puedes ver los documentos del caso.'),
            'survey' => $result,
        ]);
    }
}
