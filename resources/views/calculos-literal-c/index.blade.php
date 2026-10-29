@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-6xl">
	<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
		<div>
			<h1 class="text-xl font-semibold">Liquidaciones rápidas Art. 142.c</h1>
			<p class="text-sm text-gray-500">Cálculos independientes de patronos y trabajadores.</p>
		</div>
		@if (auth()->user()->puedeGestionar())
			<a href="{{ route('calculos-literal-c.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nuevo cálculo</a>
		@endif
	</div>

	<form method="GET" action="{{ route('calculos-literal-c.index') }}" class="bg-white border border-gray-200 p-3 mb-4 flex flex-wrap gap-2 items-end">
		<div class="flex-1 min-w-[220px]">
			<label class="block text-xs text-gray-500 mb-1">Buscar</label>
			<input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Patrono, trabajador o cédula" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<button class="px-3 py-1.5 text-sm bg-gray-800 text-white hover:bg-gray-900">Filtrar</button>
		<a href="{{ route('calculos-literal-c.index') }}" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
	</form>

	<div class="bg-white border border-gray-200 overflow-x-auto">
		<table class="min-w-full text-sm">
			<thead class="bg-gray-50 text-xs uppercase text-gray-500">
				<tr>
					<th class="px-3 py-2 text-left">Trabajador</th>
					<th class="px-3 py-2 text-left">Patrono</th>
					<th class="px-3 py-2 text-left">Egreso</th>
					<th class="px-3 py-2 text-right">Neto</th>
					<th class="px-3 py-2 text-left">Creado</th>
					<th class="px-3 py-2 text-right">Acciones</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@forelse ($calculos as $calculo)
					<tr>
						<td class="px-3 py-2">
							<p class="font-medium text-gray-800">{{ $calculo->trabajador_nombre }}</p>
							<p class="text-xs text-gray-500">{{ $calculo->trabajador_cedula ?: 'Sin cédula' }}</p>
						</td>
						<td class="px-3 py-2 text-gray-600">{{ $calculo->patrono_nombre }}</td>
						<td class="px-3 py-2 text-gray-600">{{ $calculo->fecha_egreso->format('d/m/Y') }}</td>
						<td class="px-3 py-2 text-right font-medium">{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->neto) }}</td>
						<td class="px-3 py-2 text-xs text-gray-500">
							{{ $calculo->creador?->name ?? '—' }}<br>
							{{ $calculo->created_at?->format('d/m/Y H:i') }}
						</td>
						<td class="px-3 py-2">
							<div class="flex items-center justify-end gap-1">
								<a href="{{ route('calculos-literal-c.show', $calculo) }}" class="px-2 py-1 text-xs border border-gray-300 text-gray-600 hover:bg-gray-50">Ver</a>
								<a href="{{ route('calculos-literal-c.pdf', $calculo) }}" target="_blank" rel="noopener" class="px-2 py-1 text-xs border border-gray-300 text-gray-600 hover:bg-gray-50">PDF</a>
								@if (auth()->user()->puedeGestionar())
									<a href="{{ route('calculos-literal-c.edit', $calculo) }}" class="px-2 py-1 text-xs border border-gray-300 text-gray-600 hover:bg-gray-50">Editar</a>
									<form method="POST" action="{{ route('calculos-literal-c.destroy', $calculo) }}" onsubmit="return confirm('¿Eliminar definitivamente este cálculo?')">
										@csrf
										@method('DELETE')
										<button class="px-2 py-1 text-xs border border-red-300 text-red-600 hover:bg-red-50">Eliminar</button>
									</form>
								@endif
							</div>
						</td>
					</tr>
				@empty
					<tr>
						<td colspan="6" class="px-3 py-6 text-center text-gray-500">No hay cálculos guardados.</td>
					</tr>
				@endforelse
			</tbody>
		</table>
	</div>

	<div class="mt-3">{{ $calculos->links() }}</div>
</div>
@endsection
