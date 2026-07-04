<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' — '.config('app.name') : config('app.name') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=bitter:600,700|inter:400,500,600" rel="stylesheet">

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
                    <a href="{{ url('/') }}" class="hidden items-center gap-3 px-5 py-6 lg:flex">
                        <img src="{{ asset('icon.svg') }}" alt="" aria-hidden="true" class="size-9 rounded-sm">
                        <span class="font-brand text-lg font-bold leading-tight">Amparo Basurto</span>
                    </a>

                    <ul class="flex items-stretch justify-around lg:flex-col lg:gap-1 lg:px-3">
                        <li class="lg:w-full">
                            <a
                                href="{{ route('todos') }}"
                                @if (request()->routeIs('todos')) aria-current="page" @endif
                                class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-5 lg:min-h-11 lg:flex-row lg:justify-start lg:gap-3 lg:rounded-sm {{ request()->routeIs('todos') ? 'text-vino lg:bg-vino/10' : 'text-cuero/70 hover:text-cuero' }}"
                            >
                                {{-- Heroicon: clipboard-document-check (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                                </svg>
                                <span class="text-xs font-medium lg:text-sm">Tareas</span>
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
