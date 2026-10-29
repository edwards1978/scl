<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Estado de Cuenta de Prestaciones Sociales – {{ $empleado->nombre_completo }}</title>
	<style>
		body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 11px; color: #111; }
		.header { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
		.h1 { font-size: 14px; font-weight: bold; margin: 0; }
		.h2 { font-size: 11px; font-weight: bold; margin: 0; }
		.sub { font-size: 9px; color: #555; }
		table { width: 100%; border-collapse: collapse; margin-top: 8px; }
		th, td { border: 1px solid #777; padding: 4px 5px; text-align: left; font-size: 10px; }
		th { background: #f0f0f0; }
		.cuenta th, .cuenta td { font-size: 9px; padding: 2px 3px; }
		.right { text-align: right; }
		.center { text-align: center; }
		.bold { font-weight: bold; }
		.nowrap { white-space: nowrap; }
		.total td { border-top: 2px solid #111; background: #fafafa; }
		tr.total { page-break-inside: avoid; }
		.resumen { page-break-inside: avoid; }
		.meta { width: 100%; }
		.meta td { border: none; padding: 1px 6px 1px 0; font-size: 9px; }
		.nota { margin-top: 8px; font-size: 8px; color: #666; }
		.firma { margin-top: 8px; }
		.firma-label { font-size: 9px; margin-bottom: 2px; }
		.firma-linea { border-bottom: 1px solid #111; width: 50%; margin-bottom: 4px; }
		.firma-nombre { font-size: 10px; font-weight: bold; margin: 0; }
		.firma-cedula { font-weight: normal; color: #555; }
	</style>
</head>
<body>
	<div class="header">
		<h1 class="h1">{{ $patrono->razon_social }}</h1>
		<h2 class="h2">RIF {{ $patrono->rif }}</h2>
		<p class="sub">Estado de Cuenta de Prestaciones Sociales — Art. 142 y 143 LOTTT</p>
	</div>

	<table class="meta">
		<tr>
			<td class="bold">Trabajador</td><td>{{ $empleado->nombre_completo }}</td>
			<td class="bold">C.I.</td><td>{{ $empleado->cedula }}</td>
		</tr>
		<tr>
			<td class="bold">Fecha de ingreso</td><td>{{ $empleado->fecha_ingreso?->format('d/m/Y') }}</td>
			<td class="bold">Moneda</td><td>{{ $empleado->moneda }}</td>
		</tr>
		<tr>
			<td class="bold">Período</td><td>{{ $desde ?? '—' }} a {{ $hasta ?? '—' }}</td>
			<td class="bold">Totales</td>
			<td>
				Totales del período {{ $desde }} a {{ $hasta }}
				· {{ ! $hayRango ? 'período completo' : ($incluirAnteriores ? 'incluye saldos anteriores' : 'no incluye saldos anteriores') }}
				({{ $periodosFiltrados }} de {{ $totalPeriodos }} períodos)
			</td>
		</tr>
	</table>

	<table class="cuenta">
		<tr>
			<th class="right" rowspan="2">Nº</th>
			<th colspan="2">Periodo</th>
			<th rowspan="2">Tiempo de servicio</th>
			<th class="right" rowspan="2">Sal. integral</th>
			<th class="right" rowspan="2">Saldo inicial período</th>
			<th class="right" rowspan="2">Depósito trim.</th>
			<th class="right" rowspan="2">Días adic.</th>
			<th class="right" rowspan="2">Anticipos</th>
			<th class="right" rowspan="2">Capital período</th>
			<th class="right" rowspan="2">Tasa %</th>
			<th class="right" rowspan="2">Interés</th>
			<th class="right" rowspan="2">Int. pag.</th>
			<th class="right" rowspan="2">Int. pend. período</th>
			<th class="right" rowspan="2">Saldo final período</th>
		</tr>
		<tr>
			<th>Desde</th>
			<th>Hasta</th>
		</tr>
		@forelse ($cuenta as $c)
			<tr>
				<td class="right">{{ $loop->iteration }}</td>
				<td class="nowrap">{{ $c->periodo_inicio?->format('d/m/Y') }}</td>
				<td class="nowrap">{{ $c->periodo_fin?->format('d/m/Y') }}@if ($c->es_proyeccion) <span class="sub">(proyección)</span>@endif</td>
				<td class="center nowrap">{{ $c->servicio_anios }} a · {{ $c->servicio_meses }} m · {{ $c->servicio_dias }} d</td>
				<td class="right">{{ $empleado->moneda }} {{ formato_monto((float) $c->salario_integral_diario) }}</td>
				<td class="right">{{ $empleado->moneda }} {{ formato_monto((float) $c->saldo_inicial_periodo) }}</td>
				<td class="right">{{ $c->deposito_trimestre > 0 ? $c->dias_deposito.' d · '.$empleado->moneda.' '.formato_monto((float) $c->deposito_trimestre) : '—' }}</td>
				<td class="right">{{ $c->dias_adicionales > 0 ? $c->dias_adicionales_cont.' d · '.$empleado->moneda.' '.formato_monto((float) $c->dias_adicionales) : '—' }}</td>
				<td class="right">{{ $c->anticipos > 0 ? '- '.$empleado->moneda.' '.formato_monto((float) $c->anticipos) : '—' }}</td>
				<td class="right">{{ $empleado->moneda }} {{ formato_monto((float) $c->capital_periodo) }}</td>
				<td class="right">{{ $c->tasa_interes !== null ? formato_monto((float) $c->tasa_interes, 4) : '—' }}</td>
				<td class="right">{{ $c->interes_mes > 0 ? $empleado->moneda.' '.formato_monto((float) $c->interes_mes) : '—' }}</td>
				<td class="right">{{ (float) ($c->interes_pagado ?? 0) > 0 ? '- '.$empleado->moneda.' '.formato_monto((float) $c->interes_pagado) : '—' }}</td>
				<td class="right">{{ (float) $c->interes_pendiente_periodo > 0 ? $empleado->moneda.' '.formato_monto((float) $c->interes_pendiente_periodo) : '—' }}</td>
				<td class="right bold">{{ $empleado->moneda }} {{ formato_monto((float) $c->saldo_final_periodo) }}</td>
			</tr>
		@empty
			<tr><td colspan="15" class="right">{{ $hayRango ? 'Sin períodos en el rango seleccionado.' : 'Sin periodos calculados.' }}</td></tr>
		@endforelse
		<tr class="total">
			<td colspan="14" class="right bold">Totales del período</td>
			<td></td>
		</tr>
		<tr class="total">
			<td colspan="14" class="right bold">Saldo inicial del período</td>
			<td class="right bold">{{ $empleado->moneda }} {{ formato_monto($saldoInicialRango) }}</td>
		</tr>
		<tr class="total">
			<td colspan="14" class="right bold">Saldo final del período</td>
			<td class="right bold">{{ $empleado->moneda }} {{ formato_monto($saldoFinalRango) }}</td>
		</tr>
	</table>

	<table class="resumen">
		<tr class="total">
			<td class="bold">Depósitos trimestrales</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($totalDepositos) }}</td>
			<td class="bold">Días adicionales</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($totalAdicionales) }}</td>
		</tr>
		<tr class="total">
			<td class="bold">Intereses generados</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($totalIntereses) }}</td>
			<td class="bold">Anticipos descontados</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($totalAnticipos) }}</td>
		</tr>
		<tr class="total">
			<td class="bold">Intereses pagados</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($totalInteresesPagados) }}</td>
			<td class="bold">Interés capitalizado</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($totalInteresesCapitalizados) }}</td>
		</tr>
		<tr class="total">
			<td class="bold">Neto del período</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($netoPeriodo) }}</td>
			<td class="bold">Saldo final del período</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($saldoFinalRango) }}</td>
		</tr>
		<tr class="total">
			<td class="bold">Interés pendiente al cierre</td><td class="right">{{ $empleado->moneda }} {{ formato_monto($interesPendiente) }}</td>
			<td class="bold">Períodos mostrados</td><td class="right">{{ $periodosFiltrados }} de {{ $totalPeriodos }}</td>
		</tr>
	</table>

	<p class="nota">Emitido: {{ now()->format('d/m/Y') }} | La tabla muestra saldos acumulados reales; los totales de movimientos son del período. | Tasa %: promedio entre activa y pasiva del BCV aplicado al interés del período (informativa).</p>

	<div class="firma">
		<p class="firma-label">Recibí conforme,</p>
		<div class="firma-linea">&nbsp;</div>
		<p class="firma-nombre">{{ $empleado->nombre_completo }} <span class="firma-cedula">C.I. {{ $empleado->cedula }}</span></p>
	</div>
</body>
</html>
