@extends('layouts.dashboard')

@section('content')
<div class="p-4 mx-4">
    <h1 class="text-xl font-semibold mb-1">SCL - Sistema de Cálculos Laborales</h1>
    <p class="text-sm text-gray-600 mb-2">Bienvenido, {{ auth()->user()->name }}.</p>
    <p class="text-sm text-gray-600 mb-6">
        Patrono activo: <span class="font-medium">{{ session('nombre_patrono') }}</span>
        <a href="{{ route('entorno.seleccionar') }}" class="ml-1 text-xs text-blue-600 underline">Cambiar</a>
    </p>

    @foreach (config('menu') as $seccion)
        <div class="mb-8">
            <h2 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-3">{{ $seccion['titulo'] }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach ($seccion['opciones'] as $op)
                    <a href="{{ route($op['ruta']) }}" class="bg-white border border-gray-200 p-5 hover:border-blue-300 transition block">
                        <div class="text-blue-600 mb-2">
                            @include('partials.icono', ['icono' => $op['icono'], 'class' => 'w-7 h-7'])
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800">{{ $op['label'] }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ $op['desc'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
