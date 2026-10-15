@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'requiredWhen' => null])

{{-- requiredWhen : expression Alpine qui rend le champ obligatoire (ex. « complete » : formulaire complet). --}}

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}@if ($attributes->has('required')) <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>@elseif ($requiredWhen) <span class="text-red-600 dark:text-red-400" aria-hidden="true" x-show="{{ $requiredWhen }}" x-cloak>*</span>@endif</label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif
        @if ($requiredWhen) x-bind:required="{{ $requiredWhen }}" @endif
        {{ $attributes->class([
            'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900',
            'border-red-400 focus:border-red-500' => $errors->has($name),
            'border-slate-300 focus:border-brand-500 dark:border-slate-700' => ! $errors->has($name),
        ]) }}
    >

    @if ($hint)
        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
