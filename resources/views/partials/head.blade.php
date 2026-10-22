<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="SOGEDIPROMA — application de gestion des correspondances et du suivi des dossiers entre départements.">
<meta name="color-scheme" content="light dark">
<title>{{ $title }} — {{ config('app.name') }}</title>

{{-- Application installable (PWA) --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#1a1a8c">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="SOGEDIPROMA">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

{{-- Applique le thème avant le premier affichage (évite le flash clair). --}}
<script>
    (function () {
        var theme = @json(auth()->user()?->theme);
        try { theme = theme || localStorage.getItem('theme'); } catch (e) {}
        if (!theme) { theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
        document.documentElement.classList.toggle('dark', theme === 'dark');
    })();
</script>

@fonts
@viteReactRefresh
@vite(['resources/css/app.css', 'resources/js/app.js'])
@auth
    {{-- Toasts et recherche rapide (React) sur toutes les pages connectées --}}
    @vite('resources/js/islands.jsx')
@endauth
@stack('head')
