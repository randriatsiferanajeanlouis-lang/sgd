<x-layouts.app title="Mot de passe">
    <x-form.card :title="$isMandatory ? 'Remplacez votre mot de passe temporaire' : 'Changer mon mot de passe'"
                  :action="route('password.change.update')" method="PUT" :cancel="$isMandatory ? null : route('profile.show')">

        <x-form.field name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" required />

        <x-form.section title="Nouveau mot de passe" icon="key" :fields="['password', 'password_confirmation']">
            <x-form.field name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" required
                hint="Au moins 8 caractères, avec une majuscule, une minuscule et un chiffre." />
            <x-form.field name="password_confirmation" label="Confirmation du nouveau mot de passe" type="password" autocomplete="new-password" required />
        </x-form.section>
    </x-form.card>
</x-layouts.app>
