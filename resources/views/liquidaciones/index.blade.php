@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Liquidaciones de Prestaciones Sociales</h1>
		@can('gestionar')
			<a href="{{ route('liquidaciones.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nueva liquidación</a>
		@endcan
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Liquidación de prestaciones sociales al terminar la relación laboral (Art. 142 lit. C LOTTT).
		Incluye la indemnización por terminación (Art. 92 LOTTT) cuando procede.
	</p>

	{{-- Filtros --}}
	<form method="GET" action="{{ route('liquidaciones.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Empleado</label>
			<select name="empleado_id" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todos</option>
				@foreach ($empleados as $emp)
					<option value="{{ $emp->id }}" {{ request('empleado_id') == $emp->id ? 'selected' : '' }}>{{ $emp->apellidos }}, {{ $emp->nombres }}</option>
				@endforeach
			</select>
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Estatus</label>
			<select name="estatus" onchange="this.form.submit()" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todos</option>
				<option value="por_aprobar" {{ request('estatus') === 'por_aprobar' ? 'selected' : '' }}>Por aprobar</option>
				<option value="aprobada" {{ request('estatus') === 'aprobada' ? 'selected' : '' }}>Aprobada</option>
				<option value="anulada" {{ request('estatus') === 'anulada' ? 'selected' : '' }}>Anulada</option>
			</select>
		</div>
		<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Filtrar</button>
	</form>

	{{-- Tabla --}}
	@if ($liquidaciones->isEmpty())
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">No hay liquidaciones registradas.</p>
		</div>
	@else
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Fecha de liquidación" direccion="desc" />
		</div>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Trabajador</th>
						<th class="text-center px-3 py-2">Egreso</th>
						<th class="text-center px-3 py-2">Motivo</th>
						<th class="text-center px-3 py-2">Antigüedad</th>
						<th class="text-center px-3 py-2">Días</th>
						<th class="text-right px-3 py-2">Neto</th>
						<th class="text-center px-3 py-2">Estado</th>
						<th class="text-right px-3 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($liquidaciones as $l)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2">
								<div class="font-medium">{{ $l->empleado->nombre_completo }}</div>
								<div class="text-xs text-gray-400">C.I. {{ $l->empleado->cedula }}</div>
							</td>
							<td class="px-3 py-2 text-center">{{ $l->fecha_egreso->format('d/m/Y') }}</td>
							<td class="px-3 py-2 text-center">{{ $l->labelMotivo() }}</td>
							<td class="px-3 py-2 text-center">{{ $l->anios_servicio }}a {{ $l->meses_fraccion }}m</td>
							<td class="px-3 py-2 text-center">{{ $l->dias_prestaciones }}{{ $l->esDobleIndemnizacion() ? ' + ind.' : '' }}</td>
							<td class="px-3 py-2 text-right font-medium">{{ $l->moneda }} {{ formato_monto((float) $l->neto) }}</td>
							<td class="px-3 py-2 text-center">
								<span class="inline-flex text-[10px] px-2 py-0.5 {{ $l->colorEstatus() }}">{{ $l->labelEstatus() }}</span>
							</td>
							<td class="px-3 py-2">
								<div class="flex items-center justify-end gap-0.5">
									<a href="{{ route('liquidaciones.show', $l->id) }}" class="p-1 text-gray-500 hover:text-blue-600" title="Ver liquidación">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
									</a>
									@if ($l->estatus === 'aprobada')
										<a href="{{ route('liquidaciones.pdf', $l->id) }}" target="_blank" rel="noopener" class="p-1 text-gray-500 hover:text-red-600" title="Liquidación PDF">📄</a>
									@endif
									<form action="{{ route('liquidaciones.destroy', $l->id) }}" method="POST"
										onsubmit="return confirm('¿Eliminar esta liquidación?')">
										@csrf @method('DELETE')
										<button class="p-1 text-gray-400 hover:text-red-500" title="Eliminar">🗑</button>
									</form>
								</div>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div class="mt-4 flex justify-end items-center">
			{{ $liquidaciones->links() }}
		</div>
	@endif
</div>
@endsection
