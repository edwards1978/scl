@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-2xl">
	<h1 class="text-xl font-semibold mb-4">Nuevo Anticipo / Préstamo</h1>

	<form method="POST" action="{{ route('movimientos-prestaciones.store') }}" class="space-y-4">
		@csrf

		<fieldset class="bg-white border border-gray-200 p-5">
			<legend class="text-sm font-semibold text-gray-700 px-1">Movimiento</legend>
			<div class="space-y-3 mt-2">
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Empleado</label>
					<select name="empleado_id" id="empleado_id" required class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
						<option value="">Seleccione un empleado</option>
						@foreach ($patrono->empleados()->where('estatus', 'Activo')->orderBy('apellidos')->orderBy('nombres')->get() as $emp)
							<option value="{{ $emp->id }}" {{ old('empleado_id') == $emp->id ? 'selected' : '' }}>
								{{ $emp->apellidos }}, {{ $emp->nombres }} — {{ $emp->cedula }}
							</option>
						@endforeach
					</select>
					@error('empleado_id') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					<p id="info-saldo" class="text-xs text-gray-500 mt-1"></p>
				</div>

				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Tipo</label>
						<select name="tipo" id="tipo" required class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="anticipo" {{ old('tipo') === 'prestamo' ? '' : 'selected' }}>Anticipo</option>
							<option value="prestamo" {{ old('tipo') === 'prestamo' ? 'selected' : '' }}>Préstamo</option>
						</select>
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha</label>
						<input type="date" name="fecha" id="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
				</div>

				<div id="motivo-wrap">
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Motivo <span class="text-gray-400">(solo anticipo)</span></label>
					<select name="motivo" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
						<option value="">—</option>
						<option value="vivienda" {{ old('motivo') === 'vivienda' ? 'selected' : '' }}>Vivienda</option>
						<option value="educacion" {{ old('motivo') === 'educacion' ? 'selected' : '' }}>Educación</option>
						<option value="salud" {{ old('motivo') === 'salud' ? 'selected' : '' }}>Salud</option>
					</select>
				</div>

				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Concepto <span class="text-gray-400">(opcional)</span></label>
					<input type="text" name="concepto" value="{{ old('concepto') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>

				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Monto</label>
						<input type="number" name="monto" id="monto" step="0.01" min="0.01" value="{{ old('monto') }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Moneda</label>
						<select name="moneda" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="USD" {{ old('moneda', 'USD') === 'USD' ? 'selected' : '' }}>USD</option>
							<option value="BS" {{ old('moneda') === 'BS' ? 'selected' : '' }}>Bs</option>
						</select>
					</div>
				</div>

				<div id="prestamo-wrap" class="hidden">
					<div class="grid grid-cols-3 gap-3">
						<div>
							<label class="block text-xs font-medium text-gray-600 mb-0.5">Plazo (meses)</label>
							<input type="number" name="plazo_meses" min="1" max="120" value="{{ old('plazo_meses') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						</div>
						<div>
							<label class="block text-xs font-medium text-gray-600 mb-0.5">Interés anual (%)</label>
							<input type="number" name="interes_anual" step="0.01" min="0" max="100" value="{{ old('interes_anual') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						</div>
						<div>
							<label class="block text-xs font-medium text-gray-600 mb-0.5">Cuota estimada</label>
							<input type="number" name="cuota" step="0.01" min="0" value="{{ old('cuota') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						</div>
					</div>
				</div>

				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Observación</label>
					<textarea name="observacion" rows="2" class="w-full border border-gray-300 px-2 py-1.5 text-sm">{{ old('observacion') }}</textarea>
				</div>
			</div>
		</fieldset>

		<div class="flex justify-end gap-2">
			<a href="{{ route('movimientos-prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
			<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Registrar movimiento</button>
		</div>
	</form>
</div>
@endsection

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function () {
		const tipo = document.getElementById('tipo');
		const motivoWrap = document.getElementById('motivo-wrap');
		const prestamoWrap = document.getElementById('prestamo-wrap');

		function toggleTipo() {
			const esPrestamo = tipo.value === 'prestamo';
			motivoWrap.classList.toggle('hidden', esPrestamo);
			prestamoWrap.classList.toggle('hidden', !esPrestamo);
		}
		tipo.addEventListener('change', toggleTipo);
		toggleTipo();

		const empleado = document.getElementById('empleado_id');
		const fecha = document.getElementById('fecha');
		const info = document.getElementById('info-saldo');
		const maximoUrl = "{{ route('movimientos-prestaciones.maximo', ['empleadoId' => '__EMPLEADO__']) }}";

		async function actualizarInfoSaldo() {
			info.textContent = '';
			if (!empleado.value) return;

			try {
				const params = new URLSearchParams();
				if (fecha.value) params.set('fecha', fecha.value);
				const url = maximoUrl.replace('__EMPLEADO__', empleado.value) + (params.toString() ? '?' + params.toString() : '');
				const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
				if (!res.ok) return;
				const data = await res.json();
				info.textContent = `Saldo de prestaciones al ${data.fecha}: ${data.saldo} ${data.moneda} · Máximo anticipo (${data.pct}%): ${data.maximo} ${data.moneda} · Monto al 75%: ${data.monto_75} ${data.moneda}`;
			} catch (e) { /* ignore */ }
		}

		empleado.addEventListener('change', actualizarInfoSaldo);
		fecha.addEventListener('change', actualizarInfoSaldo);
		if (empleado.value) actualizarInfoSaldo();
	});
</script>
@endpush
