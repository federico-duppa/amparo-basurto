<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' — '.config('app.name') : config('app.name') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=bitter:600,700|inter:400,500,600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-dvh lg:flex">
            @auth
                {{-- Bottom nav en móvil, sidebar en desktop --}}
                <nav
                    aria-label="Módulos"
                    class="fixed inset-x-0 bottom-0 z-10 border-t border-cuero/20 bg-crema pb-[env(safe-area-inset-bottom)] lg:static lg:flex lg:min-h-dvh lg:w-60 lg:shrink-0 lg:flex-col lg:border-t-0 lg:border-r"
                >
                    <a href="{{ url('/') }}" wire:navigate class="hidden items-center gap-3 px-5 py-6 lg:flex">
                        <img src="{{ asset('icon.svg') }}" alt="" aria-hidden="true" class="size-9 rounded-sm">
                        <span class="font-brand text-lg font-bold leading-tight">Amparo Basurto</span>
                    </a>

                    <ul class="flex items-stretch justify-around lg:flex-col lg:gap-1 lg:px-3">
                        <li class="flex-1 lg:w-full">
                            <a
                                href="{{ route('todos') }}"
                                wire:navigate
                                @if (request()->routeIs('todos')) aria-current="page" @endif
                                class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 lg:min-h-11 lg:flex-row lg:px-3 lg:justify-start lg:gap-3 lg:rounded-sm {{ request()->routeIs('todos') ? 'text-vino lg:bg-vino/10' : 'text-cuero/70 hover:text-cuero' }}"
                            >
                                {{-- Heroicon: clipboard-document-check (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                                </svg>
                                <span class="text-xs font-medium lg:text-sm">Tareas</span>
                            </a>
                        </li>
                        <li class="flex-1 lg:w-full">
                            <a
                                href="{{ route('auto') }}"
                                wire:navigate
                                @if (request()->routeIs('auto')) aria-current="page" @endif
                                class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 lg:min-h-11 lg:flex-row lg:px-3 lg:justify-start lg:gap-3 lg:rounded-sm {{ request()->routeIs('auto') ? 'text-grafito lg:bg-grafito/10' : 'text-cuero/70 hover:text-cuero' }}"
                            >
                                {{-- Heroicon: wrench-screwdriver (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                                </svg>
                                <span class="text-xs font-medium lg:text-sm">Auto</span>
                            </a>
                        </li>
                        <li class="flex-1 lg:w-full">
                            <a
                                href="{{ route('salud') }}"
                                wire:navigate
                                @if (request()->routeIs('salud')) aria-current="page" @endif
                                class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 lg:min-h-11 lg:flex-row lg:px-3 lg:justify-start lg:gap-3 lg:rounded-sm {{ request()->routeIs('salud') ? 'text-ciruela lg:bg-ciruela/10' : 'text-cuero/70 hover:text-cuero' }}"
                            >
                                {{-- Heroicon: heart (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                </svg>
                                <span class="text-xs font-medium lg:text-sm">Salud</span>
                            </a>
                        </li>
                        <li class="flex-1 lg:w-full">
                            <a
                                href="{{ route('compras') }}"
                                wire:navigate
                                @if (request()->routeIs('compras')) aria-current="page" @endif
                                class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 lg:min-h-11 lg:flex-row lg:px-3 lg:justify-start lg:gap-3 lg:rounded-sm {{ request()->routeIs('compras') ? 'text-cobre lg:bg-cobre/10' : 'text-cuero/70 hover:text-cuero' }}"
                            >
                                {{-- Heroicon: shopping-cart (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                </svg>
                                <span class="text-xs font-medium lg:text-sm">Compras</span>
                            </a>
                        </li>
                        <li class="flex-1 lg:w-full">
                            <a
                                href="{{ route('plata.gastos') }}"
                                wire:navigate
                                @if (request()->routeIs('plata.*')) aria-current="page" @endif
                                class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 lg:min-h-11 lg:flex-row lg:px-3 lg:justify-start lg:gap-3 lg:rounded-sm {{ request()->routeIs('plata.*') ? 'text-oliva lg:bg-oliva/10' : 'text-cuero/70 hover:text-cuero' }}"
                            >
                                {{-- Heroicon: banknotes (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                                </svg>
                                <span class="text-xs font-medium lg:text-sm">Plata</span>
                            </a>
                        </li>
                        <li class="flex-1 lg:w-full">
                            <a
                                href="{{ route('juegos') }}"
                                wire:navigate
                                @if (request()->routeIs('juegos*')) aria-current="page" @endif
                                class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 lg:min-h-11 lg:flex-row lg:justify-start lg:gap-3 lg:rounded-sm lg:px-3 {{ request()->routeIs('juegos*') ? 'text-pizarra lg:bg-pizarra/10' : 'text-cuero/70 hover:text-cuero' }}"
                            >
                                {{-- Heroicon: puzzle-piece (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0-.582-4.717.532.532 0 0 1 .533-.57v0c.355 0 .676.186.959.401.29.221.634.349 1.003.349 1.035 0 1.875-1.007 1.875-2.25s-.84-2.25-1.875-2.25c-.37 0-.713.128-1.003.349-.283.215-.604.401-.959.401v0a.656.656 0 0 1-.658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z" />
                                </svg>
                                <span class="text-xs font-medium lg:text-sm">Juegos</span>
                            </a>
                        </li>
                    </ul>

                    <div class="hidden border-t border-cuero/20 px-5 py-3 lg:mt-auto lg:flex lg:items-center lg:justify-between lg:gap-2">
                        <span class="truncate text-sm text-cuero/70">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                aria-label="Cerrar sesión"
                                class="grid size-11 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja"
                            >
                                {{-- Heroicon: arrow-right-start-on-rectangle (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </nav>
            @endauth

            <div class="flex-1 pb-24 lg:pb-0">
                @auth
                    <header class="mx-auto flex w-full max-w-2xl items-center gap-2.5 px-4 pt-5 lg:hidden">
                        <img src="{{ asset('icon.svg') }}" alt="" aria-hidden="true" class="size-7 rounded-sm">
                        <span class="font-brand text-base font-bold">Amparo Basurto</span>
                        <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                            @csrf
                            <button
                                type="submit"
                                aria-label="Cerrar sesión"
                                class="grid size-11 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja"
                            >
                                {{-- Heroicon: arrow-right-start-on-rectangle (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                </svg>
                            </button>
                        </form>
                    </header>
                @endauth

                <main class="mx-auto w-full max-w-2xl px-4 py-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
