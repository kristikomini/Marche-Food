<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Scheda di Produzione (template) — {{ $scheda->modello }} REV{{ $scheda->revisione }}</title>
@php
    use Carbon\Carbon;

    $prodotto  = $scheda->prodotto;
    $modello   = $scheda->modello ?? 'M2PO3';
    $revisione = $scheda->revisione;
    $dataRev   = $scheda->data_revisione ? Carbon::parse($scheda->data_revisione)->format('d/m/Y') : null;

    $varianti = $prodotto?->varianti ?? collect();

    // Righe materie prime dalla ricetta della scheda (lotto/fornitore da compilare a mano).
    $materie = $scheda->ricette->map(fn ($r) => [
        'nome'      => $r->materiaPrima?->nome ?? '—',
        'fornitore' => $r->fornitore?->ragione_sociale,
    ]);
    $materieVuote = max(0, 10 - $materie->count());

    $imballaggi = $scheda->imballaggi->map(fn ($i) => [
        'componente' => $i->componente,
        'fornitore'  => $i->fornitore?->ragione_sociale,
    ]);
    $imbVuote = max(0, 6 - $imballaggi->count());

    $gas = $scheda->gas->map(fn ($g) => [
        'nome'      => $g->nome,
        'fornitore' => $g->fornitore?->ragione_sociale,
    ]);

    // Ciclo di lavoro: dai flussi della scheda, altrimenti default di config.
    $ciclo = $scheda->flussi->map(fn ($f) => [
        'numero'    => $f->flusso?->numero,
        'nome'      => $f->flusso?->nome,
        'controllo' => $f->flusso?->controllo,
    ]);
    if ($ciclo->isEmpty()) {
        $ciclo = collect(config('haccp.ciclo_lavoro_default', []))->map(fn ($c) => [
            'numero' => $c['numero'], 'nome' => $c['nome'], 'controllo' => null,
        ]);
    }
    $cicloVuote = max(0, 7 - $ciclo->count());

    $logoPath = public_path('favicon.png');
    $logo = (extension_loaded('gd') && is_file($logoPath))
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; background: #fff; padding: 18px; }
  .titolo { border: 1.5px solid #111; }
  .titolo table { width: 100%; border-collapse: collapse; }
  .titolo td { padding: 5px 8px; vertical-align: middle; }
  .titolo .brand { font-size: 8px; font-weight: 700; color: #1f5040; }
  .titolo .name { font-size: 15px; font-weight: 800; letter-spacing: .04em; text-align: center; }
  .titolo .rev { font-size: 9px; text-align: center; }
  .titolo .rev b { font-size: 10px; }
  table.grid { width: 100%; border-collapse: collapse; }
  table.grid td, table.grid th { border: 1px solid #111; padding: 4px 6px; vertical-align: middle; }
  .lbl { font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: .02em; }
  .val { font-size: 11px; font-weight: 600; }
  .hand { min-height: 16px; }
  .sect-head td { background: #e8efe9; font-weight: 700; text-transform: uppercase; font-size: 8.5px; letter-spacing: .04em; }
  .center { text-align: center; }
  .foot-note { font-size: 8px; color: #333; margin-top: 4px; line-height: 1.5; }
  .box { display: inline-block; width: 12px; height: 12px; border: 1px solid #111; vertical-align: middle; }
  .mt { margin-top: 8px; }
  .dot { border-bottom: 1px dotted #111; display: inline-block; min-width: 90px; }
</style>
</head>
<body>

<div class="titolo">
  <table>
    <tr>
      <td style="width:22%">
        @if($logo)<img src="{{ $logo }}" alt="MIF" style="width:26px;height:26px;vertical-align:middle;margin-right:5px">@endif
        <span class="brand">MARCHE<br>INTERNATIONAL FOOD</span>
      </td>
      <td style="width:48%"><div class="name">SCHEDA DI PRODUZIONE</div></td>
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
    <td class="hand">&nbsp;</td>
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
      <td class="hand">n°</td>
    </tr>
  @empty
    <tr><td class="hand">&nbsp;</td><td class="hand">&nbsp;</td><td class="hand">n°</td></tr>
  @endforelse
</table>

<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">MATERIE PRIME</td>
    <td style="width:27%">LOTTO</td>
    <td style="width:27%">FORNITORE</td>
  </tr>
  @foreach($materie as $m)
    <tr><td>{{ $m['nome'] }}</td><td class="hand">&nbsp;</td><td>{{ $m['fornitore'] ?: '' }}</td></tr>
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
  @foreach($imballaggi as $imb)
    <tr><td>{{ $imb['componente'] }}</td><td class="hand">&nbsp;</td><td>{{ $imb['fornitore'] ?: '' }}</td></tr>
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
  @forelse($gas as $g)
    <tr><td>{{ $g['nome'] }}</td><td class="hand">&nbsp;</td><td>{{ $g['fornitore'] ?: '' }}</td></tr>
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
      <td>@if($c['controllo'])<b>{{ $c['controllo'] }}:</b>@endif&nbsp;</td>
      <td>&nbsp;</td>
      <td class="hand center">&nbsp;</td>
    </tr>
  @endforeach
  @for($i = 0; $i < $cicloVuote; $i++)
    <tr><td class="hand">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  @endfor
</table>

<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">FUNZIONAMENTO METAL DETECTOR</td>
    <td style="width:27%">Inizio conf. <span class="dot"></span></td>
    <td style="width:27%">Fine conf. <span class="dot"></span></td>
  </tr>
  @foreach($campioni as $c)
    <tr>
      <td>Campione {{ $c['n'] }} Rilevato</td>
      <td class="center">OK <span class="box"></span></td>
      <td class="center">KO <span class="box"></span></td>
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
