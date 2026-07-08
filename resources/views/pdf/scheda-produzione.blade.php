<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Scheda di Produzione — {{ $produzione->lotto_produzione }}</title>
@php
    use Carbon\Carbon;

    $scheda   = $produzione->scheda;
    $prodotto = $scheda?->prodotto;

    $modello   = $scheda?->modello ?? 'M2PO3';
    $revisione = $scheda?->revisione;
    $dataRev   = $scheda?->data_revisione ? Carbon::parse($scheda->data_revisione)->format('d/m/Y') : null;

    $varianti = $prodotto?->varianti ?? collect();
    $variantiVuote = max(0, 4 - $varianti->count());
    // N° confezioni per variante (dal run).
    $confPerVariante = $produzione->confezioni->keyBy('prodotto_variante_id');

    // Materie prime realmente utilizzate. Una materia prima può essere
    // prelevata da più lotti nello stesso run: raggruppiamo per ingrediente e
    // uniamo i lotti (e i fornitori) su un'unica riga, es. "GIO12602 + GIO42632-01".
    $materie = $produzione->materiePrime
        ->groupBy(fn ($mp) => $mp->materia_prima_id ?? $mp->materiaPrima?->nome)
        ->map(function ($gruppo) {
            $lotti = $gruppo->map(fn ($mp) => $mp->acquistoRiga?->lotto
                ?: $mp->acquistoRiga?->lotto_esterno
                ?: $mp->semilavorato?->lotto)
                ->filter()->unique()->implode(' + ');

            $fornitori = $gruppo->map(fn ($mp) => $mp->acquistoRiga?->acquisto?->fornitore?->ragione_sociale
                ?: ($mp->semilavorato ? 'Semilavorato interno' : null))
                ->filter()->unique()->implode(' + ');

            return [
                'nome'      => $gruppo->first()->materiaPrima?->nome ?? '—',
                'lotto'     => $lotti,
                'fornitore' => $fornitori,
            ];
        })
        ->values();
    $materieVuote = max(0, 10 - $materie->count());

    // Imballaggi: lotti reali del run; in mancanza, template della scheda.
    $imbRun = $produzione->imballaggiPrimari->map(fn ($i) => [
        'componente' => $i->lottoImballaggio?->componente,
        'lotto'      => $i->lottoImballaggio?->lotto,
        'fornitore'  => $i->lottoImballaggio?->fornitore?->ragione_sociale,
    ]);
    if ($imbRun->isEmpty() && $scheda) {
        $imbRun = $scheda->imballaggi->map(fn ($i) => [
            'componente' => $i->componente, 'lotto' => null,
            'fornitore'  => $i->fornitore?->ragione_sociale,
        ]);
    }
    $imbVuote = max(0, 6 - $imbRun->count());

    // Gas: lotti reali del run; in mancanza, template della scheda.
    $gasRun = $produzione->gas->map(fn ($g) => [
        'nome'      => $g->lottoGas?->componente,
        'lotto'     => $g->lottoGas?->lotto,
        'fornitore' => $g->lottoGas?->fornitore?->ragione_sociale,
    ]);
    if ($gasRun->isEmpty() && $scheda) {
        $gasRun = $scheda->gas->map(fn ($g) => [
            'nome' => $g->nome, 'lotto' => null, 'fornitore' => $g->fornitore?->ragione_sociale,
        ]);
    }

    // Ciclo di lavoro: compilato dal run; in mancanza, flussi della scheda o default.
    $ciclo = $produzione->ciclo->map(fn ($c) => [
        'numero' => $c->flusso?->numero,
        'nome'   => $c->nome ?: $c->flusso?->nome,
        'reg1'   => $c->registrazione_1,
        'reg2'   => $c->registrazione_2,
        'c'      => $c->controllo,
    ]);
    if ($ciclo->isEmpty()) {
        $src = ($scheda && $scheda->flussi->isNotEmpty())
            ? $scheda->flussi->map(fn ($f) => ['numero' => $f->flusso?->numero, 'nome' => $f->flusso?->nome, 'reg1' => $f->flusso?->controllo, 'reg2' => null, 'c' => false])
            : collect(config('haccp.ciclo_lavoro_default', []))->map(fn ($c) => ['numero' => $c['numero'], 'nome' => $c['nome'], 'reg1' => null, 'reg2' => null, 'c' => false]);
        $ciclo = $src;
    }
    $cicloVuote = max(0, 8 - $ciclo->count());

    $md = $produzione->metalDetector;
    $campioni = $campioni ?? config('haccp.metal_detector_campioni', []);
    $mdVal = fn ($n) => $md ? ($md->{'campione_' . $n} ?? null) : null;

    $logoPath = public_path('favicon.png');
    $logo = (extension_loaded('gd') && is_file($logoPath))
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp
<style>
  @page { margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.1; color: #111; background: #fff; padding: 6mm 7mm; }
  .titolo { border: 1.5px solid #111; }
  .titolo table { width: 100%; border-collapse: collapse; }
  .titolo td { padding: 4px 10px; vertical-align: middle; }
  .titolo .logo { height: 44px; width: auto; }
  .titolo .name { font-size: 18px; font-weight: 800; letter-spacing: .06em; text-align: center; }
  .titolo .rev { font-size: 10px; text-align: center; }
  .titolo .rev b { font-size: 11px; }
  table.grid { width: 100%; border-collapse: collapse; }
  table.grid td, table.grid th { border: 1px solid #111; padding: 2px 6px; vertical-align: middle; height: 15px; }
  .lbl { font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: .02em; }
  .val { font-size: 11px; font-weight: 600; }
  .hand { height: 15px; }
  .sect-head td { background: #dfe4df; font-weight: 700; text-transform: uppercase; font-size: 9px; letter-spacing: .04em; height: auto; }
  .center { text-align: center; }
  .foot-note { font-size: 8.5px; color: #222; margin-top: 6px; line-height: 1.6; }
  .box { display: inline-block; width: 13px; height: 13px; border: 1px solid #111; vertical-align: middle; text-align: center; line-height: 13px; font-weight: 800; }
  .mt { margin-top: 6px; }
  .dot { border-bottom: 1px dotted #111; display: inline-block; min-width: 90px; }
  .xmark { font-weight: 800; }
</style>
</head>
<body>

<div class="titolo">
  <table>
    <tr>
      <td style="width:24%">
        @if($logo)<img src="{{ $logo }}" alt="Marche International Food" class="logo">@endif
      </td>
      <td style="width:46%"><div class="name">SCHEDA DI PRODUZIONE</div></td>
      <td style="width:30%">
        <div class="rev"><b>{{ $modello }}{{ $revisione !== null ? ' REV' . $revisione : '' }}</b></div>
        <div class="rev">{{ $dataRev ? 'del ' . $dataRev : '' }}</div>
      </td>
    </tr>
  </table>
</div>

<table class="grid mt">
  <tr>
    <td style="width:28%" class="lbl">PRODOTTO:</td>
    <td class="val">{{ $prodotto?->nome ?? '—' }}</td>
  </tr>
  <tr>
    <td class="lbl">DATA DI PRODUZIONE / LOTTO:</td>
    <td class="val">
      {{ $produzione->data_produzione ? Carbon::parse($produzione->data_produzione)->format('d/m/Y') : '' }}
      &nbsp;·&nbsp; {{ $produzione->lotto_produzione }}
    </td>
  </tr>
</table>

<table class="grid">
  <tr class="sect-head">
    <td style="width:34%">CODICE PRODOTTO</td>
    <td style="width:33%">PEZZATURA</td>
    <td style="width:33%">N° CONFEZIONI</td>
  </tr>
  @forelse($varianti as $v)
    <tr>
      <td class="val">{{ $v->codice_prodotto }}</td>
      <td class="val">{{ $v->pezzatura_label ?? '' }}</td>
      <td class="val">{{ optional($confPerVariante->get($v->id))->n_confezioni !== null ? 'n° ' . $confPerVariante->get($v->id)->n_confezioni : 'n°' }}</td>
    </tr>
  @empty
    <tr><td class="hand">&nbsp;</td><td class="hand">&nbsp;</td><td class="hand">n°</td></tr>
  @endforelse
  @for($i = 0; $i < $variantiVuote; $i++)
    <tr><td class="hand">&nbsp;</td><td>&nbsp;</td><td>n°</td></tr>
  @endfor
</table>

<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">MATERIE PRIME</td>
    <td style="width:27%">LOTTO</td>
    <td style="width:27%">FORNITORE</td>
  </tr>
  @foreach($materie as $m)
    <tr><td>{{ $m['nome'] }}</td><td class="val">{{ $m['lotto'] ?: '' }}</td><td>{{ $m['fornitore'] ?: '' }}</td></tr>
  @endforeach
  @for($i = 0; $i < $materieVuote; $i++)
    <tr><td class="hand">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  @endfor
</table>

<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">IMBALLAGGI PRIMARI</td>
    <td style="width:27%">LOTTO</td>
    <td style="width:27%">FORNITORE</td>
  </tr>
  @foreach($imbRun as $imb)
    <tr><td>{{ $imb['componente'] ?: '' }}</td><td class="val">{{ $imb['lotto'] ?: '' }}</td><td>{{ $imb['fornitore'] ?: '' }}</td></tr>
  @endforeach
  @for($i = 0; $i < $imbVuote; $i++)
    <tr><td class="hand">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  @endfor
</table>

<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">GAS</td>
    <td style="width:27%">LOTTO</td>
    <td style="width:27%">FORNITORE</td>
  </tr>
  @forelse($gasRun as $g)
    <tr><td>{{ $g['nome'] ?: '' }}</td><td class="val">{{ $g['lotto'] ?: '' }}</td><td>{{ $g['fornitore'] ?: '' }}</td></tr>
  @empty
    <tr><td class="hand">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  @endforelse
</table>

<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">CICLO DI LAVORO</td>
    <td style="width:27%">REGISTRAZIONI</td>
    <td style="width:20%">REGISTRAZIONI</td>
    <td style="width:7%" class="center">C</td>
  </tr>
  @foreach($ciclo as $c)
    <tr>
      <td>{{ $c['numero'] ? $c['numero'] . '  ' : '' }}{{ $c['nome'] }}</td>
      <td class="val">{{ $c['reg1'] ?: '' }}</td>
      <td class="val">{{ $c['reg2'] ?: '' }}</td>
      <td class="center xmark">{{ !empty($c['c']) ? 'X' : '' }}</td>
    </tr>
  @endforeach
  @for($i = 0; $i < $cicloVuote; $i++)
    <tr><td class="hand">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  @endfor
</table>

<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">FUNZIONAMENTO METAL DETECTOR</td>
    <td style="width:27%">Inizio conf. <span class="dot">{{ $md?->inizio_conf }}</span></td>
    <td style="width:27%">Fine conf. <span class="dot">{{ $md?->fine_conf }}</span></td>
  </tr>
  @foreach($campioni as $c)
    @php $val = $mdVal($c['n']); @endphp
    <tr>
      <td>Campione {{ $c['n'] }} Rilevato</td>
      <td class="center">OK <span class="box">{{ $val === 'OK' ? 'X' : '' }}</span></td>
      <td class="center">KO <span class="box">{{ $val === 'KO' ? 'X' : '' }}</span></td>
    </tr>
  @endforeach
</table>

<div class="foot-note">
  @foreach($campioni as $c)
    Campione {{ $c['n'] }}: {{ $c['materiale'] }} - Diametro {{ $c['diametro'] }} - Codice {{ $c['codice'] }}<br>
  @endforeach
</div>

</body>
</html>
