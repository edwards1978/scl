<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HistorialEstado;
use App\Models\Patrono;
use App\Models\VacacionesRecibo;
use App\Services\PapeleraService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Papelera de registros con borrado lógico.
 *
 * Solo los administradores pueden verla, restaurar y purgar. La definición de
 * cada tipo y la lógica de purga viven en `PapeleraService`.
 */
class PapeleraController extends Controller
{
    public function __construct(
        private PapeleraService $papelera = new PapeleraService,
    ) {}

    public function index(Request $request): View
    {
        $tipos = $this->papelera->tipos();
        $tipoActivo = (string) $request->query('tipo', 'empleados');

        if (! array_key_exists($tipoActivo, $tipos)) {
            $tipoActivo = 'empleados';
        }

        $config = $tipos[$tipoActivo];
        $modelo = $config['modelo'];

        $buscar = trim((string) $request->query('buscar', ''));
        $patronoId = $request->integer('patrono_id') ?: null;
        $tabla = (new $modelo)->getTable();
        $filtraPorPatrono = Schema::hasColumn($tabla, 'patrono_id');

        $query = $modelo::onlyTrashed();

        if ($filtraPorPatrono && $patronoId !== null) {
            $query->where('patrono_id', $patronoId);
        }

        if ($buscar !== '') {
            $columnas = $config['busqueda'] ?? [];
            $relacionesBusqueda = $config['busqueda_relacion'] ?? [];

            $query->where(function ($subquery) use ($buscar, $columnas, $relacionesBusqueda): void {
                foreach ($columnas as $columna) {
                    $subquery->orWhere($columna, 'like', "%{$buscar}%");
                }

                foreach ($relacionesBusqueda as $relacion => $campos) {
                    $subquery->orWhereHas($relacion, function ($relacionQuery) use ($buscar, $campos): void {
                        $relacionQuery->where(function ($camposQuery) use ($buscar, $campos): void {
                            foreach ($campos as $campo) {
                                $camposQuery->orWhere($campo, 'like', "%{$buscar}%");
                            }
                        });
                    });
                }
            });
        }

        $relaciones = $config['relaciones'];

        if (method_exists($modelo, 'historialEstados')) {
            $relaciones[] = 'historialEstados.user';
        }

        $registros = $query
            ->with($relaciones)
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        $conteos = [];

        foreach ($tipos as $clave => $tipo) {
            $clase = $tipo['modelo'];
            $conteos[$clave] = $clase::onlyTrashed()->count();
        }

        $patronos = Patrono::orderBy('razon_social')->get(['id', 'razon_social']);
        $eventosPapelera = HistorialEstado::with('user')
            ->where('campo', 'papelera')
            ->latest('id')
            ->limit(30)
            ->get();
        $retencionDias = $this->papelera->retencionDias();

        return view('papelera.index', compact(
            'tipos',
            'tipoActivo',
            'config',
            'registros',
            'conteos',
            'buscar',
            'patronoId',
            'filtraPorPatrono',
            'patronos',
            'eventosPapelera',
            'retencionDias',
        ));
    }

    public function restaurar(string $tipo, int $id): RedirectResponse
    {
        Gate::authorize('administrar');

        $tipos = $this->papelera->tipos();

        abort_unless(array_key_exists($tipo, $tipos), 404);

        $config = $tipos[$tipo];
        $clase = $config['modelo'];

        /** @var Model $registro */
        $registro = $clase::onlyTrashed()->findOrFail($id);

        if ($error = $config['conflicto']($registro)) {
            return back()->with('error', $error);
        }

        if ($error = $config['depende']($registro)) {
            return back()->with('error', $error);
        }

        try {
            DB::transaction(function () use ($registro, $config, $tipo): void {
                $registro->restore();

                if ($tipo === 'vacaciones') {
                    VacacionesRecibo::onlyTrashed()
                        ->where('vacacion_id', $registro->getKey())
                        ->restore();
                }

                $config['despues']($registro);
            });
        } catch (QueryException $exception) {
            report($exception);

            return back()->with('error', 'No se puede restaurar: otro registro activo usa los mismos datos únicos. Revise el registro antes de intentarlo de nuevo.');
        }

        return redirect()->route('papelera.index', ['tipo' => $tipo])
            ->with('success', 'Registro restaurado correctamente.');
    }

    public function purgar(string $tipo, int $id): RedirectResponse
    {
        Gate::authorize('administrar');

        $tipos = $this->papelera->tipos();

        abort_unless(array_key_exists($tipo, $tipos), 404);

        /** @var Model $registro */
        $registro = $tipos[$tipo]['modelo']::onlyTrashed()->findOrFail($id);

        if ($error = $this->papelera->purgar($tipo, $registro)) {
            return back()->with('error', $error);
        }

        return redirect()->route('papelera.index', ['tipo' => $tipo])
            ->with('success', 'Registro purgado definitivamente.');
    }
}
