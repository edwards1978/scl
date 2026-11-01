@extends('layouts.app')

@section('content')
@php
	$moneda = $empleado->moneda;
	$exportParams = array_filter(['empleadoId' => $empleado->id, 'desde' => $desde, 'hasta' => $hasta, 'incluir_anteriores' => $incluirAnteriores ? 1 : 0], fn ($v) => $v !== null);
	$usuarioId = (int) (auth()->id() ?? 0);
	$uiKey = 'scl.prestaciones.ui.'.$usuarioId;
	$modalidadDeposito = $empleado->patrono?->deposito_tipo ?? 'contabilidad';
	$infoMensajes = [];

	if ($modalidadDeposito !== 'contabilidad') {
		$infoMensajes[] = [
			'categoria' => 'Intereses',
			'texto' => 'Los intereses de esta garantía son calculados por '.($modalidadDeposito === 'fideicomiso' ? 'la entidad bancaria del fideicomiso' : 'el Fondo Nacional de Prestaciones Sociales').'. SCL no calcula intereses en esta modalidad.',
		];
	} elseif ($empleado->capitaliza_intereses) {
		$infoMensajes[] = [
			'categoria' => 'Intereses',
			'texto' => 'Capitalización de intereses autorizada por escrito desde '.(optional($empleado->capitalizacion_autorizada_desde)->format('d/m/Y') ?? '—').'.'.($empleado->capitalizacion_documento ? ' Referencia: '.$empleado->capitalizacion_documento.'.' : ''),
		];
	} else {
		$infoMensajes[] = [
			'categoria' => 'Intereses',
			'texto' => 'Intereses no capitalizados: se acumulan como pendientes y no generan intereses sobre intereses. Para capitalizarlos se requiere autorización escrita del trabajador.',
		];
	}

	if ($proyeccionHasta) {
		$infoMensajes[] = [
			'categoria' => 'Proyección',
			'texto' => 'Proyección guardada hasta '.$proyeccionHasta.'. Para reproyectar, use la opción del listado de prestaciones, que se aplica a todos los trabajadores activos.',
			'eliminar_proyeccion' => true,
		];
	}

	if ($incluyeProyeccion) {
		$infoMensajes[] = [
			'categoria' => 'Período',
			'texto' => 'Incluye períodos proyectados hasta '.$proyeccionHasta.'. Las filas están marcadas como proyección. Son cifras informativas calculadas con la última tasa y el último salario; no representan un monto causado.',
		];
	}

	$infoMensajes[] = [
		'categoria' => 'Tasa',
		'texto' => 'Tasa interés %: tasa anual promedio entre activa y pasiva del BCV, aplicada al cálculo del interés del período (informativa).',
	];
@endphp
<div
	class="p-4 mx-4"
	x-data="{
		uiKey: @js($uiKey),
		abierto: { info: false, filtros: true, totales: true },
		init() {
			try {
				const guardado = JSON.parse(localStorage.getItem(this.uiKey) || '{}');
				this.abierto.info = guardado.info === true;
				this.abierto.filtros = guardado.filtros !== false;
				this.abierto.totales = guardado.totales !== false;
			} catch (e) {}
		},
		alternar(seccion) {
			this.abierto[seccion] = ! this.abierto[seccion];
			try {
				const guardado = JSON.parse(localStorage.getItem(this.uiKey) || '{}');
				Object.assign(guardado, this.abierto);
				localStorage.setItem(this.uiKey, JSON.stringify(guardado));
			} catch (e) {}
		}
	}"
