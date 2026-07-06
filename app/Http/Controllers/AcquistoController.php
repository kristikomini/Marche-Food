<?php

namespace App\Http\Controllers;

use App\Models\Acquisto;
use App\Models\AcquistoRiga;
use App\Models\Fornitore;
use App\Models\MateriaPrima;
use App\Models\ProduzioneMateriaPrima;
use App\Models\VenditaRiga;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AcquistoController extends Controller
{
    public function index(Request $request)
    {
        $query = Acquisto::with('fornitore')
            ->withCount('righe');

        if ($search = $request->input('search')) {
            $query->where('numero_documento', 'ilike', "%{$search}%");
        }

        if ($fornitoreId = $request->input('fornitore_id')) {
            $query->where('fornitore_id', $fornitoreId);
        }

        if ($da = $request->input('da')) {
            $query->whereDate('data_documento', '>=', $da);
        }

        if ($a = $request->input('a')) {
            $query->whereDate('data_documento', '<=', $a);
        }

        $acquisti = $query->orderByDesc('data_documento')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $fornitori = Fornitore::where('attivo', true)
            ->orderBy('ragione_sociale')
            ->get(['id', 'ragione_sociale', 'codice']);

        return Inertia::render('Acquisti/Index', [
            'acquisti' => $acquisti,
            'fornitori' => $fornitori,
            'filters'  => $request->only(['search', 'fornitore_id', 'da', 'a']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Acquisti/Form', [
            'acquisto' => null,
            'fornitori' => Fornitore::where('tipo', 'alimentare')
                ->where('attivo', true)
                ->orderBy('ragione_sociale')
                ->get(['id', 'ragione_sociale', 'codice']),
            'materie' => $this->materieList(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);

        $acquisto = Acquisto::create([
            'fornitore_id'     => $data['fornitore_id'],
            'numero_documento' => $data['numero_documento'],
            'data_documento'   => $data['data_documento'],
            'tipo_documento'   => $data['tipo_documento'],
            'note'             => $data['note'] ?? null,
            'is_conto_terzi'   => $data['is_conto_terzi'] ?? false,
        ]);

        foreach ($data['righe'] as $riga) {
            $acquisto->righe()->create($riga);
        }

        return redirect()->route('acquisti.index')
            ->with('success', 'Acquisto registrato con successo.');
    }

    public function edit(Acquisto $acquisto)
    {
        $acquisto->load('righe');

        return Inertia::render('Acquisti/Form', [
            'acquisto' => $acquisto,
            'fornitori' => Fornitore::where('tipo', 'alimentare')
                ->where('attivo', true)
                ->orderBy('ragione_sociale')
                ->get(['id', 'ragione_sociale', 'codice']),
            'materie' => $this->materieList(),
        ]);
    }

    private function materieList()
    {
        return MateriaPrima::orderBy('nome')->get(['id', 'nome', 'codice']);
    }

    public function update(Request $request, Acquisto $acquisto)
    {
        // Optimistic locking: reject if the record changed since it was loaded.
        $this->assertNotStale($acquisto, $request);

        $data = $this->validateRequest($request);

        // Determine which existing righe the user is removing
        $existingIds   = $acquisto->righe()->pluck('id')->all();
        $submittedIds  = collect($data['righe'])->pluck('id')->filter()->values()->all();
        $toDeleteIds   = array_diff($existingIds, $submittedIds);

        // GAP-T1: refuse deletion of lines that are already linked to a production run
        if (!empty($toDeleteIds)) {
            $linkedCount = $acquisto->righe()
                ->whereIn('id', $toDeleteIds)
                ->whereHas('produzioniMateriePrime')
                ->count();

            if ($linkedCount > 0) {
                return back()->withErrors([
                    'righe' => "Impossibile eliminare {$linkedCount} riga/e: sono già collegate a produzioni registrate. Rimuovere prima le produzioni collegate.",
                ])->withInput();
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($acquisto, $data, $toDeleteIds) {
            $acquisto->update([
                'fornitore_id'     => $data['fornitore_id'],
                'numero_documento' => $data['numero_documento'],
                'data_documento'   => $data['data_documento'],
                'tipo_documento'   => $data['tipo_documento'],
                'note'             => $data['note'] ?? null,
                'is_conto_terzi'   => $data['is_conto_terzi'] ?? false,
            ]);

            // Delete only rows that were removed and are safe to delete
            if (!empty($toDeleteIds)) {
                $acquisto->righe()->whereIn('id', $toDeleteIds)->delete();
            }

            // GAP-T4: upsert remaining rows preserving IDs
            foreach ($data['righe'] as $rigaData) {
                $id = $rigaData['id'] ?? null;
                unset($rigaData['id']);

                if ($id) {
                    $riga = AcquistoRiga::where('id', $id)->where('acquisto_id', $acquisto->id)->first();
                    if ($riga) {
                        $riga->update($rigaData);
                    }
                } else {
                    $acquisto->righe()->create($rigaData);
                }
            }
        });

        return redirect()->route('acquisti.index')
            ->with('success', 'Acquisto aggiornato.');
    }

    public function destroy(Acquisto $acquisto)
    {
        // Soft-delete bypasses the DB foreign keys, so guard here: refuse to
        // trash a purchase whose lots are still used by an active production or
        // sale (a trashed production/sale no longer counts as a reference).
        $rigaIds = $acquisto->righe()->pluck('id');

        $consumed = ProduzioneMateriaPrima::whereIn('acquisto_riga_id', $rigaIds)
            ->whereHas('produzione')->exists();
        $sold = VenditaRiga::whereIn('acquisto_riga_id', $rigaIds)
            ->whereHas('vendita')->exists();

        if ($consumed || $sold) {
            return back()->with('error', 'Impossibile eliminare: alcuni lotti di questo acquisto sono utilizzati in produzioni o vendite attive. Elimina prima quei documenti.');
        }

        $acquisto->delete();

        return redirect()->route('acquisti.index')
            ->with('success', 'Acquisto spostato nel cestino.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'fornitore_id'       => ['required', 'exists:fornitori,id'],
            'numero_documento'   => ['required', 'string', 'max:50'],
            'data_documento'     => ['required', 'date'],
            'tipo_documento'     => ['required', 'in:DDT,Fattura,Bolla'],
            'note'               => ['nullable', 'string'],
            'is_conto_terzi'     => ['boolean'],
            'righe'              => ['required', 'array', 'min:1'],
            'righe.*.id'         => ['nullable', 'integer'],
            'righe.*.materia_prima_id' => ['nullable', 'integer', 'exists:materie_prime,id'],
            'righe.*.nome_prodotto' => ['required', 'string', 'max:200'],
            'righe.*.um'         => ['nullable', 'string', 'max:10'],
            'righe.*.quantita_pz' => ['nullable', 'numeric', 'min:0'],
            'righe.*.quantita_kg' => ['required', 'numeric', 'min:0.001'],
            'righe.*.lotto'      => ['nullable', 'string', 'max:100'],
            'righe.*.lotto_esterno' => ['nullable', 'string', 'max:100'],
            'righe.*.scadenza'          => ['nullable', 'date'],
            'righe.*.data_in'           => ['required', 'date'],
            'righe.*.data_out'          => ['nullable', 'date'],
            'righe.*.nota_credito_ref'  => ['nullable', 'string', 'max:50'],
        ]);
    }

    public function print(Acquisto $acquisto)
    {
        $acquisto->load(['fornitore', 'righe']);

        return Inertia::render('Acquisti/Print', ['acquisto' => $acquisto]);
    }

    public function export()
    {
        $righe = AcquistoRiga::with(['acquisto.fornitore'])
            ->whereHas('acquisto', fn($q) => $q->where('is_conto_terzi', false))
            ->orderBy('data_in', 'desc')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="acquisti_' . now()->format('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($righe) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($handle, ['Data Doc.', 'Fornitore', 'N° Documento', 'Prodotto', 'Lotto', 'Lotto Esterno', 'Q.tà (kg)', 'Scadenza', 'Data In', 'Data Out'], ';');
            foreach ($righe as $r) {
                fputcsv($handle, [
                    $r->acquisto?->data_documento,
                    $r->acquisto?->fornitore?->ragione_sociale,
                    $r->acquisto?->numero_documento,
                    $r->nome_prodotto,
                    $r->lotto,
                    $r->lotto_esterno,
                    $r->quantita_kg,
                    $r->scadenza,
                    $r->data_in,
                    $r->data_out,
                ], ';');
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, 'acquisti_' . now()->format('Ymd_His') . '.csv', $headers);
    }
}
