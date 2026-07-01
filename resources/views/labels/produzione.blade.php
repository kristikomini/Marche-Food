<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Etichette — {{ $lotto }}</title>
<script src="/vendor/qrcode-generator.js"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f4; color: #1e293b; padding: 16px; }
  .toolbar { max-width: 900px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; }
  .toolbar h1 { font-size: 1rem; color: #1c3d28; }
  .btn { background: #2a6941; color: #fff; border: none; border-radius: 6px; padding: 0.5rem 1rem; font-size: 0.85rem; cursor: pointer; }
  .sheet { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .label { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; display: flex; gap: 12px; align-items: center; page-break-inside: avoid; }
  .label .qr { width: 96px; height: 96px; flex-shrink: 0; }
  .label .qr img, .label .qr svg { width: 96px; height: 96px; }
  .label .info { flex: 1; min-width: 0; }
  .label .brand { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.08em; color: #2a6941; font-weight: 700; }
  .label .prod { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin: 2px 0; }
  .label .lot { font-family: monospace; font-size: 1rem; font-weight: 700; color: #1c3d28; }
  .label .meta { font-size: 0.72rem; color: #64748b; margin-top: 4px; }
  @media print {
    body { background: #fff; padding: 0; }
    .toolbar { display: none; }
    .sheet { gap: 6px; }
    .label { border: 1px solid #94a3b8; border-radius: 6px; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <h1>Etichette lotto {{ $lotto }} — {{ $copie }} copia/e</h1>
    <button class="btn" onclick="window.print()">Stampa</button>
  </div>

  <div class="sheet">
    @for($i = 0; $i < $copie; $i++)
      <div class="label">
        <div class="qr" data-qr="{{ $traceUrl }}"></div>
        <div class="info">
          <div class="brand">Marche International Food</div>
          <div class="prod">{{ $prodotto ?? 'Prodotto' }}</div>
          <div class="lot">{{ $lotto }}</div>
          <div class="meta">
            Prod.: {{ $dataProduzione }}@if($quantita) · {{ $quantita }} kg @endif<br>
            Scansiona per la tracciabilità
          </div>
        </div>
      </div>
    @endfor
  </div>

  <script>
    (function () {
      if (typeof qrcode !== 'function') { return; }
      document.querySelectorAll('.qr[data-qr]').forEach(function (el) {
        var qr = qrcode(0, 'M');
        qr.addData(el.getAttribute('data-qr'));
        qr.make();
        el.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 2, scalable: true });
      });
    })();
  </script>
</body>
</html>
