@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-3">
		<h1 class="text-xl font-semibold">Vacaciones (LOTTT)</h1>
		@can('gestionar')
			<a href="{{ route('vacaciones.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nueva Vacación</a>
		@endcan
	</div>

	<!-- <p class="text-sm text-gray-600 mb-4">
		Patrono: <span class="font-medium">{{ $patrono->razon_social }}</span> ({{ $patrono->rif }})
		<a href="{{ route('entorno.seleccionar') }}" class="ml-1 text-xs text-blue-600 underline">Cambiar</a>
	</p> -->

	{{-- Filtros --}}
	<form method="GET" action="{{ route('vacaciones.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Trabajador</label>
			<select name="empleado_id" class="border border-gray-300 px-2 py-1.5 text-sm bg-white w-56">
				<option value="">Todos</option>
				@foreach ($empleados as $e)
					<option value="{{ $e->id }}" {{ request('empleado_id') == $e->id ? 'selected' : '' }}>{{ $e->nombre_completo }} ({{ $e->cedula }})</option>
				@endforeach
			</select>
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Año (periodo)</label>
			<select name="ano" class="border border-gray-300 px-2 py-1.5 text-sm bg-white w-28">
				<option value="">Todos</option>
				@for ($a = now()->year; $a >= now()->year - 3; $a--)
					<option value="{{ $a }}" {{ request('ano') == $a ? 'selected' : '' }}>{{ $a }}</option>
				@endfor
			</select>
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Estatus cálculo</label>
			<select name="estatus_calculo" class="border border-gray-300 px-2 py-1.5 text-sm bg-white w-44">
				<option value="">Todos</option>
				<option value="por_aprobar" {{ request('estatus_calculo') == 'por_aprobar' ? 'selected' : '' }}>Por aprobar</option>
				<option value="aprobada" {{ request('estatus_calculo') == 'aprobada' ? 'selected' : '' }}>Aprobada</option>
				<option value="no_aprobada" {{ request('estatus_calculo') == 'no_aprobada' ? 'selected' : '' }}>No aprobada</option>
			</select>
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Estatus vacación</label>
			<select name="estatus_vacacion" class="border border-gray-300 px-2 py-1.5 text-sm bg-white w-44">
				<option value="">Todos</option>
				<option value="pendiente" {{ request('estatus_vacacion') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
				<option value="pagada" {{ request('estatus_vacacion') == 'pagada' ? 'selected' : '' }}>Pagada/Disfrutada</option>
			</select>
		</div>
		<button type="submit" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Filtrar</button>
		@if (request()->hasAny(['empleado_id', 'ano', 'estatus_calculo', 'estatus_vacacion']))
			<a href="{{ route('vacaciones.index') }}" class="px-2 py-1.5 text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
		@endif
	</form>

	@if ($vacaciones->isEmpty())
		<div class="text-center py-8 text-gray-400"><p class="text-sm">No hay periodos de vacaciones registrados para este patrono.</p></div>
	@else
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Fecha de cálculo" direccion="desc" />
		</div>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-xs">
				<thead class="bg-gray-50 text-gray-500 uppercase">
					<tr>
						<th class="text-left px-3 py-2">Trabajador</th>
						<th class="text-left px-3 py-2">Periodo</th>
						<th class="text-center px-3 py-2">Inicio</th>
						<th class="text-center px-3 py-2">Fin</th>
						<th class="text-center px-3 py-2">Reintegro</th>
						<th class="text-right px-3 py-2">Vacac.</th>
						<th class="text-right px-3 py-2">Bono</th>
						<th class="text-center px-3 py-2">Calc.</th>
						<th class="text-center px-3 py-2">Vac.</th>
						<th class="text-right px-3 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($vacaciones as $v)
						@php($e = $v->empleado)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2">{{ $e->nombre_completo ?? '—' }}</td>
							<td class="px-3 py-2">{{ $v->periodo_desde->format('d/m/Y') }} — {{ $v->periodo_hasta->format('d/m/Y') }}</td>
							<td class="px-3 py-2 text-center">{{ $v->fecha_inicio_real?->format('d/m/Y') ?? '—' }}</td>
							<td class="px-3 py-2 text-center">{{ $v->fecha_fin_real?->format('d/m/Y') ?? '—' }}</td>
							<td class="px-3 py-2 text-center">{{ $v->fecha_reintegro?->format('d/m/Y') ?? '—' }}</td>
							<td class="px-3 py-2 text-right">{{ $v->dias_vacaciones + $v->dias_adicionales }}</td>
							<td class="px-3 py-2 text-right">{{ $v->dias_bono + $v->dias_bono_adicionales }}</td>
							<td class="px-3 py-2 text-center">
								<span class="inline-block px-2 py-0.5 text-[10px] rounded {{ $v->colorEstatusCalculo() }}">{{ $v->labelEstatusCalculo() }}</span>
							</td>
							<td class="px-3 py-2 text-center">
								<span class="inline-block px-2 py-0.5 text-[10px] rounded {{ $v->colorEstatusVacacion() }}">{{ $v->labelEstatusVacacion() }}</span>
							</td>
							<td class="px-3 py-2">
								<div class="flex items-center justify-end gap-0.5">
									<a href="{{ route('vacaciones.show', $v) }}" class="p-1 text-gray-500 hover:text-blue-600" title="Ver cálculo">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.45 12C5.9 7 10.73 4 16 4a11.93 0 015.66 1.41l-1.55 1.55A8.96 8.96 0 0016 6c-4.42 0-8.37 2.55-10.22 6.36.03.07.05.15.05.24 0 .09-.02.17-.05.24C7.63 15.44 11.58 13 16 13c2.04 0 3.96.53 5.62 1.45l1.55-1.55A11.93 11.93 0 0116 20c-5.27 0-10.1-.99-13.55-5 0-.02 0-.04 0-.06z"/></svg>
									</a>
									@can('aprobar')
									@if ($v->puedePagar())
										<form action="{{ route('vacaciones.estatus', $v) }}" method="POST" onsubmit="return confirm('¿Marcar como pagada/disfrutada?')">
											@csrf @method('PUT')
											<input type="hidden" name="accion" value="pagar">
											<button type="submit" class="p-1 text-gray-500 hover:text-green-600" title="Pagar y disfrutar">💰</button>
										</form>
									@elseif ($v->puedeAprobar())
										<form action="{{ route('vacaciones.estatus', $v) }}" method="POST" onsubmit="return confirm('¿Aprobar este cálculo?')">
											@csrf @method('PUT')
											<input type="hidden" name="accion" value="aprobar">
											<button type="submit" class="p-1 text-gray-500 hover:text-amber-600" title="Aprobar">✓</button>
										</form>
									@endif
									@endcan
									@if (in_array($v->estatus_vacacion, ['pagada', 'disfrutada'], true))
										<a href="{{ route('vacaciones.pdf', $v) }}" target="_blank" rel="noopener" class="p-1 text-gray-500 hover:text-red-600" title="Recibo PDF">📄</a>
									@endif
									@if ($v->puedeBorrar())
										@can('eliminar')
										<form action="{{ route('vacaciones.destroy', $v) }}" method="POST" onsubmit="return confirm('¿Eliminar este periodo?')">
											@csrf @method('DELETE')
											<button type="submit" class="p-1 text-gray-400 hover:text-red-500" title="Eliminar">🗑</button>
										</form>
										@endcan
									@endif
								</div>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div class="mt-3 flex justify-between items-center">
			<div class="text-xs text-gray-500">{{ $vacaciones->total() }} registros</div>
			{{ $vacaciones->appends(request()->except('page'))->links() }}
		</div>
	@endif
</div>
@endsection
