<?php

namespace App\Http\Controllers;

use App\Models\VehicleDocumentAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleDocumentAttachmentController extends Controller
{
    /**
     * Descarga un adjunto de un documento del auto. Solo para quien tiene
     * acceso al vehículo (dueño o compartido); lo ajeno responde 404, ni
     * siquiera confirma que existe.
     *
     * Siempre streamea desde el disk con disposición de descarga, sin redirigir
     * a una URL firmada externa: en la PWA instalada esa navegación fuera del
     * scope abre otro contexto y "volver" saca al usuario de la app.
     */
    public function __invoke(VehicleDocumentAttachment $attachment): StreamedResponse
    {
        auth()->user()->accessibleVehicles()->findOrFail($attachment->document->vehicle_id);

        // El Content-Type sale del modelo y no del disk: averiguarlo en el
        // object storage costaría una llamada extra por descarga.
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mimeType(),
        ]);
    }
}
