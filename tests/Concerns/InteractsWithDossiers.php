<?php

namespace Tests\Concerns;

use App\Enums\RequestScope;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Aides communes aux tests des dossiers : création d'utilisateurs par rôle
 * et envoi d'une demande interne par l'interface HTTP réelle.
 */
trait InteractsWithDossiers
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(string $role, string $departmentCode = 'PRODUCTION', array $attributes = []): User
    {
        return User::factory()->withRole($role, $departmentCode)->create($attributes);
    }

    /**
     * @param  list<User>  $recipients
     * @param  array<string, mixed>  $overrides
     */
    protected function sendInternalRequest(User $sender, array $recipients, array $overrides = []): Dossier
    {
        $this->actingAs($sender)->post(route('dossiers.store'), [
            'subject' => 'Demande de fournitures',
            'request_type_id' => RequestType::query()->availableFor(RequestScope::Internal)->value('id'),
            'urgency_level_id' => UrgencyLevel::query()->active()->orderBy('sort_order')->value('id'),
            'due_at' => now()->addDays(5)->toDateString(),
            'description' => 'Merci de traiter cette demande.',
            'recipients' => array_map(fn (User $user) => $user->id, $recipients),
            ...$overrides,
        ])->assertSessionHasNoErrors();

        return Dossier::query()->latest('id')->firstOrFail();
    }

    protected function pdf(string $name = 'piece.pdf'): UploadedFile
    {
        return $this->realUpload($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    /**
     * Vrai fichier temporaire : son type est détecté sur le contenu (finfo),
     * comme pour un envoi réel, et non déduit du nom comme UploadedFile::fake().
     */
    protected function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }
}
