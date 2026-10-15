<?php
/**
 * SpaceFlow — Servicio de gestión de equipos (portátiles).
 *
 * Concentra las reglas de negocio del catálogo de equipos: unicidad
 * del código, estados permitidos y la protección del historial de
 * reservas (un equipo con reservas no se elimina: se da de baja).
 *
 * @package SpaceFlow\Services
 */

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AccesoDenegadoException;
use App\Exceptions\EquipoNoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Models\Equipo;
use App\Repositories\EquipoRepository;
use App\Repositories\ReservaRepository;

class EquipoService
{
    public function __construct(
        private readonly EquipoRepository $repositorio,
        private readonly ReservaRepository $reservas,
        private readonly Logger $log
    ) {
    }

    /* ---------------------------------------------------------------
     * Consultas
     * --------------------------------------------------------------- */

    /**
     * Lista equipos con filtros y paginación (solo administradores).
     *
     * @return array{equipos: array<int, array<string, mixed>>, total: int, pagina: int}
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function listarParaAdministrador(string $busqueda, string $estado, int $pagina): array
    {
        $this->requerirAdministrador('listar equipos');

        $porPagina = 10;
        $total = $this->repositorio->contarFiltrados($busqueda, $estado);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $totalPaginas);

        return [
            'equipos' => $this->repositorio->listar($busqueda, $estado, $porPagina, ($pagina - 1) * $porPagina),
            'total'   => $total,
            'pagina'  => $pagina,
        ];
    }

    /**
     * Busca un equipo por id o lanza la excepción correspondiente.
     *
     * @param int $id Identificador.
     * @return array<string, mixed>
     * @throws EquipoNoEncontradoException Si no existe.
     */
    public function obtener(int $id): array
    {
        $equipo = $this->repositorio->buscarPorId($id);
        if ($equipo === null) {
            throw new EquipoNoEncontradoException((string)$id);
        }
        return $equipo;
    }

    /**
     * Equipos reservables (disponibles o prestados) para el selector
     * del formulario de reserva. Excluye mantenimiento y baja.
     *
     * @param array<int, int> $idsOcupados Ids ocupados en la franja elegida.
     * @return array<int, array<string, mixed>>
     */
    public function listarReservables(array $idsOcupados = []): array
    {
        // Los ocupados se filtran en PHP: la lista es corta (catálogo pequeño)
        // y así la consulta sigue siendo un solo prepared statement estático.
        $filas = $this->repositorio->listarReservables();
        return array_values(array_filter(
            $filas,
            static fn(array $fila): bool => !in_array((int)$fila['id'], $idsOcupados, true)
        ));
    }

    /**
     * Estadísticas del catálogo para el dashboard.
     *
     * @return array<string, int>
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function estadisticas(): array
    {
        $this->requerirAdministrador('consultar estadísticas de equipos');
        return $this->repositorio->contarPorEstado();
    }

    /* ---------------------------------------------------------------
     * Escritura
     * --------------------------------------------------------------- */

