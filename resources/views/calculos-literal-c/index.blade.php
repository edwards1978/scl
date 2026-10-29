@extends('layouts.app')

@section('content')
@php
	$hayFiltros = request()->hasAny(['buscar', 'moneda', 'desde', 'hasta']);
@endphp
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Liquidaciones rápidas Art. 142.c</h1>
		@if (auth()->user()->puedeGestionar())
			<a href="{{ route('calculos-literal-c.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nuevo cálculo</a>
		@endif
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Cálculos independientes de patronos y trabajadores. No generan renglones en prestaciones sociales.
	</p>

	<form method="GET" action="{{ route('calculos-literal-c.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<div class="flex-1 min-w-[220px]">
			<label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
			<input type="text" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Patrono, trabajador o cédula" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Moneda</label>
			<select name="moneda" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todas</option>
				@foreach (\App\Models\CalculoLiquidacionLiteralC::monedas() as $valor => $etiqueta)
					<option value="{{ $valor }}" {{ ($filtros['moneda'] ?? '') === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
				@endforeach
			</select>
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Egreso desde</label>
			<input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Egreso hasta</label>
			<input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Filtrar</button>
		@if ($hayFiltros)
			<a href="{{ route('calculos-literal-c.index') }}" class="px-2 py-1.5 text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
		@endif
	</form>

	@if ($calculos->isEmpty())
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">No hay cálculos registrados para el filtro seleccionado.</p>
		</div>
	@else
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Fecha de egreso" direccion="desc" />
		</div>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Trabajador</th>
						<th class="text-left px-3 py-2">Patrono</th>
						<th class="text-center px-3 py-2">Egreso</th>
						<th class="text-right px-3 py-2">Neto</th>
						<th class="text-left px-3 py-2">Creado</th>
						<th class="text-right px-3 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($calculos as $calculo)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2">
								<div class="font-medium">{{ $calculo->trabajador_nombre }}</div>
								<div class="text-xs text-gray-400">C.I. {{ $calculo->trabajador_cedula ?: '—' }}</div>
							</td>
							<td class="px-3 py-2">{{ $calculo->patrono_nombre }}</td>
							<td class="px-3 py-2 text-center">{{ $calculo->fecha_egreso->format('d/m/Y') }}</td>
							<td class="px-3 py-2 text-right font-medium">{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->neto) }}</td>
							<td class="px-3 py-2 text-xs text-gray-500">
								{{ $calculo->creador?->name ?? '—' }}<br>
								{{ $calculo->created_at?->format('d/m/Y H:i') }}
							</td>
							<td class="px-3 py-2">
								<div class="flex items-center justify-end gap-0.5">
									<a href="{{ route('calculos-literal-c.show', $calculo) }}" class="p-1 text-gray-500 hover:text-blue-600" title="Ver detalle">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
									</a>
									<a href="{{ route('calculos-literal-c.pdf', $calculo) }}" target="_blank" rel="noopener" class="p-1 text-gray-500 hover:text-red-600" title="Recibo PDF">📄</a>
									@if (auth()->user()->puedeGestionar())
										<a href="{{ route('calculos-literal-c.edit', $calculo) }}" class="p-1 text-gray-500 hover:text-blue-600" title="Editar">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487a2.06 2.06 0 112.915 2.915L7.5 19.68 3 21l1.32-4.5L16.862 4.487z"/></svg>
										</a>
										<form method="POST" action="{{ route('calculos-literal-c.duplicar', $calculo) }}">
											@csrf
											<button class="p-1 text-gray-500 hover:text-blue-600" title="Duplicar cálculo">
												<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V5a2 2 0 012-2h7a2 2 0 012 2v7a2 2 0 01-2 2h-2"/><rect x="4" y="8" width="10" height="10" rx="2" stroke-width="2"/></svg>
											</button>
										</form>
										<form method="POST" action="{{ route('calculos-literal-c.destroy', $calculo) }}" onsubmit="return confirm('¿Eliminar definitivamente este cálculo?')">
											@csrf
											@method('DELETE')
											<button class="p-1 text-gray-400 hover:text-red-500" title="Eliminar">🗑</button>
										</form>
									@endif
								</div>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div class="mt-4 flex justify-between items-center">
			<div class="text-xs text-gray-500">{{ $calculos->total() }} registros</div>
			{{ $calculos->appends(request()->except('page'))->links() }}
		</div>
	@endif
</div>
@endsection
