@props(['paginador', 'clave'])

@php
    $opciones = [5, 10, 15, 25, 50, 100];
    $claveLocal = 'scl.paginacion.'.$clave.'.'.(auth()->id() ?? 0);
@endphp

<div class="mt-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2 text-xs text-gray-500">
        <span>Filas por página</span>
        <select
            data-page-size="{{ $clave }}"
            data-key="{{ $claveLocal }}"
            class="border border-gray-300 px-1.5 py-0.5 text-xs bg-white"
        >
            @foreach ($opciones as $opcion)
                <option value="{{ $opcion }}" {{ (int) $paginador->perPage() === $opcion ? 'selected' : '' }}>{{ $opcion }}</option>
            @endforeach
        </select>
    </div>

    <div class="text-xs text-gray-500">{{ $paginador->total() }} registro(s)</div>

    <div>{{ $paginador->appends(request()->except('page'))->links() }}</div>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-page-size]').forEach(function (select) {
                const clave = select.dataset.key;
                const opciones = [5, 10, 15, 25, 50, 100];
                const parametros = new URLSearchParams(window.location.search);
                const actual = Number(parametros.get('porPagina'));

                if (! parametros.has('porPagina')) {
                    const guardado = Number(localStorage.getItem(clave));

                    if (opciones.includes(guardado)) {
                        const url = new window.URL(window.location.href);
                        url.searchParams.set('porPagina', String(guardado));
                        window.location.replace(url.toString());
                        return;
                    }
                } else if (opciones.includes(actual)) {
                    localStorage.setItem(clave, String(actual));
                }

                select.addEventListener('change', function () {
                    localStorage.setItem(clave, this.value);

                    const url = new window.URL(window.location.href);
                    url.searchParams.set('porPagina', this.value);
                    url.searchParams.delete('page');
                    window.location.href = url.toString();
                });
            });
        });
    </script>
@endonce
