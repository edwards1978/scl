@extends('layouts.app')

@section('content')
@php
	$filtrosActivos = array_filter([
		'buscar' => $buscar !== '' ? $buscar : null,
		'patrono_id' => $patronoId,
	], fn ($valor) => $valor !== null && $valor !== '');
@endphp

<div class="p-4 mx-4">
	<div class="mb-4">
		<h1 class="text-xl font-semibold">Papelera</h1>
		<p class="text-sm text-gray-600">Registros eliminados con borrado lógico. Restaurarlos los devuelve a su módulo original.</p>
		@if ($retencionDias > 0)
			<p class="text-xs text-gray-500 mt-1">Purga automática: los registros se eliminan definitivamente {{ $retencionDias }} días después de ir a la papelera.</p>
		@else
			<p class="text-xs text-gray-500 mt-1">La purga automática está desactivada; solo un administrador puede purgar manualmente.</p>
		@endif
	</div>

	<form method="GET" action="{{ route('papelera.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<input type="hidden" name="tipo" value="{{ $tipoActivo }}">

		<div class="flex-1 min-w-56">
			<label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
			<input type="search" name="buscar" value="{{ $buscar }}" placeholder="Nombre, cédula, RIF, correo…"
				class="w-full border border-gray-300 px-2 py-1.5 text-sm">
		</div>

		@if ($filtraPorPatrono)
			<div class="min-w-56">
				<label class="block text-xs font-medium text-gray-600 mb-1">Patrono</label>
				<select name="patrono_id" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
					<option value="">Todos los patronos</option>
					@foreach ($patronos as $patrono)
						<option value="{{ $patrono->id }}" @selected($patronoId === $patrono->id)>{{ $patrono->razon_social }}</option>
					@endforeach
				</select>
			</div>
		@endif

		<button type="submit" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Filtrar</button>

		@if ($buscar !== '' || $patronoId !== null)
			<a href="{{ route('papelera.index', ['tipo' => $tipoActivo]) }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Limpiar</a>
		@endif
	</form>

	<div class="flex flex-wrap gap-1 mb-4">
		@foreach ($tipos as $clave => $tipo)
			<a href="{{ route('papelera.index', ['tipo' => $clave] + $filtrosActivos) }}"
				class="px-3 py-1.5 text-sm border {{ $tipoActivo === $clave ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50' }}">
				{{ $tipo['label'] }}
				<span class="ml-1 text-[10px] {{ $tipoActivo === $clave ? 'text-blue-100' : 'text-gray-400' }}">({{ $conteos[$clave] ?? 0 }})</span>
			</a>
		@endforeach
	</div>

	@if ($registros->isEmpty())
		<div class="text-center py-12 text-gray-400 bg-white border border-gray-200">
			<p class="text-sm">No hay registros eliminados en {{ strtolower($config['label']) }}{{ $filtrosActivos === [] ? '' : ' con los filtros aplicados' }}.</p>
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
						@php
							$historial = method_exists($registro, 'historialEstados') ? $registro->historialEstados : collect();
							$eliminacion = $historial->firstWhere('campo', 'papelera');
							$fechaPurga = $retencionDias > 0 && $registro->deleted_at
								? $registro->deleted_at->copy()->addDays($retencionDias)
								: null;
						@endphp
						<tr class="hover:bg-gray-50">
							<td class="px-4 py-2 font-medium">{{ $config['titulo']($registro) }}</td>
							<td class="px-4 py-2 text-xs text-gray-500">{{ $config['detalle']($registro) ?: '—' }}</td>
							<td class="px-4 py-2 text-xs text-gray-500">
								<div>{{ $registro->deleted_at?->format('d/m/Y H:i') }}</div>
								<div class="text-gray-400">{{ $eliminacion?->user?->name ?? 'Usuario no disponible' }}</div>
								@if ($fechaPurga)
									<div class="text-amber-600 mt-1">
										{{ $fechaPurga->isPast() ? 'Pendiente de purga' : 'Purga: '.$fechaPurga->format('d/m/Y') }}
									</div>
								@endif
							</td>
							<td class="px-4 py-2 text-right">
								<div class="inline-flex items-center gap-2">
									<form action="{{ route('papelera.restaurar', ['tipo' => $tipoActivo, 'id' => $registro->getKey()]) }}" method="POST"
										onsubmit="return confirm('¿Restaurar este registro?')">
										@csrf
										<button type="submit" class="px-3 py-1 text-xs bg-green-600 text-white hover:bg-green-700">Restaurar</button>
									</form>
									<form action="{{ route('papelera.purgar', ['tipo' => $tipoActivo, 'id' => $registro->getKey()]) }}" method="POST"
										onsubmit="return confirm('¿Purgar este registro definitivamente? Esta acción no se puede deshacer.')">
										@csrf
										@method('DELETE')
										<button type="submit" class="px-3 py-1 text-xs border border-red-300 text-red-600 hover:bg-red-50">Purgar</button>
									</form>
								</div>
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

	@if ($eventosPapelera->isNotEmpty())
		<div class="mt-8">
			<h2 class="text-sm font-semibold text-gray-700 mb-3">Actividad reciente de la papelera</h2>

			<div class="bg-white border border-gray-200 overflow-x-auto">
				<table class="w-full text-sm">
					<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
						<tr>
							<th class="text-left px-4 py-2">Fecha</th>
							<th class="text-left px-4 py-2">Usuario</th>
							<th class="text-left px-4 py-2">Acción</th>
							<th class="text-left px-4 py-2">Tipo</th>
							<th class="text-left px-4 py-2">Registro</th>
							<th class="text-left px-4 py-2">Detalle</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						@foreach ($eventosPapelera as $evento)
							@php
								$tipoEvento = collect($tipos)->first(fn (array $tipo): bool => $tipo['modelo'] === $evento->auditable_type);
								$colorAccion = match ($evento->valor_nuevo) {
									'eliminado' => 'bg-red-50 text-red-700',
									'purgado' => 'bg-amber-50 text-amber-700',
									default => 'bg-green-50 text-green-700',
								};
							@endphp
							<tr>
								<td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap">{{ $evento->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
								<td class="px-4 py-2">{{ $evento->user?->name ?? 'Sistema' }}</td>
								<td class="px-4 py-2">
									<span class="inline-flex text-[10px] px-2 py-0.5 {{ $colorAccion }}">
										{{ $evento->labelValor($evento->valor_nuevo) }}
									</span>
								</td>
								<td class="px-4 py-2 text-xs text-gray-500">{{ $tipoEvento['label'] ?? class_basename($evento->auditable_type) }}</td>
								<td class="px-4 py-2 text-xs text-gray-500">#{{ $evento->auditable_id }}</td>
								<td class="px-4 py-2 text-xs text-gray-500">{{ $evento->nota ?? '—' }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	@endif
</div>
@endsection
