<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentRenewal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutoDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_puede_cargar_un_documento_con_vencimiento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->set('addingDocument', true)
            ->set('docName', 'Seguro')
            ->set('docExpiresOn', '2026-12-31')
            ->set('docNote', 'La Segunda')
            ->call('addDocument')
            ->assertHasNoErrors()
            ->assertSet('addingDocument', false);

        $this->assertDatabaseHas('vehicle_documents', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->user->id,
            'name' => 'Seguro',
            'note' => 'La Segunda',
        ]);
    }

    public function test_el_nombre_y_el_vencimiento_del_documento_son_obligatorios(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->set('addingDocument', true)
            ->set('docName', '')
            ->set('docExpiresOn', '')
            ->call('addDocument')
            ->assertHasErrors(['docName' => 'required', 'docExpiresOn' => 'required']);
    }

    public function test_calcula_el_estado_del_documento(): void
    {
        $vencido = VehicleDocument::factory()->create(['expires_on' => now()->subDay()->toDateString()]);
        $porVencer = VehicleDocument::factory()->create(['expires_on' => now()->addDays(10)->toDateString()]);
        $alDia = VehicleDocument::factory()->create(['expires_on' => now()->addMonths(6)->toDateString()]);

        $this->assertSame('overdue', $vencido->status()['level']);
        $this->assertSame('soon', $porVencer->status()['level']);
        $this->assertSame('ok', $alDia->status()['level']);
    }

    public function test_puede_editar_un_documento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create([
            'name' => 'VTV',
            'expires_on' => '2026-06-01',
        ]);

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->call('startEditingDocument', $doc->id)
            ->assertSet('editDocName', 'VTV')
            ->set('editDocName', 'VTV nueva')
            ->set('editDocExpiresOn', '2027-06-01')
            ->call('saveDocument')
            ->assertHasNoErrors()
            ->assertSet('editingDocumentId', null);

        $doc->refresh();
        $this->assertSame('VTV nueva', $doc->name);
        $this->assertSame('2027-06-01', $doc->expires_on->format('Y-m-d'));
    }

    public function test_puede_eliminar_un_documento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])->call('deleteDocument', $doc->id);

        $this->assertModelMissing($doc);
    }

    public function test_no_puede_cargar_documentos_en_autos_ajenos(): void
    {
        $ajeno = Vehicle::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.documentos', ['vehicleId' => $ajeno->id])
            ->set('docName', 'Seguro')
            ->set('docExpiresOn', '2026-12-31')
            ->call('addDocument');
    }

    public function test_puede_cargar_un_documento_con_periodicidad(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->set('addingDocument', true)
            ->set('docName', 'Seguro')
            ->set('docExpiresOn', '2026-12-31')
            ->set('docIntervalMonths', 6)
            ->call('addDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_documents', [
            'vehicle_id' => $vehicle->id,
            'name' => 'Seguro',
            'interval_months' => 6,
        ]);
    }

    public function test_puede_editar_la_periodicidad_de_un_documento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create(['interval_months' => 6]);

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->call('startEditingDocument', $doc->id)
            ->assertSet('editDocIntervalMonths', 6)
            ->set('editDocIntervalMonths', 12)
            ->call('saveDocument')
            ->assertHasNoErrors();

        $this->assertSame(12, $doc->fresh()->interval_months);
    }

    public function test_renovar_un_documento_guarda_la_vigencia_anterior(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create([
            'expires_on' => '2026-08-01',
        ]);

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->call('startRenewingDocument', $doc->id)
            ->set('renewDocExpiresOn', '2027-02-01')
            ->call('saveRenewal')
            ->assertHasNoErrors()
            ->assertSet('renewingDocumentId', null);

        $doc->refresh();
        $this->assertSame('2027-02-01', $doc->expires_on->format('Y-m-d'));

        $renewal = $doc->renewals()->sole();
        $this->assertSame('2026-08-01', $renewal->expires_on->format('Y-m-d'));
        $this->assertSame($this->user->id, $renewal->user_id);
    }

    public function test_renovar_sugiere_la_proxima_fecha_segun_la_periodicidad(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create([
            'expires_on' => '2026-08-01',
            'interval_months' => 6,
        ]);

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->call('startRenewingDocument', $doc->id)
            ->assertSet('renewDocExpiresOn', '2027-02-01');
    }

    public function test_sin_periodicidad_no_sugiere_fecha_al_renovar(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create([
            'interval_months' => null,
        ]);

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->call('startRenewingDocument', $doc->id)
            ->assertSet('renewDocExpiresOn', '');
    }

    public function test_el_nuevo_vencimiento_es_obligatorio_al_renovar(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->call('startRenewingDocument', $doc->id)
            ->set('renewDocExpiresOn', '')
            ->call('saveRenewal')
            ->assertHasErrors(['renewDocExpiresOn' => 'required']);

        $this->assertDatabaseCount('vehicle_document_renewals', 0);
    }

    public function test_quien_recibe_el_auto_compartido_puede_renovar_un_documento(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $doc = VehicleDocument::factory()->for($owner)->for($vehicle)->create(['expires_on' => '2026-08-01']);
        $vehicle->members()->attach($this->user);

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])
            ->call('startRenewingDocument', $doc->id)
            ->set('renewDocExpiresOn', '2027-08-01')
            ->call('saveRenewal')
            ->assertHasNoErrors();

        // La renovación queda a nombre de quien la hizo (el invitado).
        $this->assertSame($this->user->id, $doc->renewals()->sole()->user_id);
    }

    public function test_no_puede_renovar_documentos_de_autos_ajenos(): void
    {
        $ajeno = Vehicle::factory()->create();
        $doc = VehicleDocument::factory()->for($ajeno->user)->for($ajeno)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.documentos', ['vehicleId' => $ajeno->id])
            ->call('startRenewingDocument', $doc->id);
    }

    public function test_al_eliminar_un_documento_se_borran_sus_vigencias_anteriores(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create();
        VehicleDocumentRenewal::factory()->for($this->user)->for($doc, 'document')->create();

        Livewire::test('auto.documentos', ['vehicleId' => $vehicle->id])->call('deleteDocument', $doc->id);

        $this->assertModelMissing($doc);
        $this->assertDatabaseCount('vehicle_document_renewals', 0);
    }
}
