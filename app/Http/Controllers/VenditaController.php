<?php

namespace App\Http\Controllers;

use App\Models\Vendita;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VenditaController extends Controller
{
    public function index(Request $request)
    {
        $query = Vendita::with('cliente')->withCount('righe');

        if ($search = $request->input('search')) {
            $query->where('numero_documento', 'ilike', "%{$search}%");
        }

        if ($clienteId = $request->input('cliente_id')) {
            $query->where('cliente_id', $clienteId);
        }

        if ($da = $request->input('da')) {
            $query->whereDate('data_documento', '>=', $da);
        }

        if ($a = $request->input('a')) {
            $query->whereDate('data_documento', '<=', $a);
        }

        if ($tipo = $request->input('tipo_documento')) {
            $query->where('tipo_documento', $tipo);
        }

        $vendite = $query->orderByDesc('data_documento')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $clienti = Cliente::where('attivo', true)
            ->orderBy('ragione_sociale')
            ->get(['id', 'ragione_sociale', 'codice_cliente']);

        return Inertia::render('Vendite/Index', [
            'vendite'  => $vendite,
            'clienti'  => $clienti,
            'filters'  => $request->only(['search', 'cliente_id', 'da', 'a', 'tipo_documento']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Vendite/Form', [
            'vendita' => null,
            'clienti' => Cliente::where('attivo', true)
                ->orderBy('ragione_sociale')
                ->get(['id', 'ragione_sociale', 'codice_cliente']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);

        $vendita = Vendita::create([
            'cliente_id'      => $data['cliente_id'],
            'numero_documento' => $data['numero_documento'],
            'data_documento'  => $data['data_documento'],
            'tipo_documento'  => $data['tipo_documento'],
            'note'            => $data['note'] ?? null,
        ]);

        foreach ($data['righe'] as $riga) {
            $vendita->righe()->create($riga);
        }

        return redirect()->route('vendite.index')
            ->with('success', 'Vendita registrata con successo.');
    }

    public function edit(Vendita $vendita)
    {
        $vendita->load('righe');

        return Inertia::render('Vendite/Form', [
            'vendita' => $vendita,
            'clienti' => Cliente::where('attivo', true)
                ->orderBy('ragione_sociale')
                ->get(['id', 'ragione_sociale', 'codice_cliente']),
        ]);
    }

    public function update(Request $request, Vendita $vendita)
    {
        $data = $this->validateRequest($request);

        $existingIds  = $vendita->righe()->pluck('id')->all();
        $submittedIds = collect($data['righe'])->pluck('id')->filter()->values()->all();
        $toDeleteIds  = array_diff($existingIds, $submittedIds);

        // GAP-T1: refuse deletion of lines that have bolle di reso linked to them
        if (!empty($toDeleteIds)) {
            $linkedCount = $vendita->righe()
                ->whereIn('id', $toDeleteIds)
                ->whereHas('bolleReso')
                ->count();

            if ($linkedCount > 0) {
                return back()->withErrors([
                    'righe' => "Impossibile eliminare {$linkedCount} riga/e: sono già collegate a bolle di reso. Rimuovere prima le bolle di reso collegate.",
                ])->withInput();
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($vendita, $data, $toDeleteIds) {
            $vendita->update([
                'cliente_id'       => $data['cliente_id'],
                'numero_documento' => $data['numero_documento'],
                'data_documento'   => $data['data_documento'],
                'tipo_documento'   => $data['tipo_documento'],
                'note'             => $data['note'] ?? null,
            ]);

            if (!empty($toDeleteIds)) {
                $vendita->righe()->whereIn('id', $toDeleteIds)->delete();
            }

            // GAP-T4: upsert remaining rows preserving IDs
            foreach ($data['righe'] as $rigaData) {
                $id = $rigaData['id'] ?? null;
                unset($rigaData['id']);

                if ($id) {
                    $riga = \App\Models\VenditaRiga::where('id', $id)->where('vendita_id', $vendita->id)->first();
                    if ($riga) {
                        $riga->update($rigaData);
                    }
                } else {
                    $vendita->righe()->create($rigaData);
                }
            }
        });

        return redirect()->route('vendite.index')
            ->with('success', 'Vendita aggiornata.');
    }

    public function destroy(Vendita $vendita)
    {
        $vendita->delete();

        return redirect()->route('vendite.index')
            ->with('success', 'Vendita eliminata.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'cliente_id'         => ['required', 'exists:clienti,id'],
            'numero_documento'   => ['required', 'string', 'max:50'],
            'data_documento'     => ['required', 'date'],
            'tipo_documento'     => ['required', 'in:DDT,FI,NC'],
            'note'               => ['nullable', 'string'],
            'righe'              => ['required', 'array', 'min:1'],
            'righe.*.id'         => ['nullable', 'integer'],
            'righe.*.nome_prodotto' => ['required', 'string', 'max:200'],
            'righe.*.pezzatura_gr'  => ['nullable', 'numeric', 'min:0'],
            'righe.*.um'         => ['nullable', 'string', 'max:10'],
            'righe.*.quantita_pz' => ['nullable', 'numeric', 'min:0'],
            'righe.*.quantita_kg' => ['required', 'numeric', 'min:0.001'],
            'righe.*.lotto'      => ['nullable', 'string', 'max:100'],
            'righe.*.lotto_esterno' => ['nullable', 'string', 'max:100'],
            'righe.*.scadenza'   => ['nullable', 'date'],
        ]);
    }
}
