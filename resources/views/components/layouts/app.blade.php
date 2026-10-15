@props(['title'])

@php
    $currentUser = auth()->user();
    $navigation = \App\Support\Navigation::for($currentUser);
    $unreadCount = $currentUser->unreadNotifications()->count();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="flex min-h-screen flex-col bg-univ-surface font-sans text-univ-ink antialiased dark:bg-slate-950 dark:text-slate-100" x-data="{ sidebar: false }">
    <a href="#contenu" class="absolute -left-[9999px] top-0 z-[60] rounded-br-xl bg-brand-500 px-5 py-3.5 font-semibold text-white focus:left-0">Aller au contenu</a>

    {{-- Voile mobile --}}
    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-univ-navy/60 lg:hidden" @click="sidebar = false"></div>

    {{-- Menu latéral bleu nuit, comme le pied de page et le méga-menu du site universitaire --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-univ-navy text-[#d8d8ef] transition-transform lg:translate-x-0"
        :class="sidebar && 'translate-x-0'"
        aria-label="Menu principal"
    >
        <div class="flex h-[100px] items-center justify-between gap-3 border-b border-white/10 px-5">
            <a href="{{ route('dashboard') }}" class="group flex min-w-0 flex-col items-start gap-1 text-white" title="Sogediproma — Tableau de bord">
                <img src="{{ asset('images/logo-sogediproma.png') }}" alt="Sogediproma" width="180" height="60"
                     class="h-[60px] w-auto max-w-full object-contain drop-shadow-[0_4px_12px_rgba(0,0,0,0.35)] transition-transform group-hover:-translate-y-0.5">
                <span class="pl-1 text-[10px] font-extrabold tracking-[0.11em] text-[#aaaaff] uppercase">Gestion des dossiers</span>
            </a>
            <button type="button" class="text-white lg:hidden" @click="sidebar = false" aria-label="Fermer le menu"><x-icon name="x" /></button>
        </div>

        <nav class="flex-1 space-y-7 overflow-y-auto px-4 py-6">
            @foreach ($navigation as $section)
                <div>
                    <div class="px-3 pb-2.5 text-[11px] leading-4 font-extrabold tracking-[0.11em] text-[#aaaaff] uppercase">{{ $section['label'] }}</div>
                    <ul class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            @php($active = request()->routeIs($item['active']))
                            <li>
                                <a href="{{ route($item['route']) }}" @class([
                                    'group relative flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors',
                                    'bg-white text-brand-500 shadow-[0_8px_20px_rgba(0,0,0,0.18)]' => $active,
                                    'text-[#d8d8ef] hover:bg-white/8 hover:text-white' => ! $active,
                                ]) @if ($active) aria-current="page" @endif>
                                    <span @class([
                                        'grid size-7 shrink-0 place-items-center rounded-md',
                                        'bg-brand-50 text-brand-500' => $active,
                                        'bg-white/8 text-[#aaaaff] group-hover:text-white' => ! $active,
                                    ])><x-icon :name="$item['icon']" class="size-4" /></span>
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-white/10 bg-black/10 px-5 py-3 text-[11px] text-[#b9b9dc]">
            {{ $currentUser->role->label }}{{ $currentUser->department ? ' · '.$currentUser->department->name : '' }}
        </div>
    </aside>

    <div class="flex flex-1 flex-col lg:pl-64">
        {{-- Barre supérieure bleu nuit (comme la barre d'outils du site universitaire) --}}
        <div class="hidden bg-univ-navy text-[12.5px] text-[#c9c9ea] sm:block">
            <div class="flex h-9 items-center justify-between gap-4 px-6">
                <span class="tracking-[0.01em]">SOGEDIPROMA — Suivi des correspondances et des dossiers</span>
                <span class="inline-flex items-center gap-2 capitalize"><x-icon name="calendar" class="size-3.5 text-[#aaaaff]" /> {{ now()->translatedFormat('l j F Y') }}</span>
            </div>
        </div>

        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-[#dde0ee] bg-white/95 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-950/90">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="lg:hidden" @click="sidebar = true" aria-label="Ouvrir le menu"><x-icon name="menu" class="size-6" /></button>
                <span class="relative truncate rounded-lg bg-brand-50 px-3 py-2 text-sm font-bold text-brand-500 after:absolute after:inset-x-3 after:bottom-1 after:h-0.5 after:rounded-full after:bg-univ-electric dark:bg-slate-800 dark:text-brand-100">{{ $title }}</span>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="relative inline-flex size-9 items-center justify-center rounded-full text-slate-600 hover:bg-brand-50 hover:text-brand-600 dark:text-slate-300 dark:hover:bg-slate-800"
                            aria-label="Notifications{{ $unreadCount ? ' ('.$unreadCount.' non lues)' : '' }}">
                        <x-icon name="bell" />
                        @if ($unreadCount)
                            <span class="absolute -top-1 -right-1 min-w-5 rounded-full bg-red-500 px-1 text-center text-xs leading-5 font-semibold text-white" data-testid="unread-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        @endif
                    </button>

                    <div x-show="open" x-cloak x-transition class="fixed inset-x-4 top-16 z-30 rounded-xl sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-80 border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-4 py-2 text-sm font-semibold dark:border-slate-700">Notifications</div>
                        <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                            @forelse ($currentUser->notifications()->limit(5)->get() as $notification)
                                <li>
                                    <a href="{{ route('notifications.open', $notification->id) }}" @class(['block px-4 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-800', 'font-medium' => $notification->unread()])>
                                        {{ $notification->data['message'] ?? 'Notification' }}
                                        <span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                                    </a>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-center text-sm text-slate-500">Aucune notification.</li>
                            @endforelse
                        </ul>
                        <a href="{{ route('notifications.index') }}" class="block border-t border-slate-200 px-4 py-2 text-center text-sm text-brand-600 hover:bg-slate-50 dark:border-slate-700 dark:text-brand-300 dark:hover:bg-slate-800">Voir toutes les notifications</a>
                    </div>
                </div>

                <x-theme-toggle />

                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="flex items-center gap-3 rounded-lg px-2 py-1 hover:bg-slate-100 dark:hover:bg-slate-800">
                        <x-avatar :user="$currentUser" class="ring-2 ring-brand-500/40" />
                        <span class="hidden text-left leading-tight sm:block">
                            <span class="block text-sm font-medium">{{ $currentUser->name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $currentUser->department ? $currentUser->department->name.' · ' : '' }}{{ $currentUser->role->label }}</span>
                        </span>
                    </button>

                    <div x-show="open" x-cloak x-transition class="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white py-2 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-4 pb-2 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">{{ $currentUser->username }}</div>
                        <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Mon profil</a>
                        <a href="{{ route('password.change') }}" class="block px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Changer mon mot de passe</a>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-200 pt-1 dark:border-slate-700">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-slate-100 dark:text-red-400 dark:hover:bg-slate-800">Déconnexion</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="contenu" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8">
            <x-flash />

            {{ $slot }}
        </main>

        {{-- Bandeau de pied de page bleu nuit --}}
        <footer class="bg-univ-navy text-[13px] text-[#d8d8ef]">
            <div class="flex flex-col gap-2 border-t border-white/10 bg-black/10 px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between">
                <span>&copy; {{ date('Y') }} Jean Louis Pro</span>            </div>
        </footer>
    </div>
</body>
</html>
