@props(['href', 'reference' => null])

{{-- Bouton « Voir » d'un dossier dans les listes (même apparence partout). --}}
<a href="{{ $href }}" {{ $attributes->class('btn-secondary min-h-8 px-3 py-1.5 text-xs') }}
   @if ($reference) aria-label="Voir le dossier {{ $reference }}" @endif>
    <x-icon name="eye" class="size-4" /> Voir
</a>
