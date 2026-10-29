@extends('layouts.app')

@section('content')
@php
	$emp = $pago->empleado;
	$equivalente = $pago->montoEnOtraMoneda();
@endphp
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex items-center justify-between mb-1 gap-2">
		<h1 class="text-xl font-semibold">Pago de Intereses sobre Prestaciones</h1>
		<div class="flex items-center gap-2">
			<a href="{{ route('intereses-prestaciones.comprobante', $pago) }}" target="_blank" rel="noopener" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Comprobante PDF</a>
			<a href="{{ route('intereses-prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
		</div>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		{{ $emp->nombre_completo }} · C.I. {{ $emp->cedula }}
	</p>

	<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Monto pagado</p>
			<p class="text-lg font-semibold">{{ $pago->moneda }} {{ formato_monto((float) $pago->monto) }}</p>
		</div>
		@if ($equivalente !== null)
			<div class="bg-white border border-gray-200 p-4">
				<p class="text-xs text-gray-500 mb-1">Equivalente en {{ $pago->monedaEquivalente() }}</p>
				<p class="text-lg font-semibold">{{ $pago->monedaEquivalente() }} {{ formato_monto($equivalente) }}</p>
			</div>
		@endif
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Fecha del pago</p>
			<p class="text-lg font-semibold">{{ $pago->fecha->format('d/m/Y') }}</p>
		</div>
	</div>

	<div class="bg-white border border-gray-200 p-5 mb-4">
		<h2 class="text-sm font-semibold text-gray-700 mb-3">Datos del pago</h2>
		<div class="grid grid-cols-2 gap-3 text-sm">
			<div><span class="text-gray-500">Comprobante N.º:</span> {{ $pago->numeroComprobante() }}</div>
			<div><span class="text-gray-500">Fecha del movimiento:</span> {{ $pago->fecha->format('d/m/Y') }}</div>
			<div><span class="text-gray-500">Fecha del comprobante:</span> {{ $pago->fecha_comprobante?->format('d/m/Y') ?? '—' }}</div>
			<div><span class="text-gray-500">Moneda:</span> {{ $pago->moneda }}</div>
			@if ($pago->tasa_cambio)
				<div><span class="text-gray-500">Tasa de cambio:</span> {{ formato_monto((float) $pago->tasa_cambio, 4) }}</div>
			@endif
			<div><span class="text-gray-500">Referencia:</span> {{ $pago->comprobante ?: '—' }}</div>
			@if ($pago->observacion)
				<div class="col-span-2"><span class="text-gray-500">Observación:</span> {{ $pago->observacion }}</div>
			@endif
		</div>
	</div>

	{{-- Acciones --}}
	<div class="flex justify-end">
		@can('eliminar')
			<form method="POST" action="{{ route('intereses-prestaciones.destroy', $pago) }}" onsubmit="return confirm('¿Eliminar este pago de intereses?')">
				@csrf @method('DELETE')
				<input type="hidden" name="volver_a" value="index">
				<button class="px-3 py-1.5 text-sm border border-red-300 text-red-600 hover:bg-red-50">Eliminar</button>
			</form>
		@endcan
	</div>
</div>
@endsection
