<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Comportamiento común de los modelos de archivo adjunto (Salud, Auto…):
 * un archivo en el disk por defecto con `disk`, `path`, `original_name` y
 * `size`, que se sirve por una ruta autenticada y cae junto con la fila.
 */
trait AttachedFile
{
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

    public static function bootAttachedFile(): void
    {
        // La fila cae con el delete; el archivo hay que sacarlo del disco a mano.
        static::deleted(function (self $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
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
