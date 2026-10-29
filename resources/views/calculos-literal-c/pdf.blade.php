<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Liquidación Art. 142.c - {{ $calculo->trabajador_nombre }}</title>
	<style>
		body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 11px; color: #111; }
		.header { border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 14px; }
		.h1 { font-size: 16px; font-weight: bold; margin: 0; }
		.sub { font-size: 10px; color: #555; }
		table { width: 100%; border-collapse: collapse; margin-top: 10px; }
		th, td { border: 1px solid #777; padding: 5px 6px; text-align: left; }
		th { background: #f0f0f0; }
		.right { text-align: right; }
		.bold { font-weight: bold; }
		.total td { border-top: 2px solid #111; font-weight: bold; }
		.nota { margin-top: 12px; font-size: 9px; color: #666; }
		.firma { margin-top: 34px; width: 100%; }
		.firma td { border: 0; width: 50%; text-align: center; padding-top: 28px; }
		.linea { border-top: 1px solid #111; width: 80%; margin: 0 auto 4px; }
		.meta td { border: 0; padding: 2px 4px; }
	</style>
</head>
<body>
	<div class="header">
		<h1 class="h1">{{ $calculo->patrono_nombre }}</h1>
		@if ($calculo->patrono_rif)
			<p class="sub">RIF: {{ $calculo->patrono_rif }}</p>
		@endif
		<p class="sub">Recibo de Liquidación por el Artículo 142, literal c, de la LOTTT</p>
	</div>

	<table class="meta">
		<tr><td class="bold">Trabajador</td><td>{{ $calculo->trabajador_nombre }}</td><td class="bold">Cédula</td><td>{{ $calculo->trabajador_cedula ?: '—' }}</td></tr>
		<tr><td class="bold">Cargo</td><td>{{ $calculo->cargo ?: '—' }}</td><td class="bold">Tipo de salario</td><td>{{ $calculo->labelTipoSalario() }}</td></tr>
		<tr><td class="bold">Fecha de ingreso</td><td>{{ $calculo->fecha_ingreso->format('d/m/Y') }}</td><td class="bold">Fecha de egreso</td><td>{{ $calculo->fecha_egreso->format('d/m/Y') }}</td></tr>
		<tr><td class="bold">Salario</td><td>{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->salario) }}</td><td class="bold">Salario diario normal</td><td>{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->salario_diario_normal) }}</td></tr>
		<tr><td class="bold">Salario diario integral</td><td>{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->salario_diario_integral) }}</td><td class="bold">Días de utilidades</td><td>{{ $calculo->dias_utilidades }}</td></tr>
	</table>

	<table>
		<tr><th>Concepto</th><th class="right">Días</th><th class="right">Salario diario</th><th class="right">Importe</th></tr>
		@foreach (collect($calculo->conceptos ?? [])->where('tipo', 'asignacion') as $concepto)
			<tr>
				<td>{{ $concepto['concepto'] }}<br><span class="sub">{{ $concepto['base_legal'] }}</span></td>
				<td class="right">{{ $concepto['dias'] !== null ? $concepto['dias'] : '—' }}</td>
				<td class="right">{{ $concepto['salario_diario'] !== null ? formato_monto((float) $concepto['salario_diario']) : '—' }}</td>
				<td class="right">{{ formato_monto((float) $concepto['importe']) }}</td>
			</tr>
		@endforeach
		<tr class="total"><td colspan="3" class="right">Total asignaciones</td><td class="right">{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->total_asignaciones) }}</td></tr>
	</table>

	<table>
		<tr><th>Deducción</th><th class="right">Importe</th></tr>
		@foreach (collect($calculo->conceptos ?? [])->where('tipo', 'deduccion') as $deduccion)
			<tr><td>{{ $deduccion['concepto'] }}<br><span class="sub">{{ $deduccion['base_legal'] }}</span></td><td class="right">-{{ formato_monto((float) $deduccion['importe']) }}</td></tr>
		@endforeach
		<tr class="total"><td class="right">Total deducciones</td><td class="right">-{{ formato_monto((float) $calculo->total_deducciones) }}</td></tr>
		<tr class="total"><td class="right">Neto a pagar</td><td class="right">{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->neto) }}</td></tr>
	</table>

	@if ($equivalencia)
		<p class="nota">Equivalencia informativa: {{ $equivalencia['moneda'] === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto($equivalencia['monto']) }} a la tasa BCV {{ formato_monto((float) $calculo->tasa_bcv) }}.</p>
	@endif

	@php
		$neto = (float) $calculo->neto;
		$montoLetras = monto_en_letras($neto, $calculo->moneda);
		$montoEtiqueta = $calculo->moneda === 'USD'
			? 'USD '.formato_monto($neto)
			: 'Bs. '.formato_monto($neto);
		$cedula = $calculo->trabajador_cedula
			? 'titular de la cédula de identidad Nº '.$calculo->trabajador_cedula
			: '';
	@endphp

	<p class="nota">
		Yo, {{ $calculo->trabajador_nombre }} {{ $cedula }},
		he recibido de {{ $calculo->patrono_nombre }} la cantidad de:
		{{ $montoLetras }} ({{ $montoEtiqueta }}),
		por concepto de liquidación de prestaciones sociales conforme al Artículo 142, literal c, de la LOTTT,
		a mi entera y cabal satisfacción.-
	</p>

	<table class="firma">
		<tr>
			<td><div class="linea"></div>Firma del patrono<br>{{ $calculo->patrono_nombre }}</td>
			<td><div class="linea"></div>Recibí conforme<br>{{ $calculo->trabajador_nombre }}</td>
		</tr>
	</table>
</body>
</html>
