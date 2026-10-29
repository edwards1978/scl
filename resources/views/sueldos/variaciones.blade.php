@extends('layouts.app')

@section('content')
<div class="p-4 mx-4 max-w-5xl">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Variaciones Salariales</h1>
		@if ($empleado)
			<a href="{{ route('sueldos.index', ['empleado_id' => $empleado->id]) }}"
				class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Sueldos</a>
		@endif
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Patrono: <span class="font-medium">{{ $patrono->razon_social }}</span> ({{ $patrono->rif }})
	</p>

	<form method="GET" class="mb-4">
		<label class="block text-xs font-medium text-gray-600 mb-1">Trabajador</label>
		<div class="flex gap-2 max-w-xs">
			<select name="empleado_id" onchange="this.form.submit()"
				class="w-full border border-gray-300 bg-white px-2 py-1.5 text-sm">
				<option value="">Seleccionar trabajador…</option>
				@foreach ($empleados as $e)
					<option value="{{ $e->id }}" {{ $empleado?->id === $e->id ? 'selected' : '' }}>
						{{ $e->nombre_completo }} ({{ $e->cedula }})
					</option>
				@endforeach
			</select>
		</div>
	</form>

	@if (! $empleado)
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">Seleccione un trabajador para gestionar sus variaciones salariales.</p>
		</div>
	@else
		<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
			<div>
				<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Agregar cambio salarial</h3>
				<form action="{{ route('sueldos.variaciones.store') }}" method="POST"
					class="bg-white border border-gray-200 p-4 space-y-3">
					@csrf
					<input type="hidden" name="empleado_id" value="{{ $empleado->id }}">

					<div>
						<label class="block text-xs font-medium text-gray-600 mb-1">Fecha del cambio</label>
						<input type="date" name="fecha_cambio" required
							class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-1">Monto</label>
						<input type="number" name="monto" step="0.01" min="0.01" required
							class="w-full border border-gray-300 px-2 py-1.5 text-sm" placeholder="0.00">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-1">Moneda</label>
						<select name="moneda" class="w-full border border-gray-300 bg-white px-2 py-1.5 text-sm">
							<option value="USD">USD</option>
							<option value="BS">Bs</option>
						</select>
					</div>
					<button class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Registrar cambio</button>
				</form>
			</div>

			<div>
				<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Cambios registrados ({{ $cambios->count() }})</h3>
				@if ($cambios->isEmpty())
					<div class="text-center py-8 text-gray-400 border border-dashed border-gray-200">
						<p class="text-sm">Sin cambios salariales. Se usa el sueldo de la ficha.</p>
					</div>
				@else
					<div class="bg-white border border-gray-200">
						<table class="w-full text-sm">
							<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
								<tr>
									<th class="text-left px-4 py-2">Fecha</th>
									<th class="text-right px-4 py-2">Monto</th>
									<th class="text-right px-4 py-2"></th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100">
								@foreach ($cambios as $c)
									<tr class="hover:bg-gray-50">
										<td class="px-4 py-2">{{ $c->fecha_cambio->format('d/m/Y') }}</td>
										<td class="px-4 py-2 text-right font-medium">{{ $c->moneda }} {{ formato_monto((float) $c->monto) }}</td>
										<td class="px-4 py-2 text-right">
											@can('eliminar')
												<form action="{{ route('sueldos.variaciones.destroy', $c) }}" method="POST"
													onsubmit="return confirm('¿Eliminar este cambio salarial?')">
													@csrf @method('DELETE')
													<button class="text-xs text-red-500 hover:bg-red-50 px-2 py-1">🗑</button>
												</form>
											@endcan
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				@endif
			</div>
		</div>
	@endif
</div>
@endsection