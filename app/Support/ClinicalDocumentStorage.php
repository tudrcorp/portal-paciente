<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resuelve documentos clínicos (telemedicina-doc/) desde:
 * 1) disco public del portal
 * 2) disco/NFS compartido con Integracorp (CLINICAL_STORAGE_ROOT)
 * 3) URL pública de Integracorp (INTEGRACORP_URL/storage/...)
 */
final class ClinicalDocumentStorage
{
    public static function integracorpUrl(): string
    {
        return rtrim((string) config('portal.integracorp.url', ''), '/');
    }

    public static function storageBaseUrl(): string
    {
        $configured = rtrim((string) config('portal.integracorp.storage_base_url', ''), '/');
        if ($configured !== '') {
            return $configured;
        }

        $base = self::integracorpUrl();

        return $base !== '' ? $base.'/storage' : '';
    }

    public static function clinicalStorageRoot(): string
    {
        return rtrim((string) config('portal.integracorp.clinical_storage_root', ''), DIRECTORY_SEPARATOR);
    }

    public static function publicUrl(string $relativePath): ?string
    {
        $relativePath = ltrim($relativePath, '/');
        $base = self::storageBaseUrl();

        if ($relativePath === '' || $base === '') {
            return null;
        }

        return $base.'/'.$relativePath;
    }

    public static function exists(string $relativePath): bool
    {
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '') {
            return false;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return true;
        }

        $clinicalRoot = self::clinicalStorageRoot();
        if ($clinicalRoot !== '' && is_file($clinicalRoot.DIRECTORY_SEPARATOR.$relativePath)) {
            return true;
        }

        // En producción, los clínicos viven en Integracorp aunque no haya NFS montado.
        return self::isRemoteClinicalPath($relativePath) && self::storageBaseUrl() !== '';
    }

    public static function absolutePath(string $relativePath): ?string
    {
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '') {
            return null;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->path($relativePath);
        }

        $clinicalRoot = self::clinicalStorageRoot();
        if ($clinicalRoot === '') {
            return null;
        }

        $absolute = $clinicalRoot.DIRECTORY_SEPARATOR.$relativePath;

        return is_file($absolute) ? $absolute : null;
    }

    public static function download(string $relativePath, string $downloadName): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $relativePath = ltrim($relativePath, '/');
        $absolute = self::absolutePath($relativePath);

        if ($absolute !== null) {
            return response()->download($absolute, $downloadName);
        }

        $remoteUrl = self::publicUrl($relativePath);
        abort_unless(is_string($remoteUrl) && $remoteUrl !== '', 404);

        $response = Http::withOptions(['stream' => true])
            ->timeout((float) config('portal.integracorp.download_timeout_seconds', 30))
            ->connectTimeout((float) config('portal.integracorp.connect_timeout_seconds', 5))
            ->get($remoteUrl);

        abort_unless($response->successful(), 404, __('Archivo no disponible en Integracorp'));

        $body = $response->toPsrResponse()->getBody();
        $contentType = $response->header('Content-Type') ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(1024 * 64);
            }
        }, $downloadName, [
            'Content-Type' => $contentType,
        ]);
    }

    private static function isRemoteClinicalPath(string $relativePath): bool
    {
        // Uploads del portal viven en el disco del portal; el resto puede estar en Integracorp.
        return ! str_starts_with($relativePath, 'portal-patient-documents/');
    }
}
