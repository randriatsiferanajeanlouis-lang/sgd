@props(['number'])

{{-- Une étape d'un formulaire en plusieurs étapes (voir x-form.modal, option « steps »). --}}
<div x-show="step === {{ $number }}" data-step="{{ $number }}" @if ($number > 1) x-cloak @endif {{ $attributes->class('space-y-5') }}>
    {{ $slot }}
</div>
