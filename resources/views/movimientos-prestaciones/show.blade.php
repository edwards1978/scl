@extends('layouts.app')

@section('content')
@php
	$emp = $movimiento->empleado;
@endphp
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex items-center justify-between mb-1 gap-2">
		<h1 class="text-xl font-semibold">{{ $movimiento->labelTipo() }} de Prestaciones</h1>
		<div class="flex items-center gap-2">
			<a href="{{ route('movimientos-prestaciones.comprobante', $movimiento) }}" target="_blank" rel="noopener" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Comprobante PDF</a>
			<a href="{{ route('movimientos-prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
		</div>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		{{ $emp->nombre_completo }} · C.I. {{ $emp->cedula }}
	</p>

	@if ($movimiento->estatus === 'anulado')
		<div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 text-sm">Este movimiento fue anulado.</div>
	@endif

	<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Monto otorgado</p>
			<p class="text-lg font-semibold">{{ $movimiento->moneda }} {{ formato_monto((float) $movimiento->monto) }}</p>
		</div>
		@if (!$movimiento->esAnticipo())
			<div class="bg-white border border-gray-200 p-4">
				<p class="text-xs text-gray-500 mb-1">Saldo pendiente</p>
				<p class="text-lg font-semibold">{{ $movimiento->moneda }} {{ formato_monto((float) $movimiento->saldo) }}</p>
			</div>
			<div class="bg-white border border-gray-200 p-4">
				<p class="text-xs text-gray-500 mb-1">Intereses pagados</p>
				<p class="text-lg font-semibold">{{ $movimiento->moneda }} {{ formato_monto($movimiento->totalInteresesPagados()) }}</p>
			</div>
		@else
			<div class="bg-white border border-gray-200 p-4">
				<p class="text-xs text-gray-500 mb-1">Motivo</p>
				<p class="text-lg font-semibold">{{ $movimiento->labelMotivo() ?? '—' }}</p>
			</div>
			@endif
	</div>

	<div class="bg-white border border-gray-200 p-5 mb-4">
		<h2 class="text-sm font-semibold text-gray-700 mb-3">Datos del movimiento</h2>
		<div class="grid grid-cols-2 gap-3 text-sm">
			<div><span class="text-gray-500">Fecha del movimiento:</span> {{ $movimiento->fecha->format('d/m/Y') }}</div>
			<div><span class="text-gray-500">Fecha del comprobante:</span> {{ $movimiento->fecha_comprobante?->format('d/m/Y') ?? '—' }}</div>
			<div><span class="text-gray-500">Tipo:</span> {{ $movimiento->labelTipo() }}</div>
			<div><span class="text-gray-500">Moneda:</span> {{ $movimiento->moneda }}</div>
			@if ($movimiento->tasa_cambio)
				<div><span class="text-gray-500">Tasa de cambio:</span> {{ formato_monto((float) $movimiento->tasa_cambio, 4) }}</div>
			@endif
			@if ($movimiento->plazo_meses)
				<div><span class="text-gray-500">Plazo:</span> {{ $movimiento->plazo_meses }} meses</div>
			@endif
			@if ($movimiento->interes_anual !== null)
				<div><span class="text-gray-500">Interés anual:</span> {{ formato_monto((float) $movimiento->interes_anual, 2) }}%</div>
			@endif
			@if ($movimiento->cuota !== null)
				<div><span class="text-gray-500">Cuota:</span> {{ $movimiento->moneda }} {{ formato_monto((float) $movimiento->cuota) }}</div>
			@endif
			<div><span class="text-gray-500">Estatus:</span>
				<span class="inline-flex text-[10px] px-2 py-0.5 {{ $movimiento->colorEstatus() }}">{{ ucfirst($movimiento->estatus) }}</span>
			</div>
			@if ($movimiento->concepto)
				<div class="col-span-2"><span class="text-gray-500">Concepto:</span> {{ $movimiento->concepto }}</div>
			@endif
			@if ($movimiento->observacion)
				<div class="col-span-2"><span class="text-gray-500">Observación:</span> {{ $movimiento->observacion }}</div>
			@endif
		</div>
	</div>

	@if (! $movimiento->esAnticipo())
	<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
		{{-- Registrar interés --}}
		<div class="bg-white border border-gray-200 p-5">
			<h2 class="text-sm font-semibold text-gray-700 mb-3">Registrar pago de intereses</h2>
			<form method="POST" action="{{ route('movimientos-prestaciones.intereses', $movimiento) }}" class="space-y-3">
				@csrf
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha</label>
						<input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Monto</label>
						<input type="number" name="monto" step="0.01" min="0.01" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
				</div>
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Moneda</label>
						<select name="moneda" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="USD">USD</option>
							<option value="BS">Bs</option>
						</select>
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Concepto</label>
						<input type="text" name="concepto" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
				</div>
				<button class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Registrar interés</button>
			</form>
		</div>

		{{-- Abono a préstamo --}}
		<div class="bg-white border border-gray-200 p-5">
			<h2 class="text-sm font-semibold text-gray-700 mb-3">Abonar a préstamo</h2>
			<form method="POST" action="{{ route('movimientos-prestaciones.abonar', $movimiento) }}" class="space-y-3">
				@csrf
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Monto del abono</label>
					<input type="number" name="monto" step="0.01" min="0.01" max="{{ $movimiento->saldo }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<button class="px-3 py-1.5 text-sm bg-green-600 text-white hover:bg-green-700">Registrar abono</button>
			</form>
		</div>
	</div>
	@endif

	@if (! $movimiento->esAnticipo())
	{{-- Intereses registrados --}}
	<div class="bg-white border border-gray-200 overflow-x-auto mb-4">
		<table class="w-full text-sm">
			<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
				<tr>
					<th class="text-left px-3 py-2">Fecha</th>
					<th class="text-left px-3 py-2">Concepto</th>
					<th class="text-right px-3 py-2">Monto</th>
					<th class="text-center px-3 py-2">Moneda</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@forelse ($movimiento->intereses as $interes)
					<tr>
						<td class="px-3 py-2">{{ $interes->fecha->format('d/m/Y') }}</td>
						<td class="px-3 py-2">{{ $interes->concepto ?? '—' }}</td>
						<td class="px-3 py-2 text-right font-medium">{{ $interes->moneda }} {{ formato_monto((float) $interes->monto) }}</td>
						<td class="px-3 py-2 text-center">{{ $interes->moneda }}</td>
					</tr>
				@empty
					<tr><td colspan="4" class="px-3 py-6 text-center text-gray-400">No hay intereses registrados.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
	@endif

	{{-- Acciones de estatus --}}
	<div class="flex justify-end gap-2">
		@if ($movimiento->estatus !== 'anulado' && $movimiento->estatus !== 'pagado')
			<form method="POST" action="{{ route('movimientos-prestaciones.anular', $movimiento) }}" onsubmit="return confirm('¿Anular este movimiento?')">
				@csrf
				<button class="px-3 py-1.5 text-sm border border-red-300 text-red-600 hover:bg-red-50">Anular</button>
			</form>
		@endif
		<form method="POST" action="{{ route('movimientos-prestaciones.destroy', $movimiento) }}" onsubmit="return confirm('¿Eliminar definitivamente?')">
			@csrf @method('DELETE')
			<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Eliminar</button>
		</form>
	</div>
</div>
@endsection
