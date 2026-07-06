<?php

namespace App\Http\Controllers;

use App\Models\MateriaPrima;
use App\Services\AllergenService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MateriaPrimaController extends Controller
{
    public function index(Request $request)
    {
        $query = MateriaPrima::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'ilike', "%{$search}%")
                  ->orWhere('codice', 'like', "%{$search}%");
            });
        }

        $materie = $query->orderBy('nome')->paginate(25)->withQueryString();

        return Inertia::render('MateriePrime/Index', [
            'materie'          => $materie,
            'filters'          => $request->only(['search']),
            'allergeniLabels'  => AllergenService::EU_ALLERGENS,
        ]);
    }

    public function create()
    {
        return Inertia::render('MateriePrime/Form', [
            'materia'          => null,
            'allergeniOptions' => AllergenService::options(),
        ]);
    }

    public function store(Request $request)
    {
        MateriaPrima::create($this->validated($request));

        return redirect()->route('materie-prime.index')->with('success', 'Materia prima creata.');
    }

    public function edit(MateriaPrima $materiePrime)
    {
        return Inertia::render('MateriePrime/Form', [
            'materia'          => $materiePrime,
            'allergeniOptions' => AllergenService::options(),
        ]);
    }

    public function update(Request $request, MateriaPrima $materiePrime)
    {
        $materiePrime->update($this->validated($request, $materiePrime->id));

        return redirect()->route('materie-prime.index')->with('success', 'Materia prima aggiornata.');
    }

    public function destroy(MateriaPrima $materiePrime)
    {
        $materiePrime->delete();

        return redirect()->route('materie-prime.index')->with('success', 'Materia prima eliminata.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'codice' => ['nullable', 'integer',
                Rule::unique('materie_prime', 'codice')->ignore($ignoreId)],
            'nome'   => ['required', 'string', 'max:200'],
            'allergeni'          => ['nullable', 'array'],
            'allergeni.*'        => ['string', Rule::in(array_keys(AllergenService::EU_ALLERGENS))],
            'allergeni_tracce'   => ['nullable', 'array'],
            'allergeni_tracce.*' => ['string', Rule::in(array_keys(AllergenService::EU_ALLERGENS))],
        ]);
    }
}
