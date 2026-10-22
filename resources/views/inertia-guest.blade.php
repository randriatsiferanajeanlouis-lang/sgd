{{-- Vue racine Inertia des visiteurs non connectés : page React plein écran (connexion). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $page['props']['title'] ?? config('app.name')])
    @vite('resources/js/inertia.jsx')
    @inertiaHead
</head>
<body class="min-h-screen bg-univ-navy font-sans text-univ-ink antialiased dark:text-slate-100">
    @inertia
</body>
</html>
