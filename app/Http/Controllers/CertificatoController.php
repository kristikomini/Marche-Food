<?php

namespace App\Http\Controllers;

use App\Services\CertificateExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI certificate extraction endpoint (Epic 2). Accepts an uploaded PDF/image
 * certificate and returns structured fields to auto-fill the supplier form.
 */
class CertificatoController extends Controller
{
    public function estrai(Request $request, CertificateExtractionService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);

        if (! $service->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'Estrazione AI non configurata su questo ambiente.'], 422);
        }

        $file = $request->file('file');
        $base64 = base64_encode(file_get_contents($file->getRealPath()));
        $mime = $file->getMimeType() ?: 'application/pdf';

        $result = $service->extract($base64, $mime);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
