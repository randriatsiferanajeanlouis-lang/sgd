@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-screen bg-univ-navy font-sans text-univ-ink antialiased dark:text-slate-100">
    {{-- Arrière-plan : photo de la façade sous un voile bleu roi / bleu nuit (charte universitaire) --}}
    <div class="fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <img src="{{ asset('images/login-facade.jpg') }}" alt="" class="size-full object-cover object-[30%_center]">
        <div class="absolute inset-0 bg-[linear-gradient(145deg,rgba(12,12,74,0.92),rgba(17,17,111,0.72)_45%,rgba(35,35,184,0.45))]"></div>
    </div>

    <div class="grid min-h-screen lg:grid-cols-2">
        <section class="hidden p-14 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex flex-col items-start gap-2">
                <img src="{{ asset('images/logo-sogediproma.png') }}" alt="Sogediproma" width="330" height="110"
                     class="h-[110px] w-auto drop-shadow-[0_6px_18px_rgba(0,0,0,0.45)]">
                <span class="pl-2 text-xs font-extrabold tracking-[0.11em] text-[#c9c9ff] uppercase">Gestion des dossiers</span>
            </div>

            <div>
                <p class="mb-4 text-[11px] font-extrabold tracking-[0.11em] text-[#aaaaff] uppercase">Correspondances · Départements · Archives</p>
                <h1 class="max-w-lg text-4xl leading-[1.15] font-extrabold tracking-[-0.025em]">Toutes vos correspondances, suivies et tracées.</h1>
                <p class="mt-4 max-w-md text-[15px] leading-relaxed text-[#dedffd]">
                    Réception, transmission aux départements, réponses et archivage : chaque étape est historisée.
                </p>
            </div>

            <p class="border-t border-white/10 pt-4 text-[13px] text-[#d8d8ef]">&copy; {{ date('Y') }} Jean Louis Pro</p>
        </section>

        <main class="relative flex items-center justify-center px-4 py-10">
            <x-theme-toggle class="absolute top-4 right-4 bg-white/90 dark:bg-slate-900/80" />

            <div class="w-full max-w-md rounded-[14px] border border-[#dde0ee] border-t-4 border-t-brand-500 bg-white p-6 shadow-[0_20px_50px_rgba(0,0,0,0.30)] sm:p-10 dark:border-slate-700 dark:border-t-brand-300 dark:bg-slate-900">
                {{-- Logo blanc recoloré (masque CSS) pour la carte claire sur téléphone --}}
                <div class="mb-6 lg:hidden">
                    <span role="img" aria-label="Sogediproma"
                          class="block h-16 w-48 bg-brand-500 dark:bg-white"
                          style="-webkit-mask: url('{{ asset('images/logo-sogediproma.png') }}') left center / contain no-repeat; mask: url('{{ asset('images/logo-sogediproma.png') }}') left center / contain no-repeat;"></span>
                </div>
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
