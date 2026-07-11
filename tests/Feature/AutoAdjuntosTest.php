<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentAttachment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AutoAdjuntosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** Crea un adjunto con su archivo en el disco fake, listo para servir. */
    private function makeAttachment(VehicleDocument $document, string $extension = 'pdf'): VehicleDocumentAttachment
    {
        $path = 'auto/'.$document->vehicle_id.'/'.fake()->uuid().'.'.$extension;
        Storage::disk('local')->put($path, $extension === 'pdf' ? '%PDF-1.4 fake' : 'fake-image');

        return VehicleDocumentAttachment::factory()
            ->for($document, 'document')
            ->for($this->user)
            ->create([
                'path' => $path,
                'original_name' => 'archivo.'.$extension,
            ]);
    }

    public function test_puede_adjuntar_un_pdf_a_un_documento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('docFiles.'.$document->id, UploadedFile::fake()->create('poliza.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors();

        $attachment = VehicleDocumentAttachment::sole();
        $this->assertSame($document->id, $attachment->vehicle_document_id);
        $this->assertSame($this->user->id, $attachment->user_id);
        $this->assertSame('poliza.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_puede_adjuntar_una_imagen_a_un_documento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('docFiles.'.$document->id, UploadedFile::fake()->create('oblea.jpg', 80, 'image/jpeg'))
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_document_attachments', [
            'vehicle_document_id' => $document->id,
            'original_name' => 'oblea.jpg',
        ]);
    }

    public function test_rechaza_archivos_que_no_son_pdf_ni_imagen(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('docFiles.'.$document->id, UploadedFile::fake()->create('apunte.txt', 10, 'text/plain'))
            ->assertHasErrors(['docFiles.'.$document->id => 'mimes']);

        $this->assertDatabaseEmpty('vehicle_document_attachments');
    }

    public function test_rechaza_un_archivo_de_mas_de_10_mb(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('docFiles.'.$document->id, UploadedFile::fake()->create('pesado.pdf', 11_000, 'application/pdf'))
            ->assertHasErrors(['docFiles.'.$document->id => 'max']);

        $this->assertDatabaseEmpty('vehicle_document_attachments');
    }

    public function test_rechaza_un_archivo_con_extension_desconocida(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();

        // Contenido permitido pero extensión fuera del mapa: sin una extensión
        // confiable la descarga no sabría con qué Content-Type servirlo.
        Livewire::test('auto.panel')
            ->set('docFiles.'.$document->id, UploadedFile::fake()->create('poliza.bin', 10, 'application/pdf'))
            ->assertHasErrors(['docFiles.'.$document->id => 'extensions']);

        $this->assertDatabaseEmpty('vehicle_document_attachments');
    }

    public function test_no_puede_adjuntar_a_un_documento_de_otro_auto(): void
    {
        $otro = User::factory()->create();
        $documentoAjeno = VehicleDocument::factory()
            ->for(Vehicle::factory()->for($otro))
            ->for($otro)
            ->create();

        Vehicle::factory()->for($this->user)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')
            ->set('docFiles.'.$documentoAjeno->id, UploadedFile::fake()->create('poliza.pdf', 10, 'application/pdf'));
    }

    public function test_quien_tiene_el_auto_compartido_puede_adjuntar(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $vehicle->members()->attach($this->user);
        $document = VehicleDocument::factory()->for($vehicle)->for($owner)->create();

        Livewire::test('auto.panel')
            ->set('docFiles.'.$document->id, UploadedFile::fake()->create('seguro.pdf', 50, 'application/pdf'))
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_document_attachments', [
            'vehicle_document_id' => $document->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_puede_descargar_un_adjunto_propio(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();
        $attachment = $this->makeAttachment($document);

        // Descarga directa con el nombre original, sin redirigir a otra URL:
        // en la PWA instalada una navegación externa saca al usuario de la app.
        $this->get(route('auto.adjunto', $attachment))
            ->assertOk()
            ->assertDownload($attachment->original_name)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_una_imagen_se_descarga_con_su_content_type(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();
        $attachment = $this->makeAttachment($document, 'jpg');

        $this->get(route('auto.adjunto', $attachment))
            ->assertOk()
            ->assertDownload('archivo.jpg')
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_quien_tiene_el_auto_compartido_puede_descargar(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $vehicle->members()->attach($this->user);
        $document = VehicleDocument::factory()->for($vehicle)->for($owner)->create();
        $attachment = $this->makeAttachment($document);

        $this->get(route('auto.adjunto', $attachment))->assertOk();
    }

    public function test_no_se_puede_descargar_un_adjunto_ajeno(): void
    {
        $otro = User::factory()->create();
        $documentoAjeno = VehicleDocument::factory()
            ->for(Vehicle::factory()->for($otro))
            ->for($otro)
            ->create();
        $attachment = VehicleDocumentAttachment::factory()->for($documentoAjeno, 'document')->for($otro)->create();

        $this->get(route('auto.adjunto', $attachment))->assertNotFound();
    }

    public function test_sin_iniciar_sesion_no_se_descarga_nada(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();
        $attachment = $this->makeAttachment($document);

        auth()->logout();

        $this->get(route('auto.adjunto', $attachment))->assertRedirect(route('login'));
    }

    public function test_eliminar_un_adjunto_borra_tambien_el_archivo(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();
        $attachment = $this->makeAttachment($document);

        Livewire::test('auto.panel')
            ->call('deleteDocumentAttachment', $attachment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseEmpty('vehicle_document_attachments');
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_no_puede_eliminar_un_adjunto_de_un_auto_ajeno(): void
    {
        $otro = User::factory()->create();
        $documentoAjeno = VehicleDocument::factory()
            ->for(Vehicle::factory()->for($otro))
            ->for($otro)
            ->create();
        $attachment = VehicleDocumentAttachment::factory()->for($documentoAjeno, 'document')->for($otro)->create();

        Vehicle::factory()->for($this->user)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')->call('deleteDocumentAttachment', $attachment->id);
    }

    public function test_eliminar_el_documento_borra_sus_adjuntos_y_archivos(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();
        $attachment = $this->makeAttachment($document);

        Livewire::test('auto.panel')
            ->call('deleteDocument', $document->id)
            ->assertHasNoErrors();

        $this->assertDatabaseEmpty('vehicle_document_attachments');
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_eliminar_el_auto_borra_los_adjuntos_y_archivos(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();
        $attachment = $this->makeAttachment($document);

        Livewire::test('auto.panel')
            ->call('deleteVehicle', $vehicle->id)
            ->assertHasNoErrors();

        $this->assertDatabaseEmpty('vehicle_document_attachments');
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_los_adjuntos_del_documento_se_ven_en_el_panel(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $document = VehicleDocument::factory()->for($vehicle)->for($this->user)->create();
        $attachment = $this->makeAttachment($document);

        $this->get('/auto')
            ->assertSee($attachment->original_name)
            ->assertSee(route('auto.adjunto', $attachment));
    }
}
