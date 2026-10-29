<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Liquidación de Prestaciones – {{ $empleado->nombre_completo }}</title>
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
		<h2 class="h2">RIF {{ $patrono->rif }}</h2>
		<p class="sub">Recibo de Liquidación de Prestaciones Sociales y otros conceptos laborales por Terminación de la Relación Laboral</p>
	</div>

	<table class="meta">
		<tr><td class="bold">Trabajador</td><td>{{ $empleado->nombre_completo }}</td>
			<td class="bold">Fecha de ingreso</td><td>{{ $liquidacion->fecha_ingreso->format('d/m/Y') }}</td></tr>
		<tr><td class="bold">C.I.</td><td>{{ $empleado->cedula }}</td>
			<td class="bold">Fecha de egreso</td><td>{{ $liquidacion->fecha_egreso->format('d/m/Y') }}</td></tr>
		<tr><td class="bold">Motivo</td><td>{{ $liquidacion->labelMotivo() }}</td>
			<td class="bold">Antigüedad</td><td>{{ $liquidacion->anios_servicio }} años y {{ $liquidacion->meses_fraccion }} meses</td></tr>
		<tr><td class="bold">Salario diario normal</td><td>{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $liquidacion->salario_diario_normal) }}</td>
			<td class="bold">Salario diario integral</td><td>{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $liquidacion->salario_integral_diario) }}</td></tr>
		<tr><td class="bold">Días de prestaciones</td><td>{{ $liquidacion->dias_prestaciones }}</td>
			<td class="bold">Indemnización Art. 92</td><td>{{ $liquidacion->esDobleIndemnizacion() ? 'Convenido' : 'No aplica' }}</td></tr>
	</table>

	@if ($liquidacion->garantia_ab !== null && $liquidacion->literal_c !== null)
		@php
			$esGarantia = $liquidacion->prestaciones_origen === 'garantia';
			$origenLabel = $esGarantia ? 'Garantía Art. 142 a+b' : 'Literal c (30 días por año)';
		@endphp
		<table>
			<tr><th colspan="4">Comparativo Art. 142 LOTTT — se aplica el mayor</th></tr>
			<tr>
				<td class="bold">Art. 142 literales a+b<br><span class="sub">Garantía acumulada</span></td>
				<td class="right">{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $liquidacion->garantia_ab) }}</td>
				<td class="bold">Art. 142 literal c<br><span class="sub">30 días por año</span></td>
				<td class="right">{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $liquidacion->literal_c) }}</td>
			</tr>
			<tr class="total">
				<td class="bold">Se aplica</td>
				<td colspan="3" class="right bold">{{ $origenLabel }} — {{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $liquidacion->prestaciones_mayor) }} (diferencia {{ formato_monto((float) $liquidacion->prestaciones_diferencia) }})</td>
			</tr>
		</table>
	@else
		<p class="nota">Comparativo Art. 142 a+b / literal c no registrado para esta liquidación.</p>
	@endif

	<table>
		<tr><th>Concepto (base legal)</th><th class="right">Días</th><th class="right">Sueldo diario</th><th>Importe</th></tr>
		@foreach ($asignaciones as $c)
			<tr>
				<td>{{ $c->concepto }} <span class="sub">({{ $c->base_legal }})</span>
					@if ($c->tipo_salario === 'integral') <span class="sub">[salario integral]</span>
					@elseif ($c->tipo_salario === 'normal') <span class="sub">[salario normal]</span> @endif
				</td>
				<td class="right">{{ $c->dias ?? '—' }}</td>
				<td class="right">{{ $c->salario_diario !== null ? formato_monto((float) $c->salario_diario) : '—' }}</td>
				<!-- <td class="right">{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $c->importe) }}</td> -->
				<td class="right">{{ formato_monto((float) $c->importe) }}</td>
			</tr>
		@endforeach
		<tr class="total"><td colspan="3" class="bold right">Total asignaciones</td><td class="right bold">{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto($total_asignaciones) }}</td></tr>
		<tr><td colspan="4" class="bold">Deducciones</td></tr>
		@forelse ($deducciones as $d)
			<tr>
				<td>{{ $d->concepto }} @if ($d->base_legal) <span class="sub">({{ $d->base_legal }})</span> @endif</td>
				<td class="right">{{ $d->dias ?? '—' }}</td>
				<td class="right">{{ $d->salario_diario !== null ? formato_monto((float) $d->salario_diario) : '—' }}</td>
				<!-- <td class="right">-{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $d->importe) }}</td> -->
				<td class="right">-{{ formato_monto((float) $d->importe) }}</td>
			</tr>
		@empty
			<tr><td colspan="4" class="text-gray-400">Sin deducciones</td></tr>
		@endforelse
		<tr class="total"><td colspan="3" class="bold right">Total deducciones</td><td class="right bold">-{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto($total_deducciones) }}</td></tr>
		<tr class="total"><td colspan="3" class="bold right">Neto a pagar</td><td class="right bold">{{ $liquidacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $liquidacion->neto) }}</td></tr>
	</table>

	<p class="nota">Emitido: {{ $fecha_emision->format('d/m/Y') }} | Base legal: Arts. 142, 143, 92 LOTTT.</p>

	@php
		$neto = (float) $liquidacion->neto;
		$monedaLetras = monto_en_letras($neto, $liquidacion->moneda);
		$montoEtiqueta = $liquidacion->moneda === 'USD'
			? 'USD '.formato_monto($neto)
			: 'Bs. '.formato_monto($neto);
	@endphp

	<p class="nota">
		Yo, {{ $empleado->nombre_completo }}, titular de la cédula de identidad Nº {{ $empleado->cedula }},
		he recibido de: {{ $patrono->razon_social }}, la cantidad de: {{ $monedaLetras }}
		({{ $montoEtiqueta }}), por concepto de liquidación de prestaciones sociales y otros conceptos laborales, a mi entera y cabal satisfacción.-
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
