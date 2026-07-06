<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    public function __construct(private TotpService $totp)
    {
    }

    // ── Enrollment (authenticated) ──────────────────────────────────────────

    /** Generate a fresh (unconfirmed) secret and return to the profile page. */
    public function enable(Request $request)
    {
        $user = $request->user();
        $user->two_factor_secret = $this->totp->generateSecret();
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        return back()->with('success', 'Scansiona il QR code con la tua app authenticator e inserisci il codice per confermare.');
    }

    /** Confirm the secret with a valid code; then issue recovery codes. */
    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (! $user->two_factor_secret) {
            throw ValidationException::withMessages(['code' => 'Nessuna configurazione 2FA in corso. Riprova.']);
        }

        if (! $this->totp->verify($user->two_factor_secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Codice non valido. Riprova.']);
        }

        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5) . '-' . Str::random(5)))->all();

        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = $codes;
        $user->save();

        return back()->with('success', 'Autenticazione a due fattori attivata. Salva i codici di recupero in un luogo sicuro.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        return back()->with('success', 'Autenticazione a due fattori disattivata.');
    }

    // ── Login challenge (guest, mid-login) ──────────────────────────────────

    public function showChallenge(Request $request)
    {
        if (! $request->session()->has('2fa.user')) {
            return redirect('/login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request)
    {
        $userId = $request->session()->get('2fa.user');
        if (! $userId) {
            return redirect('/login');
        }

        $user = User::find($userId);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['2fa.user', '2fa.remember']);
            return redirect('/login');
        }

        $code = trim((string) $request->input('code'));
        $recovery = trim((string) $request->input('recovery_code'));

        $ok = false;

        if ($code !== '' && $this->totp->verify($user->two_factor_secret, $code)) {
            $ok = true;
        } elseif ($recovery !== '') {
            $codes = (array) $user->two_factor_recovery_codes;
            $match = collect($codes)->first(fn ($c) => hash_equals($c, Str::upper($recovery)));
            if ($match) {
                // Consume the used recovery code.
                $user->two_factor_recovery_codes = array_values(array_filter($codes, fn ($c) => $c !== $match));
                $user->save();
                $ok = true;
            }
        }

        if (! $ok) {
            throw ValidationException::withMessages(['code' => 'Codice di verifica non valido.']);
        }

        $remember = (bool) $request->session()->get('2fa.remember', false);
        $request->session()->forget(['2fa.user', '2fa.remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
