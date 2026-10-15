<?php
/**
 * SpaceFlow — Servicio de reservas de equipos (biblioteca).
 *
 * Reglas de negocio implementadas aquí (siempre en el servidor):
 *  - La biblioteca atiende de 6:00 a. m. a 6:00 p. m. en bloques
 *    completos de una hora.
 *  - Duración mínima 1 hora y máxima MAX_HORAS_PRESTAMO (6).
 *  - Antelación máxima DIAS_ANTELACION_MAX (7 días) y nunca en el pasado.
 *  - Las reservas del mismo día deben terminar después de la hora actual.
 *  - Un equipo no puede tener dos reservas que se superpongan (la
 *    validación incluye el estado "prestamo", que también ocupa).
 *  - Un usuario no puede tener dos reservas superpuestas (no puede
 *    usar dos portátiles a la vez).
 *  - Equipos en mantenimiento o dados de baja no son reservables.
 *  - Entrega y devolución las registra SIEMPRE el administrador; el
 *    estudiante solo puede cancelar sus propias reservas activas.
 *  - Las reservas "activa" no recogidas se marcan vencidas de forma
 *    perezosa (en cada consulta) al pasar la hora de fin.
 *
 * @package SpaceFlow\Services
 */

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AccesoDenegadoException;
use App\Exceptions\ReservaInvalidaException;
use App\Exceptions\SolapamientoException;
use App\Repositories\EquipoRepository;
use App\Repositories\ReservaRepository;

class ReservaService
{
    public function __construct(
        private readonly ReservaRepository $reservas,
        private readonly EquipoRepository $equipos,
        private readonly Logger $log
    ) {
    }

    /* ---------------------------------------------------------------
     * Consultas
     * --------------------------------------------------------------- */

    /**
     * Lista reservas con búsqueda, filtros y paginación.
     *
     * @param int|null $soloUsuarioId null = todos (administrador);
     *                                entero = solo las de ese usuario.
     * @param string $estado Filtro por estado ('' = todos).
     * @param string $fecha Filtro por fecha YYYY-MM-DD ('' = todas).
     * @param int $pagina Página solicitada (se acota al rango válido).
     * @param string $busqueda Texto parcial de usuario o de equipo.
     * @return array{reservas: array<int, array<string, mixed>>, total: int, pagina: int}
     * @throws AccesoDenegadoException Si un usuario intenta ver reservas ajenas.
     */
    public function listar(
        ?int $soloUsuarioId,
        string $estado,
        string $fecha,
        int $pagina,
        string $busqueda = ''
    ): array {
        if ($soloUsuarioId === null) {
            $this->requerirAdministrador('listar reservas de todos los usuarios');
        }

        $this->marcarVencidas();

        $estadosValidos = ['activa', 'prestamo', 'entregada', 'cancelada', 'vencida'];
        $estado = in_array($estado, $estadosValidos, true) ? $estado : '';
        if ($fecha !== '' && \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha)?->format('Y-m-d') !== $fecha) {
            $fecha = '';
        }
        $busqueda = mb_substr(trim($busqueda), 0, 80);

