@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	@php($moneda = $patrono->empleados()->value('moneda') ?: 'USD')
	<div class="flex items-center justify-between mb-3">
		<h1 class="text-xl font-semibold">Reporte de Vacaciones</h1>
		<a href="{{ route('reportes.vacaciones.pdf', request()->query()) }}" target="_blank" rel="noopener" class="px-3 py-1.5 text-sm bg-red-600 text-white hover:bg-red-700">📄 Exportar PDF</a>
	</div>

<!-- 	<p class="text-sm text-gray-600 mb-4">
		Patrono: <span class="font-medium">{{ $patrono->razon_social }}</span> ({{ $patrono->rif }})
		<a href="{{ route('entorno.seleccionar') }}" class="ml-1 text-xs text-blue-600 underline">Cambiar</a>
	</p>
 -->
	<form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
		<div class="flex flex-col">
			<label class="text-xs text-gray-500 mb-0.5">Trabajador</label>
			<select name="empleado_id" class="border border-gray-300 px-2 py-1.5 text-sm bg-white w-56">
				<option value="">Todos</option>
				@foreach ($empleados as $e)
					<option value="{{ $e->id }}" {{ request('empleado_id') == $e->id ? 'selected' : '' }}>{{ $e->nombre_completo }} ({{ $e->cedula }})</option>
				@endforeach
			</select>
		</div>
		<div class="flex flex-col">
			<label class="text-xs text-gray-500 mb-0.5">Año (periodo)</label>
			<select name="ano" class="border border-gray-300 px-2 py-1.5 text-sm bg-white w-28">
				<option value="">Todos</option>
				@for ($a = now()->year; $a >= now()->year - 3; $a--)
					<option value="{{ $a }}" {{ request('ano') == $a ? 'selected' : '' }}>{{ $a }}</option>
				@endfor
			</select>
		</div>
		<div class="flex flex-col">
			<label class="text-xs text-gray-500 mb-0.5">Estatus cálculo</label>
			<select name="estatus_calculo" class="border border-gray-300 px-2 py-1.5 text-sm bg-white w-44">
				<option value="">Todos</option>
				<option value="por_aprobar" {{ request('estatus_calculo') == 'por_aprobar' ? 'selected' : '' }}>Por aprobar</option>
				<option value="aprobada" {{ request('estatus_calculo') == 'aprobada' ? 'selected' : '' }}>Aprobada</option>
				<option value="no_aprobada" {{ request('estatus_calculo') == 'no_aprobada' ? 'selected' : '' }}>No aprobada</option>
			</select>
		</div>
		<div class="flex flex-col">
			<label class="text-xs text-gray-500 mb-0.5">Estatus vacación</label>
			<select name="estatus_vacacion" class="border border-gray-300 px-2 py-1.5 text-sm bg-white w-44">
				<option value="">Todos</option>
				<option value="pendiente" {{ request('estatus_vacacion') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
				<option value="pagada" {{ request('estatus_vacacion') == 'pagada' ? 'selected' : '' }}>Pagada/Disfrutada</option>
				<option value="disfrutada" {{ request('estatus_vacacion') == 'disfrutada' ? 'selected' : '' }}>Disfrutada</option>
			</select>
		</div>
		<div class="flex items-end gap-2">
			<button type="submit" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Filtrar</button>
			@if (request()->hasAny(['empleado_id', 'ano', 'estatus_calculo', 'estatus_vacacion']))
				<a href="{{ route('reportes.vacaciones.index') }}" class="px-2 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-100">Limpiar</a>
			@endif
		</div>
	</form>

	<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-[10px] uppercase text-gray-400 font-semibold">Registros</p>
			<p class="text-xl font-bold">{{ number_format($estadisticas['totalRegistros'], 0, ',', '.') }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-[10px] uppercase text-gray-400 font-semibold">Días vacaciones</p>
			<p class="text-xl font-bold">{{ number_format($estadisticas['totalDiasVacaciones'], 0, ',', '.') }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-[10px] uppercase text-gray-400 font-semibold">Días bono</p>
			<p class="text-xl font-bold">{{ number_format($estadisticas['totalDiasBono'], 0, ',', '.') }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-[10px] uppercase text-gray-400 font-semibold">Asignaciones</p>
			<p class="text-xl font-bold">{{ $moneda }} {{ formato_monto($estadisticas['totalAsignaciones']) }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-[10px] uppercase text-gray-400 font-semibold">Deducciones</p>
			<p class="text-xl font-bold">{{ $moneda }} {{ formato_monto($estadisticas['totalDeducciones']) }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-[10px] uppercase text-gray-400 font-semibold">Neto pagado</p>
			<p class="text-xl font-bold text-green-700">{{ $moneda }} {{ formato_monto($estadisticas['totalNeto']) }}</p>
		</div>
	</div>

	@if ($vacaciones->isEmpty())
		<div class="text-center py-8 text-gray-400"><p class="text-sm">No hay periodos de vacaciones que coincidan con los filtros.</p></div>
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
						<th class="text-left px-3 py-2">Disfrute</th>
						<th class="text-center px-3 py-2">Vacac.</th>
						<th class="text-center px-3 py-2">Bono</th>
						<th class="text-right px-3 py-2">Salario</th>
						<th class="text-right px-3 py-2">Neto</th>
						<th class="text-center px-3 py-2">Calc.</th>
						<th class="text-center px-3 py-2">Vac.</th>
						<th class="text-center px-3 py-2">Ver</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($vacaciones as $v)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2">{{ $v->empleado->nombre_completo ?? '—' }}</td>
							<td class="px-3 py-2">{{ $v->periodo_desde->format('d/m/Y') }} — {{ $v->periodo_hasta->format('d/m/Y') }}</td>
							<td class="px-3 py-2">{{ $v->fecha_inicio_real?->format('d/m/Y') ?? '—' }} — {{ $v->fecha_fin_real?->format('d/m/Y') ?? '—' }}</td>
							<td class="px-3 py-2 text-center">{{ $v->diasHabilesDisfrute() }}</td>
							<td class="px-3 py-2 text-center">{{ $v->diasHabilesBono() }}</td>
							<td class="px-3 py-2 text-right">{{ $v->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $v->salario_normal) }}</td>
							<td class="px-3 py-2 text-right">
								@php($recibo = $v->recibos()->latest('fecha_emision')->first())
								@if ($recibo)
									{{ $v->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $recibo->neto) }}
								@else
									—
								@endif
							</td>
							<td class="px-3 py-2 text-center"><span class="inline-block px-2 py-0.5 text-[10px] {{ $v->colorEstatusCalculo() }}">{{ $v->labelEstatusCalculo() }}</span></td>
							<td class="px-3 py-2 text-center"><span class="inline-block px-2 py-0.5 text-[10px] {{ $v->colorEstatusVacacion() }}">{{ $v->labelEstatusVacacion() }}</span></td>
							<td class="px-3 py-2 text-center"><a href="{{ route('vacaciones.show', $v) }}" class="text-blue-600 hover:underline">Ver</a></td>
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