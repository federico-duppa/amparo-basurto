<?php

namespace App\Http\Controllers;

use App\Models\HealthAttachment;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class HealthAttachmentController extends Controller
{
    /**
     * Sirve un adjunto de Salud. Solo para quien tiene acceso a la historia
     * (dueño o compartida); lo ajeno responde 404, ni siquiera confirma que existe.
     */
    public function __invoke(HealthAttachment $attachment): Response
    {
        auth()->user()->accessibleHealthRecords()->findOrFail($attachment->health_record_id);

        $disk = Storage::disk($attachment->disk);

        // En Cloud el disk es object storage: redirigimos a una URL firmada de
        // corta vida en vez de streamear el archivo a través de la app. El disk
        // local (desarrollo y tests) sirve el archivo directo.
        if (! $disk instanceof LocalFilesystemAdapter && $disk->providesTemporaryUrls()) {
            $filename = str_replace(['"', "\r", "\n"], '', Str::ascii($attachment->original_name));

            return redirect()->away($disk->temporaryUrl($attachment->path, now()->addMinutes(5), [
                'ResponseContentType' => 'application/pdf',
                'ResponseContentDisposition' => 'inline; filename="'.$filename.'"',
            ]));
        }

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
