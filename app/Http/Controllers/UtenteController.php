<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UtenteController extends Controller
{
    public function index()
    {
        $utenti = User::orderBy('name')->get(['id', 'name', 'email', 'role', 'created_at']);

        return Inertia::render('Utenti/Index', ['utenti' => $utenti]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'in:admin,operator'],
        ]);

        User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'],
        ]);

        return redirect()->route('utenti.index')
            ->with('success', 'Utente creato con successo.');
    }

    public function update(Request $request, User $utente)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email,' . $utente->id],
            'role'  => ['required', 'in:admin,operator'],
        ]);

        $utente->update(['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role']]);

        return redirect()->route('utenti.index')
            ->with('success', 'Utente aggiornato.');
    }

    public function resetPassword(Request $request, User $utente)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $utente->update(['password' => Hash::make($data['password'])]);

        return redirect()->route('utenti.index')
            ->with('success', 'Password reimpostata.');
    }

    public function destroy(User $utente)
    {
        if ($utente->id === auth()->id()) {
            return back()->with('error', 'Non puoi eliminare il tuo account.');
        }

        if ($utente->role === 'admin' && User::where('role', 'admin')->count() === 1) {
            return back()->with('error', 'Impossibile eliminare: è l\'ultimo amministratore del sistema.');
        }

        $utente->delete();

        return redirect()->route('utenti.index')
            ->with('success', 'Utente eliminato.');
    }
}
