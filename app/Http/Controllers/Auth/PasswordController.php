<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/ChangePassword', [
            'title' => 'Mot de passe',
            'isMandatory' => (bool) $request->user()->must_change_password,
            'urls' => [
                'update' => route('password.change.update'),
                'cancel' => route('profile.show'),
            ],
        ]);
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('status', __('auth.password_changed'));
    }
}
