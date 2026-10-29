@extends('layouts.app')

@section('content')
@php
	$moneda = $empleado->moneda;
	$exportParams = array_filter(['empleadoId' => $empleado->id, 'desde' => $desde, 'hasta' => $hasta, 'incluir_anteriores' => $incluirAnteriores ? 1 : 0], fn ($v) => $v !== null);
@endphp
<div class="p-4 mx-4">
	<div class="flex items-center justify-between mb-1 gap-2">
		<h1 class="text-xl font-semibold">Estado de Cuenta de Prestaciones Sociales</h1>
		<div class="flex items-center gap-2">
			<a href="{{ route('intereses-prestaciones.index', ['empleado_id' => $empleado->id]) }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Pagos de intereses</a>
			<a href="{{ route('prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
		</div>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		{{ $empleado->nombre_completo }} · C.I. {{ $empleado->cedula }} · Ingreso {{ $empleado->fecha_ingreso?->format('d/m/Y') }}
	</p>

	@if (! $empleado->estaActivo())
		<div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs">
			Trabajador inactivo o liquidado: esta pantalla es de consulta histórica. No se permiten nuevos cálculos, pagos de intereses ni anticipos.
		</div>
	@endif

	@php
		$modalidadDeposito = $empleado->patrono?->deposito_tipo ?? 'contabilidad';
	@endphp
	@if ($modalidadDeposito !== 'contabilidad')
		<div class="mb-4 p-3 bg-blue-50 border border-blue-200 text-blue-800 text-xs">
			Los intereses de esta garantía son calculados por {{ $modalidadDeposito === 'fideicomiso' ? 'la entidad bancaria del fideicomiso' : 'el Fondo Nacional de Prestaciones Sociales' }}. SCL no calcula intereses en esta modalidad.
		</div>
	@elseif ($empleado->capitaliza_intereses)
		<div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs">
			Capitalización de intereses autorizada por escrito desde {{ optional($empleado->capitalizacion_autorizada_desde)->format('d/m/Y') ?? '—' }}.
			@if ($empleado->capitalizacion_documento) Referencia: {{ $empleado->capitalizacion_documento }}. @endif
		</div>
	@else
		<div class="mb-4 p-3 bg-gray-50 border border-gray-200 text-gray-600 text-xs">
			Intereses no capitalizados: se acumulan como pendientes y no generan intereses sobre intereses. Para capitalizarlos se requiere autorización escrita del trabajador.
		</div>
	@endif

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
						min="2000-01" max="{{ $maxProyeccion }}"
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
						min="2000-01" max="{{ $maxProyeccion }}"
						title="Seleccione el mes y año en formato AAAA-MM"
						class="w-full border border-gray-300 px-2 py-1.5 pr-8 text-sm">
				</div>
			</div>
			<label class="inline-flex items-center gap-2 text-sm text-gray-700 pb-2 whitespace-nowrap" title="Al filtrar, incluye los saldos anteriores a la fecha desde">
				<input type="hidden" name="incluir_anteriores" value="0">
				<input type="checkbox" name="incluir_anteriores" value="1" @checked($incluirAnteriores) class="border-gray-300">
				Incluir saldos acumulados anteriores
			</label>
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

	@if ($proyeccionHasta)
		<div class="bg-white border border-gray-200 px-3 py-2 mb-4 flex flex-wrap items-center gap-3">
			<p class="text-xs text-gray-600">
				Proyección guardada hasta <strong>{{ $proyeccionHasta }}</strong>.
				Para reproyectar, use la opción del listado de prestaciones, que se aplica a todos los trabajadores activos.
			</p>
			@if ($empleado->estaActivo())
				<form method="POST" action="{{ route('prestaciones.proyeccion.destroy', $empleado->id) }}" class="ml-auto">
					@csrf @method('DELETE')
					<button class="px-3 py-1.5 text-sm border border-red-300 text-red-600 hover:bg-red-50">Eliminar proyección</button>
				</form>
			@endif
		</div>
	@endif

	@if ($incluyeProyeccion)
		<div class="mb-3 p-3 bg-blue-50 border border-blue-200 text-blue-800 text-xs">
			Incluye períodos proyectados hasta {{ $proyeccionHasta }}. Las filas están marcadas como proyección. Son cifras informativas calculadas con la última tasa y el último salario; no representan un monto causado.
		</div>
	@endif

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
			El neto del período es la variación real del saldo (capital más interés pendiente); los pagos de intereses que exceden el interés pendiente y el capital disponible no se aplican al saldo.
			Intereses pagados del período: {{ $moneda }} {{ formato_monto($totalInteresesPagados) }}.
			La tabla inferior muestra los saldos acumulados reales de cada mes.
		</p>
	</div>

	<div class="bg-white border border-gray-200 overflow-x-auto mb-4">
		<table class="w-full text-sm">
			<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
				<tr>
					<th rowspan="2" class="text-center px-3 py-2 w-12 align-middle">Nº</th>
					<th colspan="2" class="text-center px-3 py-2 border-l border-gray-200">Periodo</th>
					<th rowspan="2" class="text-center px-3 py-2 border-l border-gray-200">Tiempo de servicio</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle border-l border-gray-200">Sal. integral</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Saldo inicial período</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Depósito trim.</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Días adic.</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Anticipos</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Capital período</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Tasa interés %</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Interés</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Int. pag.</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Int. pend. período</th>
					<th rowspan="2" class="text-right px-3 py-2 align-middle">Saldo final período</th>
					<th rowspan="2" class="text-center px-2 py-2 align-middle border-l border-gray-200">Acción</th>
				</tr>
				<tr>
					<th class="text-left px-3 py-2 border-l border-gray-200">Desde</th>
					<th class="text-left px-3 py-2">Hasta</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@forelse ($cuenta as $c)
					<tr class="hover:bg-gray-50">
						<td class="px-3 py-2 text-center text-gray-500">{{ $loop->iteration }}</td>
						<td class="px-3 py-2 whitespace-nowrap">{{ $c->periodo_inicio?->format('d/m/Y') }}</td>
						<td class="px-3 py-2 whitespace-nowrap">
							{{ $c->periodo_fin?->format('d/m/Y') }}
						</td>
						<td class="px-3 py-2 text-center whitespace-nowrap">
							{{ $c->servicio_anios }} a · {{ $c->servicio_meses }} m · {{ $c->servicio_dias }} d
							@if ($c->es_proyeccion)
								<br>
								<span class="ml-1 inline-block text-[10px] px-1.5 py-0.5 bg-blue-100 text-blue-700">proyección</span>
							@endif
						</td>
						<td class="px-3 py-2 text-right text-gray-600">{{ formato_monto((float) $c->salario_integral_diario) }}</td>
						<td class="px-3 py-2 text-right">{{ formato_monto((float) $c->saldo_inicial_periodo) }}</td>
						<td class="px-3 py-2 text-right">{{ $c->deposito_trimestre > 0 ? $c->dias_deposito.' d · '.formato_monto((float) $c->deposito_trimestre) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ $c->dias_adicionales > 0 ? $c->dias_adicionales_cont.' d · '.formato_monto((float) $c->dias_adicionales) : '—' }}</td>
						<td class="px-3 py-2 text-right text-red-600">{{ $c->anticipos > 0 ? '- '.formato_monto((float) $c->anticipos) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ formato_monto((float) $c->capital_periodo) }}</td>
						<td class="px-3 py-2 text-right">{{ $c->tasa_interes !== null ? formato_monto((float) $c->tasa_interes, 4) : '—' }}</td>
						<td class="px-3 py-2 text-right">
							{{ $c->interes_mes > 0 ? formato_monto((float) $c->interes_mes) : '—' }}
							@if ((float) ($c->interes_capitalizado ?? 0) > 0)
								<div class="text-[10px] text-amber-600">cap. {{ formato_monto((float) $c->interes_capitalizado) }}</div>
							@endif
						</td>
						<td class="px-3 py-2 text-right text-red-600">{{ (float) ($c->interes_pagado ?? 0) > 0 ? '- '.formato_monto((float) $c->interes_pagado) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ (float) $c->interes_pendiente_periodo > 0 ? formato_monto((float) $c->interes_pendiente_periodo) : '—' }}</td>
						<td class="px-3 py-2 text-right font-medium">{{ formato_monto((float) $c->saldo_final_periodo) }}</td>
						<td class="px-2 py-2 text-center">
							@if ($c->es_proyeccion && $empleado->estaActivo())
								<form method="POST" action="{{ route('prestaciones.proyeccion.periodo.destroy', [$empleado->id, $c->id]) }}"
									onsubmit="return confirm('¿Eliminar el período proyectado {{ $c->periodo }}?')">
									@csrf @method('DELETE')
									<button class="p-1 text-gray-400 hover:text-red-500" title="Eliminar período proyectado">🗑</button>
								</form>
							@else
								<span class="text-gray-300">—</span>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="16" class="text-center py-8 text-gray-400">{{ $hayRango ? 'Sin períodos en el rango seleccionado.' : 'Sin periodos calculados.' }}</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
	<p class="text-xs text-gray-500 mb-4">Tasa interés %: tasa anual promedio entre activa y pasiva del BCV, aplicada al cálculo del interés del período (informativa).</p>

	<div class="flex justify-end gap-2">
		@if ($empleado->estaActivo())
		<form method="POST" action="{{ route('prestaciones.recalcular', $empleado->id) }}" onsubmit="return confirm('¿Recalcular el estado de cuenta completo?')">
			@csrf
			<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">↻ Recalcular</button>
		</form>
		@endif
		<a href="{{ route('prestaciones.pdf', $exportParams) }}" target="_blank" rel="noopener" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Descargar PDF</a>
		<a href="{{ route('prestaciones.hoja-calculo', $exportParams) }}" target="_blank" rel="noopener" class="px-4 py-1.5 text-sm bg-green-600 text-white hover:bg-green-700">Hoja de Cálculo (.ods)</a>
	</div>
</div>
@endsection
