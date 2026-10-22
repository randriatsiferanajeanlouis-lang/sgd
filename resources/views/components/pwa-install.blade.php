@props(['variant' => 'icon'])

{{-- Bouton « Installer l'application » (PWA) ; masqué quand l'application est déjà installée. --}}
<div x-data="pwaInstall" x-show="!installed" x-cloak {{ $attributes->class('relative') }}>
    @if ($variant === 'full')
        <button type="button" @click="install()" class="btn-secondary w-full" data-testid="pwa-install">
            <x-icon name="download" class="size-4" /> Installer l’application
        </button>
    @else
        <button type="button" @click="install()" data-testid="pwa-install"
                class="inline-flex size-9 items-center justify-center rounded-full text-slate-600 hover:bg-brand-50 hover:text-brand-600 dark:text-slate-300 dark:hover:bg-slate-800"
                title="Installer l’application" aria-label="Installer l’application">
            <x-icon name="download" />
        </button>
    @endif

    {{-- Marche à suivre quand le navigateur ne propose pas l'installation directe --}}
    <div x-show="help" x-cloak x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-univ-navy/60 p-4" @click.self="help = false" @keydown.escape.window="help = false">
        <div class="w-full max-w-md rounded-[14px] border-t-4 border-t-brand-500 bg-white p-6 text-left text-sm shadow-2xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="pwa-help-title">
            <div class="mb-4 flex items-center gap-3">
                <img src="/icons/icon-192.png" alt="" class="size-12 rounded-xl">
                <h2 id="pwa-help-title" class="text-lg font-extrabold text-univ-ink dark:text-white">Installer SOGEDIPROMA</h2>
            </div>

            <template x-if="ios">
                <ol class="list-decimal space-y-2 pl-5 text-univ-muted dark:text-slate-300">
                    <li>Ouvrez cette page dans <strong>Safari</strong>.</li>
                    <li>Touchez le bouton <strong>Partager</strong> (carré avec une flèche vers le haut).</li>
                    <li>Choisissez <strong>« Sur l’écran d’accueil »</strong>, puis <strong>Ajouter</strong>.</li>
                </ol>
            </template>

            <template x-if="!ios && !secure">
                <div class="space-y-3 text-univ-muted dark:text-slate-300">
                    <p>L’installation complète exige une <strong>connexion sécurisée (HTTPS)</strong>, ce que le serveur de test n’a pas encore.</p>
                    <p class="font-bold text-univ-ink dark:text-white">Pour tester sur Android avec Chrome :</p>
                    <ol class="list-decimal space-y-1 pl-5">
                        <li>Ouvrez <code class="rounded bg-brand-50 px-1 text-brand-500">chrome://flags</code> et cherchez <strong>« Insecure origins treated as secure »</strong>.</li>
                        <li>Saisissez l’adresse <code class="rounded bg-brand-50 px-1 text-brand-500" x-text="location.origin"></code>, choisissez <strong>Enabled</strong>, puis <strong>Relaunch</strong>.</li>
                        <li>Revenez ici et touchez de nouveau <strong>Installer l’application</strong>.</li>
                    </ol>
                    <p>Sinon : menu <strong>⋮</strong> de Chrome → <strong>« Ajouter à l’écran d’accueil »</strong> (raccourci simple).</p>
                </div>
            </template>

            <template x-if="!ios && secure">
                <p class="text-univ-muted dark:text-slate-300">Ouvrez le menu du navigateur (<strong>⋮</strong> ou <strong>…</strong>) puis choisissez <strong>« Installer l’application »</strong> ou <strong>« Ajouter à l’écran d’accueil »</strong>.</p>
            </template>

            <button type="button" class="btn-primary mt-5 w-full" @click="help = false">Compris</button>
        </div>
    </div>
</div>
