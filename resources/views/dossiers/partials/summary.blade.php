@php
    $dossierClosed = in_array($dossier->status, [\App\Enums\DossierStatus::Answered, \App\Enums\DossierStatus::Archived], true);
    $deadline = \App\Support\Deadline::describe($dossier->due_at, $dossierClosed);
    $steps = \App\Enums\DossierStatus::cases();
    $currentStep = array_search($dossier->status, $steps, true);
    $answeredCount = $dossier->recipients->whereNotNull('answered_at')->count();
    $recipientCount = $dossier->recipients->count();
    $previous = url()->previous();
    $backUrl = $previous !== url()->current() && str_starts_with($previous, url('/')) ? $previous : route('dashboard');
@endphp

<nav class="mb-4 flex items-center gap-2 text-sm" aria-label="Fil d’Ariane">
    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 font-semibold text-brand-500 hover:underline dark:text-brand-300">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
        Retour
    </a>
    <span class="text-slate-300 dark:text-slate-600">/</span>
    <span class="text-slate-500 dark:text-slate-400">Dossiers</span>
    <span class="text-slate-300 dark:text-slate-600">/</span>
    <span class="font-mono font-semibold text-univ-ink dark:text-white" aria-current="page">{{ $dossier->reference }}</span>
</nav>

<div class="card overflow-hidden p-0">
    <div class="border-b border-univ-line bg-univ-surface/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-950/30">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md bg-brand-500 px-2 py-0.5 font-mono text-xs font-bold tracking-wide text-white dark:bg-brand-400">{{ $dossier->reference }}</span>
                    <x-badge :color="$dossier->status->color()">{{ $dossier->status->label() }}</x-badge>
                    <x-badge :color="$dossier->isExternal() ? 'violet' : 'slate'">{{ $dossier->isExternal() ? 'Demande externe' : 'Demande interne' }}</x-badge>
                    <x-deadline :due-at="$dossier->due_at" :closed="$dossierClosed" />
                </div>
                <h1 class="mt-3 text-xl leading-snug font-extrabold tracking-[-0.02em] break-words text-univ-ink sm:text-2xl dark:text-white">{{ $dossier->subject }}</h1>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($canViewHistory)
                    <a href="#historique" class="btn-secondary"><x-icon name="clock" class="size-4" /> Historique</a>
                @endif
                @can('update', $dossier)
                    <a href="{{ route('dossiers.edit', $dossier) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Modifier</a>
                @endcan
                @can('delete', $dossier)
                    <form method="POST" action="{{ route('dossiers.destroy', $dossier) }}"
                          onsubmit="return confirm('Placer le dossier {{ $dossier->reference }} dans la corbeille ? Il disparaîtra de votre espace, mais les destinataires le conserveront. Vous pourrez le restaurer depuis la corbeille.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger"><x-icon name="trash" class="size-4" /> Supprimer</button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Avancement du dossier --}}
        <ol class="mt-5 flex items-center" aria-label="Avancement du dossier">
            @foreach ($steps as $index => $step)
                @php($done = $index <= $currentStep)
                <li @class(['flex items-center', 'flex-1' => ! $loop->last]) @if ($index === $currentStep) aria-current="step" @endif>
                    <span class="flex items-center gap-2">
                        <span @class([
                            'flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                            'bg-brand-500 text-white dark:bg-brand-400' => $done,
                            'border border-univ-line bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900' => ! $done,
                            'ring-4 ring-brand-500/15' => $index === $currentStep,
                        ])>
                            @if ($index < $currentStep)
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        <span @class(['hidden text-xs sm:inline', 'font-bold text-univ-ink dark:text-white' => $done, 'text-slate-400' => ! $done])>{{ $step->label() }}</span>
                    </span>
                    @unless ($loop->last)
                        <span @class(['mx-3 h-0.5 flex-1 rounded-full', 'bg-brand-500 dark:bg-brand-400' => $index < $currentStep, 'bg-univ-line dark:bg-slate-700' => $index >= $currentStep])></span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </div>

    <div class="px-6 py-5">
        @if ($deadline && $deadline['overdue'])
            <div class="mb-5 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
                <x-icon name="clock" class="size-5 shrink-0" />
                <span>Statut : {{ $dossier->status->label() }} — Alerte : délai dépassé.</span>
            </div>
        @endif

        <dl class="grid gap-px overflow-hidden rounded-lg border border-univ-line bg-univ-line text-sm sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-800 dark:bg-slate-800">
            @php($cell = 'flex items-start gap-3 bg-white p-4 dark:bg-slate-900')
            @php($label = 'text-[11px] font-bold tracking-[0.06em] text-slate-500 uppercase dark:text-slate-400')

            <div class="{{ $cell }}">
                <x-icon name="document" class="mt-0.5 size-5 shrink-0 text-brand-400" />
                <div class="min-w-0"><dt class="{{ $label }}">Type de demande</dt><dd class="mt-1 font-semibold break-words">{{ $dossier->typeLabel() }}</dd></div>
            </div>
            <div class="{{ $cell }}">
                <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-brand-400" />
                <div><dt class="{{ $label }}">Urgence</dt>
                    <dd class="mt-1 flex items-center gap-2 font-semibold">
                        @if ($dossier->urgencyLevel)
                            <span class="size-2.5 rounded-full" style="background-color: {{ $dossier->urgencyLevel->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                        @endif
                        {{ $dossier->urgencyLevel?->name ?? 'Non précisée' }}
                    </dd>
                </div>
            </div>
            <div class="{{ $cell }}">
                <x-avatar :user="$dossier->creator" size="size-9" class="shrink-0 text-xs" />
                <div class="min-w-0">
                    <dt class="{{ $label }}">{{ $dossier->isExternal() ? 'Enregistré par' : 'Expéditeur' }}</dt>
                    <dd class="mt-1 font-semibold">{{ $dossier->creator->name }}</dd>
                    <dd class="text-xs text-slate-500 dark:text-slate-400">{{ collect([$dossier->creator->job_title, $dossier->creator->department?->name])->filter()->implode(' · ') }}</dd>
                </div>
            </div>
            <div class="{{ $cell }}">
                <x-icon name="calendar" class="mt-0.5 size-5 shrink-0 text-brand-400" />
                <div><dt class="{{ $label }}">Créé le</dt><dd class="mt-1 font-semibold">{{ $dossier->created_at->format('d/m/Y') }} <span class="font-normal text-slate-500 dark:text-slate-400">à {{ $dossier->created_at->format('H:i') }}</span></dd></div>
            </div>
            <div class="{{ $cell }}">
                <x-icon name="clock" class="mt-0.5 size-5 shrink-0 text-brand-400" />
                <div><dt class="{{ $label }}">Échéance</dt>
                    <dd class="mt-1 font-semibold">
                        @if ($dossier->due_at)
                            {{ $dossier->due_at->format('d/m/Y') }} <span class="font-normal text-slate-500 dark:text-slate-400">à {{ $dossier->due_at->format('H:i') }}</span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </div>
            <div class="{{ $cell }}">
                <x-icon name="users" class="mt-0.5 size-5 shrink-0 text-brand-400" />
                <div class="min-w-0 flex-1">
                    <dt class="{{ $label }}">Réponses reçues</dt>
                    <dd class="mt-1 font-semibold">{{ $answeredCount }} / {{ $recipientCount }} <span class="font-normal text-slate-500 dark:text-slate-400">destinataire{{ $recipientCount > 1 ? 's' : '' }}</span></dd>
                    <dd class="mt-2 h-1.5 overflow-hidden rounded-full bg-univ-line dark:bg-slate-700" aria-hidden="true">
                        <div class="h-full rounded-full bg-green-500" style="width: {{ $recipientCount ? round($answeredCount / $recipientCount * 100) : 0 }}%"></div>
                    </dd>
                </div>
            </div>
            @if ($dossier->isExternal())
                <div class="{{ $cell }}">
                    <x-icon name="inbox" class="mt-0.5 size-5 shrink-0 text-brand-400" />
                    <div><dt class="{{ $label }}">Reçu le</dt><dd class="mt-1 font-semibold">{{ $dossier->received_at?->format('d/m/Y') ?? '—' }}</dd></div>
                </div>
                <div class="{{ $cell }}">
                    <x-icon name="mail" class="mt-0.5 size-5 shrink-0 text-brand-400" />
                    <div><dt class="{{ $label }}">Canal de réception</dt><dd class="mt-1 font-semibold">{{ $dossier->reception_channel?->label() ?? '—' }}</dd></div>
                </div>
            @endif
        </dl>

        @if ($dossier->isExternal() && $dossier->externalRequester)
            @php($requester = $dossier->externalRequester)
            <div class="mt-5 flex items-start gap-3 rounded-lg border border-violet-200 bg-violet-50/60 p-4 text-sm dark:border-violet-900 dark:bg-violet-950/30">
                <x-icon name="user" class="mt-0.5 size-5 shrink-0 text-violet-600 dark:text-violet-300" />
                <div>
                    <div class="text-[11px] font-bold tracking-[0.06em] text-violet-700 uppercase dark:text-violet-300">Demandeur externe</div>
                    <div class="mt-1 font-semibold">{{ $requester->full_name }}@if ($requester->organization) <span class="font-normal">— {{ $requester->organization }}</span>@endif</div>
                    <div class="text-slate-500 dark:text-slate-400">{{ collect([$requester->phone, $requester->email])->filter()->implode(' · ') }}</div>
                </div>
            </div>
        @endif

        @if ($dossier->description)
            <div class="mt-5">
                <div class="text-[11px] font-bold tracking-[0.06em] text-slate-500 uppercase dark:text-slate-400">Description</div>
                <div class="mt-2 rounded-lg border-l-4 border-brand-500 bg-univ-surface/60 px-4 py-3 leading-relaxed whitespace-pre-line dark:border-brand-400 dark:bg-slate-950/30">{{ $dossier->description }}</div>
            </div>
        @endif
    </div>
</div>
