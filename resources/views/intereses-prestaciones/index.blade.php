@extends('layouts.app')

@section('content')
@php
	$hayFiltros = request()->hasAny(['empleado_id', 'desde', 'hasta']);
	$empleadoFiltroInactivo = $empleadoFiltro && ! $empleadoFiltro->estaActivo();
@endphp
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Pagos de Intereses sobre Prestaciones</h1>
		@can('gestionar')
			<a href="{{ route('intereses-prestaciones.create', array_filter(['empleado_id' => $filtros['empleado_id'] ?? null])) }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nuevo pago</a>
		@endcan
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Intereses del Art. 143 LOTTT. Se aplican al interés pendiente del trabajador y recalculan su garantía.
	</p>

	{{-- Filtros --}}
	<form method="GET" action="{{ route('intereses-prestaciones.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Empleado</label>
			<select name="empleado_id" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todos</option>
				@foreach ($empleados as $emp)
					<option value="{{ $emp->id }}" {{ (int) ($filtros['empleado_id'] ?? 0) === (int) $emp->id ? 'selected' : '' }}>{{ $emp->apellidos }}, {{ $emp->nombres }}</option>
				@endforeach
			</select>
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
			<input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
			<input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Filtrar</button>
		@if ($hayFiltros)
			<a href="{{ route('intereses-prestaciones.index') }}" class="px-2 py-1.5 text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
		@endif
	</form>

	@if ($empleadoFiltroInactivo)
		<div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs">
			Historial de {{ $empleadoFiltro->nombre_completo }}: trabajador inactivo o liquidado. Solo consulta.
		</div>
	@endif

	{{-- Totales del filtro --}}
	<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Total pagado en USD</p>
			<p class="text-lg font-semibold">{{ formato_monto((float) ($totales['USD'] ?? 0)) }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-4">
			<p class="text-xs text-gray-500 mb-1">Total pagado en Bs.</p>
			<p class="text-lg font-semibold">{{ formato_monto((float) ($totales['BS'] ?? 0)) }}</p>
		</div>
	</div>

	{{-- Listado --}}
	@if ($pagos->isEmpty())
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">No hay pagos registrados para el filtro seleccionado.</p>
		</div>
	@else
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Fecha" direccion="desc" />
		</div>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Trabajador</th>
						<th class="text-center px-3 py-2">Fecha</th>
						<th class="text-center px-3 py-2">F. comprobante</th>
						<th class="text-right px-3 py-2">Monto</th>
						<th class="text-right px-3 py-2">Tasa</th>
						<th class="text-left px-3 py-2">Comprobante</th>
						<th class="text-left px-3 py-2">Observación</th>
						<th class="text-right px-3 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($pagos as $p)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2">
								<div class="font-medium">{{ $p->empleado?->nombre_completo ?? '—' }}</div>
								<div class="text-xs text-gray-400">C.I. {{ $p->empleado?->cedula ?? '—' }}</div>
							</td>
							<td class="px-3 py-2 text-center">{{ $p->fecha->format('d/m/Y') }}</td>
							<td class="px-3 py-2 text-center">{{ $p->fecha_comprobante?->format('d/m/Y') ?? '—' }}</td>
							<td class="px-3 py-2 text-right font-medium">{{ $p->moneda }} {{ formato_monto((float) $p->monto) }}</td>
							<td class="px-3 py-2 text-right">{{ $p->tasa_cambio ? formato_monto((float) $p->tasa_cambio, 4) : '—' }}</td>
							<td class="px-3 py-2">{{ $p->comprobante ?: '—' }}</td>
							<td class="px-3 py-2">{{ $p->observacion ?: '—' }}</td>
							<td class="px-3 py-2">
								<div class="flex items-center justify-end gap-0.5">
									<a href="{{ route('intereses-prestaciones.show', $p) }}" class="p-1 text-gray-500 hover:text-blue-600" title="Ver detalle">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
									</a>
									@can('eliminar')
										<form action="{{ route('intereses-prestaciones.destroy', $p) }}" method="POST"
											onsubmit="return confirm('¿Eliminar este pago de intereses?')">
											@csrf @method('DELETE')
											<button class="p-1 text-gray-400 hover:text-red-500" title="Eliminar">🗑</button>
										</form>
									@endcan
								</div>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div class="mt-4 flex justify-between items-center">
			<div class="text-xs text-gray-500">{{ $pagos->total() }} registros</div>
			{{ $pagos->appends(request()->except('page'))->links() }}
		</div>
	@endif
</div>
@endsection
