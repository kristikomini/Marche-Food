<?php

namespace App\Http\Controllers;

use App\Models\Acquisto;
use App\Models\AcquistoRiga;
use App\Models\Vendita;
use App\Models\VenditaRiga;
use App\Models\Fornitore;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Import/Index');
    }

    public function importAcquisti(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        $path = $request->file('file')->getRealPath();
        $rows = $this->parseCsv($path);

        if (empty($rows)) {
            return back()->with('error', 'File CSV vuoto o non valido.');
        }

        $imported = 0;
        $errors   = [];
        $grouped  = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $key  = trim($row['fornitore_codice'] ?? '') . '|' . trim($row['numero_documento'] ?? '') . '|' . trim($row['data_documento'] ?? '');
            $grouped[$key][] = ['row' => $row, 'line' => $line];
        }

        // GAP-T2: wrap all inserts in a single transaction — all succeed or nothing is committed
        DB::beginTransaction();
        try {
            foreach ($grouped as $key => $items) {
                $first  = $items[0]['row'];
                $codice = trim($first['fornitore_codice'] ?? '');

                $fornitore = Fornitore::where('codice', $codice)->first();
                if (!$fornitore) {
                    $errors[] = "Riga {$items[0]['line']}: fornitore '{$codice}' non trovato.";
                    continue;
                }

                $dataDoc = $this->parseDate($first['data_documento'] ?? '');
                if (!$dataDoc) {
                    $errors[] = "Riga {$items[0]['line']}: data_documento non valida.";
                    continue;
                }

                // GAP-D5: normalize tipo_documento to canonical casing
                $tipo = match (strtoupper(trim($first['tipo_documento'] ?? ''))) {
                    'DDT'     => 'DDT',
                    'FATTURA' => 'Fattura',
                    'BOLLA'   => 'Bolla',
                    default   => 'DDT',
                };

                $acquisto = Acquisto::create([
                    'fornitore_id'     => $fornitore->id,
                    'numero_documento' => trim($first['numero_documento']),
                    'data_documento'   => $dataDoc,
                    'tipo_documento'   => $tipo,
                    'note'             => trim($first['note_documento'] ?? '') ?: null,
                ]);

                foreach ($items as $item) {
                    $r = $item['row'];
                    $kgVal = str_replace(',', '.', trim($r['quantita_kg'] ?? '0'));
                    if (!is_numeric($kgVal) || (float)$kgVal <= 0) {
                        $errors[] = "Riga {$item['line']}: quantita_kg non valida.";
                        continue;
                    }

                    $acquisto->righe()->create([
                        'nome_prodotto' => trim($r['nome_prodotto'] ?? ''),
                        'quantita_kg'   => (float)$kgVal,
                        'quantita_pz'   => is_numeric($r['quantita_pz'] ?? '') ? (float)str_replace(',', '.', $r['quantita_pz']) : null,
                        'lotto'         => trim($r['lotto'] ?? '') ?: null,
                        'lotto_esterno' => trim($r['lotto_esterno'] ?? '') ?: null,
                        'scadenza'      => $this->parseDate($r['scadenza'] ?? '') ?: null,
                        'data_in'       => $this->parseDate($r['data_in'] ?? '') ?: $dataDoc,
                    ]);
                    $imported++;
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                $msg = 'Importazione annullata (rollback completo). Correggere gli errori e riprovare. Errori: ' . implode('; ', array_slice($errors, 0, 5));
                return back()->with('error', $msg);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Errore imprevisto durante l\'importazione: ' . $e->getMessage());
        }

        $msg = "Importati {$imported} righe acquisto in " . count($grouped) . " documenti.";
        return redirect()->route('import.index')->with('success', $msg);
    }

    public function importVendite(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        $path = $request->file('file')->getRealPath();
        $rows = $this->parseCsv($path);

        if (empty($rows)) {
            return back()->with('error', 'File CSV vuoto o non valido.');
        }

        $imported = 0;
        $errors   = [];
        $grouped  = [];

        foreach ($rows as $i => $row) {
            $key = trim($row['cliente_codice'] ?? '') . '|' . trim($row['numero_documento'] ?? '') . '|' . trim($row['data_documento'] ?? '');
            $grouped[$key][] = ['row' => $row, 'line' => $i + 2];
        }

        // GAP-T2: all-or-nothing transaction
        DB::beginTransaction();
        try {
            foreach ($grouped as $key => $items) {
                $first  = $items[0]['row'];
                $codice = trim($first['cliente_codice'] ?? '');

                $cliente = Cliente::where('codice_cliente', $codice)->first();
                if (!$cliente) {
                    $errors[] = "Cliente '{$codice}' non trovato.";
                    continue;
                }

                $dataDoc = $this->parseDate($first['data_documento'] ?? '');
                if (!$dataDoc) {
                    $errors[] = "data_documento non valida per documento {$first['numero_documento']}.";
                    continue;
                }

                // GAP-D5: normalize tipo_documento to canonical values
                $tipo = match (strtoupper(trim($first['tipo_documento'] ?? ''))) {
                    'DDT' => 'DDT',
                    'FI'  => 'FI',
                    'NC'  => 'NC',
                    default => 'DDT',
                };

                $vendita = Vendita::create([
                    'cliente_id'       => $cliente->id,
                    'numero_documento' => trim($first['numero_documento']),
                    'data_documento'   => $dataDoc,
                    'tipo_documento'   => $tipo,
                    'note'             => trim($first['note_documento'] ?? '') ?: null,
                ]);

                foreach ($items as $item) {
                    $r = $item['row'];
                    $kgVal = str_replace(',', '.', trim($r['quantita_kg'] ?? '0'));
                    if (!is_numeric($kgVal) || (float)$kgVal <= 0) {
                        $errors[] = "Riga {$item['line']}: quantita_kg non valida.";
                        continue;
                    }

                    $vendita->righe()->create([
                        'nome_prodotto' => trim($r['nome_prodotto'] ?? ''),
                        'pezzatura_gr'  => is_numeric($r['pezzatura_gr'] ?? '') ? (float)str_replace(',', '.', $r['pezzatura_gr']) : null,
                        'quantita_kg'   => (float)$kgVal,
                        'quantita_pz'   => is_numeric($r['quantita_pz'] ?? '') ? (float)str_replace(',', '.', $r['quantita_pz']) : null,
                        'lotto'         => trim($r['lotto'] ?? '') ?: null,
                        'lotto_esterno' => trim($r['lotto_esterno'] ?? '') ?: null,
                        'scadenza'      => $this->parseDate($r['scadenza'] ?? '') ?: null,
                    ]);
                    $imported++;
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                $msg = 'Importazione annullata (rollback completo). Correggere gli errori e riprovare. Errori: ' . implode('; ', array_slice($errors, 0, 5));
                return back()->with('error', $msg);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Errore imprevisto durante l\'importazione: ' . $e->getMessage());
        }

        $msg = "Importate {$imported} righe vendita in " . count($grouped) . " documenti.";
        return redirect()->route('import.index')->with('success', $msg);
    }

    public function downloadTemplateAcquisti()
    {
        $headers = ['fornitore_codice','numero_documento','data_documento','tipo_documento','nome_prodotto','quantita_kg','quantita_pz','lotto','lotto_esterno','scadenza','data_in','note_documento'];
        return $this->csvResponse('template_acquisti.csv', $headers, [
            ['FOR001','DDT/2024/001','01/01/2024','DDT','Tonno al naturale','100.000','','A2024001','','31/12/2025','01/01/2024',''],
        ]);
    }

    public function downloadTemplateVendite()
    {
        $headers = ['cliente_codice','numero_documento','data_documento','tipo_documento','nome_prodotto','pezzatura_gr','quantita_kg','quantita_pz','lotto','lotto_esterno','scadenza','note_documento'];
        return $this->csvResponse('template_vendite.csv', $headers, [
            ['CLI001','DDT/2024/001','01/01/2024','DDT','Tonno all\'olio 800g','800','50.000','62','LP2024-001','','31/12/2025',''],
        ]);
    }

    private function parseCsv(string $path): array
    {
        $rows    = [];
        $handle  = fopen($path, 'r');
        $headers = null;

        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if ($line === false) continue;
            if (!$headers) {
                $headers = array_map('trim', $line);
                continue;
            }
            if (count($line) < count($headers)) {
                $line = array_pad($line, count($headers), '');
            }
            $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
        }

        fclose($handle);
        return $rows;
    }

    private function parseDate(string $d): ?string
    {
        if (!$d) return null;
        $d = trim($d);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $d, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }
        return null;
    }

    private function csvResponse(string $filename, array $headers, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
