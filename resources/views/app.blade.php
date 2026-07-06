<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marche International Food S.r.l.</title>
    <link rel="icon" type="image/png" sizes="64x64" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    {{-- Apply the saved theme before first paint to avoid a flash of the wrong theme --}}
    <script>
        (function () {
            try { if (localStorage.getItem('mif-theme') === 'dark') document.documentElement.classList.add('dark'); } catch (e) {}
        })();
    </script>
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
