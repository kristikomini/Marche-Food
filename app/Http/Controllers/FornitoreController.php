<?php

namespace App\Http\Controllers;

use App\Models\Fornitore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FornitoreController extends Controller
{
    public function index(Request $request): Response
    {
        $fornitori = Fornitore::query()
            ->when($request->search, fn($q, $s) => $q->where('ragione_sociale', 'ilike', "%{$s}%")
                ->orWhere('codice', 'ilike', "%{$s}%"))
            ->when($request->tipo, fn($q, $t) => $q->where('tipo', $t))
            ->orderBy('ragione_sociale')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Fornitori/Index', [
            'fornitori' => $fornitori,
            'filters'   => $request->only(['search', 'tipo']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Fornitori/Form', [
            'fornitore' => null,
        ]);
    }

    /**
     * Esporta l'elenco fornitori in CSV (apribile direttamente in Excel).
     * Rispetta gli stessi filtri della pagina indice.
     */
    public function export(Request $request)
    {
        $fornitori = Fornitore::query()
            ->when($request->search, fn($q, $s) => $q->where('ragione_sociale', 'ilike', "%{$s}%")
                ->orWhere('codice', 'ilike', "%{$s}%"))
            ->when($request->tipo, fn($q, $t) => $q->where('tipo', $t))
            ->orderBy('ragione_sociale')
            ->get();

        $tipoLabel = [
            'alimentare'            => 'Alimentare',
            'imballaggio_primario'  => 'Imballaggio Primario',
            'detergente_secondario' => 'Detergente',
            'conto_terzi'           => 'Conto Terzi',
        ];

        $filename = 'fornitori_' . now()->format('Ymd_His') . '.csv';

        $callback = function () use ($fornitori, $tipoLabel) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // BOM UTF-8 per Excel
            fputcsv($handle, [
                'Codice', 'Ragione Sociale', 'Tipo', 'Partita IVA', 'Indirizzo',
                'Email', 'Telefono', 'HACCP Certificato', 'Scad. HACCP',
                'MOCA Certificato', 'N° MOCA', 'Attivo', 'Note',
            ], ';');

            foreach ($fornitori as $f) {
                fputcsv($handle, [
                    $f->codice,
                    $f->ragione_sociale,
                    $tipoLabel[$f->tipo] ?? $f->tipo,
                    $f->piva,
                    $f->indirizzo,
                    $f->email,
                    $f->telefono,
                    $f->haccp_certificato ? 'Sì' : 'No',
                    $f->haccp_scadenza?->format('d/m/Y'),
                    $f->moca_certificato ? 'Sì' : 'No',
                    $f->moca_numero,
                    $f->attivo ? 'Sì' : 'No',
                    $f->note,
                ], ';');
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Fornitore::create($data);

        return redirect()->route('fornitori.index')
            ->with('success', 'Fornitore creato con successo.');
    }

    public function edit(Fornitore $fornitore): Response
    {
        return Inertia::render('Fornitori/Form', [
            'fornitore' => [
                'id'                  => $fornitore->id,
                'codice'              => $fornitore->codice,
                'ragione_sociale'     => $fornitore->ragione_sociale,
                'tipo'                => $fornitore->tipo,
                'piva'                => $fornitore->piva,
                'indirizzo'           => $fornitore->indirizzo,
                'email'               => $fornitore->email,
                'telefono'            => $fornitore->telefono,
                'haccp_certificato'   => (bool) $fornitore->haccp_certificato,
                'haccp_scadenza'      => $fornitore->haccp_scadenza?->toDateString(),
                'certificazioni_note' => $fornitore->certificazioni_note,
                'moca_certificato'    => (bool) $fornitore->moca_certificato,
                'moca_numero'         => $fornitore->moca_numero,
                'attivo'              => (bool) $fornitore->attivo,
                'note'                => $fornitore->note,
            ],
        ]);
    }

    public function update(Request $request, Fornitore $fornitore)
    {
        $data = $this->validated($request, $fornitore->id);
        $fornitore->update($data);

        return redirect()->route('fornitori.index')
            ->with('success', 'Fornitore aggiornato con successo.');
    }

    public function destroy(Fornitore $fornitore)
    {
        $fornitore->delete();

        return redirect()->route('fornitori.index')
            ->with('success', 'Fornitore eliminato.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'codice'               => ['nullable', 'string', 'max:20', Rule::unique('fornitori', 'codice')->ignore($ignoreId)],
            'ragione_sociale'      => 'required|string|max:200',
            'tipo'                 => 'required|in:alimentare,imballaggio_primario,detergente_secondario,conto_terzi',
            'piva'                 => 'nullable|string|max:20',
            'indirizzo'            => 'nullable|string',
            'email'                => 'nullable|email|max:100',
            'telefono'             => 'nullable|string|max:30',
            'haccp_certificato'    => 'boolean',
            'haccp_scadenza'       => 'nullable|date',
            'certificazioni_note'  => 'nullable|string',
            'moca_certificato'     => 'boolean',
            'moca_numero'          => 'nullable|string|max:50',
            'attivo'               => 'boolean',
            'note'                 => 'nullable|string',
        ]);
    }
}
