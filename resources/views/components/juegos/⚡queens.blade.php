<?php

use App\Support\QueensPuzzle;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Queens')] class extends Component
{
    /** Regiones del tablero (8x8, índice 0..7). Lo único que ve el cliente. */
    public array $regions = [];

    /** Cambia con cada tablero nuevo: re-siembra el componente Alpine. */
    public int $gameId = 0;

    public function mount(): void
    {
        $this->nuevo();
    }

    /** Arma un tablero nuevo con solución única. */
    public function nuevo(): void
    {
        $this->regions = QueensPuzzle::generate()['regions'];
        $this->gameId++;
    }
}; ?>

<div class="space-y-5">
    <div class="flex items-center gap-2">
        <a
            href="{{ route('juegos') }}"
            wire:navigate
            class="grid size-9 place-items-center rounded-sm text-cuero/60 hover:text-cuero focus-visible:outline-2 focus-visible:outline-pizarra"
            aria-label="Volver a Juegos"
        >
            {{-- Heroicon: chevron-left (outline) --}}
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </a>
        <h1 class="font-brand text-2xl font-bold text-pizarra">Queens</h1>
    </div>

    <div
        wire:key="board-{{ $gameId }}"
        x-data="queens({ regions: @js($regions) })"
        class="space-y-4"
    >
        {{-- Estado: reinas puestas, cronómetro y silenciador. --}}
        <div class="flex items-center justify-between text-sm text-cuero/70">
            <span aria-live="polite"><span x-text="queenCount()">0</span> de 8 reinas</span>
            <span class="inline-flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 tabular-nums">
                    {{-- Heroicon: clock (mini) --}}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 text-cuero/50">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
                    </svg>
                    <span x-text="timeLabel">00:00</span>
                </span>
                <button
                    type="button"
                    @click="toggleMute()"
                    :aria-pressed="muted"
                    :aria-label="muted ? 'Activar sonido' : 'Silenciar'"
                    class="grid size-8 place-items-center rounded-sm text-cuero/60 hover:text-cuero focus-visible:outline-2 focus-visible:outline-pizarra"
                >
                    {{-- Heroicon: speaker-wave (mini) --}}
                    <svg x-show="!muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                        <path d="M10 3.75a.75.75 0 0 0-1.264-.546L4.703 7H3.167a.75.75 0 0 0-.7.48A6.985 6.985 0 0 0 2 10c0 .887.165 1.737.468 2.52.111.29.39.48.7.48h1.535l4.033 3.796A.75.75 0 0 0 10 16.25V3.75Z" />
                        <path d="M15.95 5.05a.75.75 0 0 0-1.06 1.061 5.5 5.5 0 0 1 0 7.778.75.75 0 0 0 1.06 1.06 7 7 0 0 0 0-9.899Z" />
                        <path d="M13.829 7.172a.75.75 0 0 0-1.061 1.06 2.5 2.5 0 0 1 0 3.536.75.75 0 0 0 1.06 1.06 4 4 0 0 0 0-5.656Z" />
                    </svg>
                    {{-- Heroicon: speaker (mini) + cruz --}}
                    <svg x-show="muted" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                        <path d="M10 3.75a.75.75 0 0 0-1.264-.546L4.703 7H3.167a.75.75 0 0 0-.7.48A6.985 6.985 0 0 0 2 10c0 .887.165 1.737.468 2.52.111.29.39.48.7.48h1.535l4.033 3.796A.75.75 0 0 0 10 16.25V3.75Z" />
                        <path d="M13.28 7.22a.75.75 0 0 0-1.06 1.06L13.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L15 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L17.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L15 8.94l-1.72-1.72Z" />
                    </svg>
                </button>
            </span>
        </div>

        {{-- Tablero. Un toque cicla la casilla; deslizar el dedo pinta cruces
             (o las borra, si arrancás sobre una). touch-none evita que el gesto
             haga scroll de la página mientras marcás. --}}
        <div
            class="queens-board grid grid-cols-8 touch-none select-none overflow-hidden rounded-sm"
            :class="won && 'is-won'"
            role="grid"
            aria-label="Tablero de Queens, 8 por 8"
            @pointermove="onPointerMove($event)"
            @pointerup.window="onPointerUp()"
            @pointercancel.window="onPointerCancel()"
        >
            <template x-for="cell in cellList" :key="cell.r + '-' + cell.c">
                <button
                    type="button"
                    :data-cell="cell.r + ',' + cell.c"
                    @pointerdown="onPointerDown(cell.r, cell.c)"
                    @click="onCellClick(cell.r, cell.c)"
                    :style="cellBg(cell.r, cell.c) + ';' + cellBorder(cell.r, cell.c) + ';--cell-delay:' + (cell.r + cell.c)"
                    :aria-label="cellLabel(cell.r, cell.c)"
                    class="relative grid aspect-square w-full place-items-center focus:outline-none focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-monte"
                >
                    {{-- Onda dorada de victoria (invisible salvo al ganar) --}}
                    <span class="queens-sheen pointer-events-none absolute inset-0" aria-hidden="true"></span>

                    {{-- Aro de conflicto --}}
                    <span
                        x-show="isBad(cell.r, cell.c)"
                        class="pointer-events-none absolute inset-0 ring-2 ring-inset ring-teja"
                        aria-hidden="true"
                    ></span>

                    {{-- Marca (X): "acá no va reina". A mano o puesta por una reina. --}}
                    <svg
                        x-show="showCross(cell.r, cell.c)"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                        class="size-1/3 text-cuero/45"
                    >
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>

                    {{-- Reina (corona). Al ganar se vuelve dorada (ver .queens-crown en app.css) --}}
                    <svg
                        x-show="showQueen(cell.r, cell.c)"
                        :class="isBad(cell.r, cell.c) ? 'text-teja' : 'text-cuero'"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                        class="queens-crown size-2/3"
                    >
                        <path d="M4 18 L6.5 10 L9.5 13 L12 7 L14.5 13 L17.5 10 L20 18 Z" />
                        <rect x="5" y="19" width="14" height="2.4" rx="1" />
                    </svg>
                </button>
            </template>
        </div>

        {{-- Victoria --}}
        <div
            x-show="won"
            x-cloak
            role="status"
            class="rounded-sm border border-yerba/40 bg-yerba/10 p-4 text-center"
        >
            <p class="font-brand text-lg font-bold text-yerba">Lo resolviste.</p>
            <p class="mt-0.5 text-sm text-cuero/80">Tardaste <span x-text="timeLabel" class="tabular-nums">00:00</span>. Muy bien.</p>
        </div>

        {{-- Controles --}}
        <div class="flex gap-2">
            <button
                type="button"
                @click="reset()"
                class="flex-1 rounded-sm border border-cuero/25 px-4 py-2.5 text-sm font-medium text-cuero hover:bg-cuero/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero"
            >
                Vaciar
            </button>
            <button
                type="button"
                wire:click="nuevo"
                class="flex-1 rounded-sm bg-pizarra px-4 py-2.5 text-sm font-medium text-crema hover:bg-pizarra/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pizarra"
            >
                Tablero nuevo
            </button>
        </div>

        {{-- Cómo se juega --}}
        <details class="group rounded-sm border border-cuero/20 bg-cuero/5 [&_summary]:list-none">
            <summary class="flex cursor-pointer items-center justify-between gap-2 px-4 py-3 text-sm font-medium text-cuero">
                Cómo se juega
                {{-- Heroicon: chevron-down (mini) --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 text-cuero/50 transition-transform group-open:rotate-180">
                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </summary>
            <div class="space-y-2 border-t border-cuero/15 px-4 py-3 text-sm text-cuero/80">
                <p>Poné una reina en cada fila, cada columna y cada color: ocho en total.</p>
                <p>Dos reinas no pueden tocarse, ni siquiera en diagonal.</p>
                <p>Un toque marca la casilla con una cruz (para descartarla), otro pone la reina y otro la deja limpia. Si una reina rompe una regla, la vas a ver en rojo.</p>
                <p>Al poner una reina se <strong>cruzan solas</strong> las casillas que quedan prohibidas por ella (su fila, su columna, su color y las que la tocan). Si sacás la reina, esas cruces se van, pero las que ya habías puesto a mano quedan.</p>
                <p>También podés <strong>deslizar el dedo</strong> por el tablero para ir marcando cruces de corrido; si arrancás el deslizamiento sobre una cruz, en cambio las vas borrando.</p>
            </div>
        </details>
    </div>
</div>
