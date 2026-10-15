@props(['title', 'subtitle' => null, 'kicker' => null])

<div class="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4 border-b border-univ-line pb-5 dark:border-slate-800">
    <div class="min-w-0">
        @if ($kicker)
            <p class="kicker mb-2">{{ $kicker }}</p>
        @endif
        <h1 class="text-[22px] leading-tight font-extrabold tracking-[-0.025em] break-words text-univ-ink sm:text-[28px] dark:text-white">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-univ-muted dark:text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endif
</div>
