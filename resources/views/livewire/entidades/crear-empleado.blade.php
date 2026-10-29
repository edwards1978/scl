<div class="p-4 max-w-2xl mx-auto">
    @if ($exito)
        <div class="bg-green-50 border border-green-200 p-4 mb-4">
            <p class="text-sm text-green-700 font-medium">{{ $exito }}</p>
            <div class="mt-3">
                <a href="{{ route('empleados.index') }}" class="inline-block px-4 py-2 text-sm bg-green-600 text-white hover:bg-green-700">
                    Volver al listado
                </a>
                <button wire:click="$refresh" class="inline-block px-4 py-2 text-sm bg-blue-600 text-white hover:bg-blue-700 ml-2">
                    Registrar otro empleado
                </button>
            </div>
        </div>
    @else
        <h1 class="text-xl font-semibold mb-4">Nuevo Empleado</h1>

        <form wire:submit="guardar" class="space-y-6">

            <fieldset class="bg-white border border-gray-200 p-5">
                <legend class="text-sm font-semibold text-gray-700 px-1">Datos Personales</legend>
                <div class="space-y-3 mt-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Nombres</label>
                            <input type="text" wire:model="nombres" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                            @error('nombres') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Apellidos</label>
                            <input type="text" wire:model="apellidos" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                            @error('apellidos') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">Cédula</label>
                        <input type="text" wire:model="cedula" class="w-full border border-gray-300 px-2 py-1.5 text-sm" placeholder="V-12345678">
                        @error('cedula') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha de Nacimiento <span class="text-gray-400">(opcional)</span></label>
                            <input type="date" wire:model="fecha_nacimiento" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Género</label>
                            <select wire:model="genero" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
                                <option value="">Seleccione...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Teléfono <span class="text-gray-400">(opcional)</span></label>
                            <input type="text" wire:model="telefono" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Correo <span class="text-gray-400">(opcional)</span></label>
                            <input type="email" wire:model="correo" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">Dirección <span class="text-gray-400">(opcional)</span></label>
                        <textarea wire:model="direccion" rows="2" class="w-full border border-gray-300 px-2 py-1.5 text-sm"></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="bg-white border border-gray-200 p-5">
                <legend class="text-sm font-semibold text-gray-700 px-1">Datos Laborales</legend>
                <div class="space-y-3 mt-2">
                    <div class="bg-blue-50 border border-blue-200 px-3 py-2">
                        <label class="block text-xs font-medium text-blue-700 mb-0.5">Patrono</label>
                        <p class="text-sm font-semibold text-blue-800">
                            {{ $patrono->razon_social }}
                            <a href="{{ route('entorno.seleccionar') }}" class="ml-2 text-xs font-medium text-blue-600 underline">Cambiar</a>
                        </p>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha de Ingreso</label>
                            <input type="date" wire:model="fecha_ingreso" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                            @error('fecha_ingreso') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Salario Base mensual ({{ $moneda === 'USD' ? 'USD' : 'Bs.' }})</label>
                            <input type="text" wire:model="salario_base" placeholder="9999.99" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                            @error('salario_base') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Moneda del Sueldo</label>
                            <select wire:model="moneda" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
                                <option value="BS">Bs. (Bolívares)</option>
                                <option value="USD">USD (Dólar)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Cargo <span class="text-gray-400">(opcional)</span></label>
                            <input type="text" wire:model="cargo" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Departamento <span class="text-gray-400">(opcional)</span></label>
                            <input type="text" wire:model="departamento" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Tipo Contrato</label>
                            <select wire:model="tipo_contrato" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
                                <option value="Indeterminado">Indeterminado</option>
                                <option value="Determinado">Determinado</option>
                                <option value="Obra">Obra</option>
                            </select>
                            @error('tipo_contrato') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Tipo Salario</label>
                            <select wire:model="tipo_salario" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
                                <option value="Fijo">Fijo</option>
                                <option value="Variable">Variable</option>
                                <option value="Mixto">Mixto</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Estatus</label>
                            <select wire:model="estatus" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
                                <option value="Activo">Activo</option>
                                <option value="Suspendido">Suspendido</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">Jornada Laboral <span class="text-gray-400">(opcional)</span></label>
                        <input type="text" wire:model="jornada_laboral" placeholder="Ej: Lunes a Viernes 8:00-17:00" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                    </div>
                </div>
            </fieldset>

            <fieldset class="bg-white border border-gray-200 p-5">
                <legend class="text-sm font-semibold text-gray-700 px-1">Capitalización de intereses sobre prestaciones</legend>
                <div class="space-y-3 mt-2">
                    <div class="flex items-start gap-2">
                        <input id="capitaliza_intereses" type="checkbox" wire:model.live="capitaliza_intereses" class="mt-1 border-gray-300">
                        <div>
                            <label for="capitaliza_intereses" class="text-sm text-gray-700">Capitalizar intereses anualmente</label>
                            <p class="text-[11px] text-gray-500">Requiere manifestación escrita del trabajador. Sin esta autorización, los intereses quedan pendientes y no se suman al capital.</p>
                        </div>
                    </div>
                    @if ($capitaliza_intereses)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha de la autorización</label>
                                <input type="date" wire:model="capitalizacion_autorizada_desde" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                                @error('capitalizacion_autorizada_desde') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-0.5">Documento o referencia</label>
                                <input type="text" wire:model="capitalizacion_documento" placeholder="Ej: carta firmada 15/03/2025" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                                @error('capitalizacion_documento') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                </div>
            </fieldset>

            <div class="flex justify-end gap-2">
                <a href="{{ route('empleados.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove>Crear Empleado</span>
                    <span wire:loading>Guardando...</span>
                </button>
            </div>
        </form>
    @endif
</div>
