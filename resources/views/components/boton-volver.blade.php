@props([
    'fallback',
    'label' => '← Volver',
    'force' => false,
])

<a
    href="{{ $fallback }}"
    data-volver-atras
    @if ($force) data-volver-forzar="1" @endif
    {{ $attributes->merge(['class' => 'inline-flex items-center px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50']) }}
>{{ $label }}</a>

@once
    <script>
        document.addEventListener('click', function (evento) {
            const enlace = evento.target.closest('[data-volver-atras]');

            if (! enlace || evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
                return;
            }

            if (enlace.dataset.volverForzar === '1') {
                return;
            }

            let referrer = null;

            try {
                referrer = document.referrer ? new window.URL(document.referrer) : null;
            } catch (e) {
                return;
            }

            if (! referrer || referrer.origin !== window.location.origin || window.history.length <= 1) {
                return;
            }

            // Después de guardar, el referrer puede ser el formulario de alta o
            // edición. En ese caso se usa el destino de respaldo, no el formulario.
            if (/\/(crear|create|editar|edit)(\/|$)/.test(referrer.pathname)) {
                return;
            }

            evento.preventDefault();
            window.history.back();
        });
    </script>
@endonce
