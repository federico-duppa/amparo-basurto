<?php

namespace App\Models;

use Database\Factories\VehicleDocumentRenewalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una vigencia anterior de un documento: al renovarlo, la fecha que quedó
 * atrás se guarda acá para conservar la historia.
 */
class VehicleDocumentRenewal extends Model
{
    /** @use HasFactory<VehicleDocumentRenewalFactory> */
    use HasFactory;

    protected $fillable = [
        'expires_on',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<VehicleDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(VehicleDocument::class, 'vehicle_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
