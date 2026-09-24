<x-layouts.app titulo="Registrar movimiento">
<x-slot:migas><a href="{{ route('movimientos.index') }}" class="hover:text-carbon-800">Movimientos</a><x-icono n="derecha" clase="size-3" />Nuevo</x-slot:migas>
@php
    $iconos = [
        'entrada_compra' => ['camion', 'bg-emerald-50 text-emerald-600'],
        'salida_produccion' => ['fabrica', 'bg-marca-50 text-marca-600'],
        'ingreso_produccion' => ['paquete', 'bg-emerald-50 text-emerald-600'],
        'devolucion_produccion' => ['deshacer', 'bg-sky-50 text-sky-600'],
        'salida_despacho' => ['enviar', 'bg-marca-50 text-marca-600'],
        'traslado' => ['flechas', 'bg-sky-50 text-sky-600'],
        'ajuste_entrada' => ['mas-circulo', 'bg-amber-50 text-amber-600'],
        'ajuste_salida' => ['menos', 'bg-amber-50 text-amber-600'],
    ];
@endphp
<div class="mx-auto max-w-4xl">
    <p class="mb-5 text-sm text-carbon-600">¿Qué vas a registrar? Los tipos siguen el flujo de la planta: llega materia prima, sale a producción, regresa como producto terminado y se despacha.</p>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach ($iconos as $tipo => [$icono, $color])
            @php $t = \App\Models\Movimiento::TIPOS[$tipo]; @endphp
            <a href="{{ route('movimientos.create', ['tipo' => $tipo] + request()->only('producto')) }}" class="tarjeta aparecer group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-marca-300" style="animation-delay: {{ $loop->index * 40 }}ms">
                <span class="grid size-12 shrink-0 place-items-center rounded-xl {{ $color }}"><x-icono :n="$icono" clase="size-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-display font-bold text-carbon-900 group-hover:text-marca-700">{{ $t['nombre'] }}</p>
                    <p class="text-sm text-carbon-500">{{ $t['ayuda'] }}</p>
                </div>
                <x-icono n="derecha" clase="size-5 text-carbon-300 transition group-hover:translate-x-0.5 group-hover:text-marca-600" />
            </a>
        @endforeach
    </div>
    <p class="mt-4 flex items-center gap-2 text-xs text-carbon-500"><x-icono n="llave" clase="size-4" /> Los repuestos usados en mantenimiento se descuentan desde la orden de trabajo.</p>
</div>
</x-layouts.app>
