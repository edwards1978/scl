@php
	$historial = $registro->historialEstados;
	$creadoPor = $registro->createdBy;
	$modificadoPor = $registro->updatedBy;
	$aprobadoPor = $registro->approvedBy;
	$anuladoPor = $registro->annulledBy;
@endphp

<div class="bg-white border border-gray-200 p-5 mt-6">
	<h2 class="text-sm font-semibold text-gray-700 mb-3">Auditoría</h2>

	<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
		<div class="border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Creado por</p>
			<p class="font-medium">{{ $creadoPor?->name ?? '—' }}</p>
			<p class="text-xs text-gray-400">{{ $registro->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
		</div>
		<div class="border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Última modificación</p>
			<p class="font-medium">{{ $modificadoPor?->name ?? '—' }}</p>
			<p class="text-xs text-gray-400">{{ $registro->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
		</div>
		<div class="border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Aprobado por</p>
			<p class="font-medium">{{ $aprobadoPor?->name ?? '—' }}</p>
			<p class="text-xs text-gray-400">{{ $registro->approved_at?->format('d/m/Y H:i') ?? '—' }}</p>
		</div>
		<div class="border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Anulado por</p>
			<p class="font-medium">{{ $anuladoPor?->name ?? '—' }}</p>
			<p class="text-xs text-gray-400">{{ $registro->annulled_at?->format('d/m/Y H:i') ?? '—' }}</p>
		</div>
	</div>

	<h3 class="text-xs font-semibold text-gray-500 uppercase mt-5 mb-2">Historial de estados</h3>

	@if ($historial->isEmpty())
		<p class="text-xs text-gray-400">Sin cambios de estado registrados.</p>
	@else
		<div class="overflow-x-auto">
			<table class="w-full text-xs">
				<thead class="bg-gray-50 text-gray-600 uppercase">
					<tr>
						<th class="text-left px-2 py-1.5">Fecha</th>
						<th class="text-left px-2 py-1.5">Usuario</th>
						<th class="text-left px-2 py-1.5">Campo</th>
						<th class="text-left px-2 py-1.5">Anterior</th>
						<th class="text-left px-2 py-1.5">Nuevo</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($historial as $cambio)
						<tr>
							<td class="px-2 py-1.5 whitespace-nowrap">{{ $cambio->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
							<td class="px-2 py-1.5">{{ $cambio->user?->name ?? 'Sistema' }}</td>
							<td class="px-2 py-1.5">{{ $cambio->labelCampo() }}</td>
							<td class="px-2 py-1.5 text-gray-500">{{ $cambio->labelValor($cambio->valor_anterior) }}</td>
							<td class="px-2 py-1.5 font-medium">{{ $cambio->labelValor($cambio->valor_nuevo) }}</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	@endif
</div>
