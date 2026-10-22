@session('status')
    <div data-toast="success" class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200" role="status">{{ $value }}</div>
@endsession

@session('warning')
    <div data-toast="warning" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200" role="alert">{{ $value }}</div>
@endsession

@session('error')
    <div data-toast="error" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">{{ $value }}</div>
@endsession

@session('temporary_password')
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100" role="alert">
        Mot de passe temporaire de <strong>{{ $value['username'] }}</strong> :
        <code class="rounded bg-white px-2 py-0.5 font-mono dark:bg-slate-900">{{ $value['password'] }}</code>
        — affiché une seule fois. L’utilisateur devra le changer à sa prochaine connexion.
    </div>
@endsession
