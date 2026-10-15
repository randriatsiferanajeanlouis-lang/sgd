<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveDepartmentRequest;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\User;
use App\Services\DepartmentService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $departments) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        $canManage = $request->user()->can('create', Department::class);

        $dossierCount = Dossier::query()
            ->selectRaw('count(*)')
            ->where(fn (Builder $query) => $query
                ->whereColumn('dossiers.originating_department_id', 'departments.id')
                ->orWhereExists(fn ($recipients) => $recipients
                    ->select(DB::raw(1))
                    ->from('dossier_recipients')
                    ->whereColumn('dossier_recipients.dossier_id', 'dossiers.id')
                    ->whereColumn('dossier_recipients.department_id', 'departments.id')));

        $all = Department::query()
            ->with('manager:id,name')
            ->withCount(['users', 'users as active_users_count' => fn (Builder $users) => $users->where('is_active', true)])
            ->addSelect(['dossiers_count' => $dossierCount])
            ->orderBy('sort_order')
            ->get();

        // Recherche par nom ou code, même règle que les autres listes (App\Support\Search).
        $term = trim((string) $request->string('search'));
        $departments = $term === '' ? $all : Department::query()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', Search::pattern($term))
                ->orWhere('code', 'like', Search::pattern($term)))
            ->pluck('id')
            ->pipe(fn ($ids) => $all->whereIn('id', $ids)->values());

        if (Search::wantsSuggestions()) {
            return response()->json([
                'results' => $departments->take(Search::SUGGESTIONS)->map(fn (Department $department) => [
                    'label' => $department->name,
                    'detail' => $department->code.' · '.$department->active_users_count.' compte(s) actif(s) sur '.$department->users_count,
                    'url' => $canManage
                        ? route('admin.departments.edit', $department)
                        : route('directory.dossiers', $department),
                ])->values(),
            ]);
        }

        return view('admin.departments.index', [
            // Dossiers envoyés par l'utilisateur, par département destinataire (lecture seule).
            'sentCounts' => $canManage ? collect() : DossierRecipient::query()
                ->whereHas('dossier', fn (Builder $dossier) => $dossier->where('created_by', $request->user()->id))
                ->selectRaw('department_id, COUNT(DISTINCT dossier_id) as total')
                ->groupBy('department_id')
                ->pluck('total', 'department_id'),
            'departments' => $departments,
            'cards' => $all,
            'totals' => [
                'users' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'departments' => $all->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Department::class);

        return view('admin.departments.form', ['department' => new Department, 'members' => collect()]);
    }

    public function store(SaveDepartmentRequest $request): RedirectResponse
    {
        $this->departments->create([
            ...$request->safe()->except('manager_id'),
            'sort_order' => (int) Department::query()->max('sort_order') + 1,
        ]);

        return redirect()->route('admin.departments.index')->with('status', 'Le département a été créé.');
    }

    public function edit(Department $department): View
    {
        Gate::authorize('update', $department);

        return view('admin.departments.form', [
            'department' => $department,
            'members' => User::query()->active()->where('department_id', $department->id)->orderBy('last_name')->get(['id', 'name', 'job_title']),
        ]);
    }

    public function update(SaveDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->departments->update($department, $request->safe()->except('manager_id'));

        if ($request->input('manager_id') != $department->manager_id) {
            $this->departments->assignManager($department, $request->filled('manager_id') ? User::query()->find($request->integer('manager_id')) : null);
        }

        return redirect()->route('admin.departments.index')->with('status', 'Le département a été mis à jour.');
    }

    public function toggle(Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);

        $this->departments->setActive($department, ! $department->is_active);

        return back()->with('status', $department->is_active
            ? 'Le département a été réactivé.'
            : 'Le département a été désactivé. Ses employés, dossiers, documents et historique sont conservés.');
    }
}
