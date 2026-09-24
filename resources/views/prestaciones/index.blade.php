@extends('layouts.app')

@section('content')
<div class="p-4 mx-4">
	<h1 class="text-xl font-semibold mb-1">Prestaciones Sociales</h1>
	<p class="text-sm text-gray-600 mb-4">
		Garantía de prestaciones sociales (Art. 142 LOTTT): depósitos trimestrales de 15 días de salario
		integral, días adicionales por antigüedad e intereses (Art. 143) con la tasa del BCV.
	</p>

	{{-- Tabla --}}
	@if ($empleados->isEmpty())
		<div class="text-center py-12 text-gray-400">
			<p class="text-sm">No hay empleados registrados en este patrono.</p>
		</div>
	@else
		<div class="bg-white border border-gray-200 overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Trabajador</th>
						<th class="text-center px-3 py-2">C.I.</th>
						<th class="text-center px-3 py-2">Ingreso</th>
						<th class="text-right px-3 py-2">Saldo prestaciones</th>
						<th class="text-right px-3 py-2">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($empleados as $e)
						<tr class="hover:bg-gray-50">
							<td class="px-3 py-2 font-medium">{{ $e->nombre_completo }}</td>
							<td class="px-3 py-2 text-center">{{ $e->cedula }}</td>
							<td class="px-3 py-2 text-center">{{ $e->fecha_ingreso?->format('d/m/Y') ?? '—' }}</td>
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
