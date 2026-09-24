@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Empleados</h1>
		<a href="{{ route('empleados.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nuevo Empleado</a>
	</div>
<!-- 	<p class="text-sm text-gray-600 mb-4">
		Patrono: <span class="font-medium">{{ $patrono->razon_social }}</span> ({{ $patrono->rif }})
		<a href="{{ route('entorno.seleccionar') }}" class="ml-1 text-xs text-blue-600 underline">Cambiar</a>
	</p>
 -->
	<form method="GET" class="mb-3 flex gap-2 flex-wrap">
		<input type="hidden" name="porPagina" value="{{ $empleados->perPage() }}">
		<input type="text" name="buscar" placeholder="Buscar por nombre o cédula…" value="{{ request('buscar') }}" class="border border-gray-300 px-2 py-1.5 text-sm flex-1 max-w-xs">
		<button type="submit" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Buscar</button>
		@if (request('buscar'))
			<a href="{{ route('empleados.index') }}" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-600 hover:bg-gray-200 border border-gray-300">Limpiar</a>
		@endif
	</form>

	@if ($empleados->isEmpty())
		<div class="text-center py-8 text-gray-400"><p class="text-sm">No hay empleados registrados para este patrono.</p></div>
	@else
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-4 py-2">Cédula</th>
						<th class="text-left px-4 py-2">Nombres</th>
						<th class="text-left px-4 py-2">Apellidos</th>
						<th class="text-left px-4 py-2">Cargo</th>
						<th class="text-left px-4 py-2">Contrato</th>
						<th class="text-right px-4 py-2">Salario</th>
						<th class="text-center px-4 py-2">Estatus</th>
						<th class="text-right px-4 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($empleados as $e)
						<tr class="hover:bg-gray-50">
							<td class="px-4 py-2 text-xs font-mono">{{ $e->cedula }}</td>
							<td class="px-4 py-2">{{ $e->nombres }}</td>
							<td class="px-4 py-2">{{ $e->apellidos }}</td>
							<td class="px-4 py-2 text-xs">{{ $e->cargo ?? '—' }}</td>
							<td class="px-4 py-2 text-xs">{{ $e->tipo_contrato }}</td>
							<td class="px-4 py-2 text-xs text-right">
								@if ($e->moneda === 'USD')
									<div class="font-medium">USD {{ formato_monto((float) $e->salario_base) }}</div>
									@if ($tasaDolar > 0)
										<div class="text-gray-400">≈ Bs. {{ formato_monto((float) $e->salario_base * $tasaDolar) }}</div>
									@endif
								@else
									<div>Bs. {{ formato_monto((float) $e->salario_base) }}</div>
								@endif
							</td>
							<td class="px-4 py-2 text-center">
								<span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5
									{{ $e->estatus === 'Activo' ? 'bg-green-50 text-green-600' : ($e->estatus === 'Suspendido' ? 'bg-yellow-50 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">
									{{ $e->estatus }}
								</span>
							</td>
								<td class="px-4 py-2">
									<div class="flex items-center justify-end gap-0.5">
										<a href="{{ route('empleados.edit', $e) }}" class="p-1 text-gray-400 hover:text-amber-500 hover:bg-amber-50" title="Editar">
											<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
										</a>
										<form action="{{ route('empleados.destroy', $e) }}" method="POST" onsubmit="return confirm('¿Eliminar a {{ $e->nombre_completo }}?')">
											@csrf @method('DELETE')
											<button type="submit" class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50" title="Eliminar">
												<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
											</button>
										</form>
									</div>
								</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div class="mt-4 flex items-center justify-between">
			<div class="flex items-center gap-2 text-xs text-gray-500">
				<span>Filas por página:</span>
				<select onchange="window.location='{{ route('empleados.index') }}?porPagina='+this.value+'&buscar='+encodeURIComponent('{{ request('buscar') }}')"
					class="border border-gray-300 px-1.5 py-0.5 text-xs bg-white">
					@foreach ([5, 10, 15, 25, 50, 100] as $n)
						<option value="{{ $n }}" {{ $empleados->perPage() == $n ? 'selected' : '' }}>{{ $n }}</option>
					@endforeach
				</select>
			</div>
			{{ $empleados->appends(['porPagina' => $empleados->perPage(), 'buscar' => request('buscar')])->links() }}
		</div>
	@endif
</div>
@endsection
