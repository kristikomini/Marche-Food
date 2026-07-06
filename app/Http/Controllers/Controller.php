<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    /**
     * Optimistic-locking guard. If the client submitted the `updated_at` it
     * loaded and the record has since changed, reject the save so we don't
     * silently overwrite another user's edit.
     */
    protected function assertNotStale(Model $model, Request $request): void
    {
        $submitted = $request->input('updated_at');
        if (! $submitted || ! $model->updated_at) {
            return;
        }

        $submittedTs = \Illuminate\Support\Carbon::parse($submitted)->timestamp;
        if ($submittedTs !== $model->updated_at->timestamp) {
            throw ValidationException::withMessages([
                'updated_at' => 'Questo documento è stato modificato da un altro utente nel frattempo. Ricarica la pagina e riprova.',
            ]);
        }
    }
}
