<?php
/**
 * SpaceFlow — Repositorio de reservas.
 *
 * Gestiona los préstamos de portátiles. La consulta estrella es
 * buscarSolapamiento(): detecta si un equipo ya tiene una reserva
 * activa que intersecte con un horario dado. Es la base de la
 * garantía de que dos estudiantes nunca reciben el mismo portátil
 * en el mismo bloque horario.
 *
 * @package SpaceFlow\Repositories
 */

declare(strict_types=1);

namespace App\Repositories;

class ReservaRepository extends RepositorioBase
{
    /** SQL base con los JOIN de presentación (usuario + equipo). */
    private const SQL_BASE =
        'SELECT r.id, r.usuario_id, u.nombre AS usuario_nombre,
                r.equipo_id, e.codigo AS equipo_codigo, e.nombre AS equipo_nombre,
                r.fecha, r.hora_inicio, r.hora_fin, r.estado, r.motivo, r.fecha_creacion
           FROM reservas r
           INNER JOIN usuarios u ON u.id = r.usuario_id
           INNER JOIN equipos   e ON e.id = r.equipo_id';

    /** {@inheritdoc} */
    protected function tabla(): string
    {
        return 'reservas';
    }

    /**
     * Comprueba si un equipo ya está reservado en un rango horario.
     *
     * Nota: los estados que ocupan son 'activa' (confirmada, aún sin
     * recoger) y 'prestamo' (equipo físicamente entregado).
     *
     * Regla de solapamiento de intervalos: dos bloques se pisan si
     * (inicio_nuevo < fin_existente) AND (fin_nuevo > inicio_existente).
     * Las reservas "adyacentes" (10:00-12:00 y 12:00-14:00) NO se pisan.
     *
     * @param int $equipoId Equipo consultado.
     * @param string $fecha Fecha YYYY-MM-DD.
     * @param string $horaInicio Inicio HH:MM:SS propuesto.
     * @param string $horaFin Fin HH:MM:SS propuesto.
     * @param int|null $excluirReservaId Reserva a ignorar (ediciones).
     * @return array<string, mixed>|null Fila en conflicto o null si está libre.
     */
    public function buscarSolapamiento(
        int $equipoId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $excluirReservaId = null
    ): ?array {
        $sql = self::SQL_BASE
             . ' WHERE r.equipo_id = :equipo_id'
             . ' AND r.fecha = :fecha'
             . ' AND r.estado IN ("activa", "prestamo")'
             . ' AND r.hora_inicio < :hora_fin'
             . ' AND r.hora_fin > :hora_inicio';

        $parametros = [
            ':equipo_id'   => $equipoId,
            ':fecha'       => $fecha,
            ':hora_inicio' => $horaInicio,
            ':hora_fin'    => $horaFin,
        ];
        if ($excluirReservaId !== null) {
            $sql .= ' AND r.id <> :excluir_id';
            $parametros[':excluir_id'] = $excluirReservaId;
        }

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        $fila = $consulta->fetch();
        return $fila === false ? null : $fila;
    }

