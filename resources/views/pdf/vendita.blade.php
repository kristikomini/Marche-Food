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
    $trim = fn ($v, $d = 2) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, $d, ',', '.'), '0'), ',');

    $aliqLabel = $aliquotaPrincipale !== null ? $trim($aliquotaPrincipale) : '';

    // dompdf needs GD to rasterise a transparent PNG; skip the logo if GD is
    // unavailable so the PDF still renders instead of throwing a 500.
    $logoPath = public_path('favicon.png');
    $logo = (extension_loaded('gd') && is_file($logoPath))
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    $c = $vendita->cliente;

    // Recipient (SPETT.LE) box — split the single `indirizzo` into name / street /
    // city lines with the province in the bottom-right corner, matching the paper
    // Fattura DdT. Parses the common Italian format "VIA … - CAP CITTÀ (PR)" and
    // falls back to showing whatever is there when it doesn't match.
    $rName   = $c?->ragione_sociale ?? '';
    $rawAddr = trim((string) ($c?->indirizzo ?? ''));
    $rProv   = '';
    if (preg_match('/\(\s*([A-Za-z]{2})\s*\)\s*$/', $rawAddr, $mProv)) {
        $rProv   = strtoupper($mProv[1]);
        $rawAddr = trim(preg_replace('/\(\s*[A-Za-z]{2}\s*\)\s*$/', '', $rawAddr));
    }
    $addrParts = preg_split('/\s*[-–,]\s*/', $rawAddr, 2);
    $rStreet   = $addrParts[0] ?? '';
    $rCity     = $addrParts[1] ?? '';

    // Filler height so the item grid keeps its column rules running down the
    // page like the reference, regardless of how many lines there are.
    $fillPx = max(0, 300 - (count($righe) * 26));
