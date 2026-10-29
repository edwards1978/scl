@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Bonificación de Fin de Año</h1>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Anticipo a las utilidades (Art. 132 LOTTT). El período de cálculo es el año natural, asumiendo
		los meses laborados hasta diciembre. La bonificación se determina como fracción del tope de días
		(límite máximo 120).
	</p>

	{{-- Filtros --}}
	<form method="GET" action="{{ route('utilidades.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Año</label>
			<select name="anio" onchange="this.form.submit()" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="todos" {{ $anio === 'todos' ? 'selected' : '' }}>Mostrar todos los años</option>
				@foreach ($anios as $a)
					<option value="{{ $a }}" {{ $anio === (string) $a ? 'selected' : '' }}>{{ $a }}</option>
				@endforeach
			</select>
		</div>
		<div class="flex-1"></div>
		<div class="text-xs text-gray-500">
			Días de bono configurados: <span class="font-medium">{{ $patrono->dias_bonificacion }}</span>
		</div>
	</form>

	{{-- Calcular / aprobar en bloque --}}
	<div class="mb-4 flex flex-wrap items-stretch gap-3">
		<form method="POST" action="{{ route('utilidades.calcular') }}" class="flex-1 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
			@csrf
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-1">Año a calcular</label>
				<input type="number" name="anio" min="2000" max="2200" value="{{ $anio !== 'todos' ? $anio : now()->year }}" class="w-28 border border-gray-300 px-2 py-1.5 text-sm">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-1">Base del salario</label>
				<select name="tipo_base" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
					<option value="ultimo">Último sueldo registrado</option>
					<option value="promedio">Promedio del período</option>
				</select>
			</div>
			<button class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Calcular bonificación</button>
		</form>

		@if ($pendientes > 0)
			@can('aprobar')
			<form method="POST" action="{{ route('utilidades.aprobar-todas') }}"
				class="bg-white border border-gray-200 p-3 flex items-end"
				onsubmit="return confirm('¿Aprobar las {{ $pendientes }} bonificaciones pendientes{{ $anio !== 'todos' ? ' del año '.$anio : '' }}?')">
				@csrf
				<input type="hidden" name="anio" value="{{ $anio }}">
				<button class="px-3 py-1.5 text-sm bg-amber-600 text-white hover:bg-amber-700">
					✓ Aprobar todas las pendientes ({{ $pendientes }})
				</button>
			</form>
			@endcan
		@endif
	</div>

	{{-- Tabla general --}}
	@if ($bonificaciones->isEmpty())
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">No hay bonificaciones registradas{{ $anio !== 'todos' ? " para el año {$anio}" : '' }}.</p>
		</div>
	@else
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Año" direccion="desc" />
		</div>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Trabajador</th>
						<th class="text-center px-3 py-2">Año</th>
						<th class="text-center px-3 py-2">Meses</th>
						<th class="text-center px-3 py-2">Días</th>
						<th class="text-right px-3 py-2">Bono</th>
						<th class="text-center px-3 py-2">Estado</th>
						<th class="text-right px-3 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($bonificaciones as $b)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2">
								<div class="font-medium">{{ $b->empleado->nombre_completo }}</div>
								<div class="text-xs text-gray-400">C.I. {{ $b->empleado->cedula }}</div>
							</td>
							<td class="px-3 py-2 text-center">{{ $b->anio }}</td>
							<td class="px-3 py-2 text-center">{{ $b->meses_laborados }}</td>
							<td class="px-3 py-2 text-center">{{ $b->dias_a_pagar }}</td>
							<td class="px-3 py-2 text-right font-medium">
								$ {{ formato_monto((float) $b->bono_usd) }}
								<div class="text-xs text-gray-400">Bs. {{ formato_monto((float) $b->bono_bs) }}</div>
							</td>
							<td class="px-3 py-2 text-center">
								<span class="inline-flex text-[10px] px-2 py-0.5
									{{ $b->estatus === 'aprobado' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">
									{{ $b->estatus === 'aprobado' ? 'Aprobada' : 'Pendiente' }}
								</span>
							</td>
<td class="px-3 py-2">
							<div class="flex items-center justify-end gap-0.5">
								@if ($b->estatus === 'aprobado')
									<a href="{{ route('utilidades.show', $b->id) }}" class="p-1 text-gray-500 hover:text-blue-600" title="Ver recibo">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.45 12C5.9 7 10.73 4 16 4a11.93 0 015.66 1.41l-1.55 1.55A8.96 8.96 0 0016 6c-4.42 0-8.37 2.55-10.22 6.36.03.07.05.15.05.24 0 .09-.02.17-.05.24C7.63 15.44 11.58 13 16 13c2.04 0 3.96.53 5.62 1.45l1.55-1.55A11.93 11.93 0 0116 20c-5.27 0-10.1-.99-13.55-5 0-.02 0-.04 0-.06z"/></svg>
									</a>
									<a href="{{ route('utilidades.pdf', $b->id) }}" target="_blank" rel="noopener" class="p-1 text-gray-500 hover:text-red-600" title="Recibo PDF">📄</a>
								@else
									@can('aprobar')
										<form action="{{ route('utilidades.aprobar', $b->id) }}" method="POST" onsubmit="return confirm('¿Aprobar esta bonificación?')">
											@csrf
											<button class="p-1 text-gray-500 hover:text-amber-600" title="Aprobar">✓</button>
										</form>
									@endcan
								@endif
								<form action="{{ route('utilidades.recalcular', $b->id) }}" method="POST" onsubmit="return confirm('¿Recalcular esta bonificación?')">
									@csrf
									<button class="p-1 text-gray-500 hover:text-blue-600" title="Recalcular">↻</button>
								</form>
								@can('eliminar')
									<form action="{{ route('utilidades.destroy', $b->id) }}" method="POST"
										onsubmit="return confirm('¿Eliminar esta bonificación?')">
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

		<div class="mt-4 flex justify-end items-center">
			{{ $bonificaciones->appends(['anio' => $anio])->links() }}
		</div>
	@endif
</div>
@endsection