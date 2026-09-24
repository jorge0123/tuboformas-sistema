@props(['titulo' => 'Inicio'])
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1f1d1b">
    <title>{{ $titulo ?? 'Inicio' }} · Tuboformas</title>
    <link rel="icon" href="{{ asset('img/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ menu: false }">
@php $usuario = auth()->user(); $menu = \App\Support\Menu::para($usuario); @endphp

{{-- Barra lateral --}}
<div x-show="menu" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-carbon-950/60 lg:hidden" @click="menu = false"></div>
<aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-carbon-900 transition-transform duration-200 lg:translate-x-0"
       :class="menu && 'translate-x-0'">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-5">
        <img src="{{ asset('img/logo.png') }}" alt="" class="h-9 w-auto">
        <div class="leading-tight">
            <p class="font-display text-[15px] font-extrabold tracking-tight text-white">tuboformas</p>
            <p class="text-[11px] font-medium text-carbon-400">Mantenimiento y bodega</p>
        </div>
        <button class="btn-icono ml-auto text-carbon-400 hover:text-white lg:hidden" @click="menu = false" aria-label="Cerrar menú">
            <x-icono n="x" />
        </button>
    </div>
    <nav class="scroll-fino flex-1 overflow-y-auto px-3 pb-6">
        @foreach ($menu as $grupo)
            @if ($grupo['titulo'])<p class="nav-grupo">{{ $grupo['titulo'] }}</p>@else<div class="h-3"></div>@endif
            <div class="space-y-0.5">
                @foreach ($grupo['items'] as $item)
                    @php $activo = collect(explode('|', $item['activo']))->contains(fn ($p) => request()->routeIs($p)); @endphp
                    <a href="{{ route($item['ruta']) }}" class="nav-link {{ $activo ? 'activo' : '' }}">
                        <x-icono :n="$item['icono']" clase="size-[18px] shrink-0 {{ $activo ? 'text-marca-400' : 'text-carbon-400' }}" />
                        {{ $item['nombre'] }}
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
    <div class="border-t border-white/5 p-3">
        <a href="{{ route('perfil') }}" class="flex items-center gap-3 rounded-lg p-2 transition hover:bg-white/5">
            <span class="grid size-9 place-items-center rounded-full bg-marca-600 text-xs font-bold text-white">{{ $usuario->iniciales() }}</span>
            <span class="min-w-0 leading-tight">
                <span class="block truncate text-sm font-semibold text-white">{{ $usuario->name }}</span>
                <span class="block truncate text-xs text-carbon-400">{{ $usuario->rolPrincipal()?->nombre ?? 'Sin rol' }}</span>
            </span>
        </a>
    </div>
</aside>

<div class="flex min-h-full flex-col lg:pl-64">
    {{-- Barra superior --}}
    <header class="no-imprimir sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-carbon-200/70 bg-white/85 px-4 backdrop-blur sm:px-6">
        <button class="btn-icono -ml-2 text-carbon-600 lg:hidden" @click="menu = true" aria-label="Abrir menú"><x-icono n="menu" /></button>
        <div class="min-w-0 flex-1">
            @isset($migas)
                <nav class="flex items-center gap-1.5 truncate text-xs font-medium text-carbon-500">{{ $migas }}</nav>
            @endisset
            <h1 class="truncate font-display text-lg font-bold text-carbon-900">{{ $titulo ?? 'Inicio' }}</h1>
        </div>

        {{-- Notificaciones --}}
        <div class="relative" x-data="campana('{{ route('notificaciones.recientes') }}')" @click.outside="abierto = false">
            <button class="btn-icono relative text-carbon-600" @click="abierto = !abierto; abierto && cargar()" aria-label="Notificaciones">
                <x-icono n="campana" />
                <span x-show="sinLeer > 0" x-cloak x-text="sinLeer > 9 ? '9+' : sinLeer"
                      class="absolute -top-0.5 -right-0.5 grid min-w-5 place-items-center rounded-full bg-marca-600 px-1 text-[10px] font-bold text-white ring-2 ring-white"></span>
            </button>
            <div x-show="abierto" x-cloak x-transition.origin.top.right
                 class="absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-carbon-200">
                <div class="flex items-center justify-between border-b border-carbon-100 px-4 py-3">
                    <p class="font-display text-sm font-bold">Notificaciones</p>
                    <form method="POST" action="{{ route('notificaciones.leer-todas') }}">@csrf
                        <button class="text-xs font-semibold text-marca-700 hover:underline" x-show="sinLeer > 0">Marcar todas como leídas</button>
                    </form>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <template x-for="n in items" :key="n.id">
                        <a :href="n.url" class="flex gap-3 border-b border-carbon-50 px-4 py-3 transition hover:bg-carbon-50" :class="!n.leida && 'bg-marca-50/40'">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="n.leida ? 'bg-transparent' : 'bg-marca-600'"></span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-carbon-900" x-text="n.titulo"></span>
                                <span class="block text-xs text-carbon-600" x-text="n.mensaje"></span>
                                <span class="mt-0.5 block text-[11px] text-carbon-400" x-text="n.hace"></span>
                            </span>
                        </a>
                    </template>
                    <p x-show="!items.length" class="px-4 py-10 text-center text-sm text-carbon-500">No tienes notificaciones.</p>
                </div>
                <a href="{{ route('notificaciones.index') }}" class="block bg-carbon-50 px-4 py-2.5 text-center text-xs font-semibold text-carbon-700 hover:bg-carbon-100">Ver todas</a>
            </div>
        </div>

        {{-- Usuario --}}
        <div class="relative" x-data="{ abierto: false }" @click.outside="abierto = false">
            <button class="flex items-center gap-2 rounded-full p-0.5 pr-2 transition hover:bg-carbon-100" @click="abierto = !abierto">
                <span class="grid size-8 place-items-center rounded-full bg-carbon-900 text-xs font-bold text-white">{{ $usuario->iniciales() }}</span>
                <x-icono n="abajo" clase="hidden size-4 text-carbon-500 sm:block" />
            </button>
            <div x-show="abierto" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl bg-white py-1 shadow-xl ring-1 ring-carbon-200">
                <div class="border-b border-carbon-100 px-4 py-3">
                    <p class="truncate text-sm font-semibold">{{ $usuario->name }}</p>
                    <p class="truncate text-xs text-carbon-500">{{ '@'.$usuario->username }} · {{ $usuario->rolPrincipal()?->nombre }}</p>
                </div>
                <a href="{{ route('perfil') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-carbon-700 hover:bg-carbon-50"><x-icono n="usuario" clase="size-4" /> Mi perfil</a>
                @can('herramientas.ver')
                    <a href="{{ route('herramientas.mias') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-carbon-700 hover:bg-carbon-50"><x-icono n="caja-herr" clase="size-4" /> Mis herramientas</a>
                @endcan
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="flex w-full items-center gap-2 px-4 py-2 text-sm text-marca-700 hover:bg-marca-50"><x-icono n="salir" clase="size-4" /> Cerrar sesión</button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
</div>