>
	<div class="flex items-center justify-between mb-1 gap-2">
		<h1 class="text-xl font-semibold">Estado de Cuenta de Prestaciones Sociales</h1>
		<div class="flex items-center gap-2">
			<button type="button" @click="alternar('info')" :aria-expanded="abierto.info"
				class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">
				Información
				<span class="inline-flex min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white">{{ count($infoMensajes) }}</span>
			</button>
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

	<div x-show="abierto.info" x-transition class="mb-4 border border-gray-200 bg-white">
		<div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
			<h2 class="text-sm font-semibold text-gray-800">Información del estado de cuenta</h2>
			<button type="button" @click="alternar('info')" class="text-lg leading-none text-gray-400 hover:text-gray-700" aria-label="Cerrar información">×</button>
		</div>
		<div class="divide-y divide-gray-100">
			@foreach ($infoMensajes as $mensaje)
				<div class="flex flex-col gap-1 px-4 py-3 text-xs text-gray-600 sm:flex-row sm:gap-3">
					<span class="inline-flex h-fit shrink-0 items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-600">{{ $mensaje['categoria'] }}</span>
					<div class="flex-1">
						<p>{{ $mensaje['texto'] }}</p>
						@if (! empty($mensaje['eliminar_proyeccion']) && $empleado->estaActivo())
							<form method="POST" action="{{ route('prestaciones.proyeccion.destroy', $empleado->id) }}" class="mt-2">
								@csrf @method('DELETE')
								<button type="submit" class="px-2 py-1 text-xs border border-red-300 text-red-600 hover:bg-red-50">Eliminar proyección</button>
							</form>
						@endif
					</div>
				</div>
			@endforeach
		</div>
	</div>

	<form method="GET" action="{{ route('prestaciones.show', $empleado->id) }}" class="bg-white border border-gray-200 p-3 mb-4">
		<div class="flex flex-wrap items-center justify-between gap-2">
			<h2 class="text-sm font-semibold text-gray-800">Filtros del período</h2>
			<button type="button" @click="alternar('filtros')" :aria-expanded="abierto.filtros"
				class="px-2 py-1 text-xs border border-gray-300 text-gray-600 hover:bg-gray-50">
				<span x-text="abierto.filtros ? 'Ocultar' : 'Mostrar'"></span>
			</button>
		</div>
		<div x-show="abierto.filtros" class="mt-3">
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
			<a id="limpiar-filtros" href="{{ route('prestaciones.show', $empleado->id) }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Limpiar</a>
		</div>
		<p class="text-[11px] text-gray-400 mt-1">Formato AAAA-MM · Ejemplo: 2025-01 a 2025-06</p>
		<p class="text-xs text-gray-500 mt-2">
			Mostrando {{ $periodosFiltrados }} de {{ $totalPeriodos }} período(s).
			@if ($hayRango)
				Período: {{ $periodoDesde }} a {{ $periodoHasta }} · {{ $incluirAnteriores ? 'incluye saldos anteriores' : 'solo el período' }}.
			@else
				Período completo: {{ $periodoDesde }} a {{ $periodoHasta }}.
			@endif
		</p>
		</div>
		<p x-show="!abierto.filtros" class="mt-2 text-xs text-gray-500">
			Mostrando {{ $periodosFiltrados }} de {{ $totalPeriodos }} período(s) ·
			{{ $periodoDesde }} a {{ $periodoHasta }}
			@if ($hayRango)
				· {{ $incluirAnteriores ? 'incluye saldos anteriores' : 'solo el período' }}
			@endif
		</p>
	</form>


	<div class="bg-white border border-gray-200 p-4 mb-4">
		<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
			<h2 class="text-sm font-semibold text-gray-800">Totales del período</h2>
			<div class="flex items-center gap-2">
				<div class="text-xs text-gray-500">
					Período: <span class="font-medium text-gray-700">{{ $periodoDesde }} a {{ $periodoHasta }}</span>
					@if ($hayRango)
						· {{ $incluirAnteriores ? 'incluye saldos anteriores' : 'no incluye saldos anteriores' }}
					@endif
				</div>
				<button type="button" @click="alternar('totales')" :aria-expanded="abierto.totales"
					class="px-2 py-1 text-xs border border-gray-300 text-gray-600 hover:bg-gray-50">
					<span x-text="abierto.totales ? 'Contraer' : 'Expandir'"></span>
				</button>
			</div>
		</div>
		<div x-show="abierto.totales" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
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
		<p x-show="abierto.totales" class="text-xs text-gray-500 mt-3">
			Los totales de movimientos son siempre del período seleccionado. El saldo inicial incluye los períodos anteriores solo si el checkbox está marcado; si no, empieza en cero.
			El neto del período es la variación real del saldo (capital más interés pendiente); los pagos de intereses que exceden el interés pendiente y el capital disponible no se aplican al saldo.
			Intereses pagados del período: {{ $moneda }} {{ formato_monto($totalInteresesPagados) }}.
			La tabla inferior muestra los saldos acumulados reales de cada mes.
		</p>
		<p x-show="!abierto.totales" class="text-xs text-gray-500 mt-1">
			Saldo final: <strong>{{ $moneda }} {{ formato_monto($saldoFinalRango) }}</strong>
			· Neto: <strong>{{ $moneda }} {{ formato_monto($netoPeriodo) }}</strong>
			· Interés pendiente: <strong>{{ $moneda }} {{ formato_monto($interesPendiente) }}</strong>
		</p>
	</div>

	<div class="mb-2 flex flex-wrap items-center justify-between gap-2">
		<p class="text-xs text-gray-500">{{ $totalCuenta }} período(s) en el rango filtrado.</p>
		<div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
			<div class="flex items-center gap-1">
				<a href="{{ $cuenta->previousPageUrl() ? $cuenta->previousPageUrl().'#tabla-prestaciones' : '#' }}"
					class="px-2 py-0.5 border border-gray-300 {{ $cuenta->onFirstPage() ? 'pointer-events-none opacity-40' : 'hover:bg-gray-50' }}">←</a>
				<span>Página {{ $cuenta->currentPage() }} de {{ $cuenta->lastPage() }}</span>
				<a href="{{ $cuenta->nextPageUrl() ? $cuenta->nextPageUrl().'#tabla-prestaciones' : '#' }}"
					class="px-2 py-0.5 border border-gray-300 {{ $cuenta->hasMorePages() ? 'hover:bg-gray-50' : 'pointer-events-none opacity-40' }}">→</a>
			</div>
			<span>Filas por página</span>
			<select onchange="const u = new window.URL(window.location.href); u.searchParams.set('porPagina', this.value); u.searchParams.delete('page'); window.location.href = u.toString();"
				class="border border-gray-300 px-1.5 py-0.5 text-xs bg-white">
				@foreach ([10, 25, 50, 100] as $n)
					<option value="{{ $n }}" {{ $porPagina === $n ? 'selected' : '' }}>{{ $n }}</option>
				@endforeach
			</select>
		</div>
	</div>

	<div id="tabla-prestaciones" class="bg-white border border-gray-200 overflow-x-auto mb-4">
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
						<td class="px-3 py-2 text-center text-gray-500">{{ ($cuenta->currentPage() - 1) * $cuenta->perPage() + $loop->iteration }}</td>
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
						<td class="px-3 py-2 text-right text-red-600 whitespace-nowrap">{{ $c->anticipos > 0 ? '- '.formato_monto((float) $c->anticipos) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ formato_monto((float) $c->capital_periodo) }}</td>
						<td class="px-3 py-2 text-right">{{ $c->tasa_interes !== null ? formato_monto((float) $c->tasa_interes, 4) : '—' }}</td>
						<td class="px-3 py-2 text-right">
							{{ $c->interes_mes > 0 ? formato_monto((float) $c->interes_mes) : '—' }}
							@if ((float) ($c->interes_capitalizado ?? 0) > 0)
								<div class="text-[10px] text-amber-600">cap. {{ formato_monto((float) $c->interes_capitalizado) }}</div>
							@endif
						</td>
						<td class="px-3 py-2 text-right text-red-600 whitespace-nowrap">{{ (float) ($c->interes_pagado ?? 0) > 0 ? '- '.formato_monto((float) $c->interes_pagado) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ (float) $c->interes_pendiente_periodo > 0 ? formato_monto((float) $c->interes_pendiente_periodo) : '—' }}</td>
						<td class="px-3 py-2 text-right font-medium">{{ formato_monto((float) $c->saldo_final_periodo) }}</td>
						<td class="px-2 py-2 text-center">
							@if ($empleado->estaActivo())
								<div
									x-data="{
										abierto: false,
										estilo: '',
										alternar() {
											this.abierto = ! this.abierto;
											if (! this.abierto) return;
											this.$nextTick(() => this.posicionar());
										},
										posicionar() {
											const boton = this.$refs.boton.getBoundingClientRect();
											const menu = this.$refs.menu;
											const ancho = menu.offsetWidth || 220;
											const alto = menu.offsetHeight || 150;
											const margen = 8;
											let izquierda = Math.min(boton.left, window.innerWidth - ancho - margen);
											izquierda = Math.max(margen, izquierda);
											let arriba = boton.bottom + 4;
											if (arriba + alto > window.innerHeight - margen) {
												arriba = boton.top - alto - 4;
											}
											arriba = Math.max(margen, arriba);
											this.estilo = `position: fixed; left: ${izquierda}px; top: ${arriba}px; z-index: 9999;`;
										}
									}"
									@click.outside="abierto = false"
									@keydown.escape.window="abierto = false"
									@resize.window="abierto = false"
									@scroll.window="abierto = false"
									class="inline-block text-left"
								>
									<button x-ref="boton" type="button" @click="alternar()" :aria-expanded="abierto" aria-haspopup="true"
										class="px-2 py-1 text-gray-500 hover:text-gray-800 hover:bg-gray-100"
										title="Acciones del período" aria-label="Acciones del período">
										<span class="text-lg leading-none">…</span>
									</button>
									<div x-ref="menu" x-show="abierto" :style="estilo" style="display: none" class="flex flex-col border border-gray-200 bg-white p-1 shadow-lg">
										<a href="{{ route('movimientos-prestaciones.create', array_filter([
											'empleado_id' => $empleado->id,
											'tipo' => 'anticipo',
											'fecha' => $c->periodo_fin?->format('Y-m-d'),
											'fecha_comprobante' => now()->format('Y-m-d'),
											'monto' => $c->capital_periodo,
											'moneda' => $moneda,
										], fn ($valor) => $valor !== null && $valor !== '')) }}"
											class="whitespace-nowrap px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
											Anticipo
										</a>
										@if ($modalidadDeposito === 'contabilidad')
											<a href="{{ route('intereses-prestaciones.create', array_filter([
												'empleado_id' => $empleado->id,
												'fecha' => $c->periodo_fin?->format('Y-m-d'),
												'fecha_comprobante' => now()->format('Y-m-d'),
												'monto' => $c->interes_pendiente_periodo,
												'moneda' => $moneda,
											], fn ($valor) => $valor !== null && $valor !== '')) }}"
												class="whitespace-nowrap px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
												Pago de intereses
											</a>
										@endif
										@if ($c->es_proyeccion)
											<form method="POST" action="{{ route('prestaciones.proyeccion.periodo.destroy', [$empleado->id, $c->id]) }}"
												onsubmit="return confirm('¿Eliminar el período proyectado {{ $c->periodo }}?')">
												@csrf @method('DELETE')
												<button type="submit" class="whitespace-nowrap px-2 py-1 text-left text-xs text-red-600 hover:bg-red-50">
													Eliminar proyección
												</button>
											</form>
										@endif
									</div>
								</div>
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

	<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
		<p class="text-xs text-gray-500">Página {{ $cuenta->currentPage() }} de {{ $cuenta->lastPage() }}</p>
		{{ $cuenta->links() }}
	</div>

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

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function () {
		const empleadoId = @json((string) $empleado->id);
		const usuarioId = @json((string) $usuarioId);
		const filtrosKey = `scl.prestaciones.filtros.${usuarioId}.${empleadoId}`;
		const paginaKey = `scl.prestaciones.pagina.${usuarioId}.${empleadoId}`;
		const uiKey = `scl.prestaciones.ui.${usuarioId}`;

		const leer = (clave, defecto = null) => {
			try {
				const valor = localStorage.getItem(clave);
				return valor ? JSON.parse(valor) : defecto;
			} catch (e) {
				return defecto;
			}
		};

		const params = new URLSearchParams(window.location.search);
		const tieneFiltros = ['desde', 'hasta', 'incluir_anteriores'].some((clave) => params.has(clave));
		const tienePagina = params.has('page');
		const tienePorPagina = params.has('porPagina');
		const valoresIncluir = params.getAll('incluir_anteriores');
		const incluirAnteriores = valoresIncluir.length
			? valoresIncluir[valoresIncluir.length - 1] !== '0'
			: true;
		const filtrosGuardados = leer(filtrosKey);
		const paginaGuardada = Number.parseInt(localStorage.getItem(paginaKey) || '0', 10);
		const uiGuardada = leer(uiKey, {});
		const url = new window.URL(window.location.href);
		let cambiarUrl = false;

		if (! tieneFiltros && filtrosGuardados && typeof filtrosGuardados === 'object') {
			['desde', 'hasta'].forEach((clave) => {
				if (filtrosGuardados[clave]) {
					url.searchParams.set(clave, filtrosGuardados[clave]);
					cambiarUrl = true;
				}
			});

			if (typeof filtrosGuardados.incluir_anteriores === 'boolean') {
				url.searchParams.set('incluir_anteriores', filtrosGuardados.incluir_anteriores ? '1' : '0');
				cambiarUrl = true;
			}
		}

		if (! tienePagina && ! tieneFiltros && ! tienePorPagina && paginaGuardada > 1) {
			url.searchParams.set('page', String(paginaGuardada));
			cambiarUrl = true;
		}

		if (! tienePorPagina && [10, 25, 50, 100].includes(Number(uiGuardada.porPagina))) {
			url.searchParams.set('porPagina', String(uiGuardada.porPagina));
			cambiarUrl = true;
		}

		if (cambiarUrl) {
			window.location.replace(url.toString());
			return;
		}

		if (tieneFiltros) {
			const filtrosAplicados = params.has('desde') || params.has('hasta') || ! incluirAnteriores;

			if (filtrosAplicados) {
				localStorage.setItem(filtrosKey, JSON.stringify({
					desde: params.get('desde') || '',
					hasta: params.get('hasta') || '',
					incluir_anteriores: incluirAnteriores,
				}));
			} else {
				localStorage.removeItem(filtrosKey);
			}
		}

		if (tienePagina) {
			localStorage.setItem(paginaKey, String(Math.max(1, Number.parseInt(params.get('page') || '1', 10) || 1)));
		} else if (tieneFiltros || tienePorPagina) {
			localStorage.setItem(paginaKey, '1');
		}

		if (tienePorPagina && [10, 25, 50, 100].includes(Number(params.get('porPagina')))) {
			const ui = leer(uiKey, {});
			ui.porPagina = Number(params.get('porPagina'));
			localStorage.setItem(uiKey, JSON.stringify(ui));
		}

		const limpiar = document.getElementById('limpiar-filtros');
		if (limpiar) {
			limpiar.addEventListener('click', function () {
				localStorage.removeItem(filtrosKey);
				localStorage.removeItem(paginaKey);
			});
		}
	});
</script>
@endpush
