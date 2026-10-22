<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function show(Request $request): InertiaResponse
    {
        $user = $request->user()->load(['department', 'role']);

        return Inertia::render('Profile/Show', [
            'title' => 'Mon profil',
            'profile' => [
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'username' => $user->username,
                'initials' => $user->initials(),
                'photoUrl' => $user->photo_path ? route('users.photo', $user) : null,
                'details' => collect([
                    'Nom' => $user->last_name,
                    'Prénom' => $user->first_name,
                    'Fonction' => $user->job_title,
                    'Département' => $user->department?->name,
                    'Rôle' => $user->role->label,
                    'Téléphone' => $user->phone,
                    'E-mail interne' => $user->email,
                ])->map(fn ($value, $label) => ['label' => $label, 'value' => $value])->values(),
            ],
            'urls' => [
                'edit' => route('profile.edit'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request, AuditTrail $auditTrail): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['phone', 'email']));
        $photoChanged = $request->boolean('remove_photo') || $request->hasFile('photo');

        if ($request->boolean('remove_photo') || $request->hasFile('photo')) {
            if ($user->photo_path) {
                Storage::disk('local')->delete($user->photo_path);
            }

            $user->photo_path = $request->hasFile('photo')
                ? $request->file('photo')->store('profile-photos', 'local')
                : null;
        }

        $changes = array_intersect_key($user->getDirty(), array_flip(['phone', 'email']));
        $before = array_intersect_key($user->getOriginal(), $changes);
        $user->save();

        if ($changes !== [] || $photoChanged) {
            $auditTrail->record('user.profile_updated', $user, $before, $changes + ($photoChanged ? ['photo' => 'modifiée'] : []), department: $user->department);
        }

        return redirect()->route('profile.show')->with('status', 'Votre profil a été mis à jour.');
    }

    /**
     * Les photos sont stockées hors du dossier public : elles ne sont
     * servies qu'aux utilisateurs connectés.
     */
    public function photo(User $user): StreamedResponse
    {
        abort_unless($user->photo_path && Storage::disk('local')->exists($user->photo_path), 404);

        return Storage::disk('local')->response($user->photo_path);
    }

    public function updateTheme(Request $request): RedirectResponse|HttpResponse
    {
        $validated = $request->validate(['theme' => ['required', 'in:light,dark']]);

        $request->user()->forceFill(['theme' => $validated['theme']])->save();

        return $request->expectsJson() ? response()->noContent() : back();
    }
}
