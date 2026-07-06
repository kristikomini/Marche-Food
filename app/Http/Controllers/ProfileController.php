<?php

namespace App\Http\Controllers;

use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show(Request $request, TotpService $totp)
    {
        $user = $request->user();

        // Epic 4: 2FA is available to admins only.
        if (! $user->isAdmin()) {
            return Inertia::render('Profilo', [
                'twoFactor' => ['enabled' => false, 'pending' => false, 'adminOnly' => true],
            ]);
        }

        $twoFactor = [
            'enabled' => $user->hasTwoFactorEnabled(),
            // Setup in progress: secret set but not yet confirmed.
            'pending' => (bool) $user->two_factor_secret && ! $user->hasTwoFactorEnabled(),
            'otpauthUri' => null,
            'secret'   => null,
            'recoveryCodes' => null,
        ];

        if ($user->two_factor_secret && ! $user->hasTwoFactorEnabled()) {
            $twoFactor['secret'] = $user->two_factor_secret;
            $twoFactor['otpauthUri'] = $totp->otpauthUri(
                $user->two_factor_secret,
                $user->email,
                config('app.name', 'Marche Food')
            );
        }

        if ($user->hasTwoFactorEnabled()) {
            $twoFactor['recoveryCodes'] = (array) $user->two_factor_recovery_codes;
        }

        return Inertia::render('Profilo', [
            'twoFactor' => $twoFactor,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'La password attuale non è corretta.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password aggiornata con successo.');
    }
}
