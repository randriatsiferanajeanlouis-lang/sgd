<x-layouts.app title="Créer un utilisateur">
    <x-form.card title="Créer un utilisateur" subtitle="L’utilisateur devra changer ce mot de passe à sa première connexion."
                  :action="route('admin.users.store')" :cancel="route('admin.users.index')" submit="Créer le compte" submit-icon="plus"
                  enctype="multipart/form-data">

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.field name="last_name" label="Nom" required />
            <x-form.field name="first_name" label="Prénom" required />
        </div>

        <x-form.section title="Poste" icon="building" :fields="['department_id', 'job_title', 'role_id']">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="department_id" label="Département" :options="$departments->pluck('name', 'id')" placeholder="Sélectionner" required />
                <x-form.field name="job_title" label="Fonction" required />
                <x-form.select name="role_id" label="Rôle" :options="$roles->pluck('label', 'id')" placeholder="Sélectionner" required />
            </div>
        </x-form.section>

        <x-form.section title="Connexion" icon="key" :fields="['username', 'password', 'password_confirmation', 'is_active']">
            <x-form.field name="username" label="Nom d’utilisateur" required autocomplete="off" hint="Minuscules, chiffres, . _ -" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field name="password" label="Mot de passe" type="password" required autocomplete="new-password"
                    hint="Au moins 8 caractères, avec une majuscule, une minuscule et un chiffre." />
                <x-form.field name="password_confirmation" label="Confirmation du mot de passe" type="password" required autocomplete="new-password" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="size-4 rounded border-slate-300">
                Compte actif
            </label>
        </x-form.section>

        <x-form.section title="Autres informations" icon="info" :open="false" :fields="['phone', 'email', 'photo']">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field name="phone" label="Téléphone" />
                <x-form.field name="email" label="E-mail interne" type="email" />
            </div>
            <x-form.field name="photo" label="Photo" type="file" accept="image/png,image/jpeg,image/webp" />
        </x-form.section>
    </x-form.card>
</x-layouts.app>
