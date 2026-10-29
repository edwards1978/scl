<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportarEmpleadosRequest;
use App\Http\Requests\StoreEmpleadoRequest;
use App\Http\Requests\UpdateEmpleadoRequest;
use App\Models\BonificacionFinAno;
use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\MovimientoPrestaciones;
use App\Models\Parametro;
use App\Models\Patrono;
use App\Models\PrestacionSocial;
use App\Models\Sueldo;
use App\Models\Vacacion;
use App\Services\EmpleadosImportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmpleadoController extends Controller
{
    public function __construct(
        private readonly EmpleadosImportExportService $importExport = new EmpleadosImportExportService,
    ) {}

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();

        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $query = Empleado::with('patrono')->where('patrono_id', $patrono->id);

        if ($buscar = $request->get('buscar')) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombres', 'like', "%{$buscar}%")
                    ->orWhere('apellidos', 'like', "%{$buscar}%")
                    ->orWhere('cedula', 'like', "%{$buscar}%");
            });
        }

        $empleados = $query->orderBy('nombres')->orderBy('apellidos')->paginate($porPagina);

        $tasaDolar = (float) Parametro::get('tasa_dolar', 0.0);

        return view('empleados.index', compact('empleados', 'patrono', 'tasaDolar'));
    }

    /** Exporta los empleados del patrono actual a OpenDocument Spreadsheet (.ods). */
    public function exportar(Request $request): StreamedResponse
    {
        $patrono = $this->patronoActual();
        $buscar = $request->string('buscar')->trim()->toString();

        $spreadsheet = $this->importExport->exportar($patrono, $buscar !== '' ? $buscar : null);

        $filename = 'empleados-'.(Str::slug($patrono->razon_social) ?: 'patrono')
            .'-'.now()->format('Ymd').'.ods';

        return $this->descargarOds($spreadsheet, $filename);
    }

    /** Descarga una plantilla .ods con los encabezados e instrucciones de importación. */
    public function plantilla(): StreamedResponse
    {
        $spreadsheet = $this->importExport->plantilla($this->patronoActual());

        return $this->descargarOds($spreadsheet, 'plantilla-empleados.ods');
    }

    /** Importa empleados desde un archivo .ods, creando los nuevos y actualizando los ya registrados por cédula. */
    public function importar(ImportarEmpleadosRequest $request): RedirectResponse
    {
        $patrono = $this->patronoActual();

        try {
            $resultado = $this->importExport->importar(
                $request->file('archivo')->getRealPath(),
                $patrono
            );
        } catch (\Throwable $e) {
            return redirect()->route('empleados.index')
                ->with('error', 'No se pudo importar el archivo: '.$e->getMessage());
        }

        $resumen = [];

        if ($resultado['creados'] > 0) {
            $resumen[] = "{$resultado['creados']} empleado(s) creado(s)";
        }

        if ($resultado['actualizados'] > 0) {
            $resumen[] = "{$resultado['actualizados']} empleado(s) actualizado(s)";
        }

        if ($resultado['omitidos'] > 0) {
            $resumen[] = "{$resultado['omitidos']} omitido(s) por cédula repetida en el archivo";
        }

        $mensaje = 'Importación completada: '.($resumen !== [] ? implode(', ', $resumen) : 'sin cambios').'.';

        $redireccion = redirect()->route('empleados.index');

        if ($resultado['errores'] !== [] || $resultado['cedulas_omitidas'] !== [] || $resultado['cedulas_actualizadas'] !== []) {
            $redireccion->with('importacion_detalle', [
                'errores' => array_slice($resultado['errores'], 0, 20),
                'errores_total' => count($resultado['errores']),
                'omitidos' => array_slice($resultado['cedulas_omitidas'], 0, 20),
                'omitidos_total' => count($resultado['cedulas_omitidas']),
                'actualizados' => array_slice($resultado['cedulas_actualizadas'], 0, 20),
                'actualizados_total' => count($resultado['cedulas_actualizadas']),
            ]);
        }

        if ($resultado['creados'] === 0 && $resultado['actualizados'] === 0 && $resultado['errores'] === [] && $resultado['omitidos'] === 0) {
            return $redireccion->with('info', 'El archivo no contiene filas de datos para importar.');
        }

        if ($resultado['errores'] !== [] && $resultado['creados'] === 0 && $resultado['actualizados'] === 0) {
            return $redireccion->with('error', $mensaje);
        }

        if ($resultado['errores'] !== [] || $resultado['omitidos'] > 0) {
            return $redireccion->with('info', $mensaje);
        }

        return $redireccion->with('success', $mensaje);
    }

    private function descargarOds(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Ods($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.oasis.opendocument.spreadsheet',
        ]);
    }

    public function create(): View
    {
        $patrono = $this->patronoActual();

        return view('empleados.create', compact('patrono'));
    }

    public function store(StoreEmpleadoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['patrono_id'] = (int) session('patrono_id');
        $data['salario_inicial'] = $data['salario_inicial'] ?? $data['salario_base'];

        $empleado = Empleado::create($data);

        return redirect()->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombre_completo} creado exitosamente.");
    }

    public function edit(Empleado $empleado): View
    {
        $this->verificarPertenece($empleado);

        $patrono = $this->patronoActual();
        $patronos = Patrono::where('estado', 'Activo')->orderBy('razon_social')->get();

        return view('empleados.edit', compact('empleado', 'patrono', 'patronos'));
    }

    public function update(UpdateEmpleadoRequest $request, Empleado $empleado): RedirectResponse
    {
        $this->verificarPertenece($empleado);

        $data = $request->validated();
        $data['patrono_id'] = $empleado->patrono_id;

        $empleado->update($data);

        return redirect()->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombre_completo} actualizado exitosamente.");
    }

    public function destroy(Empleado $empleado): RedirectResponse
    {
        $this->verificarPertenece($empleado);

        $tieneHistorial = Vacacion::where('empleado_id', $empleado->id)->exists()
            || Sueldo::where('empleado_id', $empleado->id)->exists()
            || CambioSalario::where('empleado_id', $empleado->id)->exists()
            || MovimientoPrestaciones::where('empleado_id', $empleado->id)->exists()
            || PrestacionSocial::where('empleado_id', $empleado->id)->exists()
            || Liquidacion::where('empleado_id', $empleado->id)->exists()
            || BonificacionFinAno::where('empleado_id', $empleado->id)->exists();

        if ($tieneHistorial) {
            return redirect()->route('empleados.index')
                ->with('error', 'No se puede eliminar un trabajador con historial laboral. Use el estatus Inactivo.');
        }

        $nombre = $empleado->nombre_completo;
        $empleado->delete();

        return redirect()->route('empleados.index')
            ->with('success', "Empleado {$nombre} eliminado exitosamente.");
    }

    public function cambiarPatrono(Request $request, Empleado $empleado): RedirectResponse
    {
        $this->verificarPertenece($empleado);

        $request->validate([
            'nuevo_patrono_id' => 'required|exists:patronos,id',
        ]);

        $nuevoPatrono = Patrono::findOrFail($request->integer('nuevo_patrono_id'));

        if ($nuevoPatrono->estado !== 'Activo') {
            return back()->withErrors(['nuevo_patrono_id' => 'El patrono seleccionado no está activo.']);
        }

        $existe = Empleado::where('patrono_id', $nuevoPatrono->id)
            ->where('cedula', $empleado->cedula)
            ->where('id', '!=', $empleado->id)
            ->exists();

        if ($existe) {
            return back()->withErrors(['nuevo_patrono_id' => "La cédula {$empleado->cedula} ya está registrada en el patrono seleccionado."]);
        }

        $patronoAnterior = (int) $empleado->patrono_id;

        DB::transaction(function () use ($empleado, $nuevoPatrono, $patronoAnterior) {
            $empleado->update(['patrono_id' => $nuevoPatrono->id]);

            Vacacion::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);

            MovimientoPrestaciones::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);

            PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);

            Liquidacion::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);

            BonificacionFinAno::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);
        });

        return redirect()->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombre_completo} transferido a {$nuevoPatrono->razon_social} exitosamente.");
    }

    private function verificarPertenece(Empleado $empleado): void
    {
        abort_if((int) $empleado->patrono_id !== (int) session('patrono_id'), 404);
    }
}
