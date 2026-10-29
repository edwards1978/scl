@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-4">
		<h1 class="text-xl font-semibold">Usuarios</h1>
		<a href="{{ route('usuarios.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nuevo Usuario</a>
	</div>

	@if ($usuarios->isEmpty())
		<div class="text-center py-8 text-gray-400"><p class="text-sm">No hay usuarios registrados.</p></div>
	@else
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-4 py-2">Nombre</th>
						<th class="text-left px-4 py-2">Correo</th>
						<th class="text-left px-4 py-2">Rol</th>
						<th class="text-left px-4 py-2">Patronos asignados</th>
						<th class="text-right px-4 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($usuarios as $usuario)
						<tr class="hover:bg-gray-50">
							<td class="px-4 py-2">
								{{ $usuario->name }}
								@if ($usuario->is(auth()->user()))
									<span class="text-[10px] text-gray-400">(usted)</span>
								@endif
							</td>
							<td class="px-4 py-2 text-xs text-gray-500">{{ $usuario->email }}</td>
							<td class="px-4 py-2">
								<span class="inline-flex items-center px-2 py-0.5 text-[10px] {{ $usuario->esAdministrador() ? 'bg-blue-50 text-blue-600' : ($usuario->puedeGestionar() ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500') }}">
									{{ $usuario->rol?->label() ?? 'Sin rol' }}
								</span>
							</td>
							<td class="px-4 py-2 text-xs text-gray-500">
								@if ($usuario->esAdministrador())
									Todos los patronos
								@elseif ($usuario->patronos->isEmpty())
									<span class="text-amber-600">Sin asignar</span>
								@else
									{{ $usuario->patronos->pluck('razon_social')->join(', ') }}
								@endif
							</td>
							<td class="px-4 py-2">
								<div class="flex items-center justify-end gap-0.5">
									<a href="{{ route('usuarios.edit', $usuario) }}" class="p-1 text-gray-400 hover:text-amber-500 hover:bg-amber-50" title="Editar">
										<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
									</a>
									@unless ($usuario->is(auth()->user()))
										<form action="{{ route('usuarios.destroy', $usuario) }}" method="POST" onsubmit="return confirm('¿Eliminar el usuario {{ $usuario->name }}?')">
											@csrf @method('DELETE')
											<button type="submit" class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50" title="Eliminar">
												<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
											</button>
										</form>
									@endunless
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
				<select onchange="window.location='{{ route('usuarios.index') }}?porPagina='+this.value"
					class="border border-gray-300 px-1.5 py-0.5 text-xs bg-white">
					@foreach ([5, 10, 15, 25, 50, 100] as $n)
						<option value="{{ $n }}" {{ $usuarios->perPage() == $n ? 'selected' : '' }}>{{ $n }}</option>
					@endforeach
				</select>
			</div>
			{{ $usuarios->appends(['porPagina' => $usuarios->perPage()])->links() }}
		</div>
	@endif
</div>
@endsection
