<div class="p-4 max-w-2xl mx-auto">
	@if ($error)
		<div class="mb-4 p-2 bg-red-50 border border-red-200 text-red-700 text-xs">{{ $error }}</div>
	@endif

	<form wire:submit="guardar">
		<fieldset class="border border-gray-200 p-4 mb-4">
			<legend class="text-xs font-semibold text-gray-600 px-1">Datos del periodo</legend>

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
					<label class="block text-xs font-medium text-gray-600 mb-1">Fecha inicio (disfrute)</label>
					<input type="date" wire:model.live="fecha_inicio"
						class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					<p class="text-xs text-gray-400 mt-1">Sugerida automáticamente: fin del período trabajado (día en que nace el derecho a disfrutar). Puedes cambiarla.</p>
				</div>
			</div>
		</fieldset>

		@if ($preview)
			<fieldset class="border border-gray-200 p-4 mb-4">
				<legend class="text-xs font-semibold text-gray-600 px-1">Previsualización del cálculo</legend>

				<div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
					<div><span class="text-gray-400">Periodo trabajado:</span> {{ \Carbon\Carbon::parse($preview['periodo_desde'])->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($preview['periodo_hasta'])->format('d/m/Y') }}</div>
					<div><span class="text-gray-400">Año de servicio:</span> {{ $preview['anio_servicio'] }}</div>
					<div><span class="text-gray-400">Disfrute:</span> {{ \Carbon\Carbon::parse($preview['fecha_inicio_real'])->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($preview['fecha_fin_real'])->format('d/m/Y') }}</div>
					<div><span class="text-gray-400">Reintegro:</span> {{ \Carbon\Carbon::parse($preview['fecha_reintegro'])->format('d/m/Y') }}</div>
					<div><span class="text-gray-400">Días de vacaciones:</span> {{ $preview['dias_vacaciones'] }} hábiles ({{ $preview['dias_vacaciones_base'] }} + {{ $preview['dias_vacaciones_adic'] }} adicionales)</div>
					<div><span class="text-gray-400">Bono vacacional:</span> {{ $preview['dias_bono'] }} días de salario ({{ $preview['dias_bono_base'] }} + {{ $preview['dias_bono_adic'] }})</div>
					<div><span class="text-gray-400">Feriados en periodo:</span> {{ count($preview['feriados_en_periodo']) }}</div>
					<div><span class="text-gray-400">Días de descanso:</span> {{ $preview['dias_descanso'] }}</div>
					<div><span class="text-gray-400">Días feriados:</span> {{ $preview['dias_feriados'] }}</div>
					<div><span class="text-gray-400">Salario normal:</span> {{ $preview['salario_normal'] }} {{ $preview['moneda'] }}</div>
				</div>

				<div class="mt-4 space-y-1 text-xs">
					<div class="font-medium text-gray-700">Asignaciones ({{ $preview['moneda'] }})</div>
					@foreach ($preview['asignaciones'] as $a)
						<div class="flex justify-between py-0.5">
							<span>{{ $a['concepto'] }} — {{ $a['dias'] }} días</span>
							<span>{{ formato_monto($a['importe']) }}</span>
						</div>
					@endforeach
					@if (count($preview['deducciones']))
						<div class="pt-1 font-medium text-gray-700">Deducciones</div>
						@foreach ($preview['deducciones'] as $d)
							<div class="flex justify-between py-0.5">
								<span title="{{ $d['base_legal'] }}">{{ $d['concepto'] }} ({{ $d['porcentaje'] * 100 }}%)</span>
								<span class="text-red-600">- {{ formato_monto($d['importe']) }}</span>
							</div>
						@endforeach
					@endif
					<div class="flex justify-between pt-1 border-t border-gray-200 font-medium">
						<span>Total asignaciones</span><span>{{ formato_monto($preview['total_asignaciones']) }}</span>
					</div>
					<div class="flex justify-between">
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
			<a href="{{ route('vacaciones.index') }}"
				class="px-4 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
			<button type="submit" wire:loading.attr="disabled" @if (empty($preview)) disabled @endif
				class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
				<span wire:loading.remove>✓ Guardar</span>
				<span wire:loading>Guardando…</span>
			</button>
		</div>
	</form>
</div>