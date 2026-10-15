<?php

namespace App\Models;

use App\Enums\ReminderChannel;
use App\Enums\ReminderReason;
use App\Enums\ReminderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dossier_id',
    'dossier_recipient_id',
    'sent_by',
    'type',
    'channel',
    'reason',
    'message',
    'scheduled_at',
    'sent_at',
])]
class Reminder extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'channel' => ReminderChannel::class,
            'reason' => ReminderReason::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Relances programmées, échues et pas encore envoyées.
     */
    #[Scope]
    protected function due(Builder $query): Builder
    {
        return $query->whereNull('sent_at')->where('scheduled_at', '<=', now());
    }
}
