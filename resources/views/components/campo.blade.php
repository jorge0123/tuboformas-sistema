@props(['nombre', 'etiqueta', 'valor' => null, 'tipo' => 'text', 'ayuda' => null, 'requerido' => false, 'clase' => ''])
{{-- Campo de formulario con etiqueta, valor anterior y error. Para select/textarea usar el slot. --}}
<div class="{{ $clase }}">
    <label for="{{ $nombre }}" class="etiqueta">{{ $etiqueta }}@if ($requerido)<span class="text-marca-600"> *</span>@endif</label>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <input id="{{ $nombre }}" name="{{ $nombre }}" type="{{ $tipo }}" value="{{ old($nombre, $valor) }}" @required($requerido)
               {{ $attributes->merge(['class' => 'campo'.($errors->has($nombre) ? ' campo-error' : '')]) }}>
    @endif
    @if ($ayuda)<p class="ayuda">{{ $ayuda }}</p>@endif
    @error($nombre)<p class="error">{{ $message }}</p>@enderror
</div>
