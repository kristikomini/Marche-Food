<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search)
    {
        $result = $search->search((string) $request->input('q', ''));

        return Inertia::render('Ricerca/Index', [
            'q'      => $result['q'],
            'gruppi' => $result['gruppi'],
        ]);
    }
}