@endphp
<style>
  @page { margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: "DejaVu Sans", sans-serif;
    color: #1a1a1a;
    background: #fff;
    padding: 7mm 6mm;
    font-size: 9pt;
  }
  .mono { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; }

  /* ---- top header ---- */
  table.top { width: 100%; border-collapse: collapse; }
  table.top > tbody > tr > td { vertical-align: top; padding: 0; }
  .logo img { width: 100px; height: auto; }
  .vendor { font-size: 8px; line-height: 1.4; }
  .vendor b { font-size: 8.5px; }
  .oval {
    width: 46px; height: 32px; border: 1.4px solid #000; border-radius: 50%;
    text-align: center; font-size: 6.5px; font-weight: bold; line-height: 1.35;
    padding-top: 4px;
  }
  .recipient {
    border: 0.6pt solid #000; border-radius: 7px; padding: 6px 10px; min-height: 74px;
  }
  .recipient .sp { font-size: 7px; }
  .recipient .r { font-size: 11px; font-weight: bold; margin-top: 3px; line-height: 1.5; }
  .recipient .prov { font-weight: bold; font-size: 11px; }

  /* ---- field rows ---- */
  table.fields { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin-top: 5px; }
  table.fields td { vertical-align: top; padding: 0; }
  .fld {
    border: 0.6pt solid #000; border-radius: 7px; padding: 2px 6px 4px; min-height: 30px;
  }
  .fld .lab { font-size: 6.6pt; font-weight: bold; white-space: nowrap; }
  .fld .val {
    font-family: "DejaVu Sans Mono", monospace; font-size: 9pt; font-weight: bold;
    padding-left: 2px; min-height: 12px; margin-top: 2px;
  }
  .fld .val.center { text-align: center; }
  .fld .val.big { font-family: "DejaVu Sans", sans-serif; font-weight: bold; font-size: 11px; }

  /* ---- items ---- */
  table.items { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; }
  table.items th {
    font-size: 7px; font-weight: bold; padding: 3px 4px; text-align: center;
    vertical-align: middle; border: 0.6pt solid #000; border-right: none;
  }
  table.items thead th:last-child { border-right: 0.6pt solid #000; }
  table.items td {
    border-left: 0.6pt solid #000; border-bottom: none; padding: 2px 4px; vertical-align: top;
    font-family: "DejaVu Sans Mono", monospace; font-size: 8.6px; font-weight: bold;
  }
  table.items tbody { border-left: 0.6pt solid #000; border-right: 0.6pt solid #000; border-bottom: 0.6pt solid #000; }
  table.items td:first-child { border-left: none; }
  table.items td.c-iva { border-right: none; }
  .c-cod  { width: 11%; text-align: center; }
  .c-desc { width: 34%; text-align: left; }
  .c-um   { width: 6%;  text-align: center; }
  .c-qta  { width: 9%;  text-align: right; }
  .c-prz  { width: 12%; text-align: right; }
  .c-s1   { width: 6%;  text-align: center; }
  .c-s2   { width: 6%;  text-align: center; }
  .c-imp  { width: 11%; text-align: right; }
  .c-iva  { width: 5%;  text-align: center; }
  td.c-desc .lotto { font-weight: normal; display: block; font-family: "DejaVu Sans", sans-serif; font-size: 7.5px; }

  /* ---- totals ---- */
  table.tot { width: 100%; border-collapse: separate; border-spacing: 3px 0; margin-top: 4px; }
  table.tot td { vertical-align: top; padding: 0; }
  .tbox { border: 0.6pt solid #000; border-radius: 7px; padding: 2px 6px; text-align: center; min-height: 30px; }
  .tbox .lab { font-size: 6.4px; font-weight: bold; }
  .tbox .val { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; font-size: 10px; padding-top: 5px; }
  .subrow { width: 100%; border-collapse: separate; border-spacing: 3px 0; }
  .subrow td { vertical-align: top; padding: 0; }
  .cur { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; font-size: 11px; }

  /* ---- bands ---- */
  .band { border: 0.6pt solid #000; border-radius: 7px; margin-top: 4px; padding: 3px 8px; min-height: 40px; }
  .band .lab { font-size: 6.6px; font-weight: bold; }
  .band .val { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; font-size: 9px; margin-top: 3px; }
  table.tr-row { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin-top: 4px; }
  table.tr-row td { vertical-align: top; padding: 0; }
  table.tr-row .band { margin-top: 0; }
  .ck { display: inline-block; width: 13px; height: 13px; border: 1px solid #000; vertical-align: middle; }

  table.sign { width: 100%; border-collapse: collapse; margin-top: 16px; }
  table.sign td { font-size: 7px; text-align: center; padding: 0 6px; vertical-align: bottom; }
  .sign-line { border-top: 0.6pt solid #000; padding-top: 2px; }
</style>
</head>
<body>

{{-- ===== HEADER ===== --}}
<table class="top">
  <tr>
    <td class="logo" style="width:104px">
      @if($logo)<img src="{{ $logo }}" alt="Marche International Food">@endif
    </td>
    <td class="vendor" style="padding: 32px 0 0 16px;">
      <b>Marche International Food S.r.l.</b><br>Via G. Rossini, 63 - 62029 Tolentino (MC)<br>
      C.F. e P.Iva 01891440438<br>MAIL info@marcheinternationalfood.com<br>PEC mifood@pec.it<br>
      TEL +39 0733 1715820 | CEL +39 340 9927059<br>marcheinternationalfood.com
    </td>
    <td style="width:52px; padding-top:2px">
      <div class="oval">IT<br>G5J07<br>CE</div>
    </td>
    <td style="width:300px; padding-left:10px">
      <div class="recipient">
        <div class="sp">SPETT.LE</div>
        <div class="r">{{ $rName }}</div>
        @if($rStreet)<div class="r">{{ $rStreet }}</div>@endif
        <table style="width:100%; border-collapse:collapse; margin-top:3px"><tr>
          <td style="padding:0; font-size:11px; font-weight:bold; line-height:1.5">{{ $rCity }}</td>
          <td class="prov" style="padding:0; text-align:right; vertical-align:bottom; white-space:nowrap">{{ $rProv }}</td>
        </tr></table>
      </div>
    </td>
  </tr>
</table>

{{-- ===== FIELD ROW 1 ===== --}}
<table class="fields">
  <tr>
    <td style="width:110px"><div class="fld"><div class="lab">COD.CLIENTE</div><div class="val center">{{ $c?->codice_cliente ?? '' }}</div></div></td>
    <td style="width:70px"><div class="fld"><div class="lab">IVA</div><div class="val">{{ $c?->codice_iva ?? '' }}</div></div></td>
    <td style="width:80px"><div class="fld"><div class="lab">ZONA</div><div class="val">{{ $c?->zona ?? '' }}</div></div></td>
    <td style="width:80px"><div class="fld"><div class="lab">AGENTE</div><div class="val">{{ $c?->agente ?? '' }}</div></div></td>
    <td style="width:70px"><div class="fld"><div class="lab">CATEG.</div><div class="val">{{ $c?->categoria ?? '' }}</div></div></td>
    <td><div class="fld"><div class="lab">PARTITA IVA</div><div class="val">{{ $c?->piva ?? '' }}</div></div></td>
    <td style="width:150px"><div class="fld"><div class="lab">NUMERO DOCUMENTO</div><div class="val center">{{ $vendita->numero_documento }}</div></div></td>
    <td style="width:120px"><div class="fld"><div class="lab">DATA DOCUMENTO</div><div class="val center">{{ Carbon::parse($vendita->data_documento)->format('d/m/Y') }}</div></div></td>
    <td style="width:56px"><div class="fld"><div class="lab">PAG.</div><div class="val center">001</div></div></td>
  </tr>
</table>

{{-- ===== FIELD ROW 2 ===== --}}
<table class="fields">
  <tr>
    <td><div class="fld"><div class="lab">CONDIZIONI DI PAGAMENTO</div><div class="val">{{ $vendita->condizioni_pagamento ?? '' }}</div></div></td>
    <td style="width:45%"><div class="fld"><div class="lab">BANCA D'APPOGGIO</div><div class="val">{{ $c?->banca_appoggio ?? '' }}</div></div></td>
  </tr>
</table>

{{-- ===== FIELD ROW 3 ===== --}}
<table class="fields">
  <tr>
    <td style="width:210px"><div class="fld"><div class="lab">TELEFONO</div><div class="val">{{ $c?->telefono ?? '' }}</div></div></td>
    <td style="width:210px"><div class="fld"><div class="lab">CODICE FISCALE</div><div class="val">{{ $c?->piva ?? '' }}</div></div></td>
    <td style="width:150px"><div class="fld"><div class="lab">VALUTA</div><div class="val">{{ $c?->valuta ?: 'Euro' }}</div></div></td>
    <td><div class="fld"><div class="lab">TIPO DOCUMENTO</div><div class="val big">{{ $tipoLabel[$vendita->tipo_documento] ?? $vendita->tipo_documento }}</div></div></td>
  </tr>
</table>

{{-- ===== ITEMS ===== --}}
<table class="items">
  <thead>
    <tr>
      <th class="c-cod">CODICE ARTICOLO</th>
      <th class="c-desc" style="text-align:center">D E S C R I Z I O N E</th>
      <th class="c-um">U.M.</th>
      <th class="c-qta">QUANTITA'</th>
      <th class="c-prz">PREZZO UNITARIO</th>
      <th class="c-s1">SC.1%</th>
      <th class="c-s2">SC.2%</th>
      <th class="c-imp">IMPORTO NETTO</th>
      <th class="c-iva">IVA</th>
    </tr>
  </thead>
  <tbody>
    @forelse($righe as $r)
      @php
        $qtaFatt = (!empty($r->quantita_pz) && (float) $r->quantita_pz > 0) ? $r->quantita_pz : $r->quantita_kg;
        $lotto = '';
        if ($r->lotto || $r->lotto_esterno) { $lotto .= 'Lotto N. ' . ($r->lotto ?: $r->lotto_esterno); }
        if ($r->scadenza) { $lotto .= ' Scad. ' . Carbon::parse($r->scadenza)->format('d/m/Y'); }
      @endphp
      <tr>
        <td class="c-cod">{{ $r->codice_articolo ?: '' }}</td>
        <td class="c-desc">{{ $r->nome_prodotto }}@if($lotto)<span class="lotto">{{ $lotto }}</span>@endif</td>
        <td class="c-um">{{ $r->um ?: '' }}</td>
        <td class="c-qta">{{ $qty($qtaFatt) }}</td>
        <td class="c-prz">{{ $r->prezzo_unitario !== null ? number_format((float) $r->prezzo_unitario, 3, ',', '.') : '' }}</td>
        <td class="c-s1">{{ $trim($r->sconto_1) }}</td>
        <td class="c-s2">{{ $trim($r->sconto_2) }}</td>
        <td class="c-imp">{{ $r->importo_netto !== null ? $eur($r->importo_netto) : '' }}</td>
        <td class="c-iva">{{ $trim($r->aliquota_iva) }}</td>
      </tr>
    @empty
    @endforelse
    @if($fillPx > 0)
      <tr>
        <td class="c-cod" style="height:{{ $fillPx }}px">&nbsp;</td>
        <td class="c-desc"></td><td class="c-um"></td><td class="c-qta"></td><td class="c-prz"></td>
        <td class="c-s1"></td><td class="c-s2"></td><td class="c-imp"></td><td class="c-iva"></td>
      </tr>
    @endif
  </tbody>
</table>

{{-- ===== TOTALS ===== --}}
<table class="tot">
  <tr>
    {{-- left block: imponibile / al.iva / importo iva --}}
    <td style="width:42%">
      <table class="subrow">
        <tr>
          <td><div class="tbox"><div class="lab">IMPONIBILE</div><div class="val">{{ $eur($imponibile) }}</div></div></td>
          <td style="width:60px"><div class="tbox"><div class="lab">AL.IVA</div><div class="val">{{ $aliqLabel }}</div></div></td>
          <td><div class="tbox"><div class="lab">IMPORTO IVA</div><div class="val">{{ $eur($imposta) }}</div></div></td>
        </tr>
      </table>
      <table class="subrow" style="margin-top:3px">
        <tr>
          <td class="mono" style="text-align:right; padding-right:8px; font-size:9pt">{{ $eur($imponibile) }}</td>
          <td class="mono" style="width:60px; text-align:center; font-size:9pt">TOT</td>
          <td class="mono" style="text-align:right; padding-right:8px; font-size:9pt">{{ $eur($imposta) }}</td>
        </tr>
      </table>
    </td>
    {{-- right block --}}
    <td>
      <table class="subrow">
        <tr>
          <td><div class="tbox"><div class="lab">TOTALE MERCE</div><div class="val">{{ $eur($imponibile) }}</div></div></td>
          <td style="width:22%"><div class="tbox"><div class="lab">% SCONTO</div><div class="val">&nbsp;</div></div></td>
          <td><div class="tbox"><div class="lab">IMPORTO SCONTO</div><div class="val">&nbsp;</div></div></td>
          <td><div class="tbox"><div class="lab">NETTO MERCE</div><div class="val">{{ $eur($imponibile) }}</div></div></td>
        </tr>
      </table>
      <table class="subrow" style="margin-top:3px">
        <tr>
          <td><div class="tbox"><div class="lab">BOLLI</div><div class="val">&nbsp;</div></div></td>
          <td><div class="tbox"><div class="lab">SPESE INCASSO</div><div class="val">&nbsp;</div></div></td>
          <td><div class="tbox"><div class="lab">VARIE</div><div class="val">&nbsp;</div></div></td>
          <td><div class="tbox"><div class="lab">ACCONTO</div><div class="val">&nbsp;</div></div></td>
        </tr>
      </table>
      <table class="subrow" style="margin-top:3px">
        <tr>
          <td>
            <table style="width:100%; border:0.6pt solid #000; border-radius:7px; border-collapse:collapse"><tr>
              <td style="text-align:center; font-size:6.4px; font-weight:bold; padding:2px 4px">TOTALE A PAGARE</td>
              <td style="width:34px; text-align:right"><span class="cur">EUR</span></td>
              <td style="width:78px; text-align:right; padding:2px 6px"><span class="mono" style="font-size:12px">{{ $eur($totale) }}</span></td>
            </tr></table>
          </td>
          <td>
            <table style="width:100%; border:0.6pt solid #000; border-radius:7px; border-collapse:collapse"><tr>
              <td style="text-align:center; font-size:6.4px; font-weight:bold; padding:2px 4px">TOTALE FATTURA</td>
              <td style="width:34px; text-align:right"><span class="cur">EUR</span></td>
              <td style="width:78px; text-align:right; padding:2px 6px"><span class="mono" style="font-size:12px">{{ $eur($totale) }}</span></td>
            </tr></table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

{{-- ===== SCADENZE ===== --}}
<div class="band"><span class="lab">SCADENZE</span><div class="val">{{ $vendita->condizioni_pagamento ?? '' }}</div></div>

{{-- ===== TRANSPORT ROW ===== --}}
<table class="tr-row">
  <tr>
    <td style="width:70px"><div class="band"><span class="lab">N.COLLI</span><div class="val">{{ $vendita->n_colli ?? '' }}</div></div></td>
    <td style="width:90px"><div class="band"><span class="lab">PORTO</span><div class="val">&nbsp;</div></div></td>
    <td><div class="band"><span class="lab">CAUSALE DEL TRASPORTO</span><div class="val">{{ $vendita->causale_trasporto ?: 'VENDITA' }}</div></div></td>
    <td style="width:90px"><div class="band"><span class="lab">TOT. PESO</span><div class="val">{{ $vendita->peso_totale !== null ? $qty($vendita->peso_totale) : '' }}</div></div></td>
    <td style="width:130px"><div class="band"><span class="lab">DATA DEL TRASPORTO</span><div class="val">{{ Carbon::parse($vendita->data_trasporto ?: $vendita->data_documento)->format('d/m/Y') }}</div></div></td>
  </tr>
</table>

{{-- ===== INCARICATO ===== --}}
<div class="band"><span class="lab">INCARICATO DEL TRASPORTO</span><div class="val">&nbsp;</div></div>

{{-- ===== DESTINATARIO + CONTROLLO ===== --}}
<table style="width:100%; border-collapse:collapse; margin-top:4px"><tr>
  <td style="vertical-align:top">
    <div class="band" style="min-height:60px; margin-top:0">
      <span class="lab">DESTINATARIO DELLA MERCE&nbsp;&nbsp;&nbsp;<i>(SE DIVERSO DALL'INTESTATARIO)</i></span>
      <div class="val" style="margin-top:3px">{{ $vendita->destinatario_diverso ?: '' }}</div>
    </div>
  </td>
  <td style="width:130px; vertical-align:top; padding-left:4px; text-align:center; font-size:7px; font-weight:bold">
    CONTROLLO<br>MERCI e<br>TEMPERATURA<br>
    <span style="display:inline-block; margin-top:3px">ok <span class="ck"></span>&nbsp;&nbsp;ko <span class="ck"></span></span>
  </td>
</tr></table>

{{-- ===== FIRME ===== --}}
<table class="sign">
  <tr>
    <td style="width:33%"><div class="sign-line">Firma (per uso interno)</div></td>
    <td style="width:34%"></td>
    <td style="width:33%"><div class="sign-line">Firma per accettazione merci</div></td>
  </tr>
</table>

{{-- ===== NOTE ===== --}}
<div class="band" style="margin-top:6px"><span class="lab">NOTE</span><div class="val">{{ $vendita->note ?? '' }}</div></div>

</body>
</html>
