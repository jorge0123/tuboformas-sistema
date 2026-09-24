<x-layouts.app titulo="Mi perfil">
<div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="tarjeta h-fit p-6 text-center">
        <span class="mx-auto grid size-20 place-items-center rounded-full bg-marca-600 font-display text-2xl font-extrabold text-white">{{ $u->iniciales() }}</span>
        <h2 class="mt-4 font-display text-xl font-extrabold">{{ $u->name }}</h2>
        <p class="text-sm text-carbon-500">{{ '@'.$u->username }}</p>
        <div class="mt-3 flex flex-wrap justify-center gap-1">
            <span class="insignia-roja">{{ $u->rolPrincipal()?->nombre }}</span>
            @if ($u->especialidad)<span class="insignia-gris">{{ $u->especialidad->nombre }}</span>@endif
        </div>
        <p class="mt-4 text-xs text-carbon-500">{{ $u->puesto }}</p>
    </section>

    <div class="space-y-6 lg:col-span-2">
        <form method="POST" action="{{ route('perfil.update') }}" class="tarjeta">
            @csrf @method('PUT')
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Mis datos</h3></div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                <x-campo nombre="name" etiqueta="Nombre" :valor="$u->name" requerido clase="sm:col-span-2" />
                <x-campo nombre="email" etiqueta="Correo" tipo="email" :valor="$u->email" />
                <x-campo nombre="telefono" etiqueta="Teléfono" :valor="$u->telefono" />
                <label class="flex items-start gap-3 rounded-xl bg-carbon-50 p-4 text-sm sm:col-span-2">
                    <input type="checkbox" name="notif_email" value="1" class="check mt-0.5" @checked($u->notif_email)>
                    <span><b>Recibir avisos por correo</b><span class="block text-carbon-500">Órdenes asignadas, avances, ajustes por aprobar y stock bajo. Siempre te llegan también a la campana del sistema.</span></span>
                </label>
                <div class="flex justify-end sm:col-span-2"><button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar</button></div>
            </div>
        </form>

        <form method="POST" action="{{ route('perfil.password') }}" class="tarjeta">
            @csrf @method('PUT')
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Cambiar contraseña</h3></div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
                <x-campo nombre="actual" etiqueta="Actual" tipo="password" requerido autocomplete="current-password" />
                <x-campo nombre="password" etiqueta="Nueva" tipo="password" requerido autocomplete="new-password" ayuda="Mínimo 8, letras y números." />
                <x-campo nombre="password_confirmation" etiqueta="Confirmar" tipo="password" requerido autocomplete="new-password" />
                <div class="flex justify-end sm:col-span-3"><button class="btn-oscuro"><x-icono n="candado" clase="size-4" /> Actualizar contraseña</button></div>
            </div>
        </form>
    </div>
</div>
</x-layouts.app>
