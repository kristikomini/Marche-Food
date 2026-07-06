<?php

namespace App\Http\Controllers;

use App\Models\Produzione;
use App\Models\Recall;
use App\Models\RecallNotifica;
use App\Models\VenditaRiga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RecallController extends Controller
{
    /** Search tool + list of existing recalls. */
    public function index(Request $request)
    {
        $query = trim($request->input('q', ''));

        $produzioni   = collect();
        $venditeRighe = collect();

        if ($query !== '') {
            $produzioni = Produzione::with('scheda.prodotto')
                ->where('lotto_produzione', 'ilike', "%{$query}%")
                ->orderByDesc('data_produzione')
                ->limit(20)
                ->get();

            $lottiProduzione = $produzioni->pluck('lotto_produzione')->all();

            $venditeRighe = VenditaRiga::with(['vendita.cliente'])
                ->where(function ($q) use ($lottiProduzione, $query) {
                    $q->whereIn('lotto', $lottiProduzione)
                      ->orWhereIn('lotto_esterno', $lottiProduzione);
                    if (!empty($query)) {
                        $q->orWhere('lotto', 'ilike', "%{$query}%")
                          ->orWhere('lotto_esterno', 'ilike', "%{$query}%");
                    }
                })
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
        }

        $recalls = Recall::withCount([
                'notifiche',
                'notifiche as notificate_count' => fn ($q) => $q->where('notificato', true),
            ])
            ->orderByRaw("CASE stato WHEN 'aperto' THEN 0 WHEN 'in_corso' THEN 1 ELSE 2 END")
            ->orderByDesc('data_apertura')
            ->limit(50)
            ->get();

        return Inertia::render('Recall/Index', [
            'q'            => $query,
            'produzioni'   => $produzioni,
            'venditeRighe' => $venditeRighe,
            'recalls'      => $recalls,
        ]);
    }

    /** Open a recall for a lot, auto-populating the customers to notify. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'lotto'    => ['required', 'string', 'max:100'],
            'prodotto' => ['nullable', 'string', 'max:200'],
            'motivo'   => ['required', 'string'],
        ]);

        $recall = DB::transaction(function () use ($data) {
            $recall = Recall::create([
                'lotto'         => $data['lotto'],
                'prodotto'      => $data['prodotto'] ?? null,
                'motivo'        => $data['motivo'],
                'stato'         => 'aperto',
                'data_apertura' => now()->toDateString(),
            ]);

            // Affected sales lines for this lot become notification tasks.
            $righe = VenditaRiga::with('vendita.cliente')
                ->where('lotto', $data['lotto'])
                ->orWhere('lotto_esterno', $data['lotto'])
                ->get();

            foreach ($righe as $r) {
                $recall->notifiche()->create([
                    'cliente_id'      => $r->vendita?->cliente_id,
                    'vendita_riga_id' => $r->id,
                    'documento'       => $r->vendita?->numero_documento,
                    'quantita_kg'     => $r->quantita_kg,
                    'notificato'      => false,
                ]);
            }

            return $recall;
        });

        return redirect()->route('recall.show', $recall)
            ->with('success', 'Recall aperto. ' . $recall->notifiche()->count() . ' cliente/i da notificare.');
    }

    public function show(Recall $recall)
    {
        $recall->load(['notifiche.cliente']);

        return Inertia::render('Recall/Show', [
            'recall'   => $recall,
            'notifiche' => $recall->notifiche()->with('cliente')->orderBy('notificato')->get(),
        ]);
    }

    /** Toggle a customer notification as done/undone. */
    public function markNotificato(Request $request, Recall $recall, RecallNotifica $notifica)
    {
        abort_unless($notifica->recall_id === $recall->id, 404);

        $notificato = $request->boolean('notificato', true);
        $notifica->update([
            'notificato'    => $notificato,
            'notificato_at' => $notificato ? now() : null,
        ]);

        // Auto-advance state to in_corso once at least one notification is done.
        if ($notificato && $recall->stato === 'aperto') {
            $recall->update(['stato' => 'in_corso']);
        }

        return back()->with('success', 'Notifica aggiornata.');
    }

    public function updateStato(Request $request, Recall $recall)
    {
        $data = $request->validate([
            'stato' => ['required', 'in:aperto,in_corso,chiuso'],
        ]);

        $recall->update([
            'stato'         => $data['stato'],
            'data_chiusura' => $data['stato'] === 'chiuso' ? now()->toDateString() : null,
        ]);

        return back()->with('success', 'Stato recall aggiornato.');
    }
}
