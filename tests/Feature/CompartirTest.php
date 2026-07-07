<?php

namespace Tests\Feature;

use App\Models\ShoppingItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompartirTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    // --- La puerta de entrada ------------------------------------------------

    public function test_sin_sesion_manda_a_entrar(): void
    {
        auth()->logout();

        $this->get('/compartir?text=Comprar+yerba')->assertRedirect(route('login'));
    }

    public function test_muestra_el_texto_compartido(): void
    {
        $this->get('/compartir?text=Acordate+del+asado+el+viernes')
            ->assertOk()
            ->assertSee('Acordate del asado el viernes')
            ->assertSee('¿Qué hago con eso?');
    }

    public function test_sin_nada_compartido_lo_explica(): void
    {
        $this->get('/compartir')
            ->assertOk()
            ->assertSee('No me llegó nada esta vez');
    }

    public function test_no_duplica_el_link_que_ya_viene_dentro_del_texto(): void
    {
        Livewire::withQueryParams([
            'text' => 'Mirá esto https://ejemplo.com/nota',
            'url' => 'https://ejemplo.com/nota',
        ])
            ->test('compartir.recibir')
            ->assertSet('draft', 'Mirá esto https://ejemplo.com/nota');
    }

    public function test_junta_titulo_y_texto_cuando_son_distintos(): void
    {
        Livewire::withQueryParams([
            'title' => 'Una nota',
            'text' => 'El contenido de la nota',
        ])
            ->test('compartir.recibir')
            ->assertSet('draft', "Una nota\n\nEl contenido de la nota");
    }

    // --- Guardar como tarea ----------------------------------------------------

    public function test_guarda_el_texto_como_tarea(): void
    {
        Livewire::withQueryParams(['text' => 'Llamar al plomero'])
            ->test('compartir.recibir')
            ->call('saveTask')
            ->assertHasNoErrors()
            ->assertSet('saved', 'tarea')
            ->assertSee('Listo, quedó guardado.');

        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => 'Llamar al plomero',
            'notes' => null,
        ]);
    }

    public function test_un_texto_largo_va_de_titulo_y_notas(): void
    {
        $text = "Presupuesto del plomero\nCambiar la canilla del baño\nTraer los repuestos";

        Livewire::withQueryParams(['text' => $text])
            ->test('compartir.recibir')
            ->call('saveTask')
            ->assertHasNoErrors();

        $todo = $this->user->todos()->sole();

        $this->assertSame('Presupuesto del plomero', $todo->title);
        $this->assertSame($text, $todo->notes);
    }

    public function test_una_primera_linea_eterna_se_recorta_para_el_titulo(): void
    {
        $text = str_repeat('a', 300);

        Livewire::withQueryParams(['text' => $text])
            ->test('compartir.recibir')
            ->call('saveTask')
            ->assertHasNoErrors();

        $todo = $this->user->todos()->sole();

        $this->assertSame(255, mb_strlen($todo->title));
        $this->assertSame($text, $todo->notes);
    }

    public function test_el_texto_se_puede_retocar_antes_de_guardar(): void
    {
        Livewire::withQueryParams(['text' => 'algo con errores'])
            ->test('compartir.recibir')
            ->set('draft', 'Comprar carbón')
            ->call('saveTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => 'Comprar carbón',
        ]);
    }

    public function test_no_guarda_una_tarea_vacia(): void
    {
        Livewire::test('compartir.recibir')
            ->set('draft', '   ')
            ->call('saveTask')
            ->assertHasErrors('draft')
            ->assertSet('saved', '');

        $this->assertSame(0, $this->user->todos()->count());
    }

    // --- Sumar a las compras ----------------------------------------------------

    public function test_suma_la_primera_linea_a_la_lista_de_compras(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();

        Livewire::withQueryParams(['text' => "Yerba\nDe la buena, la que toma tu mamá"])
            ->test('compartir.recibir')
            ->call('saveShoppingItem')
            ->assertHasNoErrors()
            ->assertSet('saved', 'compra')
            ->assertSee($list->name);

        $this->assertDatabaseHas('shopping_items', [
            'shopping_list_id' => $list->id,
            'user_id' => $this->user->id,
            'name' => 'Yerba',
        ]);
    }

    public function test_sin_listas_crea_la_de_siempre(): void
    {
        Livewire::withQueryParams(['text' => 'Fideos'])
            ->test('compartir.recibir')
            ->call('saveShoppingItem')
            ->assertHasNoErrors();

        $list = $this->user->shoppingLists()->sole();

        $this->assertSame(ShoppingList::DEFAULT_NAME, $list->name);
        $this->assertSame('Fideos', $list->items()->sole()->name);
    }

    public function test_no_anota_dos_veces_la_misma_cosa(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        ShoppingItem::factory()->for($list, 'list')->for($this->user)->create(['name' => 'Leche']);

        Livewire::withQueryParams(['text' => 'leche'])
            ->test('compartir.recibir')
            ->call('saveShoppingItem')
            ->assertHasNoErrors()
            ->assertSet('saved', 'compra');

        $this->assertSame(1, $list->items()->count());
    }

    // --- El manifest que registra el share target --------------------------------

    public function test_el_manifest_declara_el_share_target(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertSame('/compartir', $manifest['share_target']['action']);
        $this->assertSame('GET', $manifest['share_target']['method']);
        $this->assertSame('text', $manifest['share_target']['params']['text']);
        $this->assertSame('standalone', $manifest['display']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_el_layout_enlaza_el_manifest(): void
    {
        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('manifest.json');
    }
}
