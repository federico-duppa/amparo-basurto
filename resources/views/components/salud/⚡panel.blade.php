<?php

use App\Livewire\Concerns\SharesWithMembers;
use App\Models\HealthAttachment;
use App\Models\HealthEntry;
use App\Models\HealthRecord;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Salud')] class extends Component
{
    use SharesWithMembers;
    use WithFileUploads;

    /** Entradas del timeline visibles por página; "Ver más" agranda la ventana. */
    private const ENTRIES_PAGE = 20;

    // Historia seleccionada (null hasta que exista alguna).
    public ?int $recordId = null;

    /** Caché de requireRecord() dentro del mismo request. */
    private ?HealthRecord $requiredRecord = null;

    // Alta de una historia clínica.
    public bool $addingRecord = false;
    public string $newTipo = 'persona';
    public string $newTitular = '';
    public string $newNacimiento = '';

    // Edición del titular (solo el dueño).
    public bool $editingRecord = false;
    public string $editTipo = 'persona';
    public string $editTitular = '';
    public string $editNacimiento = '';

    // Edición de la ficha médica (cualquiera con acceso).
    public bool $editingFicha = false;
    public string $fichaEspecie = '';
    public string $fichaRaza = '';
    public string $fichaGrupo = '';
    public string $fichaObraSocial = '';
    public string $fichaAlergias = '';
    public string $fichaCondiciones = '';
    public string $fichaMedicacion = '';

    // Compartir la historia con otra persona (solo el dueño).
    public string $shareUsername = '';

    // Alta de una entrada del timeline.
    public bool $addingEntry = false;
    public string $entryDate = '';
    public string $entryType = 'consulta';
    public string $entryTitle = '';
    public string $entryDetail = '';

    // Edición de una entrada ya guardada.
    public ?int $editingEntryId = null;
    public string $editEntryDate = '';
    public string $editEntryType = 'consulta';
    public string $editEntryTitle = '';
    public string $editEntryDetail = '';

    // Archivo (PDF o imagen) recién elegido en cada input. Van de a uno: el
    // driver S3 de las subidas temporales de Livewire no soporta [multiple].
    public $recordFile = null;
    public $entryFile = null;
    public $editEntryFile = null;

    // Tandas acumuladas de a un archivo, que se guardan junto con la entrada.
    public array $entryFiles = [];
    public array $editEntryFiles = [];

    // Filtros del timeline.
    public string $search = '';
    public string $filterType = '';

    // Ventana visible del timeline.
    public int $entriesLimit = self::ENTRIES_PAGE;

    public function mount(): void
    {
        $this->entryDate = now()->format('Y-m-d');
        $this->recordId = auth()->user()->accessibleHealthRecords()->min('health_records.id');
    }

    // --- Historia -----------------------------------------------------------

    public function createRecord(): void
    {
        $data = $this->validate([
            'newTipo' => ['required', Rule::in(array_keys(HealthRecord::TIPOS))],
            'newTitular' => ['required', 'string', 'max:80'],
            'newNacimiento' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'newTitular.required' => 'Contame de quién es la historia.',
            'newNacimiento.before_or_equal' => 'Esa fecha de nacimiento todavía no llegó.',
        ]);

        // Un documento no tiene fecha de nacimiento: si quedó cargada, la ignoro.
        $nacimiento = $data['newTipo'] === 'documento' ? null : ($data['newNacimiento'] ?: null);

        $record = auth()->user()->healthRecords()->create([
            'tipo' => $data['newTipo'],
            'titular' => trim($data['newTitular']),
            'nacimiento' => $nacimiento,
        ]);

        $this->reset('newTipo', 'newTitular', 'newNacimiento', 'addingRecord');
        $this->recordId = $record->id;
    }

    public function selectRecord(int $id): void
    {
        auth()->user()->accessibleHealthRecords()->findOrFail($id);

        $this->recordId = $id;
        $this->reset('editingRecord', 'editingFicha', 'addingEntry', 'editingEntryId', 'addingRecord', 'search', 'filterType', 'entriesLimit', 'recordFile', 'entryFile', 'editEntryFile', 'entryFiles', 'editEntryFiles');
    }

    public function startEditingRecord(): void
    {
        $record = $this->requireOwnedRecord();

        $this->editTipo = $record->tipo;
        $this->editTitular = $record->titular;
        $this->editNacimiento = $record->nacimiento?->format('Y-m-d') ?? '';
        $this->editingRecord = true;
        $this->resetValidation();
    }

    public function saveRecord(): void
    {
        $record = $this->requireOwnedRecord();

        $data = $this->validate([
            'editTipo' => ['required', Rule::in(array_keys(HealthRecord::TIPOS))],
            'editTitular' => ['required', 'string', 'max:80'],
            'editNacimiento' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'editTitular.required' => 'Contame de quién es la historia.',
            'editNacimiento.before_or_equal' => 'Esa fecha de nacimiento todavía no llegó.',
        ]);

        $nacimiento = $data['editTipo'] === 'documento' ? null : ($data['editNacimiento'] ?: null);

        $record->update([
            'tipo' => $data['editTipo'],
            'titular' => trim($data['editTitular']),
            'nacimiento' => $nacimiento,
        ]);

        $this->editingRecord = false;
    }

    public function deleteRecord(int $id): void
    {
        // Solo el dueño puede eliminar la historia (borra también sus entradas).
        $record = auth()->user()->healthRecords()->findOrFail($id);
        $record->delete();

        $this->recordId = auth()->user()->accessibleHealthRecords()->min('health_records.id');
        $this->reset('editingRecord', 'editingFicha', 'addingEntry', 'editingEntryId', 'addingRecord', 'search', 'filterType', 'entriesLimit', 'recordFile', 'entryFile', 'editEntryFile', 'entryFiles', 'editEntryFiles');
    }

    // --- Ficha médica ---------------------------------------------------------

    public function startEditingFicha(): void
    {
        $record = $this->requireRecord();

        $this->fichaEspecie = (string) $record->especie;
        $this->fichaRaza = (string) $record->raza;
        $this->fichaGrupo = (string) $record->grupo_sanguineo;
        $this->fichaObraSocial = (string) $record->obra_social;
        $this->fichaAlergias = (string) $record->alergias;
        $this->fichaCondiciones = (string) $record->condiciones;
        $this->fichaMedicacion = (string) $record->medicacion;
        $this->editingFicha = true;
        $this->resetValidation();
    }

    public function saveFicha(): void
    {
        $record = $this->requireRecord();

        $data = $this->validate([
            'fichaEspecie' => ['nullable', 'string', 'max:60'],
            'fichaRaza' => ['nullable', 'string', 'max:60'],
            'fichaGrupo' => ['nullable', 'string', 'max:10'],
            'fichaObraSocial' => ['nullable', 'string', 'max:120'],
            'fichaAlergias' => ['nullable', 'string', 'max:1000'],
            'fichaCondiciones' => ['nullable', 'string', 'max:1000'],
            'fichaMedicacion' => ['nullable', 'string', 'max:1000'],
        ]);

        // Cada tipo edita solo los campos que le corresponden; los demás no se
        // muestran en el formulario y conservan su valor (para una persona,
        // especie y raza quedan en null; para un documento, no hay cobertura).
        $especie = $record->esMascota() ? (trim($data['fichaEspecie'] ?? '') ?: null) : $record->especie;
        $raza = $record->esMascota() ? (trim($data['fichaRaza'] ?? '') ?: null) : $record->raza;
        $grupo = $record->esPersona() ? (trim($data['fichaGrupo'] ?? '') ?: null) : $record->grupo_sanguineo;
        $obraSocial = $record->esDocumento() ? $record->obra_social : (trim($data['fichaObraSocial'] ?? '') ?: null);

        $record->update([
            'especie' => $especie,
            'raza' => $raza,
            'grupo_sanguineo' => $grupo,
            'obra_social' => $obraSocial,
            'alergias' => trim($data['fichaAlergias'] ?? '') ?: null,
            'condiciones' => trim($data['fichaCondiciones'] ?? '') ?: null,
            'medicacion' => trim($data['fichaMedicacion'] ?? '') ?: null,
        ]);

        $this->editingFicha = false;
    }

    // --- Compartir ------------------------------------------------------------

    protected function shareableOwned(): HealthRecord
    {
        return $this->requireOwnedRecord();
    }

    protected function shareableNoun(): array
    {
        return ['noun' => 'historia', 'genero' => 'f'];
    }

    // --- Entradas del timeline --------------------------------------------------

    public function addEntry(): void
    {
        $record = $this->requireRecord();

        $data = $this->validate([
            'entryDate' => ['required', 'date'],
            'entryType' => ['required', Rule::in(array_keys(HealthEntry::TYPES))],
            'entryTitle' => ['required', 'string', 'max:120'],
            'entryDetail' => ['nullable', 'string', 'max:5000'],
            ...$this->attachmentRules('entryFiles'),
        ], [
            'entryDate.required' => '¿De qué día es?',
            'entryTitle.required' => 'Ponele un título, aunque sea corto.',
            ...$this->attachmentMessages('entryFiles'),
        ]);

        $entry = $record->entries()->make([
            'occurred_on' => $data['entryDate'],
            'type' => $data['entryType'],
            'title' => trim($data['entryTitle']),
            'detail' => trim($this->entryDetail) === '' ? null : trim($this->entryDetail),
        ]);
        $entry->user_id = auth()->id();
        $entry->save();

        $this->storeAttachments($record, $entry, $this->entryFiles);

        $this->reset('entryTitle', 'entryDetail', 'entryFiles', 'addingEntry');
        $this->entryDate = now()->format('Y-m-d');
        $this->entryType = 'consulta';
    }

    public function startEditingEntry(int $id): void
    {
        $entry = $this->requireRecord()->entries()->findOrFail($id);

        $this->editingEntryId = $entry->id;
        $this->editEntryDate = $entry->occurred_on->format('Y-m-d');
        $this->editEntryType = $entry->type;
        $this->editEntryTitle = $entry->title;
        $this->editEntryDetail = (string) $entry->detail;
        $this->editEntryFiles = [];
        $this->resetValidation();
    }

    public function saveEntry(): void
    {
        $record = $this->requireRecord();
        $entry = $record->entries()->findOrFail($this->editingEntryId);

        $data = $this->validate([
            'editEntryDate' => ['required', 'date'],
            'editEntryType' => ['required', Rule::in(array_keys(HealthEntry::TYPES))],
            'editEntryTitle' => ['required', 'string', 'max:120'],
            'editEntryDetail' => ['nullable', 'string', 'max:5000'],
            ...$this->attachmentRules('editEntryFiles'),
        ], [
            'editEntryDate.required' => '¿De qué día es?',
            'editEntryTitle.required' => 'Ponele un título, aunque sea corto.',
            ...$this->attachmentMessages('editEntryFiles'),
        ]);

        $entry->update([
            'occurred_on' => $data['editEntryDate'],
            'type' => $data['editEntryType'],
            'title' => trim($data['editEntryTitle']),
            'detail' => trim($this->editEntryDetail) === '' ? null : trim($this->editEntryDetail),
        ]);

        $this->storeAttachments($record, $entry, $this->editEntryFiles);

        $this->cancelEditEntry();
    }

    public function cancelEditEntry(): void
    {
        $this->reset('editingEntryId', 'editEntryDate', 'editEntryType', 'editEntryTitle', 'editEntryDetail', 'editEntryFiles');
        $this->resetValidation();
    }

    public function deleteEntry(int $id): void
    {
        $this->requireRecord()->entries()->findOrFail($id)->delete();

        if ($this->editingEntryId === $id) {
            $this->cancelEditEntry();
        }
    }

    /** Saca de la tanda un archivo elegido para una entrada nueva, antes de guardar. */
    public function removeEntryFile(int $index): void
    {
        unset($this->entryFiles[$index]);
        $this->entryFiles = array_values($this->entryFiles);
    }

    /** Ídem para la tanda de una entrada en edición. */
    public function removeEditEntryFile(int $index): void
    {
        unset($this->editEntryFiles[$index]);
        $this->editEntryFiles = array_values($this->editEntryFiles);
    }

    // --- Adjuntos de la historia --------------------------------------------------

    /** El archivo suelto se guarda apenas termina de subir, sin formulario aparte. */
    public function updatedRecordFile(): void
    {
        if ($this->recordFile === null) {
            return;
        }

        $record = $this->requireRecord();

        try {
            $this->validate($this->pickRules('recordFile'), $this->pickMessages('recordFile'));
        } catch (ValidationException $exception) {
            // Limpio el archivo rechazado para que se pueda volver a intentar.
            $this->recordFile = null;

            throw $exception;
        }

        $this->storeAttachments($record, null, [$this->recordFile]);
        $this->recordFile = null;
    }

    /** Cada archivo elegido para una entrada se suma a su tanda; se guarda con ella. */
    public function updatedEntryFile(): void
    {
        $this->addToBatch('entryFile', 'entryFiles');
    }

    public function updatedEditEntryFile(): void
    {
        $this->addToBatch('editEntryFile', 'editEntryFiles');
    }

    private function addToBatch(string $property, string $batch): void
    {
        if ($this->{$property} === null) {
            return;
        }

        if (count($this->{$batch}) >= 10) {
            $this->{$property} = null;
            $this->addError($batch, 'Hasta 10 archivos por entrada.');

            return;
        }

        try {
            $this->validate($this->pickRules($property), $this->pickMessages($property));
        } catch (ValidationException $exception) {
            $this->{$property} = null;

            throw $exception;
        }

        $this->{$batch}[] = $this->{$property};
        $this->{$property} = null;
    }

    public function deleteAttachment(int $id): void
    {
        // Cualquiera con acceso a la historia; el modelo borra también el archivo.
        $this->requireRecord()->attachments()->findOrFail($id)->delete();
    }

    private function storeAttachments(HealthRecord $record, ?HealthEntry $entry, array $files): void
    {
        foreach ($files as $file) {
            // Nombre y tamaño se leen antes de store(): en el mismo disk,
            // Livewire mueve el archivo temporal y después ya no está.
            $originalName = $file->getClientOriginalName();
            $size = $file->getSize();

            $attachment = $record->attachments()->make([
                'disk' => config('filesystems.default'),
                'path' => $file->store('salud/'.$record->id),
                'original_name' => $originalName,
                'size' => $size,
            ]);
            $attachment->health_entry_id = $entry?->id;
            $attachment->user_id = auth()->id();
            $attachment->save();
        }
    }

    /**
     * PDF o imagen de hasta 10 MB. `mimes` valida el contenido real y
     * `extensions` que el nombre tenga una extensión del mapa del modelo,
     * que es de donde sale el Content-Type al descargar.
     */
    private function fileRules(): array
    {
        $extensions = implode(',', array_keys(HealthAttachment::MIME_TYPES));

        return ['file', 'mimes:'.$extensions, 'extensions:'.$extensions, 'max:10240'];
    }

    private const FILE_TYPE_MESSAGE = 'Solo puedo guardar PDF o imágenes (JPG, PNG, WebP o HEIC).';

    private function pickRules(string $property): array
    {
        return [$property => $this->fileRules()];
    }

    private function pickMessages(string $property): array
    {
        return [
            $property.'.mimes' => self::FILE_TYPE_MESSAGE,
            $property.'.extensions' => self::FILE_TYPE_MESSAGE,
            $property.'.max' => 'Ese archivo pesa más de 10 MB y no lo puedo guardar.',
        ];
    }

    private function attachmentRules(string $property): array
    {
        return [
            $property => ['array', 'max:10'],
            $property.'.*' => $this->fileRules(),
        ];
    }

    private function attachmentMessages(string $property): array
    {
        return [
            $property.'.max' => 'Hasta 10 archivos por entrada.',
            $property.'.*.mimes' => self::FILE_TYPE_MESSAGE,
            $property.'.*.extensions' => self::FILE_TYPE_MESSAGE,
            $property.'.*.max' => 'Ese archivo pesa más de 10 MB y no lo puedo guardar.',
        ];
    }

    public function filterByType(string $type): void
    {
        $this->filterType = $this->filterType === $type ? '' : $type;
        $this->entriesLimit = self::ENTRIES_PAGE;
    }

    public function updatedSearch(): void
    {
        $this->entriesLimit = self::ENTRIES_PAGE;
    }

    // --- Helpers ----------------------------------------------------------------

    /**
     * Historia a la que el usuario tiene acceso (propia o compartida).
     * Cualquier persona con acceso opera el día a día: ficha y entradas.
     * Memoizado por request: varias acciones lo resuelven en el mismo render.
     */
    private function requireRecord(): HealthRecord
    {
        return $this->requiredRecord ??= auth()->user()->accessibleHealthRecords()->findOrFail($this->recordId);
    }

    /**
     * Historia de la que el usuario es dueño. Editar al titular, eliminar y
     * compartir son acciones reservadas al dueño.
     */
    private function requireOwnedRecord(): HealthRecord
    {
        return auth()->user()->healthRecords()->findOrFail($this->recordId);
    }

    #[Computed]
    public function records(): Collection
    {
        return auth()->user()->accessibleHealthRecords()->with('user')->orderBy('health_records.id')->get();
    }

    #[Computed]
    public function record(): ?HealthRecord
    {
        return $this->records->firstWhere('id', $this->recordId) ?? $this->records->first();
    }

    #[Computed]
    public function isOwner(): bool
    {
        return $this->record !== null && $this->record->user_id === auth()->id();
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->record?->members()->orderBy('name')->get() ?? collect();
    }

    /** Los archivos sueltos de la historia (los de entradas se ven en el timeline). */
    #[Computed]
    public function documents(): Collection
    {
        return $this->record?->attachments()->whereNull('health_entry_id')->with('user')->orderByDesc('id')->get() ?? collect();
    }

    /**
     * Entradas del timeline, de la más reciente a la más vieja, filtradas
     * por tipo y por búsqueda de texto si corresponde. Se muestran de a
     * ENTRIES_PAGE; "Ver más" agranda la ventana.
     */
    #[Computed]
    public function entries(): Collection
    {
        return $this->entriesWindow->take($this->entriesLimit)->values();
    }

    #[Computed]
    public function hasMoreEntries(): bool
    {
        return $this->entriesWindow->count() > $this->entriesLimit;
    }

    public function showMoreEntries(): void
    {
        $this->entriesLimit += self::ENTRIES_PAGE;
    }

    /**
     * La ventana consultada en SQL: con limit+1 alcanza para armar la página
     * y saber si hay más, sin cargar toda la historia en cada render.
     */
    #[Computed]
    public function entriesWindow(): Collection
    {
        $record = $this->record;

        if (! $record) {
            return collect();
        }

        return $record->entries()
            ->with('attachments')
            ->when($this->filterType !== '', fn ($query) => $query->where('type', $this->filterType))
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';

                $query->where(fn ($q) => $q->whereLike('title', $term)->orWhereLike('detail', $term));
            })
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit($this->entriesLimit + 1)
            ->get();
    }

    #[Computed]
    public function isFiltering(): bool
    {
        return $this->filterType !== '' || trim($this->search) !== '';
    }
};
?>

