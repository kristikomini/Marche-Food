<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Vendita — {{ $vendita->numero_documento }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; padding: 24px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1c3d28; padding-bottom: 12px; margin-bottom: 16px; }
  .brand h1 { font-size: 15px; font-weight: 700; color: #1c3d28; }
  .brand p { font-size: 9px; color: #64748b; margin-top: 2px; }
  .doc-info { text-align: right; }
  .doc-info .num { font-size: 14px; font-weight: 700; color: #1c3d28; }
  .doc-info .sub { font-size: 9px; color: #64748b; }
  .meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px; }
  .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; }
  .meta-box .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; margin-bottom: 2px; }
  .meta-box .value { font-size: 11px; font-weight: 600; color: #1e293b; }
  .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #2a6941; background: #f0fdf4; border-left: 3px solid #2a6941; padding: 4px 8px; margin-bottom: 6px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f8fafc; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; padding: 5px 6px; text-align: left; border-bottom: 1px solid #e2e8f0; }
  td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; font-size: 10px; vertical-align: top; }
  .num-cell { text-align: right; }
  .footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 8px; color: #94a3b8; display: flex; justify-content: space-between; }
</style>
</head>
<body>
<div class="header">
  <div class="brand">
    <h1>Marche International Food S.r.l.</h1>
    <p>Registro Vendite — Tracciabilità HACCP</p>
  </div>
  <div class="doc-info">
    <div class="num">{{ $vendita->tipo_documento }} N° {{ $vendita->numero_documento }}</div>
    <div class="sub">Stampato il {{ now()->format('d/m/Y H:i') }}</div>
  </div>
</div>

<div class="meta-grid">
  <div class="meta-box">
    <div class="label">Cliente</div>
    <div class="value">{{ $vendita->cliente?->ragione_sociale ?? '—' }}</div>
  </div>
  <div class="meta-box">
    <div class="label">Data Documento</div>
    <div class="value">{{ \Carbon\Carbon::parse($vendita->data_documento)->format('d/m/Y') }}</div>
  </div>
  <div class="meta-box">
    <div class="label">Tipo</div>
    <div class="value">{{ $vendita->tipo_documento }}</div>
  </div>
</div>

<div class="section-title">Righe</div>
<table>
  <thead>
    <tr>
      <th>Prodotto</th>
      <th>Lotto</th>
      <th>Lotto est.</th>
      <th class="num-cell">Pezzatura (g)</th>
      <th class="num-cell">Q.tà (kg)</th>
      <th class="num-cell">Q.tà (pz)</th>
      <th>Scadenza</th>
    </tr>
  </thead>
  <tbody>
    @forelse($vendita->righe as $r)
      <tr>
        <td>{{ $r->nome_prodotto }}</td>
        <td>{{ $r->lotto ?: '—' }}</td>
        <td>{{ $r->lotto_esterno ?: '—' }}</td>
        <td class="num-cell">{{ $r->pezzatura_gr !== null ? number_format($r->pezzatura_gr, 0, ',', '.') : '—' }}</td>
        <td class="num-cell">{{ $r->quantita_kg !== null ? number_format($r->quantita_kg, 3, ',', '.') : '—' }}</td>
        <td class="num-cell">{{ $r->quantita_pz !== null ? number_format($r->quantita_pz, 0, ',', '.') : '—' }}</td>
        <td>{{ $r->scadenza ? \Carbon\Carbon::parse($r->scadenza)->format('d/m/Y') : '—' }}</td>
      </tr>
    @empty
      <tr><td colspan="7" style="color:#94a3b8;font-style:italic">Nessuna riga.</td></tr>
    @endforelse
  </tbody>
</table>

@if($vendita->note)
<div style="margin-top:14px">
  <div class="section-title">Note</div>
  <p style="font-size:10px">{{ $vendita->note }}</p>
</div>
@endif

<div class="footer">
  <span>Marche International Food S.r.l. — documento generato dal sistema di tracciabilità HACCP</span>
  <span>Pag. 1</span>
</div>
</body>
</html>
