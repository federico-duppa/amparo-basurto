<?php

namespace App\Models;

use App\Models\Concerns\AttachedFile;
use Database\Factories\VehicleDocumentAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un archivo adjunto a un documento del auto: la póliza, la oblea de la VTV,
 * el comprobante de la patente, en PDF o como foto.
 */
class VehicleDocumentAttachment extends Model
{
    use AttachedFile;

    /** @use HasFactory<VehicleDocumentAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'size',
    ];

    /**
     * @return BelongsTo<VehicleDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(VehicleDocument::class, 'vehicle_document_id');
    }

    /**
     * Quién lo subió (dueño o alguien con el auto compartido).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
