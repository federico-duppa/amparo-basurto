<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Historia de {{ $record->titular }} — {{ config('app.name') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=bitter:600,700|inter:400,500,600&display=swap" rel="stylesheet">

        @vite('resources/css/app.css')

        <style>
            @page { margin: 1.6cm; }
        </style>
    </head>
    <body class="bg-crema text-cuero print:bg-white">
        {{-- Watermark: el monograma AB en trazo cuero, apenas presente --}}
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true"
            class="pointer-events-none fixed bottom-6 right-6 size-40 opacity-[0.07]">
            <g fill="none" stroke="#5B3A29" stroke-width="4" stroke-linecap="square">
                <path d="M14 46 L25 18 L36 46"/>
                <path d="M42 18 V46"/>
                <path d="M42 18 a7 7 0 0 1 0 14 a7 7 0 0 1 0 14"/>
            </g>
        </svg>

        <div class="mx-auto max-w-2xl px-4 py-6 print:max-w-none print:px-0 print:py-0">
            {{-- Acciones (no se imprimen) --}}
            <div class="mb-6 flex flex-wrap items-center gap-2 print:hidden">
                <a href="{{ route('salud') }}"
                    class="min-h-11 rounded-sm border border-cuero/30 px-3 text-sm leading-[2.75rem] text-cuero/80 hover:text-cuero">
                    ← Volver a Salud
                </a>
                <button type="button" onclick="window.print()"
                    class="ml-auto min-h-11 rounded-sm bg-monte px-4 text-sm font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                    Imprimir o guardar como PDF
                </button>
            </div>

            {{-- Encabezado del reporte --}}
            <header class="border-b-2 border-cuero pb-4">
                <div class="flex items-start gap-3">
                    <img src="{{ asset('icon.svg') }}" alt="" aria-hidden="true" class="size-10 rounded-sm">
                    <div class="min-w-0 flex-1">
                        <p class="font-brand text-sm font-semibold text-cuero/70">{{ config('app.name') }} · Historia clínica</p>
                        <h1 class="font-brand text-3xl font-bold leading-tight">{{ $record->titular }}</h1>
                        <p class="mt-1 text-sm text-cuero/70">
                            {{ \App\Models\HealthRecord::TIPOS[$record->tipo] ?? 'Persona' }}
                            @if ($record->nacimiento && ! $record->esDocumento())
                                · Nació el {{ $record->nacimiento->format('d/m/Y') }}
                                @if ($record->edad() !== null)
                                    ({{ $record->edad() }} {{ $record->edad() === 1 ? 'año' : 'años' }})
                                @endif
                            @endif
                        </p>
                    </div>
                </div>
            </header>

            {{-- Ficha --}}
            <section class="mt-6 break-inside-avoid">
                <h2 class="font-brand text-lg font-bold">Ficha</h2>
                <dl class="mt-2 space-y-2 text-sm">
                    @if ($record->esMascota())
                        <div class="flex gap-2">
                            <dt class="w-36 shrink-0 font-medium text-cuero/70">Especie / raza</dt>
                            <dd>{{ trim(($record->especie ?? '').' · '.($record->raza ?? ''), ' ·') ?: '—' }}</dd>
                        </div>
                    @endif
                    @if ($record->esPersona())
                        <div class="flex gap-2">
                            <dt class="w-36 shrink-0 font-medium text-cuero/70">Grupo sanguíneo</dt>
                            <dd>{{ $record->grupo_sanguineo ?: '—' }}</dd>
                        </div>
                    @endif
                    @unless ($record->esDocumento())
                        <div class="flex gap-2">
                            <dt class="w-36 shrink-0 font-medium text-cuero/70">{{ $record->esMascota() ? 'Veterinaria' : 'Obra social' }}</dt>
                            <dd>{{ $record->obra_social ?: '—' }}</dd>
                        </div>
                    @endunless
                    <div class="flex gap-2">
                        <dt class="w-36 shrink-0 font-medium text-cuero/70">Alergias</dt>
                        <dd @class(['bg-ocre px-1 font-medium text-negro' => (bool) $record->alergias])>{{ $record->alergias ?: '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-36 shrink-0 font-medium text-cuero/70">Condiciones</dt>
                        <dd>{{ $record->condiciones ?: '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-36 shrink-0 font-medium text-cuero/70">Medicación actual</dt>
                        <dd>{{ $record->medicacion ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Vencimientos --}}
            @if ($reminders->isNotEmpty())
                <section class="mt-6 break-inside-avoid">
                    <h2 class="border-b border-cuero/30 pb-1 font-brand text-lg font-bold">Vencimientos</h2>
                    <ul class="mt-2 space-y-1.5 text-sm">
                        @foreach ($reminders as $row)
                            <li class="flex flex-wrap items-baseline gap-x-2">
                                <span class="font-medium">{{ $row['reminder']->name }}</span>
                                <span class="text-cuero/70">{{ $row['status']['headline'] }} · {{ $row['reminder']->expires_on->format('d/m/Y') }}</span>
                                @if ($row['reminder']->note)
                                    <span class="w-full text-xs text-cuero/60">{{ $row['reminder']->note }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Vacunas --}}
            @if ($vaccineGroups->isNotEmpty())
                <section class="mt-6">
                    <h2 class="border-b border-cuero/30 pb-1 font-brand text-lg font-bold">Vacunas</h2>
                    <div class="mt-2 space-y-3 text-sm">
                        @foreach ($vaccineGroups as $name => $applications)
                            <div class="break-inside-avoid">
                                <h3 class="font-medium">{{ $name }}</h3>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($applications as $vaccine)
                                        <li class="text-cuero/80">
                                            {{ $vaccine->applied_on->format('d/m/Y') }}@if ($vaccine->dose) · {{ $vaccine->dose }}@endif
                                            @if ($vaccine->next_due_on) · próxima: {{ $vaccine->next_due_on->format('d/m/Y') }}@endif
                                            @if ($vaccine->note) <span class="text-xs text-cuero/60">({{ $vaccine->note }})</span>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Mediciones --}}
            @if ($measurementGroups->isNotEmpty())
                <section class="mt-6">
                    <h2 class="border-b border-cuero/30 pb-1 font-brand text-lg font-bold">Mediciones</h2>
                    <div class="mt-2 space-y-3 text-sm">
                        @foreach ($measurementGroups as $type => $measurements)
                            <div class="break-inside-avoid">
                                <h3 class="font-medium">{{ \App\Models\HealthMeasurement::TYPES[$type]['label'] }}</h3>
                                <ul class="mt-1 space-y-0.5">
                                    @foreach ($measurements as $measurement)
                                        <li class="text-cuero/80">{{ $measurement->measured_on->format('d/m/Y') }} · {{ $measurement->formattedValue() }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Contactos --}}
            @if ($contacts->isNotEmpty())
                <section class="mt-6 break-inside-avoid">
                    <h2 class="border-b border-cuero/30 pb-1 font-brand text-lg font-bold">Contactos</h2>
                    <ul class="mt-2 space-y-1.5 text-sm">
                        @foreach ($contacts as $contact)
                            <li>
                                <span class="font-medium">{{ $contact->name }}</span>@if ($contact->specialty) · {{ $contact->specialty }}@endif
                                @if ($contact->phone) · {{ $contact->phone }}@endif
                                @if ($contact->note) <span class="text-xs text-cuero/60">({{ $contact->note }})</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Historia (timeline completo) --}}
            @if ($entries->isNotEmpty())
                <section class="mt-6">
                    <h2 class="border-b border-cuero/30 pb-1 font-brand text-lg font-bold">Historia</h2>
                    <ul class="mt-2 space-y-3 text-sm">
                        @foreach ($entries as $entry)
                            <li class="break-inside-avoid">
                                <p>
                                    <span class="font-medium">{{ $entry->occurred_on->format('d/m/Y') }}</span>
                                    · <span class="text-cuero/70">{{ $entry->typeLabel() }}</span>
                                    · {{ $entry->title }}
                                </p>
                                @if ($entry->detail)
                                    <p class="mt-0.5 whitespace-pre-line text-cuero/80">{{ $entry->detail }}</p>
                                @endif
                                @if ($entry->attachments->isNotEmpty())
                                    <p class="mt-0.5 text-xs text-cuero/60">Adjuntos: {{ $entry->attachments->pluck('original_name')->join(', ') }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Adjuntos sueltos, como inventario --}}
            @if ($documents->isNotEmpty())
                <section class="mt-6 break-inside-avoid">
                    <h2 class="border-b border-cuero/30 pb-1 font-brand text-lg font-bold">Documentos guardados</h2>
                    <ul class="mt-2 space-y-1 text-sm text-cuero/80">
                        @foreach ($documents as $document)
                            <li>{{ $document->original_name }} <span class="text-xs text-cuero/60">({{ $document->created_at->format('d/m/Y') }})</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <footer class="mt-8 border-t border-cuero/30 pt-3 text-xs text-cuero/60">
                Generado el {{ now()->format('d/m/Y') }} con {{ config('app.name') }}.
            </footer>
        </div>
    </body>
</html>
