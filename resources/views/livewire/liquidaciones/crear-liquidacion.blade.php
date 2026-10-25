<div class="p-4 max-w-2xl mx-auto">
	@if ($error)
		<div class="mb-4 p-2 bg-red-50 border border-red-200 text-red-700 text-xs">{{ $error }}</div>
	@endif

	<form wire:submit="guardar">
		<fieldset class="border border-gray-200 p-4 mb-4">
			<legend class="text-xs font-semibold text-gray-600 px-1">Datos de la terminación</legend>

			<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-1">Trabajador</label>
					<select wire:model.live="empleado_id"
						class="w-full border border-gray-300 bg-white px-2 py-1.5 text-sm">
						<option value="0">Seleccionar…</option>
						@foreach ($empleados as $e)
							<option value="{{ $e->id }}">{{ $e->nombre_completo }} ({{ $e->cedula }})</option>
						@endforeach
					</select>
					@error('empleado_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-1">Fecha de egreso</label>
					<input type="date" wire:model.live="fecha_egreso"
						class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<div class="md:col-span-2">
					<label class="block text-xs font-medium text-gray-600 mb-1">Motivo de la terminación</label>
					<select wire:model.live="motivo"
						class="w-full border border-gray-300 bg-white px-2 py-1.5 text-sm">
						<option value="despido_justificado">Despido justificado</option>
						<option value="despido_no_justificado">Despido no justificado convenido</option>
						<option value="retiro_voluntario">Retiro voluntario</option>
						<option value="retiro_justificado">Retiro justificado</option>
						<option value="voluntad_comun">Voluntad común de las partes</option>
						<option value="causa_ajena">Causa ajena a la voluntad de las partes</option>
						<option value="conclusion_contrato">Conclusión de contrato</option>
					</select>
					<p class="text-xs text-gray-400 mt-1">La indemnización (Art. 92 LOTTT) aplica en despido no justificado, retiro justificado o causa ajena a la voluntad.</p>
				</div>
			</div>
		</fieldset>

		@if ($preview)
			<fieldset class="border border-gray-200 p-4 mb-4">
				<legend class="text-xs font-semibold text-gray-600 px-1">Previsualización del cálculo</legend>

				<div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
					<div><span class="text-gray-400">Ingreso:</span> {{ \App\Models\Empleado::find($empleado_id)?->fecha_ingreso?->format('d/m/Y') ?? '—' }}</div>
					<div><span class="text-gray-400">Egreso:</span> {{ \Carbon\Carbon::parse($this->fecha_egreso)->format('d/m/Y') }}</div>
					<div><span class="text-gray-400">Antigüedad:</span> {{ $preview['antiguedad']['anios'] }} años y {{ $preview['antiguedad']['meses'] }} meses</div>
					<div><span class="text-gray-400">Días prestaciones:</span> {{ $preview['dias_prestaciones'] }}</div>
					<div><span class="text-gray-400">Indemnización Art. 92:</span> {{ $preview['aplica_indemnizacion'] ? 'Equivalente a prestaciones' : 'No aplica' }}</div>
					<div><span class="text-gray-400">Sueldo diario normal:</span> {{ formato_monto($preview['salario_diario_normal']) }} {{ $preview['moneda'] }}</div>
					<div><span class="text-gray-400">Salario diario integral:</span> {{ formato_monto($preview['salario_integral_diario']) }} {{ $preview['moneda'] }}</div>
				</div>

				@php $esGarantiaPreview = ($preview['prestaciones_origen'] ?? null) === 'garantia'; @endphp
				<div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
					<div class="border p-2 {{ $esGarantiaPreview ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200' }}">
						<div class="text-gray-500">Art. 142 a+b · garantía</div>
						<div class="font-semibold mt-1">{{ $preview['moneda'] }} {{ formato_monto($preview['garantia_ab']) }}</div>
						<div class="text-gray-400 mt-1">Capital e intereses, antes de anticipos</div>
					</div>
					<div class="border p-2 {{ ! $esGarantiaPreview ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200' }}">
						<div class="text-gray-500">Art. 142 c · 30 días/año</div>
						<div class="font-semibold mt-1">{{ $preview['moneda'] }} {{ formato_monto($preview['literal_c']) }}</div>
						<div class="text-gray-400 mt-1">{{ $preview['dias_prestaciones'] }} días × salario integral</div>
					</div>
					<div class="border border-emerald-400 bg-emerald-50 p-2">
						<div class="text-emerald-700">Se aplica el mayor</div>
						<div class="font-semibold text-emerald-800 mt-1">{{ $esGarantiaPreview ? 'Garantía Art. 142 a+b' : 'Literal c (30 días/año)' }}</div>
						<div class="font-bold text-emerald-800">{{ $preview['moneda'] }} {{ formato_monto($preview['prestaciones_mayor']) }}</div>
						<div class="text-emerald-700 mt-1">Diferencia: {{ formato_monto($preview['prestaciones_diferencia']) }}</div>
					</div>
				</div>

				<div class="mt-4 space-y-1 text-xs">
					<div class="font-medium text-gray-700">Asignaciones ({{ $preview['moneda'] }})</div>
					@foreach ($preview['asignaciones'] as $a)
						<div class="flex justify-between py-0.5">
							<span>{{ $a['concepto'] }}@if ($a['dias'] !== null) — {{ $a['dias'] }} días @endif
								<span class="text-gray-400">
									@if ($a['tipo_salario'] === 'integral')
										(salario integral)
									@elseif ($a['tipo_salario'] === 'normal')
										(salario normal)
									@endif
									@if ($a['salario_diario'] !== null) · sueldo diario {{ formato_monto($a['salario_diario']) }} {{ $preview['moneda'] }} @endif
								</span>
								<span class="text-gray-300 block">{{ $a['base_legal'] }}</span>
							</span>
							<span>{{ formato_monto($a['importe']) }}</span>
						</div>
					@endforeach
					<div class="flex justify-between pt-1 border-t border-gray-200 font-medium">
						<span>Total asignaciones</span><span>{{ formato_monto($preview['total_asignaciones']) }}</span>
					</div>
					<div class="pt-1 font-medium text-gray-700">Deducciones</div>
					@foreach ($preview['deducciones'] as $d)
						<div class="flex justify-between py-0.5">
							<span>{{ $d['concepto'] }}
								<span class="text-gray-300 block">{{ $d['base_legal'] }}</span>
							</span>
							<span class="text-red-600">- {{ formato_monto($d['importe']) }}</span>
						</div>
					@endforeach
					<div class="flex justify-between pt-1 border-t border-gray-200 font-medium">
						<span>Total deducciones</span><span class="text-red-600">- {{ formato_monto($preview['total_deducciones']) }}</span>
					</div>
					<div class="flex justify-between font-semibold">
						<span>Neto a pagar</span><span>{{ formato_monto($preview['neto']) }}</span>
					</div>
				</div>
			</fieldset>
		@elseif ($empleado_id !== 0)
			<div class="mb-4 p-3 bg-gray-50 border border-gray-200 text-xs text-gray-500">
				Espere a que el sistema prepare el cálculo…
			</div>
		@endif

		<div class="flex justify-end gap-2 pt-4 border-t border-gray-200">
			<a href="{{ route('liquidaciones.index') }}"
				class="px-4 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
			<button type="submit" wire:loading.attr="disabled" @if (empty($preview)) disabled @endif
				class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
				<span wire:loading.remove>✓ Guardar</span>
				<span wire:loading>Guardando…</span>
			</button>
		</div>
	</form>
</div>
