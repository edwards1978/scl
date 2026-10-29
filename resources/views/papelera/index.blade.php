@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="mb-4">
		<h1 class="text-xl font-semibold">Papelera</h1>
		<p class="text-sm text-gray-600">Registros eliminados con borrado lógico. Restaurarlos los devuelve a su módulo original.</p>
	</div>

	<div class="flex flex-wrap gap-1 mb-4">
		@foreach ($tipos as $clave => $tipo)
			<a href="{{ route('papelera.index', ['tipo' => $clave]) }}"
				class="px-3 py-1.5 text-sm border {{ $tipoActivo === $clave ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50' }}">
				{{ $tipo['label'] }}
				<span class="ml-1 text-[10px] {{ $tipoActivo === $clave ? 'text-blue-100' : 'text-gray-400' }}">({{ $conteos[$clave] ?? 0 }})</span>
			</a>
		@endforeach
	</div>

	@if ($registros->isEmpty())
		<div class="text-center py-12 text-gray-400 bg-white border border-gray-200">
			<p class="text-sm">No hay registros eliminados en {{ strtolower($config['label']) }}.</p>
		</div>
	@else
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-4 py-2">Registro</th>
						<th class="text-left px-4 py-2">Detalle</th>
						<th class="text-left px-4 py-2">Eliminado</th>
						<th class="text-right px-4 py-2">Acción</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($registros as $registro)
						<tr class="hover:bg-gray-50">
							<td class="px-4 py-2 font-medium">{{ $config['titulo']($registro) }}</td>
							<td class="px-4 py-2 text-xs text-gray-500">{{ $config['detalle']($registro) ?: '—' }}</td>
							<td class="px-4 py-2 text-xs text-gray-500">{{ $registro->deleted_at?->format('d/m/Y H:i') }}</td>
							<td class="px-4 py-2 text-right">
								<form action="{{ route('papelera.restaurar', ['tipo' => $tipoActivo, 'id' => $registro->getKey()]) }}" method="POST"
									onsubmit="return confirm('¿Restaurar este registro?')">
									@csrf
									<button type="submit" class="px-3 py-1 text-xs bg-green-600 text-white hover:bg-green-700">Restaurar</button>
								</form>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div class="mt-4 flex justify-end">
			{{ $registros->links() }}
		</div>
	@endif
</div>
@endsection
