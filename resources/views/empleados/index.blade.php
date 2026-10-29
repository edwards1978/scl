@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Empleados</h1>
		@can('gestionar')
			<a href="{{ route('empleados.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nuevo Empleado</a>
		@endcan
	</div>
<!-- 	<p class="text-sm text-gray-600 mb-4">
		Patrono: <span class="font-medium">{{ $patrono->razon_social }}</span> ({{ $patrono->rif }})
		<a href="{{ route('entorno.seleccionar') }}" class="ml-1 text-xs text-blue-600 underline">Cambiar</a>
	</p>
 -->
	{{-- Importar / exportar (.ods) --}}
	<div class="mb-4 bg-white border border-gray-200 p-3" x-data="{ mostrarImportar: {{ $errors->has('archivo') ? 'true' : 'false' }} }">
		<div class="flex flex-wrap items-center gap-2">
			<span class="text-xs font-semibold text-gray-500 uppercase mr-1">Importar / Exportar (.ods)</span>
			<a href="{{ route('empleados.exportar', request('buscar') ? ['buscar' => request('buscar')] : []) }}"
				class="px-3 py-1.5 text-sm border border-gray-300 text-gray-700 hover:bg-gray-50">⬆ Exportar</a>
			<a href="{{ route('empleados.plantilla') }}"
				class="px-3 py-1.5 text-sm border border-gray-300 text-gray-700 hover:bg-gray-50">📄 Plantilla</a>
			<button type="button" x-on:click="mostrarImportar = ! mostrarImportar"
				class="px-3 py-1.5 text-sm border border-green-300 text-green-700 hover:bg-green-50">⬇ Importar</button>
		</div>

		<form x-show="mostrarImportar" style="display: none" action="{{ route('empleados.importar') }}" method="POST"
			enctype="multipart/form-data" class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-end gap-3">
			@csrf
			<div class="flex-1 min-w-[240px]">
				<label class="block text-xs font-medium text-gray-600 mb-1">Archivo .ods</label>
				<input type="file" name="archivo" accept=".ods,application/vnd.oasis.opendocument.spreadsheet" required
					class="block w-full text-sm border border-gray-300 px-2 py-1.5">
				@error('archivo') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<button type="submit" class="px-3 py-1.5 text-sm bg-green-600 text-white hover:bg-green-700">Importar empleados</button>
			<p class="w-full text-[11px] text-gray-500">
				Los empleados nuevos se crean y los ya registrados se actualizan por cédula, lo que permite actualizaciones masivas:
				exporte, modifique el archivo y vuelva a importarlo. Use la plantilla como referencia de columnas y formatos.
			</p>
		</form>
	</div>

	@php $detalleImportacion = session('importacion_detalle'); @endphp
	@if ($detalleImportacion)
		<div class="mb-4 bg-blue-50 border border-blue-200 p-3 text-xs text-blue-800 space-y-2">
			@if (! empty($detalleImportacion['actualizados']))
				<div>
					<p class="font-semibold">Empleados actualizados:</p>
					<p>
						{{ implode(', ', $detalleImportacion['actualizados']) }}@if (($detalleImportacion['actualizados_total'] ?? count($detalleImportacion['actualizados'])) > count($detalleImportacion['actualizados'])) … ({{ $detalleImportacion['actualizados_total'] }} en total)@endif
					</p>
				</div>
			@endif
			@if (! empty($detalleImportacion['omitidos']))
				<div>
					<p class="font-semibold">Cédulas omitidas (repetidas en el archivo):</p>
					<p>
						{{ implode(', ', $detalleImportacion['omitidos']) }}@if (($detalleImportacion['omitidos_total'] ?? count($detalleImportacion['omitidos'])) > count($detalleImportacion['omitidos'])) … ({{ $detalleImportacion['omitidos_total'] }} en total)@endif
					</p>
				</div>
			@endif
			@if (! empty($detalleImportacion['errores']))
				<div>
					<p class="font-semibold">Filas con errores:</p>
					<ul class="list-disc list-inside space-y-0.5">
						@foreach ($detalleImportacion['errores'] as $error)
							<li>{{ $error }}</li>
						@endforeach
					</ul>
					@if (($detalleImportacion['errores_total'] ?? count($detalleImportacion['errores'])) > count($detalleImportacion['errores']))
						<p class="mt-1 text-blue-700">
							Mostrando los primeros {{ count($detalleImportacion['errores']) }} de {{ $detalleImportacion['errores_total'] }} errores.
						</p>
					@endif
				</div>
			@endif
		</div>
	@endif

	{{-- Filtros --}}
	<form method="GET" action="{{ route('empleados.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<input type="hidden" name="porPagina" value="{{ $empleados->perPage() }}">
		<div class="flex-1 max-w-xs">
			<label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
			<input type="text" name="buscar" placeholder="Buscar por nombre o cédula…" value="{{ request('buscar') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<button type="submit" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Filtrar</button>
		@if (request('buscar'))
			<a href="{{ route('empleados.index') }}" class="px-2 py-1.5 text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
		@endif
	</form>

	@if ($empleados->isEmpty())
		<div class="text-center py-8 text-gray-400"><p class="text-sm">No hay empleados registrados para este patrono.</p></div>
	@else
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Nombres y Apellidos" direccion="asc" />
		</div>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-4 py-2">Trabajador</th>
						<th class="text-left px-4 py-2">Cargo</th>
						<th class="text-left px-4 py-2">Contrato</th>
						<th class="text-right px-4 py-2">Salario mensual</th>
						<th class="text-center px-4 py-2">Estatus</th>
						<th class="text-right px-4 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($empleados as $e)
						<tr class="hover:bg-gray-50">
							<td class="px-4 py-2">
								<div class="font-medium">{{ $e->nombre_completo }}</div>
								<div class="text-xs text-gray-400">C.I. {{ $e->cedula }}</div>
							</td>
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
