@foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $tipo => $variante)
    @if (session($tipo))
        <x-alerta :tipo="$variante" :mensaje="session($tipo)" class="mx-4 mt-3" />
    @endif
@endforeach

@if ($errors->any())
    <x-alerta tipo="error" :duracion="12000" class="mx-4 mt-3">
        <strong>Revisa los datos:</strong>
        <ul class="mt-1 list-disc list-inside space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alerta>
@endif
