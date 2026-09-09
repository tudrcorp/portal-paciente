<?php

use App\Support\PatientDocumentsPresentation;

it('formats clinical document names without patient and case code', function () {
    $formatted = PatientDocumentsPresentation::formatClinicalDocumentName(
        '26-03-2026 RESULTADOS LABS TIROIDEO BECKY ACOSTA TDC-1226',
        'BECKY KORYNA DEL VALLE ACOSTA QUINTERO',
    );

    expect($formatted)->toBe('RESULTADOS LABS TIROIDEO');
});

it('keeps custom document names without clinical date pattern', function () {
    $formatted = PatientDocumentsPresentation::formatClinicalDocumentName(
        'Resultado de laboratorio — hemograma',
        'Becky Acosta',
    );

    expect($formatted)->toBe('Resultado de laboratorio — hemograma');
});

it('strips patient and case even when order varies', function () {
    $formatted = PatientDocumentsPresentation::formatClinicalDocumentName(
        '22-03-2026 IM AMD TDC1226 BECKY ACOSTA.pdf',
        'BECKY KORYNA DEL VALLE ACOSTA QUINTERO',
    );

    expect($formatted)->toBe('IM AMD');
});

it('splits clinical document names into two display lines', function () {
    [$primary, $secondary] = PatientDocumentsPresentation::clinicalDocumentNameLines(
        'RESULTADOS LABS TIROIDEO'
    );

    expect($primary)->toBe('RESULTADOS LABS TIROIDEO')
        ->and($secondary)->toBeNull();
});