{{-- Avisos flotantes: entran desde abajo, se van solos a los 5 s (pausa al pasar el mouse) --}}
<div x-data="{ avisos: [] }"
     @aviso.window="avisos.push({ id: Date.now() + Math.random(), visible: true, ...$event.detail })"
     x-init="
        @if (session('ok')) $nextTick(() => avisar(@js(session('ok')))); @endif
        @if (session('error')) $nextTick(() => avisar(@js(session('error')), 'error')); @endif
        @if ($errors->any()) $nextTick(() => avisar(@js($errors->first()), 'error')); @endif
     "
     class="pointer-events-none fixed inset-x-0 bottom-0 z-[70] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6" aria-live="polite">
    <template x-for="a in avisos" :key="a.id">
        <div x-data="{ pausa: false, restante: 5000, t: null,
                      correr() { const ini = Date.now(); this.t = setTimeout(() => a.visible = false, this.restante); this.$el._ini = ini },
                      detener() { clearTimeout(this.t); this.restante -= Date.now() - this.$el._ini } }"
             x-init="$nextTick(() => correr())" @mouseenter="pausa = true; detener()" @mouseleave="pausa = false; correr()"
             x-show="a.visible"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-4 opacity-0 sm:translate-x-4 sm:translate-y-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 scale-95"
             x-effect="if (!a.visible) setTimeout(() => avisos = avisos.filter(x => x.id !== a.id), 260)"
             class="pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-carbon-200">
            <div class="flex items-start gap-3 p-4">
                <span class="grid size-8 shrink-0 place-items-center rounded-full"
                      :class="{ 'bg-emerald-50 text-emerald-600': a.tipo === 'exito', 'bg-marca-50 text-marca-600': a.tipo === 'error', 'bg-sky-50 text-sky-600': a.tipo === 'info' }">
                    <template x-if="a.tipo === 'exito'"><x-icono n="check-circulo" clase="size-5" /></template>
                    <template x-if="a.tipo === 'error'"><x-icono n="alerta" clase="size-5" /></template>
                    <template x-if="a.tipo === 'info'"><x-icono n="campana" clase="size-5" /></template>
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p class="text-sm font-semibold text-carbon-900" x-text="a.tipo === 'error' ? 'No se pudo completar' : (a.tipo === 'info' ? 'Aviso' : 'Listo')"></p>
                    <p class="mt-0.5 text-sm text-carbon-600" x-text="a.mensaje"></p>
                </div>
                <button class="rounded-md p-1 text-carbon-400 transition hover:bg-carbon-100 hover:text-carbon-700" @click="a.visible = false" aria-label="Cerrar"><x-icono n="x" clase="size-4" /></button>
            </div>
            <div class="absolute bottom-0 left-0 h-0.5 animate-[vaciar_5s_linear_forwards]" :style="pausa && 'animation-play-state: paused'"
                 :class="{ 'bg-emerald-500': a.tipo === 'exito', 'bg-marca-500': a.tipo === 'error', 'bg-sky-500': a.tipo === 'info' }"></div>
        </div>
    </template>
</div>

{{-- Diálogo de confirmación: cualquier <form data-confirmar="…"> lo usa en lugar de confirm() --}}
<div x-data="confirmacion" x-show="abierto" x-cloak class="fixed inset-0 z-[80] grid place-items-center p-4" @keydown.escape.window="cancelar()">
    <div x-show="abierto" x-transition.opacity.duration.200ms class="absolute inset-0 bg-carbon-950/50 backdrop-blur-[2px]" @click="cancelar()"></div>
    <div x-show="abierto" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0 scale-95"
         class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="alertdialog" aria-modal="true">
        <div class="flex gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-full" :class="peligro ? 'bg-marca-50 text-marca-600' : 'bg-carbon-100 text-carbon-700'">
                <x-icono n="alerta" clase="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="font-display text-lg font-bold text-carbon-900" x-text="titulo"></h3>
                <p class="mt-1.5 text-sm leading-relaxed text-carbon-600" x-text="mensaje"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="btn-secundario" @click="cancelar()">Cancelar</button>
            <button type="button" x-ref="aceptar" :class="peligro ? 'btn bg-marca-600 text-white hover:bg-marca-700' : 'btn-oscuro'" @click="aceptar()" x-text="boton"></button>
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
