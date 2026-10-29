<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Comprobante de pago — {{ $movimiento->empleado->nombre_completo }}</title>
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
		.watermark { position: fixed; top: 42%; left: 14%; font-size: 90px; color: rgba(220, 38, 38, .12); font-weight: bold; transform: rotate(-25deg); }
	</style>
</head>
<body>
	@if ($movimiento->estatus === 'anulado')
		<div class="watermark">ANULADO</div>
	@endif

	<div class="header">
		<h1>{{ $movimiento->patrono->razon_social }}</h1>
		<h2>RIF {{ $movimiento->patrono->rif }}</h2>
		<p class="muted" style="margin:4px 0 0;">Comprobante de pago de {{ strtolower($movimiento->labelTipo()) }} de prestaciones sociales</p>
	</div>

	<table class="meta">
		<tr>
			<td class="label">Comprobante N.º</td>
			<td><strong>MOV-{{ str_pad((string) $movimiento->id, 6, '0', STR_PAD_LEFT) }}</strong></td>
			<td class="label">Fecha de emisión</td>
			<td>{{ ($movimiento->fecha_comprobante ?? now())->format('d/m/Y') }}</td>
		</tr>
		<tr>
			<td class="label">Trabajador</td>
			<td>{{ $movimiento->empleado->nombre_completo }}</td>
			<td class="label">C.I.</td>
			<td>{{ $movimiento->empleado->cedula }}</td>
		</tr>
		<tr>
			<td class="label">Fecha del movimiento</td>
			<td>{{ $movimiento->fecha->format('d/m/Y') }}</td>
			<td class="label">Estatus</td>
			<td>{{ ucfirst($movimiento->estatus) }}</td>
		</tr>
	</table>

	<div class="box">
		<p class="muted" style="margin:0;">Monto recibido</p>
		<p class="amount">{{ $movimiento->moneda }} {{ formato_monto((float) $movimiento->monto) }}</p>
		<p style="margin:0;">
			Por concepto de <strong>{{ strtolower($movimiento->labelTipo()) }}</strong>
			@if ($movimiento->motivo)
				— motivo: {{ $movimiento->labelMotivo() }}
			@endif
			@if ($movimiento->concepto)
				— {{ $movimiento->concepto }}
			@endif
		</p>
	</div>

	@if (! $movimiento->esAnticipo())
		<table class="table">
			<tr><th>Plazo</th><th>Interés anual</th><th>Cuota</th><th>Saldo pendiente</th><th>Intereses pagados</th></tr>
			<tr>
				<td>{{ $movimiento->plazo_meses !== null ? $movimiento->plazo_meses.' meses' : '—' }}</td>
				<td>{{ $movimiento->interes_anual !== null ? formato_monto((float) $movimiento->interes_anual, 2).'%' : '—' }}</td>
				<td>{{ $movimiento->cuota !== null ? $movimiento->moneda.' '.formato_monto((float) $movimiento->cuota) : '—' }}</td>
				<td>{{ $movimiento->moneda }} {{ formato_monto((float) $movimiento->saldo) }}</td>
				<td>{{ $movimiento->moneda }} {{ formato_monto($movimiento->totalInteresesPagados()) }}</td>
			</tr>
		</table>
	@endif

	<p style="margin-top:18px; line-height:1.5;">
		Yo, <strong>{{ $movimiento->empleado->nombre_completo }}</strong>, titular de la cédula de identidad
		<strong>{{ $movimiento->empleado->cedula }}</strong>, declaro haber recibido de
		<strong>{{ $movimiento->patrono->razon_social }}</strong> la cantidad de
		<strong>{{ $movimiento->moneda }} {{ formato_monto((float) $movimiento->monto) }}</strong>
		por el concepto indicado en este comprobante.
	</p>

<!-- 	<p class="note">
		Este comprobante se emite como constancia del movimiento registrado en SCL. No sustituye los documentos
		contables, bancarios o legales que correspondan. Generado el {{ now()->format('d/m/Y H:i') }}.
	</p>
 -->
	<table class="signatures">
		<tr>
			<td>
				<div class="sign-line"></div>
				<div class="sign-name">{{ $movimiento->empleado->nombre_completo }}</div>
				<div class="sign-role">Trabajador · C.I. {{ $movimiento->empleado->cedula }}</div>
			</td>
			<td>
				<div class="sign-line"></div>
<!-- 				<div class="sign-name">{{ $movimiento->patrono->representante_legal ?: "Firma Autorizada" }}</div> -->
				<div class="sign-name">Firma Autorizada</div>
				<div class="sign-role">Por {{ $movimiento->patrono->razon_social }}</div>
			</td>
		</tr>
	</table>
</body>
</html>
