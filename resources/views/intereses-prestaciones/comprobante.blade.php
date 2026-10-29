<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Comprobante de pago de intereses — {{ $pago->empleado->nombre_completo }}</title>
	<style>
		@page { margin: 32px 38px; }
		body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #111827; font-size: 11px; }
		.header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 18px; }
		.header h1 { font-size: 17px; margin: 0 0 3px; }
		.header h2 { font-size: 11px; margin: 0; font-weight: normal; }
		.muted { color: #6b7280; }
		.meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
		.meta td { padding: 4px 8px 4px 0; vertical-align: top; }
		.meta .label { color: #6b7280; width: 130px; }
		.box { border: 1px solid #d1d5db; padding: 14px; margin: 14px 0; }
		.amount { font-size: 20px; font-weight: bold; margin: 4px 0; }
		.table { width: 100%; border-collapse: collapse; margin-top: 10px; }
		.table th, .table td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: left; }
		.table th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
		.right { text-align: right; }
		.note { color: #6b7280; font-size: 9px; margin-top: 14px; line-height: 1.45; }
		.signatures { width: 100%; margin-top: 48px; border-collapse: collapse; }
		.signatures td { width: 50%; padding-top: 28px; text-align: center; vertical-align: bottom; }
		.sign-line { border-top: 1px solid #111827; width: 80%; margin: 0 auto 5px; }
		.sign-name { font-weight: bold; font-size: 10px; }
		.sign-role { color: #6b7280; font-size: 9px; }
	</style>
</head>
<body>
	<div class="header">
		<h1>{{ $pago->patrono->razon_social }}</h1>
		<h2>RIF {{ $pago->patrono->rif }}</h2>
		<p class="muted" style="margin:4px 0 0;">Comprobante de pago de intereses sobre prestaciones sociales</p>
	</div>

	<table class="meta">
		<tr>
			<td class="label">Comprobante N.º</td>
			<td><strong>{{ $pago->numeroComprobante() }}</strong></td>
			<td class="label">Fecha de emisión</td>
			<td>{{ ($pago->fecha_comprobante ?? now())->format('d/m/Y') }}</td>
		</tr>
		<tr>
			<td class="label">Trabajador</td>
			<td>{{ $pago->empleado->nombre_completo }}</td>
			<td class="label">C.I.</td>
			<td>{{ $pago->empleado->cedula }}</td>
		</tr>
		<tr>
			<td class="label">Fecha del movimiento</td>
			<td>{{ $pago->fecha->format('d/m/Y') }}</td>
			<td class="label">Moneda</td>
			<td>{{ $pago->moneda }}</td>
		</tr>
		@if ($pago->comprobante)
			<tr>
				<td class="label">Referencia</td>
				<td colspan="3">{{ $pago->comprobante }}</td>
			</tr>
		@endif
	</table>

	<div class="box">
		<p class="muted" style="margin:0;">Monto pagado</p>
		<p class="amount">{{ $pago->moneda }} {{ formato_monto((float) $pago->monto) }}</p>
		<p style="margin:0;">
			Por concepto de <strong>intereses sobre prestaciones sociales</strong> (Art. 143 LOTTT)
			@if ($pago->observacion)
				— {{ $pago->observacion }}
			@endif
		</p>
	</div>

<!-- 	@php
		$equivalente = $pago->montoEnOtraMoneda();
	@endphp
	@if ($equivalente !== null)
		<table class="table">
			<tr><th>Tasa de cambio BCV</th><th>Equivalente en {{ $pago->monedaEquivalente() }}</th></tr>
			<tr>
				<td>{{ formato_monto((float) $pago->tasa_cambio, 4) }}</td>
				<td>{{ $pago->monedaEquivalente() }} {{ formato_monto($equivalente) }}</td>
			</tr>
		</table>
	@endif -->

	<p style="margin-top:18px; line-height:1.5;">
		Yo, <strong>{{ $pago->empleado->nombre_completo }}</strong>, titular de la cédula de identidad
		<strong>{{ $pago->empleado->cedula }}</strong>, declaro haber recibido de
		<strong>{{ $pago->patrono->razon_social }}</strong> la cantidad de
		<strong>{{ $pago->moneda }} {{ formato_monto((float) $pago->monto) }}</strong>
		por concepto de intereses sobre prestaciones sociales.
	</p>

	<table class="signatures">
		<tr>
			<td>
				<div class="sign-line"></div>
				<div class="sign-name">{{ $pago->empleado->nombre_completo }}</div>
				<div class="sign-role">Trabajador · C.I. {{ $pago->empleado->cedula }}</div>
			</td>
			<td>
				<div class="sign-line"></div>
<!-- 				<div class="sign-name">{{ $pago->patrono->representante_legal ?: "Firma Autorizada" }}</div> -->
					<div class="sign-name">Firma Autorizada</div>
				<div class="sign-role">Por {{ $pago->patrono->razon_social }}</div>
			</td>
		</tr>
	</table>
</body>
</html>
