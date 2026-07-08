<?php

namespace App\Http\Controllers;

use App\Models\LottoImballaggioPrimario;
use App\Models\LottoDetergente;
use App\Models\LottoGas;
use App\Models\Fornitore;
use App\Models\ProduzioneImballaggioPrimario;
use App\Models\ProduzioneDetergente;
use App\Models\ProduzioneGas;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImballaggioController extends Controller
{
    // ─── INDEX (tabbed) ───────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $searchP = $request->input('search_p');
        $searchD = $request->input('search_d');

        $primari = LottoImballaggioPrimario::with('fornitore')
            ->when($searchP, fn($q) => $q->where(function ($q) use ($searchP) {
                $q->where('componente', 'ilike', "%{$searchP}%")
                  ->orWhere('lotto', 'ilike', "%{$searchP}%")
                  ->orWhere('numero_ddt', 'ilike', "%{$searchP}%");
            }))
            ->orderByDesc('data_in')->orderByDesc('id')
            ->paginate(20, ['*'], 'page_p')->withQueryString();

        $detergenti = LottoDetergente::with('fornitore')
            ->when($searchD, fn($q) => $q->where(function ($q) use ($searchD) {
                $q->where('componente', 'ilike', "%{$searchD}%")
                  ->orWhere('lotto', 'ilike', "%{$searchD}%")
                  ->orWhere('numero_ddt', 'ilike', "%{$searchD}%");
            }))
            ->orderByDesc('data_in')->orderByDesc('id')
            ->paginate(20, ['*'], 'page_d')->withQueryString();

        $searchG = $request->input('search_g');
        $gas = LottoGas::with('fornitore')
            ->when($searchG, fn($q) => $q->where(function ($q) use ($searchG) {
                $q->where('componente', 'ilike', "%{$searchG}%")
                  ->orWhere('lotto', 'ilike', "%{$searchG}%")
                  ->orWhere('numero_ddt', 'ilike', "%{$searchG}%");
            }))
            ->orderByDesc('data_in')->orderByDesc('id')
            ->paginate(20, ['*'], 'page_g')->withQueryString();

        return Inertia::render('Imballaggi/Index', [
            'primari'    => $primari,
            'detergenti' => $detergenti,
            'gas'        => $gas,
            'filters'    => $request->only(['search_p', 'search_d', 'search_g', 'tab']),
        ]);
    }

    // ─── IMBALLAGGI PRIMARI ───────────────────────────────────────────────────

    public function createPrimario()
    {
        return Inertia::render('Imballaggi/FormPrimario', [
            'lotto'     => null,
            'fornitori' => $this->fornitoriPrimari(),
        ]);
    }

    public function storePrimario(Request $request)
    {
        LottoImballaggioPrimario::create($this->validatePrimario($request));

        return redirect()->route('imballaggi.index', ['tab' => 'primari'])
            ->with('success', 'Lotto imballaggio registrato.');
    }

    public function editPrimario(LottoImballaggioPrimario $primario)
    {
        return Inertia::render('Imballaggi/FormPrimario', [
            'lotto'     => $primario,
            'fornitori' => $this->fornitoriPrimari(),
        ]);
    }

    public function updatePrimario(Request $request, LottoImballaggioPrimario $primario)
    {
        $primario->update($this->validatePrimario($request));

        return redirect()->route('imballaggi.index', ['tab' => 'primari'])
            ->with('success', 'Lotto imballaggio aggiornato.');
    }

    public function destroyPrimario(LottoImballaggioPrimario $primario)
    {
        if (ProduzioneImballaggioPrimario::where('lotto_imballaggio_id', $primario->id)
            ->whereHas('produzione')->exists()) {
            return back()->with('error', 'Impossibile eliminare: questo lotto imballaggio è utilizzato in una produzione attiva.');
        }

        $primario->delete();

        return redirect()->route('imballaggi.index', ['tab' => 'primari'])
            ->with('success', 'Lotto spostato nel cestino.');
    }

    // ─── DETERGENTI ──────────────────────────────────────────────────────────

    public function createDetergente()
    {
        return Inertia::render('Imballaggi/FormDetergente', [
            'lotto'     => null,
            'fornitori' => $this->fornitoriDetergenti(),
        ]);
    }

    public function storeDetergente(Request $request)
    {
        LottoDetergente::create($this->validateDetergente($request));

        return redirect()->route('imballaggi.index', ['tab' => 'detergenti'])
            ->with('success', 'Lotto detergente registrato.');
    }

    public function editDetergente(LottoDetergente $detergente)
    {
        return Inertia::render('Imballaggi/FormDetergente', [
            'lotto'     => $detergente,
            'fornitori' => $this->fornitoriDetergenti(),
        ]);
    }

    public function updateDetergente(Request $request, LottoDetergente $detergente)
    {
        $detergente->update($this->validateDetergente($request));

        return redirect()->route('imballaggi.index', ['tab' => 'detergenti'])
            ->with('success', 'Lotto detergente aggiornato.');
    }

    public function destroyDetergente(LottoDetergente $detergente)
    {
        if (ProduzioneDetergente::where('lotto_detergente_id', $detergente->id)
            ->whereHas('produzione')->exists()) {
            return back()->with('error', 'Impossibile eliminare: questo lotto detergente è utilizzato in una produzione attiva.');
        }

        $detergente->delete();

        return redirect()->route('imballaggi.index', ['tab' => 'detergenti'])
            ->with('success', 'Lotto spostato nel cestino.');
    }

    // ─── GAS ─────────────────────────────────────────────────────────────────

    public function createGas()
    {
        return Inertia::render('Imballaggi/FormGas', [
            'lotto'     => null,
            'fornitori' => $this->fornitoriGas(),
        ]);
    }

    public function storeGas(Request $request)
    {
        LottoGas::create($this->validateGas($request));

        return redirect()->route('imballaggi.index', ['tab' => 'gas'])
            ->with('success', 'Lotto gas registrato.');
    }

    public function editGas(LottoGas $gas)
    {
        return Inertia::render('Imballaggi/FormGas', [
            'lotto'     => $gas,
            'fornitori' => $this->fornitoriGas(),
        ]);
    }

    public function updateGas(Request $request, LottoGas $gas)
    {
        $gas->update($this->validateGas($request));

        return redirect()->route('imballaggi.index', ['tab' => 'gas'])
            ->with('success', 'Lotto gas aggiornato.');
    }

    public function destroyGas(LottoGas $gas)
    {
        if (ProduzioneGas::where('lotto_gas_id', $gas->id)->whereHas('produzione')->exists()) {
            return back()->with('error', 'Impossibile eliminare: questo lotto gas è utilizzato in una produzione attiva.');
        }

        $gas->delete();

        return redirect()->route('imballaggi.index', ['tab' => 'gas'])
            ->with('success', 'Lotto spostato nel cestino.');
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function fornitoriGas()
    {
        return Fornitore::where('attivo', true)
            ->orderBy('ragione_sociale')
            ->get(['id', 'ragione_sociale', 'codice']);
    }

    private function validateGas(Request $request): array
    {
        return $request->validate([
            'fornitore_id'    => ['required', 'exists:fornitori,id'],
            'componente'      => ['required', 'string', 'max:200'],
            'codice_articolo' => ['nullable', 'string', 'max:50'],
            'um'              => ['nullable', 'string', 'max:10'],
            'quantita'        => ['nullable', 'numeric', 'min:0'],
            'lotto'           => ['nullable', 'string', 'max:100'],
            'scadenza'        => ['nullable', 'date'],
            'numero_ddt'      => ['nullable', 'string', 'max:50'],
            'data_in'         => ['required', 'date'],
            'data_out'        => ['nullable', 'date', 'after_or_equal:data_in'],
            'note'            => ['nullable', 'string'],
        ]);
    }

    private function fornitoriPrimari()
    {
        return Fornitore::where('tipo', 'imballaggio_primario')
            ->where('attivo', true)
            ->orderBy('ragione_sociale')
            ->get(['id', 'ragione_sociale', 'codice']);
    }

    private function fornitoriDetergenti()
    {
        return Fornitore::where('tipo', 'detergente_secondario')
            ->where('attivo', true)
            ->orderBy('ragione_sociale')
            ->get(['id', 'ragione_sociale', 'codice']);
    }

    private function validatePrimario(Request $request): array
    {
        return $request->validate([
            'fornitore_id'   => ['required', 'exists:fornitori,id'],
            'componente'     => ['required', 'string', 'max:200'],
            'codice_articolo' => ['nullable', 'string', 'max:50'],
            'um'             => ['nullable', 'string', 'max:10'],
            'quantita'       => ['nullable', 'numeric', 'min:0'],
            'lotto'          => ['nullable', 'string', 'max:100'],
            'numero_ddt'     => ['nullable', 'string', 'max:50'],
            'data_in'        => ['required', 'date'],
            'data_out'       => ['nullable', 'date', 'after_or_equal:data_in'],
            'note'           => ['nullable', 'string'],
        ]);
    }

    private function validateDetergente(Request $request): array
    {
        return $request->validate([
            'fornitore_id'   => ['required', 'exists:fornitori,id'],
            'componente'     => ['required', 'string', 'max:200'],
            'codice_articolo' => ['nullable', 'string', 'max:50'],
            'um'             => ['nullable', 'string', 'max:10'],
            'quantita'       => ['nullable', 'numeric', 'min:0'],
            'lotto'          => ['nullable', 'string', 'max:100'],
            'scadenza'       => ['nullable', 'date'],
            'numero_ddt'     => ['nullable', 'string', 'max:50'],
            'data_in'        => ['required', 'date'],
            'data_out'       => ['nullable', 'date', 'after_or_equal:data_in'],
            'note'           => ['nullable', 'string'],
        ]);
    }
}
