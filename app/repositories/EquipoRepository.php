<?php
/**
 * SpaceFlow — Repositorio de equipos (CRUD sobre MySQL).
 *
 * Única capa que conoce SQL para la tabla equipos. Todas las consultas
 * con datos variables usan sentencias preparadas de PDO.
 *
 * @package SpaceFlow\Repositories
 */

declare(strict_types=1);

namespace App\Repositories;

class EquipoRepository extends RepositorioBase
{
    /** {@inheritdoc} */
    protected function tabla(): string
    {
        return 'equipos';
    }

    /**
     * Lista equipos aplicando búsqueda, filtro de estado y paginación.
     *
     * @param string $busqueda    Texto parcial de código, nombre o marca.
     * @param string $estado      'disponible' | 'prestado' | 'mantenimiento' | 'baja' | '' (todos).
     * @param int $limite         Tamaño de página.
     * @param int $desplazamiento Registros a saltar (offset).
     * @return array<int, array<string, mixed>>
     */
    public function listar(string $busqueda = '', string $estado = '', int $limite = 10, int $desplazamiento = 0): array
    {
        [$where, $parametros] = $this->condiciones($busqueda, $estado);

        $sql = 'SELECT * FROM equipos' . $where
             . ' ORDER BY estado = "disponible" DESC, codigo'
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
     * Cuenta los equipos que cumplen los mismos filtros que listar().
     *
     * @return int
     */
    public function contarFiltrados(string $busqueda = '', string $estado = ''): int
    {
        [$where, $parametros] = $this->condiciones($busqueda, $estado);
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM equipos' . $where);
        $consulta->execute($parametros);
        return (int)$consulta->fetchColumn();
    }

    /**
     * Construye la cláusula WHERE y sus parámetros para listado/conteo.
     *
     * @return array{0: string, 1: array<string, string>} WHERE y parámetros.
     */
    private function condiciones(string $busqueda, string $estado): array
    {
        $fragmentos = [];
        $parametros = [];

        if ($busqueda !== '') {
            // Marcadores únicos: las consultas nativas no repiten nombres.
            $fragmentos[] = '(codigo LIKE :busq_codigo OR nombre LIKE :busq_nombre OR marca LIKE :busq_marca)';
            $parametros[':busq_codigo'] = '%' . $busqueda . '%';
            $parametros[':busq_nombre'] = '%' . $busqueda . '%';
            $parametros[':busq_marca']  = '%' . $busqueda . '%';
        }
        if ($estado !== '') {
            $fragmentos[] = 'estado = :estado';
            $parametros[':estado'] = $estado;
        }

        $where = $fragmentos === [] ? '' : ' WHERE ' . implode(' AND ', $fragmentos);
        return [$where, $parametros];
    }

    /**
     * Lista solo los equipos que pueden entrar en el ciclo de reservas
     * (disponibles y prestados). Excluye mantenimiento y baja.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarReservables(): array
    {
        $consulta = $this->conexion->query(
            "SELECT id, codigo, nombre, marca, estado
               FROM equipos
              WHERE estado IN ('disponible', 'prestado')
              ORDER BY codigo"
        );
        return $consulta->fetchAll();
    }

    /**
     * Busca un equipo por id.
     *
     * @param int $id Identificador.
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        $fila = $this->buscarPorIdGenerico($id);
        return $fila;
    }

    /**
     * Busca un equipo por su código (ej. PORT-001).
     *
     * @param string $codigo Código del equipo.
     * @return array<string, mixed>|null
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM equipos WHERE codigo = :codigo');
        $consulta->execute([':codigo' => strtoupper(trim($codigo))]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $fila;
    }

    /**
     * Indica si ya existe un equipo con ese código.
     *
     * @param string $codigo Código a verificar.
     * @param int|null $excluirId Id a ignorar (para ediciones).
     * @return bool
     */
    public function codigoExiste(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM equipos WHERE codigo = :codigo';
        $parametros = [':codigo' => strtoupper(trim($codigo))];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $parametros[':excluir_id'] = $excluirId;
        }
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return (int)$consulta->fetchColumn() > 0;
    }

    /**
     * Inserta un equipo y devuelve el id generado.
     *
     * @param array{codigo:string, nombre:string, marca:string|null, estado:string} $datos
     * @return int Id insertado.
     */
    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO equipos (codigo, nombre, marca, estado)
                VALUES (:codigo, :nombre, :marca, :estado)';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':codigo' => $datos['codigo'],
            ':nombre' => $datos['nombre'],
            ':marca'  => $datos['marca'],
            ':estado' => $datos['estado'],
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    /**
     * Actualiza los datos de un equipo.
     *
     * @param int $id Identificador.
     * @param array<string, mixed> $datos Mismos campos que crear().
     * @return bool true si se actualizó una fila.
     */
    public function actualizar(int $id, array $datos): bool
    {
        $sql = 'UPDATE equipos SET
                    codigo = :codigo, nombre = :nombre,
                    marca = :marca, estado = :estado
                WHERE id = :id';
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':codigo' => $datos['codigo'],
            ':nombre' => $datos['nombre'],
            ':marca'  => $datos['marca'],
            ':estado' => $datos['estado'],
            ':id'     => $id,
        ]);
    }

    /**
     * Cambia únicamente el estado de un equipo (entrega/devolución).
     *
     * @param int $id Identificador.
     * @param string $estado Nuevo estado.
     * @return bool
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        $consulta = $this->conexion->prepare('UPDATE equipos SET estado = :estado WHERE id = :id');
        return $consulta->execute([':estado' => $estado, ':id' => $id]);
    }

    /**
     * Elimina físicamente un equipo (solo si no tiene reservas; la FK
     * RESTRICT lo impediría, pero se valida antes para dar un mensaje amigable).
     *
     * @param int $id Identificador.
     * @return bool
     */
    public function eliminar(int $id): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM equipos WHERE id = :id');
        return $consulta->execute([':id' => $id]);
    }

    /**
     * Cuenta equipos por estado (para el dashboard).
     *
     * @return array{disponibles:int, prestados:int, mantenimiento:int, baja:int, total:int}
     */
    public function contarPorEstado(): array
    {
        $sql = "SELECT
                    SUM(estado = 'disponible')    AS disponibles,
                    SUM(estado = 'prestado')      AS prestados,
                    SUM(estado = 'mantenimiento') AS mantenimiento,
                    SUM(estado = 'baja')          AS baja,
                    COUNT(*)                      AS total
                FROM equipos";
        $fila = $this->conexion->query($sql)->fetch();
        return [
            'disponibles'   => (int)($fila['disponibles'] ?? 0),
            'prestados'     => (int)($fila['prestados'] ?? 0),
            'mantenimiento' => (int)($fila['mantenimiento'] ?? 0),
            'baja'          => (int)($fila['baja'] ?? 0),
            'total'         => (int)($fila['total'] ?? 0),
        ];
    }

    /**
     * Búsqueda genérica por id (reutiliza el patrón de RepositorioBase).
     *
     * @param int $id Identificador.
     * @return array<string, mixed>|null
     */
    private function buscarPorIdGenerico(int $id): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM equipos WHERE id = :id');
        $consulta->execute([':id' => $id]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $fila;
    }
}
