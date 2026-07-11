<?php

namespace App\Models;

use App\Models\Concerns\AttachedFile;
use Database\Factories\HealthAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un archivo adjunto a una historia clínica: receta, orden, estudio, certificado,
 * en PDF o como foto. Puede colgar de una entrada del timeline o estar suelto
 * en la historia.
 */
class HealthAttachment extends Model
{
    use AttachedFile;

    /** @use HasFactory<HealthAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'size',
    ];

    /**
     * @return BelongsTo<HealthRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'health_record_id');
    }

    /**
     * La entrada del timeline de la que cuelga, si no está suelto en la historia.
     *
     * @return BelongsTo<HealthEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(HealthEntry::class, 'health_entry_id');
    }

    /**
     * Quién lo subió (dueño o alguien con la historia compartida).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