    /**
     * Devuelve los ids de los equipos ocupados en un rango horario.
     *
     * Alimenta el selector del formulario de reserva: solo se muestran
     * los portátiles realmente libres para la franja pedida.
     *
     * @param string $fecha Fecha YYYY-MM-DD.
     * @param string $horaInicio Inicio HH:MM:SS.
     * @param string $horaFin Fin HH:MM:SS.
     * @return array<int, int> Ids de equipos ocupados.
     */
    public function equiposOcupados(string $fecha, string $horaInicio, string $horaFin): array
    {
        $sql = 'SELECT DISTINCT equipo_id FROM reservas
                 WHERE fecha = :fecha
                   AND estado IN ("activa", "prestamo")
                   AND hora_inicio < :hora_fin
                   AND hora_fin > :hora_inicio';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':fecha'       => $fecha,
            ':hora_inicio' => $horaInicio,
            ':hora_fin'    => $horaFin,
        ]);
        return array_map('intval', $consulta->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Comprueba si un USUARIO ya tiene una reserva que se superponga.
     *
     * Un estudiante no puede usar dos portátiles en horarios cruzados.
     * Mismos estados ocupan que en buscarSolapamiento().
     *
     * @param int $usuarioId Usuario consultado.
     * @param string $fecha Fecha YYYY-MM-DD.
     * @param string $horaInicio Inicio HH:MM:SS propuesto.
     * @param string $horaFin Fin HH:MM:SS propuesto.
     * @return array<string, mixed>|null Fila en conflicto o null.
     */
    public function buscarSolapamientoPorUsuario(
        int $usuarioId,
        string $fecha,
        string $horaInicio,
        string $horaFin
    ): ?array {
        $sql = self::SQL_BASE
             . ' WHERE r.usuario_id = :usuario_id'
             . ' AND r.fecha = :fecha'
             . ' AND r.estado IN ("activa", "prestamo")'
             . ' AND r.hora_inicio < :hora_fin'
             . ' AND r.hora_fin > :hora_inicio';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':usuario_id'  => $usuarioId,
            ':fecha'       => $fecha,
            ':hora_inicio' => $horaInicio,
            ':hora_fin'    => $horaFin,
        ]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $fila;
    }

    /**
     * Lista reservas con búsqueda, filtros y paginación.
     *
     * @param int|null $usuarioId Filtra por solicitante (null = todos).
     * @param string $estado Filtra por estado ('' = todos).
     * @param string $fecha Filtra por fecha YYYY-MM-DD ('' = todas).
     * @param int $limite Tamaño de página.
     * @param int $desplazamiento Offset.
     * @param string $busqueda Texto parcial de usuario o de equipo.
     * @return array<int, array<string, mixed>>
     */
    public function listar(
        ?int $usuarioId = null,
        string $estado = '',
        string $fecha = '',
        int $limite = 10,
        int $desplazamiento = 0,
        string $busqueda = ''
    ): array {
        [$where, $parametros] = $this->condiciones($usuarioId, $estado, $fecha, $busqueda);

        $sql = self::SQL_BASE . $where
             . ' ORDER BY r.fecha DESC, r.hora_inicio DESC'
             . ' LIMIT :limite OFFSET :desplazamiento';

        $consulta = $this->conexion->prepare($sql);
        $consulta->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $consulta->bindValue(':desplazamiento', $desplazamiento, \PDO::PARAM_INT);
        foreach ($parametros as $clave => $valor) {
            $consulta->bindValue($clave, $valor);
        }
        $consulta->execute();
        return $consulta->fetchAll();
    }

    /**
     * Cuenta reservas que cumplen los mismos filtros que listar().
     *
     * @param string $busqueda Texto parcial de usuario o de equipo.
     * @return int
     */
    public function contarFiltrados(
        ?int $usuarioId = null,
        string $estado = '',
        string $fecha = '',
        string $busqueda = ''
    ): int {
        [$where, $parametros] = $this->condiciones($usuarioId, $estado, $fecha, $busqueda);
        $sql = 'SELECT COUNT(*) FROM reservas r
                  INNER JOIN usuarios u ON u.id = r.usuario_id
                  INNER JOIN equipos   e ON e.id = r.equipo_id' . $where;
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return (int)$consulta->fetchColumn();
    }

    /**
     * Construye WHERE y parámetros para listado/conteo.
     *
     * @return array{0: string, 1: array<string, string|int>} WHERE y parámetros.
     */
    private function condiciones(?int $usuarioId, string $estado, string $fecha, string $busqueda = ''): array
    {
        $fragmentos = [];
        $parametros = [];

        if ($usuarioId !== null) {
            $fragmentos[] = 'r.usuario_id = :usuario_id';
            $parametros[':usuario_id'] = $usuarioId;
        }
        if ($estado !== '') {
            $fragmentos[] = 'r.estado = :estado';
            $parametros[':estado'] = $estado;
        }
        if ($fecha !== '') {
            $fragmentos[] = 'r.fecha = :fecha';
            $parametros[':fecha'] = $fecha;
        }
        if ($busqueda !== '') {
            // Marcadores únicos: las consultas nativas no repiten nombres.
            $fragmentos[] = '(u.nombre LIKE :busq_usuario OR u.correo LIKE :busq_correo'
                          . ' OR e.codigo LIKE :busq_codigo OR e.nombre LIKE :busq_equipo)';
            $parametros[':busq_usuario'] = '%' . $busqueda . '%';
            $parametros[':busq_correo']  = '%' . $busqueda . '%';
            $parametros[':busq_codigo']  = '%' . $busqueda . '%';
            $parametros[':busq_equipo']  = '%' . $busqueda . '%';
        }

        $where = $fragmentos === [] ? '' : ' WHERE ' . implode(' AND ', $fragmentos);
        return [$where, $parametros];
    }

    /**
     * Busca una reserva por id con sus JOIN.
     *
     * @param int $id Identificador.
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = self::SQL_BASE . ' WHERE r.id = :id';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':id' => $id]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $fila;
    }

    /**
     * Cuenta cuántas reservas (de cualquier estado) tiene un equipo.
     *
     * Se usa para decidir si un equipo puede eliminarse físicamente
     * o solo darse de baja (conservando el historial).
     *
     * @param int $equipoId Equipo.
     * @return int
     */
    public function contarPorEquipo(int $equipoId): int
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM reservas WHERE equipo_id = :equipo_id');
        $consulta->execute([':equipo_id' => $equipoId]);
        return (int)$consulta->fetchColumn();
    }

    /**
     * Inserta una reserva y devuelve el id generado.
     *
     * @param array{usuario_id:int, equipo_id:int, fecha:string,
     *              hora_inicio:string, hora_fin:string,
     *              estado:string, motivo:string|null} $datos
     * @return int Id insertado.
     */
    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO reservas
                    (usuario_id, equipo_id, fecha, hora_inicio, hora_fin, estado, motivo)
                VALUES
                    (:usuario_id, :equipo_id, :fecha, :hora_inicio, :hora_fin, :estado, :motivo)';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':usuario_id'  => $datos['usuario_id'],
            ':equipo_id'   => $datos['equipo_id'],
            ':fecha'       => $datos['fecha'],
            ':hora_inicio' => $datos['hora_inicio'],
            ':hora_fin'    => $datos['hora_fin'],
            ':estado'      => $datos['estado'],
            ':motivo'      => $datos['motivo'],
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    /**
     * Cambia el estado de una reserva (cancelar, entregar, recoger...).
     *
     * @param int $id Identificador.
     * @param string $estado Nuevo estado.
     * @return bool
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        $consulta = $this->conexion->prepare('UPDATE reservas SET estado = :estado WHERE id = :id');
        return $consulta->execute([':estado' => $estado, ':id' => $id]);
    }

    /**
     * Marca como vencidas las reservas activas cuya hora de fin ya pasó.
     *
     * Se ejecuta de forma perezosa (en cada consulta del panel) para no
     * depender de tareas programadas, que XAMPP no ejecuta por defecto.
     *
     * @param string $fechaActual Fecha de hoy YYYY-MM-DD.
     * @param string $horaActual Hora actual HH:MM:SS.
     * @return int Cantidad de reservas marcadas.
     */
    public function marcarVencidas(string $fechaActual, string $horaActual): int
    {
        // Marcadores únicos: con prepares nativos de PDO no se puede
        // repetir un mismo nombre en una sentencia (HY093).
        $sql = "UPDATE reservas
                   SET estado = 'vencida'
                 WHERE estado = 'activa'
                   AND (fecha < :fecha_menor OR (fecha = :fecha_igual AND hora_fin <= :hora))";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':fecha_menor' => $fechaActual,
            ':fecha_igual' => $fechaActual,
            ':hora'        => $horaActual,
        ]);
        return $consulta->rowCount();
    }

    /**
     * Reservas que terminan dentro de los próximos N minutos (avisos).
     *
     * @param int $minutos Horizonte de aviso en minutos.
     * @param string $fechaActual Fecha de hoy YYYY-MM-DD.
     * @param string $horaActual Hora actual HH:MM:SS.
     * @return array<int, array<string, mixed>>
     */
    public function proximasAVencer(int $minutos, string $fechaActual, string $horaActual): array
    {
        // Nombres de marcador únicos (preparados nativos de PDO).
        $sql = self::SQL_BASE
             . " WHERE r.estado IN ('activa', 'prestamo')"
             . ' AND r.fecha = :fecha'
             . ' AND r.hora_fin > :hora_minima'
             . ' AND r.hora_fin <= ADDTIME(:hora_adtime, CONCAT(:minutos, ":00"))'
             . ' ORDER BY r.hora_fin';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':fecha'       => $fechaActual,
            ':hora_minima' => $horaActual,
            ':hora_adtime' => $horaActual,
            ':minutos'     => $minutos,
        ]);
        return $consulta->fetchAll();
    }

    /**
     * Cuenta reservas por estado (para el dashboard del administrador).
     *
     * @return array{activas:int, prestamo:int, entregadas:int, canceladas:int, vencidas:int, total:int}
     */
    public function contarPorEstado(): array
    {
        $sql = "SELECT
                    SUM(estado = 'activa')    AS activas,
                    SUM(estado = 'prestamo')  AS prestamo,
                    SUM(estado = 'entregada') AS entregadas,
                    SUM(estado = 'cancelada') AS canceladas,
                    SUM(estado = 'vencida')   AS vencidas,
                    COUNT(*)                  AS total
                FROM reservas";
        $fila = $this->conexion->query($sql)->fetch();
        return [
            'activas'    => (int)($fila['activas'] ?? 0),
            'prestamo'   => (int)($fila['prestamo'] ?? 0),
            'entregadas' => (int)($fila['entregadas'] ?? 0),
            'canceladas' => (int)($fila['canceladas'] ?? 0),
            'vencidas'   => (int)($fila['vencidas'] ?? 0),
            'total'      => (int)($fila['total'] ?? 0),
        ];
    }

    /**
     * Próximas reservas (hoy en adelante) de un usuario, activas o en préstamo.
     *
     * @param int $usuarioId Usuario.
     * @param int $limite Cantidad máxima de filas.
     * @return array<int, array<string, mixed>>
     */
    public function proximasDeUsuario(int $usuarioId, int $limite = 4): array
    {
        $sql = self::SQL_BASE
             . " WHERE r.usuario_id = :usuario_id"
             . " AND r.estado IN ('activa', 'prestamo')"
             . ' AND r.fecha >= :hoy'
             . ' ORDER BY r.fecha, r.hora_inicio'
             . ' LIMIT :limite';
        $consulta = $this->conexion->prepare($sql);
        $consulta->bindValue(':usuario_id', $usuarioId, \PDO::PARAM_INT);
        $consulta->bindValue(':hoy', date('Y-m-d'));
        $consulta->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    /**
     * Reservas activas o en préstamo de HOY de un usuario.
     *
     * Alimenta las notificaciones de cuenta regresiva del estudiante.
     *
     * @param int $usuarioId Usuario.
     * @return array<int, array<string, mixed>>
     */
    public function activasHoyDeUsuario(int $usuarioId): array
    {
        $sql = self::SQL_BASE
             . " WHERE r.usuario_id = :usuario_id"
             . " AND r.estado IN ('activa', 'prestamo')"
             . ' AND r.fecha = :hoy'
             . ' ORDER BY r.hora_fin';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':usuario_id' => $usuarioId, ':hoy' => date('Y-m-d')]);
        return $consulta->fetchAll();
    }

    /**
     * Préstamos que ya pasaron de la hora de fin sin devolverse.
     *
     * El administrador debe regularizar estas entregas: el equipo sigue
     * físicamente con el estudiante aunque la hora haya concluido.
     *
     * @param string $fechaActual Fecha de hoy YYYY-MM-DD.
     * @param string $horaActual Hora actual HH:MM:SS.
     * @return array<int, array<string, mixed>>
     */
    public function prestamosAtrasados(string $fechaActual, string $horaActual): array
    {
        // Nombres de marcador únicos (preparados nativos de PDO).
        $sql = self::SQL_BASE
             . " WHERE r.estado = 'prestamo'"
             . ' AND (r.fecha < :fecha_menor OR (r.fecha = :fecha_igual AND r.hora_fin <= :hora))'
             . ' ORDER BY r.fecha, r.hora_fin';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':fecha_menor' => $fechaActual,
            ':fecha_igual' => $fechaActual,
            ':hora'        => $horaActual,
        ]);
        return $consulta->fetchAll();
    }

    /**
     * Cuenta las reservas activas/en préstamo de una fecha.
     *
     * @param string $fecha Fecha YYYY-MM-DD.
     * @return int
     */
    public function contarActivasHoy(string $fecha): int
    {
        $sql = "SELECT COUNT(*) FROM reservas
                 WHERE fecha = :fecha AND estado IN ('activa', 'prestamo')";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':fecha' => $fecha]);
        return (int)$consulta->fetchColumn();
    }
}
