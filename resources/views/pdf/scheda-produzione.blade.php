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

    $pezzatura = null;
    if ($prodotto && $prodotto->pezzatura_valore) {
        $pezzatura = rtrim(rtrim(number_format((float) $prodotto->pezzatura_valore, 3, ',', '.'), '0'), ',')
            . ' ' . ($prodotto->pezzatura_um ?? '');
    }

    // Materie prime utilizzate nel run (con lotto e fornitore reali).
    $materie = $produzione->materiePrime->map(function ($mp) {
        $lotto = $mp->acquistoRiga?->lotto
            ?: $mp->acquistoRiga?->lotto_esterno
            ?: $mp->semilavorato?->lotto;
        $fornitore = $mp->acquistoRiga?->acquisto?->fornitore?->ragione_sociale
            ?: ($mp->semilavorato ? 'Semilavorato interno' : null);
        return [
            'nome'      => $mp->materiaPrima?->nome ?? '—',
            'lotto'     => $lotto,
            'fornitore' => $fornitore,
        ];
    });

    // Righe imballaggi: etichette standard del modello; se il run ha lotti
    // collegati, li mostriamo, altrimenti la riga resta da compilare a mano.
    $imbStandard = ['Vaschetta gr 200', 'Film gr 200', 'Vaschetta kg1', 'Film kg1'];
    $imbRun = $produzione->imballaggiPrimari->map(fn ($i) => [
        'componente' => $i->lottoImballaggio?->componente,
        'lotto'      => $i->lottoImballaggio?->lotto,
        'fornitore'  => $i->lottoImballaggio?->fornitore?->ragione_sociale,
    ]);

    // Numero di righe vuote di riempimento per la sezione materie prime.
    $materieVuote = max(0, 8 - $materie->count());

    $logoPath = public_path('favicon.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
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
  .muted { color: #555; }
  .center { text-align: center; }
  .campioni td { border: none; font-size: 8.5px; padding: 1px 0; }
  .foot-note { font-size: 8px; color: #333; margin-top: 4px; line-height: 1.5; }
  .box { display: inline-block; width: 12px; height: 12px; border: 1px solid #111; vertical-align: middle; }
  .mt { margin-top: 8px; }
</style>
</head>
<body>

<!-- Intestazione -->
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

<!-- Prodotto / data / codice -->
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
  <tr>
    <td class="val">{{ $prodotto?->codice_prodotto ?? '' }}</td>
    <td class="val">{{ $pezzatura ?? '' }}</td>
    <td class="hand">&nbsp;</td>
  </tr>
</table>

<!-- Materie prime -->
<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">MATERIE PRIME</td>
    <td style="width:27%">LOTTO</td>
    <td style="width:27%">FORNITORE</td>
  </tr>
  @foreach($materie as $m)
    <tr>
      <td>{{ $m['nome'] }}</td>
      <td class="val">{{ $m['lotto'] ?: '' }}</td>
      <td>{{ $m['fornitore'] ?: '' }}</td>
    </tr>
  @endforeach
  @for($i = 0; $i < $materieVuote; $i++)
    <tr><td class="hand">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  @endfor
</table>

<!-- Imballaggi primari -->
<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">IMBALLAGGI PRIMARI</td>
    <td style="width:27%">LOTTO</td>
    <td style="width:27%">FORNITORE</td>
  </tr>
  @forelse($imbRun as $imb)
    <tr>
      <td>{{ $imb['componente'] ?: '' }}</td>
      <td class="val">{{ $imb['lotto'] ?: '' }}</td>
      <td>{{ $imb['fornitore'] ?: '' }}</td>
    </tr>
  @empty
    @foreach($imbStandard as $label)
      <tr><td>{{ $label }}</td><td class="hand">&nbsp;</td><td>&nbsp;</td></tr>
    @endforeach
  @endforelse
</table>

<!-- Gas -->
<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">GAS</td>
    <td style="width:27%">LOTTO</td>
    <td style="width:27%">FORNITORE</td>
  </tr>
  <tr>
    <td>TRESARIS NC30 bombola grande</td>
    <td class="hand">&nbsp;</td>
    <td>LINDE GAS</td>
  </tr>
</table>

<!-- Ciclo di lavoro -->
<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">CICLO DI LAVORO</td>
    <td style="width:27%">REGISTRAZIONI</td>
    <td style="width:20%">REGISTRAZIONI</td>
    <td style="width:7%" class="center">C</td>
  </tr>
  <tr><td>1 &nbsp; Prelievo prodotti</td><td>&nbsp;</td><td>&nbsp;</td><td class="hand center">&nbsp;</td></tr>
  <tr><td>3 &nbsp; Preparazione ingred. + additivi</td><td>&nbsp;</td><td>&nbsp;</td><td class="hand center">&nbsp;</td></tr>
  <tr><td>7 &nbsp; Porzionatura e confezionamento</td><td><b>Controllo peso:</b></td><td>&nbsp;</td><td class="hand center">&nbsp;</td></tr>
  <tr><td>10 &nbsp; Immagaz. Preparaz. pallet e Sped.</td><td>&nbsp;</td><td>&nbsp;</td><td class="hand center">&nbsp;</td></tr>
</table>

<!-- Metal detector -->
<table class="grid">
  <tr class="sect-head">
    <td style="width:46%">FUNZIONAMENTO METAL DETECTOR</td>
    <td style="width:27%">Inizio conf. .......................</td>
    <td style="width:27%">Fine conf. .......................</td>
  </tr>
  <tr><td>Campione 1 Rilevato</td><td class="center">OK <span class="box"></span></td><td class="center">KO <span class="box"></span></td></tr>
  <tr><td>Campione 2 Rilevato</td><td class="center">OK <span class="box"></span></td><td class="center">KO <span class="box"></span></td></tr>
  <tr><td>Campione 3 Rilevato</td><td class="center">OK <span class="box"></span></td><td class="center">KO <span class="box"></span></td></tr>
</table>

<div class="foot-note">
  Campione 1: Materiale Ferroso - Diametro 2,5 mm - Codice 260920<br>
  Campione 2: Materiale Non Ferroso - Diametro 3,5 mm - Codice 260967<br>
  Campione 3: Materiale Aisi 316 - Diametro 4,5 mm - Codice 260948
</div>

</body>
</html>
