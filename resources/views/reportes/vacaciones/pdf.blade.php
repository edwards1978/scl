<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Reporte de Vacaciones – {{ $patrono->razon_social }}</title>
	<style>
		body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 11px; color: #111; }
		.header { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 12px; }
		.h1 { font-size: 16px; font-weight: bold; margin: 0; }
		.sub { font-size: 10px; color: #555; }
		table { width: 100%; border-collapse: collapse; margin-top: 8px; }
		th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; font-size: 10px; }
		th { background: #eee; }
		.right { text-align: right; }
		.center { text-align: center; }
		.bold { font-weight: bold; }
		.total td { border-top: 2px solid #111; background: #f7f7f7; font-weight: bold; }
		.stats td { font-size: 10px; }
		.nota { margin-top: 10px; font-size: 8px; color: #666; }
		.page_footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8px; color: #666; border-top: 1px solid #ccc; padding-top: 3px; }
		.page_number { text-align: right; }
	</style>
</head>
<body>
	<div class="page_footer">
		<table style="border:0; margin:0;">
			<tr>
				<td style="border:0;">SCL — Sistema de Cálculos Laborales</td>
				<td class="right page_number" style="border:0;">Página {PAGE_NUM} de {PAGE_COUNT}</td>
			</tr>
		</table>
	</div>

	<div class="header">
		<h1 class="h1">SCL — Sistema de Cálculos Laborales</h1>
		<p class="sub">Reporte de Vacaciones y Bono Vacacional (LOTTT)</p>
		<!-- <p class="sub">Patrono: <span class="bold">{{ $patrono->razon_social }}</span> — RIF {{ $patrono->rif }} — Emitido: {{ now()->format('d/m/Y H:i') }}</p> -->
	</div>

	<table class="stats">
		<tr class="total">
			<td>Registros: {{ number_format($estadisticas['totalRegistros'], 0, ',', '.') }}</td>
			<td>Días vacaciones: {{ number_format($estadisticas['totalDiasVacaciones'], 0, ',', '.') }}</td>
			<td>Días bono: {{ number_format($estadisticas['totalDiasBono'], 0, ',', '.') }}</td>
			<td>Asignaciones: {{ formato_monto($estadisticas['totalAsignaciones']) }}</td>
			<td>Deducciones: {{ formato_monto($estadisticas['totalDeducciones']) }}</td>
			<td>Neto: {{ formato_monto($estadisticas['totalNeto']) }}</td>
		</tr>
	</table>

	<table>
		<thead>
			<tr>
				<th>Trabajador</th>
				<th>Cédula</th>
				<th>Periodo trabajado</th>
				<th>Disfrute</th>
				<th>Reintegro</th>
				<th class="center">Vacac.</th>
				<th class="center">Bono</th>
				<th class="right">Salario</th>
				<th class="right">Neto</th>
				<th>Calc.</th>
				<th>Vac.</th>
			</tr>
		</thead>
		<tbody>
			@forelse ($vacaciones as $v)
				<tr>
					<td>{{ $v['trabajador'] }}</td>
					<td>{{ $v['cedula'] }}</td>
					<td>{{ $v['periodo_trabajado'] }}</td>
					<td>{{ $v['disfrute'] }}</td>
					<td>{{ $v['reintegro'] }}</td>
					<td class="center">{{ $v['dias_vacaciones'] }}</td>
					<td class="center">{{ $v['dias_bono'] }}</td>
					<td class="right">{{ $v['moneda'] === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto($v['salario_normal']) }}</td>
					<td class="right">{{ $v['neto'] !== null ? ($v['moneda'] === 'USD' ? 'USD' : 'Bs.').' '.formato_monto($v['neto']) : '—' }}</td>
					<td>{{ $v['estatus_calculo'] }}</td>
					<td>{{ $v['estatus_vacacion'] }}</td>
				</tr>
			@empty
				<tr><td colspan="11" class="center">Sin registros para los filtros seleccionados.</td></tr>
			@endforelse
		</tbody>
	</table>

	<p class="nota">Base legal: LOTTT Art. 190 y 192. Reporte generado por SCL — Sistema de Cálculos Laborales.</p>
</body>
</html>