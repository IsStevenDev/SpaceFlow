<?php
/**
 * SpaceFlow — Repositorio de usuarios (CRUD sobre MySQL).
 *
 * Única capa que conoce SQL para la tabla usuarios. Las consultas se
 * ejecutan siempre mediante prepared statements de PDO.
 *
 * @package SpaceFlow\Repositories
 */

declare(strict_types=1);

namespace App\Repositories;

class UsuarioRepository extends RepositorioBase
{
    /** SQL base con las uniones de catálogo que necesita la presentación. */
    private const SQL_BASE =
        'SELECT u.id, u.nombre, u.correo, u.password_hash, u.rol_id,
                r.nombre AS rol_nombre, u.tipo_documento, u.numero_documento,
                u.programa_id, p.nombre AS programa_nombre, u.estado,
                u.fecha_creacion
           FROM usuarios u
           INNER JOIN roles r ON r.id = u.rol_id
      LEFT JOIN programas p ON p.id = u.programa_id';

    /** {@inheritdoc} */
    protected function tabla(): string
    {
        return 'usuarios';
    }

    /**
     * Lista usuarios aplicando búsqueda, filtros y paginación.
     *
     * @param string $busqueda    Texto parcial de nombre o correo.
     * @param int|null $rolId     Filtro por rol (null = todos).
     * @param string $estado      'activo', 'inactivo' o '' (todos).
     * @param int $limite         Tamaño de página.
     * @param int $desplazamiento Registros a saltar (offset).
     * @return array<int, array<string, mixed>>
     */
    public function listar(
        string $busqueda = '',
        ?int $rolId = null,
        string $estado = '',
        int $limite = 10,
        int $desplazamiento = 0
    ): array {
        [$where, $parametros] = $this->condiciones($busqueda, $rolId, $estado);

        $sql = self::SQL_BASE . $where
            . ' ORDER BY u.fecha_creacion DESC'
            . ' LIMIT :limite OFFSET :desplazamiento';

        $consulta = $this->conexion->prepare($sql);
        // Los límites se envían como enteros (bindValue con tipo entero).
        $consulta->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $consulta->bindValue(':desplazamiento', $desplazamiento, \PDO::PARAM_INT);
        foreach ($parametros as $clave => $valor) {
            $consulta->bindValue($clave, $valor);
        }
        $consulta->execute();
        return $consulta->fetchAll();
    }

    /**
     * Cuenta los usuarios que cumplen los mismos filtros que listar().
     *
     * @return int
     */
    public function contarFiltrados(string $busqueda = '', ?int $rolId = null, string $estado = ''): int
    {
        [$where, $parametros] = $this->condiciones($busqueda, $rolId, $estado);

        $sql = 'SELECT COUNT(*) FROM usuarios u
                  INNER JOIN roles r ON r.id = u.rol_id'
             . ' LEFT JOIN programas p ON p.id = u.programa_id'
             . $where;

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return (int)$consulta->fetchColumn();
    }

    /**
     * Construye la cláusula WHERE y sus parámetros para listado/conteo.
     *
     * @return array{0: string, 1: array<string, string|int>} WHERE y parámetros.
     */
    private function condiciones(string $busqueda, ?int $rolId, string $estado): array
    {
        $fragmentos = [];
        $parametros = [];

        if ($busqueda !== '') {
            // Con consultas preparadas nativas cada marcador debe ser
            // único: se usa uno distinto para nombre y para correo.
            $fragmentos[] = '(u.nombre LIKE :busqueda_nombre OR u.correo LIKE :busqueda_correo)';
            $parametros[':busqueda_nombre'] = '%' . $busqueda . '%';
            $parametros[':busqueda_correo'] = '%' . $busqueda . '%';
        }
        if ($rolId !== null) {
            $fragmentos[] = 'u.rol_id = :rol_id';
            $parametros[':rol_id'] = $rolId;
        }
        if ($estado !== '') {
            $fragmentos[] = 'u.estado = :estado';
            $parametros[':estado'] = $estado;
        }

        $where = $fragmentos === [] ? '' : ' WHERE ' . implode(' AND ', $fragmentos);
        return [$where, $parametros];
    }