        $porPagina = 10;
        $total = $this->reservas->contarFiltrados($soloUsuarioId, $estado, $fecha, $busqueda);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $totalPaginas);

        return [
            'reservas' => $this->reservas->listar(
                $soloUsuarioId,
                $estado,
                $fecha,
                $porPagina,
                ($pagina - 1) * $porPagina,
                $busqueda
            ),
            'total'   => $total,
            'pagina'  => $pagina,
        ];
    }

    /**
     * Busca una reserva por id (con JOIN de usuario y equipo).
     *
     * @param int $id Identificador.
     * @return array<string, mixed>
     * @throws ReservaInvalidaException Si no existe.
     */
    public function obtener(int $id): array
    {
        $reserva = $this->reservas->buscarPorId($id);
        if ($reserva === null) {
            throw new ReservaInvalidaException('La reserva solicitada no existe.');
        }
        return $reserva;
    }

    /**
     * Próximas reservas (hoy en adelante) de un usuario para el dashboard.
     *
     * @param int $usuarioId Usuario.
     * @param int $limite Cantidad máxima a devolver.
     * @return array<int, array<string, mixed>>
     */
    public function proximasDeUsuario(int $usuarioId, int $limite = 4): array
    {
        $this->marcarVencidas();
        return $this->reservas->proximasDeUsuario($usuarioId, $limite);
    }

    /**
     * Reservas activas de hoy de un usuario (para las notificaciones).
     *
     * @param int $usuarioId Usuario.
     * @return array<int, array<string, mixed>>
     */
    public function activasHoyDeUsuario(int $usuarioId): array
    {
        $this->marcarVencidas();
        return $this->reservas->activasHoyDeUsuario($usuarioId);
    }

    /**
     * Panel del administrador: reservas que terminan en los próximos
     * minutos y préstamos que ya se pasaron de la hora.
     *
     * @param int $minutos Horizonte de aviso (30 por defecto).
     * @return array{vencer: array<int, array<string, mixed>>, atrasados: array<int, array<string, mixed>>}
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function panelVencimientos(int $minutos = MINUTOS_AVISO_TEMPRANO): array
    {
        $this->requerirAdministrador('consultar el panel de vencimientos');
        $this->marcarVencidas();

        return [
            'vencer'    => $this->reservas->proximasAVencer($minutos, date('Y-m-d'), date('H:i:s')),
            'atrasados' => $this->reservas->prestamosAtrasados(date('Y-m-d'), date('H:i:s')),
        ];
    }

    /**
     * Estadísticas de reservas para los KPI del administrador.
     *
     * @return array<string, int> Totales por estado más 'hoy' y 'vencer'.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function estadisticas(): array
    {
        $this->requerirAdministrador('consultar estadísticas de reservas');
        $this->marcarVencidas();

        $conteos = $this->reservas->contarPorEstado();
        $conteos['hoy'] = $this->reservas->contarActivasHoy(date('Y-m-d'));
        $conteos['vencer'] = count(
            $this->reservas->proximasAVencer(MINUTOS_AVISO_TEMPRANO, date('Y-m-d'), date('H:i:s'))
        );
        return $conteos;
    }

    /**
     * Equipos realmente libres para una franja horaria concreta.
     *
     * Devuelve los equipos reservables menos los que ya tienen una
     * reserva que se superpone en esa fecha y horario.
     *
     * @param string $fecha Fecha YYYY-MM-DD.
     * @param string $horaInicio Inicio HH:MM.
     * @param string $horaFin Fin HH:MM.
     * @return array<int, array<string, mixed>> Equipos disponibles.
     * @throws ReservaInvalidaException Si la fecha u hora no son válidas.
     */
    public function disponibilidad(string $fecha, string $horaInicio, string $horaFin): array
    {
        $this->validarFranja($fecha, $horaInicio, $horaFin, false);
        $ocupados = $this->reservas->equiposOcupados($fecha, $horaInicio . ':00', $horaFin . ':00');
        return array_values(array_filter(
            $this->equipos->listarReservables(),
            static fn(array $equipo): bool => !in_array((int)$equipo['id'], $ocupados, true)
        ));
    }

    /**
     * Estadísticas combinadas de equipos y reservas (dashboard).
     *
     * @return array{equipos: array<string, int>, reservas: array<string, int>}
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function estadisticasDashboard(): array
    {
        $this->requerirAdministrador('consultar estadísticas de la biblioteca');
        $this->marcarVencidas();

        return [
            'equipos'  => $this->equipos->contarPorEstado(),
            'reservas' => $this->estadisticas(),
        ];
    }

    /* ---------------------------------------------------------------
     * Escritura
     * --------------------------------------------------------------- */

    /**
     * Crea una reserva tras validar todas las reglas de negocio.
     *
     * @param array<string, mixed> $datos Datos crudos del formulario.
     * @param int $usuarioId Solicitante (siempre el de la sesión).
     * @return int Id de la reserva creada.
     * @throws ReservaInvalidaException Si alguna regla no se cumple.
     * @throws SolapamientoException Si el horario ya está ocupado.
     */
    public function crear(array $datos, int $usuarioId): int
    {
        // Libera primero las reservas no recogidas ya vencidas para que
        // la disponibilidad se calcule sobre el estado real.
        $this->marcarVencidas();

        $fecha    = trim((string)($datos['fecha'] ?? ''));
        $inicio   = trim((string)($datos['hora_inicio'] ?? ''));
        $duracion = (int)($datos['duracion'] ?? 0);
        $equipoId = (int)($datos['equipo_id'] ?? 0);
        $motivo   = trim((string)($datos['motivo'] ?? ''));
        $horaFin  = '';

        // Comprobación explícita de la duración para dar el mensaje
        // exacto antes de calcular la hora de fin.
        if ($duracion < 1 || $duracion > MAX_HORAS_PRESTAMO) {
            throw new ReservaInvalidaException(
                'La duración debe ser de 1 a ' . MAX_HORAS_PRESTAMO . ' horas.'
            );
        }

        $this->validarFranja($fecha, $inicio, $this->calcularFin($inicio, $duracion), true);
        $horaFin = $this->calcularFin($inicio, $duracion);

        if (mb_strlen($motivo) > 255) {
            throw new ReservaInvalidaException('El motivo no puede superar los 255 caracteres.');
        }

        $equipo = $this->equipos->buscarPorId($equipoId);
        if ($equipo === null) {
            throw new ReservaInvalidaException('El equipo seleccionado no existe.');
        }
        if (!in_array($equipo['estado'], ['disponible', 'prestado'], true)) {
            $etiqueta = match ((string)$equipo['estado']) {
                'mantenimiento' => 'en mantenimiento',
                'baja'          => 'dado de baja',
                default         => (string)$equipo['estado'],
            };
            throw new ReservaInvalidaException(
                "El equipo {$equipo['codigo']} está {$etiqueta} y no se puede reservar."
            );
        }

        // Ocupación del equipo: activa o prestamo (incluye "ayer no recogido"
        // ya validado por marcarVencidas()).
        $conflicto = $this->reservas->buscarSolapamiento(
            $equipoId,
            $fecha,
            $inicio . ':00',
            $horaFin . ':00'
        );
        if ($conflicto !== null) {
            throw new SolapamientoException(
                (string)$conflicto['equipo_codigo'],
                substr((string)$conflicto['hora_inicio'], 0, 5),
                substr((string)$conflicto['hora_fin'], 0, 5)
        );
        }

        // Un usuario no puede usar dos portátiles en horarios cruzados.
        if ($this->reservas->buscarSolapamientoPorUsuario($usuarioId, $fecha, $inicio . ':00', $horaFin . ':00') !== null) {
            throw new ReservaInvalidaException(
                "Ya tienes una reserva que se superpone a ese horario (del {$fecha} de {$inicio} a {$horaFin}). "
                . 'Cancela la anterior o elige otra franja.'
            );
        }

        $id = $this->reservas->crear([
            'usuario_id'  => $usuarioId,
            'equipo_id'   => $equipoId,
            'fecha'       => $fecha,
            'hora_inicio' => $inicio . ':00',
            'hora_fin'    => $horaFin . ':00',
            'estado'      => 'activa',
            'motivo'      => $motivo !== '' ? $motivo : null,
        ]);

        $this->log->info(
            "Creó la reserva #{$id}: equipo {$equipo['codigo']} el {$fecha} "
            . "de {$inicio} a {$horaFin}."
        );
        return $id;
    }

    /**
     * Cancela una reserva (el estudiante la suya; el administrador, cualquiera).
     *
     * @param int $id Reserva a cancelar.
     * @param int $actorId Usuario de la sesión.
     * @param bool $esAdmin true si el actor es administrador.
     * @return void
     * @throws ReservaInvalidaException Si no existe o no está activa.
     * @throws AccesoDenegadoException Si intenta cancelar una ajena.
     */
    public function cancelar(int $id, int $actorId, bool $esAdmin): void
    {
        $this->marcarVencidas();
        $reserva = $this->obtener($id);

        $esDueno = (int)$reserva['usuario_id'] === $actorId;
        if (!$esDueno && !$esAdmin) {
            $this->log->advertencia(
                "Intento de acceso no autorizado: '" . ($_SESSION['usuario']['correo'] ?? 'invitado')
                . "' intentó cancelar la reserva #{$id} de '{$reserva['usuario_nombre']}'."
            );
            throw new AccesoDenegadoException('Solo puedes cancelar tus propias reservas.');
        }

        if ($reserva['estado'] !== 'activa') {
            throw new ReservaInvalidaException(match ((string)$reserva['estado']) {
                'prestamo'  => 'El equipo ya fue entregado; el administrador debe registrar la devolución.',
                'entregada' => 'La reserva ya fue entregada y no se puede cancelar.',
                'vencida'   => 'La reserva venció sin recogerse y ya no se puede cancelar.',
                'cancelada' => 'La reserva ya estaba cancelada.',
                default     => 'La reserva no se puede cancelar.',
            });
        }

        $this->reservas->cambiarEstado($id, 'cancelada');
        $this->log->info(
            "Canceló la reserva #{$id} ({$reserva['equipo_codigo']}, {$reserva['fecha']} "
            . substr((string)$reserva['hora_inicio'], 0, 5) . ' a ' . substr((string)$reserva['hora_fin'], 0, 5) . ').'
        );
    }

    /**
     * Registra la entrega física del equipo al estudiante (solo admin).
     *
     * Se permite mientras la reserva siga "activa" (no vencida): el
     * personal de la biblioteca decide el momento exacto de entrega.
     * El equipo pasa a estado "prestado".
     *
     * @param int $id Reserva.
     * @return void
     * @throws ReservaInvalidaException Si la reserva no está activa o no existe.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function registrarEntrega(int $id): void
    {
        $this->requerirAdministrador('registrar entregas de equipos');
        $this->marcarVencidas();
        $reserva = $this->obtener($id);

        if ($reserva['estado'] !== 'activa') {
            throw new ReservaInvalidaException(match ((string)$reserva['estado']) {
                'prestamo'  => 'El equipo ya está en préstamo; registra la devolución.',
                'entregada' => 'La reserva ya fue entregada.',
                'vencida'   => 'La reserva venció sin recogerse; no se puede entregar. '
                             . 'El estudiante debe crear una nueva reserva.',
                'cancelada' => 'La reserva está cancelada; no se puede entregar.',
                default     => 'La reserva no se puede entregar.',
            });
        }

        $this->reservas->cambiarEstado($id, 'prestamo');
        $this->equipos->cambiarEstado((int)$reserva['equipo_id'], 'prestado');
        $this->log->info(
            "Registró la entrega del equipo {$reserva['equipo_codigo']} a "
            . "'{$reserva['usuario_nombre']}' (reserva #{$id})."
        );
    }

    /**
     * Registra la devolución del equipo (solo admin): el equipo vuelve
     * a estar "disponible" para nuevas reservas.
     *
     * @param int $id Reserva.
     * @return void
     * @throws ReservaInvalidaException Si la reserva no está en préstamo.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function registrarDevolucion(int $id): void
    {
        $this->requerirAdministrador('registrar devoluciones de equipos');
        $this->marcarVencidas();
        $reserva = $this->obtener($id);

        if ($reserva['estado'] !== 'prestamo') {
            throw new ReservaInvalidaException(match ((string)$reserva['estado']) {
                'activa'    => 'La reserva aún no se ha entregado; registra la entrega primero.',
                'entregada' => 'La reserva ya fue devuelta.',
                'vencida'   => 'La reserva venció sin entregarse; no hay equipo que devolver.',
                'cancelada' => 'La reserva está cancelada; no hay equipo que devolver.',
                default     => 'La reserva no tiene un préstamo activo.',
            });
        }

        $this->reservas->cambiarEstado($id, 'entregada');

        // El equipo solo vuelve a "disponible" si seguía marcado como
        // prestado: si el administrador lo pasó a mantenimiento durante
        // el préstamo, ese estado se respeta.
        $equipo = $this->equipos->buscarPorId((int)$reserva['equipo_id']);
        if ($equipo !== null && $equipo['estado'] === 'prestado') {
            $this->equipos->cambiarEstado((int)$reserva['equipo_id'], 'disponible');
        }

        $this->log->info(
            "Registró la devolución del equipo {$reserva['equipo_codigo']} de "
            . "'{$reserva['usuario_nombre']}' (reserva #{$id})."
        );
    }

    /* ---------------------------------------------------------------
     * Auxiliares privados
     * --------------------------------------------------------------- */

    /**
     * Marca como vencidas las reservas activas cuya hora de fin pasó.
     *
     * Ejecución perezosa: se invoca antes de cada consulta relevante
     * (no depende de tareas programadas, que XAMPP no ejecuta).
     *
     * @return void
     */
    private function marcarVencidas(): void
    {
        $marcadas = $this->reservas->marcarVencidas(date('Y-m-d'), date('H:i:s'));
        if ($marcadas > 0) {
            $this->log->advertencia(
                "Se marcaron {$marcadas} reserva(s) vencida(s) sin haber recogido el equipo."
            );
        }
    }

    /**
     * Valida la fecha y la franja horaria de una reserva.
     *
     * @param string $fecha Fecha YYYY-MM-DD.
     * @param string $horaInicio Inicio HH:MM.
     * @param string $horaFin Fin HH:MM.
     * @param bool $esCreacion true = reserva nueva (exige futuro y antelación).
     * @return void
     * @throws ReservaInvalidaException Con todos los errores encontrados.
     */
    private function validarFranja(string $fecha, string $horaInicio, string $horaFin, bool $esCreacion): void
    {
        $errores = [];
        $hoy = new \DateTimeImmutable('today');

        $fechaDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($fecha === '' || $fechaDt === false || $fechaDt->format('Y-m-d') !== $fecha) {
            $errores[] = 'Selecciona una fecha válida.';
            $fechaDt = null;
        } elseif ($esCreacion) {
            if ($fechaDt < $hoy) {
                $errores[] = 'No se pueden crear reservas en fechas pasadas.';
            } elseif ($fechaDt > $hoy->modify('+' . DIAS_ANTELACION_MAX . ' days')) {
                $errores[] = 'Solo puedes reservar hasta ' . DIAS_ANTELACION_MAX . ' días de anticipación.';
            }
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaInicio)) {
            $errores[] = 'Selecciona una hora de inicio válida.';
        } else {
            [$hora] = explode(':', $horaInicio);
            if ((int)$hora < HORA_BIBLIOTECA_INICIO || (int)$hora >= HORA_BIBLIOTECA_FIN) {
                $errores[] = 'La biblioteca atiende de 6:00 a. m. a 6:00 p. m.: '
                           . 'el inicio debe estar entre 6:00 y 17:00.';
            }
            if (!preg_match('/^\d{2}:00$/', $horaInicio)) {
                $errores[] = 'Las reservas se hacen en bloques completos de una hora (minutos en punto).';
            }
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaFin)) {
            $errores[] = 'Selecciona una duración válida.';
        } else {
            if ($horaFin > sprintf('%02d:00', HORA_BIBLIOTECA_FIN)) {
                $errores[] = 'La reserva no puede terminar después de las 6:00 p. m. (18:00).';
            }
            if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $horaInicio, $i)
                && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $horaFin, $f)) {
                $duracion = ((int)$f[1] * 60 + (int)$f[2]) - ((int)$i[1] * 60 + (int)$i[2]);
                if ($duracion < 60) {
                    $errores[] = 'La duración mínima es de 1 hora.';
                } elseif ($duracion > MAX_HORAS_PRESTAMO * 60) {
                    $errores[] = 'La duración máxima es de ' . MAX_HORAS_PRESTAMO . ' horas.';
                }
            }
        }

        // Las reservas de hoy deben comenzar en la hora actual o más tarde
        // (nunca se reserva un bloque que ya empezó hace rato; con inicio
        // en la hora en punto, la hora de fin siempre queda en el futuro).
        if ($esCreacion && $fechaDt !== null && $fechaDt->format('Y-m-d') === date('Y-m-d')) {
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaInicio)
                && $horaInicio < date('H:00')) {
                $errores[] = 'Para reservas de hoy, la hora de inicio (' . $horaInicio
                           . ') no puede ser anterior a la hora actual (' . date('H:00') . ').';
            }
        }

        if ($errores !== []) {
            throw new ReservaInvalidaException($errores);
        }
    }

    /**
     * Calcula la hora de fin a partir del inicio y la duración.
     *
     * @param string $horaInicio Inicio HH:MM (en punto).
     * @param int $duracionHoras Duración en horas.
     * @return string Fin HH:MM ('' si el inicio no es válido).
     */
    private function calcularFin(string $horaInicio, int $duracionHoras): string
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaInicio)) {
            return '';
        }
        if ($duracionHoras < 1 || $duracionHoras > MAX_HORAS_PRESTAMO) {
            return '';
        }
        return date('H:i', strtotime($horaInicio) + $duracionHoras * 3600);
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
        if (($_SESSION['usuario']['rol_id'] ?? 0) !== 1) {
            $this->log->advertencia(
                "Intento de acceso no autorizado: '"
                . ($_SESSION['usuario']['correo'] ?? 'invitado')
                . "' intentó {$accion}."
            );
            throw new AccesoDenegadoException('Tu rol no tiene permiso para realizar esta operación.');
        }
    }
}
