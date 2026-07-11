<?php

use App\Models\Vehicle;
use App\Models\VehicleDocumentAttachment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Documentación con vencimiento del auto (seguro, VTV, patente…), con sus
 * renovaciones y archivos adjuntos. Cualquier persona con acceso al auto
 * la opera.
 */
new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $vehicleId;

    /** Caché de requireVehicle() dentro del mismo request. */
    private ?Vehicle $requiredVehicle = null;

    // Alta de un documento.
    public bool $addingDocument = false;
    public string $docName = '';
    public string $docExpiresOn = '';
    public string $docNote = '';
    public ?int $docIntervalMonths = null;

    // Edición de un documento ya guardado.
    public ?int $editingDocumentId = null;
    public string $editDocName = '';
    public string $editDocExpiresOn = '';
    public string $editDocNote = '';
    public ?int $editDocIntervalMonths = null;

    // Renovación de un documento (conserva la vigencia anterior).
    public ?int $renewingDocumentId = null;
    public string $renewDocExpiresOn = '';

    // Adjunto (PDF o imagen) recién elegido, por documento. Se guarda apenas
    // termina de subir; van de a uno: el driver S3 de las subidas temporales
    // de Livewire no soporta [multiple].
    public array $docFiles = [];

    // Historial de vigencias anteriores desplegado.
    public ?int $docHistoryId = null;

    public function addDocument(): void
    {
        $vehicle = $this->requireVehicle();

        $data = $this->validate([
            'docName' => ['required', 'string', 'max:60'],
            'docExpiresOn' => ['required', 'date'],
            'docNote' => ['nullable', 'string', 'max:255'],
            'docIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
        ], [
            'docName.required' => '¿Qué documento querés seguir?',
            'docExpiresOn.required' => '¿Cuándo vence?',
        ]);

        $document = $vehicle->documents()->make([
            'name' => trim($data['docName']),
            'expires_on' => $data['docExpiresOn'],
            'note' => trim($this->docNote) === '' ? null : trim($this->docNote),
            'interval_months' => $data['docIntervalMonths'],
        ]);
        $document->user_id = auth()->id();
        $document->save();

        $this->reset('docName', 'docExpiresOn', 'docNote', 'docIntervalMonths', 'addingDocument');
    }

    public function startEditingDocument(int $id): void
    {
        $document = $this->requireVehicle()->documents()->findOrFail($id);

        $this->editingDocumentId = $document->id;
        $this->editDocName = $document->name;
        $this->editDocExpiresOn = $document->expires_on->format('Y-m-d');
        $this->editDocNote = (string) $document->note;
        $this->editDocIntervalMonths = $document->interval_months;
        $this->resetValidation();
    }

    public function saveDocument(): void
    {
        $document = $this->requireVehicle()->documents()->findOrFail($this->editingDocumentId);

        $data = $this->validate([
            'editDocName' => ['required', 'string', 'max:60'],
            'editDocExpiresOn' => ['required', 'date'],
            'editDocNote' => ['nullable', 'string', 'max:255'],
            'editDocIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
        ], [
            'editDocName.required' => '¿Qué documento querés seguir?',
            'editDocExpiresOn.required' => '¿Cuándo vence?',
        ]);

        $document->update([
            'name' => trim($data['editDocName']),
            'expires_on' => $data['editDocExpiresOn'],
            'note' => trim($this->editDocNote) === '' ? null : trim($this->editDocNote),
            'interval_months' => $data['editDocIntervalMonths'],
        ]);

        $this->cancelEditDocument();
    }

    public function cancelEditDocument(): void
    {
        $this->reset('editingDocumentId', 'editDocName', 'editDocExpiresOn', 'editDocNote', 'editDocIntervalMonths');
        $this->resetValidation();
    }

    /**
     * Renovar un documento: la vigencia que vence queda guardada como
     * historial y el documento pasa a la fecha nueva. Si el documento tiene
     * periodicidad, la fecha sugerida ya viene calculada.
     */
    public function startRenewingDocument(int $id): void
    {
        $document = $this->requireVehicle()->documents()->findOrFail($id);

        $this->renewingDocumentId = $document->id;
        $this->renewDocExpiresOn = $document->suggestedNextExpiry()?->format('Y-m-d') ?? '';
        $this->resetValidation();
    }

    public function saveRenewal(): void
    {
        $document = $this->requireVehicle()->documents()->findOrFail($this->renewingDocumentId);

        $this->validate([
            'renewDocExpiresOn' => ['required', 'date'],
        ], [
            'renewDocExpiresOn.required' => '¿Hasta cuándo vale ahora?',
        ]);

        $renewal = $document->renewals()->make([
            'expires_on' => $document->expires_on->format('Y-m-d'),
        ]);
        $renewal->user_id = auth()->id();
        $renewal->save();

        $document->update(['expires_on' => $this->renewDocExpiresOn]);

        $this->reset('renewingDocumentId', 'renewDocExpiresOn');
    }

    public function cancelRenewal(): void
    {
        $this->reset('renewingDocumentId', 'renewDocExpiresOn');
        $this->resetValidation();
    }

    public function toggleDocHistory(int $id): void
    {
        // Acordeón: abrir un historial cierra el que estuviera abierto.
        $this->docHistoryId = $this->docHistoryId === $id ? null : $id;
    }

    public function deleteDocument(int $id): void
    {
        $this->requireVehicle()->documents()->findOrFail($id)->delete();

        if ($this->editingDocumentId === $id) {
            $this->cancelEditDocument();
        }

        if ($this->renewingDocumentId === $id) {
            $this->cancelRenewal();
        }

        if ($this->docHistoryId === $id) {
            $this->docHistoryId = null;
        }
    }

    // --- Adjuntos de la documentación ---------------------------------------

    /**
     * El adjunto se guarda apenas termina de subir, sin formulario aparte.
     * Cada documento tiene su propio input, atado a `docFiles.{id}`.
     */
    public function updatedDocFiles($file, string $key): void
    {
        if ($file === null) {
            return;
        }

        $document = $this->requireVehicle()->documents()->findOrFail((int) $key);

        try {
            $this->validate(
                ['docFiles.'.$key => $this->fileRules()],
                $this->fileMessages('docFiles.'.$key),
            );
        } catch (ValidationException $exception) {
            // Limpio el archivo rechazado para que se pueda volver a intentar.
            unset($this->docFiles[$key]);

            throw $exception;
        }

        // Nombre y tamaño se leen antes de store(): en el mismo disk,
        // Livewire mueve el archivo temporal y después ya no está.
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();

        $attachment = $document->attachments()->make([
            'disk' => config('filesystems.default'),
            'path' => $file->store('auto/'.$document->vehicle_id),
            'original_name' => $originalName,
            'size' => $size,
        ]);
        $attachment->user_id = auth()->id();
        $attachment->save();

        unset($this->docFiles[$key]);
    }

    public function deleteDocumentAttachment(int $id): void
    {
        // Cualquiera con acceso al auto; el modelo borra también el archivo.
        $this->requireVehicle()->documentAttachments()->findOrFail($id)->delete();
    }

    /**
     * PDF o imagen de hasta 10 MB. `mimes` valida el contenido real y
     * `extensions` que el nombre tenga una extensión del mapa del modelo,
     * que es de donde sale el Content-Type al descargar.
     */
    private function fileRules(): array
    {
        $extensions = implode(',', array_keys(VehicleDocumentAttachment::MIME_TYPES));

        return ['file', 'mimes:'.$extensions, 'extensions:'.$extensions, 'max:10240'];
    }

    private function fileMessages(string $property): array
    {
        return [
            $property.'.mimes' => 'Solo puedo guardar PDF o imágenes (JPG, PNG, WebP o HEIC).',
            $property.'.extensions' => 'Solo puedo guardar PDF o imágenes (JPG, PNG, WebP o HEIC).',
            $property.'.max' => 'Ese archivo pesa más de 10 MB y no lo puedo guardar.',
        ];
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * Auto al que el usuario tiene acceso (propio o compartido). Memoizado
     * por request: varias acciones lo resuelven en el mismo render.
     */
    private function requireVehicle(): Vehicle
    {
        return $this->requiredVehicle ??= auth()->user()->accessibleVehicles()->findOrFail($this->vehicleId);
    }

    #[Computed]
    public function vehicle(): ?Vehicle
    {
        return auth()->user()->accessibleVehicles()->find($this->vehicleId);
    }

    /**
     * Con el auto compartido (dueño + al menos una persona más) mostramos
     * quién anotó cada registro; en un auto de una sola persona es ruido.
     */
    #[Computed]
    public function isShared(): bool
    {
        return $this->vehicle !== null && $this->vehicle->members()->exists();
    }

    /**
     * Documentación del auto, ordenada por urgencia (lo vencido y lo que se
     * viene primero), con su estado calculado contra la fecha de hoy.
     */
    #[Computed]
    public function documents(): Collection
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return collect();
        }

        return $vehicle->documents()
            ->with(['user', 'renewals.user', 'attachments'])
            ->get()
            ->map(fn ($document) => [
                'doc' => $document,
                'status' => $document->status(),
            ])
            ->sortBy(fn ($row) => [$row['status']['rank'], $row['status']['urgency']])
            ->values();
    }
};
?>

