<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Recibo Bonificación de Fin de Año – {{ $empleado->nombre_completo }}</title>
	<style>
		body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 12px; color: #111; }
		.header { border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 16px; }
		.h1 { font-size: 16px; font-weight: bold; margin: 0; }
		.h2 { font-size: 12px; font-weight: bold; margin: 0; }
		.sub { font-size: 10px; color: #555; }
		table { width: 100%; border-collapse: collapse; margin-top: 10px; }
		th, td { border: 1px solid #777; padding: 5px 6px; text-align: left; font-size: 11px; }
		th { background: #f0f0f0; }
		.right { text-align: right; }
		.bold { font-weight: bold; }
		.total td { border-top: 2px solid #111; }
		.nota { margin-top: 14px; font-size: 9px; color: #666; }
		.firma { margin-top: 28px; }
		.firma-label { font-size: 11px; margin-bottom: 4px; }
		.firma-linea { border-bottom: 1px solid #111; width: 50%; margin-bottom: 4px; }
		.firma-nombre { font-size: 11px; font-weight: bold; margin: 0; }
		.firma-cedula { font-weight: normal; color: #555; }
		.firma-fila { width: 100%; }
		.huella { display: inline-block; margin-right: 40px; text-align: center; vertical-align: top; }
		.cuadro { width: 60px; height: 60px; border: 1px solid #111; }
		.huella span { font-size: 9px; color: #555; display: block; margin-top: 3px; }
	</style>
</head>
<body>
	<div class="header">
		<h1 class="h1">{{ $patrono->razon_social }}</h1>
		<h2 class="h2">RIF ({{ $patrono->rif }})</h2>
		<p class="sub">Recibo de Bonificación de Fin de Año (anticipo de utilidades – LOTTT)</p>
	</div>

	<table class="meta">
		<tr><td class="bold">Trabajador</td><td>{{ $empleado->nombre_completo }}</td>
			<td class="bold">Fecha de cálculo</td><td>{{ $fecha_emision instanceof \Carbon\Carbon ? $fecha_emision->format('d/m/Y') : \Carbon\Carbon::parse($fecha_emision)->format('d/m/Y') }}</td></tr>
		<tr><td class="bold">C.I.</td><td>{{ $empleado->cedula }}</td>
			<td class="bold">Año</td><td>{{ $bonificacion->anio }}</td></tr>
		<tr><td class="bold">Periodo</td><td>{{ $bonificacion->periodo_inicio->format('d/m/Y') }} — {{ $bonificacion->periodo_fin->format('d/m/Y') }}</td>
			<td class="bold">Moneda</td><td>{{ $bonificacion->moneda }}</td></tr>
		<tr><td class="bold">Meses laborados</td><td>{{ $bonificacion->meses_laborados }}</td>
			<td class="bold">Días a pagar</td><td>{{ $bonificacion->dias_a_pagar }}</td></tr>
		<tr><td class="bold">Salario diario</td><td>{{ $bonificacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) ($bonificacion->moneda === 'USD' ? $bonificacion->salario_diario_usd : $bonificacion->salario_diario_bs)) }}</td>
			<td class="bold">Sueldo Mensual</td><td>{{ $bonificacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) ($bonificacion->moneda === 'USD' ? $bonificacion->salario_mensual_usd : $bonificacion->salario_mensual_bs)) }}</td></tr>
	</table>

	<table>
		<tr><th>Concepto (base legal)</th><th class="right">Días</th><th>Importe</th></tr>
		@foreach ($asignaciones as $c)
			<tr>
				<td>{{ $c['concepto'] }} <span class="sub">({{ $c['base_legal'] }}) — {{ $c['observacion'] }}</span></td>
				<td class="right">{{ $c['dias'] }}</td>
				<td class="right">{{ formato_monto((float) $c['importe']) }}</td>
			</tr>
		@endforeach
		<tr class="total"><td colspan="2" class="bold right">Total asignaciones</td><td class="right bold">{{ formato_monto($total_asignaciones) }}</td></tr>
		<tr><td colspan="3" class="bold">Deducciones</td></tr>
		@forelse ($deducciones as $d)
			<tr>
				<td>{{ $d['concepto'] }} <span class="sub">({{ $d['base_legal'] }})</span></td>
				<td class="right">{{ $d['porcentaje'] * 100 }}%</td>
				<td class="right">-{{ formato_monto((float) $d['importe']) }}</td>
			</tr>
		@empty
			<tr><td colspan="3" class="py-1 text-gray-400">Sin deducciones</td></tr>
		@endforelse
		<tr class="total"><td colspan="2" class="bold right">Total deducciones</td><td class="right bold">-{{ formato_monto($total_deducciones) }}</td></tr>
		<tr class="total"><td colspan="2" class="bold right">Neto a pagar</td><td class="right bold">{{ $moneda['principal'] }}</td></tr>
		@if ($moneda['equivalente'])
			<tr class="total"><td colspan="2" class="right sub">{{ $moneda['equivalente'] }}</td></tr>
		@endif
	</table>

	<p class="nota">Emitido: {{ \Carbon\Carbon::parse($fecha_emision)->format('d/m/Y') }} | Anticipo imputable a utilidades | Base legal: LOTTT Art. 132.</p>

	@php
		$neto = (float) $bonificacion->neto;
		$monedaLetras = monto_en_letras($neto, $bonificacion->moneda);
		$montoEtiqueta = $bonificacion->moneda === 'USD'
			? 'USD '.formato_monto($neto)
			: 'Bs. '.formato_monto($neto);
	@endphp

	<p class="nota">
		Yo, {{ $empleado->nombre_completo }}, titular de la cédula de identidad Nº {{ $empleado->cedula }},
		he recibido de: {{ $patrono->razon_social }}, la cantidad de: {{ $monedaLetras }}
		({{ $montoEtiqueta }}), por concepto de bonificación de fin de año, a mi entera y cabal satisfacción.-
	</p>

	<div class="firma">
		<p class="firma-label">Recibí conforme,</p>
		<div class="firma-fila">
			<div style="display:inline-block; width:50%; vertical-align:top;">
				<div class="firma-linea">&nbsp;</div>
				<p class="firma-nombre">{{ $empleado->nombre_completo }} <span class="firma-cedula">C.I. {{ $empleado->cedula }}</span></p>
			</div>
			<div style="display:inline-block; vertical-align:top;">
				<div class="huella">
					<div class="cuadro">&nbsp;</div>
					<span>Huella dactilar</span>
				</div>
				<div class="huella">
					<div class="cuadro">&nbsp;</div>
					<span>Huella dactilar</span>
				</div>
			</div>
		</div>
	</div>
</body>
</html>