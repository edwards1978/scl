@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-2xl">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Nuevo Pago de Intereses</h1>
		<a href="{{ route('intereses-prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Intereses del Art. 143 LOTTT. El pago se aplica al interés pendiente del trabajador; el exceso reduce capital.
	</p>

	@if ($empleados->isEmpty())
		<div class="bg-white border border-gray-200 p-5 text-sm text-gray-500">
			No hay trabajadores activos para registrar pagos de intereses.
		</div>
		<div class="flex justify-end mt-4">
			<a href="{{ route('intereses-prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Volver al listado</a>
		</div>
	@else
		<form method="POST" action="{{ route('intereses-prestaciones.store') }}" class="space-y-4">
			@csrf

			<fieldset class="bg-white border border-gray-200 p-5">
				<legend class="text-sm font-semibold text-gray-700 px-1">Pago</legend>
				<div class="space-y-3 mt-2">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Trabajador</label>
						<select name="empleado_id" id="empleado_id" required class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="">Seleccione un trabajador</option>
							@foreach ($empleados as $emp)
								<option value="{{ $emp->id }}" {{ (int) old('empleado_id', $empleado?->id) === (int) $emp->id ? 'selected' : '' }}>
									{{ $emp->nombre_completo }} — {{ $emp->cedula }}
								</option>
							@endforeach
						</select>
						@error('empleado_id') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>

					<div class="grid grid-cols-3 gap-3">
						<div>
							<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha del movimiento</label>
							<input type="date" name="fecha" id="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" max="{{ now()->addMonths(12)->endOfMonth()->format('Y-m-d') }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
							@error('fecha') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
						</div>
						<div>
							<label class="block text-xs font-medium text-gray-600 mb-0.5">Monto</label>
							<input type="text" name="monto" inputmode="decimal" value="{{ old('monto') }}" placeholder="0,00" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
							@error('monto') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
						</div>
						<div>
							<label class="block text-xs font-medium text-gray-600 mb-0.5">Moneda</label>
							<select name="moneda" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
								<option value="USD" {{ old('moneda', 'USD') === 'USD' ? 'selected' : '' }}>USD</option>
								<option value="BS" {{ old('moneda') === 'BS' ? 'selected' : '' }}>Bs</option>
							</select>
						</div>
					</div>
					<p class="text-[11px] text-gray-500">
						La fecha puede ser futura (hasta 12 meses); si el período aún no ha concluido, se usa la proyección guardada.
					</p>

					<div id="info-intereses" class="hidden bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-900"></div>

					<div class="grid grid-cols-2 gap-3">
						<div>
							<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha del comprobante</label>
							<input type="date" name="fecha_comprobante" value="{{ old('fecha_comprobante', now()->format('Y-m-d')) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
							@error('fecha_comprobante') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
						</div>
						<div>
							<label class="block text-xs font-medium text-gray-600 mb-0.5">Comprobante <span class="text-gray-400">(opcional)</span></label>
							<input type="text" name="comprobante" value="{{ old('comprobante') }}" maxlength="100" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
							@error('comprobante') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
						</div>
					</div>

					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Observación <span class="text-gray-400">(opcional)</span></label>
						<textarea name="observacion" rows="2" maxlength="500" class="w-full border border-gray-300 px-2 py-1.5 text-sm">{{ old('observacion') }}</textarea>
						@error('observacion') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
				</div>
			</fieldset>

			<div class="flex justify-end gap-2">
				<a href="{{ route('intereses-prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
				<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Registrar pago</button>
			</div>
		</form>
	@endif
</div>
@endsection

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function () {
		const empleado = document.getElementById('empleado_id');
		const fecha = document.getElementById('fecha');
		const info = document.getElementById('info-intereses');
		const saldoUrl = "{{ route('intereses-prestaciones.saldo', ['empleadoId' => '__EMPLEADO__']) }}";

		const numero = (valor, decimales) => valor === null || valor === undefined
			? '—'
			: Number(valor).toLocaleString('es-VE', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });

		async function actualizarInfoIntereses() {
			info.classList.add('hidden');
			info.textContent = '';
			if (!empleado.value) return;

			try {
				const params = new URLSearchParams();
				if (fecha.value) params.set('fecha', fecha.value);
				const url = saldoUrl.replace('__EMPLEADO__', empleado.value) + (params.toString() ? '?' + params.toString() : '');
				const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
				if (!res.ok) return;
				const data = await res.json();

				if (!data.periodo) {
					info.textContent = `Sin períodos calculados al ${data.fecha}.`;
				} else {
					const tasaTexto = data.tasa === null
						? 'sin tasa BCV a la fecha'
						: `tasa BCV ${numero(data.tasa, 4)}`;
					const etiqueta = data.es_proyeccion ? ' · proyección' : '';
					info.textContent = `Interés pendiente al ${data.fecha} (cierre ${data.periodo})${etiqueta}: ${numero(data.usd, 2)} USD · ${numero(data.bs, 2)} Bs (${tasaTexto}).`;
				}
				info.classList.remove('hidden');
			} catch (e) { /* ignore */ }
		}

		empleado.addEventListener('change', actualizarInfoIntereses);
		fecha.addEventListener('change', actualizarInfoIntereses);
		if (empleado.value) actualizarInfoIntereses();
	});
</script>
@endpush
