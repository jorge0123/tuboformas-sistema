@props(['titulo' => 'Inicio', 'vivo' => true])
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
    {{-- Antes de pintar: el menú queda como se dejó (contraído, secciones plegadas) y no "salta" al cargar. --}}
    <script>
        try {
            if (localStorage.getItem('tf-menu-colapsado') === '1') document.documentElement.classList.add('menu-colapsado');
            const g = JSON.parse(localStorage.getItem('tf-menu-grupos') || '{}');
            const css = Object.keys(g).filter((k) => g[k] === false).map((k) => {
                const s = CSS.escape(k);
                return `html:not(.menu-colapsado) [data-grupo="${s}"]{display:none}html:not(.menu-colapsado) [data-grupo-actual="${s}"][x-cloak]{display:block!important}`;
            }).join('');
            // En la misma capa que [x-cloak] (base): así gana por especificidad.
            if (css) document.head.appendChild(Object.assign(document.createElement('style'), { id: 'tf-grupos-previo', textContent: `@layer base{${css}}` }));
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ menu: false }">
@php
    $usuario = auth()->user();
    $menu = \App\Support\Menu::para($usuario);
    $barra = \App\Support\Menu::barraInferior($usuario);
    // Formularios y pantallas con captura propia no se refrescan solas.
    $enVivo = $vivo && ! request()->routeIs('*.create', '*.edit', 'perfil', 'roles.*', 'catalogos.*');
@endphp

{{-- Barra lateral: se contrae a solo íconos (Ctrl/⌘ + B). En celular es un cajón. --}}
<div x-data="menuLateral" class="contents">
<div x-show="menu" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-carbon-950/60 backdrop-blur-[2px] lg:hidden" @click="menu = false"></div>
<aside class="barra-lateral group/barra fixed inset-y-0 left-0 z-50 flex -translate-x-full flex-col bg-carbon-950 transition-transform duration-300 ease-out lg:translate-x-0"
       :class="menu && 'translate-x-0 shadow-2xl'" @keydown.escape.window="menu = false">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-56 bg-[radial-gradient(ellipse_at_top_left,rgba(217,0,22,.20),transparent_70%)]"></div>

    <div class="relative flex h-16 shrink-0 items-center gap-3 border-b border-white/[.06] px-[1.125rem]">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('img/logo.png') }}" alt="" class="size-9 shrink-0 object-contain transition-transform duration-300 hover:rotate-[-6deg]">
            <span class="menu-texto leading-tight">
                <span class="block font-display text-[15px] font-extrabold tracking-tight text-white">tuboformas</span>
                <span class="block text-[11px] font-medium text-carbon-400">Mantenimiento</span>
            </span>
        </a>
        <button class="btn-icono ml-auto text-carbon-400 hover:text-white lg:hidden" @click="menu = false" aria-label="Cerrar menú"><x-icono n="x" /></button>
    </div>

    {{-- Asa en el borde para contraer/expandir --}}
    <button type="button" @click="alternar()" class="absolute top-[4.5rem] -right-3 z-10 hidden size-6 place-items-center rounded-full bg-white text-carbon-600 opacity-0 shadow-md ring-1 ring-carbon-200 transition group-hover/barra:opacity-100 hover:scale-110 hover:text-marca-600 focus-visible:opacity-100 lg:grid"
            :aria-label="colapsado ? 'Expandir menú' : 'Contraer menú'">
        <x-icono n="izquierda" clase="size-3.5 transition-transform duration-300" x-bind:class="colapsado && 'rotate-180'" />
    </button>

    <nav class="scroll-fino relative flex-1 overflow-x-hidden overflow-y-auto px-3 pt-2 pb-4" @scroll.passive="ocultarTip()">
        @foreach ($menu as $grupo)
            @php $clave = $grupo['titulo'] ?? 'inicio'; @endphp
            @if ($grupo['titulo'])
                <button type="button" class="nav-grupo" @click="alternarGrupo(@js($clave))" :aria-expanded="grupoAbierto(@js($clave)).toString()">
                    <span class="menu-texto">{{ $grupo['titulo'] }}</span>
                    <x-icono n="abajo" clase="menu-texto ml-auto size-3.5 transition-transform duration-200" x-bind:class="!grupoAbierto({{ Js::from($clave) }}) && '-rotate-90'" />
                </button>
            @endif
            @php
                $activoDe = fn ($item) => collect(explode('|', $item['activo']))->contains(fn ($p) => request()->routeIs($p));
                $actual = collect($grupo['items'])->first($activoDe);
            @endphp
            {{-- Toda la sección se pliega en un solo movimiento. --}}
            <div @if ($grupo['titulo']) data-grupo="{{ $clave }}" x-show="colapsado || grupoAbierto(@js($clave))" x-collapse.duration.220ms @endif>
                <div class="space-y-0.5">
                    @foreach ($grupo['items'] as $item)
                        @include('components.layouts._nav-item', ['item' => $item, 'activo' => $activoDe($item)])
                    @endforeach
                </div>
            </div>
            {{-- Plegada y estás dentro: queda a la vista solo la pantalla actual. --}}
            @if ($grupo['titulo'] && $actual)
                <div data-grupo-actual="{{ $clave }}" x-show="!colapsado && !grupoAbierto(@js($clave))" x-cloak x-transition.opacity.duration.150ms>
                    @include('components.layouts._nav-item', ['item' => $actual, 'activo' => true])
                </div>
            @endif
        @endforeach
    </nav>
    <div class="relative space-y-1 border-t border-white/[.06] p-3">
        <button type="button" @click="alternar()" class="nav-link hidden w-full lg:flex" @mouseenter="mostrarTip($event, 'Expandir menú', 'Ctrl + B')" @mouseleave="ocultarTip()">
            <span class="shrink-0"><x-icono n="panel" clase="nav-icono size-[18px]" /></span>
            <span class="menu-texto flex-1 text-left">Contraer menú</span>
            <kbd class="menu-texto rounded bg-white/[.08] px-1.5 py-0.5 font-sans text-[10px] font-semibold text-carbon-400">Ctrl B</kbd>
        </button>
        <a href="{{ route('perfil') }}" class="nav-perfil" @mouseenter="mostrarTip($event, @js($usuario->name), @js($usuario->rolPrincipal()?->nombre))" @mouseleave="ocultarTip()">
            <span class="relative grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-marca-500 to-marca-700 text-xs font-bold text-white ring-2 ring-carbon-950">
                {{ $usuario->iniciales() }}
                <span class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full bg-emerald-400 ring-2 ring-carbon-950"></span>
            </span>
            <span class="menu-texto min-w-0 leading-tight">
                <span class="block truncate text-sm font-semibold text-white">{{ $usuario->name }}</span>
                <span class="block truncate text-xs text-carbon-400">{{ $usuario->rolPrincipal()?->nombre ?? 'Sin rol' }}</span>
            </span>
        </a>
    </div>
