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
            'fornitore' => $fornitore,
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
            'tipo'                 => 'required|in:alimentare,imballaggio_primario,detergente_secondario',
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
