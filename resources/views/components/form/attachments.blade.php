@props(['name' => 'attachments', 'label' => 'Pièces jointes'])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</label>
    <input id="{{ $name }}" type="file" name="{{ $name }}[]" multiple
           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">PDF, Word, Excel ou images — 20 Mo maximum par fichier, 10 fichiers maximum.</p>
    @error($name)
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
    @error($name.'.*')
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
