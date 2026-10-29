@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<h1 class="text-xl font-semibold mb-1">Prestaciones Sociales</h1>
	<p class="text-sm text-gray-600 mb-4">
		Garantía de prestaciones sociales (Art. 142 LOTTT): depósitos trimestrales de 15 días de salario
		integral, días adicionales por antigüedad e intereses (Art. 143) con la tasa del BCV.
	</p>

	{{-- Filtros --}}
	<form method="GET" action="{{ route('prestaciones.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<div class="flex-1 max-w-xs">
			<label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
			<input type="text" name="buscar" placeholder="Buscar por nombre o cédula…" value="{{ request('buscar') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Estatus</label>
			<select name="estatus" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todos</option>
				<option value="Activo" {{ request('estatus') === 'Activo' ? 'selected' : '' }}>Activo</option>
				<option value="Inactivo" {{ request('estatus') === 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
				<option value="Suspendido" {{ request('estatus') === 'Suspendido' ? 'selected' : '' }}>Suspendido</option>
			</select>
		</div>
		<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Filtrar</button>
		@if (request()->hasAny(['buscar', 'estatus']))
			<a href="{{ route('prestaciones.index') }}" class="px-2 py-1.5 text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
		@endif
	</form>

	@if ($activos > 0)
		<div class="bg-white border border-gray-200 p-3 mb-4">
			<form method="POST" action="{{ route('prestaciones.proyectar') }}" class="flex flex-wrap items-end gap-3">
				@csrf
				<div>
					<label for="hasta_proyeccion" class="block text-xs text-gray-500 mb-1">Proyectar / calcular hasta</label>
					<input id="hasta_proyeccion" type="month" name="hasta" required
						min="{{ $minProyeccion }}" max="{{ $maxProyeccion }}"
						value="{{ old('hasta', $proyeccionHasta ?? $minProyeccion) }}"
						class="border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<button class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">
					{{ $proyeccionHasta ? 'Reproyectar' : 'Proyectar' }}
				</button>
			</form>
			<p class="text-[11px] text-gray-500 mt-2">
				Se aplicará a los {{ $activos }} trabajador(es) activo(s) del patrono, sin importar los filtros del listado.
				Máximo 12 meses hacia adelante. La proyección usa la última tasa BCV disponible y el último salario registrado;
				se guarda como base operativa para anticipos e intereses con fecha futura y no se usa en liquidaciones.
				@if ($proyeccionHasta) Proyección guardada hasta <strong>{{ $proyeccionHasta }}</strong>. @endif
			</p>
			@error('hasta') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
		</div>
	@endif

	{{-- Tabla --}}
	@if ($empleados->isEmpty())
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">
				{{ request()->hasAny(['buscar', 'estatus']) ? 'No hay trabajadores que coincidan con el filtro seleccionado.' : 'No hay empleados registrados en este patrono.' }}
			</p>
		</div>
	@else
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Nombres y Apellidos" direccion="asc" />
		</div>
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Trabajador</th>
						<th class="text-center px-3 py-2">Ingreso</th>
						<th class="text-center px-3 py-2">Estatus</th>
						<th class="text-center px-3 py-2">Calculado hasta</th>
						<th class="text-right px-3 py-2" title="Saldo al cierre del último período calculado, real o proyectado">Saldo prestaciones</th>
						<th class="text-right px-3 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($empleados as $e)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2">
								<div class="font-medium">{{ $e->nombre_completo }}</div>
								<div class="text-xs text-gray-400">C.I. {{ $e->cedula }}</div>
							</td>
							<td class="px-3 py-2 text-center">{{ $e->fecha_ingreso?->format('d/m/Y') ?? '—' }}</td>
							<td class="px-3 py-2 text-center">
								<span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5
									{{ $e->estaActivo() ? 'bg-green-50 text-green-600' : ($e->estatus === 'Suspendido' ? 'bg-yellow-50 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">
									{{ $e->estatus }}
								</span>
							</td>
							<td class="px-3 py-2 text-center whitespace-nowrap">
								@if ($calculadoHasta[$e->id] ?? null)
									{{ $calculadoHasta[$e->id]->format('d/m/Y') }}
								@else
									<span class="text-gray-400">—</span>
								@endif
							</td>
							<td class="px-3 py-2 text-right font-medium">{{ $e->moneda }} {{ formato_monto($saldos[$e->id] ?? 0) }}</td>
							<td class="px-3 py-2">
								<div class="flex items-center justify-end gap-0.5">
									<a href="{{ route('prestaciones.show', $e->id) }}" class="p-1 text-gray-500 hover:text-blue-600" title="Estado de cuenta">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
									</a>
									<a href="{{ route('prestaciones.pdf', $e->id) }}" target="_blank" rel="noopener" class="p-1 text-gray-500 hover:text-red-600" title="Estado de cuenta PDF">📄</a>
								</div>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div class="mt-4 flex justify-end items-center">
			{{ $empleados->links() }}
		</div>
	@endif
</div>
@endsection
