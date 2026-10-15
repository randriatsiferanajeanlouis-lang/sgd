@props(['name', 'label', 'value' => null, 'rows' => 4, 'requiredWhen' => null])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}@if ($attributes->has('required')) <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>@elseif ($requiredWhen) <span class="text-red-600 dark:text-red-400" aria-hidden="true" x-show="{{ $requiredWhen }}" x-cloak>*</span>@endif</label>

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($requiredWhen) x-bind:required="{{ $requiredWhen }}" @endif
        {{ $attributes->class([
            'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900',
            'border-red-400' => $errors->has($name),
            'border-slate-300 focus:border-brand-500 dark:border-slate-700' => ! $errors->has($name),
        ]) }}
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
