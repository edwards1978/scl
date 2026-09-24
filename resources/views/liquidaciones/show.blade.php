@extends('layouts.app')

@section('content')
@php
	$editando = $liquidacion->estatus !== 'aprobada';
@endphp
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Liquidación de Prestaciones</h1>
		<a href="{{ route('liquidaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		{{ $empleado->nombre_completo }} · C.I. {{ $empleado->cedula }} · Motivo: {{ $liquidacion->labelMotivo() }}
	</p>

	@if ($liquidacion->estatus === 'anulada')
		<div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 text-sm">Esta liquidación fue anulada.</div>
	@endif
	@if ($liquidacion->esDobleIndemnizacion())
		<div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm">
			Incluye indemnización por terminación (Art. 92 LOTTT) por valor equivalente a las prestaciones sociales.
		</div>
	@endif

    @if(session('success'))
        <div class="mx-4 mt-3 p-3 bg-green-100 border border-green-300 text-green-700 text-sm rounded">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mx-4 mt-3 p-3 bg-red-100 border border-red-300 text-red-700 text-sm rounded">{{ session('error') }}</div>
    @endif

	<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Antigüedad</p>
			<p class="text-lg font-semibold">{{ $liquidacion->anios_servicio }}a {{ $liquidacion->meses_fraccion }}m</p>
			<p class="text-xs text-gray-400 mt-1">Ingreso {{ $liquidacion->fecha_ingreso->format('d/m/Y') }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Días prestaciones</p>
			<p class="text-lg font-semibold">{{ $liquidacion->dias_prestaciones }}</p>
			<p class="text-xs text-gray-400 mt-1">Salario integral {{ formato_monto((float) $liquidacion->salario_integral_diario) }} {{ $liquidacion->moneda }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Sueldo diario normal</p>
			<p class="text-lg font-semibold">{{ $liquidacion->moneda }} {{ formato_monto((float) $liquidacion->salario_diario_normal) }}</p>
			<p class="text-xs text-gray-400 mt-1">Base para vacaciones y utilidades</p>
		</div>
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Salario diario integral</p>
			<p class="text-lg font-semibold">{{ $liquidacion->moneda }} {{ formato_monto((float) $liquidacion->salario_integral_diario) }}</p>
			<p class="text-xs text-gray-400 mt-1">Base para prestaciones e indemnización</p>
		</div>
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Total asignaciones</p>
			<p class="text-lg font-semibold text-green-700">{{ $liquidacion->moneda }} {{ formato_monto((float) $liquidacion->total_asignaciones) }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Neto a pagar</p>
			<p class="text-lg font-semibold text-blue-700">{{ $liquidacion->moneda }} {{ formato_monto((float) $liquidacion->neto) }}</p>
		</div>
	</div>

    {{-- Conceptos editables --}}
    <form method="POST" action="{{ route('liquidaciones.conceptos.update', $liquidacion->id) }}">
        @csrf @method('PUT')
        @if ($errors->any())
            <div class="mx-4 mb-4 p-3 bg-red-100 border border-red-300 text-red-700 text-sm rounded">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="bg-white border border-gray-200 overflow-x-auto mb-4">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Concepto</th>
						<th class="text-left px-3 py-2">Base legal</th>
						<th class="text-center px-3 py-2 w-24">Días</th>
						<th class="text-center px-3 py-2 w-32">Sueldo diario</th>
						<th class="text-right px-3 py-2 w-32">Importe</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($liquidacion->asignaciones as $c)
						<tr>
							<td class="px-3 py-2 font-medium">{{ $c->concepto }}</td>
							<td class="px-3 py-2 text-xs text-gray-500">{{ $c->base_legal ?? '—' }}</td>
							<td class="px-3 py-2">
								<input type="number" step="0.01" min="0" name="conceptos[{{ $c->id }}][dias]"
									value="{{ $c->dias ?? '' }}" {{ $editando ? '' : 'disabled' }}
									class="w-full border border-gray-300 px-2 py-1 text-right text-sm @error('conceptos.'.$c->id.'.dias') border-red-500 @enderror">
								@error('conceptos.'.$c->id.'.dias')
									<span class="text-red-500 text-xs">{{ $message }}</span>
								@enderror
							</td>
							<td class="px-3 py-2">
								@if ($c->salario_diario !== null)
									<input type="number" step="0.01" min="0" name="conceptos[{{ $c->id }}][salario_diario]"
										value="{{ $c->salario_diario }}" {{ $editando ? '' : 'disabled' }}
										class="w-full border border-gray-300 px-2 py-1 text-right text-sm">
									<div class="text-[10px] text-gray-400 text-right">
										{{ $c->tipo_salario === 'integral' ? 'salario integral' : ($c->tipo_salario === 'normal' ? 'salario normal' : '') }}
									</div>
								@else
									<div class="text-xs text-gray-300 text-center">—</div>
								@endif
							</td>
							<td class="px-3 py-2">
								<input type="number" step="0.01" min="0" name="conceptos[{{ $c->id }}][importe]"
									value="{{ $c->importe }}" {{ $editando ? '' : 'disabled' }}
									class="w-full border border-gray-300 px-2 py-1 text-right text-sm @error('conceptos.'.$c->id.'.importe') border-red-500 @enderror">
								@error('conceptos.'.$c->id.'.importe')
									<span class="text-red-500 text-xs">{{ $message }}</span>
								@enderror
							</td>
						</tr>
					@endforeach
					<tr class="bg-gray-50 font-medium">
						<td colspan="4" class="px-3 py-2 text-right">Total asignaciones</td>
						<td class="px-3 py-2 text-right">{{ $liquidacion->moneda }} {{ formato_monto((float) $liquidacion->total_asignaciones) }}</td>
					</tr>
				</tbody>
				<tbody class="divide-y divide-gray-100">
					@foreach ($liquidacion->deducciones as $c)
						<tr>
							<td class="px-3 py-2 text-gray-600">{{ $c->concepto }}</td>
							<td class="px-3 py-2 text-xs text-gray-500">{{ $c->base_legal ?? '—' }}</td>
							<td class="px-3 py-2">
								<input type="number" step="0.01" min="0" name="conceptos[{{ $c->id }}][dias]"
									value="{{ $c->dias ?? '' }}" {{ $editando ? '' : 'disabled' }}
									class="w-full border border-gray-300 px-2 py-1 text-right text-sm @error('conceptos.'.$c->id.'.dias') border-red-500 @enderror">
								@error('conceptos.'.$c->id.'.dias')
									<span class="text-red-500 text-xs">{{ $message }}</span>
								@enderror
							</td>
							<td class="px-3 py-2">
								@if ($c->salario_diario !== null)
									<input type="number" step="0.01" min="0" name="conceptos[{{ $c->id }}][salario_diario]"
										value="{{ $c->salario_diario }}" {{ $editando ? '' : 'disabled' }}
										class="w-full border border-gray-300 px-2 py-1 text-right text-sm">
									<div class="text-[10px] text-gray-400 text-right">
										{{ $c->tipo_salario === 'integral' ? 'salario integral' : ($c->tipo_salario === 'normal' ? 'salario normal' : '') }}
									</div>
								@else
									<div class="text-xs text-gray-300 text-center">—</div>
								@endif
							</td>
							<td class="px-3 py-2">
								<input type="number" step="0.01" min="0" name="conceptos[{{ $c->id }}][importe]"
									value="{{ $c->importe }}" {{ $editando ? '' : 'disabled' }}
									class="w-full border border-gray-300 px-2 py-1 text-right text-sm @error('conceptos.'.$c->id.'.importe') border-red-500 @enderror">
								@error('conceptos.'.$c->id.'.importe')
									<span class="text-red-500 text-xs">{{ $message }}</span>
								@enderror
							</td>
						</tr>
					@endforeach
					<tr class="bg-gray-50">
						<td colspan="4" class="px-3 py-2 text-right text-gray-600">Total deducciones</td>
						<td class="px-3 py-2 text-right text-red-600">- {{ formato_monto((float) $liquidacion->total_deducciones) }}</td>
					</tr>
				</tbody>
				<tfoot>
					<tr class="bg-gray-50 font-semibold">
						<td colspan="4" class="px-3 py-2 text-right">Neto a pagar</td>
						<td class="px-3 py-2 text-right text-blue-700">{{ $liquidacion->moneda }} {{ formato_monto((float) $liquidacion->neto) }}</td>
					</tr>
				</tfoot>
			</table>
		</div>

		@if ($editando)
			<div class="flex justify-end mb-4">
				<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Guardar cambios</button>
			</div>
		@endif
	</form>

	{{-- Acciones de estatus --}}
	<div class="flex justify-end gap-2">
		@if ($liquidacion->estatus === 'por_aprobar')
			<form method="POST" action="{{ route('liquidaciones.anular', $liquidacion->id) }}" onsubmit="return confirm('¿Anular esta liquidación?')">
				@csrf
				<button class="px-3 py-1.5 text-sm border border-red-300 text-red-600 hover:bg-red-50">Anular</button>
			</form>
			<form method="POST" action="{{ route('liquidaciones.aprobar', $liquidacion->id) }}" onsubmit="return confirm('¿Aprobar esta liquidación?')">
				@csrf
				<button class="px-4 py-1.5 text-sm bg-green-600 text-white hover:bg-green-700">Aprobar liquidación</button>
			</form>
		@elseif ($liquidacion->estatus === 'aprobada')
			<a href="{{ route('liquidaciones.pdf', $liquidacion->id) }}" target="_blank" rel="noopener" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Descargar PDF</a>
		@endif
	</div>
</div>
@endsection