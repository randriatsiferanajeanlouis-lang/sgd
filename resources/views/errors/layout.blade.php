<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Sogediproma</title>
    {{-- Page autonome : aucune dépendance à la base ni aux assets compilés. --}}
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, "Segoe UI", sans-serif; background: #f8fafc; color: #1e293b; }
        main { max-width: 32rem; margin: 1rem; padding: 2.5rem; border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; text-align: center; }
        .code { font-size: .875rem; font-weight: 600; color: #2563eb; letter-spacing: .05em; }
        h1 { font-size: 1.5rem; margin: .5rem 0 1rem; }
        p { color: #475569; line-height: 1.6; }
        a { display: inline-block; margin-top: 1.5rem; padding: .6rem 1.2rem; border-radius: .5rem; background: #2563eb; color: #fff; text-decoration: none; }
        @media (prefers-color-scheme: dark) {
            body { background: #020617; color: #e2e8f0; }
            main { background: #0f172a; border-color: #1e293b; }
            p { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <main>
        <div class="code">SOGEDIPROMA · @yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        @hasSection('noLink')
        @else
            <a href="{{ url('/') }}">Retour à l’accueil</a>
        @endif
    </main>
</body>
</html>
