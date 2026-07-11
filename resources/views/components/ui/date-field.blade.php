@props([
    'model',                 // nombre de la propiedad Livewire (wire:model)
    'id' => null,            // id único para el label/aria (puede repetir el model salvo en listas)
    'label' => null,         // texto del label; null = sin label
    'srLabel' => false,      // label solo para lectores de pantalla
    'optional' => false,     // agrega "(opcional)" al label
    'accent' => 'monte',     // acento del módulo: vino | grafito | oliva | ciruela | monte
    'preset' => 'pasado',    // pasado | tarea | vencimiento | nacimiento
    'min' => null,           // ISO yyyy-mm-dd o null
    'max' => null,           // ISO yyyy-mm-dd o null
    'startView' => null,     // days | months | years (por defecto según preset)
])

@php
    $fieldId = $id ?? $model;
    $resolvedMax = $max ?? ($preset === 'nacimiento' ? today()->toDateString() : null);
    $resolvedStartView = $startView ?? ($preset === 'nacimiento' ? 'years' : 'days');

    // Clases concretas por acento (literales para que Tailwind las genere).
    $c = match ($accent) {
        'vino' => ['bg' => 'bg-vino', 'ring' => 'focus-visible:border-vino focus-visible:ring-vino/40', 'soft' => 'ring-vino/60', 'text' => 'text-vino'],
        'grafito' => ['bg' => 'bg-grafito', 'ring' => 'focus-visible:border-grafito focus-visible:ring-grafito/40', 'soft' => 'ring-grafito/60', 'text' => 'text-grafito'],
        'oliva' => ['bg' => 'bg-oliva', 'ring' => 'focus-visible:border-oliva focus-visible:ring-oliva/40', 'soft' => 'ring-oliva/60', 'text' => 'text-oliva'],
        'ciruela' => ['bg' => 'bg-ciruela', 'ring' => 'focus-visible:border-ciruela focus-visible:ring-ciruela/40', 'soft' => 'ring-ciruela/60', 'text' => 'text-ciruela'],
        default => ['bg' => 'bg-monte', 'ring' => 'focus-visible:border-monte focus-visible:ring-monte/40', 'soft' => 'ring-monte/60', 'text' => 'text-monte'],
    };
@endphp

