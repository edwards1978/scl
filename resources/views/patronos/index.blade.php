@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-4">
		<h1 class="text-xl font-semibold">Patronos</h1>
		<a href="{{ route('patronos.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nuevo Patrono</a>
	</div>

	{{-- Filtros --}}
	<form method="GET" action="{{ route('patronos.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<input type="hidden" name="porPagina" value="{{ $patronos->perPage() }}">
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Campo</label>
			<select name="campo" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="razon_social" {{ $campo === 'razon_social' ? 'selected' : '' }}>Razón Social</option>
				<option value="rif" {{ $campo === 'rif' ? 'selected' : '' }}>R.I.F.</option>
			</select>
		</div>
		<div class="flex-1 max-w-xs">
			<label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
			<input type="text" name="buscar" placeholder="Buscar…" value="{{ request('buscar') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<button type="submit" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Filtrar</button>
		@if (request('buscar'))
			<a href="{{ route('patronos.index') }}" class="px-2 py-1.5 text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
		@endif
	</form>

	@if ($patronos->isEmpty())
		<div class="text-center py-8 text-gray-400"><p class="text-sm">No hay patronos registrados.</p></div>
	@else
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Razón Social" direccion="asc" />
		</div>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-4 py-2">R.I.F.</th>
						<th class="text-left px-4 py-2">Razón Social</th>
						<th class="text-left px-4 py-2">Nombre Comercial</th>
						<th class="text-left px-4 py-2">Depósito prestaciones</th>
						<th class="text-center px-4 py-2">Empleados</th>
						<th class="text-center px-4 py-2">Estado</th>
						<th class="text-right px-4 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($patronos as $p)
						<tr class="hover:bg-gray-50">
							<td class="px-4 py-2 text-xs font-mono">{{ $p->rif }}</td>
							<td class="px-4 py-2">{{ $p->razon_social }}</td>
							<td class="px-4 py-2 text-xs text-gray-500">{{ $p->nombre_comercial ?? '—' }}</td>
							<td class="px-4 py-2 text-xs">
								{{ $p->deposito_tipo === 'contabilidad' ? 'Contabilidad' : ($p->deposito_tipo === 'fideicomiso' ? 'Fideicomiso' : 'Fondo') }}
							</td>
							<td class="px-4 py-2 text-center text-xs">{{ $p->empleados_count }}</td>
							<td class="px-4 py-2 text-center">
								<span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 {{ $p->estado === 'Activo' ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500' }}">
									{{ $p->estado }}
								</span>
							</td>
							<td class="px-4 py-2">
								<div class="flex items-center justify-end gap-0.5">
									<a href="{{ route('patronos.edit', $p) }}" class="p-1 text-gray-400 hover:text-amber-500 hover:bg-amber-50" title="Editar">
										<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
									</a>
									<form action="{{ route('patronos.toggle', $p) }}" method="POST" onsubmit="return confirm('¿Cambiar estado de {{ $p->razon_social }}?')">
										@csrf @method('PATCH')
										<button type="submit" class="text-[10px] px-1.5 py-0.5 bg-gray-100 text-gray-500 hover:bg-gray-200">{{ $p->estado === 'Activo' ? 'desactivar' : 'activar' }}</button>
									</form>
									<form action="{{ route('patronos.destroy', $p) }}" method="POST" onsubmit="return confirm('¿Eliminar el patrono {{ $p->razon_social }}? Se eliminarán todos sus empleados.')">
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
				<select onchange="window.location='{{ route('patronos.index') }}?porPagina='+this.value+'&campo={{ $campo }}&buscar='+encodeURIComponent('{{ request('buscar') }}')"
					class="border border-gray-300 px-1.5 py-0.5 text-xs bg-white">
					@foreach ([5, 10, 15, 25, 50, 100] as $n)
						<option value="{{ $n }}" {{ $patronos->perPage() == $n ? 'selected' : '' }}>{{ $n }}</option>
					@endforeach
				</select>
			</div>
			{{ $patronos->appends(['porPagina' => $patronos->perPage(), 'campo' => $campo, 'buscar' => request('buscar')])->links() }}
		</div>
	@endif
</div>
@endsection
