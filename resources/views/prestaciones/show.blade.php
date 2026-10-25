@extends('layouts.app')

@section('content')
@php
	$moneda = $empleado->moneda;
	$exportParams = array_filter(['empleadoId' => $empleado->id, 'desde' => $desde, 'hasta' => $hasta, 'incluir_anteriores' => $incluirAnteriores ? 1 : 0], fn ($v) => $v !== null);
@endphp
<div class="p-4 mx-auto max-w-6xl">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Estado de Cuenta de Prestaciones Sociales</h1>
		<a href="{{ route('prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		{{ $empleado->nombre_completo }} · C.I. {{ $empleado->cedula }} · Ingreso {{ $empleado->fecha_ingreso?->format('d/m/Y') }}
	</p>

	<form method="GET" action="{{ route('prestaciones.show', $empleado->id) }}" class="bg-white border border-gray-200 p-3 mb-4">
		@php
			$desdeValor = old('desde', $desde);
			$hastaValor = old('hasta', $hasta);
		@endphp
		<div class="flex flex-wrap items-end gap-3">
			<div class="w-36 shrink-0">
				<label for="desde" class="block text-xs text-gray-500 mb-1">Periodo desde <span class="text-gray-400">(AAAA-MM)</span></label>
				<div class="relative">
					<input id="desde" type="text" name="desde" value="{{ $desdeValor }}"
						placeholder="AAAA-MM" pattern="\d{4}-(0[1-9]|1[0-2])" inputmode="numeric" autocomplete="off"
						onfocus="this.type='month'" onblur="if(!this.value)this.type='text'"
						min="2000-01" max="{{ now()->format('Y-m') }}"
						title="Seleccione el mes y año en formato AAAA-MM"
						class="w-full border border-gray-300 px-2 py-1.5 pr-8 text-sm">
				</div>
			</div>
			<div class="w-36 shrink-0">
				<label for="hasta" class="block text-xs text-gray-500 mb-1">Periodo hasta <span class="text-gray-400">(AAAA-MM)</span></label>
				<div class="relative">
					<input id="hasta" type="text" name="hasta" value="{{ $hastaValor }}"
						placeholder="AAAA-MM" pattern="\d{4}-(0[1-9]|1[0-2])" inputmode="numeric" autocomplete="off"
						onfocus="this.type='month'" onblur="if(!this.value)this.type='text'"
						min="2000-01" max="{{ now()->format('Y-m') }}"
						title="Seleccione el mes y año en formato AAAA-MM"
						class="w-full border border-gray-300 px-2 py-1.5 pr-8 text-sm">
				</div>
			</div>
			@if ($hayRango)
				<label class="inline-flex items-center gap-2 text-sm text-gray-700 pb-2 whitespace-nowrap">
					<input type="hidden" name="incluir_anteriores" value="0">
					<input type="checkbox" name="incluir_anteriores" value="1" @checked($incluirAnteriores) class="border-gray-300">
					Incluir saldos acumulados anteriores
				</label>
			@endif
			<button class="px-4 py-1.5 text-sm bg-gray-800 text-white hover:bg-gray-900">Filtrar</button>
			<a href="{{ route('prestaciones.show', $empleado->id) }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Limpiar</a>
		</div>
		<p class="text-[11px] text-gray-400 mt-1">Formato AAAA-MM · Ejemplo: 2025-01 a 2025-06</p>
		@if ($errors->any())
			<div class="mt-2 text-xs text-red-600">{{ $errors->first() }}</div>
		@endif
		<p class="text-xs text-gray-500 mt-2">
			Mostrando {{ $periodosFiltrados }} de {{ $totalPeriodos }} período(s).
			@if ($hayRango)
				Período: {{ $periodoDesde }} a {{ $periodoHasta }} · {{ $incluirAnteriores ? 'incluye saldos anteriores' : 'solo el período' }}.
			@else
				Período completo: {{ $periodoDesde }} a {{ $periodoHasta }}.
			@endif
		</p>
	</form>

	<div class="bg-white border border-gray-200 p-4 mb-4">
		<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
			<h2 class="text-sm font-semibold text-gray-800">Totales del período</h2>
			<div class="text-xs text-gray-500">
				Período: <span class="font-medium text-gray-700">{{ $periodoDesde }} a {{ $periodoHasta }}</span>
				@if ($hayRango)
					· {{ $incluirAnteriores ? 'incluye saldos anteriores' : 'no incluye saldos anteriores' }}
				@endif
			</div>
		</div>
		<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
			<div class="border border-gray-200 p-3">
				<p class="text-xs text-gray-500 mb-1">Saldo inicial del período</p>
				<p class="text-base font-semibold">{{ $moneda }} {{ formato_monto($saldoInicialRango) }}</p>
			</div>
			<div class="border border-gray-200 p-3">
				<p class="text-xs text-gray-500 mb-1">Depósitos del período</p>
				<p class="text-base font-semibold">{{ $moneda }} {{ formato_monto($totalDepositos) }}</p>
			</div>
			<div class="border border-gray-200 p-3">
				<p class="text-xs text-gray-500 mb-1">Días adicionales del período</p>
				<p class="text-base font-semibold">{{ $moneda }} {{ formato_monto($totalAdicionales) }}</p>
			</div>
			<div class="border border-gray-200 p-3">
				<p class="text-xs text-gray-500 mb-1">Intereses devengados del período</p>
				<p class="text-base font-semibold">{{ $moneda }} {{ formato_monto($totalIntereses) }}</p>
			</div>
			<div class="border border-gray-200 p-3">
				<p class="text-xs text-gray-500 mb-1">Anticipos del período</p>
				<p class="text-base font-semibold text-red-600">{{ $moneda }} {{ formato_monto($totalAnticipos) }}</p>
			</div>
			<div class="border border-emerald-300 bg-emerald-50 p-3">
				<p class="text-xs text-emerald-700 mb-1">Neto del período</p>
				<p class="text-base font-semibold text-emerald-800">{{ $moneda }} {{ formato_monto($netoPeriodo) }}</p>
			</div>
			<div class="border border-blue-300 bg-blue-50 p-3">
				<p class="text-xs text-blue-700 mb-1">Saldo final del período</p>
				<p class="text-base font-semibold text-blue-800">{{ $moneda }} {{ formato_monto($saldoFinalRango) }}</p>
			</div>
			<div class="border border-gray-200 p-3">
				<p class="text-xs text-gray-500 mb-1">Interés pendiente al cierre</p>
				<p class="text-base font-semibold text-amber-700">{{ $moneda }} {{ formato_monto($interesPendiente) }}</p>
			</div>
			<div class="border border-gray-200 p-3">
				<p class="text-xs text-gray-500 mb-1">Interés capitalizado del período</p>
				<p class="text-base font-semibold">{{ $moneda }} {{ formato_monto($totalInteresesCapitalizados) }}</p>
			</div>
		</div>
		<p class="text-xs text-gray-500 mt-3">
			Los totales de movimientos son siempre del período seleccionado. El saldo inicial incluye los períodos anteriores solo si el checkbox está marcado; si no, empieza en cero.
			El saldo final es la suma del saldo inicial más el neto del período.
			Intereses pagados del período: {{ $moneda }} {{ formato_monto($totalInteresesPagados) }}.
			La tabla inferior muestra los saldos acumulados reales de cada mes.
		</p>
	</div>

	<div class="bg-white border border-gray-200 overflow-x-auto mb-4">
		<table class="w-full text-sm">
			<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
				<tr>
					<th class="text-center px-3 py-2 w-12">Nº</th>
					<th class="text-left px-3 py-2">Periodo</th>
					<th class="text-right px-3 py-2">Sal. integral</th>
					<th class="text-right px-3 py-2">Saldo acum. inicial</th>
					<th class="text-right px-3 py-2">Capital</th>
					<th class="text-right px-3 py-2">Depósito trim.</th>
					<th class="text-right px-3 py-2">Días adic.</th>
					<th class="text-right px-3 py-2">Interés</th>
					<th class="text-right px-3 py-2">Tasa interés %</th>
					<th class="text-right px-3 py-2">Int. pend.</th>
					<th class="text-right px-3 py-2">Int. pag.</th>
					<th class="text-right px-3 py-2">Anticipos</th>
					<th class="text-right px-3 py-2">Saldo acum. final</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@forelse ($cuenta as $c)
					<tr class="hover:bg-gray-50">
						<td class="px-3 py-2 text-center text-gray-500">{{ $loop->index }}</td>
						<td class="px-3 py-2">{{ \Carbon\Carbon::createFromFormat('Y-m', $c->periodo)->format('m/Y') }}</td>
						<td class="px-3 py-2 text-right text-gray-600">{{ formato_monto((float) $c->salario_integral_diario) }}</td>
						<td class="px-3 py-2 text-right">{{ formato_monto((float) $c->saldo_inicial) }}</td>
						<td class="px-3 py-2 text-right">{{ formato_monto((float) ($c->capital_final ?? $c->saldo_final)) }}</td>
						<td class="px-3 py-2 text-right">{{ $c->deposito_trimestre > 0 ? $c->dias_deposito.' d · '.formato_monto((float) $c->deposito_trimestre) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ $c->dias_adicionales > 0 ? $c->dias_adicionales_cont.' d · '.formato_monto((float) $c->dias_adicionales) : '—' }}</td>
						<td class="px-3 py-2 text-right">
							{{ $c->interes_mes > 0 ? formato_monto((float) $c->interes_mes) : '—' }}
							@if ((float) ($c->interes_capitalizado ?? 0) > 0)
								<div class="text-[10px] text-amber-600">cap. {{ formato_monto((float) $c->interes_capitalizado) }}</div>
							@endif
						</td>
						<td class="px-3 py-2 text-right">{{ $c->tasa_interes !== null ? formato_monto((float) $c->tasa_interes, 4) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ (float) ($c->interes_pendiente_final ?? 0) > 0 ? formato_monto((float) $c->interes_pendiente_final) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ (float) ($c->interes_pagado ?? 0) > 0 ? formato_monto((float) $c->interes_pagado) : '—' }}</td>
						<td class="px-3 py-2 text-right text-red-600">{{ $c->anticipos > 0 ? '- '.formato_monto((float) $c->anticipos) : '—' }}</td>
						<td class="px-3 py-2 text-right font-medium">{{ formato_monto((float) $c->saldo_final) }}</td>
					</tr>
				@empty
					<tr><td colspan="13" class="text-center py-8 text-gray-400">{{ $hayRango ? 'Sin períodos en el rango seleccionado.' : 'Sin periodos calculados.' }}</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
	<p class="text-xs text-gray-500 mb-4">Tasa interés %: tasa anual del BCV aplicada al cálculo del interés del período (informativa).</p>

	<div class="bg-white border border-gray-200 p-4 mb-4">
		<h2 class="text-sm font-semibold text-gray-800 mb-3">Pagos de intereses</h2>

		<form method="POST" action="{{ route('prestaciones.intereses', $empleado->id) }}" class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
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
				<button class="px-4 py-1.5 text-sm bg-gray-800 text-white hover:bg-gray-900">Registrar pago</button>
				<span class="text-xs text-gray-500 ml-2">El pago se aplica al interés pendiente; el exceso reduce capital. Se recalcula desde el mes del pago.</span>
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
									<form method="POST" action="{{ route('prestaciones.intereses.destroy', [$empleado->id, $p->id]) }}" onsubmit="return confirm('¿Eliminar este pago de intereses?')">
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

	<div class="flex justify-end gap-2">
		<form method="POST" action="{{ route('prestaciones.recalcular', $empleado->id) }}" onsubmit="return confirm('¿Recalcular el estado de cuenta completo?')">
			@csrf
			<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">↻ Recalcular</button>
		</form>
		<a href="{{ route('prestaciones.pdf', $exportParams) }}" target="_blank" rel="noopener" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Descargar PDF</a>
		<a href="{{ route('prestaciones.hoja-calculo', $exportParams) }}" target="_blank" rel="noopener" class="px-4 py-1.5 text-sm bg-green-600 text-white hover:bg-green-700">Hoja de Cálculo (.ods)</a>
	</div>
</div>
@endsection
