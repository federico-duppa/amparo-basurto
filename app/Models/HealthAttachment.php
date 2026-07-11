<?php

namespace App\Models;

use Database\Factories\HealthAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un archivo adjunto a una historia clínica: receta, orden, estudio, certificado,
 * en PDF o como foto. Puede colgar de una entrada del timeline o estar suelto
 * en la historia.
 */
class HealthAttachment extends Model
{
    /** @use HasFactory<HealthAttachmentFactory> */
    use HasFactory;

    /**
     * Extensiones aceptadas y el Content-Type con el que se sirve cada una.
     * La validación de subida exige que la extensión esté acá (reglas `mimes`
     * + `extensions`), así la descarga siempre sabe qué tipo entregar.
     */
    public const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
    ];

    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'size',
    ];

    protected static function booted(): void
    {
        // La fila cae con el delete; el archivo hay que sacarlo del disco a mano.
        static::deleted(function (HealthAttachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

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

    /**
     * Content-Type según la extensión del nombre original. Los adjuntos
     * anteriores a las imágenes no pasaron por la regla `extensions`, pero
     * son todos PDF, que es justo el default.
     */
    public function mimeType(): string
    {
        $extension = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));

        return self::MIME_TYPES[$extension] ?? 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType(), 'image/');
    }

    /** Tamaño legible para la interfaz, en formato es-AR ("1,2 MB", "340 KB"). */
    public function sizeLabel(): string
    {
        if ($this->size >= 1024 * 1024) {
            return number_format($this->size / (1024 * 1024), 1, ',', '.').' MB';
        }

        return max(1, (int) round($this->size / 1024)).' KB';
    }
}