{{-- Documentación --}}
<div class="space-y-3">
    <div>
        <h2 class="font-brand text-lg font-bold">Documentación</h2>
        <p class="text-sm text-cuero/60">Seguro, VTV, patente y lo que venza con fecha. Te aviso cuando se acerca.</p>
    </div>

    @if ($this->documents->isEmpty())
        <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
            Todavía no cargaste ningún documento. Anotá el seguro o la VTV con su vencimiento y no se te pasa.
        </p>
    @else
        <ul class="space-y-2">
            @foreach ($this->documents as $row)
                @php($doc = $row['doc'])
                @php($status = $row['status'])
                <li wire:key="doc-{{ $doc->id }}" class="rounded-sm border border-cuero/20 p-3">
                    @if ($this->editingDocumentId === $doc->id)
                        <form wire:submit="saveDocument" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label for="editDocName-{{ $doc->id }}" class="mb-1 block text-sm font-medium">Documento</label>
                                    <input id="editDocName-{{ $doc->id }}" type="text" wire:model="editDocName" autocomplete="off" list="documentos-comunes"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editDocName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-ui.date-field model="editDocExpiresOn" id="editDocExpiresOn-{{ $doc->id }}" label="Vence" accent="grafito" preset="vencimiento" />
                                </div>
                                <div>
                                    <label for="editDocIntervalMonths-{{ $doc->id }}" class="mb-1 block text-sm font-medium">Se renueva cada <span class="font-normal text-cuero/60">(meses, opcional)</span></label>
                                    <input id="editDocIntervalMonths-{{ $doc->id }}" type="number" inputmode="numeric" min="1" wire:model="editDocIntervalMonths"
                                        placeholder="12"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editDocIntervalMonths') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div>
                                <label for="editDocNote-{{ $doc->id }}" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                                <input id="editDocNote-{{ $doc->id }}" type="text" wire:model="editDocNote" autocomplete="off"
                                    placeholder="Compañía, número de póliza…"
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @error('editDocNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                    Guardar
                                </button>
                                <button type="button" wire:click="cancelEditDocument"
                                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ $doc->name }}</span>
                                    <span @class([
                                        'rounded-sm px-2 py-0.5 text-xs font-semibold',
                                        'bg-teja text-crema' => $status['level'] === 'overdue',
                                        'bg-ocre text-negro' => $status['level'] === 'soon',
                                        'bg-yerba text-crema' => $status['level'] === 'ok',
                                    ])>
                                        {{ $status['headline'] }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-cuero/70">{{ $status['detail'] }}</p>
                                @if ($doc->note)
                                    <p class="mt-1 text-xs text-cuero/60">{{ $doc->note }}</p>
                                @endif
                                @if ($doc->interval_months)
                                    <p class="mt-1 text-xs text-cuero/50">Se renueva cada {{ $doc->interval_months }} {{ $doc->interval_months === 1 ? 'mes' : 'meses' }}.</p>
                                @endif
                                @if ($this->isShared)
                                    <p class="mt-1 text-xs text-cuero/50">Anotó {{ $doc->user->name }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center">
                                <button type="button" wire:click="startEditingDocument({{ $doc->id }})"
                                    aria-label="Editar {{ $doc->name }}"
                                    class="grid size-9 place-items-center text-cuero/50 hover:text-grafito focus-visible:outline-2 focus-visible:outline-grafito">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                        <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                    </svg>
                                </button>
                                <button type="button" wire:click="deleteDocument({{ $doc->id }})"
                                    wire:confirm="Vas a eliminar «{{ $doc->name }}»{{ $doc->renewals->isNotEmpty() ? ' y sus vigencias anteriores' : '' }}. Esto no se puede deshacer."
                                    aria-label="Eliminar {{ $doc->name }}"
                                    class="grid size-9 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        @if ($this->renewingDocumentId === $doc->id)
                            <form wire:submit="saveRenewal" class="mt-3 space-y-3 border-t border-cuero/15 pt-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-ui.date-field model="renewDocExpiresOn" id="renewDocExpiresOn-{{ $doc->id }}" label="Nuevo vencimiento" accent="grafito" preset="vencimiento" />
                                        @error('renewDocExpiresOn') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                @if ($doc->interval_months)
                                    <p class="text-xs text-cuero/60">Te sugerí la fecha según su periodicidad de {{ $doc->interval_months }} {{ $doc->interval_months === 1 ? 'mes' : 'meses' }}. Cambiala si no coincide.</p>
                                @endif
                                <p class="text-xs text-cuero/60">La vigencia hasta el {{ $doc->expires_on->format('d/m/Y') }} queda guardada en el historial.</p>
                                <div class="flex gap-2">
                                    <button type="submit"
                                        class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                        Guardar
                                    </button>
                                    <button type="button" wire:click="cancelRenewal"
                                        class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                </div>
                            </form>
                        @else
                            <button type="button" wire:click="startRenewingDocument({{ $doc->id }})"
                                class="mt-3 min-h-11 w-full rounded-sm border border-grafito/40 px-3 text-sm font-medium text-grafito hover:bg-grafito/5 focus-visible:outline-2 focus-visible:outline-grafito sm:w-auto">
                                Lo renové
                            </button>
                        @endif

                        @if ($doc->renewals->isNotEmpty())
                            <button type="button" wire:click="toggleDocHistory({{ $doc->id }})"
                                aria-expanded="{{ $this->docHistoryId === $doc->id ? 'true' : 'false' }}"
                                class="mt-3 flex min-h-11 items-center gap-1 text-sm text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                    class="size-4 transition-transform {{ $this->docHistoryId === $doc->id ? 'rotate-90' : '' }}">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                </svg>
                                {{ $this->docHistoryId === $doc->id ? 'Ocultar vigencias anteriores' : 'Ver vigencias anteriores' }}
                            </button>

                            @if ($this->docHistoryId === $doc->id)
                                <ul class="mt-2 divide-y divide-cuero/15 border-y border-cuero/15">
                                    @foreach ($doc->renewals as $renewal)
                                        <li wire:key="renewal-{{ $renewal->id }}" class="py-2">
                                            <p class="text-sm">Venció el <span class="font-medium">{{ $renewal->expires_on->format('d/m/Y') }}</span></p>
                                            @if ($this->isShared)
                                                <p class="text-xs text-cuero/50">Renovó {{ $renewal->user->name }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif

                        {{-- Adjuntos del documento: la póliza, la oblea, en PDF o foto --}}
                        <div class="mt-3 border-t border-cuero/15 pt-3" x-data="{ falloSubida: false }">
                            @if ($doc->attachments->isNotEmpty())
                                <ul class="mb-2 space-y-1">
                                    @foreach ($doc->attachments as $attachment)
                                        <li wire:key="doc-attachment-{{ $attachment->id }}" class="flex items-center gap-2">
                                            <a href="{{ route('auto.adjunto', $attachment) }}" download="{{ $attachment->original_name }}"
                                                class="flex min-w-0 flex-1 items-center gap-2 rounded-sm text-cuero hover:text-grafito focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-grafito">
                                                @if ($attachment->isImage())
                                                    {{-- Heroicon: photo (solid mini) --}}
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 shrink-0 text-cuero/60">
                                                        <path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 0 1 3.25 3h13.5A2.25 2.25 0 0 1 19 5.25v9.5A2.25 2.25 0 0 1 16.75 17H3.25A2.25 2.25 0 0 1 1 14.75v-9.5Zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 0 0 .75-.75v-2.69l-2.22-2.219a.75.75 0 0 0-1.06 0l-1.91 1.909.47.47a.75.75 0 1 1-1.06 1.06L6.53 8.091a.75.75 0 0 0-1.06 0l-2.97 2.97ZM12 7a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" clip-rule="evenodd" />
                                                    </svg>
                                                @else
                                                    {{-- Heroicon: paper-clip (solid mini) --}}
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 shrink-0 text-cuero/60">
                                                        <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 0 0-4.242 0l-7 7a3 3 0 0 0 4.241 4.243h.001l.497-.5a.75.75 0 0 1 1.064 1.057l-.498.501-.002.002a4.5 4.5 0 0 1-6.364-6.364l7-7a4.5 4.5 0 0 1 6.368 6.36l-3.455 3.553A2.625 2.625 0 1 1 9.52 9.52l3.45-3.451a.75.75 0 1 1 1.061 1.06l-3.45 3.451a1.125 1.125 0 0 0 1.587 1.595l3.454-3.553a3 3 0 0 0 0-4.242Z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                                <span class="min-w-0 flex-1 truncate text-sm">{{ $attachment->original_name }}</span>
                                                <span class="shrink-0 text-xs text-cuero/50">{{ $attachment->sizeLabel() }}</span>
                                            </a>
                                            <button type="button" wire:click="deleteDocumentAttachment({{ $attachment->id }})"
                                                wire:confirm="Vas a eliminar {{ $attachment->original_name }}. Esto no se puede deshacer."
                                                aria-label="Eliminar {{ $attachment->original_name }}"
                                                class="grid size-11 shrink-0 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <label class="inline-block">
                                <span class="sr-only">Adjuntar archivo a {{ $doc->name }}</span>
                                <input type="file" wire:model="docFiles.{{ $doc->id }}" accept="application/pdf,image/jpeg,image/png,image/webp,image/heic,.pdf,.jpg,.jpeg,.png,.webp,.heic" class="peer sr-only"
                                    x-on:livewire-upload-start="falloSubida = false" x-on:livewire-upload-error="falloSubida = true">
                                <span class="inline-grid min-h-11 cursor-pointer place-items-center rounded-sm border border-cuero/30 px-4 text-sm text-cuero/80 hover:text-cuero peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-grafito">
                                    Adjuntar PDF o foto
                                </span>
                            </label>
                            <p wire:loading wire:target="docFiles.{{ $doc->id }}" class="mt-1 text-sm text-cuero/60" role="status">Subiendo…</p>
                            <p x-cloak x-show="falloSubida" class="mt-1 text-sm text-teja" role="alert">No pude subir ese archivo. Puede que sea muy pesado; probá con uno más liviano.</p>
                            @error('docFiles.'.$doc->id) <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Agregar documento --}}
    @if ($this->addingDocument)
        <form wire:submit="addDocument" class="space-y-3 rounded-sm border border-cuero/20 p-3">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="docName" class="mb-1 block text-sm font-medium">Documento</label>
                    <input id="docName" type="text" wire:model="docName" autocomplete="off" list="documentos-comunes"
                        placeholder="Seguro, VTV, patente…"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    <datalist id="documentos-comunes">
                        <option value="Seguro"></option>
                        <option value="VTV"></option>
                        <option value="Patente"></option>
                    </datalist>
                    @error('docName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-ui.date-field model="docExpiresOn" label="Vence" accent="grafito" preset="vencimiento" />
                </div>
                <div>
                    <label for="docIntervalMonths" class="mb-1 block text-sm font-medium">Se renueva cada <span class="font-normal text-cuero/60">(meses, opcional)</span></label>
                    <input id="docIntervalMonths" type="number" inputmode="numeric" min="1" wire:model="docIntervalMonths"
                        placeholder="12"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    <p class="mt-1 text-xs text-cuero/60">Con esto te sugiero la próxima fecha al renovarlo.</p>
                    @error('docIntervalMonths') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="docNote" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                <input id="docNote" type="text" wire:model="docNote" autocomplete="off"
                    placeholder="Compañía, número de póliza…"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                @error('docNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="submit"
                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                    Agregar
                </button>
                <button type="button" wire:click="$set('addingDocument', false)"
                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
            </div>
        </form>
    @else
        <button type="button" wire:click="$set('addingDocument', true)"
            class="min-h-11 w-full rounded-sm border border-dashed border-cuero/40 px-3 text-sm font-medium text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
            + Agregar documento
        </button>
    @endif
</div>