</aside>
    <script>
    (() => {
        // Aquí el menú ya está completo (lista + pie); antes, el alto disponible no es el final y la
        // posición guardada se recortaría (se notaba al entrar a las últimas opciones, ej. Auditoría).
        const nav = document.currentScript.previousElementSibling.querySelector('nav');
        try { const y = sessionStorage.getItem('tf-menu-scroll'); if (y !== null) nav.scrollTop = +y; } catch (e) {}
        // Si la opción activa quedó fuera de la vista, se centra (sin animación: se ve natural).
        const a = [...nav.querySelectorAll('a.nav-link.activo')].find((x) => x.offsetParent);
        if (a) {
            const r = a.getBoundingClientRect(), c = nav.getBoundingClientRect();
            if (r.top < c.top + 8 || r.bottom > c.bottom - 8) nav.scrollTop += r.top - c.top - (c.height - r.height) / 2;
        }
        const guardar = () => { try { sessionStorage.setItem('tf-menu-scroll', Math.round(nav.scrollTop)); } catch (e) {} };
        addEventListener('pagehide', guardar);
        nav.addEventListener('click', (e) => e.target.closest('a') && guardar());
    })();
</script>

{{-- Etiqueta flotante del menú contraído (fuera del <nav> para que no la recorte el scroll) --}}
<div x-show="tip.visible" x-cloak x-transition:enter="transition duration-100 ease-out" x-transition:enter-start="opacity-0 -translate-x-1"
     class="pointer-events-none fixed left-[calc(4.5rem+10px)] z-[60] hidden -translate-y-1/2 lg:block" :style="`top:${tip.top}px`">
    <div class="relative rounded-lg bg-carbon-900 px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-white shadow-xl ring-1 ring-white/10">
        <span class="absolute top-1/2 -left-1 size-2 -translate-y-1/2 rotate-45 bg-carbon-900"></span>
        <span x-text="tip.texto"></span>
        <span x-show="tip.ayuda" class="ml-1.5 font-medium text-carbon-400" x-text="tip.ayuda"></span>
    </div>
</div>
</div>

