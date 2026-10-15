<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Version d'un document : toutes les versions partagent document_key.
 * Une seule version est ACTIVE ; les autres sont REPLACED ou CANCELLED
 * et restent enregistrées.
 */
#[Fillable([
    'dossier_id',
    'response_id',
    'document_key',
    'version',
    'status',
    'previous_version_id',
    'category',
    'original_name',
    'disk',
    'path',
    'mime_type',
    'size',
    'checksum',
    'uploaded_by',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason',
])]
class Document extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => DocumentStatus::class,
            'category' => DocumentCategory::class,
            'size' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === DocumentStatus::Active;
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Active->value);
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    /**
     * @return BelongsTo<Response, $this>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'previous_version_id');
    }

    /**
     * @return HasOne<Document, $this>
     */
    public function nextVersion(): HasOne
    {
        return $this->hasOne(Document::class, 'previous_version_id');
    }

    /**
     * Toutes les versions du même document, de la plus ancienne à la plus récente.
     *
     * @return HasMany<Document, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Document::class, 'document_key', 'document_key')->orderBy('version');
    }
}
