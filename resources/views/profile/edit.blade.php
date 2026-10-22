<x-layouts.app title="Modifier mon profil">
    <x-form.card title="Modifier mes coordonnées" :action="route('profile.update')" method="PUT" :cancel="route('profile.show')"
                  enctype="multipart/form-data">

        <x-form.section title="Coordonnées" icon="mail" :fields="['phone', 'email']">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field name="phone" label="Téléphone" :value="$user->phone" autocomplete="tel" />
                <x-form.field name="email" label="E-mail interne" type="email" :value="$user->email" autocomplete="email" />
            </div>
        </x-form.section>

        <x-form.section title="Photo" icon="user" :fields="['photo', 'remove_photo']">
            <div class="flex items-center gap-4">
                <x-avatar :user="$user" size="size-16" />
                <div class="flex-1">
                    <x-form.field name="photo" label="Nouvelle photo" type="file" accept="image/png,image/jpeg,image/webp" hint="JPG, PNG ou WebP, 2 Mo maximum." />
                    @if ($user->photo_path)
                        <label class="mt-2 flex items-center gap-2 text-sm">
                            <input type="checkbox" name="remove_photo" value="1" class="size-4 rounded border-slate-300">
                            Supprimer la photo actuelle
                        </label>
                    @endif
                </div>
            </div>
        </x-form.section>
    </x-form.card>
</x-layouts.app>
