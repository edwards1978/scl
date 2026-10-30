@props(['status'])

@if ($status)
    <x-alerta tipo="success" :mensaje="$status" class="mb-4" />
@endif
