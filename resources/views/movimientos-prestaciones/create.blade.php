@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-2xl">
	<h1 class="text-xl font-semibold mb-4">Nuevo Anticipo / Préstamo</h1>

	<form method="POST" action="{{ route('movimientos-prestaciones.store') }}" class="space-y-4">
		@csrf
		@if ($volverUrl)
			<input type="hidden" name="origen" value="prestaciones">
			<input type="hidden" name="volver_empleado_id" value="{{ $prefill['empleado_id'] }}">
			<input type="hidden" name="volver_page" value="{{ old('volver_page', request('volver_page')) }}">
			<input type="hidden" name="volver_porPagina" value="{{ old('volver_porPagina', request('volver_porPagina')) }}">
			<input type="hidden" name="volver_desde" value="{{ old('volver_desde', request('volver_desde')) }}">
			<input type="hidden" name="volver_hasta" value="{{ old('volver_hasta', request('volver_hasta')) }}">
			<input type="hidden" name="volver_incluir_anteriores" value="{{ old('volver_incluir_anteriores', request('volver_incluir_anteriores')) }}">
		@endif

		<fieldset class="bg-white border border-gray-200 p-5">
			<legend class="text-sm font-semibold text-gray-700 px-1">Movimiento</legend>
			<div class="space-y-3 mt-2">
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Empleado</label>
					<select name="empleado_id" id="empleado_id" required class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
						<option value="">Seleccione un empleado</option>
						@foreach ($patrono->empleados()->where('estatus', 'Activo')->orderBy('nombres')->orderBy('apellidos')->get() as $emp)
							<option value="{{ $emp->id }}" {{ (int) old('empleado_id', $prefill['empleado_id']) === (int) $emp->id ? 'selected' : '' }}>
								{{ $emp->nombre_completo }} — {{ $emp->cedula }}
							</option>
						@endforeach
					</select>
					@error('empleado_id') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
				</div>

				<div class="grid grid-cols-3 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Tipo</label>
						<select name="tipo" id="tipo" required class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="anticipo" {{ old('tipo', $prefill['tipo']) === 'prestamo' ? '' : 'selected' }}>Anticipo</option>
							<option value="prestamo" {{ old('tipo', $prefill['tipo']) === 'prestamo' ? 'selected' : '' }}>Préstamo</option>
						</select>
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha del movimiento</label>
						<input type="date" name="fecha" id="fecha" value="{{ old('fecha', $prefill['fecha']) }}" max="{{ now()->addMonths(12)->endOfMonth()->format('Y-m-d') }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						@error('fecha') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha del comprobante</label>
						<input type="date" name="fecha_comprobante" value="{{ old('fecha_comprobante', $prefill['fecha_comprobante']) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						@error('fecha_comprobante') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
				</div>
				<p class="text-[11px] text-gray-500">
					La fecha del movimiento puede ser futura (hasta 12 meses) y se usa para el cálculo de prestaciones.
					La fecha del comprobante es la referencia contable del documento.
				</p>

				<div id="info-saldo" class="hidden bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-900"></div>

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
						<input type="number" name="monto" id="monto" step="0.01" min="0.01" value="{{ old('monto', $prefill['monto']) }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						<div id="limite-acciones" class="mt-1 flex gap-2">
							<button type="button" id="monto-100" class="px-2 py-1 text-[11px] border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:opacity-50" disabled>100% del límite</button>
							<button type="button" id="monto-75" class="px-2 py-1 text-[11px] border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:opacity-50" disabled>75% del límite</button>
						</div>
						<p id="aviso-monto" class="hidden mt-1 text-[11px] text-amber-700"></p>
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Moneda</label>
						<select name="moneda" id="moneda" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="USD" {{ old('moneda', $prefill['moneda']) === 'USD' ? 'selected' : '' }}>USD</option>
							<option value="BS" {{ old('moneda', $prefill['moneda']) === 'BS' ? 'selected' : '' }}>Bs</option>
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
			<x-boton-volver :fallback="$volverUrl ?? route('movimientos-prestaciones.index')" label="Cancelar" />
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
		const limiteAcciones = document.getElementById('limite-acciones');

		function toggleTipo() {
			const esPrestamo = tipo.value === 'prestamo';
			motivoWrap.classList.toggle('hidden', esPrestamo);
			prestamoWrap.classList.toggle('hidden', !esPrestamo);
			limiteAcciones.classList.toggle('hidden', esPrestamo);
		}
		tipo.addEventListener('change', toggleTipo);
		toggleTipo();

		const empleado = document.getElementById('empleado_id');
		const fecha = document.getElementById('fecha');
		const info = document.getElementById('info-saldo');
		const monto = document.getElementById('monto');
		const moneda = document.getElementById('moneda');
		const avisoMonto = document.getElementById('aviso-monto');
		const boton100 = document.getElementById('monto-100');
		const boton75 = document.getElementById('monto-75');
		const maximoUrl = "{{ route('movimientos-prestaciones.maximo', ['empleadoId' => '__EMPLEADO__']) }}";
		let ultimoLimite = null;

		const numero = (valor, decimales) => valor === null || valor === undefined
			? '—'
			: Number(valor).toLocaleString('es-VE', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });

		async function actualizarInfoSaldo() {
			info.classList.add('hidden');
			info.textContent = '';
			avisoMonto.classList.add('hidden');
			avisoMonto.textContent = '';
			ultimoLimite = null;
			boton100.disabled = true;
			boton75.disabled = true;
			if (!empleado.value) return;

			try {
				const params = new URLSearchParams();
				if (fecha.value) params.set('fecha', fecha.value);
				if (moneda.value) params.set('moneda', moneda.value);
				const url = maximoUrl.replace('__EMPLEADO__', empleado.value) + (params.toString() ? '?' + params.toString() : '');
				const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
				if (!res.ok) return;
				const data = await res.json();
				ultimoLimite = data;
				const tieneLimite = Number(data.maximo) > 0;
				boton100.disabled = !tieneLimite;
				boton75.disabled = !tieneLimite;
				const etiqueta = data.es_proyeccion ? ' (proyección)' : '';
				info.textContent = `Saldo de prestaciones al ${data.fecha}${etiqueta}: ${numero(data.saldo, 2)} ${data.moneda} · Límite de anticipo (${data.pct}%): ${numero(data.maximo, 2)} ${data.moneda} · 75% del límite: ${numero(data.monto_75, 2)} ${data.moneda}`;
				info.classList.remove('hidden');

				if (tipo.value !== 'prestamo' && monto.value !== '' && Number(monto.value) > Number(data.maximo)) {
					monto.value = Number(data.maximo).toFixed(2);
					avisoMonto.textContent = `El monto sugerido supera el máximo permitido; se ajustó a ${numero(data.maximo, 2)} ${data.moneda}.`;
					avisoMonto.classList.remove('hidden');
				}
			} catch (e) { /* ignore */ }
		}

		boton100.addEventListener('click', () => {
			if (!ultimoLimite) return;
			monto.value = Number(ultimoLimite.maximo).toFixed(2);
			avisoMonto.classList.add('hidden');
		});

		boton75.addEventListener('click', () => {
			if (!ultimoLimite) return;
			monto.value = Number(ultimoLimite.monto_75).toFixed(2);
			avisoMonto.classList.add('hidden');
		});

		empleado.addEventListener('change', actualizarInfoSaldo);
		fecha.addEventListener('change', actualizarInfoSaldo);
		moneda.addEventListener('change', actualizarInfoSaldo);
		tipo.addEventListener('change', actualizarInfoSaldo);
		if (empleado.value) actualizarInfoSaldo();
	});
</script>
@endpush