<section class="space-y-6">
    <header class="flex items-center gap-3">
        <span class="h-8 w-1.5 rounded-sm bg-ciruela" aria-hidden="true"></span>
        <h1 class="font-brand text-3xl font-bold">Salud</h1>
    </header>

    @if (! $this->record || $this->addingRecord)
        @if (! $this->record)
            {{-- Sin historias todavía --}}
            <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
                Todavía no armaste ninguna historia clínica. Puede ser tuya, de un familiar, de un paciente o de tu mascota: contame de quién es y empezamos.
            </p>
        @endif

        @php($nombreLabel = match ($newTipo) { 'mascota' => '¿Cómo se llama?', 'documento' => '¿Qué documento es?', default => '¿De quién es?' })
        @php($nombrePlaceholder = match ($newTipo) { 'mascota' => 'Nombre de la mascota', 'documento' => 'Nombre o referencia', default => 'Nombre y apellido' })
        <form wire:submit="createRecord" class="space-y-3 rounded-sm border border-cuero/20 p-4">
            <h2 class="font-brand text-lg font-bold">Nueva historia clínica</h2>

            <fieldset>
                <legend class="mb-1 block text-sm font-medium">¿De quién es la historia?</legend>
                <div class="flex gap-2" role="radiogroup">
                    @foreach (\App\Models\HealthRecord::TIPOS as $value => $label)
                        <label class="flex-1">
                            <input type="radio" wire:model.live="newTipo" value="{{ $value }}" class="peer sr-only">
                            <span class="grid min-h-11 cursor-pointer place-items-center rounded-sm border border-cuero/30 text-sm text-cuero/70 hover:text-cuero peer-checked:border-ciruela peer-checked:bg-ciruela/10 peer-checked:font-semibold peer-checked:text-ciruela peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ciruela">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="newTitular" class="mb-1 block text-sm font-medium">{{ $nombreLabel }}</label>
                    <input id="newTitular" type="text" wire:model="newTitular" autocomplete="off"
                        placeholder="{{ $nombrePlaceholder }}"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newTitular') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                @unless ($newTipo === 'documento')
                    <div>
                        <x-ui.date-field model="newNacimiento" label="Fecha de nacimiento" :optional="true" accent="ciruela" preset="nacimiento" />
                    </div>
                @endunless
            </div>

            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                    Guardar historia
                </button>
                @if ($this->record)
                    <button type="button" wire:click="$set('addingRecord', false)"
                        class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                @endif
            </div>
        </form>
    @endif

    @if ($this->record && ! $this->addingRecord)
        @php($record = $this->record)

        {{-- Selector de historia + alta de otra --}}
        <div class="flex flex-wrap gap-2" role="group" aria-label="Elegí una historia">
            @if ($this->records->count() > 1)
                @foreach ($this->records as $r)
                    <button type="button" wire:click="selectRecord({{ $r->id }})"
                        @if ($r->id === $record->id) aria-current="true" @endif
                        class="min-h-11 rounded-sm border px-3 text-sm {{ $r->id === $record->id ? 'border-ciruela bg-ciruela/10 font-semibold text-ciruela' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }}">
                        {{ $r->titular }}@unless ($r->user_id === auth()->id())<span class="ml-1 text-xs font-normal text-cuero/50">· compartida</span>@endunless
                    </button>
                @endforeach
            @endif
            <button type="button" wire:click="$set('addingRecord', true)"
                class="min-h-11 rounded-sm border border-dashed border-cuero/40 px-3 text-sm text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-ciruela">
                + Nueva historia
            </button>
        </div>

        {{-- Titular --}}
        <div class="rounded-sm border border-cuero/20 p-4">
            @if ($this->editingRecord)
                @php($editNombreLabel = match ($editTipo) { 'mascota' => '¿Cómo se llama?', 'documento' => '¿Qué documento es?', default => '¿De quién es?' })
                <form wire:submit="saveRecord" class="space-y-3">
                    <h2 class="font-brand text-lg font-bold">Editar titular</h2>
                    <fieldset>
                        <legend class="mb-1 block text-sm font-medium">¿De quién es la historia?</legend>
                        <div class="flex gap-2" role="radiogroup">
                            @foreach (\App\Models\HealthRecord::TIPOS as $value => $label)
                                <label class="flex-1">
                                    <input type="radio" wire:model.live="editTipo" value="{{ $value }}" class="peer sr-only">
                                    <span class="grid min-h-11 cursor-pointer place-items-center rounded-sm border border-cuero/30 text-sm text-cuero/70 hover:text-cuero peer-checked:border-ciruela peer-checked:bg-ciruela/10 peer-checked:font-semibold peer-checked:text-ciruela peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ciruela">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="editTitular" class="mb-1 block text-sm font-medium">{{ $editNombreLabel }}</label>
                            <input id="editTitular" type="text" wire:model="editTitular" autocomplete="off"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('editTitular') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                        @unless ($editTipo === 'documento')
                            <div>
                                <x-ui.date-field model="editNacimiento" label="Fecha de nacimiento" :optional="true" accent="ciruela" preset="nacimiento" />
                            </div>
                        @endunless
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('editingRecord', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                    </div>
                </form>
            @else
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-brand text-2xl font-bold leading-tight">{{ $record->titular }}</h2>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="inline-block rounded-sm border border-cuero/30 px-2 py-0.5 text-xs font-medium text-cuero/70">
                                {{ \App\Models\HealthRecord::TIPOS[$record->tipo] ?? 'Persona' }}
                            </span>
                            @if ($record->esMascota() && ($record->especie || $record->raza))
                                <span class="text-xs text-cuero/60">{{ trim($record->especie.' · '.$record->raza, ' ·') }}</span>
                            @endif
                        </div>
                        @if ($record->nacimiento && ! $record->esDocumento())
                            <p class="mt-1 text-sm text-cuero/70">
                                Nació el {{ $record->nacimiento->format('d/m/Y') }}
                                @if ($record->edad() !== null)
                                    · {{ $record->edad() }} {{ $record->edad() === 1 ? 'año' : 'años' }}
                                @endif
                            </p>
                        @endif
                        @unless ($this->isOwner)
                            <p class="mt-1 text-xs text-cuero/60">Compartida por {{ $record->user->name }}</p>
                        @endunless
                    </div>
                    <div class="flex shrink-0 items-center">
                        <a href="{{ route('salud.reporte', $record) }}" aria-label="Reporte para imprimir de {{ $record->titular }}"
                            class="grid size-11 place-items-center text-cuero/60 hover:text-ciruela focus-visible:outline-2 focus-visible:outline-ciruela">
                            {{-- Heroicon: printer (outline) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                            </svg>
                        </a>
                        @if ($this->isOwner)
                            <button type="button" wire:click="startEditingRecord" aria-label="Editar titular"
                                class="grid size-11 place-items-center text-cuero/60 hover:text-cuero focus-visible:outline-2 focus-visible:outline-ciruela">
                                {{-- Heroicon: pencil-square (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                            </button>
                            <button type="button" wire:click="deleteRecord({{ $record->id }})"
                                wire:confirm="Vas a eliminar la historia clínica de {{ $record->titular }} con todas sus entradas y sus adjuntos. Esto no se puede deshacer."
                                aria-label="Eliminar historia"
                                class="grid size-11 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                {{-- Heroicon: trash (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- Ficha médica: lo que dirías en una guardia --}}
        <div class="rounded-sm border border-cuero/20 p-4">
            @if ($this->editingFicha)
                <form wire:submit="saveFicha" class="space-y-3">
                    <h2 class="font-brand text-lg font-bold">Editar ficha</h2>
                    @if ($record->esMascota())
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="fichaEspecie" class="mb-1 block text-sm font-medium">Especie</label>
                                <input id="fichaEspecie" type="text" wire:model="fichaEspecie" autocomplete="off"
                                    placeholder="Perro, gato…"
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @error('fichaEspecie') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="fichaRaza" class="mb-1 block text-sm font-medium">Raza</label>
                                <input id="fichaRaza" type="text" wire:model="fichaRaza" autocomplete="off"
                                    placeholder="Mestizo, labrador…"
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @error('fichaRaza') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label for="fichaObraSocial" class="mb-1 block text-sm font-medium">Veterinaria</label>
                            <input id="fichaObraSocial" type="text" wire:model="fichaObraSocial" autocomplete="off"
                                placeholder="Nombre y datos de contacto"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('fichaObraSocial') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @elseif ($record->esPersona())
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="fichaGrupo" class="mb-1 block text-sm font-medium">Grupo sanguíneo</label>
                                <input id="fichaGrupo" type="text" wire:model="fichaGrupo" autocomplete="off"
                                    placeholder="0+"
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @error('fichaGrupo') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="fichaObraSocial" class="mb-1 block text-sm font-medium">Obra social / prepaga</label>
                                <input id="fichaObraSocial" type="text" wire:model="fichaObraSocial" autocomplete="off"
                                    placeholder="Nombre y n° de afiliado"
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @error('fichaObraSocial') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                    <div>
                        <label for="fichaAlergias" class="mb-1 block text-sm font-medium">Alergias</label>
                        <textarea id="fichaAlergias" wire:model="fichaAlergias" rows="2"
                            placeholder="Penicilina, maní…"
                            class="w-full rounded-sm border border-cuero/30 bg-crema px-3 py-2 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"></textarea>
                        @error('fichaAlergias') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fichaCondiciones" class="mb-1 block text-sm font-medium">Condiciones crónicas</label>
                        <textarea id="fichaCondiciones" wire:model="fichaCondiciones" rows="2"
                            placeholder="Hipertensión, diabetes…"
                            class="w-full rounded-sm border border-cuero/30 bg-crema px-3 py-2 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"></textarea>
                        @error('fichaCondiciones') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fichaMedicacion" class="mb-1 block text-sm font-medium">Medicación actual</label>
                        <textarea id="fichaMedicacion" wire:model="fichaMedicacion" rows="2"
                            placeholder="Droga y dosis, una por línea"
                            class="w-full rounded-sm border border-cuero/30 bg-crema px-3 py-2 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"></textarea>
                        @error('fichaMedicacion') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('editingFicha', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                    </div>
                </form>
            @else
                <div class="flex items-center gap-2">
                    <h2 class="font-brand text-lg font-bold">Ficha</h2>
                    <button type="button" wire:click="startEditingFicha"
                        class="ml-auto min-h-11 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-ciruela">
                        Editar
                    </button>
                </div>

                <dl class="mt-3 space-y-3">
                    @if ($record->esMascota())
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm text-cuero/60">Especie</dt>
                                <dd class="font-medium">{{ $record->especie ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-cuero/60">Raza</dt>
                                <dd class="font-medium">{{ $record->raza ?? '—' }}</dd>
                            </div>
                        </div>
                        <div>
                            <dt class="text-sm text-cuero/60">Veterinaria</dt>
                            <dd class="font-medium">{{ $record->obra_social ?? '—' }}</dd>
                        </div>
                    @elseif ($record->esPersona())
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm text-cuero/60">Grupo sanguíneo</dt>
                                <dd class="font-medium">{{ $record->grupo_sanguineo ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-cuero/60">Obra social / prepaga</dt>
                                <dd class="font-medium">{{ $record->obra_social ?? '—' }}</dd>
                            </div>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm text-cuero/60">Alergias</dt>
                        <dd class="mt-0.5">
                            @if ($record->alergias)
                                <span class="inline-block rounded-sm bg-ocre px-2 py-1 font-medium text-negro whitespace-pre-line">{{ $record->alergias }}</span>
                            @else
                                <span class="font-medium">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-cuero/60">Condiciones crónicas</dt>
                        <dd class="font-medium whitespace-pre-line">{{ $record->condiciones ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-cuero/60">Medicación actual</dt>
                        <dd class="font-medium whitespace-pre-line">{{ $record->medicacion ?? '—' }}</dd>
                    </div>
                </dl>
            @endif
        </div>

        {{-- Vencimientos y recordatorios --}}
        <livewire:salud.vencimientos :record-id="$record->id" :key="'vencimientos-'.$record->id" />

        {{-- Carnet de vacunas --}}
        <livewire:salud.vacunas :record-id="$record->id" :key="'vacunas-'.$record->id" />

        {{-- Mediciones --}}
        <livewire:salud.mediciones :record-id="$record->id" :key="'mediciones-'.$record->id" />

        {{-- Contactos médicos --}}
        <livewire:salud.contactos :record-id="$record->id" :key="'contactos-'.$record->id" />

        {{-- Adjuntos: archivos sueltos de la historia (PDF o imágenes) --}}
        <div class="space-y-3 rounded-sm border border-cuero/20 p-4" x-data="{ falloSubida: false }">
            <div class="flex flex-wrap items-start gap-2">
                <div class="min-w-0 flex-1">
                    <h2 class="font-brand text-lg font-bold">Adjuntos</h2>
                    <p class="text-sm text-cuero/60">Certificados, estudios y otros papeles de la historia, en PDF o como foto. Si es de una consulta puntual, también podés adjuntarlo al anotar la entrada.</p>
                </div>
                <label class="shrink-0">
                    <input type="file" wire:model="recordFile" accept="application/pdf,image/jpeg,image/png,image/webp,image/heic,.pdf,.jpg,.jpeg,.png,.webp,.heic" class="peer sr-only"
                        x-on:livewire-upload-start="falloSubida = false" x-on:livewire-upload-error="falloSubida = true">
                    <span class="grid min-h-11 cursor-pointer place-items-center rounded-sm bg-monte px-4 text-sm font-medium text-crema hover:bg-monte/90 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-monte">
                        Subir archivo
                    </span>
                </label>
            </div>

            <p wire:loading wire:target="recordFile" class="text-sm text-cuero/60" role="status">Subiendo…</p>
            <p x-cloak x-show="falloSubida" class="text-sm text-teja" role="alert">No pude subir ese archivo. Puede que sea muy pesado; probá con uno más liviano.</p>
            @error('recordFile') <p class="text-sm text-teja" role="alert">{{ $message }}</p> @enderror

            @if ($this->documents->isEmpty())
                <p class="text-sm text-cuero/60">Todavía no hay documentos en esta historia. Subí un PDF o una foto y queda guardado acá.</p>
            @else
                <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                    @foreach ($this->documents as $document)
                        <li wire:key="attachment-{{ $document->id }}" class="flex items-center gap-2 py-2">
                            <a href="{{ route('salud.adjunto', $document) }}" download="{{ $document->original_name }}"
                                class="flex min-w-0 flex-1 items-center gap-2 rounded-sm text-cuero hover:text-ciruela focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ciruela">
                                @if ($document->isImage())
                                    {{-- Heroicon: photo (outline) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5 shrink-0 text-cuero/60">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                @else
                                    {{-- Heroicon: paper-clip (outline) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5 shrink-0 text-cuero/60">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                    </svg>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $document->original_name }}</span>
                                    <span class="block text-xs text-cuero/50">{{ $document->created_at->format('d/m/Y') }} · {{ $document->sizeLabel() }} · {{ $document->user->name }}</span>
                                </span>
                            </a>
                            <button type="button" wire:click="deleteAttachment({{ $document->id }})"
                                wire:confirm="Vas a eliminar {{ $document->original_name }}. Esto no se puede deshacer."
                                aria-label="Eliminar {{ $document->original_name }}"
                                class="grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                {{-- Heroicon: trash (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Compartir (solo el dueño) --}}
        @if ($this->isOwner)
            <div class="space-y-3 rounded-sm border border-cuero/20 p-4">
                <div>
                    <h2 class="font-brand text-lg font-bold">Compartir</h2>
                    <p class="text-sm text-cuero/60">Sumá a otra persona con su usuario y verá esta historia completa, con su ficha y sus entradas.</p>
                </div>

                @if ($this->members->isNotEmpty())
                    <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                        @foreach ($this->members as $member)
                            <li wire:key="member-{{ $member->id }}" class="flex items-center gap-2 py-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $member->name }}</p>
                                    <p class="text-xs text-cuero/50">{{ '@'.$member->username }}</p>
                                </div>
                                <button type="button" wire:click="unshare({{ $member->id }})"
                                    wire:confirm="Vas a dejar de compartir la historia con {{ $member->name }}. Ya no va a poder verla."
                                    aria-label="Dejar de compartir con {{ $member->name }}"
                                    class="min-h-11 shrink-0 rounded-sm px-3 text-sm text-cuero/70 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                    Quitar
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form wire:submit="share" class="flex flex-wrap items-end gap-2">
                    <div class="min-w-0 flex-1">
                        <label for="shareUsername" class="mb-1 block text-sm font-medium">Usuario</label>
                        <input id="shareUsername" type="text" wire:model="shareUsername" autocomplete="off" autocapitalize="none"
                            placeholder="usuario"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    </div>
                    <button type="submit" wire:loading.attr="disabled"
                        class="min-h-11 shrink-0 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                        Compartir
                    </button>
                    @error('shareUsername') <p class="w-full text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </form>
            </div>
        @endif

        {{-- Entradas --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <h2 class="font-brand text-lg font-bold">Entradas</h2>
                <button type="button" wire:click="$set('addingEntry', true)"
                    class="ml-auto min-h-11 rounded-sm bg-monte px-4 text-sm font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                    Anotar
                </button>
            </div>

            @if ($this->addingEntry)
                <form wire:submit="addEntry" class="space-y-3 rounded-sm border border-cuero/20 p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <x-ui.date-field model="entryDate" label="Fecha" accent="ciruela" preset="pasado" />
                        </div>
                        <div>
                            <label for="entryType" class="mb-1 block text-sm font-medium">Tipo</label>
                            <select id="entryType" wire:model="entryType"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @foreach (\App\Models\HealthEntry::TYPES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('entryType') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="entryTitle" class="mb-1 block text-sm font-medium">Título</label>
                        <input id="entryTitle" type="text" wire:model="entryTitle" autocomplete="off"
                            placeholder="Control con clínica, análisis de sangre…"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                        @error('entryTitle') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="entryDetail" class="mb-1 block text-sm font-medium">Detalle <span class="font-normal text-cuero/60">(opcional)</span></label>
                        <textarea id="entryDetail" wire:model="entryDetail" rows="3"
                            placeholder="Lo que quieras dejar anotado: indicaciones, resultados, cómo siguió…"
                            class="w-full rounded-sm border border-cuero/30 bg-crema px-3 py-2 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"></textarea>
                        @error('entryDetail') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div x-data="{ falloSubida: false }">
                        <label class="inline-block">
                            <span class="sr-only">Adjuntar archivo a la entrada</span>
                            <input type="file" wire:model="entryFile" accept="application/pdf,image/jpeg,image/png,image/webp,image/heic,.pdf,.jpg,.jpeg,.png,.webp,.heic" class="peer sr-only"
                                x-on:livewire-upload-start="falloSubida = false" x-on:livewire-upload-error="falloSubida = true">
                            <span class="inline-grid min-h-11 cursor-pointer place-items-center rounded-sm border border-cuero/30 px-4 text-sm text-cuero/80 hover:text-cuero peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ciruela">
                                Adjuntar PDF o foto <span class="ml-1 font-normal text-cuero/60">(opcional, de a uno)</span>
                            </span>
                        </label>
                        <p wire:loading wire:target="entryFile" class="mt-1 text-sm text-cuero/60" role="status">Subiendo…</p>
                        <p x-cloak x-show="falloSubida" class="mt-1 text-sm text-teja" role="alert">No pude subir ese archivo. Puede que sea muy pesado; probá con uno más liviano.</p>
                        @if ($entryFiles !== [])
                            <ul class="mt-2 space-y-1">
                                @foreach ($entryFiles as $index => $file)
                                    <li class="flex items-center gap-2 text-sm">
                                        <span class="min-w-0 flex-1 truncate">{{ $file->getClientOriginalName() }}</span>
                                        <button type="button" wire:click="removeEntryFile({{ $index }})"
                                            class="min-h-11 shrink-0 rounded-sm px-2 text-sm text-cuero/70 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                            Quitar
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @error('entryFile') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        @error('entryFiles') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        @error('entryFiles.*') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" wire:loading.attr="disabled"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                            Guardar entrada
                        </button>
                        <button type="button" wire:click="$set('addingEntry', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                    </div>
                </form>
            @endif

            {{-- Filtros: por tipo y por texto --}}
            <div class="space-y-2">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por tipo">
                    @foreach (\App\Models\HealthEntry::TYPES as $value => $label)
                        <button type="button" wire:click="filterByType('{{ $value }}')"
                            @if ($this->filterType === $value) aria-pressed="true" @endif
                            class="min-h-11 rounded-sm border px-3 text-sm {{ $this->filterType === $value ? 'border-ciruela bg-ciruela/10 font-semibold text-ciruela' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                <div>
                    <label for="search" class="sr-only">Buscar en las entradas</label>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search" autocomplete="off"
                        placeholder="Buscar en las entradas…"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                </div>
            </div>

            @if ($this->entries->isEmpty())
                <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70" aria-live="polite">
                    @if ($this->isFiltering)
                        No encontré nada con eso.
                    @else
                        Todavía no anotaste nada en esta historia. Cuando haya una consulta, un estudio o lo que sea, lo sumamos acá.
                    @endif
                </p>
            @else
                <ul class="space-y-2">
                    @foreach ($this->entries as $entry)
                        <li wire:key="entry-{{ $entry->id }}" class="rounded-sm border border-cuero/20 p-3">
                            @if ($this->editingEntryId === $entry->id)
                                <form wire:submit="saveEntry" class="space-y-3">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <x-ui.date-field model="editEntryDate" id="editEntryDate-{{ $entry->id }}" label="Fecha" accent="ciruela" preset="pasado" />
                                        </div>
                                        <div>
                                            <label for="editEntryType-{{ $entry->id }}" class="mb-1 block text-sm font-medium">Tipo</label>
                                            <select id="editEntryType-{{ $entry->id }}" wire:model="editEntryType"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                @foreach (\App\Models\HealthEntry::TYPES as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('editEntryType') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div>
                                        <label for="editEntryTitle-{{ $entry->id }}" class="mb-1 block text-sm font-medium">Título</label>
                                        <input id="editEntryTitle-{{ $entry->id }}" type="text" wire:model="editEntryTitle" autocomplete="off"
                                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                        @error('editEntryTitle') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="editEntryDetail-{{ $entry->id }}" class="mb-1 block text-sm font-medium">Detalle <span class="font-normal text-cuero/60">(opcional)</span></label>
                                        <textarea id="editEntryDetail-{{ $entry->id }}" wire:model="editEntryDetail" rows="3"
                                            class="w-full rounded-sm border border-cuero/30 bg-crema px-3 py-2 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"></textarea>
                                        @error('editEntryDetail') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                    <div x-data="{ falloSubida: false }">
                                        @if ($entry->attachments->isNotEmpty())
                                            <ul class="mb-2 space-y-1">
                                                @foreach ($entry->attachments as $attachment)
                                                    <li wire:key="edit-attachment-{{ $attachment->id }}" class="flex items-center gap-2 text-sm">
                                                        <span class="min-w-0 flex-1 truncate">{{ $attachment->original_name }}</span>
                                                        <button type="button" wire:click="deleteAttachment({{ $attachment->id }})"
                                                            wire:confirm="Vas a eliminar {{ $attachment->original_name }}. Esto no se puede deshacer."
                                                            class="min-h-11 shrink-0 rounded-sm px-2 text-sm text-cuero/70 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                                            Eliminar
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        <label class="inline-block">
                                            <span class="sr-only">Adjuntar archivo a la entrada</span>
                                            <input type="file" wire:model="editEntryFile" accept="application/pdf,image/jpeg,image/png,image/webp,image/heic,.pdf,.jpg,.jpeg,.png,.webp,.heic" class="peer sr-only"
                                                x-on:livewire-upload-start="falloSubida = false" x-on:livewire-upload-error="falloSubida = true">
                                            <span class="inline-grid min-h-11 cursor-pointer place-items-center rounded-sm border border-cuero/30 px-4 text-sm text-cuero/80 hover:text-cuero peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ciruela">
                                                Adjuntar PDF o foto <span class="ml-1 font-normal text-cuero/60">(opcional, de a uno)</span>
                                            </span>
                                        </label>
                                        <p wire:loading wire:target="editEntryFile" class="mt-1 text-sm text-cuero/60" role="status">Subiendo…</p>
                                        <p x-cloak x-show="falloSubida" class="mt-1 text-sm text-teja" role="alert">No pude subir ese archivo. Puede que sea muy pesado; probá con uno más liviano.</p>
                                        @if ($editEntryFiles !== [])
                                            <ul class="mt-2 space-y-1">
                                                @foreach ($editEntryFiles as $index => $file)
                                                    <li class="flex items-center gap-2 text-sm">
                                                        <span class="min-w-0 flex-1 truncate">{{ $file->getClientOriginalName() }}</span>
                                                        <button type="button" wire:click="removeEditEntryFile({{ $index }})"
                                                            class="min-h-11 shrink-0 rounded-sm px-2 text-sm text-cuero/70 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                                            Quitar
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @error('editEntryFile') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        @error('editEntryFiles') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        @error('editEntryFiles.*') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit"
                                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                            Guardar
                                        </button>
                                        <button type="button" wire:click="cancelEditEntry"
                                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                    </div>
                                </form>
                            @else
                                <div class="flex items-start gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-cuero/60">
                                            <span>{{ $entry->occurred_on->format('d/m/Y') }}</span>
                                            <span class="rounded-sm border border-ciruela/40 px-1.5 py-0.5 font-medium text-ciruela">{{ $entry->typeLabel() }}</span>
                                        </p>
                                        <h3 class="mt-1 font-medium">{{ $entry->title }}</h3>
                                        @if ($entry->detail)
                                            <p class="mt-1 text-sm text-cuero/80 whitespace-pre-line">{{ $entry->detail }}</p>
                                        @endif
                                        @if ($entry->attachments->isNotEmpty())
                                            <ul class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($entry->attachments as $attachment)
                                                    <li wire:key="entry-attachment-{{ $attachment->id }}">
                                                        <a href="{{ route('salud.adjunto', $attachment) }}" download="{{ $attachment->original_name }}"
                                                            class="inline-flex min-h-11 items-center gap-1.5 rounded-sm border border-cuero/30 px-2.5 text-sm text-cuero/80 hover:text-ciruela focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ciruela">
                                                            {{-- Heroicon: paper-clip (solid mini) --}}
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 shrink-0 text-cuero/60">
                                                                <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 0 0-4.242 0l-7 7a3 3 0 0 0 4.241 4.243h.001l.497-.5a.75.75 0 0 1 1.064 1.057l-.498.501-.002.002a4.5 4.5 0 0 1-6.364-6.364l7-7a4.5 4.5 0 0 1 6.368 6.36l-3.455 3.553A2.625 2.625 0 1 1 9.52 9.52l3.45-3.451a.75.75 0 1 1 1.061 1.06l-3.45 3.451a1.125 1.125 0 0 0 1.587 1.595l3.454-3.553a3 3 0 0 0 0-4.242Z" clip-rule="evenodd" />
                                                            </svg>
                                                            <span class="max-w-48 truncate">{{ $attachment->original_name }}</span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center">
                                        <button type="button" wire:click="startEditingEntry({{ $entry->id }})" aria-label="Editar entrada"
                                            class="grid size-11 place-items-center text-cuero/60 hover:text-cuero focus-visible:outline-2 focus-visible:outline-ciruela">
                                            {{-- Heroicon: pencil-square (outline) --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                        </button>
                                        <button type="button" wire:click="deleteEntry({{ $entry->id }})"
                                            wire:confirm="Vas a eliminar esta entrada{{ $entry->attachments->isNotEmpty() ? ' con sus adjuntos' : '' }}. Esto no se puede deshacer."
                                            aria-label="Eliminar entrada"
                                            class="grid size-11 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                            {{-- Heroicon: trash (outline) --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($this->hasMoreEntries)
                    <button type="button" wire:click="showMoreEntries" wire:loading.attr="disabled"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 px-4 text-sm font-medium text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ciruela disabled:opacity-60">
                        Ver más entradas
                    </button>
                @endif
            @endif
        </div>
    @endif
</section>
