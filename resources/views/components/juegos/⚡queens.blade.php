<?php

use App\Livewire\Concerns\RecordsGameResults;
use Livewire\Attributes\Title;
use Livewire\Component;

// La partida se arma y se juega entera en el navegador (ver queens.js y el
// Alpine.data('queens') en resources/js/app.js): este componente entrega el
// marco de la página con los números del usuario (racha, mejor tiempo, si el
// del día ya está) y anota las victorias vía recordWin (RecordsGameResults).
new #[Title('Queens')] class extends Component
{
    use RecordsGameResults;

    protected function gameKey(): string
    {
        return 'queens';
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
        x-data="queens(@js($this->gameState()))"
        class="space-y-4"
    >
        {{-- El del día (mismo tablero para todos, sostiene la racha) o libre. --}}
        <div role="group" aria-label="Elegí el tablero" class="flex gap-2">
            <button
                type="button"
                @click="startDaily()"
                :aria-pressed="mode === 'daily'"
                :class="mode === 'daily' ? 'border-pizarra bg-pizarra text-crema' : 'border-cuero/25 text-cuero hover:bg-cuero/5'"
                class="flex-1 rounded-sm border px-3 py-2.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pizarra"
            >
                El del día
            </button>
            <button
                type="button"
                @click="startFree()"
                :aria-pressed="mode === 'free'"
                :class="mode === 'free' ? 'border-pizarra bg-pizarra text-crema' : 'border-cuero/25 text-cuero hover:bg-cuero/5'"
                class="flex-1 rounded-sm border px-3 py-2.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pizarra"
            >
                Libre
            </button>
        </div>

        {{-- Racha y mejor tiempo; y el aviso de que el de hoy ya está. --}}
        <p x-show="statsLabel" x-cloak x-text="statsLabel" class="text-sm text-cuero/70"></p>
        <p x-show="dailyNotice" x-cloak x-text="dailyNotice" class="text-sm text-ocre-oscuro"></p>

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

                    {{-- Aro de pista: ocre si "acá va una reina", teja si hay un error --}}
                    <span
                        x-show="isHint(cell.r, cell.c)"
                        x-cloak
                        :class="hintKind === 'error' ? 'ring-teja' : 'ring-ocre'"
                        class="pointer-events-none absolute inset-0 z-10 animate-pulse ring-4 ring-inset"
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

        {{-- Mensaje de la pista --}}
        <p
            x-show="hintMessage"
            x-cloak
            aria-live="polite"
            :class="hintKind === 'error' ? 'text-teja' : 'text-ocre-oscuro'"
            class="flex items-center gap-1.5 text-sm"
        >
            {{-- Heroicon: light-bulb (mini) --}}
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 shrink-0">
                <path d="M10 1a6 6 0 0 0-3.815 10.631C7.237 12.5 8 13.443 8 14.456v.644a.75.75 0 0 0 .572.729 6.016 6.016 0 0 0 2.856 0A.75.75 0 0 0 12 15.1v-.644c0-1.013.763-1.956 1.815-2.825A6 6 0 0 0 10 1ZM8.863 17.414a.75.75 0 0 0-.226 1.483 9.066 9.066 0 0 0 2.726 0 .75.75 0 0 0-.226-1.483 7.553 7.553 0 0 1-2.274 0Z" />
            </svg>
            <span x-text="hintMessage"></span>
        </p>

        {{-- Victoria --}}
        <div
            x-show="won"
            x-cloak
            role="status"
            class="rounded-sm border border-yerba/40 bg-yerba/10 p-4 text-center"
        >
            <p class="font-brand text-lg font-bold text-yerba">Lo resolviste.</p>
            <p class="mt-0.5 text-sm text-cuero/80">Tardaste <span x-text="timeLabel" class="tabular-nums">00:00</span>. Muy bien.</p>
            <p x-show="mode === 'daily' && streak > 0" x-cloak class="mt-0.5 text-sm text-cuero/80">Racha: <span x-text="streakLabel"></span>.</p>
        </div>

        {{-- Controles. Arriba las ayudas (pista, deshacer); abajo empezar de nuevo. --}}
        <div class="space-y-2">
            <div class="flex gap-2">
                <button
                    type="button"
                    @click="pista()"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-sm border border-cuero/25 px-3 py-2.5 text-sm font-medium text-cuero hover:bg-cuero/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero"
                >
                    {{-- Heroicon: light-bulb (mini) --}}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                        <path d="M10 1a6 6 0 0 0-3.815 10.631C7.237 12.5 8 13.443 8 14.456v.644a.75.75 0 0 0 .572.729 6.016 6.016 0 0 0 2.856 0A.75.75 0 0 0 12 15.1v-.644c0-1.013.763-1.956 1.815-2.825A6 6 0 0 0 10 1ZM8.863 17.414a.75.75 0 0 0-.226 1.483 9.066 9.066 0 0 0 2.726 0 .75.75 0 0 0-.226-1.483 7.553 7.553 0 0 1-2.274 0Z" />
                    </svg>
                    Pista
                </button>
                <button
                    type="button"
                    @click="undo()"
                    :disabled="!canUndo"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-sm border border-cuero/25 px-3 py-2.5 text-sm font-medium text-cuero hover:bg-cuero/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero disabled:pointer-events-none disabled:opacity-40"
                >
                    {{-- Heroicon: arrow-uturn-left (mini) --}}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                        <path fill-rule="evenodd" d="M7.793 2.232a.75.75 0 0 1-.025 1.06L3.622 7.25h10.003a5.375 5.375 0 0 1 0 10.75H10.75a.75.75 0 0 1 0-1.5h2.875a3.875 3.875 0 0 0 0-7.75H3.622l4.146 3.957a.75.75 0 0 1-1.036 1.085l-5.5-5.25a.75.75 0 0 1 0-1.085l5.5-5.25a.75.75 0 0 1 1.06.025Z" clip-rule="evenodd" />
                    </svg>
                    Deshacer
                </button>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    @click="vaciar()"
                    class="flex-1 rounded-sm border border-cuero/25 px-3 py-2.5 text-sm font-medium text-cuero hover:bg-cuero/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero"
                >
                    Vaciar
                </button>
                <button
                    x-show="mode === 'free'"
                    type="button"
                    @click="newGame()"
                    class="flex-1 rounded-sm bg-pizarra px-3 py-2.5 text-sm font-medium text-crema hover:bg-pizarra/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pizarra"
                >
                    Tablero nuevo
                </button>
            </div>
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
                <p><strong>El del día</strong> es el mismo tablero para todos y cambia con la fecha: resolverlo sostiene tu racha. En <strong>Libre</strong> jugás todos los tableros que quieras; el mejor tiempo cuenta en los dos.</p>
                <p>Poné una reina en cada fila, cada columna y cada color: ocho en total.</p>
                <p>Dos reinas no pueden tocarse, ni siquiera en diagonal.</p>
                <p>Un toque marca la casilla con una cruz (para descartarla), otro pone la reina y otro la deja limpia. Si una reina rompe una regla, la vas a ver en rojo.</p>
                <p>Al poner una reina se <strong>cruzan solas</strong> las casillas que quedan prohibidas por ella (su fila, su columna, su color y las que la tocan). Si sacás la reina, esas cruces se van, pero las que ya habías puesto a mano quedan.</p>
                <p>También podés <strong>deslizar el dedo</strong> por el tablero para ir marcando cruces de corrido; si arrancás el deslizamiento sobre una cruz, en cambio las vas borrando.</p>
                <p><strong>Deshacer</strong> vuelve atrás la última acción, y podés seguir tocándolo para desandar todo paso a paso. <strong>Vaciar</strong> limpia el tablero.</p>
                <p>Si te trabás, <strong>Pista</strong> te da una mano con lógica: si hay algo mal puesto te lo marca; si hay casillas que se pueden descartar por deducción, te las tacha y te cuenta el porqué; y solo te señala dónde va una reina cuando es una certeza total (esa la ponés vos).</p>
            </div>
        </details>
    </div>
</div>
