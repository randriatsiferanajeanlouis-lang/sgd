{{-- Avertissement affiché quand le type choisi exige un formulaire complet (variable Alpine « complete »). --}}
<p x-show="complete" x-cloak role="status"
   class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/60 dark:bg-amber-500/10 dark:text-amber-200">
    <x-icon name="info" class="mt-0.5 size-4" />
    <span>Pour ce type de demande, <strong>tous les champs</strong> du formulaire sont obligatoires (marqués <span class="text-red-600 dark:text-red-400">*</span>).</span>
</p>
