<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Auto')] class extends Component
{
    // Auto seleccionado (null hasta que exista alguno).
    public ?int $vehicleId = null;

    // Alta de auto.
    public string $newMarca = '';
    public string $newModelo = '';
    public string $newPatente = '';
    public ?int $newKilometraje = null;

    // Edición de los datos del auto (solo el dueño).
    public bool $editingVehicle = false;
    public string $editMarca = '';
    public string $editModelo = '';
    public string $editPatente = '';

    // Compartir el auto con otra persona (solo el dueño).
    public string $shareUsername = '';

    // Edición del kilometraje actual.
    public bool $editingKm = false;
    public ?int $kmValue = null;

    // Alta de mantenimiento a seguir.
    public bool $addingItem = false;
    public string $itemName = '';
    public ?int $itemIntervalKm = null;
    public ?int $itemIntervalMonths = null;

    // Edición de un ítem de mantenimiento ya guardado.
    public ?int $editingItemId = null;
    public string $editItemName = '';
    public ?int $editItemIntervalKm = null;
    public ?int $editItemIntervalMonths = null;

    // Registrar que un mantenimiento se hizo.
    public ?int $loggingItemId = null;
    public string $logDate = '';
    public ?int $logMileage = null;
    public ?string $logCost = null;
    public string $logNote = '';

    // Historial de realizaciones desplegado y edición de una realización.
    public ?int $historyItemId = null;
    public ?int $editingRecordId = null;
    public string $editRecordDate = '';
    public ?int $editRecordMileage = null;
    public ?string $editRecordCost = null;
    public string $editRecordNote = '';

    // Carga de combustible.
    public string $fuelDate = '';
    public ?int $fuelMileage = null;
    public ?string $fuelCost = null;

    // Edición de una carga de combustible ya guardada.
    public ?int $editingFuelId = null;
    public string $editFuelDate = '';
    public ?int $editFuelMileage = null;
    public ?string $editFuelCost = null;

    // Documentación con vencimiento (seguro, VTV, patente…).
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

    // Historial de vigencias anteriores desplegado.
    public ?int $docHistoryId = null;

    public function mount(): void
    {
        $this->logDate = now()->format('Y-m-d');
        $this->fuelDate = now()->format('Y-m-d');
        $this->vehicleId = auth()->user()->accessibleVehicles()->min('vehicles.id');
    }

    // --- Auto -------------------------------------------------------------

    public function createVehicle(): void
    {
        $data = $this->validate([
            'newMarca' => ['required', 'string', 'max:60'],
            'newModelo' => ['required', 'string', 'max:60'],
            'newPatente' => ['nullable', 'string', 'max:12'],
            'newKilometraje' => ['required', 'integer', 'min:0', 'max:9999999'],
        ], [
            'newMarca.required' => 'Contame la marca del auto.',
            'newModelo.required' => '¿Qué modelo es?',
            'newKilometraje.required' => 'Necesito el kilometraje para llevarte la cuenta.',
            'newKilometraje.integer' => 'El kilometraje va en números, sin puntos ni letras.',
        ]);

        $vehicle = auth()->user()->vehicles()->create([
            'marca' => trim($data['newMarca']),
            'modelo' => trim($data['newModelo']),
            'patente' => $data['newPatente'] ? strtoupper(trim($data['newPatente'])) : null,
            'kilometraje' => $data['newKilometraje'],
        ]);

        // Le dejo cargados los mantenimientos más comunes para que arranque con algo.
        // Son sugerencias: los podés editar o borrar.
        foreach ([
            ['name' => 'Cambio de aceite', 'interval_km' => 10000, 'interval_months' => 12],
            ['name' => 'Cambio de bujías', 'interval_km' => 40000, 'interval_months' => null],
            ['name' => 'Correa de distribución', 'interval_km' => 60000, 'interval_months' => 60],
        ] as $preset) {
            $this->makeItem($vehicle, $preset['name'], $preset['interval_km'], $preset['interval_months']);
        }

        $this->reset('newMarca', 'newModelo', 'newPatente', 'newKilometraje');
        $this->vehicleId = $vehicle->id;
    }

    public function selectVehicle(int $id): void
    {
        auth()->user()->accessibleVehicles()->findOrFail($id);

        $this->vehicleId = $id;
        $this->reset('editingKm', 'editingVehicle', 'addingItem', 'loggingItemId', 'editingItemId', 'historyItemId', 'editingRecordId', 'editingFuelId', 'addingDocument', 'editingDocumentId', 'renewingDocumentId', 'docHistoryId');
    }

    public function startEditingVehicle(): void
    {
        $vehicle = $this->requireOwnedVehicle();

        $this->editMarca = $vehicle->marca;
        $this->editModelo = $vehicle->modelo;
        $this->editPatente = (string) $vehicle->patente;
        $this->editingVehicle = true;
        $this->resetValidation();
    }

    public function saveVehicle(): void
    {
        $vehicle = $this->requireOwnedVehicle();

        $data = $this->validate([
            'editMarca' => ['required', 'string', 'max:60'],
            'editModelo' => ['required', 'string', 'max:60'],
            'editPatente' => ['nullable', 'string', 'max:12'],
        ], [
            'editMarca.required' => 'Contame la marca del auto.',
            'editModelo.required' => '¿Qué modelo es?',
        ]);

        $vehicle->update([
            'marca' => trim($data['editMarca']),
            'modelo' => trim($data['editModelo']),
            'patente' => $data['editPatente'] ? strtoupper(trim($data['editPatente'])) : null,
        ]);

        $this->editingVehicle = false;
    }

    public function startEditingKm(): void
    {
        $this->kmValue = $this->vehicle?->kilometraje;
        $this->editingKm = true;
    }

    public function saveKm(): void
    {
        $vehicle = $this->requireVehicle();

        $this->validate([
            'kmValue' => ['required', 'integer', 'min:0', 'max:9999999'],
        ], [
            'kmValue.required' => 'Decime cuánto marca el tablero.',
            'kmValue.integer' => 'El kilometraje va en números.',
        ]);

        $vehicle->update(['kilometraje' => $this->kmValue]);
        $this->editingKm = false;
    }

    public function deleteVehicle(int $id): void
    {
        // Solo el dueño puede eliminar el auto (borra también su historial).
        $vehicle = auth()->user()->vehicles()->findOrFail($id);
        $vehicle->delete();

        $this->vehicleId = auth()->user()->accessibleVehicles()->min('vehicles.id');
        $this->reset('editingKm', 'editingVehicle', 'addingItem', 'loggingItemId', 'editingItemId', 'historyItemId', 'editingRecordId', 'editingFuelId', 'addingDocument', 'editingDocumentId', 'renewingDocumentId', 'docHistoryId');
    }

    // --- Compartir --------------------------------------------------------

    public function share(): void
    {
        $vehicle = $this->requireOwnedVehicle();

        $this->shareUsername = strtolower(trim($this->shareUsername));

        $this->validate([
            'shareUsername' => ['required', 'string', 'max:50'],
        ], [
            'shareUsername.required' => 'Decime el usuario de la persona con quien lo compartís.',
        ]);

        $user = User::where('username', $this->shareUsername)->first();

        if (! $user) {
            $this->addError('shareUsername', 'No encontré a nadie con ese usuario.');

            return;
        }

        if ($vehicle->isOwnedBy($user)) {
            $this->addError('shareUsername', 'Ese auto ya es tuyo.');

            return;
        }

        if ($vehicle->members()->whereKey($user->id)->exists()) {
            $this->addError('shareUsername', 'Ya lo estás compartiendo con esa persona.');

            return;
        }

        $vehicle->members()->attach($user->id);

        $this->reset('shareUsername');
    }

    public function unshare(int $userId): void
    {
        $this->requireOwnedVehicle()->members()->detach($userId);
    }

    // --- Mantenimientos ---------------------------------------------------

    public function addItem(): void
    {
        $vehicle = $this->requireVehicle();

        $data = $this->validate([
            'itemName' => ['required', 'string', 'max:80'],
            'itemIntervalKm' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'itemIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
        ], [
            'itemName.required' => '¿Qué mantenimiento querés seguir?',
        ]);

        $this->makeItem($vehicle, trim($data['itemName']), $data['itemIntervalKm'], $data['itemIntervalMonths']);

        $this->reset('itemName', 'itemIntervalKm', 'itemIntervalMonths', 'addingItem');
    }

    public function startEditingItem(int $id): void
    {
        $item = $this->requireVehicle()->maintenanceItems()->findOrFail($id);

        $this->editingItemId = $item->id;
        $this->editItemName = $item->name;
        $this->editItemIntervalKm = $item->interval_km;
        $this->editItemIntervalMonths = $item->interval_months;
        $this->resetValidation();
    }

    public function saveItem(): void
    {
        $item = $this->requireVehicle()->maintenanceItems()->findOrFail($this->editingItemId);

        $data = $this->validate([
            'editItemName' => ['required', 'string', 'max:80'],
            'editItemIntervalKm' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'editItemIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
        ], [
            'editItemName.required' => '¿Qué mantenimiento querés seguir?',
        ]);

        $item->update([
            'name' => trim($data['editItemName']),
            'interval_km' => $data['editItemIntervalKm'],
            'interval_months' => $data['editItemIntervalMonths'],
        ]);

        $this->cancelEditItem();
    }

    public function cancelEditItem(): void
    {
        $this->reset('editingItemId', 'editItemName', 'editItemIntervalKm', 'editItemIntervalMonths');
        $this->resetValidation();
    }

    public function deleteItem(int $id): void
    {
        $this->requireVehicle()->maintenanceItems()->findOrFail($id)->delete();

        if ($this->loggingItemId === $id) {
            $this->loggingItemId = null;
        }

        if ($this->editingItemId === $id) {
            $this->cancelEditItem();
        }

        if ($this->historyItemId === $id) {
            $this->reset('historyItemId', 'editingRecordId', 'editRecordDate', 'editRecordMileage', 'editRecordCost');
        }
    }

    public function startLog(int $id): void
    {
        $item = $this->requireVehicle()->maintenanceItems()->findOrFail($id);

        $this->loggingItemId = $item->id;
        $this->logDate = now()->format('Y-m-d');
        $this->logMileage = $this->vehicle?->kilometraje;
        $this->logCost = null;
        $this->logNote = '';
        $this->resetValidation();
    }

    public function cancelLog(): void
    {
        $this->reset('loggingItemId', 'logMileage', 'logCost', 'logNote');
        $this->resetValidation();
    }

    public function saveLog(): void
    {
        $vehicle = $this->requireVehicle();
        $item = $vehicle->maintenanceItems()->findOrFail($this->loggingItemId);

        $this->logCost = $this->logCost === '' ? null : $this->logCost;

        $data = $this->validate([
            'logDate' => ['required', 'date'],
            'logMileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'logCost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'logNote' => ['nullable', 'string', 'max:255'],
        ], [
            'logDate.required' => '¿Qué día lo hiciste?',
            'logMileage.required' => 'Anotá con cuántos km lo hiciste.',
        ]);

        $record = $item->records()->make([
            'performed_on' => $data['logDate'],
            'mileage' => $data['logMileage'],
            'cost' => $data['logCost'],
            'note' => trim($this->logNote) === '' ? null : trim($this->logNote),
        ]);
        $record->user_id = auth()->id();
        $record->vehicle_id = $vehicle->id;
        $record->save();

        $vehicle->bumpMileage((int) $data['logMileage']);

        $this->reset('loggingItemId', 'logMileage', 'logCost', 'logNote');
    }

    // --- Realizaciones (historial) ----------------------------------------

    public function toggleHistory(int $id): void
    {
        // Acordeón: abrir un historial cierra el que estuviera abierto.
        $this->historyItemId = $this->historyItemId === $id ? null : $id;
        $this->cancelEditRecord();
    }

    public function startEditingRecord(int $id): void
    {
        $record = $this->requireVehicle()->maintenanceRecords()->findOrFail($id);

        $this->editingRecordId = $record->id;
        $this->editRecordDate = $record->performed_on->format('Y-m-d');
        $this->editRecordMileage = $record->mileage;
        $this->editRecordCost = $record->cost === null ? null : rtrim(rtrim((string) $record->cost, '0'), '.');
        $this->editRecordNote = (string) $record->note;
        $this->resetValidation();
    }

    public function saveRecord(): void
    {
        $vehicle = $this->requireVehicle();
        $record = $vehicle->maintenanceRecords()->findOrFail($this->editingRecordId);

        $this->editRecordCost = $this->editRecordCost === '' ? null : $this->editRecordCost;

        $data = $this->validate([
            'editRecordDate' => ['required', 'date'],
            'editRecordMileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'editRecordCost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'editRecordNote' => ['nullable', 'string', 'max:255'],
        ], [
            'editRecordDate.required' => '¿Qué día lo hiciste?',
            'editRecordMileage.required' => 'Anotá con cuántos km lo hiciste.',
        ]);

        $record->update([
            'performed_on' => $data['editRecordDate'],
            'mileage' => $data['editRecordMileage'],
            'cost' => $data['editRecordCost'],
            'note' => trim($this->editRecordNote) === '' ? null : trim($this->editRecordNote),
        ]);

        $vehicle->bumpMileage((int) $data['editRecordMileage']);

        $this->cancelEditRecord();
    }

    public function cancelEditRecord(): void
    {
        $this->reset('editingRecordId', 'editRecordDate', 'editRecordMileage', 'editRecordCost', 'editRecordNote');
        $this->resetValidation();
    }

    public function deleteRecord(int $id): void
    {
        $this->requireVehicle()->maintenanceRecords()->findOrFail($id)->delete();

        if ($this->editingRecordId === $id) {
            $this->cancelEditRecord();
        }
    }

    // --- Combustible ------------------------------------------------------

    public function addFuel(): void
    {
        $vehicle = $this->requireVehicle();

        $this->fuelCost = $this->fuelCost === '' ? null : $this->fuelCost;

        $data = $this->validate([
            'fuelDate' => ['required', 'date'],
            'fuelMileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'fuelCost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'fuelDate.required' => '¿Qué día cargaste?',
            'fuelMileage.required' => 'Anotá cuánto marcaba el auto al cargar.',
        ]);

        $log = $vehicle->fuelLogs()->make([
            'filled_on' => $data['fuelDate'],
            'mileage' => $data['fuelMileage'],
            'cost' => $data['fuelCost'],
        ]);
        $log->user_id = auth()->id();
        $log->save();

        $vehicle->bumpMileage((int) $data['fuelMileage']);

        $this->reset('fuelMileage', 'fuelCost');
        $this->fuelDate = now()->format('Y-m-d');
    }

    public function startEditingFuel(int $id): void
    {
        $log = $this->requireVehicle()->fuelLogs()->findOrFail($id);

        $this->editingFuelId = $log->id;
        $this->editFuelDate = $log->filled_on->format('Y-m-d');
        $this->editFuelMileage = $log->mileage;
        $this->editFuelCost = $log->cost === null ? null : rtrim(rtrim((string) $log->cost, '0'), '.');
        $this->resetValidation();
    }

    public function saveFuelEdit(): void
    {
        $vehicle = $this->requireVehicle();
        $log = $vehicle->fuelLogs()->findOrFail($this->editingFuelId);

        $this->editFuelCost = $this->editFuelCost === '' ? null : $this->editFuelCost;

        $data = $this->validate([
            'editFuelDate' => ['required', 'date'],
            'editFuelMileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'editFuelCost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'editFuelDate.required' => '¿Qué día cargaste?',
            'editFuelMileage.required' => 'Anotá cuánto marcaba el auto al cargar.',
        ]);

        $log->update([
            'filled_on' => $data['editFuelDate'],
            'mileage' => $data['editFuelMileage'],
            'cost' => $data['editFuelCost'],
        ]);

        $vehicle->bumpMileage((int) $data['editFuelMileage']);

        $this->cancelEditFuel();
    }

    public function cancelEditFuel(): void
    {
        $this->reset('editingFuelId', 'editFuelDate', 'editFuelMileage', 'editFuelCost');
        $this->resetValidation();
    }

    public function deleteFuel(int $id): void
    {
        $this->requireVehicle()->fuelLogs()->findOrFail($id)->delete();

        if ($this->editingFuelId === $id) {
            $this->cancelEditFuel();
        }
    }

    // --- Documentación ----------------------------------------------------

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

    // --- Helpers ----------------------------------------------------------

    private function makeItem(Vehicle $vehicle, string $name, ?int $km, ?int $months): void
    {
        $item = $vehicle->maintenanceItems()->make([
            'name' => $name,
            'interval_km' => $km,
            'interval_months' => $months,
        ]);
        $item->user_id = auth()->id();
        $item->save();
    }

    /**
     * Auto al que el usuario tiene acceso (propio o compartido). Cualquier
     * persona con acceso puede cargar mantenimientos y combustible.
     */
    private function requireVehicle(): Vehicle
    {
        return auth()->user()->accessibleVehicles()->findOrFail($this->vehicleId);
    }

    /**
     * Auto del que el usuario es dueño. Editar, eliminar y compartir son
     * acciones reservadas al dueño.
     */
    private function requireOwnedVehicle(): Vehicle
    {
        return auth()->user()->vehicles()->findOrFail($this->vehicleId);
    }

    public function pesos(int|string|null $value): string
    {
        return '$'.number_format((float) $value, 2, ',', '.');
    }

    public function km(int|string|null $value): string
    {
        return number_format((int) $value, 0, ',', '.').' km';
    }

    #[Computed]
    public function vehicles(): Collection
    {
        return auth()->user()->accessibleVehicles()->with('user')->orderBy('vehicles.id')->get();
    }

    #[Computed]
    public function vehicle(): ?Vehicle
    {
        return $this->vehicles->firstWhere('id', $this->vehicleId) ?? $this->vehicles->first();
    }

    #[Computed]
    public function isOwner(): bool
    {
        return $this->vehicle !== null && $this->vehicle->user_id === auth()->id();
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->vehicle?->members()->orderBy('name')->get() ?? collect();
    }

    /**
     * Con el auto compartido (dueño + al menos una persona más) mostramos
     * quién anotó cada registro; en un auto de una sola persona es ruido.
     */
    #[Computed]
    public function isShared(): bool
    {
        return $this->members->isNotEmpty();
    }

    #[Computed]
    public function items(): Collection
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return collect();
        }

        $km = $vehicle->kilometraje;
        $kmPerDay = $vehicle->kmPerDay();

        return $vehicle->maintenanceItems()
            ->with('latestRecord')
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'item' => $item,
                'status' => $item->status($km, $kmPerDay),
            ])
            ->sortBy(fn ($row) => [$row['status']['rank'], $row['status']['urgency']])
            ->values();
    }

    /**
     * Historial completo de realizaciones del ítem desplegado, de la más
     * reciente a la más vieja.
     */
    #[Computed]
    public function history(): Collection
    {
        if ($this->historyItemId === null) {
            return collect();
        }

        $item = $this->vehicle?->maintenanceItems()->find($this->historyItemId);

        return $item
            ? $item->records()->with('user')->orderByDesc('performed_on')->orderByDesc('mileage')->get()
            : collect();
    }

    #[Computed]
    public function fuelLogs(): Collection
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return collect();
        }

        $logs = $vehicle->fuelLogs()->with('user')->orderByDesc('filled_on')->orderByDesc('mileage')->get();

        return $logs->map(function ($log, $index) use ($logs) {
            $previous = $logs->get($index + 1); // la carga inmediatamente anterior
            $since = $previous ? $log->mileage - $previous->mileage : null;

            return ['log' => $log, 'since' => $since !== null && $since >= 0 ? $since : null];
        });
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
            ->with(['user', 'renewals.user'])
            ->get()
            ->map(fn ($document) => [
                'doc' => $document,
                'status' => $document->status(),
            ])
            ->sortBy(fn ($row) => [$row['status']['rank'], $row['status']['urgency']])
            ->values();
    }

    #[Computed]
    public function maintenanceTotal(): float
    {
        return (float) ($this->vehicle?->maintenanceRecords()->sum('cost') ?? 0);
    }

    #[Computed]
    public function fuelTotal(): float
    {
        return (float) ($this->vehicle?->fuelLogs()->sum('cost') ?? 0);
    }
};
?>

