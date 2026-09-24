<!DOCTYPE html>
<html lang="es" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Tuboformas</title>
    <link rel="icon" href="{{ asset('img/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white">
<div class="flex min-h-full">
    {{-- Panel de marca --}}
    <div class="relative hidden w-[46%] overflow-hidden bg-marca-600 lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.14),transparent_45%),radial-gradient(circle_at_85%_90%,rgba(0,0,0,0.25),transparent_50%)]"></div>
        {{-- Tubos estilizados --}}
        <svg class="absolute -right-24 bottom-0 h-[70%] opacity-[0.13]" viewBox="0 0 400 400" fill="none" stroke="white" stroke-width="26" stroke-linecap="round">
            <path d="M40 400V220a120 120 0 0 1 120-120h240"/><path d="M130 400V230a60 60 0 0 1 60-60h210"/><path d="M220 400v-80a40 40 0 0 1 40-40h140"/>
        </svg>
        <div class="relative flex h-full flex-col justify-between p-12 text-white">
            <div class="flex items-center gap-3">
                <img src="{{ asset('img/logo-blanco.png') }}" alt="Tuboformas" class="h-12 w-auto">
            </div>
            <div class="max-w-md">
                <p class="text-sm font-semibold tracking-[0.2em] text-marca-100 uppercase">Sistema interno</p>
                <h1 class="mt-3 font-display text-5xl leading-[1.05] font-extrabold">Mantenimiento y bodega, en un solo lugar.</h1>
                <p class="mt-5 text-lg leading-relaxed text-marca-50/90">Máquinas con su ficha técnica, órdenes de trabajo con seguimiento, bitácora automática e inventario con evidencia fotográfica.</p>
            </div>
            <p class="text-sm text-marca-100/80">© {{ date('Y') }} Tuboformas Guatemala, S.A.</p>
        </div>
    </div>

    {{-- Formulario --}}
    <div class="flex flex-1 flex-col justify-center px-6 py-12 sm:px-12">
        <div class="mx-auto w-full max-w-sm">
            <img src="{{ asset('img/logo.png') }}" alt="Tuboformas" class="h-16 w-auto lg:hidden">
            <h2 class="mt-8 font-display text-3xl font-extrabold tracking-tight text-carbon-900 lg:mt-0">Bienvenido</h2>
            <p class="mt-2 text-sm text-carbon-500">Ingresa con tu usuario y contraseña.</p>

            <form method="POST" action="{{ url('/login') }}" class="mt-8 space-y-5" x-data="{ ver: false, enviando: false }" @submit="enviando = true">
                @csrf
                <div>
                    <label for="username" class="etiqueta">Usuario o correo</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-carbon-400"><x-icono n="usuario" clase="size-4" /></span>
                        <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                               class="campo py-2.5 pl-9 @error('username') campo-error @enderror" placeholder="ej. jperez">
                    </div>
                    @error('username')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="etiqueta">Contraseña</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-carbon-400"><x-icono n="candado" clase="size-4" /></span>
                        <input id="password" name="password" :type="ver ? 'text' : 'password'" required autocomplete="current-password" class="campo py-2.5 pr-10 pl-9">
                        <button type="button" class="absolute inset-y-0 right-2 px-1 text-carbon-400 hover:text-carbon-700" @click="ver = !ver" :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                            <x-icono n="ojo" clase="size-4" />
                        </button>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-carbon-600">
                    <input type="checkbox" name="recordar" value="1" class="check"> Mantener la sesión en este equipo
                </label>
                <button class="btn-primario w-full py-2.5 text-[15px]" :disabled="enviando">
                    <span x-show="!enviando">Iniciar sesión</span>
                    <span x-show="enviando" x-cloak>Ingresando…</span>
                </button>
            </form>
            <p class="mt-10 text-center text-xs text-carbon-400">¿Olvidaste tu contraseña? Pide al administrador que la restablezca.</p>
        </div>
    </div>
</div>
</body>
</html>
