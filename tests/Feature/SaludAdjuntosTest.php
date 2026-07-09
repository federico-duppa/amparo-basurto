<?php

namespace Tests\Feature;

use App\Models\HealthAttachment;
use App\Models\HealthEntry;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SaludAdjuntosTest extends TestCase
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
    private function makeAttachment(HealthRecord $record, ?HealthEntry $entry = null): HealthAttachment
    {
        $path = 'salud/'.$record->id.'/'.fake()->uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');

        return HealthAttachment::factory()
            ->for($record, 'record')
            ->for($this->user)
            ->create([
                'health_entry_id' => $entry?->id,
                'path' => $path,
            ]);
    }

    public function test_puede_subir_un_pdf_suelto_a_la_historia(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();

        Livewire::test('salud.panel')
            ->set('recordFile', UploadedFile::fake()->create('estudio.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors();

        $attachment = HealthAttachment::sole();
        $this->assertSame($record->id, $attachment->health_record_id);
        $this->assertNull($attachment->health_entry_id);
        $this->assertSame($this->user->id, $attachment->user_id);
        $this->assertSame('estudio.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_rechaza_archivos_que_no_son_pdf(): void
    {
        HealthRecord::factory()->for($this->user)->create();

        Livewire::test('salud.panel')
            ->set('recordFile', UploadedFile::fake()->create('foto.jpg', 120, 'image/jpeg'))
            ->assertHasErrors(['recordFile' => 'mimes']);

        $this->assertDatabaseEmpty('health_attachments');
    }

    public function test_rechaza_un_pdf_de_mas_de_10_mb(): void
    {
        HealthRecord::factory()->for($this->user)->create();

        Livewire::test('salud.panel')
            ->set('recordFile', UploadedFile::fake()->create('pesado.pdf', 11_000, 'application/pdf'))
            ->assertHasErrors(['recordFile' => 'max']);

        $this->assertDatabaseEmpty('health_attachments');
    }

    public function test_puede_adjuntar_pdfs_al_anotar_una_entrada(): void
    {
        HealthRecord::factory()->for($this->user)->create();

        Livewire::test('salud.panel')
            ->set('entryTitle', 'Análisis de sangre')
            ->set('entryFile', UploadedFile::fake()->create('resultado.pdf', 80, 'application/pdf'))
            ->set('entryFile', UploadedFile::fake()->create('orden.pdf', 40, 'application/pdf'))
            ->call('addEntry')
            ->assertHasNoErrors();

        $entry = HealthEntry::sole();
        $this->assertSame(2, $entry->attachments()->count());
        $this->assertDatabaseHas('health_attachments', [
            'health_entry_id' => $entry->id,
            'original_name' => 'resultado.pdf',
        ]);
    }

    public function test_la_tanda_de_una_entrada_admite_hasta_10_archivos(): void
    {
        HealthRecord::factory()->for($this->user)->create();

        $component = Livewire::test('salud.panel');

        foreach (range(1, 10) as $i) {
            $component->set('entryFile', UploadedFile::fake()->create("archivo-{$i}.pdf", 10, 'application/pdf'));
        }

        $component->assertHasNoErrors()
            ->set('entryFile', UploadedFile::fake()->create('archivo-11.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['entryFiles']);
    }

    public function test_puede_adjuntar_un_pdf_al_editar_una_entrada(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $entry = HealthEntry::factory()->for($record, 'record')->for($this->user)->create();

        Livewire::test('salud.panel')
            ->call('startEditingEntry', $entry->id)
            ->set('editEntryFile', UploadedFile::fake()->create('receta.pdf', 60, 'application/pdf'))
            ->call('saveEntry')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_attachments', [
            'health_entry_id' => $entry->id,
            'original_name' => 'receta.pdf',
        ]);
    }

    public function test_quien_tiene_la_historia_compartida_puede_subir_adjuntos(): void
    {
        $owner = User::factory()->create();
        $record = HealthRecord::factory()->for($owner)->create();
        $record->members()->attach($this->user);

        Livewire::test('salud.panel')
            ->set('recordFile', UploadedFile::fake()->create('certificado.pdf', 50, 'application/pdf'))
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_attachments', [
            'health_record_id' => $record->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_puede_descargar_un_adjunto_propio(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $attachment = $this->makeAttachment($record);

        // Descarga directa con el nombre original, sin redirigir a otra URL:
        // en la PWA instalada una navegación externa saca al usuario de la app.
        $this->get(route('salud.adjunto', $attachment))
            ->assertOk()
            ->assertDownload($attachment->original_name);
    }

    public function test_quien_tiene_la_historia_compartida_puede_descargar(): void
    {
        $owner = User::factory()->create();
        $record = HealthRecord::factory()->for($owner)->create();
        $record->members()->attach($this->user);
        $attachment = HealthAttachment::factory()->for($record, 'record')->for($owner)->create([
            'path' => tap('salud/'.$record->id.'/compartido.pdf', fn ($path) => Storage::disk('local')->put($path, '%PDF-1.4 fake')),
        ]);

        $this->get(route('salud.adjunto', $attachment))->assertOk();
    }

    public function test_no_se_puede_descargar_un_adjunto_ajeno(): void
    {
        $otro = User::factory()->create();
        $record = HealthRecord::factory()->for($otro)->create();
        $attachment = HealthAttachment::factory()->for($record, 'record')->for($otro)->create();

        $this->get(route('salud.adjunto', $attachment))->assertNotFound();
    }

    public function test_sin_iniciar_sesion_no_se_descarga_nada(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $attachment = $this->makeAttachment($record);

        auth()->logout();

        $this->get(route('salud.adjunto', $attachment))->assertRedirect(route('login'));
    }

    public function test_eliminar_un_adjunto_borra_tambien_el_archivo(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $attachment = $this->makeAttachment($record);

        Livewire::test('salud.panel')
            ->call('deleteAttachment', $attachment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseEmpty('health_attachments');
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_no_puede_eliminar_un_adjunto_de_una_historia_ajena(): void
    {
        $otro = User::factory()->create();
        $recordAjeno = HealthRecord::factory()->for($otro)->create();
        $attachment = HealthAttachment::factory()->for($recordAjeno, 'record')->for($otro)->create();

        HealthRecord::factory()->for($this->user)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('salud.panel')->call('deleteAttachment', $attachment->id);
    }

    public function test_eliminar_una_entrada_borra_sus_adjuntos_y_archivos(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $entry = HealthEntry::factory()->for($record, 'record')->for($this->user)->create();
        $attachment = $this->makeAttachment($record, $entry);

        Livewire::test('salud.panel')
            ->call('deleteEntry', $entry->id)
            ->assertHasNoErrors();

        $this->assertDatabaseEmpty('health_attachments');
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_eliminar_la_historia_borra_todos_los_adjuntos_y_archivos(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $entry = HealthEntry::factory()->for($record, 'record')->for($this->user)->create();
        $suelto = $this->makeAttachment($record);
        $deEntrada = $this->makeAttachment($record, $entry);

        Livewire::test('salud.panel')
            ->call('deleteRecord', $record->id)
            ->assertHasNoErrors();

        $this->assertDatabaseEmpty('health_attachments');
        Storage::disk('local')->assertMissing($suelto->path);
        Storage::disk('local')->assertMissing($deEntrada->path);
    }

    public function test_los_adjuntos_de_una_entrada_se_ven_en_el_timeline(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $entry = HealthEntry::factory()->for($record, 'record')->for($this->user)->create();
        $this->makeAttachment($record, $entry);

        $attachment = $entry->attachments()->first();

        $this->get('/salud')
            ->assertSee($attachment->original_name)
            ->assertSee(route('salud.adjunto', $attachment));
    }
}
