<?php

namespace Tests\Feature;

use App\Models\FrequentItem;
use App\Models\ShoppingItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComprasListaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_el_primer_ingreso_deja_una_lista_y_los_frecuentes_sembrados(): void
    {
        Livewire::test('compras.lista')->assertHasNoErrors();

        $this->assertDatabaseHas('shopping_lists', [
            'user_id' => $this->user->id,
            'name' => ShoppingList::DEFAULT_NAME,
        ]);
        $this->assertSame(count(FrequentItem::DEFAULTS), $this->user->frequentItems()->count());
    }

    public function test_no_vuelve_a_sembrar_si_ya_tiene_listas(): void
    {
        ShoppingList::factory()->for($this->user)->create();

        Livewire::test('compras.lista')->assertHasNoErrors();

        $this->assertSame(0, $this->user->frequentItems()->count());
        $this->assertSame(1, $this->user->shoppingLists()->count());
    }

    public function test_puede_anotar_una_cosa(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('newItem', 'Pan lactal')
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('shopping_items', [
            'shopping_list_id' => $list->id,
            'user_id' => $this->user->id,
            'name' => 'Pan lactal',
        ]);
    }

    public function test_no_anota_dos_veces_la_misma_cosa(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        ShoppingItem::factory()->for($list, 'list')->for($this->user)->create(['name' => 'Leche']);

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('newItem', 'leche')
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertSame(1, $list->items()->count());
    }

    public function test_al_anotar_queda_guardado_como_frecuente(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('newItem', 'Escarbadientes')
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('frequent_items', [
            'user_id' => $this->user->id,
            'name' => 'Escarbadientes',
            'weight' => 1,
        ]);
    }

    public function test_anotar_no_duplica_el_frecuente_si_ya_existe(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        FrequentItem::factory()->for($this->user)->create(['name' => 'Leche']);

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('newItem', 'leche')
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->user->frequentItems()->count());
    }

    public function test_anotar_y_tachar_suman_ponderacion_y_destachar_la_devuelve(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $frequent = FrequentItem::factory()->for($this->user)->create(['name' => 'Yerba', 'weight' => 0]);

        $component = Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('newItem', 'Yerba')
            ->call('addItem');

        $this->assertSame(1, $frequent->refresh()->weight);

        $item = $list->items()->where('name', 'Yerba')->sole();

        $component->call('toggleItem', $item->id);

        $this->assertSame(2, $frequent->refresh()->weight);

        $component->call('toggleItem', $item->id);

        $this->assertSame(1, $frequent->refresh()->weight);
    }

    public function test_limpiar_con_el_tachito_no_toca_la_ponderacion(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $frequent = FrequentItem::factory()->for($this->user)->create(['name' => 'Yerba', 'weight' => 3]);
        $item = ShoppingItem::factory()->for($list, 'list')->for($this->user)
            ->create(['name' => 'Yerba', 'purchased_at' => now()]);

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->call('removeItem', $item->id);

        $this->assertSame(3, $frequent->refresh()->weight);
    }

    public function test_arrepentirse_de_un_frecuente_devuelve_la_ponderacion(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $frequent = FrequentItem::factory()->for($this->user)->create(['name' => 'Yerba', 'weight' => 0]);

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->call('toggleFrequent', $frequent->id)
            ->call('toggleFrequent', $frequent->id);

        $this->assertSame(0, $frequent->refresh()->weight);
    }

    public function test_los_frecuentes_se_ordenan_por_ponderacion(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        FrequentItem::factory()->for($this->user)->create(['name' => 'Arroz', 'weight' => 0]);
        FrequentItem::factory()->for($this->user)->create(['name' => 'Yerba', 'weight' => 5]);

        $frequents = Livewire::test('compras.lista', ['listId' => $list->id])
            ->instance()->frequents;

        $this->assertSame(['Yerba', 'Arroz'], $frequents->pluck('name')->all());
    }

    public function test_tocar_una_cosa_la_tacha_y_otro_toque_la_destacha(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $item = ShoppingItem::factory()->for($list, 'list')->for($this->user)->create();

        $component = Livewire::test('compras.lista', ['listId' => $list->id])
            ->call('toggleItem', $item->id);

        $this->assertNotNull($item->refresh()->purchased_at);
        $this->assertDatabaseHas('shopping_items', ['id' => $item->id]);

        $component->call('toggleItem', $item->id);

        $this->assertNull($item->refresh()->purchased_at);
    }

    public function test_el_tachito_saca_una_cosa_de_la_lista(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $item = ShoppingItem::factory()->for($list, 'list')->for($this->user)
            ->create(['purchased_at' => now()]);

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->call('removeItem', $item->id);

        $this->assertDatabaseMissing('shopping_items', ['id' => $item->id]);
    }

    public function test_anotar_algo_tachado_lo_destacha_en_vez_de_duplicar(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $item = ShoppingItem::factory()->for($list, 'list')->for($this->user)
            ->create(['name' => 'Leche', 'purchased_at' => now()]);

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('newItem', 'leche')
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertSame(1, $list->items()->count());
        $this->assertNull($item->refresh()->purchased_at);
    }

    public function test_un_frecuente_destacha_lo_tachado_en_vez_de_sacarlo(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $frequent = FrequentItem::factory()->for($this->user)->create(['name' => 'Yerba']);
        $item = ShoppingItem::factory()->for($list, 'list')->for($this->user)
            ->create(['name' => 'Yerba', 'purchased_at' => now()]);

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->call('toggleFrequent', $frequent->id);

        $this->assertSame(1, $list->items()->count());
        $this->assertNull($item->refresh()->purchased_at);
    }

    public function test_un_frecuente_se_suma_y_se_saca_con_el_mismo_toque(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $frequent = FrequentItem::factory()->for($this->user)->create(['name' => 'Yerba']);

        $component = Livewire::test('compras.lista', ['listId' => $list->id])
            ->call('toggleFrequent', $frequent->id);

        $this->assertDatabaseHas('shopping_items', [
            'shopping_list_id' => $list->id,
            'name' => 'Yerba',
        ]);

        $component->call('toggleFrequent', $frequent->id);

        $this->assertSame(0, $list->items()->count());
    }

    public function test_puede_guardar_y_olvidar_frecuentes(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();

        $component = Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('newFrequent', 'Palta')
            ->call('addFrequent')
            ->assertHasNoErrors();

        $frequent = $this->user->frequentItems()->where('name', 'Palta')->firstOrFail();

        $component->call('removeFrequent', $frequent->id);

        $this->assertDatabaseMissing('frequent_items', ['id' => $frequent->id]);
    }

    public function test_puede_crear_renombrar_y_eliminar_listas(): void
    {
        $existing = ShoppingList::factory()->for($this->user)->create(['name' => 'Súper']);

        $component = Livewire::test('compras.lista', ['listId' => $existing->id])
            ->set('newListName', 'Farmacia')
            ->call('createList')
            ->assertHasNoErrors();

        $nueva = $this->user->shoppingLists()->where('name', 'Farmacia')->sole();

        $component->call('startEditingList')
            ->set('editListName', 'Farmacia del barrio')
            ->call('saveList')
            ->assertHasNoErrors();

        $this->assertSame('Farmacia del barrio', $nueva->refresh()->name);

        $component->call('deleteList');

        $this->assertDatabaseMissing('shopping_lists', ['id' => $nueva->id]);
    }

    public function test_no_puede_operar_una_lista_ajena(): void
    {
        $other = User::factory()->create();
        $foreign = ShoppingList::factory()->for($other)->create();

        $this->expectException(ModelNotFoundException::class);

        // Forzamos el id ajeno después del mount (que normaliza a lo accesible)
        // para ejercitar el findOrFail que protege las acciones.
        Livewire::test('compras.lista')
            ->set('listId', $foreign->id)
            ->set('newItem', 'Intruso')
            ->call('addItem');
    }

    public function test_quien_tiene_la_lista_compartida_puede_anotar(): void
    {
        $owner = User::factory()->create();
        $shared = ShoppingList::factory()->for($owner)->create();
        $shared->members()->attach($this->user);

        Livewire::test('compras.lista', ['listId' => $shared->id])
            ->set('newItem', 'Leche')
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('shopping_items', [
            'shopping_list_id' => $shared->id,
            'user_id' => $this->user->id,
            'name' => 'Leche',
        ]);
    }

    public function test_solo_el_dueno_comparte_la_lista(): void
    {
        $owner = User::factory()->create();
        $shared = ShoppingList::factory()->for($owner)->create();
        $shared->members()->attach($this->user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('compras.lista', ['listId' => $shared->id])
            ->set('shareUsername', 'alguien')
            ->call('share');
    }

    public function test_el_dueno_comparte_por_usuario(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $friend = User::factory()->create(['username' => 'vecina']);

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('shareUsername', 'Vecina')
            ->call('share')
            ->assertHasNoErrors();

        $this->assertTrue($list->members()->whereKey($friend->id)->exists());
    }

    public function test_avisa_si_el_usuario_a_compartir_no_existe(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();

        Livewire::test('compras.lista', ['listId' => $list->id])
            ->set('shareUsername', 'fantasma')
            ->call('share')
            ->assertHasErrors('shareUsername');

        $this->assertSame(0, $list->members()->count());
    }

    public function test_eliminar_la_lista_borra_sus_cosas(): void
    {
        $list = ShoppingList::factory()->for($this->user)->create();
        $item = ShoppingItem::factory()->for($list, 'list')->for($this->user)->create();

        $list->delete();

        $this->assertDatabaseMissing('shopping_items', ['id' => $item->id]);
    }
}
