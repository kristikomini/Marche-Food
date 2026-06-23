<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Scheda Lavorazione — {{ $produzione->lotto_produzione }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; padding: 24px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1c3d28; padding-bottom: 12px; margin-bottom: 16px; }
  .brand h1 { font-size: 15px; font-weight: 700; color: #1c3d28; }
  .brand p { font-size: 9px; color: #64748b; margin-top: 2px; }
  .doc-info { text-align: right; }
  .doc-info .lotto { font-size: 14px; font-weight: 700; color: #1c3d28; }
  .doc-info .sub { font-size: 9px; color: #64748b; }
  .meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
  .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; }
  .meta-box .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; margin-bottom: 2px; }
  .meta-box .value { font-size: 11px; font-weight: 600; color: #1e293b; }
  .section { margin-bottom: 14px; }
  .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #2a6941; background: #f0fdf4; border-left: 3px solid #2a6941; padding: 4px 8px; margin-bottom: 6px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f8fafc; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; padding: 5px 6px; text-align: left; border-bottom: 1px solid #e2e8f0; }
  td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; font-size: 10px; vertical-align: top; }
  .empty-row td { color: #94a3b8; font-style: italic; }
  .footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 8px; color: #94a3b8; display: flex; justify-content: space-between; }
  .signature-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 24px; }
  .signature-box { border-top: 1px solid #1e293b; padding-top: 4px; font-size: 9px; color: #64748b; }
</style>
</head>
<body>

<div class="header">
  <div class="brand">
    <h1>Marche International Food S.r.l.</h1>
    <p>Registro di Lavorazione — HACCP</p>
  </div>
  <div class="doc-info">
    <div class="lotto">Lotto: {{ $produzione->lotto_produzione }}</div>
    <div class="sub">Stampato il {{ now()->format('d/m/Y H:i') }}</div>
  </div>
</div>

<div class="meta-grid">
  <div class="meta-box">
    <div class="label">Data Produzione</div>
    <div class="value">{{ \Carbon\Carbon::parse($produzione->data_produzione)->format('d/m/Y') }}</div>
  </div>
  <div class="meta-box">
    <div class="label">Prodotto</div>
    <div class="value">{{ $produzione->scheda?->prodotto?->nome ?? '—' }}</div>
  </div>
  <div class="meta-box">
    <div class="label">Scheda (Rev.)</div>
    <div class="value">{{ $produzione->scheda?->modello }}.{{ str_pad($produzione->scheda?->revisione, 2, '0', STR_PAD_LEFT) }}</div>
  </div>
  <div class="meta-box">
    <div class="label">Q.tà Prodotta</div>
    <div class="value">{{ $produzione->quantita_prodotta_kg ? number_format($produzione->quantita_prodotta_kg, 3, ',', '.') . ' kg' : '—' }}</div>
  </div>
  @if($produzione->operatore)
  <div class="meta-box">
    <div class="label">Operatore</div>
    <div class="value">{{ $produzione->operatore }}</div>
  </div>
  @endif
  @if($produzione->note)
  <div class="meta-box" style="grid-column:span 3">
    <div class="label">Note</div>
    <div class="value">{{ $produzione->note }}</div>
  </div>
  @endif
</div>

{{-- Materie Prime --}}
<div class="section">
  <div class="section-title">Materie Prime Utilizzate</div>
  <table>
    <thead>
      <tr>
        <th>Materia Prima</th>
        <th>Fornitore</th>
        <th>N° Documento</th>
        <th>Lotto</th>
        <th>Scadenza</th>
        <th style="text-align:right">Q.tà (kg)</th>
      </tr>
    </thead>
    <tbody>
      @forelse($produzione->materiePrime as $mp)
      <tr>
        <td>{{ $mp->materiaPrima?->nome ?? '—' }}</td>
        <td>{{ $mp->acquistoRiga?->acquisto?->fornitore?->ragione_sociale ?? '—' }}</td>
        <td>{{ $mp->acquistoRiga?->acquisto?->numero_documento ?? '—' }}</td>
        <td>{{ $mp->acquistoRiga?->lotto ?: ($mp->acquistoRiga?->lotto_esterno ?: '—') }}</td>
        <td>{{ $mp->acquistoRiga?->scadenza ? \Carbon\Carbon::parse($mp->acquistoRiga->scadenza)->format('d/m/Y') : '—' }}</td>
        <td style="text-align:right">{{ number_format($mp->quantita_kg, 3, ',', '.') }}</td>
      </tr>
      @empty
      <tr class="empty-row"><td colspan="6">Nessuna materia prima registrata.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- Imballaggi --}}
@if($produzione->imballaggiPrimari->count() > 0)
<div class="section">
  <div class="section-title">Imballaggi Primari Utilizzati</div>
  <table>
    <thead>
      <tr><th>Componente</th><th>Fornitore</th><th>Lotto</th><th>Q.tà Usata</th><th>Note</th></tr>
    </thead>
    <tbody>
      @foreach($produzione->imballaggiPrimari as $imb)
      <tr>
        <td>{{ $imb->lottoImballaggio?->componente ?? '—' }}</td>
        <td>{{ $imb->lottoImballaggio?->fornitore?->ragione_sociale ?? '—' }}</td>
        <td>{{ $imb->lottoImballaggio?->lotto ?? '—' }}</td>
        <td>{{ $imb->quantita_usata ? number_format($imb->quantita_usata, 3, ',', '.') : '—' }}</td>
        <td>{{ $imb->note ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- Detergenti --}}
@if($produzione->detergenti->count() > 0)
<div class="section">
  <div class="section-title">Detergenti e Sanificanti</div>
  <table>
    <thead>
      <tr><th>Componente</th><th>Fornitore</th><th>Lotto</th><th>Q.tà Usata</th><th>Note</th></tr>
    </thead>
    <tbody>
      @foreach($produzione->detergenti as $det)
      <tr>
        <td>{{ $det->lottoDetergente?->componente ?? '—' }}</td>
        <td>{{ $det->lottoDetergente?->fornitore?->ragione_sociale ?? '—' }}</td>
        <td>{{ $det->lottoDetergente?->lotto ?? '—' }}</td>
        <td>{{ $det->quantita_usata ? number_format($det->quantita_usata, 3, ',', '.') : '—' }}</td>
        <td>{{ $det->note ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

<div class="signature-row">
  <div class="signature-box">Operatore / Firma</div>
  <div class="signature-box">Responsabile Qualità / Firma</div>
  <div class="signature-box">Data</div>
</div>

<div class="footer">
  <span>Marche International Food S.r.l. — Documento HACCP riservato all'uso interno</span>
  <span>Lotto: {{ $produzione->lotto_produzione }}</span>
</div>

</body>
</html>
