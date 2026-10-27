<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DossierMonitorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentMemberController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\DossierTrashController;
use App\Http\Controllers\ExternalRequestController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\UserStatusController;
use App\Http\Controllers\ValidationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
| Aucune inscription publique : les comptes sont créés par l'administrateur.
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'account.usable'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/password', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.change.update');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::get('/users/{user}/photo', [ProfileController::class, 'photo'])->name('users.photo');

    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
    Route::get('/directory/departments/{department}/dossiers', [DirectoryController::class, 'dossiers'])->name('directory.dossiers');
    Route::get('/directory/departments/{department}/members', [DirectoryController::class, 'members'])->name('directory.members');

    Route::get('/dossiers', [DossierController::class, 'index'])->name('dossiers.index');
    Route::get('/dossiers/create', [DossierController::class, 'create'])->name('dossiers.create');
    Route::post('/dossiers', [DossierController::class, 'store'])->name('dossiers.store');
    Route::get('/dossiers/{dossier}', [DossierController::class, 'show'])->name('dossiers.show');
    Route::get('/dossiers/{dossier}/edit', [DossierController::class, 'edit'])->name('dossiers.edit');
    Route::put('/dossiers/{dossier}', [DossierController::class, 'update'])->name('dossiers.update');
    Route::delete('/dossiers/{dossier}', [DossierController::class, 'destroy'])->name('dossiers.destroy');
    Route::get('/demandes', [DossierController::class, 'requests'])->name('requests.index');
    Route::get('/demandes/export', [DossierController::class, 'exportRequests'])->name('requests.export');
    Route::get('/corbeille', [DossierTrashController::class, 'index'])->name('trash.index');
    Route::post('/corbeille/{dossier}/restore', [DossierTrashController::class, 'restore'])->name('trash.restore');
    Route::delete('/corbeille/{dossier}', [DossierTrashController::class, 'destroy'])->name('trash.destroy');
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('/dossiers/{dossier}/history.pdf', [HistoryController::class, 'pdf'])->name('dossiers.history.pdf');
    Route::get('/dossiers/{dossier}/history.docx', [HistoryController::class, 'word'])->name('dossiers.history.word');

    Route::post('/dossiers/{dossier}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/dossiers/{dossier}/documents.zip', [DocumentController::class, 'zip'])->name('documents.zip');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents/{document}/replace', [DocumentController::class, 'replace'])->name('documents.replace');
    Route::post('/documents/{document}/cancel', [DocumentController::class, 'cancel'])->name('documents.cancel');

    Route::post('/treatments/{recipient}/respond', [TreatmentController::class, 'respond'])->name('treatments.respond');
    Route::post('/treatments/{recipient}/archive', [TreatmentController::class, 'archive'])->name('treatments.archive');
    Route::post('/treatments/{recipient}/reopen', [TreatmentController::class, 'reopen'])->name('treatments.reopen');
    Route::post('/treatments/{recipient}/forward', [TreatmentController::class, 'forward'])->name('treatments.forward');
    Route::post('/treatments/{recipient}/remind', [ReminderController::class, 'store'])->name('reminders.store');

    Route::get('/statistics', StatisticsController::class)->name('statistics.index');
    Route::post('/treatments/{recipient}/validation', [ValidationController::class, 'store'])->name('validations.store');

    Route::get('/external-requests', [ExternalRequestController::class, 'index'])->name('external-requests.index');
    Route::get('/external-requests/create', [ExternalRequestController::class, 'create'])->name('external-requests.create');
    Route::post('/external-requests', [ExternalRequestController::class, 'store'])->name('external-requests.store');
    Route::post('/external-requests/{dossier}/official-response', [ExternalRequestController::class, 'respond'])->name('external-requests.respond');
    Route::post('/external-requests/{dossier}/archive', [ExternalRequestController::class, 'archive'])->name('external-requests.archive');

    Route::get('/validations', [ValidationController::class, 'index'])->name('validations.index');
    Route::post('/validations/{validation}/decision', [ValidationController::class, 'decide'])->name('validations.decide');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    Route::post('/users/{user}/deactivate', [UserStatusController::class, 'deactivate'])->name('users.deactivate');
    Route::get('/employees', [DepartmentMemberController::class, 'index'])->name('department-members.index');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:manage_users')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/change-department', [UserController::class, 'changeDepartment'])->name('users.change-department');
            Route::post('/users/{user}/reactivate', [UserStatusController::class, 'reactivate'])->name('users.reactivate');
            Route::post('/users/{user}/reset-password', [UserPasswordController::class, 'store'])->name('users.reset-password');
        });

        Route::get('/departments', [DepartmentController::class, 'index'])
            ->name('departments.index')
            ->middleware('can:viewAny,App\Models\Department');

        Route::middleware('can:manage_departments')->group(function () {
            Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create');
            Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
            Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::patch('/departments/{department}/toggle', [DepartmentController::class, 'toggle'])->name('departments.toggle');
        });

        Route::get('/dossiers', [DossierMonitorController::class, 'index'])->name('dossiers.index');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/activity', [ActivityLogController::class, 'index'])->middleware('can:view_activity_log')->name('activity.index');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/request-types', [SettingsController::class, 'storeRequestType'])->name('settings.request-types.store');
        Route::put('/settings/request-types/{requestType}', [SettingsController::class, 'updateRequestType'])->name('settings.request-types.update');
        Route::put('/settings/urgency-levels/{urgencyLevel}', [SettingsController::class, 'updateUrgencyLevel'])->name('settings.urgency-levels.update');
        Route::put('/settings/roles/{role}/permissions', [SettingsController::class, 'updateRolePermissions'])->name('settings.roles.permissions');
        Route::put('/settings/values/{setting}', [SettingsController::class, 'updateSetting'])->name('settings.values.update');
    });
});
