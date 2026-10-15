<?php

namespace App\Models;

use App\Enums\ReceptionChannel;
use App\Enums\ResponseDecision;
use App\Enums\ResponseType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Réponse d'un destinataire (avec décision pour une demande externe) ou
 * réponse officielle du RH au demandeur externe (type final_answer).
 */
#[Fillable([
    'dossier_id',
    'dossier_recipient_id',
    'department_id',
    'author_id',
    'type',
    'decision',
    'content',
    'justification',
    'recommendation',
    'is_final',
    'channel',
    'sent_to_requester_at',
])]
class Response extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResponseType::class,
            'decision' => ResponseDecision::class,
            'channel' => ReceptionChannel::class,
            'is_final' => 'boolean',
            'sent_to_requester_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    /**
     * @return BelongsTo<DossierRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DossierRecipient::class, 'dossier_recipient_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
