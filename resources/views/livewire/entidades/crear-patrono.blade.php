<div class="p-4 max-w-2xl mx-auto">
    @if ($exito)
        <div class="bg-green-50 border border-green-200 p-4 mb-4">
            <p class="text-sm text-green-700 font-medium">{{ $exito }}</p>
            <div class="mt-3">
                <a href="{{ route('patronos.index') }}" class="inline-block px-4 py-2 text-sm bg-green-600 text-white hover:bg-green-700">
                    Volver al listado
                </a>
                <button wire:click="$refresh" class="inline-block px-4 py-2 text-sm bg-blue-600 text-white hover:bg-blue-700 ml-2">
                    Registrar otro patrono
                </button>
            </div>
        </div>
    @else
        <h1 class="text-xl font-semibold mb-4">Nuevo Patrono</h1>

        <form wire:submit="guardar" class="space-y-6">

            <fieldset class="bg-white border border-gray-200 p-5">
                <legend class="text-sm font-semibold text-gray-700 px-1">Datos del Patrono</legend>
                <div class="space-y-3 mt-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">R.I.F.</label>
                        <input type="text" wire:model="rif" class="w-full border border-gray-300 px-2 py-1.5 text-sm" placeholder="J-12345678-9">
                        @error('rif') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">Razón Social</label>
                        <input type="text" wire:model="razon_social" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                        @error('razon_social') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">Nombre Comercial <span class="text-gray-400">(opcional)</span></label>
                        <input type="text" wire:model="nombre_comercial" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">Registro Mercantil <span class="text-gray-400">(opcional)</span></label>
                        <input type="text" wire:model="registro_mercantil" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">Dirección Fiscal</label>
                        <textarea wire:model="direccion_fiscal" rows="2" class="w-full border border-gray-300 px-2 py-1.5 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-0.5">Representante Legal</label>
                        <input type="text" wire:model="representante_legal" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Teléfono</label>
                            <input type="text" wire:model="telefono" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-0.5">Correo</label>
                            <input type="email" wire:model="correo" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
                        </div>
                    </div>
                </div>
            </fieldset>

            <div class="flex justify-end gap-2">
                <a href="{{ route('patronos.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove>Crear Patrono</span>
                    <span wire:loading>Guardando...</span>
                </button>
            </div>
        </form>
    @endif
</div>
