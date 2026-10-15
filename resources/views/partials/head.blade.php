<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} — {{ config('app.name') }}</title>

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
@vite(['resources/css/app.css', 'resources/js/app.js'])