{{-- Barra inferior (celular): accesos del día a día y la acción principal al centro --}}
<nav class="barra-inferior fixed inset-x-0 bottom-0 z-40 border-t border-carbon-200/80 bg-white/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg lg:hidden" aria-label="Accesos rápidos">
    <div class="mx-auto grid h-16 max-w-md grid-cols-5 items-stretch px-1">
        @php
            $enlace = function ($item) {
                $activo = collect(explode('|', $item['activo']))->contains(fn ($p) => request()->routeIs($p));
                return [$activo, route($item['ruta'])];
            };
            $orden = $barra['centro'] ? [$barra['items'][0] ?? null, $barra['items'][1] ?? null, 'centro', $barra['items'][2] ?? null] : [...$barra['items']];
        @endphp
        @foreach ($orden as $item)
            @if ($item === 'centro')
                @php [$activo, $url] = $enlace($barra['centro']); @endphp
                <a href="{{ $url }}" class="group relative flex flex-col items-center justify-end pb-1.5" aria-label="{{ $barra['centro']['nombre'] }}">
                    <span class="absolute -top-5 grid size-14 place-items-center rounded-2xl bg-marca-600 text-white shadow-lg shadow-marca-600/30 ring-4 ring-white transition group-active:scale-90 {{ $activo ? 'bg-marca-700' : '' }}">
                        <x-icono :n="$barra['centro']['icono']" clase="size-6" />
                    </span>
                    <span class="text-[10.5px] font-semibold {{ $activo ? 'text-marca-700' : 'text-carbon-600' }}">{{ $barra['centro']['nombre'] }}</span>
                </a>
            @elseif ($item)
                @php [$activo, $url] = $enlace($item); @endphp
                <a href="{{ $url }}" class="barra-item {{ $activo ? 'activo' : '' }}" @if ($activo) aria-current="page" @endif>
                    <span class="relative">
                        <x-icono :n="$item['icono']" clase="size-[22px]" />
                        @isset($item['contador'])<span class="absolute -top-1.5 -right-2.5 grid h-4 min-w-4 place-items-center rounded-full px-1 text-[10px] font-bold text-white ring-2 ring-white {{ ['rojo' => 'bg-marca-600', 'azul' => 'bg-sky-500'][$item['tono']] ?? 'bg-amber-500' }}">{{ $item['contador'] > 9 ? '9+' : $item['contador'] }}</span>@endisset
                    </span>
                    <span>{{ $item['nombre'] }}</span>
                </a>
            @else
                <span></span>
            @endif
        @endforeach
        <button type="button" class="barra-item" @click="menu = true" aria-label="Abrir menú completo">
            <x-icono n="menu" clase="size-[22px]" />
            <span>Más</span>
        </button>
    </div>
</nav>

<div class="contenido flex min-h-full flex-col">
    {{-- Barra superior --}}
    <header class="no-imprimir sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-carbon-200/70 bg-white/85 px-4 backdrop-blur sm:px-6">
        <button class="btn-icono -ml-2 text-carbon-600 lg:hidden" @click="menu = true" aria-label="Abrir menú"><x-icono n="menu" /></button>
        <div class="min-w-0 flex-1">
            @isset($migas)
                <nav class="flex items-center gap-1.5 truncate text-xs font-medium text-carbon-500">{{ $migas }}</nav>
            @endisset
            <div class="flex min-w-0 items-center gap-2">
                <h1 class="truncate font-display text-lg font-bold text-carbon-900">{{ $titulo ?? 'Inicio' }}</h1>
                @if ($enVivo)
                    <span class="relative hidden size-2 shrink-0 sm:block" title="En vivo: se actualiza sola cada 30 segundos">
                        <span class="absolute inset-0 animate-ping rounded-full bg-emerald-400 opacity-60 [animation-duration:2.5s]"></span>
                        <span class="relative block size-2 rounded-full bg-emerald-500"></span>
                    </span>
                @endif
            </div>
        </div>

        {{-- Notificaciones --}}
        <div class="relative" x-data="campana('{{ route('notificaciones.recientes') }}')" @click.outside="abierto = false">
            <button class="btn-icono relative text-carbon-600" @click="abierto = !abierto; abierto && cargar()" aria-label="Notificaciones">
                <x-icono n="campana" x-bind:class="sacudir && 'animate-[campana_.8s_ease-in-out]'" />
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

    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8" @if ($enVivo) data-vivo @endif>
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
     class="pointer-events-none fixed inset-x-0 top-0 z-[90] flex flex-col items-center gap-2 p-3 pt-[max(0.75rem,env(safe-area-inset-top))] sm:top-auto sm:bottom-0 sm:items-end sm:p-6" aria-live="polite">
    <template x-for="a in avisos" :key="a.id">
        <div x-data="{ pausa: false, restante: 5000, t: null,
                      correr() { const ini = Date.now(); this.t = setTimeout(() => a.visible = false, this.restante); this.$el._ini = ini },
                      detener() { clearTimeout(this.t); this.restante -= Date.now() - this.$el._ini } }"
             x-init="$nextTick(() => correr())" @mouseenter="pausa = true; detener()" @mouseleave="pausa = false; correr()"
             x-show="a.visible"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-y-4 opacity-0 sm:translate-x-4 sm:translate-y-0" x-transition:enter-end="translate-y-0 opacity-100"
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
                    <p class="text-sm font-semibold text-carbon-900" x-text="a.titulo || (a.tipo === 'error' ? 'No se pudo completar' : (a.tipo === 'info' ? 'Aviso' : 'Listo'))"></p>
                    <p class="mt-0.5 text-sm text-carbon-600" x-text="a.mensaje"></p>
                    <a x-show="a.url" :href="a.url" class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-marca-700 hover:underline">Ver <x-icono n="derecha" clase="size-3" /></a>
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
                <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-carbon-600" x-text="mensaje"></p>
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
