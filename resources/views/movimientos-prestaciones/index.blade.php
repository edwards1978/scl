@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Anticipos de Prestaciones y Préstamos</h1>
		<a href="{{ route('movimientos-prestaciones.create') }}" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Nuevo movimiento</a>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Anticipos de prestaciones sociales (Art. 144 LOTTT) y préstamos contra el saldo de prestaciones.
		El tope permitido depende del sistema de depósito configurado en el patrono.
	</p>

	{{-- Filtros --}}
	<form method="GET" action="{{ route('movimientos-prestaciones.index') }}" class="mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3">
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Empleado</label>
			<select name="empleado_id" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todos</option>
				@foreach ($empleados as $emp)
					<option value="{{ $emp->id }}" {{ request('empleado_id') == $emp->id ? 'selected' : '' }}>{{ $emp->apellidos }}, {{ $emp->nombres }}</option>
				@endforeach
			</select>
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
			<select name="tipo" onchange="this.form.submit()" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todos</option>
				<option value="anticipo" {{ request('tipo') === 'anticipo' ? 'selected' : '' }}>Anticipo</option>
				<option value="prestamo" {{ request('tipo') === 'prestamo' ? 'selected' : '' }}>Préstamo</option>
			</select>
		</div>
		<div>
			<label class="block text-xs font-medium text-gray-600 mb-1">Estatus</label>
			<select name="estatus" onchange="this.form.submit()" class="border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="">Todos</option>
				<option value="activo" {{ request('estatus') === 'activo' ? 'selected' : '' }}>Activo</option>
				<option value="pagado" {{ request('estatus') === 'pagado' ? 'selected' : '' }}>Pagado</option>
				<option value="anulado" {{ request('estatus') === 'anulado' ? 'selected' : '' }}>Anulado</option>
			</select>
		</div>
		<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Filtrar</button>
		<div class="flex-1"></div>
		<div class="text-xs text-gray-500">
			Sistema de depósito: <span class="font-medium">{{ ucfirst($patrono->deposito_tipo) }}</span>
		</div>
	</form>

	{{-- Tabla --}}
	@if ($movimientos->isEmpty())
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">No hay movimientos registrados.</p>
		</div>
	@else
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Trabajador</th>
						<th class="text-center px-3 py-2">Tipo</th>
						<th class="text-center px-3 py-2">Fecha</th>
						<th class="text-center px-3 py-2">Motivo</th>
						<th class="text-right px-3 py-2">Monto</th>
						<th class="text-right px-3 py-2">Saldo</th>
						<th class="text-center px-3 py-2">Estado</th>
						<th class="text-right px-3 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($movimientos as $m)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2">
								<div class="font-medium">{{ $m->empleado->nombre_completo }}</div>
								<div class="text-xs text-gray-400">C.I. {{ $m->empleado->cedula }}</div>
							</td>
							<td class="px-3 py-2 text-center">
								<span class="inline-flex text-[10px] px-2 py-0.5 {{ $m->esAnticipo() ? 'bg-purple-50 text-purple-700' : 'bg-indigo-50 text-indigo-700' }}">{{ $m->labelTipo() }}</span>
							</td>
							<td class="px-3 py-2 text-center">{{ $m->fecha->format('d/m/Y') }}</td>
							<td class="px-3 py-2 text-center">{{ $m->labelMotivo() ?? '—' }}</td>
							<td class="px-3 py-2 text-right font-medium">
								{{ $m->moneda }} {{ formato_monto((float) $m->monto) }}
							</td>
							<td class="px-3 py-2 text-right">
								@if (!$m->esAnticipo())
									{{ $m->moneda }} {{ formato_monto((float) $m->saldo) }}
								@else
									—
								@endif
							</td>
							<td class="px-3 py-2 text-center">
								<span class="inline-flex text-[10px] px-2 py-0.5 {{ $m->colorEstatus() }}">{{ ucfirst($m->estatus) }}</span>
							</td>
							<td class="px-3 py-2">
								<div class="flex items-center justify-end gap-0.5">
									<a href="{{ route('movimientos-prestaciones.show', $m) }}" class="p-1 text-gray-500 hover:text-blue-600" title="Ver detalle">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
									</a>
									<form action="{{ route('movimientos-prestaciones.destroy', $m) }}" method="POST"
										onsubmit="return confirm('¿Eliminar este movimiento?')">
										@csrf @method('DELETE')
										<button class="p-1 text-gray-400 hover:text-red-500" title="Eliminar">🗑</button>
									</form>
								</div>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div class="mt-4 flex justify-end items-center">
			{{ $movimientos->links() }}
		</div>
	@endif
</div>
@endsection
