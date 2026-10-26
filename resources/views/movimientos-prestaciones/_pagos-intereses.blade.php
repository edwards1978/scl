<div class="bg-white border border-gray-200 p-5">
	<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
		<div>
			<h2 class="text-sm font-semibold text-gray-700">Pagos de intereses sobre prestaciones</h2>
			<p class="text-[11px] text-gray-500">Intereses del Art. 143 LOTTT del trabajador {{ $empleado->nombre_completo }}. Se aplican al interés pendiente y recalculan sus prestaciones.</p>
		</div>
		<span class="text-[10px] px-2 py-0.5 bg-blue-50 text-blue-700">Art. 143</span>
	</div>

	<form method="POST" action="{{ route('movimientos-prestaciones.intereses.prestaciones', $empleado->id) }}" class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
		@csrf
		<div>
			<label class="block text-xs text-gray-500 mb-1">Fecha</label>
			<input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm" required>
			@error('fecha') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
		</div>
		<div>
			<label class="block text-xs text-gray-500 mb-1">Monto</label>
			<input type="text" name="monto" value="{{ old('monto') }}" placeholder="0,00" class="w-full border border-gray-300 px-2 py-1.5 text-sm" required>
			@error('monto') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
		</div>
		<div>
			<label class="block text-xs text-gray-500 mb-1">Moneda</label>
			<select name="moneda" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
				<option value="BS" {{ old('moneda', $empleado->moneda) === 'BS' ? 'selected' : '' }}>Bs.</option>
				<option value="USD" {{ old('moneda', $empleado->moneda) === 'USD' ? 'selected' : '' }}>USD</option>
			</select>
		</div>
		<div>
			<label class="block text-xs text-gray-500 mb-1">Comprobante</label>
			<input type="text" name="comprobante" value="{{ old('comprobante') }}" maxlength="100" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<div class="md:col-span-2">
			<label class="block text-xs text-gray-500 mb-1">Observación</label>
			<input type="text" name="observacion" value="{{ old('observacion') }}" maxlength="500" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
		</div>
		<div class="md:col-span-6">
			<button class="px-4 py-1.5 text-sm bg-gray-800 text-white hover:bg-gray-900">Registrar pago de intereses</button>
			<span class="text-xs text-gray-500 ml-2">El pago se aplica al interés pendiente; el exceso reduce capital.</span>
		</div>
	</form>

	@if ($pagos->isNotEmpty())
		<div class="overflow-x-auto mt-4">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
					<tr>
						<th class="text-left px-3 py-2">Fecha</th>
						<th class="text-right px-3 py-2">Monto</th>
						<th class="text-left px-3 py-2">Moneda</th>
						<th class="text-right px-3 py-2">Tasa</th>
						<th class="text-left px-3 py-2">Comprobante</th>
						<th class="text-left px-3 py-2">Observación</th>
						<th class="text-right px-3 py-2"></th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($pagos as $p)
						<tr>
							<td class="px-3 py-2">{{ $p->fecha->format('d/m/Y') }}</td>
							<td class="px-3 py-2 text-right">{{ formato_monto((float) $p->monto) }}</td>
							<td class="px-3 py-2">{{ $p->moneda }}</td>
							<td class="px-3 py-2 text-right">{{ $p->tasa_cambio ? formato_monto((float) $p->tasa_cambio, 4) : '—' }}</td>
							<td class="px-3 py-2">{{ $p->comprobante ?: '—' }}</td>
							<td class="px-3 py-2">{{ $p->observacion ?: '—' }}</td>
							<td class="px-3 py-2 text-right">
								<form method="POST" action="{{ route('movimientos-prestaciones.intereses.prestaciones.destroy', [$empleado->id, $p->id]) }}" onsubmit="return confirm('¿Eliminar este pago de intereses?')">
									@csrf
									@method('DELETE')
									<button class="text-xs text-red-600 hover:underline">Eliminar</button>
								</form>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	@endif
</div>
