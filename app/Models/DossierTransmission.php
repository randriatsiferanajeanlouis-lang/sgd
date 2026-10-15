<?php

namespace App\Models;

use App\Enums\TransmissionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dossier_id',
    'dossier_recipient_id',
    'from_department_id',
    'to_department_id',
    'transmitted_by',
    'type',
    'comment',
    'transmitted_at',
])]
class DossierTransmission extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransmissionType::class,
            'transmitted_at' => 'datetime',
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
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function transmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transmitted_by');
    }
}
