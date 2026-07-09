<?php

namespace App\Http\Controllers;

use App\Models\HealthAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HealthAttachmentController extends Controller
{
    /**
     * Descarga un adjunto de Salud. Solo para quien tiene acceso a la historia
     * (dueño o compartida); lo ajeno responde 404, ni siquiera confirma que existe.
     *
     * Siempre streamea desde el disk con disposición de descarga, sin redirigir
     * a una URL firmada externa: en la PWA instalada esa navegación fuera del
     * scope abre otro contexto y "volver" saca al usuario de la app.
     */
    public function __invoke(HealthAttachment $attachment): StreamedResponse
    {
        auth()->user()->accessibleHealthRecords()->findOrFail($attachment->health_record_id);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
