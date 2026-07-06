<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alert Scadenze HACCP</title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f4; margin: 0; padding: 0; color: #1e293b; }
  .wrap { max-width: 640px; margin: 32px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #1c3d28, #2a6941); padding: 28px 32px; color: #fff; }
  .header h1 { margin: 0 0 4px; font-size: 1.3rem; font-weight: 700; }
  .header p { margin: 0; font-size: 0.85rem; color: rgba(255,255,255,0.75); }
  .body { padding: 28px 32px; }
  .section-title { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; margin: 0 0 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
  .section { margin-bottom: 28px; }
  table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
  th { text-align: left; padding: 6px 8px; background: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
  td { padding: 8px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  .badge-danger { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; }
  .badge-warn { background: #fef9c3; color: #854d0e; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; }
  .empty { color: #94a3b8; font-size: 0.85rem; padding: 10px 0; }
  .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 32px; font-size: 0.75rem; color: #94a3b8; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>⚠ Alert Scadenze HACCP</h1>
    <p>Marche International Food S.r.l. — {{ now()->format('d/m/Y') }}</p>
  </div>
  <div class="body">

    @if(count($scaduti) > 0)
    <div class="section">
      <div class="section-title">🔴 Lotti già scaduti ({{ count($scaduti) }})</div>
      <table>
        <thead><tr><th>Prodotto</th><th>Fornitore</th><th>Lotto</th><th>Scadenza</th></tr></thead>
        <tbody>
        @foreach($scaduti as $r)
          <tr>
            <td>{{ $r['nome_prodotto'] }}</td>
            <td>{{ $r['acquisto']['fornitore']['ragione_sociale'] ?? '—' }}</td>
            <td>{{ $r['lotto'] ?: $r['lotto_esterno'] ?: '—' }}</td>
            <td><span class="badge-danger">{{ \Carbon\Carbon::parse($r['scadenza'])->format('d/m/Y') }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @endif

    @if(count($inScadenza) > 0)
    <div class="section">
      <div class="section-title">🟡 Lotti in scadenza nei prossimi 30 giorni ({{ count($inScadenza) }})</div>
      <table>
        <thead><tr><th>Prodotto</th><th>Fornitore</th><th>Lotto</th><th>Scadenza</th></tr></thead>
        <tbody>
        @foreach($inScadenza as $r)
          <tr>
            <td>{{ $r['nome_prodotto'] }}</td>
            <td>{{ $r['acquisto']['fornitore']['ragione_sociale'] ?? '—' }}</td>
            <td>{{ $r['lotto'] ?: $r['lotto_esterno'] ?: '—' }}</td>
            <td><span class="badge-warn">{{ \Carbon\Carbon::parse($r['scadenza'])->format('d/m/Y') }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @endif

    @if(count($certificatiInScadenza) > 0)
    <div class="section">
      <div class="section-title">📋 Certificati HACCP fornitori in scadenza (60 giorni) ({{ count($certificatiInScadenza) }})</div>
      <table>
        <thead><tr><th>Fornitore</th><th>Tipo</th><th>Scadenza HACCP</th></tr></thead>
        <tbody>
        @foreach($certificatiInScadenza as $f)
          <tr>
            <td>{{ $f['ragione_sociale'] }}</td>
            <td>{{ $f['tipo'] }}</td>
            <td><span class="badge-warn">{{ \Carbon\Carbon::parse($f['haccp_scadenza'])->format('d/m/Y') }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @endif

  </div>
  <div class="footer">
    Email automatica inviata dal sistema di tracciabilità HACCP · Marche International Food S.r.l.
  </div>
</div>
</body>
</html>
