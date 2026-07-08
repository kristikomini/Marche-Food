<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ragione_sociale', 'ilike', "%{$search}%")
                  ->orWhere('codice_cliente', 'ilike', "%{$search}%");
            });
        }

        if ($request->input('solo_attivi')) {
            $query->where('attivo', true);
        }

        $clienti = $query->orderBy('ragione_sociale')->paginate(25)->withQueryString();

        return Inertia::render('Clienti/Index', [
            'clienti' => $clienti,
            'filters' => $request->only(['search', 'solo_attivi']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Clienti/Form', ['cliente' => null]);
    }

    /**
     * Esporta l'elenco clienti in CSV (apribile direttamente in Excel).
     * Rispetta gli stessi filtri della pagina indice.
     */
    public function export(Request $request)
    {
        $query = Cliente::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ragione_sociale', 'ilike', "%{$search}%")
                  ->orWhere('codice_cliente', 'ilike', "%{$search}%");
            });
        }
        if ($request->input('solo_attivi')) {
            $query->where('attivo', true);
        }

        $clienti = $query->orderBy('ragione_sociale')->get();

        $headers = [
            'Codice Cliente', 'Ragione Sociale', 'Partita IVA', 'Indirizzo',
            'Email', 'Telefono', 'Zona', 'Agente', 'Categoria', 'Banca',
            'Cod. IVA', 'Valuta', 'Attivo', 'Note',
        ];

        $rows = $clienti->map(fn ($c) => [
            $c->codice_cliente,
            $c->ragione_sociale,
            $c->piva,
            $c->indirizzo,
            $c->email,
            $c->telefono,
            $c->zona,
            $c->agente,
            $c->categoria,
            $c->banca_appoggio,
            $c->codice_iva,
            $c->valuta,
            $c->attivo ? 'Sì' : 'No',
            $c->note,
        ])->all();

        $base = 'clienti_' . now()->format('Ymd_His');

        if ($request->input('format') === 'csv') {
            return $this->downloadCsv("{$base}.csv", $headers, $rows);
        }

        return \App\Support\SimpleXlsxWriter::make('Clienti')
            ->headers($headers)->rows($rows)->download("{$base}.xlsx");
    }

    /**
     * Maschera informazioni cliente — scheda anagrafica stampabile in PDF,
     * con riepilogo delle vendite registrate.
     */
    public function scheda(Cliente $cliente)
    {
        $vendite = $cliente->vendite()
            ->withCount('righe')
            ->withSum('righe', 'quantita_kg')
            ->orderByDesc('data_documento')
            ->orderByDesc('id')
            ->get();

        $riepilogo = [
            'n_documenti'  => $vendite->count(),
            'totale_kg'    => (float) $vendite->sum('righe_sum_quantita_kg'),
            'ultima'       => $vendite->first()?->data_documento,
            'per_tipo'     => $vendite->groupBy('tipo_documento')->map->count(),
        ];

        $pdf = Pdf::loadView('pdf.cliente', [
            'cliente'   => $cliente,
            'vendite'   => $vendite->take(15),
            'riepilogo' => $riepilogo,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('cliente_' . str_replace([' ', '/'], '_', $cliente->codice_cliente) . '.pdf');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Cliente::create($data);

        return redirect()->route('clienti.index')->with('success', 'Cliente creato con successo.');
    }

    public function edit(Cliente $cliente)
    {
        return Inertia::render('Clienti/Form', ['cliente' => $cliente]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $data = $this->validated($request, $cliente->id);
        $cliente->update($data);

        return redirect()->route('clienti.index')->with('success', 'Cliente aggiornato.');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('clienti.index')->with('success', 'Cliente eliminato.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'codice_cliente'  => ['required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('clienti', 'codice_cliente')->ignore($ignoreId)],
            'ragione_sociale' => ['required', 'string', 'max:200'],
            'piva'            => ['nullable', 'string', 'max:20'],
            'indirizzo'       => ['nullable', 'string'],
            'email'           => ['nullable', 'email', 'max:100'],
            'telefono'        => ['nullable', 'string', 'max:30'],
            'attivo'          => ['boolean'],
            'note'            => ['nullable', 'string'],
            'zona'                 => ['nullable', 'string', 'max:50'],
            'agente'               => ['nullable', 'string', 'max:100'],
            'categoria'            => ['nullable', 'string', 'max:50'],
            'banca_appoggio'       => ['nullable', 'string', 'max:150'],
            'codice_iva'           => ['nullable', 'string', 'max:20'],
            'valuta'               => ['nullable', 'string', 'max:20'],
            'aliquota_iva_default' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
