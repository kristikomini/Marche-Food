<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Vendita — {{ $vendita->numero_documento }}</title>
@php
    use Carbon\Carbon;

    $righe = $vendita->righe;

    $tipoLabel = [
        'DDT' => 'Documento di Trasporto',
        'FI'  => 'Fattura immediata DdT',
        'NC'  => 'Nota di Credito',
    ];

    $imponibile = 0.0;
    $perAliquota = [];               // aliquota => imponibile
    foreach ($righe as $r) {
        $imp = (float) ($r->importo_netto ?? 0);
        $imponibile += $imp;
        $al = (string) ($r->aliquota_iva ?? '0');
        $perAliquota[$al] = ($perAliquota[$al] ?? 0) + $imp;
    }
    $imposta = 0.0;
    foreach ($perAliquota as $al => $imp) {
        $imposta += round($imp * ((float) $al / 100), 2);
    }
    $imponibile = round($imponibile, 2);
    $imposta    = round($imposta, 2);
    $totale     = round($imponibile + $imposta, 2);

    $aliquotaPrincipale = null;
    if (!empty($perAliquota)) {
        arsort($perAliquota);
        $aliquotaPrincipale = array_key_first($perAliquota);
    }

    $eur = fn ($v) => number_format((float) $v, 2, ',', '.');
    $qty = fn ($v, $d = 3) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, $d, ',', '.'), '0'), ',');

    $logoPath = public_path('favicon.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
@endphp
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; background: #fff; padding: 20px; }
  .row { width: 100%; }
  .row:after { content: ""; display: table; clear: both; }
  .head-left { float: left; width: 55%; }
  .head-right { float: right; width: 42%; }
  .brand h1 { font-size: 14px; font-weight: 700; color: #1f5040; }
  .brand p { font-size: 8px; color: #64748b; line-height: 1.35; margin-top: 3px; }
  .dest-box { border: 1px solid #1f5040; border-radius: 4px; padding: 8px 10px; min-height: 70px; }
  .dest-box .lbl { font-size: 7px; letter-spacing: .08em; color: #94a3b8; text-transform: uppercase; }
  .dest-box .name { font-size: 12px; font-weight: 700; margin-top: 2px; }
  .dest-box .addr { font-size: 9px; color: #334155; margin-top: 2px; line-height: 1.35; }

  table.meta { width: 100%; border-collapse: collapse; margin-top: 12px; }
  table.meta td { border: 1px solid #cbd5e1; padding: 3px 5px; vertical-align: top; }
  table.meta .k { font-size: 6.5px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; display: block; }
  table.meta .v { font-size: 10px; font-weight: 600; color: #1e293b; }

  table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
  table.items th { background: #1f5040; color: #fff; font-size: 7px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; padding: 4px 4px; border: 1px solid #1f5040; }
  table.items td { padding: 4px 4px; border: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
  table.items .desc .lotto { font-size: 8px; color: #64748b; }
  .r { text-align: right; }
  .c { text-align: center; }

  table.tot { width: 100%; border-collapse: collapse; margin-top: 12px; }
  table.tot td { border: 1px solid #cbd5e1; padding: 4px 6px; }
  table.tot .k { font-size: 6.5px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; display: block; }
  table.tot .v { font-size: 11px; font-weight: 700; color: #1e293b; }
  table.tot .grand .v { color: #1f5040; font-size: 13px; }
  .chk { display: inline-block; width: 11px; height: 11px; border: 1px solid #111; vertical-align: middle; }
  .vfill { display: inline-block; min-height: 12px; }
  .brand-logo { width: 34px; height: 34px; vertical-align: middle; margin-right: 8px; }

  .scadenze { margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 8px; font-size: 9px; }
  .scadenze .lbl { font-size: 7px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; }
  .footer { margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 8px; color: #94a3b8; }
  .footer .cols { width: 100%; }
  .footer .cols:after { content: ""; display: table; clear: both; }
  .footer .l { float: left; }
  .footer .r { float: right; }
</style>
</head>
<body>

<div class="row">
  <div class="head-left">
    <table style="border-collapse:collapse">
      <tr>
        @if($logo)<td style="width:42px; vertical-align:top"><img class="brand-logo" src="{{ $logo }}" alt="MIF"></td>@endif
        <td style="vertical-align:top">
          <div class="brand">
            <h1>Marche International Food S.r.l.</h1>
            <p>
              Via G. Rossini, 63 — 62029 Tolentino (MC)<br>
              C.F. e P.Iva 01891440438<br>
              info@marcheinternationalfood.com — Tel. +39 0733 1715820
            </p>
          </div>
        </td>
      </tr>
    </table>
  </div>
  <div class="head-right">
    <div class="dest-box">
      <div class="lbl">Spett.le</div>
      <div class="name">{{ $vendita->cliente?->ragione_sociale ?? '—' }}</div>
      <div class="addr">{{ $vendita->cliente?->indirizzo ?? '' }}</div>
    </div>
  </div>
</div>

<table class="meta">
  <tr>
    <td style="width:12%"><span class="k">Cod. Cliente</span><span class="v">{{ $vendita->cliente?->codice_cliente ?? '—' }}</span></td>
    <td style="width:22%"><span class="k">Partita IVA</span><span class="v">{{ $vendita->cliente?->piva ?? '—' }}</span></td>
    <td style="width:14%"><span class="k">Valuta</span><span class="v">Euro</span></td>
    <td style="width:30%"><span class="k">Tipo Documento</span><span class="v">{{ $tipoLabel[$vendita->tipo_documento] ?? $vendita->tipo_documento }}</span></td>
    <td style="width:11%"><span class="k">N° Documento</span><span class="v">{{ $vendita->numero_documento }}</span></td>
    <td style="width:11%"><span class="k">Data</span><span class="v">{{ Carbon::parse($vendita->data_documento)->format('d/m/Y') }}</span></td>
  </tr>
  <tr>
    <td colspan="4"><span class="k">Condizioni di pagamento</span><span class="v">{{ $vendita->condizioni_pagamento ?: '—' }}</span></td>
    <td colspan="2"><span class="k">Causale del trasporto</span><span class="v">{{ $vendita->causale_trasporto ?: 'VENDITA' }}</span></td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th style="width:7%">Cod. Art.</th>
      <th style="width:34%; text-align:left">Descrizione</th>
      <th style="width:6%">U.M.</th>
      <th style="width:8%">Q.tà</th>
      <th style="width:11%">Prezzo Unit.</th>
      <th style="width:6%">SC.1%</th>
      <th style="width:6%">SC.2%</th>
      <th style="width:11%">Importo Netto</th>
      <th style="width:5%">IVA</th>
    </tr>
  </thead>
  <tbody>
    @forelse($righe as $r)
      @php
        $qtaFatt = (!empty($r->quantita_pz) && (float) $r->quantita_pz > 0) ? $r->quantita_pz : $r->quantita_kg;
      @endphp
      <tr>
        <td class="c">{{ $r->codice_articolo ?: '—' }}</td>
        <td class="desc">
          {{ $r->nome_prodotto }}
          @if($r->lotto || $r->lotto_esterno || $r->scadenza)
            <div class="lotto">
              @if($r->lotto || $r->lotto_esterno) Lotto N. {{ $r->lotto ?: $r->lotto_esterno }} @endif
              @if($r->scadenza) Scad. {{ Carbon::parse($r->scadenza)->format('d/m/Y') }} @endif
            </div>
          @endif
        </td>
        <td class="c">{{ $r->um ?: '—' }}</td>
        <td class="r">{{ $qty($qtaFatt) }}</td>
        <td class="r">{{ $r->prezzo_unitario !== null ? $eur($r->prezzo_unitario) : '—' }}</td>
        <td class="r">{{ $r->sconto_1 ? rtrim(rtrim(number_format($r->sconto_1, 2, ',', '.'), '0'), ',') : '' }}</td>
        <td class="r">{{ $r->sconto_2 ? rtrim(rtrim(number_format($r->sconto_2, 2, ',', '.'), '0'), ',') : '' }}</td>
        <td class="r">{{ $r->importo_netto !== null ? $eur($r->importo_netto) : '—' }}</td>
        <td class="c">{{ $r->aliquota_iva !== null ? rtrim(rtrim(number_format($r->aliquota_iva, 2, ',', '.'), '0'), ',') : '' }}</td>
      </tr>
    @empty
      <tr><td colspan="9" style="color:#94a3b8;font-style:italic">Nessuna riga.</td></tr>
    @endforelse
  </tbody>
</table>

@php
    $aliqLabel = $aliquotaPrincipale !== null
        ? rtrim(rtrim(number_format((float) $aliquotaPrincipale, 2, ',', '.'), '0'), ',')
        : '';
@endphp

<!-- Riepilogo economico (griglia completa come da modulo) -->
<table class="tot">
  <tr>
    <td style="width:14%"><span class="k">Imponibile</span><span class="v">{{ $eur($imponibile) }}</span></td>
    <td style="width:8%"><span class="k">Al. IVA</span><span class="v">{{ $aliqLabel }}</span></td>
    <td style="width:15%"><span class="k">Importo IVA</span><span class="v">{{ $eur($imposta) }}</span></td>
    <td style="width:17%"><span class="k">Totale Merce</span><span class="v">{{ $eur($imponibile) }}</span></td>
    <td style="width:10%"><span class="k">% Sconto</span><span class="v">&nbsp;</span></td>
    <td style="width:16%"><span class="k">Importo Sconto</span><span class="v">&nbsp;</span></td>
    <td style="width:20%"><span class="k">Netto Merce</span><span class="v">{{ $eur($imponibile) }}</span></td>
  </tr>
</table>
<table class="tot">
  <tr>
    <td style="width:14%"><span class="k">Bolli</span><span class="v">&nbsp;</span></td>
    <td style="width:16%"><span class="k">Spese Incasso</span><span class="v">&nbsp;</span></td>
    <td style="width:14%"><span class="k">Varie</span><span class="v">&nbsp;</span></td>
    <td style="width:16%"><span class="k">Acconto</span><span class="v">&nbsp;</span></td>
    <td style="width:20%" class="grand"><span class="k">Totale a pagare (EUR)</span><span class="v">{{ $eur($totale) }}</span></td>
    <td style="width:20%" class="grand"><span class="k">Totale Fattura (EUR)</span><span class="v">{{ $eur($totale) }}</span></td>
  </tr>
</table>

<!-- Scadenze / Note -->
<table class="tot">
  <tr>
    <td style="width:70%"><span class="k">Scadenze</span><span class="v vfill">{{ $vendita->condizioni_pagamento }}</span></td>
    <td style="width:30%"><span class="k">Note</span><span class="v vfill">{{ $vendita->note }}</span></td>
  </tr>
</table>

<!-- Dati trasporto -->
<table class="tot">
  <tr>
    <td style="width:11%"><span class="k">N° Colli</span><span class="v">&nbsp;</span></td>
    <td style="width:13%"><span class="k">Porto</span><span class="v">&nbsp;</span></td>
    <td style="width:34%"><span class="k">Causale del trasporto</span><span class="v">{{ $vendita->causale_trasporto ?: 'VENDITA' }}</span></td>
    <td style="width:18%"><span class="k">Tot. Peso</span><span class="v">&nbsp;</span></td>
    <td style="width:24%"><span class="k">Data del trasporto</span><span class="v">{{ Carbon::parse($vendita->data_documento)->format('d/m/Y') }}</span></td>
  </tr>
</table>

<!-- Destinatario / controllo / firme -->
<table class="tot">
  <tr>
    <td style="width:60%"><span class="k">Destinatario della merce (se diverso dall'intestatario)</span><span class="v">&nbsp;</span></td>
    <td style="width:40%">
      <span class="k">Controllo merci e temperatura</span>
      <span class="v">OK <span class="chk"></span> &nbsp;&nbsp; KO <span class="chk"></span></span>
    </td>
  </tr>
  <tr>
    <td style="height:40px; vertical-align:bottom"><span class="k">Firma per uso interno</span></td>
    <td style="height:40px; vertical-align:bottom"><span class="k">Firma per accettazione merci</span></td>
  </tr>
</table>

<div class="footer">
  <div class="cols">
    <span class="l">Marche International Food S.r.l. — documento generato dal sistema di tracciabilità HACCP · Stampato il {{ now()->format('d/m/Y H:i') }}</span>
    <span class="r">Pag. 1</span>
  </div>
</div>

</body>
</html>
