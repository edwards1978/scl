@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Sueldos</h1>
		<div class="flex gap-2">
			@if ($empleado)
				<a href="{{ route('sueldos.variaciones', ['empleado_id' => $empleado->id]) }}"
					class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">📈 Variaciones salariales</a>
				<form action="{{ route('sueldos.regenerar') }}" method="POST"
					onsubmit="return confirm('¿Recalcular todos los sueldos de {{ $empleado->nombre_completo }}?')">
					@csrf
					<input type="hidden" name="empleado_id" value="{{ $empleado->id }}">
					<button class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">↻ Recalcular</button>
				</form>
			@else
				<form action="{{ route('sueldos.regenerar') }}" method="POST"
					onsubmit="return confirm('¿Recalcular los sueldos de todos los trabajadores?')">
					@csrf
					<button class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">↻ Recalcular todos</button>
				</form>
			@endif
		</div>
	</div>
	<!-- <p class="text-sm text-gray-600 mb-4">
		Patrono: <span class="font-medium">{{ $patrono->razon_social }}</span> ({{ $patrono->rif }})
		<a href="{{ route('entorno.seleccionar') }}" class="ml-1 text-xs text-blue-600 underline">Cambiar</a>
	</p> -->

	<form method="GET" class="mb-4">
		<label class="block text-xs font-medium text-gray-600 mb-1">Trabajador</label>
		<div class="flex gap-2 max-w-xs">
			<select name="empleado_id" onchange="this.form.submit()"
				class="w-full border border-gray-300 bg-white px-2 py-1.5 text-sm">
				<option value="">Seleccionar trabajador…</option>
				@foreach ($empleados as $e)
					<option value="{{ $e->id }}" {{ $empleado?->id === $e->id ? 'selected' : '' }}>
						{{ $e->nombre_completo }} ({{ $e->cedula }})
					</option>
				@endforeach
			</select>
		</div>
	</form>

	@if ($info)
		<div class="mb-4 p-3 bg-blue-100 border border-blue-300 text-blue-700 text-sm">{{ $info }}</div>
	@endif

	@if (! $empleado)
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">Seleccione un trabajador para ver sus sueldos.</p>
			<p class="text-xs mt-1">Los sueldos se calculan a mes cerrado automáticamente al seleccionarlo.</p>
		</div>
	@else
		@php
			$totalPeriodos = method_exists($sueldos, 'total') ? $sueldos->total() : $sueldos->count();
		@endphp
		<div class="mb-3 bg-white border border-gray-200 divide-x divide-gray-200 grid grid-cols-2 md:grid-cols-4 text-sm">
			<div class="px-3 py-2"><span class="text-xs text-gray-500">Trabajador</span><span class="block font-medium">{{ $empleado->nombre_completo }}</span></div>
			<div class="px-3 py-2"><span class="text-xs text-gray-500">Sueldo ficha</span><span class="block font-medium">{{ $empleado->moneda }} {{ formato_monto((float) $empleado->salario_base) }}</span></div>
			<div class="px-3 py-2"><span class="text-xs text-gray-500">Ingreso</span><span class="block font-medium">{{ $empleado->fecha_ingreso->format('d/m/Y') }}</span></div>
			<div class="px-3 py-2"><span class="text-xs text-gray-500">Periodos</span><span class="block font-medium">{{ $totalPeriodos }}</span></div>
		</div>

		@if ($sueldos->isEmpty())
			<div class="text-center py-8 text-gray-400"><p class="text-sm">Sin sueldos registrados.</p></div>
		@else
			@if (method_exists($sueldos, 'hasPages'))
				<div class="mb-2 flex justify-end items-center">
					<div class="flex items-center gap-2 text-xs text-gray-500">
						<span>Filas por página:</span>
						<select onchange="window.location='{{ route('sueldos.index') }}?empleado_id={{ $empleado->id }}&porPagina='+this.value"
							class="border border-gray-300 px-1.5 py-0.5 text-xs bg-white">
							@foreach ([5, 10, 15, 25, 50, 100] as $n)
								<option value="{{ $n }}" {{ $sueldos->perPage() == $n ? 'selected' : '' }}>{{ $n }}</option>
							@endforeach
						</select>
					</div>
				</div>
			@endif

			<div class="bg-white border border-gray-200 overflow-x-auto">
				<table class="w-full text-sm">
					<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
						<tr>
							<th class="text-left px-4 py-2">Periodo</th>
							<th class="text-left px-4 py-2">Moneda</th>
							<th class="text-right px-4 py-2">Monto USD</th>
							<th class="text-right px-4 py-2">Monto Bs</th>
							<th class="text-right px-4 py-2">Tasa BCV</th>
							<th class="text-center px-4 py-2">Origen</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						@foreach ($sueldos as $s)
							<tr class="hover:bg-gray-50">
								<td class="px-4 py-2">{{ $s->label_periodo }}</td>
								<td class="px-4 py-2 text-xs">{{ $s->moneda_sueldo }}</td>
								<td class="px-4 py-2 text-right">{{ $s->monto_usd !== null ? 'USD '.formato_monto((float) $s->monto_usd) : '—' }}</td>
								<td class="px-4 py-2 text-right">{{ $s->monto_bs !== null ? 'Bs. '.formato_monto((float) $s->monto_bs) : '—' }}</td>
								<td class="px-4 py-2 text-right">{{ $s->tasa_bcv !== null ? formato_monto((float) $s->tasa_bcv, 4) : '—' }}</td>
								<td class="px-4 py-2 text-center">
									<span class="inline-flex text-[10px] px-2 py-0.5
										{{ $s->origen === 'cambio' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500' }}">
										{{ $s->origen === 'cambio' ? 'Cambio' : 'Ficha' }}
									</span>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			@if (method_exists($sueldos, 'hasPages'))
				<div class="mt-4 flex justify-end items-center">
					{{ $sueldos->appends(['empleado_id' => $empleado->id, 'porPagina' => $sueldos->perPage()])->links() }}
				</div>
			@endif
		@endif
	@endif
</div>
@endsection