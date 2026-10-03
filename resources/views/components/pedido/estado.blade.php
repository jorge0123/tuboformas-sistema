@props(['estado'])
@php
    $c = [
        'nuevo' => ['insignia-azul', 'campana'],
        'preparando' => ['insignia-violeta', 'cajas'],
        'listo' => ['insignia-verde', 'check'],
        'en_ruta' => ['insignia-ambar', 'camion'],
        'entregado' => ['insignia-verde', 'check-circulo'],
        'cancelado' => ['insignia-gris', 'x'],
    ][$estado] ?? ['insignia-gris', 'reloj'];
@endphp
<span {{ $attributes->merge(['class' => $c[0]]) }}><x-icono :n="$c[1]" clase="size-3" />{{ \App\Models\Pedido::ESTADOS[$estado] ?? $estado }}</span>
