<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Report gestionale</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; padding: 24px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f5040; padding-bottom: 12px; margin-bottom: 16px; }
  .brand h1 { font-size: 15px; font-weight: 700; color: #1f5040; }
  .brand p { font-size: 9px; color: #64748b; margin-top: 2px; }
  .doc-info { text-align: right; }
  .doc-info .num { font-size: 13px; font-weight: 700; color: #1f5040; }
  .doc-info .sub { font-size: 9px; color: #64748b; }
  .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px; }
  .kpi { background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 4px; padding: 10px; }
  .kpi .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.08em; color: #2e6b57; font-weight: 700; }
  .kpi .value { font-size: 16px; font-weight: 700; color: #1f5040; margin-top: 4px; }
  .kpi .sub { font-size: 8px; color: #64748b; }
  .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #2e6b57; background: #f0fdf4; border-left: 3px solid #2e6b57; padding: 4px 8px; margin: 14px 0 6px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f8fafc; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; padding: 5px 6px; text-align: left; border-bottom: 1px solid #e2e8f0; }
  td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
  .num-cell { text-align: right; }
  .scaduto { color: #b91c1c; font-weight: 700; }
  .in_scadenza { color: #b45309; }
  .footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 8px; color: #94a3b8; }
</style>
</head>
<body>
<div class="header">
  <div class="brand">
    <h1>Marche International Food S.r.l.</h1>
    <p>Report gestionale — Tracciabilità HACCP</p>
  </div>
  <div class="doc-info">
    <div class="num">{{ \Carbon\Carbon::parse($summary['da'])->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($summary['a'])->format('d/m/Y') }}</div>
    <div class="sub">Stampato il {{ now()->format('d/m/Y H:i') }}</div>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Acquisti</div>
    <div class="value">{{ number_format($summary['totali']['acquisti_kg'], 0, ',', '.') }} kg</div>
    <div class="sub">{{ $summary['totali']['acquisti_docs'] }} documenti (escl. conto terzi)</div>
  </div>
  <div class="kpi">
    <div class="label">Vendite</div>
    <div class="value">{{ number_format($summary['totali']['vendite_kg'], 0, ',', '.') }} kg</div>
    <div class="sub">{{ $summary['totali']['vendite_docs'] }} documenti</div>
  </div>
  <div class="kpi">
    <div class="label">Produzioni</div>
    <div class="value">{{ number_format($summary['totali']['produzioni_kg'], 0, ',', '.') }} kg</div>
    <div class="sub">{{ $summary['totali']['produzioni'] }} lotti</div>
  </div>
</div>

<div class="section-title">Acquisti per fornitore</div>
<table>
  <thead><tr><th>Fornitore</th><th class="num-cell">Documenti</th><th class="num-cell">Kg</th></tr></thead>
  <tbody>
    @forelse($summary['per_fornitore'] as $r)
      <tr><td>{{ $r->nome }}</td><td class="num-cell">{{ $r->documenti }}</td><td class="num-cell">{{ number_format($r->kg, 3, ',', '.') }}</td></tr>
    @empty
      <tr><td colspan="3" style="color:#94a3b8;font-style:italic">Nessun dato nel periodo.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="section-title">Vendite per cliente</div>
<table>
  <thead><tr><th>Cliente</th><th class="num-cell">Documenti</th><th class="num-cell">Kg</th></tr></thead>
  <tbody>
    @forelse($summary['per_cliente'] as $r)
      <tr><td>{{ $r->nome }}</td><td class="num-cell">{{ $r->documenti }}</td><td class="num-cell">{{ number_format($r->kg, 3, ',', '.') }}</td></tr>
    @empty
      <tr><td colspan="3" style="color:#94a3b8;font-style:italic">Nessun dato nel periodo.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="section-title">Lotti in scadenza / scaduti (giacenza)</div>
<table>
  <thead><tr><th>Prodotto</th><th>Fornitore</th><th>Lotto</th><th class="num-cell">Kg</th><th>Scadenza</th><th>Stato</th></tr></thead>
  <tbody>
    @forelse($scadenze as $r)
      <tr>
        <td>{{ $r['nome_prodotto'] }}</td>
        <td>{{ $r['fornitore'] }}</td>
        <td>{{ $r['lotto'] ?: $r['lotto_esterno'] }}</td>
        <td class="num-cell">{{ number_format($r['quantita_kg'], 3, ',', '.') }}</td>
        <td>{{ \Carbon\Carbon::parse($r['scadenza'])->format('d/m/Y') }}</td>
        <td class="{{ $r['stato'] }}">{{ $r['stato'] === 'scaduto' ? 'SCADUTO' : 'In scadenza' }}</td>
      </tr>
    @empty
      <tr><td colspan="6" style="color:#94a3b8;font-style:italic">Nessun lotto in scadenza.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">Marche International Food S.r.l. — documento generato dal sistema di tracciabilità HACCP</div>
</body>
</html>