    /**
     * Crea un equipo nuevo tras validar los datos.
     *
     * @param array<string, mixed> $datos Datos crudos del formulario.
     * @return int Id del equipo creado.
     * @throws ValidacionException Si algún dato no cumple las reglas.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function crear(array $datos): int
    {
        $this->requerirAdministrador('crear equipos');
        $limpios = $this->validar($datos);

        if ($this->repositorio->codigoExiste($limpios['codigo'])) {
            throw new ValidacionException('Ya existe un equipo con el código ' . $limpios['codigo'] . '.');
        }

        $id = $this->repositorio->crear($limpios);
        $this->log->info("Registró el equipo '{$limpios['codigo']}' (id #{$id}).");
        return $id;
    }

    /**
     * Actualiza los datos de un equipo existente.
     *
     * @param int $id Identificador a editar.
     * @param array<string, mixed> $datos Datos del formulario.
     * @return void
     * @throws ValidacionException Si los datos no son válidos.
     * @throws EquipoNoEncontradoException Si no existe.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function editar(int $id, array $datos): void
    {
        $this->requerirAdministrador('editar equipos');
        $existentes = $this->obtener($id);
        $limpios = $this->validar($datos, $id);

        if ($this->repositorio->codigoExiste($limpios['codigo'], $id)) {
            throw new ValidacionException('Otro equipo ya usa el código ' . $limpios['codigo'] . '.');
        }

        // Un equipo en préstamo no puede pasar a "baja" sin registrar
        // antes la devolución: se perdería el rastro del equipo físico.
        if ($limpios['estado'] === Equipo::ESTADO_BAJA
            && $existentes['estado'] === Equipo::ESTADO_PRESTADO) {
            throw new ValidacionException(
                'El equipo está prestado. Registra la devolución antes de darlo de baja.'
            );
        }

        $this->repositorio->actualizar($id, $limpios);
        $this->log->info("Editó el equipo '{$limpios['codigo']}' (id #{$id}).");
    }

    /**
     * Elimina un equipo del catálogo.
     *
     * Si el equipo ya tiene reservas registradas NO se borra el registro
     * (se perdería el historial de préstamos): en su lugar se marca como
     * "dado de baja". Solo los equipos sin historial se eliminan.
     *
     * @param int $id Identificador.
     * @return string 'eliminado' | 'baja'.
     * @throws EquipoNoEncontradoException Si no existe.
     * @throws ValidacionException Si ya estaba dado de baja.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function eliminar(int $id): string
    {
        $this->requerirAdministrador('eliminar equipos');
        $equipo = $this->obtener($id);

        if ($equipo['estado'] === Equipo::ESTADO_BAJA) {
            throw new ValidacionException('El equipo ya estaba dado de baja.');
        }

        // Un equipo físicamente prestado no puede borrarse ni darse de
        // baja: primero hay que registrar la devolución (coherente con
        // la regla de edición).
        if ($equipo['estado'] === Equipo::ESTADO_PRESTADO) {
            throw new ValidacionException(
                'El equipo está prestado. Registra la devolución antes de eliminarlo.'
            );
        }

        $conReservas = $this->reservas->contarPorEquipo($id) > 0;

        if ($conReservas) {
            // Borrado lógico: conserva el historial de préstamos.
            $this->repositorio->cambiarEstado($id, Equipo::ESTADO_BAJA);
            $this->log->info(
                "Dio de baja el equipo '{$equipo['codigo']}' (id #{$id}); conserva historial de reservas."
            );
            return 'baja';
        }

        $this->repositorio->eliminar($id);
        $this->log->info("Eliminó el equipo '{$equipo['codigo']}' (id #{$id}); sin historial de reservas.");
        return 'eliminado';
    }

    /* ---------------------------------------------------------------
     * Validaciones internas
     * --------------------------------------------------------------- */

    /**
     * Valida y normaliza los datos de un formulario de equipo.
     *
     * @param array<string, mixed> $datos Datos crudos.
     * @param int|null $excluirId Id a excluir de unicidad (ediciones).
     * @return array<string, mixed> Datos limpios.
     * @throws ValidacionException Con todos los errores encontrados.
     */
    private function validar(array $datos, ?int $excluirId = null): array
    {
        $errores = [];

        $codigo = strtoupper(trim((string)($datos['codigo'] ?? '')));
        if (!preg_match('/^[A-Z0-9-]{3,30}$/', $codigo)) {
            $errores[] = 'El código debe tener entre 3 y 30 caracteres (letras, números o guiones).';
        }

        $nombre = trim((string)($datos['nombre'] ?? ''));
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre del equipo debe tener entre 3 y 100 caracteres.';
        }

        $marca = trim((string)($datos['marca'] ?? ''));
        if ($marca !== '' && mb_strlen($marca) > 60) {
            $errores[] = 'La marca no puede superar los 60 caracteres.';
        }

        $estadosValidos = [
            Equipo::ESTADO_DISPONIBLE,
            Equipo::ESTADO_PRESTADO,
            Equipo::ESTADO_MANTENIMIENTO,
            Equipo::ESTADO_BAJA,
        ];
        $estado = (string)($datos['estado'] ?? Equipo::ESTADO_DISPONIBLE);
        if (!in_array($estado, $estadosValidos, true)) {
            $errores[] = 'Selecciona un estado válido.';
        }

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'marca'  => $marca !== '' ? $marca : null,
            'estado' => $estado,
        ];
    }

    /**
     * Comprueba que la sesión actual pertenece a un administrador.
     *
     * @param string $accion Descripción de la operación (para el log).
     * @return void
     * @throws AccesoDenegadoException Si no está autorizado.
     */
    private function requerirAdministrador(string $accion): void
    {
        $rolId = $_SESSION['usuario']['rol_id'] ?? 0;
        if ($rolId !== 1) {
            $correo = $_SESSION['usuario']['correo'] ?? 'invitado';
            $this->log->advertencia(
                "Intento de acceso no autorizado: '{$correo}' intentó {$accion}."
            );
            throw new AccesoDenegadoException('Tu rol no tiene permiso para realizar esta operación.');
        }
    }
}
