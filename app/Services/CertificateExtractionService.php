<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * AI-powered certificate extraction (Epic 2). Sends an uploaded HACCP/MOCA
 * certificate (PDF or image) to a vision LLM and extracts the expiry date
 * (haccp_scadenza) and certificate/MOCA number (moca_numero).
 *
 * The network call and the response parsing are separated so the parser can be
 * unit-tested and the whole flow can be exercised with Http::fake().
 */
class CertificateExtractionService
{
    private const PROMPT = <<<'TXT'
Sei un assistente che estrae dati da certificati HACCP o MOCA per un'azienda alimentare.
Dal documento allegato estrai:
- "haccp_scadenza": la data di scadenza del certificato in formato ISO YYYY-MM-DD (null se assente)
- "moca_numero": il numero del certificato o codice MOCA (null se assente)
Rispondi ESCLUSIVAMENTE con un oggetto JSON valido, senza testo aggiuntivo, es:
{"haccp_scadenza":"2027-06-30","moca_numero":"MOCA/2024/IT/00342"}
TXT;

    public function isConfigured(): bool
    {
        return ! empty(config('ai.anthropic.key'));
    }

    /**
     * @return array{ok:bool, haccp_scadenza?:?string, moca_numero?:?string, error?:string}
     */
    public function extract(string $base64, string $mime): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Estrazione AI non configurata (ANTHROPIC_API_KEY mancante).'];
        }

        $mediaBlock = str_contains($mime, 'pdf')
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]];

        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('ai.anthropic.key'),
                'anthropic-version' => config('ai.anthropic.version', '2023-06-01'),
                'content-type'      => 'application/json',
            ])->timeout(90)->post(rtrim((string) config('ai.anthropic.base'), '/') . '/v1/messages', [
                'model'      => config('ai.anthropic.model'),
                'max_tokens' => 300,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [$mediaBlock, ['type' => 'text', 'text' => self::PROMPT]],
                ]],
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Errore di connessione al servizio AI: ' . $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => 'Il servizio AI ha risposto con un errore (HTTP ' . $response->status() . ').'];
        }

        $text = data_get($response->json(), 'content.0.text', '');

        return $this->parseExtraction((string) $text);
    }

    /**
     * Parse the LLM text response into normalized fields. Tolerates markdown
     * code fences and surrounding prose. Pure function — unit tested.
     *
     * @return array{ok:bool, haccp_scadenza?:?string, moca_numero?:?string, error?:string}
     */
    public function parseExtraction(string $text): array
    {
        $text = trim($text);
        // Strip ```json ... ``` fences if present.
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $text = $m[0];
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            return ['ok' => false, 'error' => 'Risposta AI non interpretabile.'];
        }

        $scadenza = $data['haccp_scadenza'] ?? null;
        if ($scadenza) {
            try {
                $scadenza = Carbon::parse($scadenza)->toDateString();
            } catch (\Throwable $e) {
                $scadenza = null;
            }
        }

        $numero = $data['moca_numero'] ?? null;
        $numero = ($numero === '' || $numero === null) ? null : (string) $numero;

        return ['ok' => true, 'haccp_scadenza' => $scadenza, 'moca_numero' => $numero];
    }
}
