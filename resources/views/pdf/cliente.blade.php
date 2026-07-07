<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Scheda Cliente — {{ $cliente->ragione_sociale }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; padding: 24px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f5040; padding-bottom: 12px; margin-bottom: 16px; }
  .brand h1 { font-size: 15px; font-weight: 700; color: #1f5040; }
  .brand p { font-size: 9px; color: #64748b; margin-top: 2px; }
  .doc-info { text-align: right; }
  .doc-info .num { font-size: 14px; font-weight: 700; color: #1f5040; }
  .doc-info .sub { font-size: 9px; color: #64748b; }
  .titolo-cliente { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
  .cod-cliente { font-size: 10px; color: #64748b; margin-bottom: 16px; }
  .meta-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 16px; }
  .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; }
  .meta-box.full { grid-column: span 2; }
  .meta-box .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; margin-bottom: 2px; }
  .meta-box .value { font-size: 11px; font-weight: 600; color: #1e293b; }
  .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #2e6b57; background: #f0fdf4; border-left: 3px solid #2e6b57; padding: 4px 8px; margin: 14px 0 6px; }
  .stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 8px; }
  .stat-box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; text-align: center; }
  .stat-box .n { font-size: 16px; font-weight: 700; color: #1f5040; }
  .stat-box .l { font-size: 8px; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f8fafc; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; padding: 5px 6px; text-align: left; border-bottom: 1px solid #e2e8f0; }
  td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; font-size: 10px; vertical-align: top; }
  .num-cell { text-align: right; }
  .badge { display: inline-block; padding: 1px 6px; border-radius: 99px; font-size: 8px; font-weight: 700; }
  .badge.ok { background: #dcfce7; color: #166534; }
  .badge.no { background: #fee2e2; color: #991b1b; }
  .footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 8px; color: #94a3b8; display: flex; justify-content: space-between; }
  .sig-row { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 28px; }
  .sig-box { border-top: 1px solid #1e293b; padding-top: 4px; font-size: 9px; color: #64748b; }
</style>
</head>
<body>

<div class="header">
  <div class="brand">
    <h1>Marche International Food S.r.l.</h1>
    <p>Anagrafica Cliente — Sistema di tracciabilità HACCP</p>
  </div>
  <div class="doc-info">
    <div class="num">Scheda Cliente</div>
    <div class="sub">Stampata il {{ now()->format('d/m/Y H:i') }}</div>
  </div>
</div>

<div class="titolo-cliente">{{ $cliente->ragione_sociale }}</div>
<div class="cod-cliente">
  Codice cliente: <strong>{{ $cliente->codice_cliente }}</strong>
  &nbsp;·&nbsp;
  Stato:
  <span class="badge {{ $cliente->attivo ? 'ok' : 'no' }}">{{ $cliente->attivo ? 'ATTIVO' : 'NON ATTIVO' }}</span>
</div>

<div class="section-title">Dati anagrafici</div>
<div class="meta-grid">
  <div class="meta-box">
    <div class="label">Partita IVA</div>
    <div class="value">{{ $cliente->piva ?: '—' }}</div>
  </div>
  <div class="meta-box">
    <div class="label">Telefono</div>
    <div class="value">{{ $cliente->telefono ?: '—' }}</div>
  </div>
  <div class="meta-box">
    <div class="label">Email</div>
    <div class="value">{{ $cliente->email ?: '—' }}</div>
  </div>
  <div class="meta-box">
    <div class="label">Indirizzo</div>
    <div class="value">{{ $cliente->indirizzo ?: '—' }}</div>
  </div>
  @if($cliente->note)
  <div class="meta-box full">
    <div class="label">Note</div>
    <div class="value">{{ $cliente->note }}</div>
  </div>
  @endif
</div>

<div class="section-title">Riepilogo vendite</div>
<div class="stat-row">
  <div class="stat-box">
    <div class="n">{{ $riepilogo['n_documenti'] }}</div>
    <div class="l">Documenti</div>
  </div>
  <div class="stat-box">
    <div class="n">{{ number_format($riepilogo['totale_kg'], 2, ',', '.') }}</div>
    <div class="l">Kg totali</div>
  </div>
  <div class="stat-box">
    <div class="n">{{ $riepilogo['ultima'] ? \Carbon\Carbon::parse($riepilogo['ultima'])->format('d/m/Y') : '—' }}</div>
    <div class="l">Ultima vendita</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Data</th>
      <th>N° Documento</th>
      <th>Tipo</th>
      <th class="num-cell">Righe</th>
      <th class="num-cell">Q.tà (kg)</th>
    </tr>
  </thead>
  <tbody>
    @forelse($vendite as $v)
      <tr>
        <td>{{ \Carbon\Carbon::parse($v->data_documento)->format('d/m/Y') }}</td>
        <td>{{ $v->numero_documento }}</td>
        <td>{{ $v->tipo_documento }}</td>
        <td class="num-cell">{{ $v->righe_count }}</td>
        <td class="num-cell">{{ $v->righe_sum_quantita_kg !== null ? number_format($v->righe_sum_quantita_kg, 3, ',', '.') : '—' }}</td>
      </tr>
    @empty
      <tr><td colspan="5" style="color:#94a3b8;font-style:italic">Nessuna vendita registrata per questo cliente.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="sig-row">
  <div class="sig-box">Compilato da / Firma</div>
  <div class="sig-box">Data</div>
</div>

<div class="footer">
  <span>Marche International Food S.r.l. — documento generato dal sistema di tracciabilità HACCP</span>
  <span>Pag. 1</span>
</div>

</body>
</html>