    /**
     * Busca un usuario por su correo electrónico.
     *
     * @param string $correo Correo institucional.
     * @return array<string, mixed>|null
     */
    public function buscarPorCorreo(string $correo): ?array
    {
        $sql = self::SQL_BASE . ' WHERE u.correo = :correo';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':correo' => strtolower($correo)]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $fila;
    }

    /**
     * Busca un usuario por id, incluyendo la unión con catálogos.
     *
     * @param int $id Identificador.
     * @return array<string, mixed>|null
     */
    public function buscarCompleto(int $id): ?array
    {
        $sql = self::SQL_BASE . ' WHERE u.id = :id';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':id' => $id]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $fila;
    }

    /**
     * Indica si ya existe una cuenta con el correo indicado.
     *
     * @param string $correo Correo a verificar.
     * @param int|null $excluirId Id a ignorar (para ediciones).
     * @return bool
     */
    public function correoExiste(string $correo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE correo = :correo';
        $parametros = [':correo' => strtolower($correo)];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $parametros[':excluir_id'] = $excluirId;
        }
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return (int)$consulta->fetchColumn() > 0;
    }

    /**
     * Indica si ya existe un usuario con ese número de documento.
     *
     * @param string $numero Documento.
     * @param int|null $excluirId Id a ignorar (para ediciones).
     * @return bool
     */
    public function documentoExiste(string $numero, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE numero_documento = :numero';
        $parametros = [':numero' => $numero];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $parametros[':excluir_id'] = $excluirId;
        }
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return (int)$consulta->fetchColumn() > 0;
    }

    /**
     * Inserta un usuario y devuelve el id generado.
     *
     * @param array{nombre:string, correo:string, password_hash:string, rol_id:int,
     *              tipo_documento:string, numero_documento:string,
     *              programa_id:int|null, estado:string} $datos
     * @return int Id insertado.
     */
    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO usuarios
                    (nombre, correo, password_hash, rol_id, tipo_documento,
                     numero_documento, programa_id, estado)
                VALUES
                    (:nombre, :correo, :password_hash, :rol_id, :tipo_documento,
                     :numero_documento, :programa_id, :estado)';

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':nombre'           => $datos['nombre'],
            ':correo'           => $datos['correo'],
            ':password_hash'    => $datos['password_hash'],
            ':rol_id'           => $datos['rol_id'],
            ':tipo_documento'   => $datos['tipo_documento'],
            ':numero_documento' => $datos['numero_documento'],
            ':programa_id'      => $datos['programa_id'],
            ':estado'           => $datos['estado'],
        ]);

        return (int)$this->conexion->lastInsertId();
    }

    /**
     * Actualiza los datos de un usuario (sin contraseña).
     *
     * @param int $id Identificador.
     * @param array<string, mixed> $datos Mismos campos que crear().
     * @return bool true si se actualizó una fila.
     */
    public function actualizar(int $id, array $datos): bool
    {
        $sql = 'UPDATE usuarios SET
                    nombre = :nombre, correo = :correo, rol_id = :rol_id,
                    tipo_documento = :tipo_documento,
                    numero_documento = :numero_documento,
                    programa_id = :programa_id, estado = :estado
                WHERE id = :id';

        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':nombre'           => $datos['nombre'],
            ':correo'           => $datos['correo'],
            ':rol_id'           => $datos['rol_id'],
            ':tipo_documento'   => $datos['tipo_documento'],
            ':numero_documento' => $datos['numero_documento'],
            ':programa_id'      => $datos['programa_id'],
            ':estado'           => $datos['estado'],
            ':id'               => $id,
        ]);
    }

    /**
     * Actualiza la contraseña (ya hasheada) de un usuario.
     *
     * @param int $id Identificador.
     * @param string $hash Hash generado con password_hash().
     * @return bool
     */
    public function actualizarPassword(int $id, string $hash): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE usuarios SET password_hash = :hash WHERE id = :id'
        );
        return $consulta->execute([':hash' => $hash, ':id' => $id]);
    }

    /**
     * Desactiva (borrado lógico) un usuario.
     *
     * @param int $id Identificador.
     * @return bool
     */
    public function desactivar(int $id): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE usuarios SET estado = 'inactivo' WHERE id = :id"
        );
        return $consulta->execute([':id' => $id]);
    }

    /**
     * Cuenta usuarios por rol (para el dashboard).
     *
     * @return array<int, array{rol_id:int, rol_nombre:string, cantidad:int}>
     */
    public function contarPorRol(): array
    {
        $sql = 'SELECT r.id AS rol_id, r.nombre AS rol_nombre, COUNT(u.id) AS cantidad
                  FROM roles r
             LEFT JOIN usuarios u ON u.rol_id = r.id
              GROUP BY r.id, r.nombre
              ORDER BY r.id';
        $consulta = $this->conexion->query($sql);
        return $consulta->fetchAll();
    }

    /**
     * Cuenta usuarios activos e inactivos (para el dashboard).
     *
     * @return array{activos:int, inactivos:int, total:int}
     */
    public function contarPorEstado(): array
    {
        $sql = "SELECT
                    SUM(estado = 'activo')   AS activos,
                    SUM(estado = 'inactivo') AS inactivos,
                    COUNT(*)                 AS total
                FROM usuarios";
        $fila = $this->conexion->query($sql)->fetch();
        return [
            'activos'   => (int)($fila['activos'] ?? 0),
            'inactivos' => (int)($fila['inactivos'] ?? 0),
            'total'     => (int)($fila['total'] ?? 0),
        ];
    }

    /**
     * Cuenta cuántos administradores activos existen.
     *
     * Se usa para impedir desactivar o degradar al último administrador.
     *
     * @param int|null $excluirId Id a no contar.
     * @return int
     */
    public function contarAdministradoresActivos(?int $excluirId = null): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios
                 WHERE rol_id = 1 AND estado = 'activo'";
        $parametros = [];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $parametros[':excluir_id'] = $excluirId;
        }
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return (int)$consulta->fetchColumn();
    }
}
