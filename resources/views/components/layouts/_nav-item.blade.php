{{-- Una entrada del menú lateral: $item (de Menu::para) y $activo. --}}
<a href="{{ route($item['ruta']) }}" class="nav-link {{ $activo ? 'activo' : '' }}" @if ($activo) aria-current="page" @endif
   @mouseenter="mostrarTip($event, @js($item['nombre']), @js($item['ayuda'] ?? null))" @mouseleave="ocultarTip()" @focus="mostrarTip($event, @js($item['nombre']), @js($item['ayuda'] ?? null))" @blur="ocultarTip()">
    <span class="relative shrink-0">
        <x-icono :n="$item['icono']" clase="nav-icono size-[18px]" />
        @isset($item['contador'])<span class="nav-punto {{ ['rojo' => 'bg-marca-500', 'azul' => 'bg-sky-400'][$item['tono']] ?? 'bg-amber-400' }}"></span>@endisset
    </span>
    <span class="menu-texto min-w-0 flex-1 truncate">{{ $item['nombre'] }}</span>
    @isset($item['contador'])
        <span class="menu-texto nav-contador {{ ['rojo' => 'bg-marca-500/15 text-marca-300 ring-marca-500/30', 'azul' => 'bg-sky-400/10 text-sky-300 ring-sky-400/25'][$item['tono']] ?? 'bg-amber-400/10 text-amber-300 ring-amber-400/25' }}" title="{{ $item['ayuda'] }}">{{ $item['contador'] > 99 ? '99+' : $item['contador'] }}</span>
    @endisset
</a>
