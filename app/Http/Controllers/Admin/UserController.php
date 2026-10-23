<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeUserDepartmentRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserController extends Controller
{
    public function __construct(private readonly UserAccountService $accounts) {}

    public function index(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with(['department', 'role'])
            ->when($request->filled('search'), fn (Builder $query) => $query->where(fn (Builder $search) => $search
                ->where('last_name', 'like', Search::pattern($request->string('search')))
                ->orWhere('first_name', 'like', Search::pattern($request->string('search')))
                ->orWhere('username', 'like', Search::pattern($request->string('search')))))
            ->when($request->filled('department'), fn (Builder $query) => $query->where('department_id', $request->integer('department')))
            ->when($request->filled('role'), fn (Builder $query) => $query->where('role_id', $request->integer('role')))
            ->when($request->input('status') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (Search::wantsSuggestions()) {
            return Search::suggestions($users, fn (User $user) => [
                'label' => $user->name,
                'detail' => collect([$user->username, $user->department?->name, $user->job_title])->filter()->implode(' · '),
                'url' => route('admin.users.edit', $user),
            ]);
        }

        $viewer = $request->user();

        return Inertia::render('Admin/Users/Index', [
            'title' => 'Utilisateurs',
            'filters' => [
                'search' => $request->string('search')->toString(),
                'department' => $request->string('department')->toString(),
                'role' => $request->string('role')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'departments' => Department::query()->orderBy('sort_order')->get(['id', 'name'])
                ->map(fn (Department $department) => ['value' => (string) $department->id, 'label' => $department->name])->values(),
            'roles' => Role::query()->orderBy('label')->get(['id', 'label'])
                ->map(fn (Role $role) => ['value' => (string) $role->id, 'label' => $role->label])->values(),
            'users' => $users->paginate(20)->withQueryString()->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'lastName' => $user->last_name,
                'firstName' => $user->first_name,
                'username' => $user->username,
                'department' => $user->department?->name,
                'jobTitle' => $user->job_title,
                'role' => $user->role->label,
                'active' => (bool) $user->is_active,
                'editUrl' => route('admin.users.edit', $user),
                'canDeactivate' => $viewer->can('deactivate', $user),
                'deactivateUrl' => route('users.deactivate', $user),
                'canReactivate' => $viewer->can('reactivate', $user),
                'reactivateUrl' => route('admin.users.reactivate', $user),
                'canResetPassword' => $viewer->can('resetPassword', $user),
                'resetPasswordUrl' => route('admin.users.reset-password', $user),
            ]),
            'urls' => [
                'index' => route('admin.users.index'),
                'create' => route('admin.users.create'),
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', [
            'departments' => Department::query()->active()->orderBy('sort_order')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('label')->get(['id', 'label']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->accounts->create($request->safe()->except('photo'), $request->file('photo'));

        return redirect()->route('admin.users.edit', $user)->with('status', "Le compte « {$user->username} » a été créé. L’utilisateur devra changer son mot de passe à la première connexion.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user->load(['department', 'role', 'previousAccount']),
            'roles' => Role::query()->orderBy('label')->get(['id', 'label']),
            'departments' => Department::query()->active()->where('id', '!=', $user->department_id)->orderBy('sort_order')->get(['id', 'name']),
            'auditLogs' => AuditLog::query()->with('actor')->whereMorphedTo('target', $user)->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->accounts->update($user, $request->validated());

        return redirect()->route('admin.users.edit', $user)->with('status', 'Le compte a été mis à jour.');
    }

    public function changeDepartment(ChangeUserDepartmentRequest $request, User $user): RedirectResponse
    {
        $result = $this->accounts->changeDepartment(
            $user,
            Department::query()->findOrFail($request->integer('department_id')),
            $request->validated('username'),
            $request->validated('job_title'),
        );

        return redirect()->route('admin.users.edit', $result['user'])
            ->with('status', "L’ancien compte « {$user->username} » a été désactivé et son historique conservé. Nouveau compte créé.")
            ->with('temporary_password', ['username' => $result['user']->username, 'password' => $result['temporary_password']]);
    }
}
