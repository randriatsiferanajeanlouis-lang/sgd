@if ($visibleResponses->isNotEmpty())
    <section class="card" aria-labelledby="responses-title">
        <h2 id="responses-title" class="text-lg font-semibold">Réponses</h2>

        <ol class="mt-4 space-y-4">
            @foreach ($visibleResponses as $response)
                <li @class([
                    'rounded-xl border p-4',
                    'border-green-300 bg-green-50/50 dark:border-green-800 dark:bg-green-950/30' => $response->is_final,
                    'border-slate-200 dark:border-slate-800' => ! $response->is_final,
                ])>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div>
                            <span class="font-semibold">{{ $response->author->name }}</span>
                            @if ($response->is_final)
                                <x-badge color="green" class="ml-1">Réponse officielle</x-badge>
                            @endif
                            @if ($response->decision)
                                <x-badge :color="$response->decision->color()" class="ml-1">Décision {{ Str::lower($response->decision->label()) }}</x-badge>
                            @endif
                        </div>
                        <span class="text-slate-500 dark:text-slate-400">{{ $response->created_at->format('d/m/Y H:i') }}</span>
                    </div>

                    <div class="mt-2 whitespace-pre-line">{{ $response->content }}</div>

                    @if ($response->justification)
                        <div class="mt-2 text-sm"><span class="font-medium">Justification :</span> {{ $response->justification }}</div>
                    @endif
                    @if ($response->recommendation)
                        <div class="mt-1 text-sm"><span class="font-medium">Recommandation :</span> {{ $response->recommendation }}</div>
                    @endif
                    @if ($response->is_final)
                        <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Envoyée le {{ $response->sent_to_requester_at?->format('d/m/Y') }} · {{ $response->channel?->label() }}
                        </div>
                    @endif

                    @if ($response->documents->isNotEmpty())
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($response->documents as $document)
                                <li>@include('dossiers.partials.document-link', ['document' => $document])</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
