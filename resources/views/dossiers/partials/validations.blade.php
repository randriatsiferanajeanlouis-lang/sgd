@if ($dossier->validations->isNotEmpty())
    <section class="card" aria-labelledby="validations-title">
        <x-section-title id="validations-title" icon="check" :count="$dossier->validations->count()">Validations hiérarchiques</x-section-title>

        <ol class="mt-4 space-y-4">
            @foreach ($dossier->validations as $validation)
                <li class="rounded-lg border border-univ-line p-4 dark:border-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span>
                            <span class="font-semibold">{{ $validation->requester->name }}</span> → {{ $validation->validator->name }}
                        </span>
                        <x-badge :color="$validation->status->color()">{{ $validation->status->label() }}</x-badge>
                    </div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Transmis le {{ $validation->created_at->format('d/m/Y H:i') }}</div>
                    @if ($validation->request_comment)
                        <div class="mt-2 text-sm"><span class="font-medium">Commentaire de l’employé :</span> {{ $validation->request_comment }}</div>
                    @endif
                    @if ($validation->decided_at)
                        <div class="mt-2 text-sm">
                            <span class="font-medium">Décision du {{ $validation->decided_at->format('d/m/Y H:i') }}</span>
                            @if ($validation->decision_comment) : {{ $validation->decision_comment }} @endif
                        </div>
                    @endif

                    @can('decide', $validation)
                        <form method="POST" action="{{ route('validations.decide', $validation) }}" class="mt-4 space-y-3">
                            @csrf
                            <x-form.textarea name="comment" label="Commentaire (facultatif)" rows="3" />
                            <div class="flex gap-2">
                                <button type="submit" name="decision" value="approve" class="btn-success">Valider</button>
                                <button type="submit" name="decision" value="reject" class="btn-danger">Refuser</button>
                            </div>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ol>
    </section>
@endif