<div>
    @if ($label)
        <label
            for="{{ $fieldId }}"
            id="{{ $fieldId }}-label"
            @class(['mb-1 block text-sm font-medium', 'sr-only' => $srLabel])
        >
            {{ $label }}
            @if ($optional)
                <span class="font-normal text-cuero/60">(opcional)</span>
            @endif
        </label>
    @endif

    <div
        x-data="dateField({ preset: @js($preset), min: @js($min), max: @js($resolvedMax), startView: @js($resolvedStartView) })"
        x-modelable="value"
        wire:model="{{ $model }}"
        class="relative"
    >
        {{-- Disparador: se ve como un input, abre el calendario propio --}}
        <button
            type="button"
            id="{{ $fieldId }}"
            @if ($label) aria-labelledby="{{ $fieldId }}-label" @endif
            aria-haspopup="dialog"
            :aria-expanded="open"
            @click="openSheet()"
            class="flex min-h-11 w-full items-center gap-2 rounded-sm border border-cuero/30 bg-crema px-3 text-left text-base focus:outline-none focus-visible:ring-2 {{ $c['ring'] }}"
        >
            {{-- Heroicon: calendar-days (outline) --}}
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5 shrink-0 text-cuero/60">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
            <span x-show="display" x-text="display" x-cloak></span>
            <span x-show="!display" x-cloak class="text-cuero/50">Elegí una fecha</span>
        </button>

        {{-- Hoja inferior (móvil) / modal centrado (desktop) --}}
        <template x-if="open">
            <div
                class="fixed inset-0 z-40 flex items-end justify-center sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-label="{{ $label ?? 'Elegí una fecha' }}"
                @keydown.escape.window="close()"
            >
                <div
                    class="absolute inset-0 bg-negro/40"
                    x-transition:enter="transition-opacity ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:leave="transition-opacity ease-in duration-150"
                    x-transition:leave-end="opacity-0"
                    @click="close()"
                ></div>

                <div
                    x-trap.inert.noscroll="open"
                    class="relative z-10 max-h-[85dvh] w-full overflow-y-auto border border-cuero/20 bg-crema p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-[0_-2px_0_0_rgba(91,58,41,0.15)] sm:max-w-sm sm:rounded-sm sm:pb-4 sm:shadow-[0_2px_0_0_rgba(91,58,41,0.2)]"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-end="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                    @click.stop
                >
                    {{-- Chips de acceso rápido --}}
                    <template x-if="chips.length">
                        <div class="mb-3 flex flex-wrap gap-2">
                            <template x-for="chip in chips" :key="chip.label">
                                <button
                                    type="button"
                                    @click="pick(chip.iso)"
                                    class="min-h-11 rounded-sm border border-cuero/25 bg-crema px-3 text-sm font-medium hover:border-cuero/50"
                                    :class="value === chip.iso ? '{{ $c['bg'] }} text-crema border-transparent' : ''"
                                    x-text="chip.label"
                                ></button>
                            </template>
                        </div>
                    </template>

                    {{-- Encabezado: navegación de mes + saltos a mes/año --}}
                    <div class="mb-2 flex items-center gap-1">
                        <button type="button" @click="prevMonth()" aria-label="Mes anterior"
                            class="grid size-11 shrink-0 place-items-center rounded-sm hover:bg-cuero/10 disabled:opacity-30"
                            :disabled="mode !== 'days'">
                            {{-- chevron-left (mini) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 0 1.06L9.06 10l3.73 3.71a.75.75 0 1 1-1.06 1.06l-4.25-4.24a.75.75 0 0 1 0-1.06l4.25-4.24a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div class="flex flex-1 items-center justify-center gap-1">
                            <button type="button" @click="toMonths()"
                                class="min-h-11 rounded-sm px-2 font-brand text-base font-bold capitalize hover:bg-cuero/10"
                                x-text="monthLabel"></button>
                            <button type="button" @click="toYears()"
                                class="min-h-11 rounded-sm px-2 font-brand text-base font-bold hover:bg-cuero/10"
                                x-text="viewYear"></button>
                        </div>

                        <button type="button" @click="nextMonth()" aria-label="Mes siguiente"
                            class="grid size-11 shrink-0 place-items-center rounded-sm hover:bg-cuero/10 disabled:opacity-30"
                            :disabled="mode !== 'days'">
                            {{-- chevron-right (mini) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 0-1.06L10.94 10 7.21 6.29a.75.75 0 1 1 1.06-1.06l4.25 4.24a.75.75 0 0 1 0 1.06l-4.25 4.24a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    {{-- Vista días --}}
                    <div x-show="mode === 'days'">
                        <div class="grid grid-cols-7 text-center text-xs font-medium text-cuero/60">
                            <template x-for="dia in DIAS" :key="dia">
                                <div class="py-1" x-text="dia"></div>
                            </template>
                        </div>
                        <div class="grid grid-cols-7 gap-0.5">
                            <template x-for="(cell, i) in days" :key="i">
                                <div class="min-h-11">
                                    <button
                                        type="button"
                                        x-show="!cell.blank"
                                        x-cloak
                                        @click="pick(cell.iso)"
                                        :disabled="cell.disabled"
                                        :aria-current="cell.isToday ? 'date' : false"
                                        :class="cell.selected
                                            ? '{{ $c['bg'] }} text-crema font-semibold'
                                            : (cell.isToday ? 'ring-1 {{ $c['soft'] }} text-cuero' : 'text-cuero hover:bg-cuero/10')"
                                        class="grid size-full min-h-11 place-items-center rounded-sm text-sm disabled:pointer-events-none disabled:opacity-30"
                                        x-text="cell.d"
                                    ></button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Vista meses --}}
                    <div x-show="mode === 'months'" x-cloak class="grid grid-cols-3 gap-1">
                        <template x-for="(nombre, m) in MESES_CORTOS" :key="m">
                            <button
                                type="button"
                                @click="pickMonth(m)"
                                :class="m === viewMonth ? '{{ $c['bg'] }} text-crema font-semibold' : 'text-cuero hover:bg-cuero/10'"
                                class="min-h-11 rounded-sm text-sm capitalize"
                                x-text="nombre"
                            ></button>
                        </template>
                    </div>

                    {{-- Vista años --}}
                    <div x-show="mode === 'years'" x-cloak x-ref="yearsBox" class="grid max-h-64 grid-cols-4 gap-1 overflow-y-auto">
                        <template x-for="y in years" :key="y">
                            <button
                                type="button"
                                @click="pickYear(y)"
                                :data-current="y === viewYear"
                                :class="y === viewYear ? '{{ $c['bg'] }} text-crema font-semibold' : 'text-cuero hover:bg-cuero/10'"
                                class="min-h-11 rounded-sm text-sm"
                                x-text="y"
                            ></button>
                        </template>
                    </div>

                    {{-- Acciones --}}
                    <div class="mt-3 flex items-center justify-between gap-2 border-t border-cuero/15 pt-3">
                        <button type="button" @click="clear()"
                            class="min-h-11 rounded-sm px-2 text-sm text-cuero/70 hover:text-teja"
                            x-show="value" x-cloak>
                            Borrar
                        </button>
                        <button type="button" @click="close()"
                            class="ml-auto min-h-11 rounded-sm px-4 text-sm font-medium {{ $c['text'] }} hover:bg-cuero/10">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @error($model)
        <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p>
    @enderror
</div>
