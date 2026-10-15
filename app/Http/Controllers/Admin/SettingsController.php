<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequestScope;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UrgencyLevel;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paramétrage : types de demandes, urgences, permissions des rôles,
 * réglages généraux. Chaque modification est tracée.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('manage_settings') || $request->user()->hasPermission('manage_permissions'), 403);

        return view('admin.settings.index', [
            'requestTypes' => RequestType::query()->orderBy('sort_order')->get(),
            'urgencyLevels' => UrgencyLevel::query()->orderBy('sort_order')->get(),
            'roles' => Role::query()->with('permissions:id')->orderBy('id')->get(),
            'permissionGroups' => Permission::query()->orderBy('id')->get()->groupBy('group'),
            'settings' => Setting::query()->orderBy('group')->orderBy('key')->get(),
            'scopes' => collect(RequestScope::cases())->mapWithKeys(fn (RequestScope $scope) => [$scope->value => $scope->label()]),
        ]);
    }

    public function storeRequestType(Request $request): RedirectResponse
    {
        Gate::authorize('manage_request_types');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:request_types,name'],
            'scope' => ['required', Rule::enum(RequestScope::class)],
        ]);

        DB::transaction(function () use ($validated) {
            $type = RequestType::query()->create([
                ...$validated,
                'code' => Str::upper(Str::slug($validated['name'], '_')).'_'.Str::upper(Str::random(4)),
                // Règle par défaut : formulaire complet (décochable dans le tableau des types).
                'requires_complete_form' => true,
                'sort_order' => (int) RequestType::query()->max('sort_order') + 1,
            ]);

            $this->auditTrail->record('request_type.saved', $type, newValues: $type->only(['name', 'scope', 'is_active']));
        });

        return back()->with('status', 'Type de demande ajouté.');
    }

    public function updateRequestType(Request $request, RequestType $requestType): RedirectResponse
    {
        Gate::authorize('manage_request_types');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('request_types', 'name')->ignore($requestType)],
            'scope' => ['required', Rule::enum(RequestScope::class)],
            'requires_complete_form' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($request, $requestType, $validated) {
            $tracked = ['name', 'scope', 'requires_complete_form', 'is_active'];
            $before = $requestType->only($tracked);
            $requestType->update([
                ...$validated,
                'requires_complete_form' => $request->boolean('requires_complete_form'),
                'is_active' => $request->boolean('is_active'),
            ]);
            $this->auditTrail->record('request_type.saved', $requestType, $before, $requestType->only($tracked));
        });

        return back()->with('status', 'Type de demande mis à jour.');
    }

    public function updateUrgencyLevel(Request $request, UrgencyLevel $urgencyLevel): RedirectResponse
    {
        Gate::authorize('manage_urgencies');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('urgency_levels', 'name')->ignore($urgencyLevel)],
            'response_delay_hours' => ['required', 'integer', 'min:1', 'max:8760'],
        ]);

        DB::transaction(function () use ($request, $urgencyLevel, $validated) {
            $before = $urgencyLevel->only(['name', 'response_delay_hours', 'is_active']);
            $urgencyLevel->update([...$validated, 'is_active' => $request->boolean('is_active')]);
            $this->auditTrail->record('urgency_level.saved', $urgencyLevel, $before, $urgencyLevel->only(['name', 'response_delay_hours', 'is_active']));
        });

        return back()->with('status', 'Niveau d’urgence mis à jour.');
    }

    /**
     * Un administrateur ne peut pas retirer manage_permissions à son propre
     * rôle (il perdrait l'accès à cet écran).
     */
    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('manage_permissions');

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissionIds = collect($validated['permissions'] ?? [])->map(fn ($id) => (int) $id);

        if ($request->user()->role_id === $role->id) {
            $permissionIds->push(Permission::query()->where('name', 'manage_permissions')->value('id'));
        }

        DB::transaction(function () use ($role, $permissionIds) {
            $before = $role->permissions()->pluck('name')->sort()->values()->all();
            $role->permissions()->sync($permissionIds->unique()->all());
            $after = $role->permissions()->pluck('name')->sort()->values()->all();

            $this->auditTrail->record('role.permissions_updated', $role, ['permissions' => $before], ['permissions' => $after]);
        });

        return back()->with('status', "Permissions du rôle « {$role->label} » mises à jour.");
    }

    public function updateSetting(Request $request, Setting $setting): RedirectResponse
    {
        Gate::authorize('manage_settings');

        $validated = $request->validate(['value' => ['nullable', 'string', 'max:255']]);

        DB::transaction(function () use ($setting, $validated) {
            $before = ['value' => $setting->value];
            $setting->update(['value' => $validated['value']]);
            $this->auditTrail->record('setting.updated', $setting, $before, ['value' => $setting->value], $setting->key);
        });

        return back()->with('status', 'Paramètre mis à jour.');
    }
}
