@extends('layouts.app')

@section('content')
<div class="p-4 mx-4 max-w-5xl">
	<h1 class="text-xl font-semibold mb-1">Tasas de Interés sobre Prestaciones Sociales</h1>
	<p class="text-sm text-gray-600 mb-4">
		Tasa de interés promedio entre la activa y la pasiva aplicable al cálculo de los intereses
		sobre prestaciones sociales, publicada por el Banco Central de Venezuela.
	</p>

	<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
		<div>
			<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Registrar tasa manual</h3>
			<form action="{{ route('tasas-interes.store') }}" method="POST"
				class="bg-white border border-gray-200 p-4 space-y-3">
				@csrf
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-1">Periodo (YYYY-MM)</label>
					<input type="text" name="periodo" required pattern="\d{4}-(0[1-9]|1[0-2])" placeholder="2026-06"
						class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-1">Tasa de interés (%)</label>
					<input type="number" name="tasa_interes" step="0.0001" min="0" required
						class="w-full border border-gray-300 px-2 py-1.5 text-sm" placeholder="0.0000">
				</div>
				<button class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Guardar tasa</button>
			</form>
		</div>

		<div>
			<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Importar archivo Excel del BCV</h3>
			<form action="{{ route('tasas-interes.importar') }}" method="POST" enctype="multipart/form-data"
				class="bg-white border border-gray-200 p-4 space-y-3">
				@csrf
				<p class="text-xs text-gray-500">
					El archivo debe contener la hoja principal del BCV: la fila 2 con "BANCO CENTRAL DE VENEZUELA"
					y la fila 3 con "TASA DE INTERÉS APLICABLE AL CÁLCULO DE LOS INTERESES SOBRE PRESTACIONES SOCIALES".
					Los datos inician en la fila 8 (año + meses) y la tasa se toma de la columna "Promedio entre
					Activa y Pasiva".
				</p>
				<input type="file" name="archivo" accept=".xls,.xlsx" required
					class="block w-full text-sm border border-gray-300 px-2 py-1.5">
				<button class="px-3 py-1.5 text-sm bg-green-600 text-white hover:bg-green-700">⬆ Importar por lotes</button>
			</form>
		</div>
	</div>

	<form method="GET" action="{{ route('tasas-interes.index') }}" class="mb-2 flex flex-wrap items-end gap-3">
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Periodo desde</label>
			<input type="text" name="desde" value="{{ $desde }}" placeholder="YYYY-MM"
				class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Periodo hasta</label>
			<input type="text" name="hasta" value="{{ $hasta }}" placeholder="YYYY-MM"
				class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Fuente</label>
			<select name="fuente" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todas</option>
				<option value="excel" {{ $fuente === 'excel' ? 'selected' : '' }}>Excel BCV</option>
				<option value="manual" {{ $fuente === 'manual' ? 'selected' : '' }}>Manual</option>
			</select>
		</div>
		<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-100">Filtrar</button>
		@if ($desde || $hasta || $fuente !== '')
			<a href="{{ route('tasas-interes.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-100">Limpiar</a>
		@endif
	</form>

	<div class="mb-2 flex justify-end items-center">
		<div class="flex items-center gap-2 text-xs text-gray-500">
			<span>Filas por página:</span>
			<select onchange="window.location='{{ route('tasas-interes.index') }}'+'?porPagina='+this.value
				+'&desde='+encodeURIComponent('{{ $desde }}')
				+'&hasta='+encodeURIComponent('{{ $hasta }}')
				+'&fuente='+encodeURIComponent('{{ $fuente }}')"
				class="border border-gray-300 px-1.5 py-0.5 text-xs bg-white">
				@foreach ([5, 10, 15, 25, 50, 100] as $n)
					<option value="{{ $n }}" {{ $tasas->perPage() == $n ? 'selected' : '' }}>{{ $n }}</option>
				@endforeach
			</select>
		</div>
	</div>

	<div class="bg-white border border-gray-200 overflow-x-auto">
		<table class="w-full text-sm">
			<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
				<tr>
					<th class="text-left px-4 py-2">Periodo</th>
					<th class="text-right px-4 py-2">Tasa de interés (%)</th>
					<th class="text-center px-4 py-2">Fuente</th>
					<th class="text-right px-4 py-2"></th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@forelse ($tasas as $t)
					<tr class="hover:bg-gray-50">
						<td class="px-4 py-2">{{ $t->periodo }}</td>
						<td class="px-4 py-2 text-right font-medium">{{ formato_monto((float) $t->tasa_interes, 4) }}</td>
						<td class="px-4 py-2 text-center">
							<span class="inline-flex text-[10px] px-2 py-0.5
								{{ $t->fuente === 'excel' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
								{{ $t->labelFuente() }}
							</span>
						</td>
						<td class="px-4 py-2 text-right">
							<form action="{{ route('tasas-interes.destroy', $t) }}" method="POST"
								onsubmit="return confirm('¿Eliminar esta tasa?')">
								@csrf @method('DELETE')
								<button class="text-xs text-red-500 hover:bg-red-50 px-2 py-1">🗑</button>
							</form>
						</td>
					</tr>
				@empty
					<tr><td colspan="4" class="text-center py-8 text-gray-400">Sin tasas registradas.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>

	<div class="mt-4 flex justify-end items-center">
		{{ $tasas->appends(['porPagina' => $tasas->perPage()])->links() }}
	</div>
</div>
@endsection
