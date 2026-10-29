@extends('layouts.app')

@section('content')
<div class="p-4 mx-4 max-w-5xl">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Tasas de Cambio BCV</h1>
		<a href="{{ route('tasas.verificar') }}"
			class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-100">⚙ Verificar tasas requeridas</a>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Tasa de venta del USD publicada por el Banco Central de Venezuela.
	</p>

	<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
		<div>
			<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Registrar tasa manual</h3>
			<form action="{{ route('tasas.store') }}" method="POST"
				class="bg-white border border-gray-200 p-4 space-y-3">
				@csrf
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-1">Fecha</label>
					<input type="date" name="fecha" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-1">Tasa USD (Bs por USD)</label>
					<input type="number" name="tasa_usd" step="0.000001" min="0.000001" required
						class="w-full border border-gray-300 px-2 py-1.5 text-sm" placeholder="0.000000">
				</div>
				<button class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Guardar tasa</button>
			</form>
		</div>

		<div>
			<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Importar archivo Excel del BCV</h3>
			<form action="{{ route('tasas.importar') }}" method="POST" enctype="multipart/form-data"
				class="bg-white border border-gray-200 p-4 space-y-3">
				@csrf
				<p class="text-xs text-gray-500">
					El archivo del BCV trae una hoja por día (nombre DDMMYYYY) y la tasa USD de venta en la
					columna G de la fila de la moneda.
				</p>
				<input type="file" name="archivo" accept=".xls,.xlsx" required
					class="block w-full text-sm border border-gray-300 px-2 py-1.5">
				<button class="px-3 py-1.5 text-sm bg-green-600 text-white hover:bg-green-700">⬆ Importar por lotes</button>
			</form>
		</div>
	</div>

	<form method="GET" action="{{ route('tasas.index') }}" class="mb-2 flex flex-wrap items-end gap-3">
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Fecha desde</label>
			<input type="date" name="desde" value="{{ $desde?->format('Y-m-d') }}"
				class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Fecha hasta</label>
			<input type="date" name="hasta" value="{{ $hasta?->format('Y-m-d') }}"
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
			<a href="{{ route('tasas.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-100">Limpiar</a>
		@endif
	</form>

	<div class="mb-2 flex items-center justify-between">
		<x-orden-indicador campo="Fecha" direccion="desc" />
		<div class="flex items-center gap-2 text-xs text-gray-500">
			<span>Filas por página:</span>
			<select onchange="window.location='{{ route('tasas.index') }}'+'?porPagina='+this.value
				+'&desde='+encodeURIComponent('{{ $desde?->toDateString() ?? '' }}')
				+'&hasta='+encodeURIComponent('{{ $hasta?->toDateString() ?? '' }}')
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
					<th class="text-left px-4 py-2">Fecha</th>
					<th class="text-right px-4 py-2">Tasa USD (Bs)</th>
					<th class="text-center px-4 py-2">Fuente</th>
					<th class="text-right px-4 py-2"></th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@forelse ($tasas as $t)
					<tr class="hover:bg-gray-50">
						<td class="px-4 py-2">{{ $t->fecha->format('d/m/Y') }}</td>
						<td class="px-4 py-2 text-right font-medium">{{ formato_monto((float) $t->tasa_usd, 6) }}</td>
						<td class="px-4 py-2 text-center">
							<span class="inline-flex text-[10px] px-2 py-0.5
								{{ $t->fuente === 'excel' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
								{{ $t->fuente === 'excel' ? 'Excel BCV' : 'Manual' }}
							</span>
						</td>
						<td class="px-4 py-2 text-right">
							<form action="{{ route('tasas.destroy', $t) }}" method="POST"
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