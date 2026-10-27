@props(['title', 'flash' => true])

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
    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>

    {{-- Menu latéral : sections repliables, entrée active en cyan --}}
    <aside
        class="dark sidebar-midnight fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground transition-transform lg:translate-x-0"
        :class="sidebar && 'translate-x-0'"
        aria-label="Menu principal"
    >
        <div class="flex h-16 items-center justify-between gap-3 border-b border-sidebar-border px-4">
            <a href="{{ route('dashboard') }}" class="group flex min-w-0 items-center gap-3" title="Sogediproma — Tableau de bord">
                <img src="{{ asset('images/logo-sogediproma.png') }}" alt="Sogediproma" width="120" height="40"
                     class="h-9 w-auto max-w-[130px] object-contain invert transition-transform group-hover:-translate-y-0.5 dark:invert-0">
                <span class="border-l border-sidebar-border pl-3 text-[10px] leading-tight font-bold tracking-[0.08em] text-univ-muted uppercase dark:text-slate-400">Gestion<br>des dossiers</span>
            </a>
            <button type="button" class="rounded-md p-1 text-univ-muted hover:bg-sidebar-accent lg:hidden dark:text-slate-400" @click="sidebar = false" aria-label="Fermer le menu"><x-icon name="x" /></button>
        </div>

        <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4 [scrollbar-width:thin]">
            @foreach ($navigation as $section)
                @php($sectionKey = 'nav-'.Str::slug($section['label']))
                <div x-data="navSection('{{ $sectionKey }}')">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                            class="mb-1 flex min-h-7 w-full items-center justify-between rounded-md px-3 py-1 text-[10.5px] font-bold tracking-[0.1em] text-univ-muted uppercase hover:text-univ-ink dark:text-slate-400 dark:hover:text-slate-200">
                        {{ $section['label'] }}
                        <svg class="size-3.5 transition-transform" :class="open ? '' : '-rotate-90'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <ul class="space-y-0.5" x-show="open" x-transition.opacity.duration.150ms>
                        @foreach ($section['items'] as $item)
                            @php($active = request()->routeIs(...explode('|', $item['active'])))
                            <li>
                                <a href="{{ route($item['route']) }}" @class([
                                    'group flex min-h-9 items-center gap-3 rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors',
                                    'bg-sidebar-primary text-sidebar-primary-foreground shadow-[0_6px_18px_-6px_rgb(6_182_212/0.55)]' => $active,
                                    'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' => ! $active,
                                ]) @if ($active) aria-current="page" @endif>
                                    <x-icon :name="$item['icon']" @class(['size-[18px]', 'opacity-70 group-hover:opacity-100' => ! $active]) />
                                    {{ $item['label'] }}
                                </a>

                                {{-- Sous-menu (ex. « Voir les dossiers » sous « Demande ») --}}
                                @if ($item['children'] !== [])
                                    <ul class="mt-0.5 ml-[22px] space-y-0.5 border-l border-sidebar-border pl-2.5">
                                        @foreach ($item['children'] as $child)
                                            @php($childActive = request()->routeIs(...explode('|', $child['active'])))
                                            <li>
                                                <a href="{{ route($child['route']) }}" @class([
                                                    'group flex min-h-8 items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium transition-colors',
                                                    'bg-sidebar-accent font-semibold text-sidebar-primary' => $childActive,
                                                    'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' => ! $childActive,
                                                ]) @if ($childActive) aria-current="page" @endif>
                                                    <x-icon :name="$child['icon']" @class(['size-4', 'opacity-70 group-hover:opacity-100' => ! $childActive]) />
                                                    {{ $child['label'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        {{-- Carte utilisateur --}}
        <div class="border-t border-sidebar-border p-3">
            <a href="{{ route('profile.show') }}" class="flex items-center gap-3 rounded-xl border border-sidebar-border bg-univ-surface/60 p-2.5 hover:bg-sidebar-accent dark:bg-white/[0.02]">
                <span class="relative">
                    <x-avatar :user="$currentUser" size="size-9" class="text-xs" />
                    <span class="absolute -right-0.5 -bottom-0.5 size-3 rounded-full border-2 border-sidebar bg-emerald-500" aria-hidden="true"></span>
                </span>
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-sm font-semibold text-univ-ink dark:text-white">{{ $currentUser->name }}</span>
                    <span class="block truncate text-[11px] text-univ-muted dark:text-slate-400">{{ $currentUser->role->label }}{{ $currentUser->department ? ' · '.$currentUser->department->name : '' }}</span>
                    <span class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">● En ligne</span>
                </span>
            </a>
        </div>
    </aside>

    <div class="flex flex-1 flex-col lg:pl-64">
        {{-- Barre supérieure --}}
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-univ-line bg-white/85 px-4 backdrop-blur-md sm:px-6 dark:border-slate-800 dark:bg-slate-950/80">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <button type="button" class="rounded-md p-1.5 hover:bg-univ-surface lg:hidden dark:hover:bg-slate-800" @click="sidebar = true" aria-label="Ouvrir le menu"><x-icon name="menu" class="size-6" /></button>

                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-command-palette'))"
                        class="flex h-10 w-full max-w-md items-center gap-2.5 rounded-xl border border-univ-line bg-univ-surface/70 px-3 text-sm text-univ-muted transition hover:border-brand-300 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-400 dark:hover:border-brand-400/40"
                        aria-label="Rechercher une page (Ctrl K)">
                    <x-icon name="search" class="size-4" />
                    <span class="truncate">Rechercher une page…</span>
                    <kbd class="ml-auto hidden rounded-md border border-univ-line bg-white px-1.5 py-0.5 font-mono text-[10px] text-univ-muted sm:inline dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">Ctrl K</kbd>
                </button>
            </div>

            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                <x-pwa-install />

                <x-theme-toggle />

                <div class="relative" x-data="notificationBell({{ $unreadCount }})" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="relative inline-flex size-9 items-center justify-center rounded-full text-slate-600 hover:bg-brand-50 hover:text-brand-600 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-brand-300"
                            aria-label="Notifications{{ $unreadCount ? ' ('.$unreadCount.' non lues)' : '' }}">
                        <x-icon name="bell" />
                        {{-- Le compteur suit les nouvelles notifications reçues en temps réel (WebSocket). --}}
                        @if ($unreadCount)
                            <span x-text="badge" class="absolute -top-0.5 -right-0.5 min-w-5 rounded-full bg-rose-500 px-1 text-center text-[11px] leading-5 font-semibold text-white ring-2 ring-white dark:ring-slate-950" data-testid="unread-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        @else
                            <span x-show="count > 0" x-cloak x-text="badge" class="absolute -top-0.5 -right-0.5 min-w-5 rounded-full bg-rose-500 px-1 text-center text-[11px] leading-5 font-semibold text-white ring-2 ring-white dark:ring-slate-950"></span>
                        @endif
                    </button>

                    <div x-show="open" x-cloak x-transition class="fixed inset-x-4 top-16 z-30 overflow-hidden rounded-xl border border-univ-line bg-white shadow-xl sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-80 dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-univ-line px-4 py-2.5 text-sm font-semibold dark:border-slate-800">Notifications</div>
                        <ul class="max-h-80 divide-y divide-univ-line overflow-y-auto dark:divide-slate-800">
                            <template x-for="item in live" :key="item.id">
                                <li>
                                    <a :href="item.url" class="flex gap-3 px-4 py-3 text-sm font-medium hover:bg-univ-surface dark:hover:bg-slate-800">
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-400" aria-hidden="true"></span>
                                        <span>
                                            <span x-text="item.message"></span>
                                            <span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">à l’instant</span>
                                        </span>
                                    </a>
                                </li>
                            </template>
                            @forelse ($currentUser->notifications()->limit(5)->get() as $notification)
                                <li>
                                    <a href="{{ route('notifications.open', $notification->id) }}" @class(['flex gap-3 px-4 py-3 text-sm hover:bg-univ-surface dark:hover:bg-slate-800', 'font-medium' => $notification->unread()])>
                                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand-400' => $notification->unread(), 'bg-transparent' => ! $notification->unread()]) aria-hidden="true"></span>
                                        <span>
                                            {{ \App\Notifications\DossierNotification::displayMessage($notification->data) }}
                                            <span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                                        </span>
                                    </a>
                                </li>
                            @empty
                                <li x-show="live.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">Aucune notification.</li>
                            @endforelse
                        </ul>
                        <a href="{{ route('notifications.index') }}" class="block border-t border-univ-line px-4 py-2.5 text-center text-sm font-medium text-brand-600 hover:bg-univ-surface dark:border-slate-800 dark:text-brand-300 dark:hover:bg-slate-800">Voir toutes les notifications</a>
                    </div>
                </div>

                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="flex items-center gap-2.5 rounded-xl py-1 pr-2 pl-1 hover:bg-univ-surface dark:hover:bg-slate-800">
                        <x-avatar :user="$currentUser" size="size-8" class="text-xs ring-2 ring-brand-400/40" />
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block text-sm font-semibold">{{ $currentUser->name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $currentUser->department ? $currentUser->department->name.' · ' : '' }}{{ $currentUser->role->label }}</span>
                        </span>
                        <svg class="hidden size-4 text-slate-400 md:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <div x-show="open" x-cloak x-transition class="absolute right-0 z-30 mt-2 w-60 overflow-hidden rounded-xl border border-univ-line bg-white py-1.5 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-univ-line px-4 pt-1 pb-2.5 dark:border-slate-800">
                            <div class="text-sm font-semibold">{{ $currentUser->name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $currentUser->username }}</div>
                        </div>
                        <a href="{{ route('profile.show') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm hover:bg-univ-surface dark:hover:bg-slate-800"><x-icon name="user" class="size-4 opacity-70" /> Mon profil</a>
                        <a href="{{ route('password.change') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm hover:bg-univ-surface dark:hover:bg-slate-800"><x-icon name="key" class="size-4 opacity-70" /> Changer mon mot de passe</a>
                        <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-univ-line pt-1 dark:border-slate-800">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-rose-600 hover:bg-univ-surface dark:text-rose-400 dark:hover:bg-slate-800"><x-icon name="x" class="size-4" /> Déconnexion</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="contenu" class="mx-auto w-full max-w-[1400px] flex-1 px-4 py-6 sm:px-6 sm:py-8">
            {{-- Les pages React (Inertia) affichent elles-mêmes les messages. --}}
            @if ($flash)
                <x-flash />
            @endif

            {{ $slot }}
        </main>

        <footer class="border-t border-univ-line px-4 py-4 text-[13px] text-univ-muted sm:px-6 dark:border-slate-800 dark:text-slate-400">
            <span>&copy; {{ date('Y') }} Jean Louis Pro</span>
        </footer>
    </div>

    {{-- Toasts et recherche rapide (React, resources/js/islands.jsx) --}}
    <div id="react-islands"></div>
    <script type="application/json" id="islands-data">@json(['commands' => \App\Support\Navigation::commands($currentUser), 'userId' => $currentUser->id])</script>
</body>
</html>
