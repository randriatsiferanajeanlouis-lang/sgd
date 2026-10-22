@if ($visibleResponses->isNotEmpty())
    <section class="card" aria-labelledby="responses-title">
        <x-section-title id="responses-title" icon="send" :count="$visibleResponses->count()">Réponses</x-section-title>

        <ol class="mt-5 space-y-4">
            @foreach ($visibleResponses as $response)
                <li @class([
                    'overflow-hidden rounded-lg border',
                    'border-green-300 dark:border-green-800' => $response->is_final,
                    'border-univ-line dark:border-slate-800' => ! $response->is_final,
                ])>
                    <div @class([
                        'flex flex-wrap items-center justify-between gap-3 px-4 py-3',
                        'bg-green-50 dark:bg-green-950/30' => $response->is_final,
                        'bg-univ-surface/60 dark:bg-slate-950/30' => ! $response->is_final,
                    ])>
                        <div class="flex min-w-0 items-center gap-3">
                            <x-avatar :user="$response->author" size="size-9" class="shrink-0 text-xs" />
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-semibold">{{ $response->author->name }}</span>
                                    @if ($response->is_final)
                                        <x-badge color="green">Réponse officielle</x-badge>
                                    @endif
                                    @if ($response->decision)
                                        <x-badge :color="$response->decision->color()">Décision {{ Str::lower($response->decision->label()) }}</x-badge>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ collect([$response->author->job_title, $response->author->department?->name])->filter()->implode(' · ') }}</div>
                            </div>
                        </div>
                        <time class="text-xs text-slate-500 dark:text-slate-400" datetime="{{ $response->created_at->toIso8601String() }}">
                            {{ $response->created_at->format('d/m/Y') }} à {{ $response->created_at->format('H:i') }}
                        </time>
                    </div>

                    <div class="px-4 py-4">
                        <div class="leading-relaxed whitespace-pre-line">{{ $response->content }}</div>

                        @if ($response->justification || $response->recommendation)
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                @if ($response->justification)
                                    <div class="rounded-md bg-univ-surface/60 px-3 py-2 dark:bg-slate-950/30"><dt class="text-xs font-bold text-slate-500 dark:text-slate-400">Justification :</dt><dd class="mt-0.5">{{ $response->justification }}</dd></div>
                                @endif
                                @if ($response->recommendation)
                                    <div class="rounded-md bg-univ-surface/60 px-3 py-2 dark:bg-slate-950/30"><dt class="text-xs font-bold text-slate-500 dark:text-slate-400">Recommandation :</dt><dd class="mt-0.5">{{ $response->recommendation }}</dd></div>
                                @endif
                            </dl>
                        @endif

                        @if ($response->is_final)
                            <div class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                                Envoyée le {{ $response->sent_to_requester_at?->format('d/m/Y') }} · {{ $response->channel?->label() }}
                            </div>
                        @endif

                        @if ($response->documents->isNotEmpty())
                            <ul class="mt-4 flex flex-wrap gap-2 border-t border-univ-line pt-3 text-sm dark:border-slate-800">
                                @foreach ($response->documents as $document)
                                    <li class="rounded-md border border-univ-line px-2.5 py-1 dark:border-slate-700">@include('dossiers.partials.document-link', ['document' => $document])</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
