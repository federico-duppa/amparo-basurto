<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Juegos')] class extends Component
{
    /**
     * Catálogo de juegos. Cada uno se agrega acá con su ruta; los que todavía no
     * están se muestran como "en camino" para dar a entender que la sección crece.
     */
    public function games(): array
    {
        return [
            [
                'nombre' => 'Queens',
                'resumen' => 'Una reina por fila, por columna y por color, sin que se toquen. Grilla de 8 por 8.',
                'ruta' => 'juegos.queens',
            ],
            [
                'nombre' => 'Sol y luna',
                'resumen' => 'Soles y lunas mitad y mitad, sin tres seguidos, respetando los = y los ×. Grilla de 6 por 6.',
                'ruta' => 'juegos.solyluna',
            ],
        ];
    }
}; ?>

<div class="space-y-6">
    <header>
        <h1 class="font-brand text-2xl font-bold text-pizarra">Juegos</h1>
        <p class="mt-1 text-sm text-cuero/70">Elegí con qué despejarte un rato.</p>
    </header>

    <ul class="space-y-3">
        @foreach ($this->games() as $game)
            <li>
                <a
                    href="{{ route($game['ruta']) }}"
                    wire:navigate
                    class="flex items-center gap-4 rounded-sm border border-pizarra/25 bg-pizarra/5 p-4 transition-colors hover:bg-pizarra/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pizarra"
                >
                    <span class="grid size-12 shrink-0 place-items-center rounded-sm bg-pizarra/10 text-pizarra" aria-hidden="true">
                        {{-- Heroicon: puzzle-piece (outline) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0-.582-4.717.532.532 0 0 1 .533-.57v0c.355 0 .676.186.959.401.29.221.634.349 1.003.349 1.035 0 1.875-1.007 1.875-2.25s-.84-2.25-1.875-2.25c-.37 0-.713.128-1.003.349-.283.215-.604.401-.959.401v0a.656.656 0 0 1-.658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z" />
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-brand text-lg font-bold text-cuero">{{ $game['nombre'] }}</span>
                        <span class="mt-0.5 block text-sm text-cuero/70">{{ $game['resumen'] }}</span>
                    </span>
                    <span class="ml-auto shrink-0 text-pizarra/60" aria-hidden="true">
                        {{-- Heroicon: chevron-right (mini) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                            <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
