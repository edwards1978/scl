@extends('layouts.app')

@section('content')
<div class="p-4 mx-4 max-w-5xl">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Verificación de tasas BCV para sueldos</h1>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Chequeo general de las tasas necesarias para calcular los sueldos de todos los meses
		requeridos (desde el primer ingreso hasta el mes actual). Una tasa cubre un mes si existe
		una publicación en o antes del último día del mes.
	</p>

	<div class="bg-white border border-gray-200 p-4 mb-6 flex items-center gap-6">
		<div>
			<div class="text-2xl font-bold {{ $resultado['faltantes'] ? 'text-red-600' : 'text-green-600' }}">
				{{ $resultado['faltantes'] ? count($resultado['faltantes']).' faltante(s)' : 'Todo cubierto' }}
			</div>
			<div class="text-xs text-gray-500">{{ $resultado['cubiertos'] }} de {{ $resultado['periodos'] }} meses con tasa</div>
		</div>
		<div class="flex-1">
			@if ($resultado['faltantes'])
				<div class="text-xs text-red-600">
					Los siguientes meses no disponen de tasa BCV para su cálculo. Importe el archivo del BCV
					o registre una tasa manualmente para cubrirlos.
				</div>
			@else
				<div class="text-xs text-green-600">
					Todos los meses requeridos cuentan con tasa BCV disponible para el cálculo de sueldos.
				</div>
			@endif
		</div>
		<div>
			<a href="{{ route('tasas.index') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">← Volver a tasas</a>
		</div>
	</div>

	@if ($resultado['faltantes'])
		<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Meses sin tasa disponible</h3>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-4 py-2">Periodo</th>
						<th class="text-left px-4 py-2">Fecha de cierre</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($resultado['faltantes'] as $f)
						<tr class="hover:bg-gray-50">
							<td class="px-4 py-2 font-medium">{{ \Carbon\Carbon::parse($f['periodo'].'-01')->format('m/Y') }}</td>
							<td class="px-4 py-2">{{ \Carbon\Carbon::parse($f['cierre'])->format('d/m/Y') }}</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	@endif
</div>
@endsection