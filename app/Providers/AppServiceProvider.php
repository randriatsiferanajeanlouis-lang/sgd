<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\DossierTransmission;
use App\Models\DossierValidation;
use App\Models\ExternalRequester;
use App\Models\Reminder;
use App\Models\RequestType;
use App\Models\Response;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());

        // Chaque permission de la table permissions devient une ability :
        // @can('dossiers.create_external'), middleware can:users.manage...
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ?: null);

        // Noms stables stockés dans dossier_activity_logs.subject_type,
        // indépendants des noms de classes PHP.
        Relation::enforceMorphMap([
            'dossier' => Dossier::class,
            'dossier_recipient' => DossierRecipient::class,
            'response' => Response::class,
            'document' => Document::class,
            'dossier_transmission' => DossierTransmission::class,
            'reminder' => Reminder::class,
            'user' => User::class,
            'department' => Department::class,
            'role' => Role::class,
            'request_type' => RequestType::class,
            'urgency_level' => UrgencyLevel::class,
            'setting' => Setting::class,
            'external_requester' => ExternalRequester::class,
            'dossier_validation' => DossierValidation::class,
        ]);
    }
}