<section class="space-y-6">
    <header class="flex items-center gap-3">
        <span class="h-8 w-1.5 rounded-sm bg-grafito" aria-hidden="true"></span>
        <h1 class="font-brand text-3xl font-bold">Auto</h1>
    </header>

    @if (! $this->vehicle)
        {{-- Sin auto todavía: alta --}}
        <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
            Todavía no cargaste ningún auto. Contame cuál es y empezamos a llevarle la cuenta.
        </p>

        <form wire:submit="createVehicle" class="space-y-3 rounded-sm border border-cuero/20 p-4">
            <h2 class="font-brand text-lg font-bold">Tu auto</h2>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="newMarca" class="mb-1 block text-sm font-medium">Marca</label>
                    <input id="newMarca" type="text" wire:model="newMarca" autocomplete="off"
                        placeholder="Volkswagen"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newMarca') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="newModelo" class="mb-1 block text-sm font-medium">Modelo</label>
                    <input id="newModelo" type="text" wire:model="newModelo" autocomplete="off"
                        placeholder="Gol Trend"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newModelo') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="newPatente" class="mb-1 block text-sm font-medium">Patente <span class="font-normal text-cuero/60">(opcional)</span></label>
                    <input id="newPatente" type="text" wire:model="newPatente" autocomplete="off"
                        placeholder="AB123CD"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base uppercase placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newPatente') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="newKilometraje" class="mb-1 block text-sm font-medium">Kilometraje actual</label>
                    <input id="newKilometraje" type="number" inputmode="numeric" min="0" wire:model="newKilometraje"
                        placeholder="85000"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newKilometraje') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="min-h-11 w-full rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60 sm:w-auto">
                Guardar auto
            </button>
        </form>
    @else
        @php($vehicle = $this->vehicle)

        {{-- Selector cuando hay más de un auto --}}
        @if ($this->vehicles->count() > 1)
            <div class="flex flex-wrap gap-2" role="group" aria-label="Elegí un auto">
                @foreach ($this->vehicles as $v)
                    <button type="button" wire:click="selectVehicle({{ $v->id }})"
                        @if ($v->id === $vehicle->id) aria-current="true" @endif
                        class="min-h-11 rounded-sm border px-3 text-sm {{ $v->id === $vehicle->id ? 'border-grafito bg-grafito/10 font-semibold text-grafito' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }}">
                        {{ $v->nombre() }}@unless ($v->user_id === auth()->id())<span class="ml-1 text-xs font-normal text-cuero/50">· compartido</span>@endunless
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Ficha del auto --}}
        <div class="rounded-sm border border-cuero/20 p-4">
            @if ($this->editingVehicle)
                <form wire:submit="saveVehicle" class="space-y-3">
                    <h2 class="font-brand text-lg font-bold">Editar auto</h2>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="editMarca" class="mb-1 block text-sm font-medium">Marca</label>
                            <input id="editMarca" type="text" wire:model="editMarca" autocomplete="off"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('editMarca') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="editModelo" class="mb-1 block text-sm font-medium">Modelo</label>
                            <input id="editModelo" type="text" wire:model="editModelo" autocomplete="off"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('editModelo') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="editPatente" class="mb-1 block text-sm font-medium">Patente <span class="font-normal text-cuero/60">(opcional)</span></label>
                            <input id="editPatente" type="text" wire:model="editPatente" autocomplete="off"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base uppercase focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('editPatente') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('editingVehicle', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                    </div>
                </form>
            @else
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-brand text-2xl font-bold leading-tight">{{ $vehicle->nombre() }}</h2>
                        @if ($vehicle->patente)
                            <span class="mt-1 inline-block rounded-sm bg-ocre px-2 py-0.5 text-xs font-semibold tracking-wide text-negro">
                                {{ $vehicle->patente }}
                            </span>
                        @endif
                        @unless ($this->isOwner)
                            <p class="mt-1 text-xs text-cuero/60">Compartido por {{ $vehicle->user->name }}</p>
                        @endunless
                    </div>
                    @if ($this->isOwner)
                        <div class="flex shrink-0 items-center">
                            <button type="button" wire:click="startEditingVehicle" aria-label="Editar auto"
                                class="grid size-11 place-items-center text-cuero/60 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                                {{-- Heroicon: pencil-square (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                            </button>
                            <button type="button" wire:click="deleteVehicle({{ $vehicle->id }})"
                                wire:confirm="Vas a eliminar este auto y todo su historial de mantenimientos y cargas. Esto no se puede deshacer."
                                aria-label="Eliminar auto"
                                class="grid size-11 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-4 border-t border-cuero/15 pt-3">
                @if ($this->editingKm)
                    <form wire:submit="saveKm" class="flex flex-wrap items-end gap-2">
                        <div>
                            <label for="kmValue" class="mb-1 block text-sm font-medium">Kilometraje actual</label>
                            <input id="kmValue" type="number" inputmode="numeric" min="0" wire:model="kmValue"
                                class="min-h-11 w-40 rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                        </div>
                        <button type="submit"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('editingKm', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                        @error('kmValue') <p class="w-full text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </form>
                @else
                    <div class="flex items-center gap-3">
                        <div>
                            <p class="text-sm text-cuero/60">Kilometraje actual</p>
                            <p class="font-brand text-xl font-bold">{{ $this->km($vehicle->kilometraje) }}</p>
                        </div>
                        <button type="button" wire:click="startEditingKm"
                            class="ml-auto min-h-11 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                            Actualizar
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Compartir (solo el dueño) --}}
        @if ($this->isOwner)
            <div class="space-y-3 rounded-sm border border-cuero/20 p-4">
                <div>
                    <h2 class="font-brand text-lg font-bold">Compartir</h2>
                    <p class="text-sm text-cuero/60">Sumá a otra persona con su usuario y verá este auto con todos sus gastos y mantenimientos.</p>
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
                                    wire:confirm="Vas a dejar de compartir el auto con {{ $member->name }}. Ya no va a poder verlo."
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

        {{-- Mantenimientos --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <h2 class="font-brand text-lg font-bold">Mantenimientos</h2>
                @if ($this->maintenanceTotal > 0)
                    <span class="ml-auto text-sm text-cuero/60">Gastado: {{ $this->pesos($this->maintenanceTotal) }}</span>
                @endif
            </div>

            @if ($this->items->isEmpty())
                <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
                    Todavía no seguís ningún mantenimiento. Agregá el primero y te aviso cuándo toca.
                </p>
            @else
                <ul class="space-y-2">
                    @foreach ($this->items as $row)
                        @php($item = $row['item'])
                        @php($status = $row['status'])
                        <li wire:key="item-{{ $item->id }}" class="rounded-sm border border-cuero/20 p-3">
                            @if ($this->editingItemId === $item->id)
                                <form wire:submit="saveItem" class="space-y-3">
                                    <div>
                                        <label for="editItemName-{{ $item->id }}" class="mb-1 block text-sm font-medium">¿Qué mantenimiento?</label>
                                        <input id="editItemName-{{ $item->id }}" type="text" wire:model="editItemName" autocomplete="off"
                                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                        @error('editItemName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label for="editItemIntervalKm-{{ $item->id }}" class="mb-1 block text-sm font-medium">Cada cuántos km <span class="font-normal text-cuero/60">(opcional)</span></label>
                                            <input id="editItemIntervalKm-{{ $item->id }}" type="number" inputmode="numeric" min="1" wire:model="editItemIntervalKm"
                                                placeholder="10000"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('editItemIntervalKm') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="editItemIntervalMonths-{{ $item->id }}" class="mb-1 block text-sm font-medium">Cada cuántos meses <span class="font-normal text-cuero/60">(opcional)</span></label>
                                            <input id="editItemIntervalMonths-{{ $item->id }}" type="number" inputmode="numeric" min="1" wire:model="editItemIntervalMonths"
                                                placeholder="12"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('editItemIntervalMonths') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <p class="text-xs text-cuero/60">Cambiar los intervalos recalcula cuándo toca el próximo. El historial de realizaciones no se toca.</p>
                                    <div class="flex gap-2">
                                        <button type="submit"
                                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                            Guardar
                                        </button>
                                        <button type="button" wire:click="cancelEditItem"
                                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                    </div>
                                </form>
                            @else
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium">{{ $item->name }}</span>
                                        <span @class([
                                            'rounded-sm px-2 py-0.5 text-xs font-semibold',
                                            'bg-teja text-crema' => $status['level'] === 'overdue',
                                            'bg-ocre text-negro' => $status['level'] === 'soon',
                                            'bg-yerba text-crema' => $status['level'] === 'ok',
                                            'border border-cuero/30 text-cuero/70' => $status['level'] === 'none',
                                        ])>
                                            {{ $status['headline'] }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-cuero/70">{{ $status['detail'] }}</p>

                                    @if ($item->latestRecord)
                                        <p class="mt-1 text-xs text-cuero/60">
                                            Último: {{ $item->latestRecord->performed_on->format('d/m/Y') }}
                                            · {{ $this->km($item->latestRecord->mileage) }}@if ($item->latestRecord->cost !== null) · {{ $this->pesos($item->latestRecord->cost) }}@endif
                                        </p>
                                        @if ($item->latestRecord->note)
                                            <p class="mt-1 text-xs text-cuero/60">{{ $item->latestRecord->note }}</p>
                                        @endif
                                    @endif

                                    <p class="mt-1 text-xs text-cuero/50">
                                        Cada
                                        @if ($item->interval_km){{ $this->km($item->interval_km) }}@endif
                                        @if ($item->interval_km && $item->interval_months) o @endif
                                        @if ($item->interval_months){{ $item->interval_months }} {{ $item->interval_months === 1 ? 'mes' : 'meses' }}@endif
                                        @if (! $item->interval_km && ! $item->interval_months)<span class="italic">sin recordatorio automático</span>@endif
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center">
                                    <button type="button" wire:click="startEditingItem({{ $item->id }})"
                                        aria-label="Editar {{ $item->name }}"
                                        class="grid size-9 place-items-center text-cuero/50 hover:text-grafito focus-visible:outline-2 focus-visible:outline-grafito">
                                        {{-- Heroicon: pencil-square (mini) --}}
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                            <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                        </svg>
                                    </button>
                                    <button type="button" wire:click="deleteItem({{ $item->id }})"
                                        wire:confirm="Vas a eliminar «{{ $item->name }}» y su historial. Esto no se puede deshacer."
                                        aria-label="Eliminar {{ $item->name }}"
                                        class="grid size-9 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            @if ($this->loggingItemId === $item->id)
                                <form wire:submit="saveLog" class="mt-3 space-y-3 border-t border-cuero/15 pt-3">
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <x-ui.date-field model="logDate" id="logDate-{{ $item->id }}" label="Fecha" accent="grafito" preset="pasado" />
                                        </div>
                                        <div>
                                            <label for="logMileage-{{ $item->id }}" class="mb-1 block text-sm font-medium">Kilometraje</label>
                                            <input id="logMileage-{{ $item->id }}" type="number" inputmode="numeric" min="0" wire:model="logMileage"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('logMileage') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="logCost-{{ $item->id }}" class="mb-1 block text-sm font-medium">Costo <span class="font-normal text-cuero/60">(opcional)</span></label>
                                            <input id="logCost-{{ $item->id }}" type="number" inputmode="decimal" min="0" step="0.01" wire:model="logCost"
                                                placeholder="0,00"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('logCost') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div>
                                        <label for="logNote-{{ $item->id }}" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                                        <input id="logNote-{{ $item->id }}" type="text" wire:model="logNote" autocomplete="off"
                                            placeholder="Taller, qué se hizo, repuestos…"
                                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                        @error('logNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit"
                                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                            Guardar
                                        </button>
                                        <button type="button" wire:click="cancelLog"
                                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                    </div>
                                </form>
                            @else
                                <button type="button" wire:click="startLog({{ $item->id }})"
                                    class="mt-3 min-h-11 w-full rounded-sm border border-grafito/40 px-3 text-sm font-medium text-grafito hover:bg-grafito/5 focus-visible:outline-2 focus-visible:outline-grafito sm:w-auto">
                                    Registrar que lo hice
                                </button>
                            @endif

                            @if ($item->latestRecord)
                                <button type="button" wire:click="toggleHistory({{ $item->id }})"
                                    aria-expanded="{{ $this->historyItemId === $item->id ? 'true' : 'false' }}"
                                    class="mt-3 flex min-h-11 items-center gap-1 text-sm text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                        class="size-4 transition-transform {{ $this->historyItemId === $item->id ? 'rotate-90' : '' }}">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $this->historyItemId === $item->id ? 'Ocultar historial' : 'Ver historial' }}
                                </button>

                                @if ($this->historyItemId === $item->id)
                                    <ul class="mt-2 divide-y divide-cuero/15 border-y border-cuero/15">
                                        @foreach ($this->history as $record)
                                            <li wire:key="record-{{ $record->id }}" class="py-2">
                                                @if ($this->editingRecordId === $record->id)
                                                    <form wire:submit="saveRecord" class="space-y-3">
                                                        <div class="grid gap-3 sm:grid-cols-3">
                                                            <div>
                                                                <x-ui.date-field model="editRecordDate" id="editRecordDate-{{ $record->id }}" label="Fecha" accent="grafito" preset="pasado" />
                                                            </div>
                                                            <div>
                                                                <label for="editRecordMileage-{{ $record->id }}" class="mb-1 block text-sm font-medium">Kilometraje</label>
                                                                <input id="editRecordMileage-{{ $record->id }}" type="number" inputmode="numeric" min="0" wire:model="editRecordMileage"
                                                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                                @error('editRecordMileage') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                                            </div>
                                                            <div>
                                                                <label for="editRecordCost-{{ $record->id }}" class="mb-1 block text-sm font-medium">Costo <span class="font-normal text-cuero/60">(opcional)</span></label>
                                                                <input id="editRecordCost-{{ $record->id }}" type="number" inputmode="decimal" min="0" step="0.01" wire:model="editRecordCost"
                                                                    placeholder="0,00"
                                                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                                @error('editRecordCost') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label for="editRecordNote-{{ $record->id }}" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                                                            <input id="editRecordNote-{{ $record->id }}" type="text" wire:model="editRecordNote" autocomplete="off"
                                                                placeholder="Taller, qué se hizo, repuestos…"
                                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                            @error('editRecordNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                                        </div>
                                                        <div class="flex gap-2">
                                                            <button type="submit"
                                                                class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                                                Guardar
                                                            </button>
                                                            <button type="button" wire:click="cancelEditRecord"
                                                                class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                                        </div>
                                                    </form>
                                                @else
                                                    <div class="flex items-center gap-2">
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-sm">
                                                                <span class="font-medium">{{ $record->performed_on->format('d/m/Y') }}</span>
                                                                · {{ $this->km($record->mileage) }}@if ($record->cost !== null) · {{ $this->pesos($record->cost) }}@endif
                                                            </p>
                                                            @if ($record->note)
                                                                <p class="text-xs text-cuero/60">{{ $record->note }}</p>
                                                            @endif
                                                            @if ($this->isShared)
                                                                <p class="text-xs text-cuero/50">Anotó {{ $record->user->name }}</p>
                                                            @endif
                                                        </div>
                                                        <button type="button" wire:click="startEditingRecord({{ $record->id }})"
                                                            aria-label="Editar realización del {{ $record->performed_on->format('d/m/Y') }}"
                                                            class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-grafito focus-visible:outline-2 focus-visible:outline-grafito">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                                                <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                                            </svg>
                                                        </button>
                                                        <button type="button" wire:click="deleteRecord({{ $record->id }})"
                                                            wire:confirm="Vas a eliminar esta realización. Esto no se puede deshacer."
                                                            aria-label="Eliminar realización del {{ $record->performed_on->format('d/m/Y') }}"
                                                            class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Agregar mantenimiento --}}
            @if ($this->addingItem)
                <form wire:submit="addItem" class="space-y-3 rounded-sm border border-cuero/20 p-3">
                    <div>
                        <label for="itemName" class="mb-1 block text-sm font-medium">¿Qué mantenimiento?</label>
                        <input id="itemName" type="text" wire:model="itemName" autocomplete="off"
                            placeholder="Filtro de aire, líquido de frenos…"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                        @error('itemName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="itemIntervalKm" class="mb-1 block text-sm font-medium">Cada cuántos km <span class="font-normal text-cuero/60">(opcional)</span></label>
                            <input id="itemIntervalKm" type="number" inputmode="numeric" min="1" wire:model="itemIntervalKm"
                                placeholder="10000"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('itemIntervalKm') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="itemIntervalMonths" class="mb-1 block text-sm font-medium">Cada cuántos meses <span class="font-normal text-cuero/60">(opcional)</span></label>
                            <input id="itemIntervalMonths" type="number" inputmode="numeric" min="1" wire:model="itemIntervalMonths"
                                placeholder="12"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('itemIntervalMonths') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-cuero/60">Si dejás los dos vacíos, lo llevo en el historial pero no te aviso del próximo.</p>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                            Agregar
                        </button>
                        <button type="button" wire:click="$set('addingItem', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                    </div>
                </form>
            @else
                <button type="button" wire:click="$set('addingItem', true)"
                    class="min-h-11 w-full rounded-sm border border-dashed border-cuero/40 px-3 text-sm font-medium text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                    + Agregar mantenimiento
                </button>
            @endif
        </div>

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

        {{-- Combustible --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <h2 class="font-brand text-lg font-bold">Combustible</h2>
                @if ($this->fuelTotal > 0)
                    <span class="ml-auto text-sm text-cuero/60">Gastado: {{ $this->pesos($this->fuelTotal) }}</span>
                @endif
            </div>

            <form wire:submit="addFuel" class="grid gap-3 rounded-sm border border-cuero/20 p-3 sm:grid-cols-4 sm:items-end">
                <div>
                    <x-ui.date-field model="fuelDate" label="Fecha" accent="grafito" preset="pasado" />
                </div>
                <div>
                    <label for="fuelMileage" class="mb-1 block text-sm font-medium">Kilometraje</label>
                    <input id="fuelMileage" type="number" inputmode="numeric" min="0" wire:model="fuelMileage"
                        placeholder="{{ $vehicle->kilometraje }}"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('fuelMileage') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="fuelCost" class="mb-1 block text-sm font-medium">Costo <span class="font-normal text-cuero/60">(opcional)</span></label>
                    <input id="fuelCost" type="number" inputmode="decimal" min="0" step="0.01" wire:model="fuelCost"
                        placeholder="0,00"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('fuelCost') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                    Anotar carga
                </button>
            </form>

            @if ($this->fuelLogs->isEmpty())
                <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
                    Todavía no anotaste ninguna carga. Cuando cargues, guardá el kilometraje y lo que gastaste.
                </p>
            @else
                <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                    @foreach ($this->fuelLogs as $row)
                        @php($log = $row['log'])
                        <li wire:key="fuel-{{ $log->id }}" class="py-2">
                            @if ($this->editingFuelId === $log->id)
                                <form wire:submit="saveFuelEdit" class="space-y-3">
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <x-ui.date-field model="editFuelDate" id="editFuelDate-{{ $log->id }}" label="Fecha" accent="grafito" preset="pasado" />
                                        </div>
                                        <div>
                                            <label for="editFuelMileage-{{ $log->id }}" class="mb-1 block text-sm font-medium">Kilometraje</label>
                                            <input id="editFuelMileage-{{ $log->id }}" type="number" inputmode="numeric" min="0" wire:model="editFuelMileage"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('editFuelMileage') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="editFuelCost-{{ $log->id }}" class="mb-1 block text-sm font-medium">Costo <span class="font-normal text-cuero/60">(opcional)</span></label>
                                            <input id="editFuelCost-{{ $log->id }}" type="number" inputmode="decimal" min="0" step="0.01" wire:model="editFuelCost"
                                                placeholder="0,00"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('editFuelCost') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit"
                                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                            Guardar
                                        </button>
                                        <button type="button" wire:click="cancelEditFuel"
                                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                    </div>
                                </form>
                            @else
                                <div class="flex items-center gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm">
                                            <span class="font-medium">{{ $log->filled_on->format('d/m/Y') }}</span>
                                            · {{ $this->km($log->mileage) }}
                                            @if ($log->cost !== null) · {{ $this->pesos($log->cost) }} @endif
                                        </p>
                                        @if ($row['since'] !== null)
                                            <p class="text-xs text-cuero/50">Recorriste {{ $this->km($row['since']) }} desde la carga anterior.</p>
                                        @endif
                                        @if ($this->isShared)
                                            <p class="text-xs text-cuero/50">Anotó {{ $log->user->name }}</p>
                                        @endif
                                    </div>
                                    <button type="button" wire:click="startEditingFuel({{ $log->id }})"
                                        aria-label="Editar carga del {{ $log->filled_on->format('d/m/Y') }}"
                                        class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-grafito focus-visible:outline-2 focus-visible:outline-grafito">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                            <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                        </svg>
                                    </button>
                                    <button type="button" wire:click="deleteFuel({{ $log->id }})"
                                        wire:confirm="Vas a eliminar esta carga. Esto no se puede deshacer."
                                        aria-label="Eliminar carga del {{ $log->filled_on->format('d/m/Y') }}"
                                        class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>
