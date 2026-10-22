@props(['value' => null])

{{-- Affiché quand le type « Autre » est choisi (variable Alpine « type » du formulaire). --}}
@php($otherId = \App\Models\RequestType::otherId())

@if ($otherId)
    <div x-show="type === '{{ $otherId }}'" x-cloak x-transition.opacity data-testid="other-request-type">
        <x-form.field name="request_type_other" label="Précisez le type de demande" :value="$value" maxlength="150"
                      required-when="type === '{{ $otherId }}'" placeholder="Ex. : Location de salle, Autorisation d’absence…"
                      hint="Ce texte remplace « Autre » : c’est le type que verront les destinataires." />
    </div>
@endif
