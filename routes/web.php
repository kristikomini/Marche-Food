<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FornitoreController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\AcquistoController;
use App\Http\Controllers\VenditaController;
use App\Http\Controllers\ImballaggioController;
use App\Http\Controllers\ProdottoController;
use App\Http\Controllers\MateriaPrimaController;
use App\Http\Controllers\SchedaProduzioneController;
use App\Http\Controllers\ProduzioneController;
use App\Http\Controllers\BollaResoController;
use App\Http\Controllers\NotaCreditoController;
use App\Http\Controllers\DestinazioneIngredientiController;
use App\Http\Controllers\FlussoProduzioneController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\UtenteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TracciabilitaController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\RecallController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MagazzinoController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\CertificatoController;
use App\Http\Controllers\CestinoController;

// ─── Health / readiness probe (public, no auth) ─────────────────────────────────
Route::get('/health', [HealthController::class, 'show'])->name('health');

// ─── Auth (guest only) ───────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

    // Password reset
    Route::get('/forgot-password',  [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password',        [ResetPasswordController::class, 'reset'])->name('password.update');

    // Two-factor login challenge (mid-login, before session is authenticated)
    Route::get('/2fa/challenge',  [TwoFactorController::class, 'showChallenge'])->name('2fa.challenge');
    Route::post('/2fa/challenge', [TwoFactorController::class, 'verifyChallenge'])->name('2fa.verify')->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ─── Authenticated ────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Notifiche (centro avvisi)
    Route::get('notifiche', [NotificationController::class, 'index'])->name('notifiche.index');
    Route::post('notifiche/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('notifiche.dismiss');
    Route::post('notifiche/dismiss-all', [NotificationController::class, 'dismissAll'])->name('notifiche.dismiss-all');

    // Ricerca globale
    Route::get('cerca', [SearchController::class, 'index'])->name('cerca');

    // Tracciabilità lotti
    Route::get('tracciabilita', [TracciabilitaController::class, 'index'])->name('tracciabilita');
    Route::get('tracciabilita/search', [TracciabilitaController::class, 'search'])->name('tracciabilita.search');

    // Recall report + stateful workflow
    Route::get('recall', [RecallController::class, 'index'])->name('recall.index');
    Route::post('recall', [RecallController::class, 'store'])->name('recall.store');
    Route::get('recall/{recall}', [RecallController::class, 'show'])->name('recall.show');
    Route::put('recall/{recall}/stato', [RecallController::class, 'updateStato'])->name('recall.stato');
    Route::post('recall/{recall}/notifiche/{notifica}', [RecallController::class, 'markNotificato'])->name('recall.notifica');

    // Reportistica gestionale
    Route::get('report', [ReportController::class, 'index'])->name('report.index');
    Route::get('report/pdf', [ReportController::class, 'pdf'])->name('report.pdf');
    Route::get('report/csv', [ReportController::class, 'csv'])->name('report.csv');

    // Giacenze di magazzino
    Route::get('magazzino', [MagazzinoController::class, 'index'])->name('magazzino.index');
    Route::get('magazzino/export', [MagazzinoController::class, 'export'])->name('magazzino.export');

    // PDF download
    Route::get('produzioni/{produzione}/pdf', [ReportController::class, 'produzionePdf'])->name('produzioni.pdf');
    Route::get('produzioni/{produzione}/scheda', [ReportController::class, 'schedaProduzionePdf'])->name('produzioni.scheda');
    Route::get('acquisti/{acquisto}/pdf', [ReportController::class, 'acquistoPdf'])->name('acquisti.pdf');
    Route::get('vendite/{vendita}/pdf', [ReportController::class, 'venditaPdf'])->name('vendite.pdf');
    Route::get('produzioni/{produzione}/etichetta', [ReportController::class, 'produzioneEtichetta'])->name('produzioni.etichetta');
    Route::get('acquisti/{acquisto}/etichette', [ReportController::class, 'acquistoEtichette'])->name('acquisti.etichette');
    Route::get('vendite/{vendita}/etichette', [ReportController::class, 'venditaEtichette'])->name('vendite.etichette');

    // Kiosk mode (tablet, factory floor)
    Route::get('produzioni/kiosk', [KioskController::class, 'index'])->name('produzioni.kiosk');
    Route::get('produzioni/kiosk/lookup', [KioskController::class, 'lookup'])->name('produzioni.kiosk.lookup');

    // CSV exports
    Route::get('acquisti/export',   [AcquistoController::class,   'export'])->name('acquisti.export');
    Route::get('vendite/export',    [VenditaController::class,    'export'])->name('vendite.export');
    Route::get('produzioni/export', [ProduzioneController::class, 'export'])->name('produzioni.export');
    Route::get('fornitori/export',  [FornitoreController::class,  'export'])->name('fornitori.export');
    Route::get('clienti/export',    [ClienteController::class,    'export'])->name('clienti.export');

    // Schede di produzione — maschera cliente (PDF)
    Route::get('clienti/{cliente}/scheda', [ClienteController::class, 'scheda'])->name('clienti.scheda');

    // Profile
    Route::get('profilo', [ProfileController::class, 'show'])->name('profilo');
    Route::put('profilo/password', [ProfileController::class, 'updatePassword'])->name('profilo.password');
    // 2FA enrollment: admins only (Epic 4)
    Route::middleware('admin')->group(function () {
        Route::post('profilo/2fa/enable',  [TwoFactorController::class, 'enable'])->name('profilo.2fa.enable');
        Route::post('profilo/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('profilo.2fa.confirm');
        Route::delete('profilo/2fa',       [TwoFactorController::class, 'disable'])->name('profilo.2fa.disable');
    });

    // ── ANAGRAFICA: index for all, CRUD for admin only ──────────────────────

    Route::get('fornitori', [FornitoreController::class, 'index'])->name('fornitori.index');
    Route::get('clienti',   [ClienteController::class, 'index'])->name('clienti.index');
    Route::get('prodotti',  [ProdottoController::class, 'index'])->name('prodotti.index');
    Route::get('materie-prime', [MateriaPrimaController::class, 'index'])->name('materie-prime.index');
    Route::get('destinazione-ingredienti', [DestinazioneIngredientiController::class, 'index'])->name('destinazione-ingredienti.index');

    Route::middleware('admin')->group(function () {
        // AI certificate extraction (Epic 2)
        Route::post('fornitori/estrai-certificato', [CertificatoController::class, 'estrai'])->name('fornitori.estrai-certificato');

        Route::resource('fornitori', FornitoreController::class)
            ->except(['show', 'index'])
            ->parameters(['fornitori' => 'fornitore']);
        Route::resource('clienti', ClienteController::class)
            ->except(['show', 'index'])
            ->parameters(['clienti' => 'cliente']);
        Route::resource('prodotti', ProdottoController::class)
            ->except(['show', 'index'])
            ->parameters(['prodotti' => 'prodotto']);
        Route::resource('materie-prime', MateriaPrimaController::class)
            ->except(['show', 'index'])
            ->parameters(['materie-prime' => 'materiePrime']);
        Route::resource('destinazione-ingredienti', DestinazioneIngredientiController::class)
            ->only(['store', 'destroy'])
            ->parameters(['destinazione-ingredienti' => 'destinazioneIngredienti']);
    });

    // Scheda dettaglio materia prima (lotti in uscita + prodotti collegati).
    // Registrata dopo la resource admin cosicché `create`/`edit` abbiano priorità.
    Route::get('materie-prime/{materiePrime}', [MateriaPrimaController::class, 'show'])
        ->name('materie-prime.show');

    // ── SCREEN 1 — ALIMENTI ─────────────────────────────────────────────────
    // Operator: create + edit. Admin: also delete.

    Route::resource('acquisti', AcquistoController::class)
        ->except(['show', 'destroy'])
        ->parameters(['acquisti' => 'acquisto']);
    Route::resource('vendite', VenditaController::class)
        ->except(['show', 'destroy'])
        ->parameters(['vendite' => 'vendita']);
    Route::resource('bolle-reso', BollaResoController::class)
        ->except(['show', 'destroy'])
        ->parameters(['bolle-reso' => 'bolleReso']);
    Route::resource('note-credito', NotaCreditoController::class)
        ->except(['show', 'destroy'])
        ->parameters(['note-credito' => 'noteCredito']);

    // ── SCREEN 2 — IMBALLAGGI ───────────────────────────────────────────────

    Route::get('imballaggi', [ImballaggioController::class, 'index'])->name('imballaggi.index');
    Route::get('imballaggi/primari/create',           [ImballaggioController::class, 'createPrimario'])->name('imballaggi.primari.create');
    Route::post('imballaggi/primari',                 [ImballaggioController::class, 'storePrimario'])->name('imballaggi.primari.store');
    Route::get('imballaggi/primari/{primario}/edit',  [ImballaggioController::class, 'editPrimario'])->name('imballaggi.primari.edit');
    Route::put('imballaggi/primari/{primario}',       [ImballaggioController::class, 'updatePrimario'])->name('imballaggi.primari.update');
    Route::get('imballaggi/detergenti/create',            [ImballaggioController::class, 'createDetergente'])->name('imballaggi.detergenti.create');
    Route::post('imballaggi/detergenti',                  [ImballaggioController::class, 'storeDetergente'])->name('imballaggi.detergenti.store');
    Route::get('imballaggi/detergenti/{detergente}/edit', [ImballaggioController::class, 'editDetergente'])->name('imballaggi.detergenti.edit');
    Route::put('imballaggi/detergenti/{detergente}',      [ImballaggioController::class, 'updateDetergente'])->name('imballaggi.detergenti.update');
    Route::get('imballaggi/gas/create',       [ImballaggioController::class, 'createGas'])->name('imballaggi.gas.create');
    Route::post('imballaggi/gas',             [ImballaggioController::class, 'storeGas'])->name('imballaggi.gas.store');
    Route::get('imballaggi/gas/{gas}/edit',   [ImballaggioController::class, 'editGas'])->name('imballaggi.gas.edit');
    Route::put('imballaggi/gas/{gas}',        [ImballaggioController::class, 'updateGas'])->name('imballaggi.gas.update');

    // ── SCREEN 3 — PRODUZIONE ───────────────────────────────────────────────
    // Schede: index for all, CRUD admin only. Produzioni: operator + admin.
    // Flussi: admin only (workflow configuration).

    Route::get('schede', [SchedaProduzioneController::class, 'index'])->name('schede.index');
    Route::get('schede/confronto', [SchedaProduzioneController::class, 'confronto'])->name('schede.confronto');
    Route::get('schede/{schede}/print', [SchedaProduzioneController::class, 'print'])->name('schede.print');
    Route::get('schede/{schede}/pdf', [SchedaProduzioneController::class, 'pdfVuota'])->name('schede.pdf');
    Route::get('acquisti/{acquisto}/print', [AcquistoController::class, 'print'])->name('acquisti.print');
    Route::get('produzioni/{produzione}/print', [ProduzioneController::class, 'print'])->name('produzioni.print');
    Route::post('produzioni/{produzione}/semilavorato', [ProduzioneController::class, 'storeSemilavorato'])->name('produzioni.semilavorato.store');
    Route::resource('produzioni', ProduzioneController::class)
        ->except(['show', 'destroy'])
        ->parameters(['produzioni' => 'produzione']);

    // ── ADMIN-ONLY routes ───────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {

        // Delete operational records
        Route::delete('acquisti/{acquisto}',       [AcquistoController::class, 'destroy'])->name('acquisti.destroy');
        Route::delete('vendite/{vendita}',         [VenditaController::class, 'destroy'])->name('vendite.destroy');
        Route::delete('bolle-reso/{bolleReso}',    [BollaResoController::class, 'destroy'])->name('bolle-reso.destroy');
        Route::delete('note-credito/{noteCredito}',[NotaCreditoController::class, 'destroy'])->name('note-credito.destroy');
        Route::delete('imballaggi/primari/{primario}',    [ImballaggioController::class, 'destroyPrimario'])->name('imballaggi.primari.destroy');
        Route::delete('imballaggi/detergenti/{detergente}', [ImballaggioController::class, 'destroyDetergente'])->name('imballaggi.detergenti.destroy');
        Route::delete('imballaggi/gas/{gas}',               [ImballaggioController::class, 'destroyGas'])->name('imballaggi.gas.destroy');
        Route::delete('produzioni/{produzione}',   [ProduzioneController::class, 'destroy'])->name('produzioni.destroy');

        // Schede CRUD
        Route::resource('schede', SchedaProduzioneController::class)
            ->except(['show', 'index'])
            ->parameters(['schede' => 'schede']);

        // Flussi di lavorazione (workflow configuration)
        Route::resource('flussi', FlussoProduzioneController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['flussi' => 'flussi']);

        // Import dati storici
        Route::get('import', [ImportController::class, 'index'])->name('import.index');
        Route::post('import/acquisti', [ImportController::class, 'importAcquisti'])->name('import.acquisti');
        Route::post('import/vendite',  [ImportController::class, 'importVendite'])->name('import.vendite');
        Route::get('import/template-acquisti', [ImportController::class, 'downloadTemplateAcquisti'])->name('import.template-acquisti');
        Route::get('import/template-vendite',  [ImportController::class, 'downloadTemplateVendite'])->name('import.template-vendite');

        // Audit log (chi ha fatto cosa)
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');

        // Cestino (ripristino / eliminazione definitiva dei documenti eliminati)
        Route::get('cestino', [CestinoController::class, 'index'])->name('cestino.index');
        Route::post('cestino/{tipo}/{id}/restore', [CestinoController::class, 'restore'])->name('cestino.restore');
        Route::delete('cestino/{tipo}/{id}', [CestinoController::class, 'forceDelete'])->name('cestino.force-delete');

        // Gestione utenti
        Route::resource('utenti', UtenteController::class)
            ->except(['show'])
            ->parameters(['utenti' => 'utente']);
        Route::post('utenti/{utente}/reset-password', [UtenteController::class, 'resetPassword'])->name('utenti.reset-password');
    });
});
